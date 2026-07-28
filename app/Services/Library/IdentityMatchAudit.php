<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdentityMatchAudit
{
    /**
     * Validate and audit identity matching decision.
     *
     * @param array<string, string> $sessionProfile Normalized CRM user profile
     * @param ?object $matchedReader First matched reader (or null)
     * @param array<int, string> $candidates Identifiers used for matching
     * @return array<string, mixed> Audit result with visibility fields
     */
    public function validate(array $sessionProfile, ?object $matchedReader, array $candidates): array
    {
        $matchedBy = $this->detectMatchType($sessionProfile, $matchedReader);
        $ambiguityCheck = $this->checkAmbiguity($candidates, $matchedReader);

        $audit = [
            'status' => $matchedReader ? 'matched' : 'no_match',
            'matched_by' => $matchedBy,
            'candidate_count' => count($candidates),
            'candidates_used' => $candidates,
            'has_ambiguity' => $ambiguityCheck['ambiguous'],
            'ambiguity_details' => $ambiguityCheck['details'],
            'reader_id' => $matchedReader?->id ? (string) $matchedReader->id : null,
            'reader_registration_at' => $matchedReader?->registration_at ? $matchedReader->registration_at->toIso8601String() : null,
        ];

        // Log the matching decision for audit trail
        $this->logMatchingDecision($sessionProfile, $audit, $matchedReader);

        return $audit;
    }

    /**
     * Detect which identifier was used for successful match.
     *
     * @param array<string, string> $sessionProfile
     * @param ?object $matchedReader
     * @return string One of: 'email', 'ad_login', 'no_match'
     */
    private function detectMatchType(array $sessionProfile, ?object $matchedReader): string
    {
        if (! $matchedReader) {
            return 'no_match';
        }

        // Heuristic: check which candidate likely matched
        $email = mb_strtolower(trim((string) ($sessionProfile['email'] ?? '')));
        $adLogin = mb_strtolower(trim((string) ($sessionProfile['ad_login'] ?? '')));

        // Email is primary for reader_contacts lookup
        if ($email !== '') {
            return 'email';
        }

        if ($adLogin !== '') {
            return 'ad_login_fallback';
        }

        return 'unknown';
    }

    /**
     * Check if multiple readers share the same email (ambiguity).
     *
     * @param array<int, string> $candidates
     * @param ?object $firstMatch
     * @return array<string, mixed> {ambiguous: bool, details: string, count: int}
     */
    private function checkAmbiguity(array $candidates, ?object $firstMatch): array
    {
        if (! $firstMatch || $candidates === []) {
            return [
                'ambiguous' => false,
                'details' => 'No match or no candidates',
                'count' => 0,
            ];
        }

        try {
            $duplicateCount = DB::table('app.readers as r')
                ->join('app.reader_contacts as rc', 'rc.reader_id', '=', 'r.id')
                ->where('rc.contact_type', 'EMAIL')
                ->where(function ($query) use ($candidates): void {
                    foreach ($candidates as $value) {
                        $query->orWhere('rc.value_normalized_key', $value);
                    }
                })
                ->distinct('r.id')
                ->count();

            if ($duplicateCount > 1) {
                return [
                    'ambiguous' => true,
                    'details' => "Multiple readers ({$duplicateCount}) share email/ad_login; selected most recent",
                    'count' => $duplicateCount,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('IdentityMatchAudit.checkAmbiguity failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'ambiguous' => false,
            'details' => 'Single reader matched',
            'count' => 1,
        ];
    }

    /**
     * Log matching decision for audit trail and debugging.
     *
     * @param array<string, string> $sessionProfile
     * @param array<string, mixed> $audit
     * @param ?object $matchedReader
     * @return void
     */
    private function logMatchingDecision(array $sessionProfile, array $audit, ?object $matchedReader): void
    {
        $logContext = [
            'match_status' => $audit['status'],
            'matched_by' => $audit['matched_by'],
            'matched_reader_id' => $audit['reader_id'],
            'session_user_id' => $sessionProfile['id'] ?? 'unknown',
            'session_email' => $sessionProfile['email'] ?? 'none',
            'session_ad_login' => $sessionProfile['ad_login'] ?? 'none',
            'candidates_count' => $audit['candidate_count'],
            'has_ambiguity' => $audit['has_ambiguity'],
            'ambiguity_details' => $audit['ambiguity_details'],
        ];

        // Try logging via Facade (works in full Laravel context)
        try {
            if ($matchedReader) {
                Log::info('Identity mapping: Reader matched', $logContext);
            } else {
                Log::warning('Identity mapping: No reader matched', $logContext);
            }
        } catch (\Throwable $e) {
            // Graceful fallback if Log facade not available (e.g., in unit tests)
            // In production, Log facade is always available
        }

        // Also try to record in DB if table exists (for Phase 1.5)
        try {
            if (DB::getSchemaBuilder()->hasTable('identity_match_logs')) {
                DB::table('identity_match_logs')->insert([
                    'session_user_id' => $sessionProfile['id'] ?? null,
                    'session_email' => $sessionProfile['email'] ?? null,
                    'session_ad_login' => $sessionProfile['ad_login'] ?? null,
                    'matched_reader_id' => $audit['reader_id'] ?? null,
                    'matched_by' => $audit['matched_by'],
                    'candidate_count' => $audit['candidate_count'],
                    'has_ambiguity' => $audit['has_ambiguity'],
                    'ambiguity_details' => $audit['ambiguity_details'] ?? null,
                    'is_stale' => false, // set to true by caller if needed
                    'stale_reason' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Graceful fallback: DB logging optional, not critical
        }
    }

    /**
     * Check if email has changed since match (stale detection).
     *
     * @param string $currentEmail Email from current session
     * @param ?string $recordedEmail Email recorded at match time (from primary_email field)
     * @return array<string, mixed> {stale: bool, reason: string}
     */
    public function checkIfStale(string $currentEmail, ?string $recordedEmail): array
    {
        $normalized_current = mb_strtolower(trim($currentEmail));
        $normalized_recorded = mb_strtolower(trim((string) $recordedEmail));

        if ($normalized_current !== $normalized_recorded) {
            return [
                'stale' => true,
                'reason' => 'Email changed in CRM since reader linkage',
                'current_email' => $normalized_current,
                'recorded_email' => $normalized_recorded,
            ];
        }

        return [
            'stale' => false,
            'reason' => 'Email consistent',
        ];
    }
}
