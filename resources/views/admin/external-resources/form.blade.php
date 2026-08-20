@extends('layouts.admin')

@php
    $editing = $resource->exists;
    $selectedAudiences = old('available_roles', $editing ? $resource->normalisedAudiences() : ($resource->available_roles ?? []));
    $selectedContentTypes = old('content_types', $resource->content_types ?? []);
@endphp
@section('title', ($editing ? __('admin.external_resources.edit') : __('admin.external_resources.create')).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :title="$editing ? __('admin.external_resources.edit') : __('admin.external_resources.create')"
        :subtitle="__('admin.external_resources.subtitle')"
    >
        <a href="{{ route('admin.external-resources.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>{{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-12">
        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $editing ? route('admin.external-resources.update', $resource) : route('admin.external-resources.store') }}"
            class="admin-card grid grid-cols-1 gap-5 sm:grid-cols-2 xl:col-span-8"
        >
            @csrf
            @if($editing) @method('PATCH') @endif

            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-title">{{ __('admin.external_resources.fields.name') }}</label>
                <input class="admin-input" id="resource-title" name="title" required value="{{ old('title', $resource->title) }}">
            </div>
            <div>
                <label class="admin-label" for="resource-provider">{{ __('admin.external_resources.fields.provider') }}</label>
                <input class="admin-input" id="resource-provider" name="provider" value="{{ old('provider', $resource->provider) }}">
            </div>
            <div>
                <label class="admin-label" for="resource-type">{{ __('admin.external_resources.fields.type') }}</label>
                <select class="admin-input" id="resource-type" name="resource_type" required>
                    @foreach (\App\Models\ExternalResource::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('resource_type', $resource->resource_type) === $type)>{{ __('admin.external_resources.types.'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="resource-access-type">{{ __('external_resources.admin.access_type') }}</label>
                <select class="admin-input" id="resource-access-type" name="access_type" required>
                    @foreach (['open', 'remote_auth', 'campus'] as $accessType)
                        <option value="{{ $accessType }}" @selected(old('access_type', $resource->access_type ?: 'open') === $accessType)>{{ __('external_resources.access_types.'.$accessType.'.label') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="resource-category">{{ __('external_resources.admin.content_category') }}</label>
                <select class="admin-input" id="resource-category" name="category">
                    <option value="">{{ __('external_resources.admin.not_specified') }}</option>
                    @foreach (array_keys((array) config('external_resources.categories', [])) as $category)
                        <option value="{{ $category }}" @selected(old('category', $resource->category) === $category)>{{ __('external_resources.categories.'.$category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-url">{{ __('admin.external_resources.fields.url') }}</label>
                <input class="admin-input" id="resource-url" type="text" name="url" value="{{ old('url', $resource->url) }}" placeholder="https://example.org или /catalog">
                <p class="mt-1 text-xs text-slate-500">{{ __('external_resources.admin.url_hint') }}</p>
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-health-url">{{ __('external_resources.admin.health_check_url') }}</label>
                <input class="admin-input" id="resource-health-url" type="text" name="health_check_url" value="{{ old('health_check_url', $resource->health_check_url) }}" placeholder="https://example.org/health">
                <p class="mt-1 text-xs text-slate-500">{{ __('external_resources.admin.health_check_url_hint') }}</p>
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-description">{{ __('admin.external_resources.fields.description') }}</label>
                <textarea class="admin-input min-h-36" id="resource-description" name="description" required>{{ old('description', $resource->description) }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <span class="admin-label">{{ __('admin.external_resources.fields.available_roles') }}</span>
                <div class="grid grid-cols-2 gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-4">
                    @foreach ($availableRoles as $role)
                        <label class="flex items-center gap-2 text-sm">
                            <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="available_roles[]" value="{{ $role }}" @checked(in_array($role, $selectedAudiences, true))>
                            {{ __('external_resources.audiences.'.$role) }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ __('external_resources.admin.audiences_hint') }}</p>
            </div>
            <div class="sm:col-span-2">
                <span class="admin-label">{{ __('external_resources.admin.content_types') }}</span>
                <div class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-2">
                    @foreach ($contentTypes as $contentType)
                        <label class="flex items-center gap-2 text-sm">
                            <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="content_types[]" value="{{ $contentType }}" @checked(in_array($contentType, $selectedContentTypes, true))>
                            {{ __('external_resources.content_types.'.$contentType) }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="admin-label" for="resource-expiry">{{ __('admin.external_resources.fields.license_expires_at') }}</label>
                <input class="admin-input" id="resource-expiry" type="date" name="license_expires_at" value="{{ old('license_expires_at', $resource->license_expires_at?->format('Y-m-d')) }}" @cannot('external_resources.manage_contracts') disabled @endcannot>
            </div>
            <div>
                <label class="admin-label" for="resource-logo">{{ __('admin.external_resources.fields.logo') }}</label>
                <input class="admin-input" id="resource-logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                @if ($resource->logo_path)
                    <p class="mt-2 text-xs text-slate-500">{{ __('external_resources.admin.current_logo') }}</p>
                @endif
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-instructions">{{ __('admin.external_resources.fields.access_instructions') }}</label>
                <textarea class="admin-input min-h-28" id="resource-instructions" name="access_instructions">{{ old('access_instructions', $resource->access_instructions) }}</textarea>
            </div>
            <div>
                <label class="admin-label" for="resource-access-method">{{ __('digital.external.fields.access_method') }}</label>
                <select class="admin-input" id="resource-access-method" name="access_method">
                    @foreach (['public_url', 'institutional_sso', 'ip_based', 'campus_only', 'personal_account', 'librarian_mediated', 'manual_instructions'] as $method)
                        <option value="{{ $method }}" @selected(old('access_method', $resource->access_method ?: 'public_url') === $method)>{{ __('digital.external.access_methods.'.$method) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-label" for="resource-publication-status">{{ __('digital.external.fields.publication_status') }}</label>
                <p id="resource-publication-status" class="admin-input flex items-center bg-surface-low text-slate-700">
                    {{ __('digital.external.publication_statuses.'.($resource->publication_status ?: 'draft')) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">{{ __('external_resources.admin.publish_hint') }}</p>
            </div>
            @foreach (['campus_only', 'login_required'] as $flag)
                <label class="flex items-center gap-3 text-sm">
                    <input type="hidden" name="{{ $flag }}" value="0">
                    <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="{{ $flag }}" value="1" @checked(old($flag, $resource->{$flag} ?? false))>
                    {{ __('digital.external.fields.'.$flag) }}
                </label>
            @endforeach
            <div class="sm:col-span-2 rounded-xl border border-slate-200 p-4">
                <h2 class="font-headline text-xl text-primary">{{ __('external_resources.admin.translations') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('external_resources.admin.translations_hint') }}</p>
                <div class="mt-4 grid grid-cols-1 gap-4">
                    @foreach (['kk', 'ru', 'en'] as $locale)
                        <details class="rounded-xl bg-surface-low p-4" @if($locale === app()->getLocale()) open @endif>
                            <summary class="cursor-pointer font-semibold">{{ __('external_resources.locales.'.$locale) }}</summary>
                            <div class="mt-4 grid grid-cols-1 gap-4">
                                <div>
                                    <label class="admin-label" for="name-{{ $locale }}">{{ __('admin.external_resources.fields.name') }}</label>
                                    <input class="admin-input" id="name-{{ $locale }}" name="name_translations[{{ $locale }}]" value="{{ old('name_translations.'.$locale, data_get($resource->name_translations, $locale)) }}">
                                </div>
                                <div>
                                    <label class="admin-label" for="short-description-{{ $locale }}">{{ __('external_resources.admin.short_description') }}</label>
                                    <textarea class="admin-input min-h-20" id="short-description-{{ $locale }}" name="short_description_translations[{{ $locale }}]">{{ old('short_description_translations.'.$locale, data_get($resource->short_description_translations, $locale)) }}</textarea>
                                </div>
                                <div>
                                    <label class="admin-label" for="description-{{ $locale }}">{{ __('admin.external_resources.fields.description') }}</label>
                                    <textarea class="admin-input min-h-28" id="description-{{ $locale }}" name="description_translations[{{ $locale }}]">{{ old('description_translations.'.$locale, data_get($resource->description_translations, $locale)) }}</textarea>
                                </div>
                                <div>
                                    <label class="admin-label" for="instructions-{{ $locale }}">{{ __('admin.external_resources.fields.access_instructions') }}</label>
                                    <textarea class="admin-input min-h-24" id="instructions-{{ $locale }}" name="instructions_translations[{{ $locale }}]">{{ old('instructions_translations.'.$locale, data_get($resource->instructions_translations, $locale)) }}</textarea>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
            @can('external_resources.manage_contracts')
                <div><label class="admin-label" for="contract-number">{{ __('digital.external.fields.contract_number') }}</label><input class="admin-input" id="contract-number" name="contract_number" value="{{ old('contract_number', $resource->contract_number) }}"></div>
                <div><label class="admin-label" for="contract-start">{{ __('digital.external.fields.contract_starts_at') }}</label><input class="admin-input" id="contract-start" type="date" name="contract_starts_at" value="{{ old('contract_starts_at', $resource->contract_starts_at?->format('Y-m-d')) }}"></div>
                <div><label class="admin-label" for="contract-end">{{ __('digital.external.fields.contract_ends_at') }}</label><input class="admin-input" id="contract-end" type="date" name="contract_ends_at" value="{{ old('contract_ends_at', $resource->contract_ends_at?->format('Y-m-d')) }}"></div>
                <div><label class="admin-label" for="renewal-at">{{ __('digital.external.fields.renewal_at') }}</label><input class="admin-input" id="renewal-at" type="date" name="renewal_at" value="{{ old('renewal_at', $resource->renewal_at?->format('Y-m-d')) }}"></div>
                <div><label class="admin-label" for="licence-file">{{ __('digital.external.fields.licence_file') }}</label><input class="admin-input" id="licence-file" type="file" name="licence_file" accept=".pdf,.jpg,.jpeg,.png"></div>
                <div><label class="admin-label" for="contract-change-reason">{{ __('digital.external.fields.contract_change_reason') }}</label><input class="admin-input" id="contract-change-reason" name="contract_change_reason"></div>
            @endcan
            @can('external_resources.manage_contracts')
                <div class="sm:col-span-2"><label class="admin-label" for="internal-notes">{{ __('digital.external.fields.internal_notes') }}</label><textarea class="admin-input" id="internal-notes" name="internal_notes">{{ old('internal_notes', $resource->internal_notes) }}</textarea></div>
            @endcan
            <input type="hidden" name="sort_order" value="{{ old('sort_order', $resource->sort_order ?? 0) }}">
            <div class="sm:col-span-2">
                <button class="admin-btn admin-btn-primary" type="submit">
                    <span class="material-symbols-outlined text-[19px]">save</span>{{ __('common.actions.save_changes') }}
                </button>
            </div>
        </form>

        <aside class="space-y-5 xl:col-span-4">
            <div class="admin-card">
                <span class="material-symbols-outlined mb-3 text-3xl text-secondary">license</span>
                <h2 class="font-headline text-2xl text-primary">{{ __('admin.external_resources.fields.license_expires_at') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('admin.external_resources.license.expiring_soon') }}</p>
            </div>

            @if ($editing)
                <div class="admin-card">
                    <span class="material-symbols-outlined mb-3 text-3xl text-secondary">fact_check</span>
                    <h2 class="font-headline text-2xl text-primary">{{ __('external_resources.workflow.queue_title') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ __('digital.external.publication_statuses.'.$resource->publication_status) }} ·
                        {{ __('external_resources.health.'.$resource->health_status) }}
                        @if($resource->last_checked_at) · {{ $resource->last_checked_at->format('d.m.Y H:i') }} @endif
                    </p>
                    @if ($resource->publicationReadinessIssues() !== [])
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-800">
                            @foreach ($resource->publicationReadinessIssues() as $issue)
                                <li>{{ __('external_resources.readiness.'.$issue) }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (in_array($resource->publication_status, ['draft', 'review'], true))
                        <form method="POST" action="{{ route('admin.external-resources.workflow', $resource) }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="action" value="submit_review">
                            <button class="admin-btn admin-btn-secondary w-full" type="submit">{{ __('external_resources.workflow.actions.submit_review') }}</button>
                        </form>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.external-resources.destroy', $resource) }}" class="admin-card border border-red-100">
                    @csrf
                    @method('DELETE')
                    <h2 class="font-headline text-2xl text-red-900">{{ __('admin.external_resources.delete') }}</h2>
                    <label class="admin-label mt-4" for="resource-delete-reason">{{ __('common.validation.reason_required') }}</label>
                    <textarea class="admin-input min-h-24" id="resource-delete-reason" name="reason" required minlength="5"></textarea>
                    <button class="admin-btn admin-btn-danger mt-4 w-full" type="submit">{{ __('common.actions.delete') }}</button>
                </form>
            @endif
        </aside>
    </div>
@endsection
