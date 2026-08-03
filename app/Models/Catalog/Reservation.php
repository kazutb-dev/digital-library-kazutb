<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\User;
use Database\Factories\Catalog\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    public const STATUSES = ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup', 'fulfilled', 'cancelled', 'expired'];

    /** Statuses that count against the reader's reservation limit. */
    public const ACTIVE_STATUSES = ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup'];

    public const TRANSITIONS = [
        'pending' => ['queued', 'confirmed', 'cancelled'],
        'queued' => ['confirmed', 'cancelled'],
        'confirmed' => ['in_transit', 'ready_for_pickup', 'cancelled'],
        'in_transit' => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['fulfilled', 'expired', 'cancelled'],
        'fulfilled' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    protected $fillable = [
        'reservation_number', 'user_id', 'bibliographic_record_id', 'assigned_copy_id',
        'pending_transfer_branch_id', 'pickup_branch_id', 'current_branch_id', 'status',
        'queue_position', 'queue_sequence', 'queued_at', 'confirmed_at', 'copy_assigned_at',
        'ready_at', 'expires_at', 'fulfilled_at', 'cancelled_at', 'expired_at', 'notified_at',
        'extension_count', 'cancel_reason_code', 'cancel_reason', 'cancelled_by',
        'estimated_available_at', 'source', 'priority', 'priority_reason',
        'requires_resolution', 'resolution_reason', 'created_by', 'notes',
    ];

    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
            'queue_position' => 'integer',
            'queue_sequence' => 'integer',
            'queued_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'copy_assigned_at' => 'datetime',
            'ready_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'estimated_available_at' => 'datetime',
            'extension_count' => 'integer',
            'priority' => 'integer',
            'requires_resolution' => 'boolean',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function assignedCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'assigned_copy_id');
    }

    /**
     * §8.2 — manual "the copy is travelling from this branch" marker. Purely
     * informational: no transfer workflow exists behind it.
     */
    public function pendingTransferBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pending_transfer_branch_id');
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ReservationHistory::class)->orderByDesc('created_at');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(CopyTransfer::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * §8.2 — where the copy physically is, as far as the system can tell.
     * Returns one of: null (no copy yet), 'in_transit' (manual marker set),
     * 'at_library' (waiting on the shelf/desk), 'with_reader' (checked out).
     */
    public function logisticsState(): ?string
    {
        if ($this->status === 'in_transit' || $this->transfer?->status === 'in_transit') {
            return 'in_transit';
        }

        $copy = $this->assignedCopy;
        if ($copy === null) {
            return null;
        }

        return in_array($copy->status, ['issued', 'overdue'], true) ? 'with_reader' : 'at_library';
    }
}
