<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExternalResource extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'licensed',
        'open_access',
        'partner',
        'internal',
    ];

    /**
     * Reader-facing audiences from the specification.  These are deliberately
     * kept separate from Spatie role names: a reader account may represent a
     * student or a teacher, while several operational roles all represent
     * library staff to the public.
     *
     * @var list<string>
     */
    public const AUDIENCES = [
        'guest',
        'student',
        'teacher',
        'library_staff',
    ];

    /** @var list<string> */
    public const CONTENT_TYPES = [
        'electronic_books',
        'scientific_articles',
        'dissertations',
        'research_reports',
        'faculty_works',
        'journals',
        'research_data',
        'educational_materials',
        'multimedia',
        'braille_books',
        'audiobooks',
        'catalogues',
    ];

    /** @var list<string> */
    public const HEALTH_STATUSES = ['unchecked', 'healthy', 'degraded', 'unavailable'];

    protected $fillable = [
        'slug',
        'title',
        'resource_type',
        'description',
        'logo_path',
        'available_roles',
        'license_expires_at',
        'is_active',
        'access_instructions',
        'url',
        'health_check_url',
        'provider',
        'access_type',
        'category',
        'sort_order',
        'name_translations', 'short_description_translations', 'description_translations',
        'instructions_translations', 'content_types', 'access_method', 'guest_access',
        'campus_only', 'login_required', 'contract_number', 'contract_starts_at',
        'contract_ends_at', 'renewal_at', 'responsible_user_id', 'vendor_contact',
        'internal_notes', 'statistics_url', 'health_status', 'health_incident_id',
        'health_incident_started_at', 'last_checked_at',
        'licence_file_path',
        'publication_status', 'renewal_status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'available_roles' => 'array',
            'license_expires_at' => 'immutable_date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'name_translations' => 'array',
            'short_description_translations' => 'array',
            'description_translations' => 'array',
            'instructions_translations' => 'array',
            'content_types' => 'array',
            'guest_access' => 'boolean',
            'campus_only' => 'boolean',
            'login_required' => 'boolean',
            'contract_starts_at' => 'immutable_date',
            'contract_ends_at' => 'immutable_date',
            'renewal_at' => 'immutable_date',
            'health_incident_started_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $resource): void {
            if (Schema::hasColumn($resource->getTable(), 'public_id')) {
                $resource->public_id ??= (string) Str::uuid();
            }
        });
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExternalResourceEvent::class);
    }

    public function contractVersions(): HasMany
    {
        return $this->hasMany(ExternalResourceContractVersion::class)->orderByDesc('version_number');
    }

    public function notificationOutboxes(): HasMany
    {
        return $this->hasMany(ExternalResourceNotificationOutbox::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    public function scopeActive(Builder $query): Builder
    {
        $query->where('is_active', true);

        return Schema::hasColumn($this->getTable(), 'publication_status')
            ? $query->where('publication_status', 'published')
            : $query;
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('resource_type', $type);
    }

    public function scopeAvailableToRole(
        Builder $query,
        string $role
    ): Builder {
        return $query->whereJsonContains('available_roles', $role);
    }

    public function scopeExpiringSoon(
        Builder $query,
        int $withinDays = 30
    ): Builder {
        return $query
            ->where('is_active', true)
            ->whereRaw('COALESCE(contract_ends_at, license_expires_at) BETWEEN ? AND ?', [
                today('UTC')->toDateString(),
                today('UTC')->addDays($withinDays)->toDateString(),
            ]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function expiresSoon(int $withinDays = 30): bool
    {
        $expiry = $this->effectiveExpiryDate();

        if (! $this->is_active || $expiry === null || $this->licenceExpired()) {
            return false;
        }

        return $expiry->betweenIncluded(
            today('UTC'),
            today('UTC')->addDays($withinDays)
        );
    }

    public function effectiveExpiryDate(): ?CarbonImmutable
    {
        return $this->contract_ends_at ?? $this->license_expires_at;
    }

    public function licenceExpired(): bool
    {
        $expiry = $this->effectiveExpiryDate();

        // A date-only contract remains valid through the end date itself.
        return $expiry !== null && $expiry->isBefore(today('UTC'));
    }

    public function accessStatus(int $expiryNoticeDays = 60): string
    {
        if (! $this->is_active || ($this->publication_status !== null && $this->publication_status !== 'published')) {
            return 'inactive';
        }

        if (! $this->readyForDirectory()) {
            return 'inactive';
        }

        if ($this->licenceExpired()) {
            return 'expired';
        }

        return $this->expiresSoon($expiryNoticeDays) ? 'expiring_soon' : 'active';
    }

    public function canOpen(?User $user, bool $onCampus = false): bool
    {
        if (! $this->is_active
            || ($this->publication_status !== null && $this->publication_status !== 'published')
            || $this->licenceExpired()
            || $this->health_status === 'unavailable'
            || ! $this->readyForDirectory()) {
            return false;
        }
        if ($this->campus_only && ! $onCampus) {
            return false;
        }
        if ($this->login_required && $user === null) {
            return false;
        }
        if ($user === null) {
            return $this->guest_access
                && (empty($this->available_roles) || in_array('guest', $this->normalisedAudiences(), true));
        }

        $allowed = $this->normalisedAudiences();

        if ($allowed === []) {
            return true;
        }

        return array_intersect($allowed, self::audiencesForUser($user)) !== [];
    }

    /**
     * Machine-readable reasons why this record cannot be made public. Contract
     * identifiers and files remain optional/private, but a paid or partner
     * resource needs a verified validity end date.
     *
     * @return list<string>
     */
    public function publicationReadinessIssues(): array
    {
        $issues = [];

        foreach (['title', 'description', 'access_instructions', 'access_method'] as $field) {
            if (trim((string) $this->{$field}) === '') {
                $issues[] = $field;
            }
        }
        if ((array) $this->content_types === []) {
            $issues[] = 'content_types';
        }
        if ($this->normalisedAudiences() === []) {
            $issues[] = 'available_roles';
        }
        if (! self::isSafeDestination((string) $this->url, (string) $this->resource_type)) {
            $issues[] = 'url';
        }
        if (in_array($this->resource_type, ['licensed', 'partner'], true)
            && $this->effectiveExpiryDate() === null) {
            $issues[] = 'agreement_end_date';
        }
        if (in_array($this->resource_type, ['licensed', 'partner'], true) && $this->licenceExpired()) {
            $issues[] = 'agreement_expired';
        }

        return array_values(array_unique($issues));
    }

    public function readyForPublication(): bool
    {
        return $this->publicationReadinessIssues() === [];
    }

    /**
     * Expired agreements stay publicly discoverable with a closed access
     * button. All other publication-readiness failures remain private.
     */
    public function readyForDirectory(): bool
    {
        return array_values(array_diff(
            $this->publicationReadinessIssues(),
            ['agreement_expired'],
        )) === [];
    }

    public function publiclyDiscoverable(): bool
    {
        return $this->publication_status === 'published'
            && $this->is_active
            && $this->readyForDirectory();
    }

    /**
     * Convert historical RBAC values to the four reader-facing audiences.
     *
     * @return list<string>
     */
    public function normalisedAudiences(): array
    {
        $audiences = [];

        foreach ((array) $this->available_roles as $role) {
            foreach (match ((string) $role) {
                'guest' => ['guest'],
                'student', 'bachelor', 'master', 'doctoral' => ['student'],
                'teacher', 'faculty', 'researcher' => ['teacher'],
                'member', 'reader' => ['student', 'teacher'],
                'library_staff', 'staff', 'employee', 'librarian', 'senior_librarian',
                'director', 'admin', 'system_administrator', 'digital_materials_officer',
                'repository_editor', 'cataloguer', 'bibliographer', 'acquisitions',
                'acquisitions_officer', 'support_specialist' => ['library_staff'],
                default => [],
            } as $audience) {
                $audiences[] = $audience;
            }
        }

        return array_values(array_unique($audiences));
    }

    /** @return list<string> */
    public static function audiencesForUser(User $user): array
    {
        $roles = $user->getRoleNames()->map(static fn (mixed $role): string => (string) $role)->all();
        $audiences = [];

        if (array_intersect($roles, ['student', 'bachelor', 'master', 'doctoral']) !== []) {
            $audiences[] = 'student';
        }
        if (array_intersect($roles, ['teacher', 'faculty', 'researcher']) !== []) {
            $audiences[] = 'teacher';
        }

        if (array_intersect($roles, [
            'librarian', 'senior_librarian', 'director', 'admin', 'system_administrator',
            'digital_materials_officer', 'repository_editor', 'cataloguer', 'bibliographer',
            'acquisitions', 'acquisitions_officer', 'support_specialist',
        ]) !== []) {
            $audiences[] = 'library_staff';
        }

        if (in_array('member', $roles, true) || $roles === []) {
            $category = null;
            if ($user->exists && Schema::hasTable('reader_profiles')) {
                $category = $user->readerProfile?->category;
            }

            if (in_array($category, ['teacher', 'faculty', 'researcher'], true)) {
                $audiences[] = 'teacher';
            } elseif (in_array($category, ['student', 'bachelor', 'master', 'doctoral'], true)) {
                $audiences[] = 'student';
            } else {
                // Legacy member records do not distinguish students from
                // teachers, so either registered-reader audience is valid.
                $audiences[] = 'student';
                $audiences[] = 'teacher';
            }
        }

        return array_values(array_unique([...$audiences, ...$roles]));
    }

    public static function isSafeDestination(string $destination, string $resourceType): bool
    {
        $destination = trim($destination);

        if ($resourceType === 'internal') {
            $decoded = $destination;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $next = rawurldecode($decoded);
                if ($next === $decoded) {
                    break;
                }
                $decoded = $next;
            }
            $parts = parse_url($decoded);

            return is_array($parts)
                && str_starts_with($decoded, '/')
                && ! str_starts_with($decoded, '//')
                && ! str_contains($decoded, '\\')
                && preg_match('/[\x00-\x1F\x7F]/', $decoded) !== 1
                && ! isset($parts['scheme'])
                && ! isset($parts['host'])
                && filter_var($decoded, FILTER_VALIDATE_URL) === false;
        }

        if (filter_var($destination, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($destination);
        if (! is_array($parts)) {
            return false;
        }
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $ipHost = trim($host, '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;

        if ($scheme !== 'https'
            || $host === ''
            || $port !== 443
            || isset($parts['user'])
            || isset($parts['pass'])
            || str_ends_with($host, '.')
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || preg_match('/[\\x00-\\x20\\x7F]/', $destination) === 1
            || str_contains($destination, '\\')
            || self::hasSensitiveQueryParameter((string) ($parts['query'] ?? ''))) {
            return false;
        }

        if (filter_var($ipHost, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $ipHost,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        // libc/cURL accept non-canonical IPv4 forms such as 127.1 and
        // 0177.0.0.1. They must not fall through as apparently harmless DNS.
        if (preg_match('/^[0-9.]+$/', $host) === 1
            || preg_match('/^(?:(?:0x[0-9a-f]+|0[0-7]+|[0-9]+)\\.)*(?:0x[0-9a-f]+|0[0-7]+|[0-9]+)$/i', $host) === 1) {
            return false;
        }

        return strlen($host) <= 253
            && preg_match(
                '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
                $host,
            ) === 1;
    }

    public static function isSafeHealthDestination(string $destination, string $resourceType): bool
    {
        if (! self::isSafeDestination($destination, $resourceType)) {
            return false;
        }

        if ($resourceType === 'internal') {
            return true;
        }

        $query = parse_url($destination, PHP_URL_QUERY);

        return $query === null || $query === '';
    }

    private static function hasSensitiveQueryParameter(string $query): bool
    {
        if ($query === '') {
            return false;
        }

        foreach (explode('&', $query) as $parameter) {
            $key = mb_strtolower(rawurldecode((string) strtok($parameter, '=')));
            $normalised = preg_replace('/[^a-z0-9]/', '', $key) ?? '';
            if (in_array($normalised, [
                'token', 'accesstoken', 'refreshtoken', 'idtoken', 'accesskey',
                'apikey', 'password', 'passwd', 'secret', 'signature', 'sig',
                'auth', 'authtoken', 'session', 'sessionid', 'credential', 'jwt',
                'code', 'oauthcode',
            ], true) || preg_match('/(?:token|secret|password|passwd|signature|credential|apikey|accesskey|sessionid)$/', $normalised) === 1) {
                return true;
            }
        }

        return false;
    }

    public function translated(string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $values = $this->{$field.'_translations'};

        $fallback = match ($field) {
            'name' => $this->title, 'instructions' => $this->access_instructions, default => $this->description
        };

        return (string) ($values[$locale] ?? $values['ru'] ?? $values['kk'] ?? $values['en'] ?? $fallback ?? '');
    }
}
