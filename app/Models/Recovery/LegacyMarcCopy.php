<?php

namespace App\Models\Recovery;

use App\Models\Catalog\BookCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyMarcCopy extends Model
{
    protected $table = 'legacy_marc_copies';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['canonical' => 'array', 'raw' => 'array'];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }
}
