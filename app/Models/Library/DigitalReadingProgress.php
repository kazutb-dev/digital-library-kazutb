<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

/**
 * A reader's last position inside a digital material. See the migration for why
 * `material_ref` is a prefixed string key rather than a foreign key.
 */
class DigitalReadingProgress extends Model
{
    protected $table = 'digital_reading_progress';

    protected $fillable = [
        'user_id', 'material_ref', 'page', 'total_pages', 'zoom', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'total_pages' => 'integer',
            'last_read_at' => 'datetime',
        ];
    }
}
