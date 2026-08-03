<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\User;
use Database\Factories\Catalog\ReaderProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderProfile extends Model
{
    /** @use HasFactory<ReaderProfileFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'student', 'bachelor', 'master', 'doctoral', 'teacher', 'faculty', 'researcher', 'staff',
    ];

    public const STATUSES = ['active', 'blocked', 'suspended'];

    protected $fillable = [
        'user_id', 'ticket_number', 'barcode', 'category', 'status', 'block_reason', 'limits_override',
        'phone', 'additional_email', 'faculty', 'department', 'study_group', 'preferred_branch_id',
        'valid_until', 'notification_preferences', 'accessibility_preferences',
    ];

    protected static function newFactory(): ReaderProfileFactory
    {
        return ReaderProfileFactory::new();
    }

    protected function casts(): array
    {
        return [
            'limits_override' => 'array',
            'birth_date' => 'date',
            'registered_at' => 'date',
            'valid_until' => 'date',
            'notification_preferences' => 'array',
            'accessibility_preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'preferred_branch_id');
    }

    /**
     * Get (or lazily create) the reader profile for a user. Every member
     * gets a ticket on first contact with the circulation desk (§3
     * "выдача читательского билета").
     */
    public static function forUser(User $user): self
    {
        // `status` is written explicitly rather than left to the column
        // default: firstOrCreate returns the in-memory model it just built,
        // which would otherwise carry a null status and read as blocked on
        // the reader's very first visit to the circulation desk.
        $profile = static::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'ticket_number' => static::nextTicketNumber(),
                // §9.4 — every card is scannable from the moment it is issued.
                'barcode' => static::nextBarcode(),
                'category' => match ($user->profile_type ?? null) {
                    'teacher' => 'teacher',
                    'staff' => 'staff',
                    default => 'student',
                },
                'status' => 'active',
            ],
        );

        // A profile created before §9.4 has no code yet; issue one on contact
        // rather than leaving an unscannable card in circulation.
        if ($profile->barcode === null) {
            $profile->forceFill(['barcode' => static::nextBarcode()])->save();
        }

        return $profile;
    }

    public static function nextTicketNumber(): string
    {
        $year = now()->format('Y');
        $sequence = (int) static::query()
            ->where('ticket_number', 'like', "KUTB-{$year}-%")
            ->count() + 1;

        do {
            $candidate = sprintf('KUTB-%s-%04d', $year, $sequence);
            $sequence++;
        } while (static::query()->where('ticket_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * §9.4 — sequential scannable code for the reader card, mirroring the
     * `book_copies.barcode` convention.
     */
    public static function nextBarcode(): string
    {
        $sequence = (int) static::query()->whereNotNull('barcode')->count() + 1;

        do {
            $candidate = sprintf('RDR%08d', $sequence);
            $sequence++;
        } while (static::query()->where('barcode', $candidate)->exists());

        return $candidate;
    }

    /**
     * Effective limit for a key: per-reader override first, then the global
     * setting (§14.3 "возможность ручной корректировки библиотекарем").
     */
    public function effectiveLimit(string $key, int $fallback): int
    {
        $override = data_get($this->limits_override, $key);

        return is_numeric($override) ? (int) $override : $fallback;
    }
}
