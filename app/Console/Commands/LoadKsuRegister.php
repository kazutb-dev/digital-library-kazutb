<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the legacy КСУ register (part 1 «Поступление») into the ksu_* module.
 *
 * Per the 2026-08-28 source audit:
 *  - INV.T990t is the КСУ record/lot number in "N/YYYY" form;
 *  - ksu1 is a materialized reporting snapshot, NOT the authoritative ledger —
 *    INV is. 70 distinct T990t values exist against only 20 ksu1 rows;
 *  - no enforced legacy NEXT_KSU_NUMBER algorithm exists, so automatic
 *    allocation is created but left DISABLED pending a librarian decision.
 *
 * Exact links are applied; unresolved links go to review; copies without a КСУ
 * number are left alone — that is a legitimate source state, not a defect.
 */
class LoadKsuRegister extends Command
{
    protected $signature = 'marc:load-ksu {--path=} {--dry-run}';

    protected $description = 'Load the legacy КСУ register, links and review queues';

    public function handle(): int
    {
        $path = rtrim((string) $this->option('path'), '/');
        if (! is_dir($path)) {
            $this->error('--path must be the extracted package directory.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $stats = ['books' => 0, 'entries' => 0, 'items_linked' => 0, 'items_unlinked' => 0,
            'unresolved_queued' => 0, 'sequences' => 0];

        // ── Books ────────────────────────────────────────────────────────
        $bookIdByCode = [];
        foreach ($this->csv($path.'/ksu/ksu_books.csv') as $r) {
            $code = trim((string) ($r['canonical_code'] ?? '')) ?: 'KSU-1';
            if (! $dry) {
                DB::table('ksu_books')->upsert([[
                    'code' => $code,
                    'name' => trim((string) ($r['name'] ?? $code)),
                    'description' => $r['description'] ?? null,
                    'legacy_source_table' => $r['source_table'] ?? null,
                    'numbering_format' => trim((string) ($r['numbering_format'] ?? 'number/year')) ?: 'number/year',
                    'reset_period' => $r['reset_period'] ?? null,
                    // The audit proves no enforced algorithm; stays off.
                    'auto_numbering_enabled' => false,
                    'numbering_rule_evidence' => $r['evidence'] ?? null,
                    'requires_manual_decision' => true,
                    'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['code'], ['name', 'description', 'legacy_source_table', 'numbering_format',
                    'reset_period', 'numbering_rule_evidence', 'updated_at']);
                $bookIdByCode[$code] = (int) DB::table('ksu_books')->where('code', $code)->value('id');
            }
            $stats['books']++;
        }
        $defaultBook = $bookIdByCode['KSU-1'] ?? null;

        // ── Sequences (observed only; allocation disabled) ───────────────
        $seqFile = $path.'/ksu/ksu_sequence_state.json';
        if (is_file($seqFile) && ! $dry && $defaultBook !== null) {
            $raw = ltrim((string) file_get_contents($seqFile), "\xEF\xBB\xBF \t\r\n");
            foreach ((array) json_decode($raw, true) as $s) {
                $code = (string) ($s['ksu_book'] ?? 'KSU-1');
                $bid = $bookIdByCode[$code] ?? $defaultBook;
                DB::table('ksu_sequences')->upsert([[
                    'ksu_book_id' => $bid,
                    'year' => (int) $s['year'],
                    'last_number' => (int) ($s['last_observed'] ?? 0),
                    'min_observed' => isset($s['minimum']) ? (int) $s['minimum'] : null,
                    'max_observed' => isset($s['maximum']) ? (int) $s['maximum'] : null,
                    'missing_numbers' => json_encode($s['missing_numbers'] ?? [], JSON_UNESCAPED_UNICODE),
                    'duplicate_numbers' => json_encode($s['duplicate_numbers'] ?? [], JSON_UNESCAPED_UNICODE),
                    'allocation_enabled' => false,
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['ksu_book_id', 'year'], ['last_number', 'min_observed', 'max_observed',
                    'missing_numbers', 'duplicate_numbers', 'updated_at']);
                $stats['sequences']++;
            }
        }

        // ── Entries ──────────────────────────────────────────────────────
        $entryIdByLegacy = [];
        foreach ($this->csv($path.'/ksu/ksu_entries.csv') as $r) {
            $legacy = trim((string) ($r['legacy_ksu_id'] ?? ''));
            if ($legacy === '') {
                continue;
            }
            [$num, $year] = $this->splitKsu($legacy, (int) ($r['entry_year'] ?? 0));
            if (! $dry && $defaultBook !== null) {
                DB::table('ksu_entries')->upsert([[
                    'ksu_book_id' => $defaultBook,
                    'entry_number' => $legacy,
                    'number' => $num,
                    'year' => $year,
                    'entry_date' => $this->date($r['entry_date_iso'] ?? null),
                    'acquisition_source' => $this->nz($r['source_normalized'] ?? $r['source_raw'] ?? null),
                    'title_count' => (int) ($r['title_count'] ?? 0),
                    'copy_count' => (int) ($r['copy_count'] ?? 0),
                    'total_cost' => is_numeric($r['total_cost_normalized'] ?? null) ? (float) $r['total_cost_normalized'] : null,
                    'total_cost_raw' => $this->nz($r['total_cost_raw'] ?? null),
                    'status' => 'legacy',
                    'legacy_ksu_id' => $legacy,
                    'legacy_source_table' => $this->nz($r['source_table'] ?? null),
                    'legacy_breakdown' => json_encode($this->breakdown($r), JSON_UNESCAPED_UNICODE),
                    'source_row_hash' => $this->nz($r['source_row_hash'] ?? null),
                    'created_at' => now(), 'updated_at' => now(),
                ]], ['ksu_book_id', 'entry_number'], ['entry_date', 'acquisition_source', 'title_count',
                    'copy_count', 'total_cost', 'total_cost_raw', 'legacy_breakdown', 'updated_at']);
                $entryIdByLegacy[$legacy] = (int) DB::table('ksu_entries')
                    ->where('ksu_book_id', $defaultBook)->where('entry_number', $legacy)->value('id');
            }
            $stats['entries']++;
        }

        // ── Exact entry items (2,408) ────────────────────────────────────
        $buf = [];
        foreach ($this->csv($path.'/ksu/ksu_entry_items.csv') as $r) {
            $ksuNum = trim((string) ($r['ksu_number_from_inv'] ?? ''));
            $entryId = $entryIdByLegacy[$ksuNum] ?? null;
            $invId = (int) ($r['source_inv_id'] ?? 0);
            if ($entryId === null || $invId === 0) {
                $stats['items_unlinked']++;

                continue;
            }
            $copyId = DB::table('book_copies')->where('legacy_inv_id', $invId)->value('id');
            $buf[] = [
                'ksu_entry_id' => $entryId,
                'book_copy_id' => $copyId,
                'bibliographic_record_id' => $copyId ? DB::table('book_copies')->where('id', $copyId)->value('bibliographic_record_id') : null,
                'source_inv_id' => $invId,
                'source_doc_id' => (int) ($r['source_doc_id'] ?? 0) ?: null,
                'inventory_number' => $this->nz($r['inventory_number'] ?? null),
                'price' => is_numeric($r['price'] ?? null) ? (float) $r['price'] : null,
                'registration_date' => $this->date($r['registration_date'] ?? null),
                'link_method' => $this->nz($r['link_method'] ?? null),
                'link_confidence' => $this->nz($r['link_confidence'] ?? null) ?: 'high',
                'created_at' => now(), 'updated_at' => now(),
            ];
            $stats['items_linked']++;
            if (count($buf) >= 500 && ! $dry) {
                DB::table('ksu_entry_items')->upsert($buf, ['ksu_entry_id', 'source_inv_id'],
                    ['book_copy_id', 'bibliographic_record_id', 'inventory_number', 'price', 'registration_date', 'updated_at']);
                $buf = [];
                $this->output->write('.');
            }
        }
        if ($buf !== [] && ! $dry) {
            DB::table('ksu_entry_items')->upsert($buf, ['ksu_entry_id', 'source_inv_id'],
                ['book_copy_id', 'bibliographic_record_id', 'inventory_number', 'price', 'registration_date', 'updated_at']);
        }
        $this->newLine();

        // ── Unresolved links (4,809) → review queue, never auto-linked ────
        // Re-running must not duplicate the queue: drop the previous unresolved
        // set for this book first. Rows a librarian already resolved are kept.
        if (! $dry) {
            DB::table('ksu_conflicts')
                ->where('kind', 'unresolved_link')
                ->where('status', 'open')
                ->when($defaultBook !== null, fn ($q2) => $q2->where('ksu_book_id', $defaultBook))
                ->delete();
        }
        $q = [];
        foreach ($this->csv($path.'/quarantine/unresolved_ksu_links.csv') as $r) {
            $q[] = [
                'ksu_book_id' => $defaultBook,
                'kind' => 'unresolved_link',
                'ksu_number_raw' => $this->nz($r['ksu_number'] ?? null),
                'source_inv_id' => (int) ($r['source_inv_id'] ?? 0) ?: null,
                'source_doc_id' => (int) ($r['source_doc_id'] ?? 0) ?: null,
                'reason' => (string) ($r['reason_code'] ?? 'NO_EXACT_KSU1_MATCH'),
                'payload' => json_encode($r, JSON_UNESCAPED_UNICODE),
                'status' => 'open',
                'created_at' => now(), 'updated_at' => now(),
            ];
            $stats['unresolved_queued']++;
            if (count($q) >= 1000 && ! $dry) {
                DB::table('ksu_conflicts')->insert($q);
                $q = [];
                $this->output->write('+');
            }
        }
        if ($q !== [] && ! $dry) {
            DB::table('ksu_conflicts')->insert($q);
        }
        $this->newLine();

        if (! $dry) {
            DB::table('ksu_audit_events')->insert([
                'event_type' => 'legacy.imported',
                'ksu_book_id' => $defaultBook,
                'actor_name' => 'MARC recovery',
                'new_values' => json_encode($stats, JSON_UNESCAPED_UNICODE),
                'reason' => 'Legacy КСУ register imported from verified MARC-SQL package.',
                'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($stats as $k => $v) {
            $this->line(sprintf('  %-20s %d', $k, $v));
        }

        return self::SUCCESS;
    }

    /** @return \Generator<int,array<string,string>> */
    private function csv(string $file): \Generator
    {
        if (! is_file($file)) {
            return;
        }
        $h = fopen($file, 'r');
        if ($h === false) {
            return;
        }
        $head = fgetcsv($h, 0, ',', '"', '\\');
        if ($head === false) {
            fclose($h);

            return;
        }
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
        while (($row = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            if (count($row) === 1 && ($row[0] ?? '') === '') {
                continue;
            }
            $out = [];
            foreach ($head as $i => $k) {
                $out[(string) $k] = (string) ($row[$i] ?? '');
            }
            yield $out;
        }
        fclose($h);
    }

    /** @return array{0:int,1:int} */
    private function splitKsu(string $legacy, int $fallbackYear): array
    {
        if (preg_match('#^(\d+)\s*/\s*(\d{4})$#', trim($legacy), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [0, $fallbackYear ?: 0];
    }

    /** @param array<string,string> $r */
    private function breakdown(array $r): array
    {
        $out = [];
        foreach ($r as $k => $v) {
            if (preg_match('/^m\d+_raw$/', $k)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private function nz(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function date(mixed $v): ?string
    {
        $s = trim((string) $v);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }
}
