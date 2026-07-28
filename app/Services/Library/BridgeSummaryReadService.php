<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BridgeSummaryReadService
{
    /**
     * @return array{data: array<string, int>, warnings: array<int, string>, notes: array<int, string>, source: string}
     */
    public function summary(): array
    {
        $hasPublicUser = $this->tableExists('public', 'User');
        $hasPublicBook = $this->tableExists('public', 'Book');
        $hasPublicBookCopy = $this->tableExists('public', 'BookCopy');

        $hasAppReaders = $this->tableExists('app', 'readers');
        $hasAppReaderContacts = $this->tableExists('app', 'reader_contacts');
        $hasAppDocuments = $this->tableExists('app', 'documents');
        $hasAppBookCopies = $this->tableExists('app', 'book_copies');

        $publicUsersTotal = $hasPublicUser ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM public."User"') : 0;
        $appReadersTotal = $hasAppReaders ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM app.readers') : 0;

        $matchedUsersByEmail = 0;
        $matchedAppReadersByEmail = 0;
        if ($hasPublicUser && $hasAppReaderContacts) {
            $row = DB::selectOne(
                <<<'SQL'
                SELECT
                  COUNT(DISTINCT u.id)::bigint AS matched_public_users,
                  COUNT(DISTINCT rc.reader_id)::bigint AS matched_app_readers
                FROM public."User" u
                JOIN app.reader_contacts rc
                  ON lower(btrim(coalesce(u."email", ''))) = lower(btrim(coalesce(rc.value_normalized, '')))
                WHERE rc.contact_type = 'EMAIL'
                  AND btrim(coalesce(u."email", '')) <> ''
                SQL
            );

            $matchedUsersByEmail = (int) ($row->matched_public_users ?? 0);
            $matchedAppReadersByEmail = (int) ($row->matched_app_readers ?? 0);
        }

        $publicBooksTotal = $hasPublicBook ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM public."Book"') : 0;
        $appDocumentsTotal = $hasAppDocuments ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM app.documents') : 0;

        $matchedBooksByIsbn = 0;
        $matchedAppDocumentsByIsbn = 0;
        if ($hasPublicBook && $hasAppDocuments) {
            $row = DB::selectOne(
                <<<'SQL'
                SELECT
                  COUNT(DISTINCT b.id)::bigint AS matched_public_books,
                  COUNT(DISTINCT d.id)::bigint AS matched_app_documents
                FROM public."Book" b
                JOIN app.documents d
                  ON regexp_replace(lower(coalesce(b."isbn", '')), '[^0-9x]', '', 'g')
                     = regexp_replace(lower(coalesce(d.isbn_normalized, '')), '[^0-9x]', '', 'g')
                WHERE btrim(coalesce(b."isbn", '')) <> ''
                  AND btrim(coalesce(d.isbn_normalized, '')) <> ''
                SQL
            );

            $matchedBooksByIsbn = (int) ($row->matched_public_books ?? 0);
            $matchedAppDocumentsByIsbn = (int) ($row->matched_app_documents ?? 0);
        }

        $publicBookCopiesTotal = $hasPublicBookCopy ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM public."BookCopy"') : 0;
        $appBookCopiesTotal = $hasAppBookCopies ? $this->countSql('SELECT COUNT(*)::bigint AS aggregate_count FROM app.book_copies') : 0;

        $matchedCopiesByInventory = 0;
        $matchedAppCopiesByInventory = 0;
        if ($hasPublicBookCopy && $hasAppBookCopies) {
            $row = DB::selectOne(
                <<<'SQL'
                SELECT
                  COUNT(DISTINCT bc.id)::bigint AS matched_public_copies,
                  COUNT(DISTINCT ac.id)::bigint AS matched_app_copies
                FROM public."BookCopy" bc
                JOIN app.book_copies ac
                  ON lower(btrim(coalesce(bc."inventoryNumber", '')))
                     = lower(btrim(coalesce(ac.inventory_number_normalized, '')))
                WHERE btrim(coalesce(bc."inventoryNumber", '')) <> ''
                  AND btrim(coalesce(ac.inventory_number_normalized, '')) <> ''
                SQL
            );

            $matchedCopiesByInventory = (int) ($row->matched_public_copies ?? 0);
            $matchedAppCopiesByInventory = (int) ($row->matched_app_copies ?? 0);
        }

        $warnings = [];
        if (! $hasPublicUser || ! $hasPublicBook || ! $hasPublicBookCopy) {
            $warnings[] = 'public circulation tables are not fully available';
        }

        if (! $hasAppReaders || ! $hasAppReaderContacts || ! $hasAppDocuments || ! $hasAppBookCopies) {
            $warnings[] = 'app library domain tables are not fully available';
        }

        $hasPublicSideData = $publicUsersTotal > 0 || $publicBooksTotal > 0 || $publicBookCopiesTotal > 0;
        $hasAppSideData = $appReadersTotal > 0 || $appDocumentsTotal > 0 || $appBookCopiesTotal > 0;
        $noMatchesDetected = $matchedUsersByEmail === 0
            && $matchedBooksByIsbn === 0
            && $matchedCopiesByInventory === 0;

        if ($hasPublicSideData && $hasAppSideData && $noMatchesDetected) {
            $warnings[] = 'no factual linkage detected';
            $warnings[] = 'public circulation domain appears isolated from app domain';
        }

        return [
            'data' => [
                'publicUsersTotal' => $publicUsersTotal,
                'appReadersTotal' => $appReadersTotal,
                'matchedUsersByEmail' => $matchedUsersByEmail,
                'unmatchedPublicUsers' => max($publicUsersTotal - $matchedUsersByEmail, 0),
                'unmatchedAppReaders' => max($appReadersTotal - $matchedAppReadersByEmail, 0),
                'publicBooksTotal' => $publicBooksTotal,
                'appDocumentsTotal' => $appDocumentsTotal,
                'matchedBooksByIsbn' => $matchedBooksByIsbn,
                'unmatchedPublicBooks' => max($publicBooksTotal - $matchedBooksByIsbn, 0),
                'unmatchedAppDocuments' => max($appDocumentsTotal - $matchedAppDocumentsByIsbn, 0),
                'publicBookCopiesTotal' => $publicBookCopiesTotal,
                'appBookCopiesTotal' => $appBookCopiesTotal,
                'matchedCopiesByInventory' => $matchedCopiesByInventory,
                'unmatchedPublicCopies' => max($publicBookCopiesTotal - $matchedCopiesByInventory, 0),
                'unmatchedAppCopies' => max($appBookCopiesTotal - $matchedAppCopiesByInventory, 0),
            ],
            'warnings' => $warnings,
            'notes' => [
                'read-only bridge snapshot between public circulation and app library domains',
            ],
            'source' => 'public."User", public."Book", public."BookCopy", app.readers, app.reader_contacts, app.documents, app.book_copies',
        ];
    }

    private function tableExists(string $schema, string $table): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS table_exists
            SQL,
            [$schema, $table]
        );

        return (bool) ($row->table_exists ?? false);
    }

    private function countSql(string $sql): int
    {
        $row = DB::selectOne($sql);

        return (int) ($row->aggregate_count ?? 0);
    }
}
