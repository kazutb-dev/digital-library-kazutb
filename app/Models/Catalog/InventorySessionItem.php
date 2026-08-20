<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySessionItem extends Model
{
    protected $fillable = [
        'inventory_session_id', 'copy_id', 'expected_branch_id', 'expected_fund_id',
        'expected_shelf', 'expected_status', 'result', 'first_scanned_at',
        'inventory_condition', 'observed_inventory_number', 'verified_by', 'verified_at',
        'location_confirmed_at', 'location_corrected_at', 'handling_seconds',
    ];

    protected function casts(): array
    {
        return [
            'first_scanned_at' => 'datetime', 'verified_at' => 'datetime',
            'location_confirmed_at' => 'datetime', 'location_corrected_at' => 'datetime',
            'handling_seconds' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }
}
