<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialReportSnapshot extends Model
{
    public const STATUSES = [
        'draft', 'generated', 'pending_review', 'approved', 'rejected',
        'superseded', 'archived',
    ];

    public const LOCKED_STATUSES = ['approved', 'superseded', 'archived'];

    /** @var list<string> */
    private const IMMUTABLE_SOURCE_FIELDS = [
        'public_id', 'report_number', 'lineage_id', 'revision', 'previous_snapshot_id', 'report_type',
        'period_preset', 'period_from', 'period_to', 'filters', 'source_data',
        'source_hash', 'schema_version', 'archive_disk', 'archive_path',
        'archive_hash', 'archive_size', 'created_by', 'retention_until',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $snapshot): void {
            if (in_array($snapshot->getOriginal('status'), self::LOCKED_STATUSES, true)) {
                throw new DomainException('An approved official report snapshot is immutable.');
            }

            if ($snapshot->isDirty(self::IMMUTABLE_SOURCE_FIELDS)) {
                throw new DomainException('Official report source data is immutable; create a revision instead.');
            }
        });

        static::deleting(function (self $snapshot): void {
            if (in_array($snapshot->status, self::LOCKED_STATUSES, true)) {
                throw new DomainException('An approved official report snapshot cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'schema_version' => 'integer',
            'filters' => 'array',
            'source_data' => 'array',
            'archive_size' => 'integer',
            'period_from' => 'immutable_datetime',
            'period_to' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'retention_until' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_snapshot_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_snapshot_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'lineage_id', 'lineage_id')->orderByDesc('revision');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(ReportExportJob::class, 'snapshot_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function sourceIsIntact(): bool
    {
        return is_string($this->source_hash)
            && is_string($this->archive_hash)
            && hash_equals($this->source_hash, $this->archive_hash)
            && hash_equals($this->source_hash, self::hashPayload($this->source_data));
    }

    /** Keep JSON number semantics stable across the database round trip. */
    protected function asJson($value, $flags = 0)
    {
        return parent::asJson($value, $flags | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @param array<string, mixed> $payload */
    public static function hashPayload(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    /** @param array<string, mixed> $payload */
    public static function canonicalJson(array $payload): string
    {
        $canonical = self::canonicalize($payload);
        $encoded = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return $encoded;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonicalize(...), $value);
    }
}
