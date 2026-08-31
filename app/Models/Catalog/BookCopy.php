<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\Fund;
use App\Models\Ksu\KsuEntry;
use App\Models\Operations\AcquisitionBatch;
use App\Models\Operations\AcquisitionBatchItem;
use Database\Factories\Catalog\BookCopyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class BookCopy extends Model
{
    /** @use HasFactory<BookCopyFactory> */
    use HasFactory;

    protected $table = 'book_copies';

    public const STATUSES = [
        'available', 'reserved', 'issued', 'overdue', 'lost', 'written_off',
        'under_repair', 'in_processing', 'on_display', 'reserved_stock',
    ];

    /** Physical/inventory lifecycle. Kept separate from circulation state. */
    public const INVENTORY_STATUSES = ['active', 'damaged', 'repair', 'lost', 'written_off'];

    /** Availability state used by loans, holds and transfers. */
    public const CIRCULATION_STATUSES = ['available', 'reserved', 'on_hold', 'on_loan', 'in_transfer', 'unavailable'];

    public const CONDITIONS = ['new', 'good', 'worn', 'damaged'];

    public const ACCESS_RESTRICTIONS = ['free', 'reading_room', 'limited'];

    /** Canonical sources used by acquisitions accounting and KSU reports. */
    public const ACQUISITION_SOURCES = [
        'purchase', 'donation', 'subscription', 'exchange', 'reader_replacement', 'other',
    ];

    /** Statuses in which a copy can be handed to a reader. */
    public const ISSUABLE_STATUSES = ['available', 'reserved'];

    protected $fillable = [
        'bibliographic_record_id', 'inventory_number', 'barcode', 'accounting_type',
        'ksu_number', 'storage_sigla', 'branch_id', 'fund_id', 'room', 'section', 'shelf_location',
        'price', 'acquisition_source', 'supplier_name', 'acquisition_date', 'registration_date',
        'condition', 'defect_description', 'status', 'access_restriction', 'issue_count',
        'inventory_status', 'circulation_status',
        'legacy_inv_id', 'legacy_doc_id', 'inventory_number_is_synthetic', 'legacy_inventory_number',
        'shelf_index', 'rack', 'sigla_code', 'legacy_sigla_id', 'service_point_code',
        'local_library_code', 'fund_raw', 'price_raw', 'currency', 'accounting_mode_raw',
        'writeoff_date', 'writeoff_act', 'writeoff_reason', 'legacy_state_raw',
        'legacy_state_label', 'legacy_notes', 'legacy_import_batch_id', 'legacy_imported_at',
        'acquisition_batch_id', 'acquisition_batch_item_id',
        'ksu_entry_id', 'inventory_sequence_scope', 'inventory_sequence_year',
        'inventory_sequence_number', 'barcode_sequence_number',
    ];

    protected static function newFactory(): BookCopyFactory
    {
        return BookCopyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'acquisition_date' => 'immutable_date',
            'registration_date' => 'immutable_date',
            'issue_count' => 'integer',
            'inventory_number_is_synthetic' => 'boolean',
            'legacy_sigla_id' => 'integer',
            'legacy_state_raw' => 'integer',
            'writeoff_date' => 'immutable_date',
            'legacy_imported_at' => 'immutable_datetime',
            'inventory_sequence_year' => 'integer',
            'inventory_sequence_number' => 'integer',
            'barcode_sequence_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BookCopy $copy): void {
            // Existing services still assign the compatibility `status`
            // column. Mirror it into the two canonical state machines so a
            // rolling deployment cannot leave contradictory state behind.
            if (! $copy->isDirty('status') || ! self::hasSeparatedStateColumns()) {
                return;
            }

            [$inventory, $circulation] = self::separatedStateFor((string) $copy->status);
            if (! $copy->isDirty('inventory_status')) {
                $copy->inventory_status = $inventory;
            }
            if (! $copy->isDirty('circulation_status')) {
                $copy->circulation_status = $circulation;
            }
        });
    }

    /** @return array{0:string,1:string} */
    public static function separatedStateFor(string $status): array
    {
        return match ($status) {
            'available' => ['active', 'available'],
            'reserved' => ['active', 'reserved'],
            'issued', 'overdue' => ['active', 'on_loan'],
            'lost' => ['lost', 'unavailable'],
            'written_off' => ['written_off', 'unavailable'],
            'under_repair' => ['repair', 'unavailable'],
            default => ['active', 'unavailable'],
        };
    }

    public function isCirculatable(): bool
    {
        $inventory = $this->inventory_status ?: self::separatedStateFor((string) $this->status)[0];
        $circulation = $this->circulation_status ?: self::separatedStateFor((string) $this->status)[1];

        return $inventory === 'active' && in_array($circulation, ['available', 'reserved'], true);
    }

    /** Available in both the compatibility and separated state machines. */
    public function scopeAvailableForCirculation(Builder $query): Builder
    {
        $query->where('status', 'available');

        if (self::hasSeparatedStateColumns()) {
            $query->where(fn (Builder $state): Builder => $state
                ->whereNull('inventory_status')
                ->orWhere('inventory_status', 'active'))
                ->where(fn (Builder $state): Builder => $state
                    ->whereNull('circulation_status')
                    ->orWhere('circulation_status', 'available'));
        }

        return $query;
    }

    private static function hasSeparatedStateColumns(): bool
    {
        if (app()->environment('testing')) {
            return Schema::hasColumns('book_copies', ['inventory_status', 'circulation_status']);
        }

        static $available;

        return $available ??= Schema::hasColumns('book_copies', ['inventory_status', 'circulation_status']);
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function acquisitionBatch(): BelongsTo
    {
        return $this->belongsTo(AcquisitionBatch::class);
    }

    public function acquisitionBatchItem(): BelongsTo
    {
        return $this->belongsTo(AcquisitionBatchItem::class);
    }

    public function ksuEntry(): BelongsTo
    {
        return $this->belongsTo(KsuEntry::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(CopyHistory::class, 'copy_id')->orderByDesc('occurred_at');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'copy_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'assigned_copy_id');
    }

    public function incidentCases(): HasMany
    {
        return $this->hasMany(CirculationIncidentCase::class, 'original_copy_id');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class, 'copy_id')
            ->whereIn('status', ['active', 'overdue'])
            ->whereNull('returned_at');
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'assigned_copy_id')
            ->whereIn('status', ['pending', 'confirmed', 'in_transit', 'ready_for_pickup']);
    }

    public function recordHistory(
        string $eventType,
        ?int $userId = null,
        ?int $actorId = null,
        ?int $loanId = null,
        array $details = [],
    ): CopyHistory {
        return $this->history()->create([
            'event_type' => $eventType,
            'user_id' => $userId,
            'actor_id' => $actorId,
            'loan_id' => $loanId,
            'details' => $details ?: null,
            'occurred_at' => now(),
        ]);
    }
}
