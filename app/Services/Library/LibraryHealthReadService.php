<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class LibraryHealthReadService
{
    /**
     * @return array{data: array<string, int>, source: string}
     */
    public function summary(): array
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT
              (SELECT COUNT(*) FROM app.documents) AS total_documents,
              (SELECT COUNT(*) FROM app.book_copies) AS total_copies,
              (SELECT COUNT(*) FROM app.readers) AS total_readers,
              (SELECT COUNT(*) FROM review.quality_issues) AS total_quality_issues,
              (SELECT COUNT(*) FROM app.documents WHERE needs_review IS TRUE) AS documents_needs_review,
              (SELECT COUNT(*) FROM app.book_copies WHERE needs_review IS TRUE) AS copies_needs_review,
              (SELECT COUNT(*) FROM app.readers WHERE needs_review IS TRUE) AS readers_needs_review,
              (SELECT COUNT(*) FROM app.documents WHERE isbn_normalized IS NULL OR BTRIM(isbn_normalized) = '') AS documents_without_isbn,
              (SELECT COUNT(*) FROM app.documents d WHERE NOT EXISTS (SELECT 1 FROM app.document_authors da WHERE da.document_id = d.id)) AS documents_without_author,
              (SELECT COUNT(*) FROM app.book_copies WHERE document_id IS NULL) AS orphan_copies,
              (SELECT COUNT(*) FROM app.documents WHERE publisher_id IS NULL) AS documents_without_publisher,
              (SELECT COUNT(*) FROM app.documents d WHERE NOT EXISTS (SELECT 1 FROM app.document_subjects ds WHERE ds.document_id = d.id)) AS documents_without_subject,
              (SELECT COUNT(*) FROM (SELECT isbn_normalized FROM app.documents WHERE isbn_normalized IS NOT NULL AND BTRIM(isbn_normalized) <> '' GROUP BY isbn_normalized HAVING COUNT(*) > 1) duplicate_groups) AS duplicate_isbn_groups
            SQL
        );

        return [
            'data' => [
                'totalDocuments' => (int) ($row->total_documents ?? 0),
                'totalCopies' => (int) ($row->total_copies ?? 0),
                'totalReaders' => (int) ($row->total_readers ?? 0),
                'totalQualityIssues' => (int) ($row->total_quality_issues ?? 0),
                'documentsNeedsReview' => (int) ($row->documents_needs_review ?? 0),
                'copiesNeedsReview' => (int) ($row->copies_needs_review ?? 0),
                'readersNeedsReview' => (int) ($row->readers_needs_review ?? 0),
                'documentsWithoutIsbn' => (int) ($row->documents_without_isbn ?? 0),
                'documentsWithoutAuthor' => (int) ($row->documents_without_author ?? 0),
                'orphanCopies' => (int) ($row->orphan_copies ?? 0),
                'documentsWithoutPublisher' => (int) ($row->documents_without_publisher ?? 0),
                'documentsWithoutSubject' => (int) ($row->documents_without_subject ?? 0),
                'duplicateIsbnGroups' => (int) ($row->duplicate_isbn_groups ?? 0),
            ],
            'source' => 'app.documents, app.book_copies, app.readers, review.quality_issues',
        ];
    }
}
