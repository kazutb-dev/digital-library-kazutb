<?php

namespace App\Models\Recovery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyImportConflict extends Model
{
    protected $table = 'legacy_import_conflicts';

    protected $fillable = ['status', 'resolution_note', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'legacy_import_batch_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
