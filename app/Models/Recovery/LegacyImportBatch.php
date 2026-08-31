<?php

namespace App\Models\Recovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyImportBatch extends Model
{
    protected $table = 'legacy_import_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'validation' => 'array', 'reconciliation' => 'array', 'apply_stats' => 'array',
            'started_at' => 'immutable_datetime', 'loaded_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }

    public function quarantinedRows(): HasMany
    {
        return $this->hasMany(LegacyImportQuarantine::class, 'legacy_import_batch_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(LegacyImportConflict::class, 'legacy_import_batch_id');
    }
}
