<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyMarcRecord extends Model
{
    protected $fillable = [
        'legacy_import_batch_id', 'source_doc_id', 'source_hash', 'leader',
        'record_type', 'bibliographic_level', 'control_number', 'fixed_008_raw',
        'modified_raw', 'canonical', 'raw', 'mapping_status',
        'bibliographic_record_id', 'apply_status',
    ];

    protected function casts(): array
    {
        return [
            'legacy_import_batch_id' => 'integer',
            'source_doc_id' => 'integer',
            'bibliographic_record_id' => 'integer',
            'canonical' => 'array',
            'raw' => 'array',
        ];
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    /**
     * MARC fields use the legacy batch/document pair rather than this row's id.
     * The batch constraint keeps repeat imports of the same source_doc_id apart.
     * Load this relation per record (as CatalogController does), not as a mixed-
     * batch eager load.
     */
    public function fields(): HasMany
    {
        $relation = $this->hasMany(LegacyMarcField::class, 'source_doc_id', 'source_doc_id')
            ->orderBy('tag')
            ->orderBy('occurrence')
            ->orderBy('id');

        if ($this->legacy_import_batch_id !== null) {
            $relation->where('legacy_import_batch_id', $this->legacy_import_batch_id);
        }

        return $relation;
    }
}
