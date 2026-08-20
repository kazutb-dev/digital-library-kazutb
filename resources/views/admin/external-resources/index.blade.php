@extends('layouts.admin')

@section('title', __('admin.external_resources.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :title="__('admin.external_resources.title')"
        :subtitle="__('admin.external_resources.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.imports.form', 'external-resources') }}">
            <span class="material-symbols-outlined text-[19px]">upload</span>
            {{ __('admin.imports.import_action') }}
        </a>
        <a href="{{ route('admin.external-resources.create') }}" class="admin-btn admin-btn-primary">
            <span class="material-symbols-outlined text-[19px]">add_link</span>{{ __('admin.external_resources.create') }}
        </a>
    </x-admin.page-header>

    <form method="GET" class="admin-card mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <div>
            <label class="admin-label" for="resource-search">{{ __('common.filters.search') }}</label>
            <input class="admin-input" id="resource-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('common.filters.search_placeholder') }}">
        </div>
        <div>
            <label class="admin-label" for="resource-type">{{ __('admin.external_resources.fields.type') }}</label>
            <select class="admin-input" id="resource-type" name="resource_type">
                <option value="">{{ __('common.fields.all') }}</option>
                @foreach (\App\Models\ExternalResource::TYPES as $type)
                    <option value="{{ $type }}" @selected(($filters['resource_type'] ?? '') === $type)>{{ __('admin.external_resources.types.'.$type) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="admin-label" for="resource-status">{{ __('common.fields.status') }}</label>
            <select class="admin-input" id="resource-status" name="status">
                <option value="">{{ __('common.filters.all_statuses') }}</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('common.status.active') }}</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('common.status.inactive') }}</option>
                <option value="expiring" @selected(($filters['status'] ?? '') === 'expiring')>{{ __('common.status.expiring_soon') }}</option>
                <option value="expired" @selected(($filters['status'] ?? '') === 'expired')>{{ __('external_resources.statuses.expired') }}</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="admin-btn admin-btn-primary flex-1" type="submit">{{ __('common.actions.apply_filters') }}</button>
            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.external-resources.index') }}" aria-label="{{ __('common.actions.clear_filters') }}">
                <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
            </a>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        @forelse ($resources as $resource)
            @php
                $effectiveExpiry = $resource->effectiveExpiryDate();
                $expired = $resource->licenceExpired();
                $expiring = ! $expired && $resource->expiresSoon(30);
                $licenseStatus = $expired ? 'expired' : ($expiring ? 'expiring_soon' : 'active');
                $licenseLabel = $effectiveExpiry
                    ? __('admin.external_resources.license.'.($expired ? 'expired' : ($expiring ? 'expiring_soon' : 'valid')))
                    : __('admin.external_resources.license.not_applicable');
            @endphp
            <article class="admin-card flex flex-col">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface-low text-secondary">
                        @if ($resource->logo_path)
                            <img src="{{ asset('storage/'.$resource->logo_path) }}" alt="" class="h-full w-full object-contain p-2">
                        @else
                            <span class="material-symbols-outlined text-3xl">public</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-2 flex flex-wrap gap-2">
                            <x-admin.status-badge :status="$resource->is_active ? 'active' : 'inactive'" :label="__('common.status.'.($resource->is_active ? 'active' : 'inactive'))" />
                            <x-admin.status-badge :status="$licenseStatus" :label="$licenseLabel" />
                            <x-admin.status-badge :status="$resource->publication_status" :label="__('digital.external.publication_statuses.'.$resource->publication_status)" />
                        </div>
                        <h2 class="font-headline text-2xl leading-tight text-primary">{{ $resource->title }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ __('admin.external_resources.types.'.$resource->resource_type) }}@if($resource->provider) · {{ $resource->provider }}@endif</p>
                    </div>
                </div>

                <p class="mt-5 line-clamp-3 text-sm leading-6 text-slate-600">{{ $resource->description }}</p>

                <dl class="mt-5 grid grid-cols-1 gap-3 rounded-xl bg-surface-low p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">{{ __('admin.external_resources.fields.available_roles') }}</dt>
                        <dd class="mt-1 font-semibold">
                            {{ collect($resource->available_roles)->map(fn ($role) => \Illuminate\Support\Facades\Lang::has('roles.names.'.$role) ? __('roles.names.'.$role) : $role)->join(', ') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">{{ __('admin.external_resources.fields.license_expires_at') }}</dt>
                        <dd @class([
                            'mt-1 font-semibold',
                            'text-red-700' => $expired,
                            'text-amber-700' => $expiring,
                        ])>
                            {{ $effectiveExpiry?->format('d.m.Y') ?? __('external_resources.expiry.not_specified') }}
                            @if ($expiring)
                                · {{ __('admin.external_resources.license.days_remaining', ['count' => (int) today('UTC')->diffInDays($effectiveExpiry)]) }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($resource->content_types))
                    <div class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('external_resources.admin.content_types') }}">
                        @foreach ($resource->content_types as $contentType)
                            <span class="rounded-full bg-surface-low px-3 py-1 text-xs font-semibold text-slate-600">{{ __('external_resources.content_types.'.$contentType) }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($resource->access_instructions)
                    <div class="mt-3 rounded-xl border border-secondary/20 bg-secondary-soft/40 p-4">
                        <p class="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-secondary">
                            <span class="material-symbols-outlined text-[16px]">menu_book</span>
                            {{ __('admin.external_resources.fields.access_instructions') }}
                        </p>
                        <p class="line-clamp-3 text-sm leading-6 text-slate-700">{{ $resource->access_instructions }}</p>
                    </div>
                @endif

                <div class="mt-auto flex flex-wrap gap-2 pt-5">
                    <a href="{{ route('admin.external-resources.edit', $resource) }}" class="admin-btn admin-btn-primary">
                        <span class="material-symbols-outlined text-[18px]">edit</span>{{ __('common.actions.edit') }}
                    </a>
                    @if ($resource->publication_status === 'published' && $resource->is_active && $resource->readyForPublication())
                        <a href="{{ route('external-resources.open', $resource) }}" rel="noopener noreferrer" target="_blank" class="admin-btn admin-btn-secondary">
                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>{{ __('common.actions.open') }}
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <div class="admin-card col-span-full py-14 text-center text-sm text-slate-500">{{ __('admin.external_resources.empty') }}</div>
        @endforelse
    </div>

    <div class="mt-6 overflow-hidden rounded-xl bg-white">
        <x-admin.pagination :paginator="$resources" />
    </div>
@endsection
