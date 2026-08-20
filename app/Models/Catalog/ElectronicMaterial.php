<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Electronic material attached to a bibliographic record (Master.md §18).
 * Files live on a non-public disk path; the download/view route checks the
 * access level against the requesting user before streaming.
 */
class ElectronicMaterial extends Model
{
    public const ACCESS_LEVELS = [
        'public', 'authenticated', 'student', 'faculty', 'staff', 'librarian',
        'campus_only', 'restricted_roles', 'embargoed', 'metadata_only', 'restricted',
    ];

    public const FILE_TYPES = ['pdf', 'image', 'presentation', 'document'];

    public const MATERIAL_TYPES = [
        'book_pdf', 'image_collection', 'presentation', 'scientific_work',
        'methodological_material', 'supplementary_file',
    ];

    public const WORKFLOW_STATUSES = [
        'uploaded', 'quarantined', 'metadata_review', 'rights_review', 'processing',
        'ready_for_review', 'approved', 'published', 'restricted', 'rejected',
        'withdrawn', 'archived', 'processing_failed',
    ];

    public const COPYRIGHT_STATUSES = [
        'public_domain', 'permission_granted', 'university_owned', 'licensed',
        'restricted', 'unknown',
    ];

    protected $fillable = [
        'bibliographic_record_id', 'title', 'file_path', 'external_url', 'file_type',
        'file_size', 'access_level', 'license_terms', 'allow_download', 'is_active', 'uploaded_by',
        'repository_item_id', 'material_type', 'description', 'language', 'source',
        'rights_holder', 'copyright_status', 'licence_type', 'licence_text', 'permission_date',
        'preview_policy', 'download_policy', 'print_policy', 'copy_policy', 'restricted_roles',
        'restricted_branches', 'campus_only', 'embargo_until', 'workflow_status',
        'processing_status', 'ocr_status', 'text_extraction_status', 'ocr_confidence', 'ocr_language',
        'public_id', 'original_filename', 'safe_filename', 'storage_disk', 'mime_type',
        'checksum_sha256', 'page_count', 'version_number', 'approved_by', 'published_at',
        'archived_at', 'withdrawn_at', 'withdrawal_reason', 'permission_document_path',
        'reviewed_by', 'extracted_text',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'allow_download' => 'boolean',
            'is_active' => 'boolean',
            'restricted_roles' => 'array',
            'restricted_branches' => 'array',
            'campus_only' => 'boolean',
            'permission_date' => 'date',
            'embargo_until' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'ocr_confidence' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $material): void {
            if (Schema::hasColumn($material->getTable(), 'public_id')) {
                $material->public_id ??= (string) Str::uuid();
            }
        });
    }

    public function bibliographicRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function repositoryItem(): BelongsTo
    {
        return $this->belongsTo(RepositoryItem::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ElectronicMaterialVersion::class)->orderByDesc('version_number');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DigitalMaterialAccessLog::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        $query->where('is_active', true);

        // The workflow column was introduced after the canonical materials
        // table. Older installations and focused tests still use the original
        // schema, where an active row is the strongest publication signal
        // available. Once the workflow column exists, however, active drafts
        // must never leak into reader-facing queries.
        if (Schema::hasColumn($query->getModel()->getTable(), 'workflow_status')) {
            $query->where('workflow_status', 'published');
        }

        return $query;
    }

    public function rightsPermitPublication(): bool
    {
        return $this->copyright_status !== 'unknown'
            && filled($this->rights_holder)
            && filled($this->source)
            && filled($this->licence_type);
    }

    public function embargoIsActive(): bool
    {
        return $this->embargo_until !== null && $this->embargo_until->isFuture();
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
