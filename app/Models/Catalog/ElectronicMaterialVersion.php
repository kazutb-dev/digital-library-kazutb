<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectronicMaterialVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size' => 'integer', 'is_active' => 'boolean'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ElectronicMaterial::class, 'electronic_material_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
