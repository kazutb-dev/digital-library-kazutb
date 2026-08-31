<?php

declare(strict_types=1);

// ============================================================================
// DEPRECATED — DESTRUCTIVE. DO NOT RUN.
// ============================================================================
// This script performed the 2026-08-12 catalogue import and is the confirmed
// cause of the MARC attribute loss documented in the migration audit:
//
//   * It begins by executing DELETE FROM book_copies and
//     DELETE FROM bibliographic_records — it REPLACES the whole catalogue.
//   * It maps ~15 fields. Copy-level attributes (barcode, KSU, price,
//     acquisition source, registration date, fund, shelf, write-off status,
//     circulation counters) are never written.
//   * It hard-codes is_draft=false, status='available', condition='new'
//     and branch_id=1, and synthesises inventory numbers as 'MARC-<INV_ID>',
//     discarding the real ones.
//   * It stores only a sha256 of each source row, so the raw MARC is lost.
//
// The supported importer is the non-destructive, field-rich Artisan command:
//
//     php artisan marc:import-catalog
//
// It upserts through marc_import_records and preserves the existing catalogue.
//
// This file is retained for forensic reference only. To run it anyway you must
// set MARC_LEGACY_IMPORTER_ACKNOWLEDGE_DATA_LOSS explicitly (see guard below),
// on top of the pre-existing confirmation and non-production guards.
// ============================================================================

$acknowledgement = getenv('MARC_LEGACY_IMPORTER_ACKNOWLEDGE_DATA_LOSS') ?: '';
if ($acknowledgement !== 'I_KNOW_THIS_SCRIPT_CAUSED_THE_2026_08_12_DATA_LOSS') {
    fwrite(STDERR, <<<'TXT'

    ============================================================
     REFUSING TO RUN: deprecated destructive MARC importer
    ============================================================
     This script DELETES the entire catalogue before importing and
     drops most MARC and copy attributes. It caused the 2026-08-12
     data loss.

     Use the supported importer instead:

         php artisan marc:import-catalog

     If you genuinely need this legacy script, set:

         MARC_LEGACY_IMPORTER_ACKNOWLEDGE_DATA_LOSS=\
             I_KNOW_THIS_SCRIPT_CAUSED_THE_2026_08_12_DATA_LOSS

    ============================================================

    TXT);
    exit(3);
}

// Usage: php import-marcsql-stream.php docs|copies

$mode = $argv[1] ?? '';
if (!in_array($mode, ['docs', 'copies'], true)) {
    fwrite(STDERR, "Usage: import-marcsql-stream.php docs|copies\n");
    exit(2);
}

$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$confirmation = getenv('MARC_IMPORT_CONFIRM') ?: '';
$expectedConfirmation = $mode === 'docs' ? 'REPLACE_CATALOG' : 'APPEND_COPIES';

if ($database === '' || $username === '' || $password === '') {
    fwrite(STDERR, "DB_DATABASE, DB_USERNAME and DB_PASSWORD must be set explicitly.\n");
    exit(2);
}

if ($confirmation !== $expectedConfirmation) {
    fwrite(STDERR, "Refusing MARC import: set MARC_IMPORT_CONFIRM={$expectedConfirmation} explicitly.\n");
    exit(2);
}

$isDisposableTarget = str_ends_with($database, '_test') || str_ends_with($database, '_recovery');
if (! $isDisposableTarget && getenv('MARC_IMPORT_ALLOW_PRODUCTION') !== 'I_UNDERSTAND_THIS_REPLACES_THE_CATALOG') {
    fwrite(STDERR, "Refusing MARC import into non-test/recovery database '{$database}'.\n");
    exit(2);
}

$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST') ?: 'postgres', getenv('DB_PORT') ?: '5432', $database),
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

function clean(?string $value): ?string
{
    if ($value === null) return null;
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
    }
    $value = @iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    return $value === '' ? null : $value;
}

function marcFields(string $raw): array
{
    $fields = [];
    foreach (explode("\x1e", $raw) as $field) {
        $field = trim($field, " \t\r\n\0");
        if (strlen($field) < 3 || !ctype_digit(substr($field, 0, 3))) continue;
        $tag = substr($field, 0, 3);
        $body = substr($field, 3);
        $parts = explode("\x1f", $body);
        $value = clean($parts[0]);
        $sub = [];
        foreach (array_slice($parts, 1) as $part) {
            if ($part === '') continue;
            $code = $part[0];
            $sub[$code][] = clean(substr($part, 1));
        }
        $fields[$tag][] = ['value' => $value, 'sub' => $sub];
    }
    return $fields;
}

function firstSub(array $fields, string $tag, string $code): ?string
{
    foreach ($fields[$tag] ?? [] as $field) {
        foreach ($field['sub'][$code] ?? [] as $value) if ($value) return $value;
    }
    return null;
}

function allSubs(array $fields, string $tag, string $code): array
{
    $result = [];
    foreach ($fields[$tag] ?? [] as $field) foreach ($field['sub'][$code] ?? [] as $value) if ($value) $result[] = $value;
    return array_values(array_unique($result));
}

function firstValue(array $fields, string $tag): ?string
{
    return clean($fields[$tag][0]['value'] ?? null);
}

$now = (new DateTimeImmutable())->format('Y-m-d H:i:sP');
$pdo->beginTransaction();
try {
    if ($mode === 'docs') {
        // The database currently contains only demo seed records. Remove only catalog rows;
        // the imported records become the authoritative catalog.
        $pdo->exec('DELETE FROM public.book_copies');
        $pdo->exec('DELETE FROM public.bibliographic_records');
        $stmt = $pdo->prepare('INSERT INTO public.bibliographic_records
            (title, subtitle, primary_author, additional_authors, publisher, publication_year, language,
             udc_code, annotation, keywords, isbn, resource_type, notes, is_draft, needs_manual_review,
             merge_status, legacy_external_id, created_at, updated_at)
            VALUES (:title,:subtitle,:author,:additional,:publisher,:year,:language,:udc,:annotation,:keywords,
                    :isbn,:type,:notes,false,false,\'active\',:legacy,:now,:now) RETURNING id');
        $audit = $pdo->prepare('INSERT INTO public.marc_import_records
            (source_doc_id,bibliographic_record_id,source_hash,created_at,updated_at) VALUES (:source,:id,:hash,:now,:now)');

        $count = 0;
        while (($line = fgets(STDIN)) !== false) {
            $row = str_getcsv(rtrim($line, "\r\n"), "\t", '"', "\\");
            if (count($row) < 4 || !ctype_digit(trim($row[0]))) continue;
            $sourceId = (int) trim($row[0]);
            $raw = $row[3] ?? '';
            $f = marcFields($raw);
            $title = firstSub($f, '245', 'a') ?: firstSub($f, '245', 'b') ?: '[Без заглавия]';
            $subtitle = firstSub($f, '245', 'b');
            $authors = array_values(array_unique(array_merge(allSubs($f, '100', 'a'), allSubs($f, '700', 'a'))));
            $year = null;
            $date = firstSub($f, '260', 'c') ?: firstSub($f, '264', 'c');
            if ($date && preg_match('/(1[5-9]\d{2}|20\d{2})/', $date, $m)) $year = (int) $m[1];
            $language = strtolower(firstSub($f, '041', 'a') ?: 'ru');
            if (!preg_match('/^[a-z0-9_-]{1,8}$/', $language)) $language = 'ru';
            $isbn = firstSub($f, '020', 'a');
            $isbn = $isbn ? preg_replace('/[^0-9Xx-]/', '', $isbn) : null;
            $keywords = array_values(array_unique(array_merge(allSubs($f, '650', 'a'), allSubs($f, '653', 'a'))));
            $udc = firstSub($f, '080', 'a') ?: firstSub($f, '090', 'a');
            $type = strtolower(firstValue($f, '000') ?: 'book');
            $type = in_array($type, ['book', 'textbook', 'study_guide', 'journal', 'thesis', 'map', 'other'], true) ? $type : 'book';
            $stmt->execute([
                ':title' => mb_substr($title, 0, 1000), ':subtitle' => $subtitle ? mb_substr($subtitle, 0, 1000) : null,
                ':author' => $authors[0] ?? null, ':additional' => $authors ? json_encode(array_slice($authors, 1), JSON_UNESCAPED_UNICODE) : null,
                ':publisher' => firstSub($f, '260', 'b') ?: firstSub($f, '264', 'b'), ':year' => $year,
                ':language' => $language, ':udc' => $udc, ':annotation' => firstSub($f, '520', 'a'),
                ':keywords' => $keywords ? json_encode($keywords, JSON_UNESCAPED_UNICODE) : null, ':isbn' => $isbn,
                ':type' => $type, ':notes' => 'Imported from MARC-SQL backup; source DOC_ID='.$sourceId,
                ':legacy' => 'marc:'.$sourceId, ':now' => $now,
            ]);
            $id = (int) $stmt->fetchColumn();
            $audit->execute([':source' => $sourceId, ':id' => $id, ':hash' => hash('sha256', $raw), ':now' => $now]);
            $count++;
            if (($count % 500) === 0) fwrite(STDERR, "docs: {$count}\n");
        }
        fwrite(STDERR, "docs imported: {$count}\n");
    } else {
        $map = $pdo->query("SELECT substring(legacy_external_id from 6)::bigint AS source_id, id FROM public.bibliographic_records WHERE legacy_external_id LIKE 'marc:%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        $existing = $pdo->query('SELECT source_inv_id FROM public.marc_import_copies')->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_fill_keys(array_map('intval', $existing), true);
        $stmt = $pdo->prepare('INSERT INTO public.book_copies
            (bibliographic_record_id,inventory_number,barcode,storage_sigla,shelf_location,branch_id,condition,status,access_restriction,created_at,updated_at)
            VALUES (:record,:inventory,:barcode,:sigla,:shelf,1,\'new\',\'available\',\'free\',:now,:now) RETURNING id');
        $audit = $pdo->prepare('INSERT INTO public.marc_import_copies
            (source_inv_id,book_copy_id,source_hash,created_at,updated_at) VALUES (:source,:id,:hash,:now,:now)');
        $count = 0; $skipped = 0;
        while (($line = fgets(STDIN)) !== false) {
            $row = str_getcsv(rtrim($line, "\r\n"), "\t", '"', "\\");
            if (count($row) < 4 || !ctype_digit(trim($row[0]))) continue;
            $inv = (int) trim($row[0]); $sourceDoc = (int) trim($row[1]); $raw = implode("\t", array_slice($row, 2));
            if (isset($existing[$inv])) continue;
            if (!isset($map[$sourceDoc])) { $skipped++; continue; }
            $inventory = 'MARC-'.$inv;
            $stmt->execute([':record' => $map[$sourceDoc], ':inventory' => $inventory, ':barcode' => null,
                ':sigla' => clean($row[2] ?? null), ':shelf' => clean($row[3] ?? null), ':now' => $now]);
            $id = (int) $stmt->fetchColumn();
            $audit->execute([':source' => $inv, ':id' => $id, ':hash' => hash('sha256', $raw), ':now' => $now]);
            $count++;
            if (($count % 5000) === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
            if (($count % 1000) === 0) fwrite(STDERR, "copies: {$count}\n");
        }
        fwrite(STDERR, "copies imported: {$count}; skipped: {$skipped}\n");
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
