<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class SubjectReadService
{
    /**
     * @return array{faculties: list<array>, departments: list<array>, specializations: list<array>}
     */
    public function listGrouped(): array
    {
        $rows = DB::select("
            SELECT
                s.id,
                s.display_subject,
                ds.source_kind,
                COUNT(DISTINCT ds.document_id) AS doc_count
            FROM app.subjects s
            JOIN app.document_subjects ds ON ds.subject_id = s.id
            GROUP BY s.id, s.display_subject, ds.source_kind
            HAVING COUNT(DISTINCT ds.document_id) > 0
            ORDER BY ds.source_kind, COUNT(DISTINCT ds.document_id) DESC
        ");

        $grouped = [
            'faculties' => [],
            'departments' => [],
            'specializations' => [],
        ];

        foreach ($rows as $row) {
            $item = [
                'id' => (string) $row->id,
                'label' => (string) $row->display_subject,
                'documentCount' => (int) $row->doc_count,
            ];

            match ($row->source_kind) {
                'faculty' => $grouped['faculties'][] = $item,
                'department' => $grouped['departments'][] = $item,
                'specialization' => $grouped['specializations'][] = $item,
                default => null,
            };
        }

        return $grouped;
    }
}
