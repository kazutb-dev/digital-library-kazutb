<?php

namespace App\Console\Commands;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class BackfillMarcUdc extends Command
{
    protected $signature = 'marc:backfill-udc
        {--host=marc-restore-sqlserver}
        {--port=1433}
        {--database=marc_current_2026}
        {--username=sa}
        {--chunk=500}
        {--dry-run}';

    protected $description = 'Backfill imported records with detailed MARC 090a UDC, falling back to 080a';

    public function handle(): int
    {
        try {
            $source = $this->connect();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $cursor = 0;
        $seen = 0;
        $changed = 0;
        $chunk = max(1, min(1000, (int) $this->option('chunk')));

        while (true) {
            $sql = sprintf(<<<'SQL'
                SELECT TOP %d
                    DOC_ID,
                    dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '090', 'a', 1) AS detailed_udc,
                    dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '080', 'a', 1) AS broad_udc
                FROM dbo.DOC
                WHERE DOC_ID > %d
                ORDER BY DOC_ID
                SQL, $chunk, $cursor);
            $rows = $source->query($sql)->fetchAll();
            if ($rows === []) {
                break;
            }

            DB::transaction(function () use ($rows, &$changed): void {
                foreach ($rows as $row) {
                    $sourceDocId = (int) $row['DOC_ID'];
                    $udc = $this->clean($row['detailed_udc'] ?? null)
                        ?? $this->clean($row['broad_udc'] ?? null);
                    $recordId = DB::table('marc_import_records')
                        ->where('source_doc_id', $sourceDocId)
                        ->value('bibliographic_record_id');

                    if ($recordId === null) {
                        continue;
                    }

                    $record = BibliographicRecord::query()->find($recordId);
                    if ($record === null || $record->udc_code === $udc) {
                        continue;
                    }

                    $changed++;
                    if (! $this->option('dry-run')) {
                        $record->udc_code = $udc;
                        $record->save();
                    }
                }
            });

            $seen += count($rows);
            $cursor = max(array_map(static fn (array $row): int => (int) $row['DOC_ID'], $rows));
            if ($seen % 1000 < $chunk) {
                $this->line(sprintf('Progress: %d DOC; changed=%d; cursor=%d.', $seen, $changed, $cursor));
            }
        }

        $this->info(sprintf(
            '%s complete: %d DOC checked, %d records %s.',
            $this->option('dry-run') ? 'Dry run' : 'Backfill',
            $seen,
            $changed,
            $this->option('dry-run') ? 'would change' : 'changed',
        ));

        return self::SUCCESS;
    }

    private function connect(): PDO
    {
        if (! in_array('dblib', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO_DBLIB is not installed.');
        }

        $password = (string) env('MARC_DB_PASSWORD', '');
        if ($password === '') {
            throw new RuntimeException('MARC_DB_PASSWORD is required.');
        }

        return new PDO(
            sprintf(
                'dblib:host=%s:%d;dbname=%s;charset=UTF-8',
                (string) $this->option('host'),
                (int) $this->option('port'),
                (string) $this->option('database'),
            ),
            (string) $this->option('username'),
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }
}
