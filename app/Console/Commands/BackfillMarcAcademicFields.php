<?php

namespace App\Console\Commands;

use App\Models\Catalog\BibliographicRecord;
use App\Services\Catalog\MarcAcademicFields;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Backfills the academic-targeting (952) and record-provenance (008) attributes
 * from a static MARC export package, for records that are already in the
 * catalogue but were imported before these fields were mapped.
 *
 * Reads canonical/marc_fields.jsonl.gz straight from the delivered package, so
 * it works offline without the SQL Server source. Records are matched through
 * marc_import_records.source_doc_id; a curated (non-null) value is never
 * overwritten unless --force is given.
 */
class BackfillMarcAcademicFields extends Command
{
    protected $signature = 'marc:backfill-academic
        {--path= : Extracted package directory containing canonical/marc_fields.jsonl.gz}
        {--fields= : Direct path to a marc_fields.jsonl.gz file (overrides --path)}
        {--force : Overwrite existing non-null values}
        {--dry-run : Report changes without writing}';

    protected $description = 'Backfill faculty/department/discipline/specialty/KSU type and record provenance from a MARC export package';

    private const ACADEMIC_FIELDS = [
        'ksu_literature_type', 'faculty', 'department',
        'disciplines', 'specialty', 'country_code', 'record_created_on',
    ];

    public function handle(): int
    {
        $file = $this->resolveFieldsFile();
        if ($file === null) {
            $this->error('Could not locate marc_fields.jsonl.gz. Pass --fields= or --path=.');

            return self::FAILURE;
        }
        $this->info('Reading '.$file);

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        // Only tags 952/008 matter; keeping just those bounds memory to the
        // few thousand documents that actually carry academic metadata.
        $grouped = [];
        $rows = 0;
        foreach ($this->stream($file) as $field) {
            $tag = (string) ($field['tag'] ?? '');
            if ($tag !== '952' && $tag !== '008') {
                continue;
            }
            $docId = (int) ($field['source_doc_id'] ?? 0);
            if ($docId === 0) {
                continue;
            }
            $grouped[$docId][] = [
                'tag' => $tag,
                'subfield_code' => $field['subfield_code'] ?? null,
                'value' => $field['value'] ?? null,
            ];
            $rows++;
        }
        $this->info(sprintf('Collected %d academic field rows across %d documents.', $rows, count($grouped)));

        $seen = 0;
        $updated = 0;
        $skippedNoRecord = 0;

        foreach (array_chunk($grouped, 500, true) as $chunk) {
            $docIds = array_keys($chunk);
            $recordIds = DB::table('marc_import_records')
                ->whereIn('source_doc_id', $docIds)
                ->pluck('bibliographic_record_id', 'source_doc_id');

            DB::transaction(function () use ($chunk, $recordIds, $force, $dryRun, &$seen, &$updated, &$skippedNoRecord): void {
                foreach ($chunk as $docId => $fields) {
                    $seen++;
                    $recordId = $recordIds[$docId] ?? null;
                    if ($recordId === null) {
                        $skippedNoRecord++;

                        continue;
                    }
                    $record = BibliographicRecord::query()->find($recordId);
                    if ($record === null) {
                        $skippedNoRecord++;

                        continue;
                    }

                    $academic = MarcAcademicFields::fromFieldRows($fields);
                    $dirty = false;
                    foreach (self::ACADEMIC_FIELDS as $attribute) {
                        $incoming = $academic[$attribute] ?? null;
                        if ($incoming === null) {
                            continue;
                        }
                        $current = $record->getAttribute($attribute);
                        $currentValue = $current === null ? null : (string) $current;
                        if ($currentValue !== null && $currentValue !== '' && ! $force) {
                            continue;
                        }
                        if ($currentValue === $incoming) {
                            continue;
                        }
                        $record->setAttribute($attribute, $incoming);
                        $dirty = true;
                    }

                    if ($dirty) {
                        $updated++;
                        if (! $dryRun) {
                            $record->save();
                        }
                    }
                }
            });

            $this->line(sprintf('Progress: %d documents; %d records %s.', $seen, $updated, $dryRun ? 'would change' : 'changed'));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s complete: %d documents, %d records %s, %d without a linked catalogue record.',
            $dryRun ? 'Dry run' : 'Backfill',
            $seen,
            $updated,
            $dryRun ? 'would change' : 'changed',
            $skippedNoRecord,
        ));

        return self::SUCCESS;
    }

    private function resolveFieldsFile(): ?string
    {
        $direct = (string) $this->option('fields');
        if ($direct !== '') {
            return is_file($direct) ? $direct : null;
        }

        $path = (string) $this->option('path');
        if ($path !== '') {
            $candidate = rtrim($path, '/').'/canonical/marc_fields.jsonl.gz';

            return is_file($candidate) ? $candidate : null;
        }

        foreach ([
            base_path('storage/app/marc/canonical/marc_fields.jsonl.gz'),
            storage_path('app/marc/canonical/marc_fields.jsonl.gz'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return \Generator<int, array<string,mixed>>
     */
    private function stream(string $file): \Generator
    {
        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$file}");
        }
        try {
            while (($line = gzgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            gzclose($handle);
        }
    }
}
