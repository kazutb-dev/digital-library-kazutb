<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryScan extends Model
{
    public $timestamps = false;

    protected $fillable = ['inventory_session_id', 'copy_id', 'scanned_by', 'code', 'classification', 'is_duplicate', 'details', 'scanned_at'];

    protected function casts(): array
    {
        return ['is_duplicate' => 'boolean', 'details' => 'array', 'scanned_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
