<?php

namespace App\Models\Recovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyImportQuarantine extends Model
{
    protected $table = 'legacy_import_quarantine';

    protected $fillable = ['status'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }
}
