@extends('layouts.admin')

@php
    $roleName = (string) ($managedUser->getRoleNames()->first() ?: $managedUser->role);
    $roleLabel = \Illuminate\Support\Facades\Lang::has('roles.names.'.$roleName)
        ? __('roles.names.'.$roleName)
        : $roleName;
    $providerLabel = \Illuminate\Support\Facades\Lang::has('admin.users.providers.'.$managedUser->auth_provider)
        ? __('admin.users.providers.'.$managedUser->auth_provider)
        : $managedUser->auth_provider;
    $permissionGroups = $effectivePermissions->groupBy(
        static fn ($permission): string => str((string) $permission->name)->before('.')->value()
    );
@endphp

@section('title', __('admin.users.details').' — '.$managedUser->name)

@section('content')
    <x-admin.page-header
        :title="$managedUser->name"
        :subtitle="__('admin.users.details')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.users.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @can('system.logs')
            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.logs.index', ['actor' => $managedUser->getKey()]) }}">
                <span class="material-symbols-outlined text-[19px]">history</span>
                {{ __('admin.nav.audit_logs') }}
            </a>
        @endcan
        <a class="admin-btn admin-btn-primary" href="{{ route('admin.users.edit', $managedUser) }}">
            <span class="material-symbols-outlined text-[19px]">edit</span>
            {{ __('common.actions.edit') }}
        </a>
    </x-admin.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
        <div class="space-y-6">
            <section class="admin-card">
                <div class="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary-container font-headline text-3xl font-semibold text-white">
                            {{ mb_strtoupper(mb_substr($managedUser->name, 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="truncate font-headline text-3xl text-primary">{{ $managedUser->name }}</h2>
                            <p class="truncate text-sm text-slate-500">{{ $managedUser->email }}</p>
                        </div>
                    </div>
                    <x-admin.status-badge
                        :status="$managedUser->is_active ? 'active' : 'inactive'"
                        :label="__('admin.users.statuses.'.($managedUser->is_active ? 'active' : 'inactive'))"
                    />
                </div>

                <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.role') }}</dt>
                        <dd class="text-sm font-semibold text-primary">{{ $roleLabel }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.auth_provider') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $providerLabel }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.ad_login') }}</dt>
                        <dd class="break-words text-sm text-slate-700">{{ $managedUser->ad_login ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.external_id') }}</dt>
                        <dd class="break-words text-sm text-slate-700">{{ $managedUser->external_id ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.department') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $managedUser->department ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.locale') }}</dt>
                        <dd class="text-sm text-slate-700">{{ __('common.languages.'.($managedUser->locale ?: 'kk')) }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.registered_at') }}</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $managedUser->created_at?->utc()->format('Y-m-d H:i:s') ?? '—' }}
                            <span class="text-xs text-slate-400">{{ __('common.time.utc') }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('admin.users.fields.last_login_at') }}</dt>
                        <dd class="text-sm text-slate-700">
                            @if ($managedUser->last_login_at)
                                {{ $managedUser->last_login_at->utc()->format('Y-m-d H:i:s') }}
                                <span class="text-xs text-slate-400">{{ __('common.time.utc') }}</span>
                            @else
                                {{ __('common.time.never') }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-headline text-2xl text-primary">{{ __('admin.users.fields.effective_permissions') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ __('roles.permission_matrix_hint') }}</p>
                    </div>
                    <x-admin.status-badge status="permissions" :label="$effectivePermissions->count().' '.__('roles.permissions_count')" />
                </div>

                @forelse ($permissionGroups as $domain => $permissions)
                    <div class="border-t border-slate-100 py-5 first:border-t-0 first:pt-0">
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-[.12em] text-secondary">
                            {{ \Illuminate\Support\Facades\Lang::has('roles.domains.'.$domain) ? __('roles.domains.'.$domain) : $domain }}
                        </h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <div class="flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <span class="material-symbols-outlined mt-0.5 text-[17px] text-emerald-700">check_circle</span>
                                    <span>
                                        {{ \Illuminate\Support\Facades\Lang::has('permissions.'.$permission->name)
                                            ? __('permissions.'.$permission->name)
                                            : __('permissions.unknown', ['permission' => $permission->name]) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                        {{ __('admin.users.no_permissions') }}
                    </div>
                @endforelse
            </section>
        </div>

        <aside class="space-y-6">
            <section class="admin-card">
                <h2 class="mb-2 font-headline text-2xl text-primary">{{ __('common.fields.status') }}</h2>
                <p class="mb-5 text-sm leading-6 text-slate-500">
                    {{ $managedUser->is_active ? __('admin.users.statuses.active') : __('admin.users.statuses.inactive') }}
                </p>

                <form
                    method="POST"
                    action="{{ route('admin.users.active', $managedUser) }}"
                    onsubmit="return confirm(@js($managedUser->is_active ? __('admin.users.confirm_deactivate', ['name' => $managedUser->name]) : __('admin.users.confirm_activate', ['name' => $managedUser->name])))"
                >
                    @csrf
                    @method('PATCH')
                    <label>
                        <span class="admin-label">{{ __('common.fields.reason') }}</span>
                        <textarea
                            class="admin-input min-h-28 resize-y"
                            name="reason"
                            minlength="5"
                            maxlength="1000"
                            placeholder="{{ __('common.validation.reason_required') }}"
                            required
                        ></textarea>
                    </label>
                    <button
                        @class([
                            'admin-btn mt-4 w-full',
                            'admin-btn-danger' => $managedUser->is_active,
                            'admin-btn-primary' => ! $managedUser->is_active,
                        ])
                        type="submit"
                    >
                        <span class="material-symbols-outlined text-[19px]">{{ $managedUser->is_active ? 'person_off' : 'person_check' }}</span>
                        {{ $managedUser->is_active ? __('common.actions.deactivate') : __('common.actions.activate') }}
                    </button>
                </form>
            </section>

            @if (session('temporary_password'))
                <section class="admin-card border-2 border-amber-300 !bg-amber-50">
                    <h2 class="mb-2 flex items-center gap-2 font-headline text-2xl text-amber-900">
                        <span class="material-symbols-outlined">key</span>
                        {{ __('admin.users.password_reset.temporary_password') }}
                    </h2>
                    <p class="mb-3 text-sm leading-6 text-amber-800">{{ __('admin.users.password_reset.shown_once') }}</p>
                    <code class="block select-all break-all rounded-lg bg-white px-4 py-3 font-mono text-base font-bold tracking-wide text-slate-900">{{ session('temporary_password') }}</code>
                </section>
            @endif

            @unless ($managedUser->is(auth()->user()))
                <section class="admin-card">
                    <h2 class="mb-2 font-headline text-2xl text-primary">{{ __('admin.users.password_reset.title') }}</h2>
                    <p class="mb-5 text-sm leading-6 text-slate-500">{{ __('admin.users.password_reset.hint') }}</p>
                    <form method="POST" action="{{ route('admin.users.reset-password', $managedUser) }}">
                        @csrf
                        <label>
                            <span class="admin-label">{{ __('admin.users.password_reset.password_label') }}</span>
                            <input
                                class="admin-input"
                                type="text"
                                name="password"
                                minlength="12"
                                maxlength="255"
                                autocomplete="off"
                                placeholder="{{ __('admin.users.password_reset.password_placeholder') }}"
                            >
                        </label>
                        <label class="mt-3 block">
                            <span class="admin-label">{{ __('common.fields.reason') }}</span>
                            <textarea
                                class="admin-input min-h-20 resize-y"
                                name="reason"
                                minlength="5"
                                maxlength="1000"
                                placeholder="{{ __('common.validation.reason_required') }}"
                                required
                            ></textarea>
                        </label>
                        <button class="admin-btn admin-btn-secondary mt-4 w-full" type="submit">
                            <span class="material-symbols-outlined text-[19px]">lock_reset</span>
                            {{ __('admin.users.password_reset.action') }}
                        </button>
                    </form>
                </section>

                <section class="admin-card">
                    <h2 class="mb-2 font-headline text-2xl text-primary">{{ __('admin.users.sessions.title') }}</h2>
                    <p class="mb-5 text-sm leading-6 text-slate-500">{{ __('admin.users.sessions.hint') }}</p>
                    <form
                        method="POST"
                        action="{{ route('admin.users.revoke-sessions', $managedUser) }}"
                        onsubmit="return confirm(@js(__('admin.users.sessions.confirm', ['name' => $managedUser->name])))"
                    >
                        @csrf
                        <label>
                            <span class="admin-label">{{ __('common.fields.reason') }}</span>
                            <textarea
                                class="admin-input min-h-20 resize-y"
                                name="reason"
                                minlength="5"
                                maxlength="1000"
                                placeholder="{{ __('common.validation.reason_required') }}"
                                required
                            ></textarea>
                        </label>
                        <button class="admin-btn admin-btn-danger mt-4 w-full" type="submit">
                            <span class="material-symbols-outlined text-[19px]">logout</span>
                            {{ __('admin.users.sessions.action') }}
                        </button>
                    </form>
                </section>
            @endunless

            <section class="rounded-xl bg-primary-container p-6 text-white">
                <span class="material-symbols-outlined mb-4 text-3xl text-cyan-300">shield_person</span>
                <h2 class="font-headline text-2xl">{{ $roleLabel }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    {{ \Illuminate\Support\Facades\Lang::has('roles.descriptions.'.$roleName)
                        ? __('roles.descriptions.'.$roleName)
                        : __('roles.custom_role_hint') }}
                </p>
            </section>
        </aside>
    </div>
@endsection
