@extends('layouts.admin')

@section('title', __('admin.users.title').' — '.__('common.app_name'))

@section('content')
    @php
        $sortLink = static function (string $column) use ($filters): string {
            $currentSort = $filters['sort'] ?? 'created_at';
            $currentDirection = $filters['direction'] ?? 'desc';

            return route('admin.users.index', array_merge(
                request()->except(['page', 'sort', 'direction']),
                [
                    'sort' => $column,
                    'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
                ],
            ));
        };

        $sortIcon = static function (string $column) use ($filters): string {
            if (($filters['sort'] ?? 'created_at') !== $column) {
                return 'unfold_more';
            }

            return ($filters['direction'] ?? 'desc') === 'asc' ? 'arrow_upward' : 'arrow_downward';
        };
    @endphp

    <x-admin.page-header
        :title="__('admin.users.title')"
        :subtitle="__('admin.users.subtitle')"
    >
        @can('roles.manage')
            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.roles.index') }}">
                <span class="material-symbols-outlined text-[19px]">admin_panel_settings</span>
                {{ __('roles.title') }}
            </a>
        @endcan
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.imports.form', 'users') }}">
            <span class="material-symbols-outlined text-[19px]">upload</span>
            {{ __('admin.imports.import_action') }}
        </a>
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.users.export', request()->query()) }}">
            <span class="material-symbols-outlined text-[19px]">download</span>
            {{ __('admin.users.export') }}
        </a>
        <a class="admin-btn admin-btn-primary" href="{{ route('admin.users.create') }}">
            <span class="material-symbols-outlined text-[19px]">person_add</span>
            {{ __('admin.users.create') }}
        </a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.users.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <label class="sm:col-span-2 xl:col-span-1">
                <span class="admin-label">{{ __('common.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('admin.users.search_placeholder') }}"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('admin.users.filters.role') }}</span>
                <select class="admin-input" name="role">
                    <option value="">{{ __('common.filters.all_roles') }}</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>
                            {{ \Illuminate\Support\Facades\Lang::has('roles.names.'.$role->name) ? __('roles.names.'.$role->name) : $role->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('admin.users.filters.provider') }}</span>
                <select class="admin-input" name="auth_provider">
                    <option value="">{{ __('common.filters.all_providers') }}</option>
                    @foreach (['demo', 'ldap'] as $provider)
                        <option value="{{ $provider }}" @selected(($filters['auth_provider'] ?? '') === $provider)>
                            {{ __('admin.users.providers.'.$provider) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('admin.users.filters.activity') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ __('admin.users.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button class="admin-btn admin-btn-primary flex-1" type="submit">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('admin.users.index') }}"
                    title="{{ __('common.actions.clear_filters') }}"
                    aria-label="{{ __('common.actions.clear_filters') }}"
                >
                    <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                </a>
            </div>
        </div>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1060px]">
                <thead>
                    <tr>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('name') }}">
                                {{ __('admin.users.fields.full_name') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('name') }}</span>
                            </a>
                        </th>
                        <th>{{ __('admin.users.fields.role') }}</th>
                        <th>{{ __('admin.users.fields.auth_provider') }}</th>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('created_at') }}">
                                {{ __('admin.users.fields.registered_at') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('created_at') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('last_login_at') }}">
                                {{ __('admin.users.fields.last_login_at') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('last_login_at') }}</span>
                            </a>
                        </th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        @php
                            $roleName = (string) ($managedUser->getRoleNames()->first() ?: $managedUser->role);
                            $roleLabel = \Illuminate\Support\Facades\Lang::has('roles.names.'.$roleName)
                                ? __('roles.names.'.$roleName)
                                : $roleName;
                        @endphp
                        <tr>
                            <td>
                                <a class="group flex min-w-56 items-start gap-3" href="{{ route('admin.users.show', $managedUser) }}">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-headline text-lg font-semibold text-primary group-hover:bg-secondary-soft">
                                        {{ mb_strtoupper(mb_substr($managedUser->name, 0, 1)) }}
                                    </span>
                                    <span class="min-w-0">
                                        <strong class="block truncate text-sm text-primary group-hover:text-secondary">{{ $managedUser->name }}</strong>
                                        <span class="block truncate text-xs text-slate-500">{{ $managedUser->email }}</span>
                                        @if ($managedUser->department)
                                            <span class="mt-1 block truncate text-xs text-slate-400">{{ $managedUser->department }}</span>
                                        @endif
                                    </span>
                                </a>
                            </td>
                            <td>
                                <x-admin.status-badge status="role" :label="$roleLabel" />
                            </td>
                            <td>
                                <strong class="block text-sm">{{ __('admin.users.providers.'.$managedUser->auth_provider) }}</strong>
                                <span class="mt-1 block text-xs text-slate-500">{{ $managedUser->auth_source ?: '—' }}</span>
                                @if($managedUser->ad_samaccountname || $managedUser->ad_login)
                                    <span class="mt-1 block font-mono text-[11px] text-slate-500">{{ $managedUser->ad_samaccountname ?: $managedUser->ad_login }}</span>
                                @endif
                                @if($managedUser->readerProfile)
                                    <span class="mt-1 block text-[11px] text-slate-500">{{ $managedUser->readerProfile->category }} · {{ $managedUser->readerProfile->status }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">
                                {{ $managedUser->created_at?->utc()->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap text-slate-600">
                                {{ $managedUser->last_login_at?->utc()->format('Y-m-d H:i') ?? __('common.time.never') }}
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$managedUser->is_active ? 'active' : 'inactive'"
                                    :label="__('admin.users.statuses.'.($managedUser->is_active ? 'active' : 'inactive'))"
                                />
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <a
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                        href="{{ route('admin.users.show', $managedUser) }}"
                                        title="{{ __('common.actions.view_details') }}"
                                        aria-label="{{ __('common.actions.view_details') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <a
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                        href="{{ route('admin.users.edit', $managedUser) }}"
                                        title="{{ __('common.actions.edit') }}"
                                        aria-label="{{ __('common.actions.edit') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">person_search</span>
                                <span class="text-sm text-slate-500">{{ __('admin.users.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$users" />
    </section>
@endsection
