<?php

namespace App\Models\Operations;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcquisitionBatchItem extends Model
{
    public const INVENTORY_NUMBER_MODES = ['auto', 'manual_list', 'range'];

    public const BARCODE_MODES = ['auto', 'manual_list', 'none'];

    protected $fillable = [
        'acquisition_batch_id',
        'bibliographic_record_id',
        'title_snapshot',
        'quantity',
        'unit_price',
        'accounting_type',
        'condition',
        'access_restriction',
        'storage_sigla',
        'service_point_code',
        'room',
        'section',
        'shelf_location',
        'shelf_index',
        'inventory_number_mode',
        'manual_inventory_numbers',
        'inventory_range_start',
        'inventory_range_end',
        'barcode_mode',
        'manual_barcodes',
        'inventory_prefix',
        'barcode_prefix',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'manual_inventory_numbers' => 'array',
            'manual_barcodes' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AcquisitionBatch::class, 'acquisition_batch_id');
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'acquisition_batch_item_id');
    }
}
