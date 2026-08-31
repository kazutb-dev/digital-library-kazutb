<?php

namespace App\Models\Recovery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyRecoveryReview extends Model
{
    public const DECISIONS = [
        'pending',
        'map_fund',
        'note',
        'ignore',
        'linked',
    ];

    protected $fillable = [
        'review_type',
        'entity_type',
        'entity_id',
        'source_table',
        'source_id',
        'raw_value',
        'decision',
        'target_type',
        'target_id',
        'decision_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'source_id' => 'integer',
            'target_id' => 'integer',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
