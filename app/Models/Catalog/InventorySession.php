<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventorySession extends Model
{
    public const STATUSES = ['draft', 'running', 'review', 'completed', 'approved', 'cancelled'];

    protected $fillable = [
        'session_number', 'scope_type', 'branch_id', 'fund_id', 'storage_sigla',
        'service_point_code', 'room', 'section', 'shelf_range', 'pilot_limit', 'inventory_date',
        'responsible_id', 'status', 'expected_count', 'found_count', 'missing_count',
        'misplaced_count', 'unknown_count', 'duplicate_count', 'started_at', 'completed_at',
        'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'inventory_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime',
            'approved_at' => 'datetime', 'expected_count' => 'integer', 'found_count' => 'integer',
            'missing_count' => 'integer', 'misplaced_count' => 'integer',
            'unknown_count' => 'integer', 'duplicate_count' => 'integer', 'pilot_limit' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventorySessionItem::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(InventoryScan::class);
    }
}
