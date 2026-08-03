@extends('layouts.admin')

@php($editing = $resource->exists)
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
                    @foreach (['licensed', 'open', 'partner', 'internal'] as $type)
                        <option value="{{ $type }}" @selected(old('resource_type', $resource->resource_type) === $type)>{{ __('admin.external_resources.types.'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-url">{{ __('admin.external_resources.fields.url') }}</label>
                <input class="admin-input" id="resource-url" type="url" name="url" required value="{{ old('url', $resource->url) }}" placeholder="https://">
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
                            <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="available_roles[]" value="{{ $role }}" @checked(in_array($role, old('available_roles', $resource->available_roles ?? []), true))>
                            {{ \Illuminate\Support\Facades\Lang::has('roles.names.'.$role) ? __('roles.names.'.$role) : $role }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="admin-label" for="resource-expiry">{{ __('admin.external_resources.fields.license_expires_at') }}</label>
                <input class="admin-input" id="resource-expiry" type="date" name="license_expires_at" value="{{ old('license_expires_at', $resource->license_expires_at?->format('Y-m-d')) }}">
            </div>
            <div>
                <label class="admin-label" for="resource-logo">{{ __('admin.external_resources.fields.logo') }}</label>
                <input class="admin-input" id="resource-logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            </div>
            <div class="sm:col-span-2">
                <label class="admin-label" for="resource-instructions">{{ __('admin.external_resources.fields.access_instructions') }}</label>
                <textarea class="admin-input min-h-28" id="resource-instructions" name="access_instructions">{{ old('access_instructions', $resource->access_instructions) }}</textarea>
            </div>
            <input type="hidden" name="sort_order" value="{{ old('sort_order', $resource->sort_order ?? 0) }}">
            <label class="flex items-center gap-3 text-sm sm:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input class="rounded border-slate-300 text-secondary focus:ring-secondary" type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource->is_active ?? true))>
                {{ __('admin.external_resources.fields.is_active') }}
            </label>
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
