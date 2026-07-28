<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BookDetailReadService
{
    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $document = DB::table('app.document_detail_v as d')
            ->select([
                'd.document_id',
                'd.legacy_doc_id',
                'd.title_display',
                'd.title_raw',
                'd.subtitle_raw',
                'd.publication_year',
                'd.language_code',
                'd.language_raw',
                'd.isbn_raw',
                'd.isbn_normalized',
                'd.isbn_is_valid',
                'd.publisher_name',
                'd.authors_json',
                'd.copy_summary_json',
                'd.document_needs_review',
                'd.document_review_reason_codes',
                'd.subjects_json',
                'd.faculty_raw',
                'd.department_raw',
                'd.specialization_raw',
                'd.raw_marc',
            ])
            ->whereRaw(
                "d.isbn_normalized = ? or d.isbn_raw = ? or d.document_id::text = ? or d.legacy_doc_id::text = ?",
                [$identifier, $identifier, $identifier, $identifier]
            )
            ->first();

        if (! $document) {
            return null;
        }

        $authors = $this->decodeJsonValue($document->authors_json);
        $copySummary = $this->decodeJsonValue($document->copy_summary_json);
        $locations = DB::table('app.document_availability_by_location_v')
            ->select([
                'institution_unit_name',
                'institution_unit_code',
                'campus_name',
                'campus_code',
                'service_point_name',
                'service_point_code',
                'total_copy_count',
                'available_copy_count',
                'unavailable_copy_count',
                'review_copy_count',
                'problem_copy_count',
                'orphan_copy_count',
            ])
            ->where('document_id', $document->document_id)
            ->orderByDesc('available_copy_count')
            ->orderByDesc('total_copy_count')
            ->get()
            ->map(function (object $location): array {
                return [
                    'institutionUnit' => [
                        'code' => (string) ($location->institution_unit_code ?? ''),
                        'name' => (string) ($location->institution_unit_name ?? ''),
                    ],
                    'campus' => [
                        'code' => (string) ($location->campus_code ?? ''),
                        'name' => (string) ($location->campus_name ?? ''),
                    ],
                    'servicePoint' => [
                        'code' => (string) ($location->service_point_code ?? ''),
                        'name' => (string) ($location->service_point_name ?? ''),
                    ],
                    'copies' => [
                        'total' => (int) ($location->total_copy_count ?? 0),
                        'available' => (int) ($location->available_copy_count ?? 0),
                        'unavailable' => (int) ($location->unavailable_copy_count ?? 0),
                        'review' => (int) ($location->review_copy_count ?? 0),
                        'problem' => (int) ($location->problem_copy_count ?? 0),
                        'orphan' => (int) ($location->orphan_copy_count ?? 0),
                    ],
                ];
            })
            ->all();

        $primaryAuthor = is_array($authors) && isset($authors[0]['name']) ? (string) $authors[0]['name'] : 'Автор не указан';
        $publisherName = (string) ($document->publisher_name ?: 'Издатель не указан');
        $titleDisplay = (string) ($document->title_display ?: $document->title_raw ?: 'Без названия');
        $titleRaw = (string) ($document->title_raw ?: $document->title_display ?: 'Без названия');
        $subtitle = (string) ($document->subtitle_raw ?: '');
        $isbnRaw = (string) ($document->isbn_normalized ?: $document->isbn_raw ?: '');
        $languageCode = (string) ($document->language_code ?: '');
        $languageRaw = (string) ($document->language_raw ?: $languageCode ?: '');
        $availableCopies = is_array($copySummary) ? (int) ($copySummary['availableCopies'] ?? 0) : 0;
        $totalCopies = is_array($copySummary) ? (int) ($copySummary['totalCopies'] ?? 0) : 0;
        $reviewReasonCodes = $this->normalizePgArray($document->document_review_reason_codes ?? null);

        $subjects = $this->decodeJsonValue($document->subjects_json);
        $classification = [];
        if (is_array($subjects) && count($subjects) > 0) {
            $classification = array_map(static fn (array $s): array => [
                'id' => (string) ($s['id'] ?? ''),
                'label' => (string) ($s['label'] ?? ''),
                'sourceKind' => (string) ($s['sourceKind'] ?? ''),
            ], $subjects);
        }

        return [
            'id' => (string) ($document->document_id ?? $document->legacy_doc_id ?? ''),
            'title' => [
                'display' => $titleDisplay,
                'raw' => $titleRaw,
                'subtitle' => $subtitle,
            ],
            'primaryAuthor' => $primaryAuthor,
            'authors' => is_array($authors) ? $authors : [],
            'publisher' => [
                'name' => $publisherName,
            ],
            'publicationYear' => $document->publication_year,
            'language' => [
                'code' => $languageCode,
                'raw' => $languageRaw,
            ],
            'isbn' => [
                'raw' => $isbnRaw,
                'isValid' => (bool) ($document->isbn_is_valid ?? false),
            ],
            'copies' => [
                'available' => $availableCopies,
                'total' => $totalCopies,
            ],
            'availability' => [
                'isAvailable' => $availableCopies > 0,
                'availableCopies' => $availableCopies,
                'totalCopies' => $totalCopies,
                'locations' => $locations,
            ],
            'quality' => [
                'needsReview' => (bool) ($document->document_needs_review ?? false),
                'reviewReasonCodes' => $reviewReasonCodes,
            ],
            'classification' => $classification,
            'udc' => $this->extractUdcData($document->raw_marc ?? null, $classification),
            'source' => 'app.document_detail_v',
        ];
    }

    /**
     * @param array<int, array<string, string>> $classification
     * @return array{raw: string, source: string}
     */
    private function extractUdcData(mixed $rawMarc, array $classification = []): array
    {
        if (is_string($rawMarc) && $rawMarc !== '') {
            foreach (['080', '084'] as $tag) {
                $value = $this->extractMarcFieldValue($rawMarc, $tag);
                if ($value !== '') {
                    return ['raw' => $value, 'source' => $tag];
                }
            }
        }

        foreach ($classification as $item) {
            $kind = (string) ($item['sourceKind'] ?? '');
            $label = trim((string) ($item['label'] ?? ''));
            if ($label !== '' && in_array($kind, ['subject', 'specialization'], true)) {
                return ['raw' => $label, 'source' => $kind];
            }
        }

        return ['raw' => '', 'source' => ''];
    }

    private function extractMarcFieldValue(string $rawMarc, string $tag): string
    {
        $pattern = sprintf('/(?:^|\x1E)%s\s{0,2}([^\x1E]+)/u', preg_quote($tag, '/'));
        if (! preg_match($pattern, $rawMarc, $matches)) {
            return '';
        }

        $fieldData = (string) ($matches[1] ?? '');
        $subfields = preg_split('/\x1F/u', $fieldData) ?: [];
        $values = [];

        foreach ($subfields as $subfield) {
            $subfield = trim($subfield);
            if ($subfield === '') {
                continue;
            }

            $code = mb_substr($subfield, 0, 1);
            $value = trim(mb_substr($subfield, 1));
            if ($value === '') {
                continue;
            }

            if (in_array($code, ['a', 'x'], true)) {
                $values[] = preg_replace('/\s+/u', ' ', $value) ?: $value;
            }
        }

        if ($values !== []) {
            return trim(implode(' · ', array_unique($values)));
        }

        $normalized = preg_replace('/[\x1F\\]+/u', ' ', $fieldData);

        return trim(preg_replace('/\s+/u', ' ', $normalized ?: '') ?: '');
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeJsonValue(mixed $value): array|null
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePgArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (! is_string($value) || $value === '' || $value === '{}') {
            return [];
        }

        $trimmed = trim($value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item, ' "'),
            explode(',', $trimmed)
        ), static fn (string $item): bool => $item !== ''));
    }
}
