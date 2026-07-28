<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class AccountSummaryReadService
{
    private IdentityMatchAudit $audit;

    public function __construct(IdentityMatchAudit $audit)
    {
        $this->audit = $audit;
    }

    /**
     * @param array<string, mixed> $sessionUser
     * @return array<string, mixed>
     */
    public function summary(array $sessionUser): array
    {
        $sessionProfile = $this->normalizeSessionProfile($sessionUser);
        $reader = $this->findReaderBySessionProfile($sessionProfile);
        $candidates = $this->candidateIdentifiers($sessionProfile);

        // Audit the matching decision
        $matchAudit = $this->audit->validate($sessionProfile, $reader, $candidates);

        $readerLinked = $reader !== null;

        // Check if match is stale (email changed)
        $staleCheck = ['stale' => false, 'reason' => 'no_reader'];
        if ($readerLinked && $reader->primary_email !== null) {
            $staleCheck = $this->audit->checkIfStale(
                (string) ($sessionProfile['email'] ?? ''),
                (string) $reader->primary_email
            );
        }

        return [
            'data' => [
                'user' => $sessionProfile,
                'reader' => [
                    'linked' => $readerLinked,
                    'id' => $readerLinked ? (string) $reader->id : null,
                    'fullName' => $readerLinked ? (string) ($reader->full_name_raw ?? '') : null,
                    'legacyCode' => $readerLinked ? (string) ($reader->legacy_code_normalized ?? '') : null,
                    'registrationAt' => $readerLinked ? $this->normalizeTimestamp($reader->registration_at ?? null) : null,
                    'reregistrationAt' => $readerLinked ? $this->normalizeTimestamp($reader->reregistration_at ?? null) : null,
                    'needsReview' => $readerLinked ? (bool) ($reader->needs_review ?? false) : false,
                    'primaryEmail' => $readerLinked ? (string) ($reader->primary_email ?? '') : null,
                ],
                'stats' => [
                    'readerProfilesFound' => $readerLinked ? 1 : 0,
                    'readerContacts' => $readerLinked ? (int) ($reader->contacts_total ?? 0) : 0,
                    'openReaderReviewTasks' => $readerLinked ? (int) ($reader->open_review_tasks ?? 0) : 0,
                ],
            ],
            'matching' => [
                'status' => $matchAudit['status'],
                'matched_by' => $matchAudit['matched_by'],
                'has_ambiguity' => $matchAudit['has_ambiguity'],
                'ambiguity_details' => $matchAudit['ambiguity_details'],
                'is_stale' => $staleCheck['stale'],
                'stale_reason' => $staleCheck['reason'],
            ],
            'source' => 'session, app.readers, app.reader_contacts, app.review_tasks',
        ];
    }

    /**
     * @param array<string, mixed> $sessionUser
     * @return array<string, string>
     */
    private function normalizeSessionProfile(array $sessionUser): array
    {
        return [
            'id' => (string) ($sessionUser['id'] ?? ''),
            'name' => (string) ($sessionUser['name'] ?? ''),
            'ad_login' => (string) ($sessionUser['ad_login'] ?? ''),
            'role' => (string) ($sessionUser['role'] ?? 'reader'),
            'email' => (string) ($sessionUser['email'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $sessionProfile
     */
    private function findReaderBySessionProfile(array $sessionProfile): ?object
    {
        $identifiers = $this->candidateIdentifiers($sessionProfile);

        if ($identifiers === []) {
            return null;
        }

        return DB::table('app.readers as r')
            ->join('app.reader_contacts as rc', 'rc.reader_id', '=', 'r.id')
            ->select([
                'r.id',
                'r.full_name_raw',
                'r.legacy_code_normalized',
                'r.registration_at',
                'r.reregistration_at',
                'r.needs_review',
                'rc.value_normalized as primary_email',
                DB::raw('(SELECT COUNT(*) FROM app.reader_contacts rc2 WHERE rc2.reader_id = r.id) as contacts_total'),
                DB::raw("(SELECT COUNT(*) FROM app.review_tasks rt WHERE rt.entity_type = 'reader' AND rt.entity_id = r.id AND UPPER(COALESCE(rt.status, '')) = 'OPEN') as open_review_tasks"),
            ])
            ->where('rc.contact_type', 'EMAIL')
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $value) {
                    $query->orWhere('rc.value_normalized_key', $value);
                }
            })
            ->orderByDesc('r.registration_at')
            ->first();
    }

    /**
     * @param array<string, string> $sessionProfile
     * @return array<int, string>
     */
    private function candidateIdentifiers(array $sessionProfile): array
    {
        $values = [
            $sessionProfile['email'] ?? '',
            $sessionProfile['ad_login'] ?? '',
        ];

        $normalized = array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $values
        );

        return array_values(array_filter(array_unique($normalized), static fn (string $value): bool => $value !== ''));
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
