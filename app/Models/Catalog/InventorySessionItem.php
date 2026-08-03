<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySessionItem extends Model
{
    protected $fillable = [
        'inventory_session_id', 'copy_id', 'expected_branch_id', 'expected_fund_id',
        'expected_shelf', 'expected_status', 'result', 'first_scanned_at',
    ];

    protected function casts(): array
    {
        return ['first_scanned_at' => 'datetime'];
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
