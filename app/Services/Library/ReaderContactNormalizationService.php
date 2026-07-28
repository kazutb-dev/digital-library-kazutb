<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reader contact normalization: validate email/phone formats,
 * normalize values, update contacts, and manage review status.
 */
class ReaderContactNormalizationService
{
    private const CONTACT_TABLE = 'app.reader_contacts';
    private const READER_TABLE = 'app.readers';

    /**
     * Get contact normalization statistics.
     */
    public function contactStats(): array
    {
        $stats = DB::connection('pgsql')->select("
            SELECT
                COUNT(*) as total_contacts,
                COUNT(CASE WHEN contact_type = 'EMAIL' THEN 1 END) as email_count,
                COUNT(CASE WHEN contact_type = 'PHONE' THEN 1 END) as phone_count,
                COUNT(CASE WHEN is_placeholder THEN 1 END) as placeholder_count,
                COUNT(CASE WHEN is_valid_format THEN 1 END) as valid_format_count,
                COUNT(CASE WHEN NOT is_valid_format AND NOT is_placeholder THEN 1 END) as invalid_format_count,
                COUNT(CASE WHEN needs_review THEN 1 END) as needs_review_count,
                COUNT(CASE WHEN value_raw IS NOT NULL AND value_raw != '' THEN 1 END) as has_value_count
            FROM app.reader_contacts
        ");

        $row = $stats[0] ?? null;
        if ($row === null) {
            return ['totalContacts' => 0];
        }

        $totalReaders = (int) DB::connection('pgsql')->table(self::READER_TABLE)->count();
        $readersWithEmail = (int) DB::connection('pgsql')->selectOne("
            SELECT COUNT(DISTINCT reader_id) as cnt
            FROM app.reader_contacts
            WHERE contact_type = 'EMAIL' AND NOT is_placeholder AND value_raw IS NOT NULL AND value_raw != ''
        ")->cnt;

        return [
            'totalContacts' => (int) $row->total_contacts,
            'byType' => [
                'email' => (int) $row->email_count,
                'phone' => (int) $row->phone_count,
            ],
            'placeholderCount' => (int) $row->placeholder_count,
            'validFormatCount' => (int) $row->valid_format_count,
            'invalidFormatCount' => (int) $row->invalid_format_count,
            'needsReviewCount' => (int) $row->needs_review_count,
            'hasValueCount' => (int) $row->has_value_count,
            'totalReaders' => $totalReaders,
            'readersWithValidEmail' => $readersWithEmail,
            'readersWithoutEmail' => $totalReaders - $readersWithEmail,
        ];
    }

    /**
     * Get contacts for a specific reader.
     */
    public function readerContacts(string $readerId): array
    {
        $reader = DB::connection('pgsql')
            ->table(self::READER_TABLE)
            ->select(['id', 'full_name_raw', 'legacy_code_normalized', 'needs_review', 'review_reason_codes'])
            ->where('id', $readerId)
            ->first();

        if ($reader === null) {
            throw new \RuntimeException('Reader not found: ' . $readerId);
        }

        $contacts = DB::connection('pgsql')
            ->table(self::CONTACT_TABLE)
            ->where('reader_id', $readerId)
            ->orderBy('contact_type')
            ->orderByDesc('is_primary')
            ->get();

        return [
            'readerId' => (string) $reader->id,
            'fullName' => $reader->full_name_raw,
            'legacyCode' => $reader->legacy_code_normalized,
            'needsReview' => (bool) ($reader->needs_review ?? false),
            'contacts' => $contacts->map(fn ($c) => [
                'id' => (string) $c->id,
                'contactType' => $c->contact_type,
                'valueRaw' => $c->value_raw,
                'valueNormalized' => $c->value_normalized,
                'isPrimary' => (bool) $c->is_primary,
                'isPlaceholder' => (bool) $c->is_placeholder,
                'isValidFormat' => (bool) $c->is_valid_format,
                'needsReview' => (bool) $c->needs_review,
            ])->all(),
        ];
    }

    /**
     * Update a reader contact with new value. Validates format, normalizes, and audit-trails.
     *
     * @return array{contactId: string, updated: bool, validation: array}
     */
    public function updateContact(string $contactId, string $value, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($contactId, $value, $context): array {
            $contact = DB::connection('pgsql')
                ->table(self::CONTACT_TABLE)
                ->where('id', $contactId)
                ->lockForUpdate()
                ->first();

            if ($contact === null) {
                throw new \RuntimeException('Contact not found: ' . $contactId);
            }

            $before = $this->snapshotContact($contact);

            $trimmed = trim($value);
            $contactType = $contact->contact_type;
            $validation = $this->validateContact($contactType, $trimmed);
            $normalized = $validation['normalized'];
            $normalizedKey = $validation['normalizedKey'];

            DB::connection('pgsql')
                ->table(self::CONTACT_TABLE)
                ->where('id', $contactId)
                ->update([
                    'value_raw' => $trimmed,
                    'value_normalized' => $normalized,
                    'value_normalized_key' => $normalizedKey,
                    'is_placeholder' => $trimmed === '' || $trimmed === null,
                    'is_valid_format' => $validation['valid'],
                    'needs_review' => ! $validation['valid'],
                ]);

            // If valid contact, possibly clear reader's review status
            if ($validation['valid']) {
                $this->maybeResolveReaderReview($contact->reader_id, $context);
            }

            $afterContact = DB::connection('pgsql')
                ->table(self::CONTACT_TABLE)
                ->where('id', $contactId)
                ->first();

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'reader_contact_updated',
                'entity_type' => 'reader_contact',
                'entity_id' => $contactId,
                'reader_id' => $contact->reader_id,
                'actor_user_id' => $context['actorUserId'] ?? null,
                'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
                'request_id' => $context['requestId'] ?? null,
                'correlation_id' => $context['correlationId'] ?? null,
                'previous_state' => $before,
                'new_state' => $this->snapshotContact($afterContact),
                'metadata' => [
                    'details' => [
                        'contact_type' => $contactType,
                        'is_valid' => $validation['valid'],
                        'normalization_applied' => $normalized !== $trimmed,
                    ],
                ],
            ]);

            return [
                'contactId' => $contactId,
                'updated' => true,
                'validation' => $validation,
                'after' => $this->snapshotContact($afterContact),
            ];
        });
    }

    /**
     * Add a new contact for a reader.
     */
    public function addContact(string $readerId, string $contactType, string $value, bool $isPrimary = false, array $context = []): array
    {
        $reader = DB::connection('pgsql')
            ->table(self::READER_TABLE)
            ->where('id', $readerId)
            ->first();

        if ($reader === null) {
            throw new \RuntimeException('Reader not found: ' . $readerId);
        }

        $contactType = strtoupper(trim($contactType));
        if (! in_array($contactType, ['EMAIL', 'PHONE'], true)) {
            throw new \InvalidArgumentException('Invalid contact type: ' . $contactType);
        }

        $trimmed = trim($value);
        $validation = $this->validateContact($contactType, $trimmed);

        $contactId = (string) Str::uuid();

        DB::connection('pgsql')
            ->table(self::CONTACT_TABLE)
            ->insert([
                'id' => $contactId,
                'reader_id' => $readerId,
                'contact_type' => $contactType,
                'value_raw' => $trimmed,
                'value_normalized' => $validation['normalized'],
                'value_normalized_key' => $validation['normalizedKey'],
                'is_primary' => $isPrimary,
                'is_placeholder' => $trimmed === '',
                'is_valid_format' => $validation['valid'],
                'needs_review' => ! $validation['valid'],
                'created_at' => Carbon::now('UTC')->toDateTimeString(),
            ]);

        if ($validation['valid']) {
            $this->maybeResolveReaderReview($readerId, $context);
        }

        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => 'reader_contact_added',
            'entity_type' => 'reader_contact',
            'entity_id' => $contactId,
            'reader_id' => $readerId,
            'actor_user_id' => $context['actorUserId'] ?? null,
            'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
            'request_id' => $context['requestId'] ?? null,
            'correlation_id' => $context['correlationId'] ?? null,
            'previous_state' => null,
            'new_state' => ['contact_type' => $contactType, 'value' => $trimmed, 'valid' => $validation['valid']],
            'metadata' => ['details' => ['contact_type' => $contactType, 'is_primary' => $isPrimary]],
        ]);

        return [
            'contactId' => $contactId,
            'readerId' => $readerId,
            'contactType' => $contactType,
            'validation' => $validation,
        ];
    }

    /**
     * Bulk validate/normalize existing contacts (re-check format validity).
     */
    public function bulkNormalizeContacts(int $limit = 500): array
    {
        $contacts = DB::connection('pgsql')
            ->table(self::CONTACT_TABLE)
            ->whereNotNull('value_raw')
            ->where('value_raw', '!=', '')
            ->where('is_placeholder', false)
            ->limit($limit)
            ->get();

        $updated = 0;
        $validCount = 0;
        $invalidCount = 0;

        foreach ($contacts as $contact) {
            $validation = $this->validateContact($contact->contact_type, $contact->value_raw);
            $changed = ($contact->is_valid_format != $validation['valid'])
                || ($contact->value_normalized !== $validation['normalized']);

            if ($changed) {
                DB::connection('pgsql')
                    ->table(self::CONTACT_TABLE)
                    ->where('id', $contact->id)
                    ->update([
                        'value_normalized' => $validation['normalized'],
                        'value_normalized_key' => $validation['normalizedKey'],
                        'is_valid_format' => $validation['valid'],
                        'needs_review' => ! $validation['valid'],
                    ]);
                $updated++;
            }

            if ($validation['valid']) {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }

        return [
            'processed' => $contacts->count(),
            'updated' => $updated,
            'valid' => $validCount,
            'invalid' => $invalidCount,
        ];
    }

    /**
     * Validate and normalize a contact value by type.
     *
     * @return array{valid: bool, normalized: string, normalizedKey: string, error: string|null}
     */
    public function validateContact(string $type, string $value): array
    {
        $type = strtoupper(trim($type));

        return match ($type) {
            'EMAIL' => $this->validateEmail($value),
            'PHONE' => $this->validatePhone($value),
            default => ['valid' => false, 'normalized' => $value, 'normalizedKey' => '', 'error' => 'Unknown contact type'],
        };
    }

    private function validateEmail(string $email): array
    {
        $trimmed = trim(mb_strtolower($email));

        if ($trimmed === '') {
            return ['valid' => false, 'normalized' => '', 'normalizedKey' => '', 'error' => 'Empty email'];
        }

        $filtered = filter_var($trimmed, FILTER_VALIDATE_EMAIL);
        if ($filtered === false) {
            return [
                'valid' => false,
                'normalized' => $trimmed,
                'normalizedKey' => $trimmed,
                'error' => 'Invalid email format',
            ];
        }

        return [
            'valid' => true,
            'normalized' => $filtered,
            'normalizedKey' => $filtered,
            'error' => null,
        ];
    }

    private function validatePhone(string $phone): array
    {
        // Strip everything except digits and leading +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        if ($cleaned === '' || $cleaned === '+') {
            return ['valid' => false, 'normalized' => '', 'normalizedKey' => '', 'error' => 'Empty phone'];
        }

        // Kazakhstan phone normalization: 8xxx → +7xxx, 7xxx → +7xxx
        if (preg_match('/^8(\d{10})$/', $cleaned, $m)) {
            $cleaned = '+7' . $m[1];
        } elseif (preg_match('/^7(\d{10})$/', $cleaned, $m)) {
            $cleaned = '+7' . $m[1];
        }

        // Must be 10-15 digits (with optional leading +)
        $digits = preg_replace('/\D/', '', $cleaned);
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return [
                'valid' => false,
                'normalized' => $cleaned,
                'normalizedKey' => $digits,
                'error' => 'Phone must be 10-15 digits',
            ];
        }

        return [
            'valid' => true,
            'normalized' => $cleaned,
            'normalizedKey' => $digits,
            'error' => null,
        ];
    }

    /**
     * After adding/updating a contact, check if the reader's review
     * reason can be resolved (e.g., they now have a valid email).
     */
    private function maybeResolveReaderReview(string $readerId, array $context = []): void
    {
        $reader = DB::connection('pgsql')
            ->table(self::READER_TABLE)
            ->where('id', $readerId)
            ->first();

        if ($reader === null || ! ($reader->needs_review ?? false)) {
            return;
        }

        // Check if "missing_reader_email" reason can be cleared
        $hasValidEmail = DB::connection('pgsql')
            ->table(self::CONTACT_TABLE)
            ->where('reader_id', $readerId)
            ->where('contact_type', 'EMAIL')
            ->where('is_valid_format', true)
            ->where('is_placeholder', false)
            ->exists();

        if (! $hasValidEmail) {
            return;
        }

        $reasonCodes = $this->normalizePgArray($reader->review_reason_codes ?? null);
        $remaining = array_values(array_filter($reasonCodes, fn ($code) => $code !== 'missing_reader_email'));

        $update = [];
        if (empty($remaining)) {
            $update['needs_review'] = false;
            $update['review_reason_codes'] = DB::raw("'{}'::text[]");
        } else {
            $pgLiteral = '{' . implode(',', $remaining) . '}';
            $update['review_reason_codes'] = $pgLiteral;
        }
        $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();

        DB::connection('pgsql')
            ->table(self::READER_TABLE)
            ->where('id', $readerId)
            ->update($update);

        // Also resolve the data_quality_flag if it exists
        DB::connection('pgsql')
            ->table('app.data_quality_flags')
            ->where('entity_type', 'reader')
            ->where('entity_id', $readerId)
            ->where('issue_code', 'missing_reader_email')
            ->where('status', 'OPEN')
            ->update([
                'status' => 'RESOLVED',
                'resolved_at' => Carbon::now('UTC')->toDateTimeString(),
            ]);

        // Resolve the review task if exists
        DB::connection('pgsql')
            ->table('app.review_tasks')
            ->where('entity_type', 'reader')
            ->where('entity_id', $readerId)
            ->whereIn('status', ['OPEN', 'IN_PROGRESS'])
            ->update([
                'status' => 'COMPLETED',
                'completed_at' => Carbon::now('UTC')->toDateTimeString(),
            ]);
    }

    /**
     * @return list<string>
     */
    private function normalizePgArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '{}') {
            return [];
        }

        $trimmed = trim($trimmed, '{}');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item, "\" \t\n\r\0\x0B"),
            explode(',', $trimmed)
        ), static fn (string $item): bool => $item !== ''));
    }

    private function snapshotContact(object $c): array
    {
        return [
            'id' => (string) $c->id,
            'reader_id' => (string) $c->reader_id,
            'contact_type' => $c->contact_type,
            'value_raw' => $c->value_raw,
            'value_normalized' => $c->value_normalized,
            'is_primary' => (bool) $c->is_primary,
            'is_placeholder' => (bool) $c->is_placeholder,
            'is_valid_format' => (bool) $c->is_valid_format,
            'needs_review' => (bool) $c->needs_review,
        ];
    }
}
