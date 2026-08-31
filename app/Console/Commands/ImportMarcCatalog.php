<?php

namespace App\Console\Commands;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Services\Catalog\MarcAcademicFields;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class ImportMarcCatalog extends Command
{
    protected $signature = 'marc:import-catalog
        {--host=marc-restore-sqlserver : SQL Server host}
        {--port=1433 : SQL Server port}
        {--database=marc_current_2026 : SQL Server database}
        {--username=sa : SQL Server username}
        {--chunk=500 : DOC records per PostgreSQL transaction}
        {--after=0 : Only process source DOC_ID values above this value}
        {--limit=0 : Stop after this many DOC records (zero means all)}
        {--dry-run : Read and transform source rows without writing PostgreSQL}';

    protected $description = 'Import the restored MARC DOC/INV catalog into bibliographic_records and book_copies';

    private PDO $source;

    /** @var array<int, array{branch_id: int|null, fund_id: int|null}> */
    private array $siglaTargets = [];

    /** @var array<string, int> */
    private array $stats = [
        'docs_seen' => 0,
        'records_created' => 0,
        'records_updated' => 0,
        'drafts' => 0,
        'copies_created' => 0,
        'copies_updated' => 0,
        'copy_inventory_aliases' => 0,
    ];

    public function handle(): int
    {
        $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
        $remaining = max(0, (int) $this->option('limit'));
        $cursor = max(0, (int) $this->option('after'));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $this->source = $this->connect();
            $sourceTotals = $this->sourceTotals();
        } catch (Throwable $exception) {
            $this->error('SQL Server connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Source: %d DOC, %d INV (%d linked to an existing DOC). Starting after DOC_ID %d.',
            $sourceTotals['docs'],
            $sourceTotals['copies'],
            $sourceTotals['linked_copies'],
            $cursor,
        ));

        if (! $dryRun) {
            $this->ensureTrackingTables();
            $this->siglaTargets = $this->resolveSiglaTargets();
        }

        while (true) {
            $take = $remaining > 0 ? min($chunkSize, $remaining) : $chunkSize;
            $documents = $this->fetchDocuments($cursor, $take);
            if ($documents === []) {
                break;
            }

            $docIds = array_map(static fn (array $row): int => (int) $row['DOC_ID'], $documents);
            $copies = $this->fetchCopies($docIds);

            try {
                if ($dryRun) {
                    foreach ($documents as $document) {
                        $this->transformDocument($document);
                    }
                } else {
                    DB::transaction(function () use ($documents, $copies): void {
                        $recordIds = [];
                        foreach ($documents as $document) {
                            $recordIds[(int) $document['DOC_ID']] = $this->storeDocument($document);
                        }
                        foreach ($copies as $copy) {
                            $this->storeCopy($copy, $recordIds[(int) $copy['DOC_ID']]);
                        }
                    }, 3);
                }
            } catch (Throwable $exception) {
                $this->error(sprintf(
                    'Chunk %d..%d rolled back: %s',
                    min($docIds),
                    max($docIds),
                    $exception->getMessage(),
                ));

                return self::FAILURE;
            }

            $this->stats['docs_seen'] += count($documents);
            $cursor = max($docIds);
            if ($remaining > 0) {
                $remaining -= count($documents);
            }

            $this->line(sprintf(
                'Progress: %d DOC; cursor=%d; records +%d/~%d; copies +%d/~%d; drafts=%d.',
                $this->stats['docs_seen'],
                $cursor,
                $this->stats['records_created'],
                $this->stats['records_updated'],
                $this->stats['copies_created'],
                $this->stats['copies_updated'],
                $this->stats['drafts'],
            ));

            if (($remaining === 0 && (int) $this->option('limit') > 0) || count($documents) < $take) {
                break;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Dry run complete. ' : 'Import complete. ').json_encode($this->stats, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function connect(): PDO
    {
        if (! in_array('dblib', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('PDO_DBLIB is not installed (rebuild the app image from the project Dockerfile).');
        }

        $password = (string) env('MARC_DB_PASSWORD', '');
        if ($password === '') {
            throw new RuntimeException('MARC_DB_PASSWORD is required.');
        }

        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $database = (string) $this->option('database');

        return new PDO(
            "dblib:host={$host}:{$port};dbname={$database};charset=UTF-8",
            (string) $this->option('username'),
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }

    /**
     * The SQL intentionally calls the audited native functions. MARC ITEM is
     * never parsed in PHP.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchDocuments(int $after, int $limit): array
    {
        $sql = sprintf(<<<'SQL'
            SELECT TOP %d
                DOC_ID,
                RECTYPE,
                BIBLEVEL,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '245', 'a', 1) AS title,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '245', 'b', 1) AS subtitle,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '100', 'a', 1) AS primary_author,
                dbo.GetSubfieldsConcat(CAST(ITEM AS nvarchar(max)), '700', 'a') AS additional_authors,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '260', 'b', 1) AS publisher,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '260', 'c', 1) AS publication_year,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '020', 'a', 1) AS isbn,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '041', 'a', 1) AS language,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '090', 'a', 1) AS udc_code_detailed,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '080', 'a', 1) AS udc_code,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '520', 'a', 1) AS annotation,
                dbo.GetSubfieldsConcat(CAST(ITEM AS nvarchar(max)), '653', 'a') AS keywords,
                dbo.GetSubfieldsConcat(CAST(ITEM AS nvarchar(max)), '952', 'a') AS ksu_literature_type,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '952', 'd', 1) AS faculty,
                dbo.GetSubfield(CAST(ITEM AS nvarchar(max)), '952', 'e', 1) AS department,
                dbo.GetSubfieldsConcat(CAST(ITEM AS nvarchar(max)), '952', 'i') AS disciplines,
                dbo.GetSubfieldsConcat(CAST(ITEM AS nvarchar(max)), '952', 'j') AS specialty
            FROM dbo.DOC
            WHERE DOC_ID > %d
            ORDER BY DOC_ID
            SQL, $limit, $after);

        return $this->source->query($sql)->fetchAll();
    }

    /**
     * @param  list<int>  $docIds
     * @return list<array<string, mixed>>
     */
    private function fetchCopies(array $docIds): array
    {
        if ($docIds === []) {
            return [];
        }

        $ids = implode(',', array_map('intval', $docIds));
        $sql = <<<SQL
            SELECT
                INV_ID, DOC_ID, T090h, T090e, T090f, T090w, T876c, T876p,
                T020d, T020e, T990n, CNT, REGDATE, INVMODE, SOURCE,
                SIGLA_ID, OFFDATE, NOTES, STATE, INVP
            FROM dbo.INV
            WHERE DOC_ID IN ({$ids})
            ORDER BY INV_ID
            SQL;

        return $this->source->query($sql)->fetchAll();
    }

    private function storeDocument(array $source): int
    {
        $sourceId = (int) $source['DOC_ID'];
        $attributes = $this->transformDocument($source);
        $tracking = DB::table('marc_import_records')->where('source_doc_id', $sourceId)->first();

        if ($tracking === null) {
            $record = BibliographicRecord::query()->create($attributes);
            DB::table('marc_import_records')->insert([
                'source_doc_id' => $sourceId,
                'bibliographic_record_id' => $record->getKey(),
                'source_hash' => $this->sourceHash($source),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->stats['records_created']++;
        } else {
            $record = BibliographicRecord::query()->findOrFail($tracking->bibliographic_record_id);
            $record->fill($attributes)->save();
            DB::table('marc_import_records')->where('source_doc_id', $sourceId)->update([
                'source_hash' => $this->sourceHash($source),
                'updated_at' => now(),
            ]);
            $this->stats['records_updated']++;
        }

        if ($record->is_draft) {
            $this->stats['drafts']++;
        }

        return (int) $record->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDocument(array $source): array
    {
        $sourceId = (int) $source['DOC_ID'];
        $title = $this->clean($source['title'] ?? null, 255);
        $primary = $this->clean($source['primary_author'] ?? null, 255);
        $authors = $this->list($source['additional_authors'] ?? null, 255);
        if ($primary === null && $authors !== []) {
            $primary = array_shift($authors);
        }
        $authors = array_values(array_filter(
            array_unique($authors),
            static fn (string $author): bool => $author !== $primary,
        ));

        $rawYear = $this->clean($source['publication_year'] ?? null, 255);
        $year = $this->validStoredYear($rawYear);
        $isbn = $this->clean($source['isbn'] ?? null, 255);
        $qualityIssues = [];
        if ($title === null) {
            $qualityIssues[] = 'missing_title';
            $title = "[Без заглавия; MARC DOC_ID {$sourceId}]";
        }
        if ($rawYear !== null && $year === null) {
            $qualityIssues[] = 'invalid_year: '.$rawYear;
        }
        if ($isbn !== null && ! $this->validIsbn($isbn)) {
            $qualityIssues[] = 'invalid_isbn';
        }

        $attributes = [
            'title' => $title,
            'subtitle' => $this->clean($source['subtitle'] ?? null, 255),
            'primary_author' => $primary,
            'additional_authors' => $authors === [] ? null : $authors,
            'publisher' => $this->clean($source['publisher'] ?? null, 255),
            'publication_year' => $year,
            'language' => $this->language($source['language'] ?? null),
            'udc_code' => $this->clean($source['udc_code_detailed'] ?? $source['udc_code'] ?? null, 64),
            'annotation' => $this->clean($source['annotation'] ?? null),
            'keywords' => ($keywords = $this->list($source['keywords'] ?? null, 255)) === [] ? null : $keywords,
            'isbn' => $isbn,
            'resource_type' => $this->resourceType($source),
            'notes' => $this->importNotes($sourceId, $qualityIssues),
        ];

        $academic = MarcAcademicFields::fromValues(
            literatureTypes: $this->clean($source['ksu_literature_type'] ?? null),
            faculty: $this->clean($source['faculty'] ?? null),
            department: $this->clean($source['department'] ?? null),
            disciplines: $this->clean($source['disciplines'] ?? null),
            specialties: $this->clean($source['specialty'] ?? null),
            fixed008: null,
        );
        foreach (['ksu_literature_type', 'faculty', 'department', 'disciplines', 'specialty'] as $field) {
            $attributes[$field] = $academic[$field];
        }

        $probe = new BibliographicRecord($attributes);
        $attributes['is_draft'] = $probe->missingRequiredFields() !== [] || $qualityIssues !== [];

        return $attributes;
    }

    private function storeCopy(array $source, int $recordId): void
    {
        $sourceId = (int) $source['INV_ID'];
        $tracking = DB::table('marc_import_copies')->where('source_inv_id', $sourceId)->first();
        $existingCopyId = $tracking?->book_copy_id;
        $inventory = $this->clean($source['T090e'] ?? null, 255);
        $originalInventory = $inventory;

        if ($inventory === null || BookCopy::query()
            ->where('inventory_number', $inventory)
            ->when($existingCopyId, fn ($query) => $query->where('id', '<>', $existingCopyId))
            ->exists()) {
            $inventory = 'MARC-INV-'.$sourceId;
            $this->stats['copy_inventory_aliases']++;
        }

        $barcode = $this->clean($source['T876p'] ?? null, 255);
        if ($barcode !== null && BookCopy::query()
            ->where('barcode', $barcode)
            ->when($existingCopyId, fn ($query) => $query->where('id', '<>', $existingCopyId))
            ->exists()) {
            $barcode = null;
        }

        $sigla = (int) ($source['SIGLA_ID'] ?? 0);
        $target = $this->siglaTargets[$sigla] ?? ['branch_id' => null, 'fund_id' => null];
        $offDate = $this->excelDate($source['OFFDATE'] ?? null);
        $state = (int) ($source['STATE'] ?? 1);
        $attributes = [
            'bibliographic_record_id' => $recordId,
            'inventory_number' => $inventory,
            'barcode' => $barcode,
            'storage_sigla' => $this->clean($source['T090f'] ?? null, 255),
            'branch_id' => $target['branch_id'],
            'fund_id' => $target['fund_id'],
            'shelf_location' => $this->clean($source['T090h'] ?? $source['T090w'] ?? null, 255),
            'price' => $this->decimal($source['T876c'] ?? null),
            'acquisition_source' => $this->clean($source['SOURCE'] ?? $source['T990n'] ?? null, 255),
            'registration_date' => $this->excelDate($source['REGDATE'] ?? null),
            'condition' => 'good',
            'defect_description' => $originalInventory !== $inventory
                ? 'Исходный инвентарный номер MARC: '.($originalInventory ?? '[пусто]')."; INV_ID {$sourceId}"
                : null,
            'status' => ($state === 2 || $offDate !== null) ? 'written_off' : 'available',
            'access_restriction' => 'free',
            'issue_count' => max(0, (int) ($source['CNT'] ?? 0)),
        ];

        if ($tracking === null) {
            $copy = BookCopy::query()->create($attributes);
            DB::table('marc_import_copies')->insert([
                'source_inv_id' => $sourceId,
                'book_copy_id' => $copy->getKey(),
                'source_hash' => $this->sourceHash($source),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->stats['copies_created']++;
        } else {
            $copy = BookCopy::query()->findOrFail($tracking->book_copy_id);
            $copy->fill($attributes)->save();
            DB::table('marc_import_copies')->where('source_inv_id', $sourceId)->update([
                'source_hash' => $this->sourceHash($source),
                'updated_at' => now(),
            ]);
            $this->stats['copies_updated']++;
        }
    }

    private function clean(mixed $value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = mb_scrub((string) $value, 'UTF-8');
        $clean = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{00AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/,\s*,+/u', ', ', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B,");
        if ($clean === '') {
            return null;
        }

        return $maxLength === null ? $clean : mb_substr($clean, 0, $maxLength);
    }

    /** @return list<string> */
    private function list(mixed $value, int $maxItemLength): array
    {
        $clean = $this->clean($value);
        if ($clean === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $item): ?string => $this->clean($item, $maxItemLength),
            preg_split('/\s*;\s*/u', $clean) ?: [],
        )));
    }

    private function validStoredYear(?string $raw): ?int
    {
        if ($raw === null || preg_match('/^\d{4}$/', $raw) !== 1) {
            return null;
        }

        $year = (int) $raw;

        return ($year >= 1000 && $year <= ((int) date('Y') + 1)) ? $year : null;
    }

    private function validIsbn(string $raw): bool
    {
        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', $raw) ?? '');
        if (strlen($isbn) === 10) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $digit = ($i === 9 && $isbn[$i] === 'X') ? 10 : (ctype_digit($isbn[$i]) ? (int) $isbn[$i] : -100);
                $sum += (10 - $i) * $digit;
            }

            return $sum % 11 === 0;
        }
        if (strlen($isbn) === 13 && ctype_digit($isbn)) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $isbn[$i] * ($i % 2 === 0 ? 1 : 3);
            }

            return (10 - ($sum % 10)) % 10 === (int) $isbn[12];
        }

        return false;
    }

    private function language(mixed $raw): string
    {
        $language = mb_strtolower($this->clean($raw, 64) ?? '');

        // 041$a is hand-typed in the legacy catalogue, so the same language
        // arrives spelled in three scripts: Latin "kaz", Cyrillic "каз" (49
        // records), and Kazakh "қаз". Matching only the first and third tagged
        // the Cyrillic ones "other" and hid them from the KK language filter.
        // Kazakh is tested first on purpose: a "rus; каз" record is a Kazakh
        // edition catalogued with its translation language listed first.
        if (str_contains($language, 'kaz') || str_contains($language, 'каз') || str_contains($language, 'қаз')) {
            return 'kk';
        }
        if (str_contains($language, 'rus') || str_contains($language, 'рус')) {
            return 'ru';
        }
        // "еng" carries a Cyrillic е in part of the source data.
        if (str_contains($language, 'eng') || str_contains($language, 'еng') || str_contains($language, 'анг')) {
            return 'en';
        }

        return 'other';
    }

    private function resourceType(array $source): string
    {
        return match (trim((string) ($source['BIBLEVEL'] ?? ''))) {
            's' => 'periodical',
            'c' => 'publication',
            default => 'book',
        };
    }

    /** @param list<string> $qualityIssues */
    private function importNotes(int $sourceId, array $qualityIssues): string
    {
        $notes = ["Импортировано из marc_current_2026.dbo.DOC; DOC_ID {$sourceId}."];
        if ($qualityIssues !== []) {
            $notes[] = 'Требует проверки: '.implode('; ', $qualityIssues).'.';
        }

        return implode(' ', $notes);
    }

    private function excelDate(mixed $serial): ?string
    {
        if (! is_numeric($serial) || (float) $serial <= 0) {
            return null;
        }

        $timestamp = ((int) floor((float) $serial) - 25569) * 86400;
        if ($timestamp < -2208988800 || $timestamp > 4102444800) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || ! is_numeric(trim((string) $value))) {
            return null;
        }

        $decimal = round((float) $value, 2);

        return $decimal >= 0 && $decimal <= 9999999999.99 ? number_format($decimal, 2, '.', '') : null;
    }

    private function sourceHash(array $source): string
    {
        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** @return array{docs: int, copies: int, linked_copies: int} */
    private function sourceTotals(): array
    {
        $row = $this->source->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM dbo.DOC) AS docs,
                (SELECT COUNT(*) FROM dbo.INV) AS copies,
                (SELECT COUNT(*) FROM dbo.INV i INNER JOIN dbo.DOC d ON d.DOC_ID = i.DOC_ID) AS linked_copies
            SQL)->fetch();

        return array_map('intval', $row);
    }

    private function ensureTrackingTables(): void
    {
        foreach (['marc_import_records', 'marc_import_copies'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Tracking table {$table} is missing; run php artisan migrate first.");
            }
        }
    }

    /** @return array<int, array{branch_id: int|null, fund_id: int|null}> */
    private function resolveSiglaTargets(): array
    {
        $branches = DB::table('branches')->pluck('id', 'code');
        $funds = DB::table('funds')->pluck('id', 'code');

        return [
            6 => ['branch_id' => $branches['SCIENTIFIC-LIBRARY'] ?? null, 'fund_id' => $funds['MAIN'] ?? null],
            7 => ['branch_id' => $branches['ECONOMICS-DESK'] ?? null, 'fund_id' => $funds['UNIVERSITY-ECONOMIC'] ?? null],
            8 => ['branch_id' => $branches['TECHNOLOGY-DESK'] ?? null, 'fund_id' => $funds['UNIVERSITY-TECHNOLOGY'] ?? null],
            9 => ['branch_id' => null, 'fund_id' => $funds['COLLEGE'] ?? null],
        ];
    }
}
