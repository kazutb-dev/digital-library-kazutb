@extends('layouts.admin')

@section('title', __('admin.audit.title').' — '.__('common.app_name'))

@section('content')
    @php
        $actionLabel = static function (?string $action): string {
            $action = (string) $action;
            $full = str($action)->replace(['.', '-'], '_')->value();
            $short = str($action)->afterLast('.')->replace('-', '_')->value();

            foreach (array_unique([$full, $short]) as $normalized) {
                if (\Illuminate\Support\Facades\Lang::has('admin.audit.actions.'.$normalized)) {
                    return __('admin.audit.actions.'.$normalized);
                }
            }

            return $action;
        };

        $entityLabel = static function (?string $entity): string {
            $entity = (string) $entity;
            $normalized = str($entity)->afterLast('\\')->snake()->value();

            return \Illuminate\Support\Facades\Lang::has('admin.audit.entities.'.$normalized)
                ? __('admin.audit.entities.'.$normalized)
                : $entity;
        };

        $actionTone = static function (?string $action): string {
            $action = str((string) $action)->lower()->value();

            if (str_contains($action, 'fail') || str_contains($action, 'delete') || str_contains($action, 'remove') || str_contains($action, 'reject')) {
                return 'failed';
            }

            if (str_contains($action, 'create') || str_contains($action, 'approve') || str_contains($action, 'publish') || str_contains($action, 'activate') || str_contains($action, 'resolve')) {
                return 'active';
            }

            return 'event';
        };

        $sortLink = static function (string $column) use ($filters): string {
            $currentSort = $filters['sort'] ?? 'occurred_at';
            $currentDirection = $filters['direction'] ?? 'desc';

            return route('admin.logs.index', array_merge(
                request()->except(['page', 'sort', 'direction']),
                [
                    'sort' => $column,
                    'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
                ],
            ));
        };

        $sortIcon = static function (string $column) use ($filters): string {
            if (($filters['sort'] ?? 'occurred_at') !== $column) {
                return 'unfold_more';
            }

            return ($filters['direction'] ?? 'desc') === 'asc' ? 'arrow_upward' : 'arrow_downward';
        };
    @endphp

    <x-admin.page-header
        :eyebrow="__('admin.audit.eyebrow')"
        :title="__('admin.audit.title')"
        :subtitle="__('admin.audit.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.error-log.index') }}">
            <span class="material-symbols-outlined text-[19px]">bug_report</span>
            {{ __('admin.error_log.title') }}
        </a>
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.logs.export', request()->query()) }}">
            <span class="material-symbols-outlined text-[19px]">download</span>
            {{ __('admin.audit.export') }}
        </a>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.logs.index') }}" class="admin-card mb-6">
        <div class="mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-secondary">filter_alt</span>
            <h2 class="font-headline text-2xl text-primary">{{ __('admin.audit.advanced_filters') }}</h2>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label>
                <span class="admin-label">{{ __('admin.audit.filters.actor') }}</span>
                <input
                    class="admin-input"
                    type="search"
                    name="actor"
                    value="{{ $filters['actor'] ?? '' }}"
                    placeholder="{{ __('admin.audit.search_placeholder') }}"
                >
            </label>

            <label>
                <span class="admin-label">{{ __('admin.audit.filters.action_type') }}</span>
                <select class="admin-input" name="action_type">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach ($actionTypes as $actionType)
                        <option value="{{ $actionType }}" @selected(($filters['action_type'] ?? '') === $actionType)>
                            {{ $actionLabel($actionType) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('admin.audit.filters.entity_type') }}</span>
                <select class="admin-input" name="entity_type">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach ($entityTypes as $entityType)
                        <option value="{{ $entityType }}" @selected(($filters['entity_type'] ?? '') === $entityType)>
                            {{ $entityLabel($entityType) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('common.filters.date_from') }}</span>
                <input class="admin-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>

            <label>
                <span class="admin-label">{{ __('common.filters.date_to') }}</span>
                <input class="admin-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2.5">
                <input
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-secondary focus:ring-secondary"
                    type="checkbox"
                    name="login_watch"
                    value="1"
                    @checked($filters['login_watch'] ?? false)
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-800">{{ __('admin.audit.filters.login_watch') }}</span>
                    <span class="mt-0.5 block text-xs leading-4 text-slate-500">{{ __('admin.audit.filters.login_watch_hint') }}</span>
                </span>
            </label>
        </div>

        <div class="mt-5 flex flex-wrap justify-end gap-2">
            <a class="admin-btn admin-btn-secondary" href="{{ route('admin.logs.index') }}">
                <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                {{ __('common.actions.clear_filters') }}
            </a>
            <button class="admin-btn admin-btn-primary" type="submit">
                <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                {{ __('common.actions.apply_filters') }}
            </button>
        </div>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-headline text-2xl text-primary">{{ __('admin.audit.scope.full') }}</h2>
            <p class="text-xs text-slate-500">{{ __('admin.audit.retention_note') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('occurred_at') }}">
                                {{ __('admin.audit.fields.timestamp') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('occurred_at') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('actor_name') }}">
                                {{ __('admin.audit.fields.actor') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('actor_name') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('action_type') }}">
                                {{ __('admin.audit.fields.action') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('action_type') }}</span>
                            </a>
                        </th>
                        <th>
                            <a class="inline-flex items-center gap-1 hover:text-secondary" href="{{ $sortLink('entity_type') }}">
                                {{ __('admin.audit.fields.entity_type') }}
                                <span class="material-symbols-outlined text-[16px]">{{ $sortIcon('entity_type') }}</span>
                            </a>
                        </th>
                        <th>{{ __('admin.audit.fields.ip_address') }}</th>
                        <th>{{ __('admin.audit.fields.reason') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        @php
                            $actorRole = (string) $log->actor_role;
                            $actorRoleLabel = \Illuminate\Support\Facades\Lang::has('roles.names.'.$actorRole)
                                ? __('roles.names.'.$actorRole)
                                : $actorRole;
                            $isNonDemoLoginAttempt = in_array($log->action_type, ['login.fail', 'login.throttled'], true)
                                && ! in_array(mb_strtolower((string) $log->entity_id), $demoLoginIdentifiers, true)
                                && ! in_array(mb_strtolower((string) $log->actor_name), $demoLoginIdentifiers, true);
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                                {{ $log->occurred_at?->utc()->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                            <td>
                                <span class="block min-w-40 font-semibold text-primary">{{ $log->actor_name ?: '—' }}</span>
                                <span class="mt-1 block text-xs text-slate-400">
                                    @if ($log->actor_id)#{{ $log->actor_id }} · @endif{{ $actorRoleLabel }}
                                </span>
                                @if ($isNonDemoLoginAttempt)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                        <span class="material-symbols-outlined text-[13px]">person_alert</span>
                                        {{ __('admin.audit.non_demo_attempt') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <x-admin.status-badge :status="$actionTone($log->action_type)" :label="$actionLabel($log->action_type)" />
                            </td>
                            <td>
                                <span class="block min-w-36 text-sm text-slate-700">{{ $entityLabel($log->entity_type) }}</span>
                                <span class="mt-1 block font-mono text-xs text-slate-400">#{{ $log->entity_id }}</span>
                            </td>
                            <td class="font-mono text-xs text-slate-600">{{ $log->ip_address ?: '—' }}</td>
                            <td class="max-w-64">
                                <span class="line-clamp-2 text-xs leading-5 text-slate-500">
                                    {{ $log->reason ?: __('admin.audit.reason_missing') }}
                                </span>
                            </td>
                            <td>
                                <div class="flex justify-end">
                                    <a
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                        href="{{ route('admin.logs.show', $log) }}"
                                        title="{{ __('common.actions.view_details') }}"
                                        aria-label="{{ __('common.actions.view_details') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">difference</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">manage_search</span>
                                <span class="text-sm text-slate-500">{{ __('admin.audit.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$logs" />
    </section>
@endsection
