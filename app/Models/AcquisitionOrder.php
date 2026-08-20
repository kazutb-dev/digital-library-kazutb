<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionOrder extends Model
{
    protected $fillable = ['order_number', 'supplier', 'status', 'ordered_at', 'expected_at', 'received_at', 'currency', 'total_amount', 'created_by', 'approved_by', 'notes'];

    protected function casts(): array
    {
        return ['ordered_at' => 'date', 'expected_at' => 'date', 'received_at' => 'date', 'total_amount' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcquisitionOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
