<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcquisitionOrderItem extends Model
{
    protected $fillable = ['acquisition_order_id', 'bibliographic_record_id', 'title_snapshot', 'quantity_ordered', 'quantity_received', 'unit_price'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AcquisitionOrder::class, 'acquisition_order_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'bibliographic_record_id');
    }
}
