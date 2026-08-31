<?php

namespace App\Models\Operations;

use App\Models\AcquisitionOrder;
use App\Models\Branch;
use App\Models\Fund;
use App\Models\Ksu\KsuEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionBatch extends Model
{
    public const STATUSES = ['draft', 'confirmed', 'cancelled'];

    protected $fillable = [
        'batch_number',
        'status',
        'received_at',
        'acquisition_source',
        'supplier_name',
        'currency',
        'branch_id',
        'fund_id',
        'acquisition_order_id',
        'ksu_entry_id',
        'title_count',
        'copy_count',
        'total_amount',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_date',
            'confirmed_at' => 'immutable_datetime',
            'title_count' => 'integer',
            'copy_count' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcquisitionBatchItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcquisitionOrder::class, 'acquisition_order_id');
    }

    public function ksuEntry(): BelongsTo
    {
        return $this->belongsTo(KsuEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
