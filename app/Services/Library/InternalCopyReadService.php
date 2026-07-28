<?php

namespace App\Services\Library;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InternalCopyReadService
{
    private const COPY_TABLE = 'app.book_copies';

    /**
     * @return array<string,mixed>|null
     */
    public function findCopyDetail(string $copyId): ?array
    {
        $row = DB::connection('pgsql')
            ->table(self::COPY_TABLE . ' as bc')
            ->leftJoin('app.documents as d', 'd.id', '=', 'bc.document_id')
            ->select($this->copySelectColumns())
            ->where('bc.id', $copyId)
            ->first();

        if ($row === null) {
            return null;
        }

        $mapped = $this->mapRows([(array) $row]);

        return $mapped[0] ?? null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listCopiesByDocument(string $documentId): array
    {
        $rows = DB::connection('pgsql')
            ->table(self::COPY_TABLE . ' as bc')
            ->leftJoin('app.documents as d', 'd.id', '=', 'bc.document_id')
            ->select($this->copySelectColumns())
            ->where('bc.document_id', $documentId)
            ->when($this->hasCopyColumn('created_at'), fn ($q) => $q->orderByDesc('bc.created_at'))
            ->orderBy('bc.id')
            ->get();

        return $this->mapRows($rows->map(fn (object $row): array => (array) $row)->all());
    }

    /**
     * @return list<string>
     */
    private function copySelectColumns(): array
    {
        $copyColumns = [
            'id',
            'core_copy_id',
            'legacy_inv_id',
            'legacy_doc_id',
            'document_id',
            'branch_id',
            'sigla_id',
            'inventory_number_raw',
            'branch_hint_raw',
            'state_code',
            'needs_review',
            'review_reason_codes',
            'registered_at',
            'institution_unit_id',
            'campus_id',
            'location_mapping_status',
            'location_mapping_confidence',
            'retired_at',
            'retirement_reason_code',
            'retirement_note',
            'created_at',
            'updated_at',
        ];

        $documentColumns = [
            'title_raw',
        ];

        $select = [];

        foreach ($copyColumns as $column) {
            if ($this->hasCopyColumn($column)) {
                $select[] = 'bc.' . $column;
            }
        }

        foreach ($documentColumns as $column) {
            if ($this->hasDocumentColumn($column)) {
                $select[] = 'd.' . $column;
            }
        }

        return $select;
    }

    private function hasCopyColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::COPY_TABLE, $column);
    }

    private function hasDocumentColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn('app.documents', $column);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function mapRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $copyIds = array_values(array_filter(array_map(
            static fn (array $row): ?string => isset($row['id']) ? (string) $row['id'] : null,
            $rows
        )));

        $activeLoans = $this->activeLoanIndex($copyIds);

        return array_map(function (array $row) use ($activeLoans): array {
            $copyId = isset($row['id']) ? (string) $row['id'] : '';
            $activeLoanId = $copyId !== '' ? ($activeLoans[$copyId] ?? null) : null;
            $hasActiveLoan = $activeLoanId !== null;

            $retiredAt = isset($row['retired_at']) && $row['retired_at'] !== null
                ? $this->normalizeDateTime($row['retired_at'])
                : null;
            $isRetired = $retiredAt !== null;

            if ($isRetired) {
                $availabilityIndicator = 'RETIRED';
            } elseif ($hasActiveLoan) {
                $availabilityIndicator = 'ON_LOAN';
            } else {
                $availabilityIndicator = 'NO_ACTIVE_LOAN';
            }

            return [
                'copyIdentity' => [
                    'id' => $copyId,
                    'coreCopyId' => isset($row['core_copy_id']) ? (string) $row['core_copy_id'] : null,
                    'legacyInventoryId' => isset($row['legacy_inv_id']) ? (int) $row['legacy_inv_id'] : null,
                    'inventoryNumber' => isset($row['inventory_number_raw']) ? (string) $row['inventory_number_raw'] : null,
                ],
                'parentDocument' => [
                    'documentId' => isset($row['document_id']) ? (string) $row['document_id'] : null,
                    'legacyDocumentId' => isset($row['legacy_doc_id']) ? (int) $row['legacy_doc_id'] : null,
                    'title' => isset($row['title_raw']) ? (string) $row['title_raw'] : null,
                ],
                'branch' => [
                    'branchId' => isset($row['branch_id']) ? (string) $row['branch_id'] : null,
                    'campusId' => isset($row['campus_id']) ? (string) $row['campus_id'] : null,
                    'institutionUnitId' => isset($row['institution_unit_id']) ? (string) $row['institution_unit_id'] : null,
                    'branchHint' => isset($row['branch_hint_raw']) ? (string) $row['branch_hint_raw'] : null,
                ],
                'location' => [
                    'siglaId' => isset($row['sigla_id']) ? (string) $row['sigla_id'] : null,
                    'mappingStatus' => isset($row['location_mapping_status']) ? (string) $row['location_mapping_status'] : null,
                    'mappingConfidence' => isset($row['location_mapping_confidence']) ? (float) $row['location_mapping_confidence'] : null,
                ],
                'fundOwnership' => [
                    'institutionUnitId' => isset($row['institution_unit_id']) ? (string) $row['institution_unit_id'] : null,
                ],
                'lifecycle' => [
                    'stateCode' => isset($row['state_code']) ? (int) $row['state_code'] : null,
                    'needsReview' => isset($row['needs_review']) ? (bool) $row['needs_review'] : null,
                    'reviewReasonCodes' => $this->normalizePgArray($row['review_reason_codes'] ?? null),
                    'registeredAt' => $this->normalizeDateTime($row['registered_at'] ?? null),
                    'retiredAt' => $retiredAt,
                    'retirementReasonCode' => isset($row['retirement_reason_code']) ? (string) $row['retirement_reason_code'] : null,
                    'retirementNote' => isset($row['retirement_note']) ? (string) $row['retirement_note'] : null,
                ],
                'circulation' => [
                    'hasActiveLoan' => $hasActiveLoan,
                    'activeLoanId' => $activeLoanId,
                    'availabilityIndicator' => $availabilityIndicator,
                ],
                'timestamps' => [
                    'createdAt' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updatedAt' => $this->normalizeDateTime($row['updated_at'] ?? null),
                ],
                'source' => self::COPY_TABLE,
            ];
        }, $rows);
    }

    /**
     * @param list<string> $copyIds
     * @return array<string,string>
     */
    private function activeLoanIndex(array $copyIds): array
    {
        if ($copyIds === []) {
            return [];
        }

        $rows = DB::connection('pgsql')
            ->table('app.circulation_loans as cl')
            ->select(['cl.id', 'cl.copy_id'])
            ->whereIn('cl.copy_id', $copyIds)
            ->where('cl.status', 'active')
            ->whereNull('cl.returned_at')
            ->orderByDesc('cl.issued_at')
            ->get();

        $index = [];

        foreach ($rows as $row) {
            $copyId = (string) ($row->copy_id ?? '');
            if ($copyId === '' || isset($index[$copyId])) {
                continue;
            }

            $index[$copyId] = (string) ($row->id ?? '');
        }

        return $index;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->toISOString();
    }

    /**
     * @return array<int,string>
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