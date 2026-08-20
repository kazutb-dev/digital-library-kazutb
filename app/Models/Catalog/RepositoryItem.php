<?php

namespace App\Models\Catalog;

use App\Models\News;
use App\Models\User;
use App\Support\StorageKey;
use Database\Factories\Catalog\RepositoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepositoryItem extends Model
{
    /** @use HasFactory<RepositoryItemFactory> */
    use HasFactory;

    public const WORK_TYPES = [
        'bachelor_thesis', 'master_thesis', 'phd_dissertation', 'scientific_article',
        'research_report', 'university_publication', 'thesis_abstract',
    ];

    public const WORK_TYPE_ALIASES = [
        'abstract_of_thesis' => 'thesis_abstract',
    ];

    public const STATUSES = [
        'draft', 'metadata_review', 'author_verification', 'rights_review', 'quality_review',
        'changes_requested', 'pending_approval', 'approved', 'scheduled', 'published',
        'embargoed', 'rejected', 'withdrawn', 'archived',
    ];

    public const ACCESS_POLICIES = [
        'full_public', 'metadata_public_file_authenticated', 'campus_only',
        'embargoed', 'restricted', 'metadata_only',
    ];

    /** Policies a director may explicitly select for release after an embargo. */
    public const POST_EMBARGO_ACCESS_POLICIES = [
        'full_public', 'metadata_public_file_authenticated', 'campus_only',
        'restricted', 'metadata_only',
    ];

    /** Fields whose exact values are covered by a director approval. */
    public const APPROVAL_FIELDS = [
        'title', 'authors', 'work_type', 'year', 'department', 'udc_code',
        'abstract', 'keywords', 'language', 'title_translations', 'original_title',
        'abstract_translations', 'keyword_translations', 'supervisor', 'reviewer', 'university',
        'faculty', 'educational_programme', 'degree_level', 'defence_date',
        'publication_date', 'page_count', 'bibliography', 'doi', 'isbn_issn', 'source',
        'rights_holder', 'copyright_status', 'licence_type', 'licence_text',
        'permission_document_path', 'permission_date', 'access_policy', 'embargo_until',
        'post_embargo_access_policy',
    ];

    /** Exact file binding covered alongside the publication metadata. */
    private const APPROVAL_FILE_FIELDS = [
        'file_path', 'file_name', 'file_size', 'checksum_sha256', 'version_number',
    ];

    /** Accepted names used by earlier repository specifications/imports. */
    public const ACCESS_POLICY_ALIASES = [
        'metadata_public' => 'metadata_only',
        'authenticated' => 'metadata_public_file_authenticated',
    ];

    protected $fillable = [
        'title', 'authors', 'work_type', 'year', 'department', 'udc_code',
        'abstract', 'keywords', 'language', 'file_path', 'file_name', 'file_size',
        'status', 'uploaded_by', 'reviewed_by', 'approved_by', 'review_notes', 'published_at',
        'title_translations', 'original_title', 'supervisor', 'reviewer', 'university', 'faculty',
        'educational_programme', 'degree_level', 'defence_date', 'publication_date',
        'abstract_translations', 'keyword_translations', 'page_count', 'bibliography', 'doi',
        'isbn_issn', 'source', 'rights_holder', 'copyright_status', 'licence_type', 'licence_text',
        'permission_date', 'access_policy', 'embargo_until', 'post_embargo_access_policy',
        'scheduled_for', 'internal_review_notes', 'active_approval_id',
        'public_id', 'permission_document_path', 'checksum_sha256', 'version_number',
        'withdrawn_at', 'withdrawal_reason', 'withdrawn_by',
    ];

    protected static function newFactory(): RepositoryItemFactory
    {
        return RepositoryItemFactory::new();
    }

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'keywords' => 'array',
            'year' => 'integer',
            'file_size' => 'integer',
            'published_at' => 'datetime',
            'title_translations' => 'array',
            'abstract_translations' => 'array',
            'keyword_translations' => 'array',
            'defence_date' => 'date',
            'publication_date' => 'date',
            'permission_date' => 'date',
            'embargo_until' => 'datetime',
            'scheduled_for' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** Store import aliases in the canonical vocabulary. */
    protected function accessPolicy(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => self::normaliseAccessPolicy($value),
            set: fn (?string $value): ?string => self::normaliseAccessPolicy($value),
        );
    }

    /** Store the former `abstract_of_thesis` key under its canonical name. */
    protected function workType(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => self::normaliseWorkType($value),
            set: fn (?string $value): ?string => self::normaliseWorkType($value),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (Schema::hasColumn($item->getTable(), 'public_id')) {
                $item->public_id ??= (string) Str::uuid();
            }
        });

        // Defence in depth for every Eloquent write path, including future
        // imports/services: an approval can never silently authorise changed
        // metadata or a different PDF. The historical approval row is retained.
        static::updating(function (self $item): void {
            if ($item->active_approval_id === null
                || ! $item->isDirty([...self::APPROVAL_FIELDS, ...self::APPROVAL_FILE_FIELDS])) {
                return;
            }

            $item->active_approval_id = null;
            $item->approved_by = null;
            $item->reviewed_by = null;
            $item->scheduled_for = null;
            $item->published_at = null;

            if (in_array($item->status, ['approved', 'scheduled', 'published', 'embargoed'], true)) {
                $item->status = 'metadata_review';
            }
        });
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activeApproval(): BelongsTo
    {
        return $this->belongsTo(RepositoryApproval::class, 'active_approval_id');
    }

    public function approvalHistory(): HasMany
    {
        return $this->hasMany(RepositoryApproval::class)->latest('approved_at');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(RepositoryItemVersion::class)
            ->where('is_active', true)
            ->latestOfMany('version_number');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function authorsList(): HasMany
    {
        return $this->hasMany(RepositoryAuthor::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RepositoryItemVersion::class)->orderByDesc('version_number');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(RepositoryReview::class)->latest();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ElectronicMaterial::class);
    }

    public function linkedNews(): HasMany
    {
        return $this->hasMany(News::class, 'repository_item_id');
    }

    public function usageDaily(): HasMany
    {
        return $this->hasMany(RepositoryUsageDaily::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Records whose bibliographic metadata may be discovered publicly.
     * File policy is intentionally evaluated separately. Embargoed records
     * expose metadata only; withdrawn records remain as durable tombstones.
     */
    public function scopePublicMetadata(Builder $query): Builder
    {
        $query
            ->whereIn('status', ['published', 'embargoed', 'withdrawn'])
            ->where('copyright_status', '!=', 'unknown')
            ->whereNotNull('rights_holder')
            ->where('rights_holder', '!=', '')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->whereHas('activeApproval', function (Builder $approval): void {
                $approval->where('approver_role_snapshot', 'director')
                    ->whereNotNull('repository_item_version_id')
                    ->whereNotNull('checksum_sha256')
                    ->whereNotNull('metadata_fingerprint');
            })
            ->whereRaw($this->approvalFingerprintSql('repository_items'));

        return $this->applyRightsPublicationScope($query);
    }

    /**
     * Public repository surface from §15.6: only a released, open-full-text
     * record may be discovered by a guest.  Tombstones, embargoes and every
     * restricted policy remain outside the public query instead of relying on
     * the view to hide their download button.
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        $query
            ->where('status', 'published')
            ->where(function (Builder $access): void {
                $access->where(function (Builder $withoutEmbargo): void {
                    $withoutEmbargo->whereNull('embargo_until')
                        ->where('access_policy', 'full_public');
                })
                    ->orWhere(function (Builder $postEmbargo): void {
                        $postEmbargo->whereNotNull('embargo_until')
                            ->where('embargo_until', '<=', now())
                            ->where('post_embargo_access_policy', 'full_public');
                    });
            })
            ->where('copyright_status', '!=', 'unknown')
            ->whereNotNull('rights_holder')
            ->where('rights_holder', '!=', '')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->whereHas('activeApproval', function (Builder $approval): void {
                $approval->where('approver_role_snapshot', 'director')
                    ->whereNotNull('repository_item_version_id')
                    ->whereNotNull('checksum_sha256')
                    ->whereNotNull('metadata_fingerprint');
            })
            ->whereRaw($this->approvalFingerprintSql('repository_items'))
            ->whereRaw("LOWER(COALESCE(file_path, '')) LIKE ?", ['%.pdf'])
            ->where(function (Builder $embargo): void {
                $embargo->whereNull('embargo_until')->orWhere('embargo_until', '<=', now());
            });

        return $this->applyRightsPublicationScope($query);
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->status === 'published'
            && $this->effectiveAccessPolicy() === 'full_public'
            && $this->hasDirectorApproval()
            && $this->rightsPermitPublication()
            && $this->hasPublishablePdf()
            && ! $this->embargoIsActive();
    }

    public function isPublicMetadataVisible(): bool
    {
        return in_array($this->status, ['published', 'embargoed', 'withdrawn'], true)
            && $this->hasDirectorApproval()
            && $this->rightsPermitPublication();
    }

    public function canExposeFullText(?User $user = null, bool $onCampus = false): bool
    {
        if ($this->status !== 'published' || $this->embargoIsActive() || ! $this->hasPublishablePdf()) {
            return false;
        }
        $policy = $this->effectiveAccessPolicy();
        if ($policy === null) {
            return $user?->can('repository.read_full') ?? false;
        }

        return match ($policy) {
            'full_public' => true,
            'metadata_public_file_authenticated' => $user?->is_active === true,
            'campus_only' => $user?->is_active === true && $onCampus,
            default => false,
        };
    }

    public function hasPublishablePdf(): bool
    {
        if (blank($this->file_path) || ! StorageKey::isSafe((string) $this->file_path)) {
            return false;
        }

        $path = mb_strtolower(trim((string) $this->file_path));

        return str_ends_with($path, '.pdf');
    }

    /** Verify the canonical private file, not only a legacy `.pdf` suffix. */
    public function hasStoredPublishablePdf(): bool
    {
        if (! $this->hasPublishablePdf()) {
            return false;
        }

        try {
            $disk = Storage::disk('local');
            if (! $disk->exists($this->file_path)
                || ! in_array($disk->mimeType($this->file_path), ['application/pdf', 'application/x-pdf'], true)) {
                return false;
            }

            $stream = fopen($disk->path($this->file_path), 'rb');
            if ($stream === false) {
                return false;
            }
            try {
                $header = fread($stream, 5);
                $size = max(0, (int) $disk->size($this->file_path));
                $hash = hash_init('sha256');
                hash_update($hash, is_string($header) ? $header : '');
                hash_update_stream($hash, $stream);
                $actualChecksum = hash_final($hash);
                fseek($stream, max(0, $size - 2048));
                $tail = stream_get_contents($stream);

                return $header === '%PDF-'
                    && is_string($tail)
                    && str_contains($tail, '%%EOF')
                    && filled($this->checksum_sha256)
                    && hash_equals((string) $this->checksum_sha256, $actualChecksum);
            } finally {
                fclose($stream);
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasDirectorApproval(): bool
    {
        $approval = $this->activeApproval;
        $version = $approval?->version;

        if ($approval === null || $version === null
            || $approval->repository_item_id !== $this->getKey()
            || $version->repository_item_id !== $this->getKey()
            || ! $version->is_active
            || $approval->approver_role_snapshot !== 'director'
            || blank($approval->checksum_sha256)
            || blank($this->checksum_sha256)
            || ! hash_equals((string) $approval->checksum_sha256, (string) $this->checksum_sha256)
            || ! hash_equals((string) $approval->checksum_sha256, (string) $version->checksum_sha256)
            || (string) $version->file_path !== (string) $this->file_path
            || (int) $version->version_number !== (int) $this->version_number) {
            return false;
        }

        return hash_equals(
            (string) $approval->metadata_fingerprint,
            $this->approvalFingerprint($version),
        );
    }

    /** SQL-level immutable approval binding used by every public collection query. */
    private function approvalFingerprintSql(string $table): string
    {
        return 'EXISTS (SELECT 1 FROM repository_approvals AS approval '
            .'INNER JOIN repository_item_versions AS approved_version ON approved_version.id = approval.repository_item_version_id '
            ."WHERE approval.id = {$table}.active_approval_id "
            ."AND approval.repository_item_id = {$table}.id "
            ."AND approved_version.repository_item_id = {$table}.id "
            .'AND approved_version.is_active = '.(DB::getDriverName() === 'pgsql' ? 'TRUE' : '1').' '
            ."AND approved_version.version_number = {$table}.version_number "
            ."AND approved_version.file_path = {$table}.file_path "
            ."AND approved_version.checksum_sha256 = {$table}.checksum_sha256 "
            ."AND approval.checksum_sha256 = {$table}.checksum_sha256)";
    }

    private function applyRightsPublicationScope(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $licensed): void {
                $licensed->where('copyright_status', '!=', 'licensed')
                    ->orWhere(function (Builder $terms): void {
                        $terms->whereNotNull('licence_type')->where('licence_type', '!=', '')
                            ->whereNotNull('licence_text')->where('licence_text', '!=', '');
                    });
            })
            ->where(function (Builder $permission): void {
                $permission->where('copyright_status', '!=', 'permission_granted')
                    ->orWhereNotNull('permission_date');
            })
            ->where(function (Builder $restricted): void {
                $restricted->where('copyright_status', '!=', 'restricted')
                    ->orWhere(function (Builder $closed): void {
                        $closed->where(function (Builder $withoutEmbargo): void {
                            $withoutEmbargo->whereNull('embargo_until')
                                ->where('access_policy', '!=', 'full_public');
                        })->orWhere(function (Builder $afterEmbargo): void {
                            $afterEmbargo->whereNotNull('embargo_until')
                                ->where('post_embargo_access_policy', '!=', 'full_public');
                        });
                    });
            });
    }

    /** Invariants shared by interactive and scheduled public release paths. */
    public function readyForPublicRelease(): bool
    {
        return $this->hasDirectorApproval()
            && $this->rightsPermitPublication()
            && $this->hasStoredPublishablePdf()
            && in_array(self::normaliseAccessPolicy($this->access_policy), self::ACCESS_POLICIES, true);
    }

    public function embargoIsActive(): bool
    {
        return $this->embargo_until !== null && $this->embargo_until->isFuture();
    }

    public function rightsPermitPublication(): bool
    {
        $copyright = (string) $this->copyright_status;
        $policy = self::normaliseAccessPolicy($this->access_policy);
        $postEmbargo = self::normaliseAccessPolicy($this->post_embargo_access_policy);

        if ($copyright === 'unknown' || blank($this->rights_holder) || blank($this->source)
            || ! in_array($policy, self::ACCESS_POLICIES, true)) {
            return false;
        }

        if ($copyright === 'licensed' && (blank($this->licence_type) || blank($this->licence_text))) {
            return false;
        }

        if ($copyright === 'permission_granted' && $this->permission_date === null) {
            return false;
        }

        $hasEmbargo = $policy === 'embargoed' || $this->embargo_until !== null;
        if ($hasEmbargo && ! in_array($postEmbargo, self::POST_EMBARGO_ACCESS_POLICIES, true)) {
            return false;
        }
        $releasePolicy = $hasEmbargo ? $postEmbargo : $policy;

        return ! ($copyright === 'restricted' && $releasePolicy === 'full_public');
    }

    /** Policy approved for the record in its current released state. */
    public function effectiveAccessPolicy(): ?string
    {
        $policy = self::normaliseAccessPolicy($this->access_policy);
        $postEmbargo = self::normaliseAccessPolicy($this->post_embargo_access_policy);

        if ($this->status === 'published'
            && $this->embargo_until !== null
            && ! $this->embargoIsActive()
            && in_array($postEmbargo, self::POST_EMBARGO_ACCESS_POLICIES, true)) {
            return $postEmbargo;
        }

        return $policy;
    }

    /** Stable fingerprint of every publication-critical value and exact PDF version. */
    public function approvalFingerprint(RepositoryItemVersion $version): string
    {
        $payload = [];
        foreach (self::APPROVAL_FIELDS as $field) {
            $payload[$field] = $this->normaliseFingerprintValue($this->getAttribute($field));
        }
        $payload['version'] = [
            'id' => (int) $version->getKey(),
            'number' => (int) $version->version_number,
            'path' => (string) $version->file_path,
            'name' => (string) $version->file_name,
            'size' => (int) $version->file_size,
            'mime_type' => (string) $version->mime_type,
            'checksum_sha256' => (string) $version->checksum_sha256,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function normaliseFingerprintValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $part): mixed => $this->normaliseFingerprintValue($part), $value);
    }

    /** @return list<string> */
    public static function acceptedAccessPolicies(): array
    {
        return [...self::ACCESS_POLICIES, ...array_keys(self::ACCESS_POLICY_ALIASES)];
    }

    /** @return list<string> */
    public static function acceptedWorkTypes(): array
    {
        return [...self::WORK_TYPES, ...array_keys(self::WORK_TYPE_ALIASES)];
    }

    public static function normaliseWorkType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return self::WORK_TYPE_ALIASES[$type] ?? $type;
    }

    /** @return list<string> */
    public static function equivalentWorkTypes(string $type): array
    {
        $canonical = self::normaliseWorkType($type);

        return collect(self::WORK_TYPE_ALIASES)
            ->filter(fn (string $target): bool => $target === $canonical)
            ->keys()
            ->prepend($canonical)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function normaliseAccessPolicy(?string $policy): ?string
    {
        if ($policy === null) {
            return null;
        }

        return self::ACCESS_POLICY_ALIASES[$policy] ?? $policy;
    }

    /** @return list<string> */
    public static function equivalentAccessPolicies(string $policy): array
    {
        $canonical = self::normaliseAccessPolicy($policy);

        return collect(self::ACCESS_POLICY_ALIASES)
            ->filter(fn (string $target): bool => $target === $canonical)
            ->keys()
            ->prepend($canonical)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(authors AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(abstract, \'\')) LIKE ?', [$needle]);
            $builder->orWhereRaw('LOWER(COALESCE(udc_code, \'\')) LIKE ?', [$needle]);
            if (Schema::hasColumn('repository_items', 'public_id')) {
                $builder->orWhereRaw('LOWER(COALESCE(supervisor, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(faculty, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(educational_programme, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(doi, \'\')) LIKE ?', [$needle]);
            }
            if (Schema::hasTable('repository_authors')) {
                $builder->orWhereHas('authorsList', function (Builder $authors) use ($needle): void {
                    $authors->whereRaw('LOWER(display_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(orcid, \'\')) LIKE ?', [$needle]);
                });
            }
        });
    }
}
