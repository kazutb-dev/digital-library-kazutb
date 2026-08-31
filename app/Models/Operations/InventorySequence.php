<?php

namespace App\Models\Operations;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySequence extends Model
{
    protected $fillable = [
        'scope_key',
        'branch_id',
        'year',
        'inventory_prefix',
        'barcode_prefix',
        'last_inventory_number',
        'last_barcode_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_inventory_number' => 'integer',
            'last_barcode_number' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
