<?php

namespace App\Models\Ksu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KsuBook extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'legacy_source_table',
        'numbering_format',
        'reset_period',
        'auto_numbering_enabled',
        'numbering_rule_evidence',
        'requires_manual_decision',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'auto_numbering_enabled' => 'boolean',
            'requires_manual_decision' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(KsuSequence::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KsuEntry::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(KsuConflict::class);
    }
}
