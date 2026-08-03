@extends('layouts.admin')

@section('title', __('roles.title').' — '.__('common.app_name'))

@section('content')
    @php
        $canonicalRoles = [
            'guest',
            'member',
            'librarian',
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
            'admin',
        ];
        $oldContext = old('form_context');
    @endphp

    <x-admin.page-header
        :title="__('roles.title')"
        :subtitle="__('roles.subtitle')"
    >
        @can('users.manage')
            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.users.index') }}">
                <span class="material-symbols-outlined text-[19px]">group</span>
                {{ __('admin.users.title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <details class="admin-card mb-6" @if($oldContext === 'new') open @endif>
        <summary class="flex cursor-pointer items-center justify-between gap-4">
            <span>
                <span class="block font-headline text-2xl text-primary">{{ __('roles.create') }}</span>
                <span class="mt-1 block text-sm text-slate-500">{{ __('roles.custom_role_hint') }}</span>
            </span>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-container text-white">
                <span class="material-symbols-outlined">add</span>
            </span>
        </summary>

        <form method="POST" action="{{ route('admin.roles.store') }}" class="mt-6 border-t border-slate-100 pt-6">
            @csrf
            <input type="hidden" name="form_context" value="new">

            <label class="mb-6 block max-w-xl">
                <span class="admin-label">{{ __('roles.name') }}</span>
                <input
                    class="admin-input"
                    type="text"
                    name="name"
                    value="{{ $oldContext === 'new' ? old('name') : '' }}"
                    maxlength="80"
                    required
                >
            </label>

            <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <h2 class="font-headline text-2xl text-primary">{{ __('roles.permission_matrix') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('roles.permission_matrix_hint') }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="admin-btn admin-btn-secondary py-2" type="button" data-permission-control="select">
                        {{ __('common.actions.select_all') }}
                    </button>
                    <button class="admin-btn admin-btn-secondary py-2" type="button" data-permission-control="deselect">
                        {{ __('common.actions.deselect_all') }}
                    </button>
                </div>
            </div>

            <div class="permission-matrix grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($permissionGroups as $domain => $permissions)
                    <fieldset class="rounded-xl border border-slate-200 p-4">
                        <legend class="px-2 text-xs font-bold uppercase tracking-[.12em] text-secondary">
                            {{ \Illuminate\Support\Facades\Lang::has('roles.domains.'.$domain) ? __('roles.domains.'.$domain) : $domain }}
                        </legend>
                        <div class="space-y-2">
                            @foreach ($permissions as $permission)
                                @php
                                    $permissionId = 'new-permission-'.\Illuminate\Support\Str::slug($permission->name);
                                    $isChecked = $oldContext === 'new' && in_array($permission->name, old('permissions', []), true);
                                @endphp
                                <label for="{{ $permissionId }}" class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                                    <input
                                        id="{{ $permissionId }}"
                                        class="mt-0.5 rounded border-slate-300 text-secondary focus:ring-secondary"
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->name }}"
                                        @checked($isChecked)
                                    >
                                    <span class="text-sm leading-5 text-slate-700">
                                        {{ \Illuminate\Support\Facades\Lang::has('permissions.'.$permission->name)
                                            ? __('permissions.'.$permission->name)
                                            : __('permissions.unknown', ['permission' => $permission->name]) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <button class="admin-btn admin-btn-primary" type="submit">
                    <span class="material-symbols-outlined text-[19px]">add_moderator</span>
                    {{ __('roles.create') }}
                </button>
            </div>
        </form>
    </details>

    <div class="space-y-4">
        @forelse ($roles as $role)
            @php
                $context = 'role-'.$role->getKey();
                $isCanonical = in_array($role->name, $canonicalRoles, true);
                $roleLabel = \Illuminate\Support\Facades\Lang::has('roles.names.'.$role->name)
                    ? __('roles.names.'.$role->name)
                    : $role->name;
                $assignedPermissionNames = $oldContext === $context
                    ? old('permissions', [])
                    : $role->permissions->pluck('name')->all();
            @endphp
            <details class="admin-card" @if($oldContext === $context) open @endif>
                <summary class="flex cursor-pointer flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <span class="flex min-w-0 items-center gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-primary">
                            <span class="material-symbols-outlined">shield_person</span>
                        </span>
                        <span class="min-w-0">
                            <strong class="block truncate font-headline text-2xl text-primary">{{ $roleLabel }}</strong>
                            <span class="mt-1 block truncate font-mono text-xs text-slate-400">{{ $role->name }}</span>
                        </span>
                    </span>
                    <span class="flex flex-wrap items-center gap-2">
                        <x-admin.status-badge
                            :status="$isCanonical ? 'system' : 'custom'"
                            :label="$isCanonical ? __('roles.system_role') : __('roles.custom_role')"
                        />
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $role->users_count }} · {{ __('roles.users_count') }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $role->permissions->count() }} · {{ __('roles.permissions_count') }}
                        </span>
                        <span class="material-symbols-outlined text-slate-400">expand_more</span>
                    </span>
                </summary>

                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="mt-6 border-t border-slate-100 pt-6">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="form_context" value="{{ $context }}">

                    <label class="mb-6 block max-w-xl">
                        <span class="admin-label">{{ __('roles.name') }}</span>
                        <input
                            class="admin-input"
                            type="text"
                            name="name"
                            value="{{ $oldContext === $context ? old('name') : $role->name }}"
                            maxlength="80"
                            required
                            @readonly($isCanonical)
                        >
                    </label>

                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <h2 class="font-headline text-2xl text-primary">{{ __('roles.permission_matrix') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('roles.permission_matrix_hint') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="admin-btn admin-btn-secondary py-2" type="button" data-permission-control="select">
                                {{ __('common.actions.select_all') }}
                            </button>
                            <button class="admin-btn admin-btn-secondary py-2" type="button" data-permission-control="deselect">
                                {{ __('common.actions.deselect_all') }}
                            </button>
                        </div>
                    </div>

                    <div class="permission-matrix grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($permissionGroups as $domain => $permissions)
                            <fieldset class="rounded-xl border border-slate-200 p-4">
                                <legend class="px-2 text-xs font-bold uppercase tracking-[.12em] text-secondary">
                                    {{ \Illuminate\Support\Facades\Lang::has('roles.domains.'.$domain) ? __('roles.domains.'.$domain) : $domain }}
                                </legend>
                                <div class="space-y-2">
                                    @foreach ($permissions as $permission)
                                        @php
                                            $permissionId = 'role-'.$role->getKey().'-permission-'.\Illuminate\Support\Str::slug($permission->name);
                                        @endphp
                                        <label for="{{ $permissionId }}" class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                                            <input
                                                id="{{ $permissionId }}"
                                                class="mt-0.5 rounded border-slate-300 text-secondary focus:ring-secondary"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission->name }}"
                                                @checked(in_array($permission->name, $assignedPermissionNames, true))
                                            >
                                            <span class="text-sm leading-5 text-slate-700">
                                                {{ \Illuminate\Support\Facades\Lang::has('permissions.'.$permission->name)
                                                    ? __('permissions.'.$permission->name)
                                                    : __('permissions.unknown', ['permission' => $permission->name]) }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button class="admin-btn admin-btn-primary" type="submit">
                            <span class="material-symbols-outlined text-[19px]">save</span>
                            {{ __('common.actions.save_changes') }}
                        </button>
                    </div>
                </form>
            </details>
        @empty
            <div class="admin-card py-16 text-center text-sm text-slate-500">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">shield</span>
                {{ __('roles.messages.empty') }}
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-permission-control]').forEach((button) => {
            button.addEventListener('click', () => {
                const form = button.closest('form');
                const checkboxes = form?.querySelectorAll('.permission-matrix input[type="checkbox"]') ?? [];
                const checked = button.dataset.permissionControl === 'select';

                checkboxes.forEach((checkbox) => {
                    checkbox.checked = checked;
                });
            });
        });
    </script>
@endpush
