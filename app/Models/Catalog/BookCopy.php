<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\Fund;
use Database\Factories\Catalog\BookCopyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookCopy extends Model
{
    /** @use HasFactory<BookCopyFactory> */
    use HasFactory;

    protected $table = 'book_copies';

    public const STATUSES = [
        'available', 'reserved', 'issued', 'overdue', 'lost', 'written_off',
        'under_repair', 'in_processing', 'on_display', 'reserved_stock',
    ];

    public const CONDITIONS = ['new', 'good', 'worn', 'damaged'];

    public const ACCESS_RESTRICTIONS = ['free', 'reading_room', 'limited'];

    /** Statuses in which a copy can be handed to a reader. */
    public const ISSUABLE_STATUSES = ['available', 'reserved'];

    protected $fillable = [
        'bibliographic_record_id', 'inventory_number', 'barcode', 'accounting_type',
        'ksu_number', 'storage_sigla', 'branch_id', 'fund_id', 'shelf_location',
        'price', 'acquisition_source', 'supplier_name', 'acquisition_date', 'registration_date',
        'condition', 'defect_description', 'status', 'access_restriction', 'issue_count',
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
        ];
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
