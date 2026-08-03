<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataImportMappingProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'is_active' => 'boolean'];
    }
}
