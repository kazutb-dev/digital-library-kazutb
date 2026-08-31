<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Loads a verified MARC-SQL export package into the legacy_* raw/import layer.
 *
 * This command only fills legacy_* tables. It never touches bibliographic_records
 * or book_copies — the canonical upsert is a separate, later step that runs only
 * once reconciliation reports zero unexplained rows.
 */
class LoadMarcPackage extends Command
{
    protected $signature = 'marc:load-package
        {--path= : Extracted package directory}
        {--package-name=  : Original zip filename}
        {--package-sha=   : Verified SHA-256 of the zip}
        {--chunk=1000     : Rows per insert chunk}
        {--fresh          : Delete a previous load of this same package first}';

    protected $description = 'Load a verified MARC-SQL package into the legacy raw/import tables';

    public function handle(): int
    {
        $path = rtrim((string) $this->option('path'), '/');
        if ($path === '' || ! is_dir($path)) {
            $this->error('--path must be an extracted package directory.');

            return self::FAILURE;
        }

        $sha = strtolower(trim((string) $this->option('package-sha')));
        if (! preg_match('/^[0-9a-f]{64}$/', $sha)) {
            $this->error('--package-sha must be the verified 64-hex SHA-256 of the package.');

            return self::FAILURE;
        }

        $validation = $this->json($path.'/VALIDATION_RESULTS.json');
        $chunk = max(100, min(5000, (int) $this->option('chunk')));

        $batchId = $this->openBatch($path, $sha, $validation);
        $this->info("Import batch #{$batchId}");

        try {
            if ($this->option('fresh')) {
                foreach (['legacy_marc_fields', 'legacy_marc_copies', 'legacy_marc_records', 'legacy_import_quarantine'] as $t) {
                    DB::table($t)->where('legacy_import_batch_id', $batchId)->delete();
                }
                $this->line('Previous rows for this batch removed.');
            }

            $docs = $this->loadDocuments($path, $batchId, $chunk);
            $this->line("documents loaded: {$docs}");

            $copies = $this->loadCopies($path, $batchId, $chunk);
            $this->line("copies loaded:    {$copies}");

            $fields = $this->loadFields($path, $batchId, $chunk);
            $this->line("marc fields:      {$fields}");

            DB::table('legacy_import_batches')->where('id', $batchId)->update([
                'documents_loaded' => $docs,
                'copies_loaded' => $copies,
                'fields_loaded' => $fields,
                'status' => 'loaded',
                'loaded_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            DB::table('legacy_import_batches')->where('id', $batchId)
                ->update(['status' => 'failed', 'updated_at' => now()]);
            $this->error('Load failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Package loaded into legacy_* tables.');

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function json(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }
        // The exporter writes UTF-8 with BOM.
        $raw = ltrim((string) file_get_contents($file), "\xEF\xBB\xBF \t\r\n");

        return json_decode($raw, true) ?: [];
    }

    /** @param array<string,mixed> $validation */
    private function openBatch(string $path, string $sha, array $validation): int
    {
        $existing = DB::table('legacy_import_batches')->where('package_sha256', $sha)->first();
        if ($existing !== null) {
            DB::table('legacy_import_batches')->where('id', $existing->id)
                ->update(['status' => 'loading', 'updated_at' => now()]);

            return (int) $existing->id;
        }

        return (int) DB::table('legacy_import_batches')->insertGetId([
            'package_name' => (string) ($this->option('package-name') ?: basename($path)),
            'package_sha256' => $sha,
            'source_system' => 'MARC-SQL',
            'source_database' => 'marc',
            'status' => 'loading',
            'documents_expected' => (int) ($validation['doc_exported'] ?? 0),
            'copies_expected' => (int) ($validation['inv_exported'] ?? 0),
            'fields_expected' => (int) ($validation['parsed_marc_fields'] ?? 0),
            'validation' => json_encode($validation, JSON_UNESCAPED_UNICODE),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Streams a gzipped JSONL file, yielding decoded rows.
     *
     * @return \Generator<int, array<string,mixed>>
     */
    private function stream(string $file): \Generator
    {
        $h = gzopen($file, 'rb');
        if ($h === false) {
            throw new RuntimeException("Cannot open {$file}");
        }
        try {
            while (($line = gzgets($h)) !== false) {
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
            gzclose($h);
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private function flush(string $table, array $rows, array $uniqueBy, array $update): void
    {
        if ($rows === []) {
            return;
        }
        DB::table($table)->upsert($rows, $uniqueBy, $update);
    }

    /**
     * Canonical and raw are streamed in two separate passes so neither file is
     * ever held in memory. Pass 1 upserts the canonical payload, pass 2 attaches
     * the raw payload by source id.
     */
    private function loadDocuments(string $path, int $batchId, int $chunk): int
    {
        $buf = [];
        $n = 0;
        $cols = ['source_hash', 'leader', 'record_type', 'bibliographic_level', 'control_number',
            'fixed_008_raw', 'modified_raw', 'canonical', 'mapping_status', 'updated_at'];

        foreach ($this->stream($path.'/canonical/bibliographic_records.jsonl.gz') as $c) {
            $buf[] = [
                'legacy_import_batch_id' => $batchId,
                'source_doc_id' => (int) $c['source_doc_id'],
                'source_hash' => (string) ($c['source_hash'] ?? ''),
                'leader' => $this->cut($c['leader'] ?? null, 255),
                'record_type' => $this->cut($c['record_type'] ?? null, 8),
                'bibliographic_level' => $this->cut($c['bibliographic_level'] ?? null, 8),
                'control_number' => $this->cut($c['control_number'] ?? null, 128),
                'fixed_008_raw' => $this->cut($c['fixed_008_raw'] ?? null, 255),
                'modified_raw' => $this->cut($c['modified_raw'] ?? null, 64),
                'canonical' => json_encode($c, JSON_UNESCAPED_UNICODE),
                'mapping_status' => $this->cut($c['mapping_status'] ?? null, 64),
                'apply_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $n++;
            if (count($buf) >= $chunk) {
                $this->flush('legacy_marc_records', $buf, ['legacy_import_batch_id', 'source_doc_id'], $cols);
                $buf = [];
                $this->output->write('.');
            }
        }
        $this->flush('legacy_marc_records', $buf, ['legacy_import_batch_id', 'source_doc_id'], $cols);
        $this->newLine();

        $this->attachRaw($path.'/raw/documents_raw.jsonl.gz', 'legacy_marc_records', 'source_doc_id', 'DOC_ID', $batchId, $chunk);

        return $n;
    }

    private function loadCopies(string $path, int $batchId, int $chunk): int
    {
        $buf = [];
        $quarantine = [];
        $n = 0;
        $cols = ['source_doc_id', 'source_hash', 'relation_status', 'canonical', 'apply_status', 'updated_at'];

        foreach ($this->stream($path.'/canonical/copies.jsonl.gz') as $c) {
            $id = (int) $c['source_inv_id'];
            $relation = (string) ($c['relation_status'] ?? 'linked');
            $buf[] = [
                'legacy_import_batch_id' => $batchId,
                'source_inv_id' => $id,
                'source_doc_id' => isset($c['source_doc_id']) ? (int) $c['source_doc_id'] : null,
                'source_hash' => (string) ($c['source_hash'] ?? ''),
                'relation_status' => $this->cut($relation, 32),
                'canonical' => json_encode($c, JSON_UNESCAPED_UNICODE),
                'apply_status' => $relation === 'orphan' ? 'quarantined' : 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($relation === 'orphan') {
                $quarantine[] = [
                    'legacy_import_batch_id' => $batchId,
                    'kind' => 'orphan_copy',
                    'source_doc_id' => isset($c['source_doc_id']) ? (int) $c['source_doc_id'] : null,
                    'source_inv_id' => $id,
                    'reason' => 'INV row has no matching DOC record in the source export.',
                    'payload' => json_encode($c, JSON_UNESCAPED_UNICODE),
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            $n++;
            if (count($buf) >= $chunk) {
                $this->flush('legacy_marc_copies', $buf, ['legacy_import_batch_id', 'source_inv_id'], $cols);
                $buf = [];
                $this->output->write('.');
            }
            if (count($quarantine) >= $chunk) {
                DB::table('legacy_import_quarantine')->insert($quarantine);
                $quarantine = [];
            }
        }
        $this->flush('legacy_marc_copies', $buf, ['legacy_import_batch_id', 'source_inv_id'], $cols);
        if ($quarantine !== []) {
            DB::table('legacy_import_quarantine')->insert($quarantine);
        }
        $this->newLine();

        $this->attachRaw($path.'/raw/copies_raw.jsonl.gz', 'legacy_marc_copies', 'source_inv_id', 'INV_ID', $batchId, $chunk);

        return $n;
    }

    /**
     * Second pass: stream the raw export and attach each payload to the row it
     * belongs to, in batched UPDATE ... FROM (VALUES ...) statements.
     */
    private function attachRaw(string $file, string $table, string $keyColumn, string $rawKey, int $batchId, int $chunk): void
    {
        $pairs = [];
        $done = 0;
        foreach ($this->stream($file) as $r) {
            $pairs[] = [(int) $r[$rawKey], json_encode($r, JSON_UNESCAPED_UNICODE)];
            if (count($pairs) >= $chunk) {
                $this->applyRawChunk($table, $keyColumn, $batchId, $pairs);
                $done += count($pairs);
                $pairs = [];
                $this->output->write('+');
            }
        }
        if ($pairs !== []) {
            $this->applyRawChunk($table, $keyColumn, $batchId, $pairs);
            $done += count($pairs);
        }
        $this->newLine();
        $this->line("  raw payloads attached to {$table}: {$done}");
    }

    /** @param list<array{0:int,1:string}> $pairs */
    private function applyRawChunk(string $table, string $keyColumn, int $batchId, array $pairs): void
    {
        $values = [];
        $bindings = [];
        foreach ($pairs as [$id, $json]) {
            $values[] = '(?::bigint, ?::json)';
            $bindings[] = $id;
            $bindings[] = $json;
        }
        $bindings[] = $batchId;
        DB::statement(
            "update {$table} as t set raw = v.raw from (values ".implode(',', $values).
            ") as v(key, raw) where t.{$keyColumn} = v.key and t.legacy_import_batch_id = ?",
            $bindings
        );
    }

    private function loadFields(string $path, int $batchId, int $chunk): int
    {
        // Known tags per the source semantics audit; anything else is retained
        // and flagged rather than dropped.
        $known = ['001','005','008','013','020','022','040','041','080','084','090','097','100','245','246','250',
            '256','260','300','336','337','440','490','520','541','650','653','700','773','852','856','900','901',
            '952','990','991','998','000'];

        $buf = [];
        $n = 0;
        foreach ($this->stream($path.'/canonical/marc_fields.jsonl.gz') as $f) {
            $tag = (string) ($f['tag'] ?? '');
            $buf[] = [
                'legacy_import_batch_id' => $batchId,
                'source_doc_id' => (int) ($f['source_doc_id'] ?? 0),
                'tag' => $this->cut($tag, 8) ?? '',
                'indicator1' => $this->cut($f['indicator_1'] ?? null, 4),
                'indicator2' => $this->cut($f['indicator_2'] ?? null, 4),
                'subfield_code' => $this->cut($f['subfield_code'] ?? null, 4),
                'value' => $f['value'] ?? null,
                'occurrence' => (int) ($f['field_occurrence'] ?? 0),
                'is_known_tag' => in_array($tag, $known, true),
                'raw' => json_encode($f, JSON_UNESCAPED_UNICODE),
            ];
            $n++;
            if (count($buf) >= $chunk) {
                DB::table('legacy_marc_fields')->insert($buf);
                $buf = [];
                if ($n % ($chunk * 20) === 0) {
                    $this->output->write('.');
                }
            }
        }
        if ($buf !== []) {
            DB::table('legacy_marc_fields')->insert($buf);
        }
        $this->newLine();

        return $n;
    }

    private function cut(mixed $v, int $len): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $len);
    }
}
