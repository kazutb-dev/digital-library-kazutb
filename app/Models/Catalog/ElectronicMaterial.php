<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Electronic material attached to a bibliographic record (Master.md §18).
 * Files live on a non-public disk path; the download/view route checks the
 * access level against the requesting user before streaming.
 */
class ElectronicMaterial extends Model
{
    public const ACCESS_LEVELS = ['public', 'authenticated', 'restricted'];

    public const FILE_TYPES = ['pdf', 'image', 'presentation', 'document'];

    protected $fillable = [
        'bibliographic_record_id', 'title', 'file_path', 'external_url', 'file_type',
        'file_size', 'access_level', 'license_terms', 'allow_download', 'is_active', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'allow_download' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accessibleBy(?User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->access_level) {
            'public' => true,
            'authenticated' => $user !== null,
            'restricted' => $user !== null && $user->canAny(['digital.upload', 'digital.set_access_flags']),
            default => false,
        };
    }
}
