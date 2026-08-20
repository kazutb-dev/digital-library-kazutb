<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalMaterialAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['allowed' => 'boolean', 'created_at' => 'datetime'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ElectronicMaterial::class, 'electronic_material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
