<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

class LegacyMarcField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'legacy_import_batch_id', 'source_doc_id', 'tag', 'indicator1',
        'indicator2', 'subfield_code', 'value', 'occurrence',
        'is_known_tag', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'legacy_import_batch_id' => 'integer',
            'source_doc_id' => 'integer',
            'occurrence' => 'integer',
            'is_known_tag' => 'boolean',
            'raw' => 'array',
        ];
    }
}
