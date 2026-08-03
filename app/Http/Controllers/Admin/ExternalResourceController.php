<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalResource;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\StoredUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Throwable;

class ExternalResourceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'resource_type' => ['nullable', Rule::in(['licensed', 'open', 'partner', 'internal'])],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expiring'])],
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
                ->whereBetween('license_expires_at', [now()->startOfDay(), now()->addDays(30)->endOfDay()]);
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
                'resource_type' => 'open',
                'is_active' => true,
                'available_roles' => ['guest', 'member', 'librarian', 'admin'],
            ]),
            'availableRoles' => $this->availableRoleNames(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['is_active'] = $request->boolean('is_active');

        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = StoredUpload::put($request->file('logo'), 'external-resource-logos', 'public');
            $validated['logo_path'] = $newLogo;
        }
        unset($validated['logo']);

        try {
            $resource = DB::transaction(function () use ($validated, $audit): ExternalResource {
                $resource = ExternalResource::query()->create($validated);

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
        ]);
    }

    public function update(
        Request $request,
        ExternalResource $externalResource,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $this->validated($request, $externalResource);
        $validated['is_active'] = $request->boolean('is_active');

        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = StoredUpload::put($request->file('logo'), 'external-resource-logos', 'public');
            $validated['logo_path'] = $newLogo;
        }
        unset($validated['logo']);

        try {
            $oldLogo = DB::transaction(function () use ($externalResource, $validated, $audit): ?string {
                ExternalResource::query()
                    ->whereKey($externalResource->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $externalResource->refresh();
                $old = $this->snapshot($externalResource);
                $oldLogo = $externalResource->logo_path;
                $externalResource->update($validated);
                $externalResource->refresh();

                $audit->logRequired(
                    actionType: 'update',
                    entityType: 'external_resource',
                    entityId: $externalResource->getKey(),
                    oldValues: $old,
                    newValues: $this->snapshot($externalResource),
                    scope: 'system',
                );

                return $oldLogo;
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($newLogo, 'public');

            throw $exception;
        }

        if ($oldLogo && $externalResource->logo_path !== $oldLogo) {
            StoredUpload::deleteOrReport($oldLogo, 'public');
        }

        return back()->with('success', __('common.updated_successfully'));
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
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(['licensed', 'open', 'partner', 'internal'])],
            'description' => ['required', 'string', 'max:10000'],
            'url' => ['required', 'url:http,https', 'max:2000'],
            'available_roles' => ['required', 'array', 'min:1'],
            'available_roles.*' => ['required', Rule::in($this->availableRoleNames())],
            'license_expires_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
            'access_instructions' => ['nullable', 'string', 'max:10000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
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
        return [
            'guest',
            ...Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        ];
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
            'available_roles' => $resource->available_roles,
            'license_expires_at' => $resource->license_expires_at?->toDateString(),
            'is_active' => (bool) $resource->is_active,
            'access_instructions' => $resource->access_instructions,
            'logo_path' => $resource->logo_path,
            'sort_order' => $resource->sort_order,
        ];
    }
}
