<?php

namespace App\Models\Ksu;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsuEntryItem extends Model
{
    protected $fillable = [
        'ksu_entry_id',
        'book_copy_id',
        'bibliographic_record_id',
        'source_inv_id',
        'source_doc_id',
        'inventory_number',
        'price',
        'registration_date',
        'link_method',
        'link_confidence',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'registration_date' => 'immutable_date',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KsuEntry::class, 'ksu_entry_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }
}
