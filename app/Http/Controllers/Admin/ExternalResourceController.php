<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalResource;
use App\Models\ExternalResourceContractVersion;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\ExternalResources\ExternalResourceWorkflow;
use App\Support\StoredUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ExternalResourceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'resource_type' => ['nullable', Rule::in(ExternalResource::TYPES)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expiring', 'expired'])],
        ]);
        $query = ExternalResource::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(provider, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$needle]);
            });
        }
        if (! empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }
        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        } elseif (($filters['status'] ?? null) === 'expiring') {
            $query->where('is_active', true)
                ->whereRaw('COALESCE(contract_ends_at, license_expires_at) BETWEEN ? AND ?', [
                    today('UTC')->toDateString(),
                    today('UTC')->addDays(30)->toDateString(),
                ]);
        } elseif (($filters['status'] ?? null) === 'expired') {
            $query->where('is_active', true)
                ->whereRaw('COALESCE(contract_ends_at, license_expires_at) < ?', [today('UTC')->toDateString()]);
        }

        return view('admin.external-resources.index', [
            'resources' => $query->orderBy('sort_order')->orderBy('title')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.external-resources.form', [
            'resource' => new ExternalResource([
                'resource_type' => 'open_access',
                'is_active' => false,
                'publication_status' => 'draft',
                'available_roles' => ExternalResource::AUDIENCES,
                'content_types' => ['electronic_books'],
                'access_type' => 'open',
                'access_method' => 'public_url',
            ]),
            'availableRoles' => $this->availableRoleNames(),
            'contentTypes' => ExternalResource::CONTENT_TYPES,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['campus_only'] = $request->boolean('campus_only');
        $validated = $this->normaliseAccess($validated, $request);
        $validated = $this->protectContractFields($validated, $request);
        $validated['publication_status'] = 'draft';
        $validated['is_active'] = false;
        $validated['published_at'] = null;

        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = StoredUpload::put($request->file('logo'), 'external-resource-logos', 'public');
            $validated['logo_path'] = $newLogo;
        }
        $newLicence = null;
        if ($request->hasFile('licence_file')) {
            abort_unless($request->user()?->can('external_resources.manage_contracts'), 403);
            $newLicence = StoredUpload::put($request->file('licence_file'), 'external-resource-contracts', 'local');
            $validated['licence_file_path'] = $newLicence;
        }
        unset($validated['logo']);
        unset($validated['licence_file']);

        try {
            $resource = DB::transaction(function () use ($validated, $audit, $request): ExternalResource {
                $resource = ExternalResource::query()->create($validated);

                if ($resource->licence_file_path || $resource->contract_number) {
                    ExternalResourceContractVersion::create(['external_resource_id' => $resource->getKey(), 'version_number' => 1, 'contract_number' => $resource->contract_number, 'starts_at' => $resource->contract_starts_at, 'ends_at' => $resource->contract_ends_at, 'renewal_at' => $resource->renewal_at, 'licence_file_path' => $resource->licence_file_path, 'change_reason' => 'Initial contract record', 'created_by' => $request->user()?->getKey()]);
                }

                $audit->logRequired(
                    actionType: 'create',
                    entityType: 'external_resource',
                    entityId: $resource->getKey(),
                    newValues: $this->snapshot($resource),
                    scope: 'system',
                );

                return $resource;
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($newLogo, 'public');
            StoredUpload::deleteOrReport($newLicence, 'local');

            throw $exception;
        }

        return redirect()
            ->route('admin.external-resources.edit', $resource)
            ->with('success', __('common.created_successfully'));
    }

    public function edit(ExternalResource $externalResource): View
    {
        return view('admin.external-resources.form', [
            'resource' => $externalResource,
            'availableRoles' => $this->availableRoleNames(),
            'contentTypes' => ExternalResource::CONTENT_TYPES,
        ]);
    }

    public function update(
        Request $request,
        ExternalResource $externalResource,
        AuditLogger $audit,
        ExternalResourceWorkflow $workflow,
    ): RedirectResponse {
        $validated = $this->validated($request, $externalResource);
        $validated['campus_only'] = $request->boolean('campus_only');
        $validated = $this->normaliseAccess($validated, $request);
        $validated = $this->protectContractFields($validated, $request);
        unset(
            $validated['publication_status'],
            $validated['is_active'],
            $validated['published_at'],
            $validated['health_status'],
        );

        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = StoredUpload::put($request->file('logo'), 'external-resource-logos', 'public');
            $validated['logo_path'] = $newLogo;
        }
        $newLicence = null;
        if ($request->hasFile('licence_file')) {
            abort_unless($request->user()?->can('external_resources.manage_contracts'), 403);
            $newLicence = StoredUpload::put($request->file('licence_file'), 'external-resource-contracts', 'local');
            $validated['licence_file_path'] = $newLicence;
        }
        unset($validated['logo']);
        unset($validated['licence_file']);

        try {
            $oldLogo = DB::transaction(function () use ($externalResource, $validated, $audit, $request, $newLicence, $workflow): ?string {
                $locked = ExternalResource::query()
                    ->whereKey($externalResource->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $old = $this->snapshot($locked);
                $oldLogo = $locked->logo_path;
                $locked->fill($validated);
                $workflow->applyContentUpdateState($locked);
                $locked->save();
                $locked->refresh();
                if ($newLicence !== null) {
                    $version = ((int) $locked->contractVersions()->max('version_number')) + 1;
                    ExternalResourceContractVersion::create(['external_resource_id' => $locked->getKey(), 'version_number' => $version, 'contract_number' => $locked->contract_number, 'starts_at' => $locked->contract_starts_at, 'ends_at' => $locked->contract_ends_at, 'renewal_at' => $locked->renewal_at, 'licence_file_path' => $newLicence, 'change_reason' => trim((string) $request->input('contract_change_reason', 'Contract update')), 'created_by' => $request->user()?->getKey()]);
                }

                $audit->logRequired(
                    actionType: 'update',
                    entityType: 'external_resource',
                    entityId: $locked->getKey(),
                    oldValues: $old,
                    newValues: $this->snapshot($locked),
                    scope: 'system',
                );

                return $oldLogo;
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($newLogo, 'public');
            StoredUpload::deleteOrReport($newLicence, 'local');

            throw $exception;
        }

        $externalResource->refresh();

        if ($oldLogo && $externalResource->logo_path !== $oldLogo) {
            StoredUpload::deleteOrReport($oldLogo, 'public');
        }

        return back()->with('success', __('common.updated_successfully'));
    }

    public function reviewQueue(Request $request): View
    {
        return view('librarian.external-resources.review', [
            'librarianStaffUser' => $request->session()->get('library.user'),
            'resources' => ExternalResource::query()
                ->whereIn('publication_status', ['review', 'published', 'archived'])
                ->orderByRaw("CASE publication_status WHEN 'review' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")
                ->orderBy('sort_order')
                ->paginate(30),
        ]);
    }

    public function workflow(
        Request $request,
        ExternalResource $externalResource,
        ExternalResourceWorkflow $workflow,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['submit_review', 'publish', 'suspend', 'resume', 'archive', 'return_to_draft'])],
            'reason' => ['nullable', 'required_if:action,suspend,archive,return_to_draft', 'string', 'min:5', 'max:2000'],
        ]);
        $action = (string) $validated['action'];
        $requiresPublisher = in_array($action, ['publish', 'suspend', 'resume', 'archive', 'return_to_draft'], true);
        abort_unless(
            $requiresPublisher
                ? $request->user()?->can('external_resources.publish')
                : $request->user()?->can('external_resources.manage'),
            403,
        );

        $workflow->transition($externalResource, $action, $validated['reason'] ?? null);

        return back()->with('success', __('external_resources.workflow.success.'.$action));
    }

    public function destroy(
        Request $request,
        ExternalResource $externalResource,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        DB::transaction(function () use ($externalResource, $validated, $audit): void {
            ExternalResource::query()
                ->whereKey($externalResource->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $externalResource->refresh();
            $old = $this->snapshot($externalResource);
            $externalResource->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'external_resource',
                entityId: $externalResource->getKey(),
                oldValues: $old,
                reason: $validated['reason'],
                scope: 'system',
            );
        });

        return redirect()
            ->route('admin.external-resources.index')
            ->with('success', __('common.deleted_successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExternalResource $resource = null): array
    {
        $request->merge([
            'access_method' => $request->input('access_method', $resource?->access_method ?? 'public_url'),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(ExternalResource::TYPES)],
            'description' => ['required', 'string', 'max:10000'],
            'url' => [
                'nullable',
                'string',
                'max:2000',
                static function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (($value === null || trim((string) $value) === '')) {
                        return;
                    }
                    if (! is_string($value) || ! ExternalResource::isSafeDestination(
                        $value,
                        (string) $request->input('resource_type'),
                    )) {
                        $fail(__('external_resources.validation.unsafe_url'));
                    }
                },
            ],
            'health_check_url' => [
                'nullable',
                'string',
                'max:2000',
                static function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    if (! is_string($value) || ! ExternalResource::isSafeHealthDestination(
                        $value,
                        (string) $request->input('resource_type'),
                    )) {
                        $fail(__('external_resources.validation.unsafe_health_url'));
                    }
                },
            ],
            'available_roles' => ['required', 'array', 'min:1'],
            'available_roles.*' => ['required', Rule::in($this->availableRoleNames())],
            'license_expires_at' => ['nullable', 'date'],
            'access_instructions' => ['nullable', 'string', 'max:10000'],
            'logo' => [
                'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp', 'max:3072',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || ! method_exists($value, 'getRealPath')) {
                        return;
                    }
                    $dimensions = @getimagesize($value->getRealPath());
                    if (! is_array($dimensions)
                        || ! in_array((int) ($dimensions[2] ?? 0), [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
                        || (int) ($dimensions[0] ?? 0) < 1
                        || (int) ($dimensions[1] ?? 0) < 1) {
                        $fail(__('validation.image', ['attribute' => __('admin.external_resources.fields.logo')]));
                    }
                },
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'name_translations' => ['nullable', 'array'],
            'name_translations.kk' => ['nullable', 'string', 'max:255'],
            'name_translations.ru' => ['nullable', 'string', 'max:255'],
            'name_translations.en' => ['nullable', 'string', 'max:255'],
            'short_description_translations' => ['nullable', 'array'],
            'short_description_translations.kk' => ['nullable', 'string', 'max:1000'],
            'short_description_translations.ru' => ['nullable', 'string', 'max:1000'],
            'short_description_translations.en' => ['nullable', 'string', 'max:1000'],
            'description_translations' => ['nullable', 'array'],
            'description_translations.kk' => ['nullable', 'string', 'max:10000'],
            'description_translations.ru' => ['nullable', 'string', 'max:10000'],
            'description_translations.en' => ['nullable', 'string', 'max:10000'],
            'instructions_translations' => ['nullable', 'array'],
            'instructions_translations.kk' => ['nullable', 'string', 'max:10000'],
            'instructions_translations.ru' => ['nullable', 'string', 'max:10000'],
            'instructions_translations.en' => ['nullable', 'string', 'max:10000'],
            'content_types' => ['required', 'array', 'min:1'],
            'content_types.*' => ['required', Rule::in(ExternalResource::CONTENT_TYPES)],
            'access_type' => ['required', Rule::in(['campus', 'remote_auth', 'open'])],
            'category' => ['nullable', Rule::in(array_keys((array) config('external_resources.categories', [])))],
            'access_method' => ['required', Rule::in(['public_url', 'institutional_sso', 'ip_based', 'campus_only', 'personal_account', 'librarian_mediated', 'manual_instructions'])],
            'guest_access' => ['nullable', 'boolean'],
            'campus_only' => ['nullable', 'boolean'],
            'login_required' => ['nullable', 'boolean'],
            'contract_number' => ['nullable', 'string', 'max:255'],
            'contract_starts_at' => ['nullable', 'date'],
            'contract_ends_at' => ['nullable', 'date', 'after_or_equal:contract_starts_at'],
            'renewal_at' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'vendor_contact' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'statistics_url' => ['nullable', 'url:http,https', 'max:2000'],
            'renewal_status' => ['nullable', Rule::in(['not_required', 'pending', 'renewed', 'expired', 'cancelled'])],
            'licence_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'contract_change_reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'resource';
        $slug = $base;
        $counter = 2;
        while (ExternalResource::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    private function availableRoleNames(): array
    {
        return ExternalResource::AUDIENCES;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function normaliseAccess(array $validated, Request $request): array
    {
        $audiences = array_values(array_unique((array) ($validated['available_roles'] ?? [])));

        if (($validated['resource_type'] ?? null) === 'open_access') {
            $audiences = ExternalResource::AUDIENCES;
            $validated['access_type'] = 'open';
            $validated['access_method'] = 'public_url';
            $validated['campus_only'] = false;
        }

        $validated['available_roles'] = $audiences;
        $validated['guest_access'] = in_array('guest', $audiences, true);
        if ($validated['guest_access']) {
            $validated['login_required'] = false;
        } else {
            $validated['login_required'] = $request->boolean('login_required');
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function protectContractFields(array $validated, Request $request): array
    {
        if ($request->user()?->can('external_resources.manage_contracts')) {
            return $validated;
        }

        foreach ([
            'license_expires_at', 'contract_number', 'contract_starts_at', 'contract_ends_at',
            'renewal_at', 'renewal_status', 'responsible_user_id', 'vendor_contact',
            'statistics_url', 'internal_notes', 'licence_file', 'contract_change_reason',
        ] as $field) {
            unset($validated[$field]);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ExternalResource $resource): array
    {
        return [
            'title' => $resource->title,
            'provider' => $resource->provider,
            'resource_type' => $resource->resource_type,
            'description' => $resource->description,
            'url' => $resource->url,
            'health_check_url' => $resource->health_check_url,
            'available_roles' => $resource->available_roles,
            'license_expires_at' => $resource->license_expires_at?->toDateString(),
            'is_active' => (bool) $resource->is_active,
            'access_instructions' => $resource->access_instructions,
            'logo_path' => $resource->logo_path,
            'sort_order' => $resource->sort_order,
            'content_types' => $resource->content_types,
            'access_type' => $resource->access_type,
            'access_method' => $resource->access_method,
            'guest_access' => (bool) $resource->guest_access,
            'campus_only' => (bool) $resource->campus_only,
            'login_required' => (bool) $resource->login_required,
            'contract_starts_at' => $resource->contract_starts_at?->toDateString(),
            'contract_ends_at' => $resource->contract_ends_at?->toDateString(),
            'publication_status' => $resource->publication_status,
        ];
    }
}
