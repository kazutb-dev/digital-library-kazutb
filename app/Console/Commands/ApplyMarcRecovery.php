<?php

namespace App\Console\Commands;

use App\Services\Catalog\MarcAcademicFields;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies a loaded MARC-SQL package from the legacy_* layer onto the live
 * catalogue.
 *
 * Guarantees:
 *  - never deletes or truncates; only UPDATE existing / INSERT missing;
 *  - never touches records without a marc: legacy id (manual cataloguing);
 *  - a field a librarian edited after the original import is not overwritten —
 *    it is recorded in legacy_import_conflicts instead;
 *  - idempotent: re-running produces no duplicates and no further writes.
 *
 * Field semantics follow the 2026-08-28 source business-semantics audit.
 * In particular INV.STATE is NOT dbo.STATES: 1 = active, 2 = written off.
 */
class ApplyMarcRecovery extends Command
{
    protected $signature = 'marc:apply-recovery
        {--batch=      : legacy_import_batches id (default: latest loaded)}
        {--chunk=500   : rows per transaction}
        {--dry-run     : report what would change without writing}
        {--only=       : records|copies|relations (default: all)}';

    protected $description = 'Apply the loaded MARC package onto bibliographic_records and book_copies';

    /** Instant the original degraded import committed; later edits are human. */
    private const IMPORT_INSTANT = '2026-08-12 08:04:38+00';

    private array $stats = [];

    public function handle(): int
    {
        $batch = (int) ($this->option('batch') ?: DB::table('legacy_import_batches')
            ->where('status', 'loaded')->orderByDesc('id')->value('id'));
        if ($batch === 0) {
            $this->error('No loaded import batch found.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $only = (string) $this->option('only');
        $chunk = max(50, min(2000, (int) $this->option('chunk')));

        $this->info(($dry ? '[DRY RUN] ' : '').'Applying batch #'.$batch);
        $this->stats = [
            'records_updated' => 0, 'records_inserted' => 0, 'records_skipped_conflict' => 0,
            'copies_updated' => 0, 'copies_inserted' => 0, 'copies_quarantined' => 0,
            'conflicts_logged' => 0, 'contributors_linked' => 0, 'subjects_linked' => 0,
            'duplicate_inventory_deferred' => 0,
        ];

        try {
            if ($only === '' || $only === 'records') {
                $this->applyRecords($batch, $chunk, $dry);
            }
            if ($only === '' || $only === 'copies') {
                $this->applyCopies($batch, $chunk, $dry);
            }
            if ($only === '' || $only === 'relations') {
                $this->applyRelations($batch, $chunk, $dry);
            }
        } catch (Throwable $e) {
            $this->error('Apply failed: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());

            return self::FAILURE;
        }

        if (! $dry) {
            DB::table('legacy_import_batches')->where('id', $batch)->update([
                'status' => 'applied',
                'applied_at' => now(),
                'apply_stats' => json_encode($this->stats, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        $this->newLine();
        foreach ($this->stats as $k => $v) {
            $this->line(sprintf('  %-28s %d', $k, $v));
        }

        return self::SUCCESS;
    }

    // ── Bibliographic records ────────────────────────────────────────────

    private function applyRecords(int $batch, int $chunk, bool $dry): void
    {
        $this->line('Records...');
        $lastId = 0;
        while (true) {
            $rows = DB::table('legacy_marc_records')
                ->where('legacy_import_batch_id', $batch)
                ->where('id', '>', $lastId)
                ->orderBy('id')->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($batch, $dry, $rows) {
                $academic = $this->academicFieldsFor($batch, $rows->pluck('source_doc_id')->map(fn ($id) => (int) $id)->all());
                foreach ($rows as $row) {
                    $this->applyOneRecord($row, $batch, $dry, $academic[(int) $row->source_doc_id] ?? []);
                }
            });
            $lastId = (int) $rows->last()->id;
            $this->output->write('.');
        }
        $this->newLine();
    }

    private function applyOneRecord(object $row, int $batch, bool $dry, array $academic = []): void
    {
        $c = json_decode((string) $row->canonical, true) ?: [];
        $docId = (int) $row->source_doc_id;
        $attrs = $this->mapRecord($c, $docId);
        foreach ($academic as $field => $value) {
            if ($value !== null) {
                $attrs[$field] = $value;
            }
        }

        $tracking = DB::table('marc_import_records')->where('source_doc_id', $docId)->first();

        if ($tracking === null) {
            if ($dry) {
                $this->stats['records_inserted']++;

                return;
            }
            $attrs['legacy_external_id'] = 'marc:'.$docId;
            $attrs['legacy_import_batch_id'] = $batch;
            $attrs['legacy_imported_at'] = now();
            $attrs['merge_status'] = 'active';
            $attrs['created_at'] = now();
            $attrs['updated_at'] = now();
            $attrs['is_draft'] = $this->isDraft($attrs);
            $newId = DB::table('bibliographic_records')->insertGetId($attrs);
            DB::table('marc_import_records')->insert([
                'source_doc_id' => $docId, 'bibliographic_record_id' => $newId,
                'source_hash' => (string) $row->source_hash,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_marc_records')->where('id', $row->id)
                ->update(['bibliographic_record_id' => $newId, 'apply_status' => 'inserted', 'updated_at' => now()]);
            $this->stats['records_inserted']++;

            return;
        }

        $current = DB::table('bibliographic_records')->find($tracking->bibliographic_record_id);
        if ($current === null) {
            return;
        }

        // Anything without a marc: legacy id is manual cataloguing — never touch.
        if ($current->legacy_external_id !== null && ! str_starts_with((string) $current->legacy_external_id, 'marc:')) {
            DB::table('legacy_marc_records')->where('id', $row->id)
                ->update(['apply_status' => 'skipped', 'updated_at' => now()]);

            return;
        }

        $humanEdited = $current->updated_at !== null
            && strtotime((string) $current->updated_at) > strtotime(self::IMPORT_INSTANT);

        $apply = [];
        $conflicts = [];
        // Provenance stamps are written only when a real field actually changes,
        // otherwise every re-run would look like an update.
        $metaOnly = ['legacy_import_batch_id', 'legacy_imported_at'];
        foreach ($attrs as $field => $incoming) {
            if (in_array($field, $metaOnly, true)) {
                continue;
            }
            $currentValue = $current->$field ?? null;
            if ($this->same($currentValue, $incoming)) {
                continue;
            }
            // A human-edited, non-empty field is authoritative: log, don't overwrite.
            if ($humanEdited && $currentValue !== null && $currentValue !== '') {
                $conflicts[] = [
                    'legacy_import_batch_id' => $batch,
                    'entity_type' => 'bibliographic_record',
                    'entity_id' => $current->id,
                    'source_id' => $docId,
                    'field_name' => $field,
                    'current_value' => is_scalar($currentValue) ? (string) $currentValue : json_encode($currentValue),
                    'incoming_value' => is_scalar($incoming) ? (string) $incoming : json_encode($incoming),
                    'reason' => 'manual_edit_after_import',
                    'status' => 'open',
                    'created_at' => now(), 'updated_at' => now(),
                ];

                continue;
            }
            $apply[$field] = $incoming;
        }

        if ($dry) {
            if ($apply !== []) {
                $this->stats['records_updated']++;
            }
            $this->stats['conflicts_logged'] += count($conflicts);

            return;
        }

        $conflicts = $this->newConflictsOnly($conflicts);
        if ($conflicts !== []) {
            DB::table('legacy_import_conflicts')->insert($conflicts);
            $this->stats['conflicts_logged'] += count($conflicts);
        }
        if ($conflicts !== [] || $this->hasOpenConflict('bibliographic_record', (int) $current->id)) {
            $this->stats['records_skipped_conflict']++;
        }
        if ($apply !== []) {
            $apply['legacy_import_batch_id'] = $batch;
            $apply['legacy_imported_at'] = now();
            $apply['updated_at'] = now();
            DB::table('bibliographic_records')->where('id', $current->id)->update($apply);
            $this->stats['records_updated']++;
        }
        DB::table('legacy_marc_records')->where('id', $row->id)->update([
            'bibliographic_record_id' => $current->id,
            'apply_status' => $conflicts !== [] ? 'conflict' : ($apply !== [] ? 'updated' : 'skipped'),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function mapRecord(array $c, int $docId): array
    {
        $isbn = $this->firstOf($c['isbn'] ?? null);
        $issn = $this->firstOf($c['issn'] ?? null);
        $udc = $this->firstOf($c['udc'] ?? null);
        $bbk = $this->firstOf($c['bbk_or_other_classification'] ?? null);
        $local = $this->firstOf($c['local_classification'] ?? null);
        $series = $c['series'] ?? [];
        $seriesTitle = is_array($series) && $series !== [] ? (is_array($series[0]) ? ($series[0]['title'] ?? null) : $series[0]) : null;

        $authors = array_values(array_filter(array_map('trim', (array) ($c['authors'] ?? []))));
        $extra = array_values(array_filter(array_map('trim', (array) ($c['additional_authors'] ?? []))));
        $primary = $authors[0] ?? null;
        $additional = array_values(array_unique(array_merge(array_slice($authors, 1), $extra)));

        return [
            'title' => $this->cut($c['title'] ?? null, 1000) ?? '[Без заглавия; MARC DOC_ID '.$docId.']',
            'subtitle' => $this->cut($c['subtitle'] ?? null, 1000),
            'primary_author' => $this->cut($primary, 255),
            'additional_authors' => $additional === [] ? null : json_encode($additional, JSON_UNESCAPED_UNICODE),
            'statement_of_responsibility' => $this->cut($c['responsibility'] ?? null, 1000),
            'publisher' => $this->cut($c['publisher'] ?? null, 255),
            'publication_place' => $this->cut($c['publication_place'] ?? null, 255),
            'publication_year' => $this->year($c['publication_year'] ?? null),
            'edition_statement' => $this->cut($c['edition'] ?? null, 255),
            'language' => $this->cut($c['language_canonical'] ?? null, 16) ?? 'other',
            'legacy_language_code' => $this->cut($c['language_raw'] ?? null, 32),
            'udc_code' => $this->cut($udc, 64),
            'bbk_code' => $this->cut($bbk, 64),
            'local_classification' => $this->cut($local, 128),
            'author_mark' => $this->cut($c['author_mark'] ?? null, 16),
            'annotation' => $c['annotation'] ?? null,
            'keywords' => ($k = array_values(array_filter((array) ($c['keywords'] ?? [])))) === [] ? null : json_encode($k, JSON_UNESCAPED_UNICODE),
            'isbn' => $this->cut($isbn, 32),
            'issn' => $this->cut($issn, 32),
            'physical_extent' => $this->cut($c['physical_extent'] ?? null, 255),
            'physical_details' => $this->cut($c['physical_details'] ?? null, 255),
            'dimensions' => $this->cut($c['dimensions'] ?? null, 64),
            'series_title' => $this->cut($seriesTitle, 500),
            'part_number' => $this->cut($c['part_number'] ?? null, 64),
            'part_title' => $this->cut($c['part_title'] ?? null, 500),
            'control_number' => $this->cut($c['control_number'] ?? null, 128),
            'material_designation' => $this->cut($c['material_designation'] ?? null, 128),
            'legacy_local_path' => $this->cut($c['local_source_path'] ?? null, 500),
            'legacy_modified_at' => $this->marc005($c['modified_raw'] ?? null),
            'resource_type' => $this->resourceType($c),
        ];
    }

    private function isDraft(array $a): bool
    {
        foreach (['title', 'primary_author', 'publisher', 'publication_year', 'udc_code', 'annotation'] as $f) {
            if (($a[$f] ?? null) === null || $a[$f] === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the academic-targeting attributes (952) and record provenance
     * (008) the canonical payload does not carry, for a chunk of source docs.
     *
     * @param  list<int>  $docIds
     * @return array<int, array<string,mixed>>
     */
    private function academicFieldsFor(int $batch, array $docIds): array
    {
        $docIds = array_values(array_filter($docIds));
        if ($docIds === []) {
            return [];
        }

        $grouped = [];
        DB::table('legacy_marc_fields')
            ->where('legacy_import_batch_id', $batch)
            ->whereIn('tag', ['952', '008'])
            ->whereIn('source_doc_id', $docIds)
            ->orderBy('id')
            ->select(['source_doc_id', 'tag', 'subfield_code', 'value'])
            ->get()
            ->each(function (object $field) use (&$grouped): void {
                $grouped[(int) $field->source_doc_id][] = $field;
            });

        $out = [];
        foreach ($grouped as $docId => $fields) {
            $out[$docId] = MarcAcademicFields::fromFieldRows($fields);
        }

        return $out;
    }

    private function resourceType(array $c): string
    {
        return match (trim((string) ($c['bibliographic_level'] ?? ''))) {
            's' => 'periodical',
            'c' => 'publication',
            default => 'book',
        };
    }

    // ── Copies ───────────────────────────────────────────────────────────

    private function applyCopies(int $batch, int $chunk, bool $dry): void
    {
        $this->line('Copies...');
        $lastId = 0;
        while (true) {
            $rows = DB::table('legacy_marc_copies')
                ->where('legacy_import_batch_id', $batch)
                ->where('id', '>', $lastId)
                ->orderBy('id')->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }
            DB::transaction(function () use ($rows, $batch, $dry) {
                foreach ($rows as $row) {
                    $this->applyOneCopy($row, $batch, $dry);
                }
            });
            $lastId = (int) $rows->last()->id;
            $this->output->write('.');
        }
        $this->newLine();
    }

    private function applyOneCopy(object $row, int $batch, bool $dry): void
    {
        if ($row->relation_status === 'orphan') {
            $this->stats['copies_quarantined']++;

            return;
        }
        $c = json_decode((string) $row->canonical, true) ?: [];
        $raw = json_decode((string) $row->raw, true) ?: [];
        $invId = (int) $row->source_inv_id;
        $docId = (int) ($row->source_doc_id ?? 0);

        $recordId = DB::table('marc_import_records')->where('source_doc_id', $docId)->value('bibliographic_record_id');
        if ($recordId === null) {
            return;
        }

        $attrs = $this->mapCopy($c, $raw, $recordId, $invId, $batch);
        $tracking = DB::table('marc_import_copies')->where('source_inv_id', $invId)->first();
        $currentCopyId = $tracking?->book_copy_id;

        // The source contains inventory numbers reused across two different INV
        // rows. Inventory numbers are unique here, so the second claimant keeps
        // its synthetic number and the collision goes to the review queue
        // instead of being silently reassigned (source audit / rule 16).
        if ($attrs['inventory_number_is_synthetic'] === false) {
            $clash = DB::table('book_copies')
                ->where('inventory_number', $attrs['inventory_number'])
                ->when($currentCopyId, fn ($q) => $q->where('id', '<>', $currentCopyId))
                ->exists();
            if ($clash) {
                $claimed = $attrs['inventory_number'];
                $attrs['inventory_number'] = 'MARC-'.$invId;
                $attrs['inventory_number_is_synthetic'] = true;
                $already = DB::table('legacy_import_quarantine')
                    ->where('kind', 'duplicate_inventory')->where('source_inv_id', $invId)->exists();
                if (! $dry && ! $already) {
                    DB::table('legacy_import_quarantine')->insert([
                        'legacy_import_batch_id' => $batch,
                        'kind' => 'duplicate_inventory',
                        'source_doc_id' => $docId,
                        'source_inv_id' => $invId,
                        'reason' => 'Source inventory number '.$claimed.' is already held by another copy; synthetic number retained pending librarian decision.',
                        'payload' => json_encode(['claimed_inventory_number' => $claimed, 'source_inv_id' => $invId, 'source_doc_id' => $docId], JSON_UNESCAPED_UNICODE),
                        'status' => 'open',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->stats['duplicate_inventory_deferred'] = ($this->stats['duplicate_inventory_deferred'] ?? 0) + 1;
            }
        }

        if ($tracking === null) {
            if ($dry) {
                $this->stats['copies_inserted']++;

                return;
            }
            $attrs['created_at'] = now();
            $attrs['updated_at'] = now();
            $newId = DB::table('book_copies')->insertGetId($attrs);
            DB::table('marc_import_copies')->insert([
                'source_inv_id' => $invId, 'book_copy_id' => $newId,
                'source_hash' => (string) $row->source_hash,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('legacy_marc_copies')->where('id', $row->id)
                ->update(['book_copy_id' => $newId, 'apply_status' => 'inserted', 'updated_at' => now()]);
            $this->stats['copies_inserted']++;

            return;
        }

        $current = DB::table('book_copies')->find($tracking->book_copy_id);
        if ($current === null) {
            return;
        }
        // Manual copies never carry the MARC- synthetic prefix.
        if (! str_starts_with((string) $current->inventory_number, 'MARC-')
            && (int) ($current->legacy_inv_id ?? 0) !== $invId) {
            DB::table('legacy_marc_copies')->where('id', $row->id)
                ->update(['apply_status' => 'skipped', 'updated_at' => now()]);

            return;
        }

        $humanEdited = $current->updated_at !== null
            && strtotime((string) $current->updated_at) > strtotime('2026-08-12 08:04:49+00');

        $apply = [];
        $conflicts = [];
        $metaOnly = ['legacy_import_batch_id', 'legacy_imported_at'];
        foreach ($attrs as $f => $incoming) {
            if (in_array($f, $metaOnly, true)) {
                continue;
            }
            $cur = $current->$f ?? null;
            if ($this->same($cur, $incoming)) {
                continue;
            }
            if ($humanEdited && $cur !== null && $cur !== '' && $f !== 'legacy_import_batch_id' && $f !== 'legacy_imported_at') {
                $conflicts[] = [
                    'legacy_import_batch_id' => $batch, 'entity_type' => 'book_copy',
                    'entity_id' => $current->id, 'source_id' => $invId, 'field_name' => $f,
                    'current_value' => is_scalar($cur) ? (string) $cur : json_encode($cur),
                    'incoming_value' => is_scalar($incoming) ? (string) $incoming : json_encode($incoming),
                    'reason' => 'manual_edit_after_import', 'status' => 'open',
                    'created_at' => now(), 'updated_at' => now(),
                ];

                continue;
            }
            $apply[$f] = $incoming;
        }

        if ($dry) {
            if ($apply !== []) {
                $this->stats['copies_updated']++;
            }
            $this->stats['conflicts_logged'] += count($conflicts);

            return;
        }
        $conflicts = $this->newConflictsOnly($conflicts);
        if ($conflicts !== []) {
            DB::table('legacy_import_conflicts')->insert($conflicts);
            $this->stats['conflicts_logged'] += count($conflicts);
        }
        if ($apply !== []) {
            $apply['legacy_import_batch_id'] = $batch;
            $apply['legacy_imported_at'] = now();
            $apply['updated_at'] = now();
            DB::table('book_copies')->where('id', $current->id)->update($apply);
            $this->stats['copies_updated']++;
        }
        DB::table('legacy_marc_copies')->where('id', $row->id)->update([
            'book_copy_id' => $current->id,
            'apply_status' => $conflicts !== [] ? 'conflict' : ($apply !== [] ? 'updated' : 'skipped'),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function mapCopy(array $c, array $raw, int $recordId, int $invId, int $batch): array
    {
        // INV.STATE is a separate machine from dbo.STATES (source audit 2026-08-28):
        // 1 = active/in stock, 2 = written off. Never map dbo.STATES labels here.
        $state = (int) ($raw['STATE'] ?? $c['state_raw'] ?? 1);
        $offDate = $this->oleDate($raw['OFFDATE'] ?? null);
        $status = ($state === 2 || $offDate !== null) ? 'written_off' : 'available';

        // T090e is the real inventory number; fall back to the synthetic key only
        // when the source itself is empty.
        $inventory = $this->cut($raw['T090e'] ?? $c['inventory_number'] ?? null, 128);
        $synthetic = false;
        if ($inventory === null || $inventory === '0' || $inventory === '00' || $inventory === '000') {
            $inventory = 'MARC-'.$invId;
            $synthetic = true;
        }

        return [
            'bibliographic_record_id' => $recordId,
            'inventory_number' => $inventory,
            'inventory_number_is_synthetic' => $synthetic,
            'legacy_inventory_number' => $this->cut($raw['T090e'] ?? null, 128),
            'legacy_inv_id' => $invId,
            'legacy_doc_id' => (int) ($raw['DOC_ID'] ?? 0) ?: null,
            'barcode' => $this->cut($raw['T876p'] ?? null, 128),
            'ksu_number' => $this->cut($raw['T990t'] ?? null, 64),           // T990t = КСУ number
            'storage_sigla' => $this->cut($raw['T090f'] ?? null, 255),       // T090f = sigla text
            'sigla_code' => $this->cut($raw['T090f'] ?? null, 64),
            'legacy_sigla_id' => isset($raw['SIGLA_ID']) ? (int) $raw['SIGLA_ID'] : null,
            'shelf_index' => $this->cut($raw['TRACKINDEX'] ?? null, 128),    // TRACKINDEX = polochny index
            'fund_raw' => $this->cut($raw['T090w'] ?? null, 128),            // caption conflict: raw only
            'price' => $this->decimal($raw['T876c'] ?? null),
            'price_raw' => $this->cut($raw['T876c'] ?? null, 64),
            'acquisition_source' => $this->cut($raw['SOURCE'] ?? null, 255),
            'registration_date' => $this->oleDate($raw['REGDATE'] ?? null),
            'accounting_mode_raw' => $this->cut($raw['INVMODE'] ?? null, 16),
            'accounting_type' => match (strtoupper(trim((string) ($raw['INVMODE'] ?? '')))) {
                'I' => 'inventory', 'U' => 'non_inventory', default => null,
            },
            'writeoff_date' => $offDate,
            'writeoff_act' => $this->cut($raw['WROFFACT'] ?? null, 128),
            'writeoff_reason' => $state === 2 ? $this->cut($raw['NOTES'] ?? null, 255) : null,
            'legacy_notes' => $state === 2 ? null : ($raw['NOTES'] ?? null),
            'legacy_state_raw' => $state,
            'legacy_state_label' => $state === 2 ? 'Списан (INV.STATE=2)' : 'В фонде (INV.STATE=1)',
            'status' => $status,
            'legacy_import_batch_id' => $batch,
            'legacy_imported_at' => now(),
        ];
    }

    // ── Contributors / subjects ──────────────────────────────────────────

    private function applyRelations(int $batch, int $chunk, bool $dry): void
    {
        $this->line('Contributors / subjects...');
        $lastId = 0;
        while (true) {
            $rows = DB::table('legacy_marc_records')
                ->where('legacy_import_batch_id', $batch)
                ->whereNotNull('bibliographic_record_id')
                ->where('id', '>', $lastId)->orderBy('id')->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }
            if (! $dry) {
                DB::transaction(function () use ($rows) {
                    foreach ($rows as $row) {
                        $c = json_decode((string) $row->canonical, true) ?: [];
                        $rid = (int) $row->bibliographic_record_id;
                        $pos = 0;
                        foreach (array_merge((array) ($c['authors'] ?? []), (array) ($c['additional_authors'] ?? [])) as $i => $name) {
                            $name = trim((string) $name);
                            if ($name === '') {
                                continue;
                            }
                            $cid = $this->contributorId($name);
                            DB::table('bibliographic_record_contributor')->upsert([[
                                'bibliographic_record_id' => $rid, 'contributor_id' => $cid,
                                'role' => 'author', 'position' => $pos++,
                                'marc_tag' => $i === 0 ? '100' : '700',
                                'created_at' => now(), 'updated_at' => now(),
                            ]], ['bibliographic_record_id', 'contributor_id', 'role'], ['position', 'updated_at']);
                            $this->stats['contributors_linked']++;
                        }
                        $spos = 0;
                        foreach ((array) ($c['subjects'] ?? []) as $term) {
                            $term = trim((string) $term);
                            if ($term === '') {
                                continue;
                            }
                            $sid = $this->subjectId($term);
                            DB::table('bibliographic_record_subject')->upsert([[
                                'bibliographic_record_id' => $rid, 'subject_id' => $sid,
                                'position' => $spos++, 'marc_tag' => '650',
                                'created_at' => now(), 'updated_at' => now(),
                            ]], ['bibliographic_record_id', 'subject_id'], ['position', 'updated_at']);
                            $this->stats['subjects_linked']++;
                        }
                    }
                });
            }
            $lastId = (int) $rows->last()->id;
            $this->output->write('.');
        }
        $this->newLine();
    }

    private function contributorId(string $name): int
    {
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? $name);
        $norm = mb_substr($norm, 0, 500);
        $id = DB::table('contributors')->where('normalized_name', $norm)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('contributors')->insertGetId([
            'name' => mb_substr($name, 0, 500), 'normalized_name' => $norm,
            'kind' => 'person', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function subjectId(string $term): int
    {
        $norm = mb_substr(mb_strtolower(preg_replace('/\s+/u', ' ', trim($term)) ?? $term), 0, 500);
        $id = DB::table('subjects')->where('normalized_term', $norm)->where('scheme', 'topical')->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table('subjects')->insertGetId([
            'term' => mb_substr($term, 0, 500), 'normalized_term' => $norm,
            'scheme' => 'topical', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Drops conflicts that are already recorded, so a re-run never re-queues the
     * same librarian decision.
     *
     * @param  list<array<string,mixed>>  $conflicts
     * @return list<array<string,mixed>>
     */
    private function newConflictsOnly(array $conflicts): array
    {
        $out = [];
        foreach ($conflicts as $c) {
            $exists = DB::table('legacy_import_conflicts')
                ->where('entity_type', $c['entity_type'])
                ->where('entity_id', $c['entity_id'])
                ->where('field_name', $c['field_name'])
                ->where('source_id', $c['source_id'])
                ->exists();
            if (! $exists) {
                $out[] = $c;
            }
        }

        return $out;
    }

    private function hasOpenConflict(string $type, int $id): bool
    {
        return DB::table('legacy_import_conflicts')
            ->where('entity_type', $type)->where('entity_id', $id)->where('status', 'open')->exists();
    }

    private function same(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return (string) $a === (string) $b;
    }

    private function firstOf(mixed $v): ?string
    {
        if (is_array($v)) {
            foreach ($v as $x) {
                $x = trim((string) $x);
                if ($x !== '') {
                    return $x;
                }
            }

            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function cut(mixed $v, int $len): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? (string) $v);

        return $s === '' ? null : mb_substr($s, 0, $len);
    }

    private function year(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (preg_match('/(1[0-9]{3}|20[0-9]{2})/', (string) $v, $m)) {
            $y = (int) $m[1];

            return ($y >= 1000 && $y <= (int) date('Y') + 1) ? $y : null;
        }

        return null;
    }

    /** OLE Automation date -> ISO date. 45712 = 2025-02-24 (source audit). */
    private function oleDate(mixed $serial): ?string
    {
        if ($serial === null || ! is_numeric($serial) || (float) $serial <= 0) {
            return null;
        }
        $ts = ((int) floor((float) $serial) - 25569) * 86400;
        if ($ts < -2208988800 || $ts > 4102444800) {
            return null;
        }

        return gmdate('Y-m-d', $ts);
    }

    private function decimal(mixed $v): ?string
    {
        if ($v === null || ! is_numeric(trim((string) $v))) {
            return null;
        }
        $d = round((float) $v, 2);

        return ($d >= 0 && $d <= 9999999999.99) ? number_format($d, 2, '.', '') : null;
    }

    /** MARC 005: yyyymmddhhmmss.f */
    private function marc005(mixed $v): ?string
    {
        $s = preg_replace('/[^0-9]/', '', (string) $v) ?? '';
        if (strlen($s) < 8) {
            return null;
        }
        $y = (int) substr($s, 0, 4);
        if ($y < 1900 || $y > 2100) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d+00', $y, (int) substr($s, 4, 2), (int) substr($s, 6, 2),
            (int) substr($s, 8, 2), (int) substr($s, 10, 2), (int) substr($s, 12, 2));
    }
}
