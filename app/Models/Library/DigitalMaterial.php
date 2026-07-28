<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalMaterial extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'app.digital_materials';

    protected $guarded = [];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'allow_download' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function humanFileSize(): string
    {
        $bytes = $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' МБ';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' КБ';
        }

        return $bytes . ' Б';
    }
}
