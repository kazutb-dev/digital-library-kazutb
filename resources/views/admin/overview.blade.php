@extends('layouts.admin')

@section('title', __('admin.dashboard.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.dashboard.eyebrow')"
        :title="__('admin.dashboard.title')"
        :subtitle="__('admin.dashboard.subtitle')"
    >
        @if ($dashboardAccess['reports'])
            <a href="{{ route('admin.reports.index') }}" class="admin-btn admin-btn-secondary">
                <span class="material-symbols-outlined text-[19px]">analytics</span>
                {{ __('admin.nav.reports') }}
            </a>
        @elseif ($dashboardAccess['reports_export'])
            <a href="{{ route('admin.reports.export', ['type' => 'user-activity', 'format' => 'csv']) }}" class="admin-btn admin-btn-secondary">
                <span class="material-symbols-outlined text-[19px]">download</span>
                {{ __('admin.dashboard.export') }}
            </a>
        @endif
    </x-admin.page-header>

    @if ($metrics !== [])
        <div class="mb-10 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($metrics as $metric)
                <article @class([
                    'admin-card relative flex min-h-52 flex-col justify-between overflow-hidden',
                    '!bg-gradient-to-br !from-primary !to-primary-container text-white' => $metric['tone'] === 'primary',
                ])>
                    <div class="flex items-start justify-between">
                        <span @class([
                            'flex h-12 w-12 items-center justify-center rounded-xl',
                            'bg-white/10 text-white' => $metric['tone'] === 'primary',
                            'bg-surface-high text-primary' => $metric['tone'] !== 'primary',
                        ])>
                            <span class="material-symbols-outlined">{{ $metric['icon'] }}</span>
                        </span>
                        @unless ($metric['available'])
                            <x-admin.status-badge status="not_configured" :label="__('common.not_configured')" />
                        @endunless
                    </div>
                    <div class="mt-7">
                        <p @class(['text-xs font-bold uppercase tracking-[.08em]', 'text-white/70' => $metric['tone'] === 'primary', 'text-slate-500' => $metric['tone'] !== 'primary'])>{{ $metric['label'] }}</p>
                        <p class="mt-1 font-headline text-5xl leading-none">{{ number_format((int) $metric['value'], 0, ',', ' ') }}</p>
                        <p @class(['mt-3 text-xs leading-5', 'text-white/70' => $metric['tone'] === 'primary', 'text-slate-500' => $metric['tone'] !== 'primary'])>{{ $metric['note'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($dashboardAccess['settings'] || $dashboardAccess['logs'] || $dashboardAccess['messages'] || $dashboardAccess['news'])
        <div class="grid grid-cols-1 gap-8 xl:grid-cols-12">
        @if ($dashboardAccess['settings'] || $dashboardAccess['logs'])
        <section @class([
            'xl:col-span-7' => $dashboardAccess['messages'] || $dashboardAccess['news'],
            'xl:col-span-12' => ! $dashboardAccess['messages'] && ! $dashboardAccess['news'],
        ])>
            @if ($dashboardAccess['settings'])
            <div class="mb-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-secondary">monitor_heart</span>
                <h2 class="font-headline text-3xl text-primary">{{ __('admin.dashboard.platform_health') }}</h2>
            </div>
            <div class="admin-card space-y-3">
                @foreach ($healthItems as $item)
                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-100 p-4 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-surface-low text-primary">
                                <span class="material-symbols-outlined text-[21px]">{{ $item['icon'] }}</span>
                            </span>
                            <span>
                                <strong class="block text-sm text-primary">{{ $item['title'] }}</strong>
                                <small class="text-slate-500">{{ $item['subtitle'] }}</small>
                            </span>
                        </div>
                        <span @class(['text-sm font-semibold', 'text-secondary' => $item['ok'], 'text-red-700' => ! $item['ok']])>{{ $item['status'] }}</span>
                    </div>
                @endforeach
            </div>
            @endif

            @if ($dashboardAccess['logs'])
            <div class="mb-4 mt-8 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary">history</span>
                    <h2 class="font-headline text-3xl text-primary">{{ __('admin.audit.title') }}</h2>
                </div>
                <a class="text-sm font-bold text-secondary hover:underline" href="{{ route('admin.logs.index') }}">{{ __('admin.dashboard.view_all') }}</a>
            </div>
            <div class="admin-card divide-y divide-slate-100 !p-2">
                @forelse ($recentLogs as $log)
                    @php
                        $actionLabel = (string) $log->action_type;
                        $fullAction = str($log->action_type)->replace(['.', '-'], '_')->value();
                        $shortAction = str($log->action_type)->afterLast('.')->replace('-', '_')->value();
                        foreach (array_unique([$fullAction, $shortAction]) as $normalizedAction) {
                            if (\Illuminate\Support\Facades\Lang::has('admin.audit.actions.'.$normalizedAction)) {
                                $actionLabel = __('admin.audit.actions.'.$normalizedAction);
                                break;
                            }
                        }
                        $entityKey = 'admin.audit.entities.'.str($log->entity_type)->afterLast('\\')->snake()->value();
                        $entityLabel = \Illuminate\Support\Facades\Lang::has($entityKey)
                            ? __($entityKey)
                            : $log->entity_type;
                    @endphp
                    <a href="{{ route('admin.logs.show', $log) }}" class="flex items-start gap-4 rounded-lg p-4 hover:bg-surface-low">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface-high text-primary">
                            <span class="material-symbols-outlined text-[19px]">fact_check</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center justify-between gap-2">
                                <strong class="text-sm">{{ $actionLabel }}</strong>
                                <time class="text-xs text-slate-500" datetime="{{ $log->occurred_at?->toIso8601String() }}">{{ $log->occurred_at?->utc()->diffForHumans() }}</time>
                            </span>
                            <span class="mt-1 block truncate text-xs text-slate-500">
                                {{ $log->actor_name ?: __('common.time.not_available') }} · {{ $entityLabel }} #{{ $log->entity_id }}
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="p-6 text-center text-sm text-slate-500">{{ __('admin.audit.empty') }}</p>
                @endforelse
            </div>
            @endif
        </section>
        @endif

        @if ($dashboardAccess['messages'] || $dashboardAccess['news'])
        <section @class([
            'xl:col-span-5' => $dashboardAccess['settings'] || $dashboardAccess['logs'],
            'xl:col-span-12' => ! $dashboardAccess['settings'] && ! $dashboardAccess['logs'],
        ])>
            @if ($dashboardAccess['messages'])
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary">pending_actions</span>
                    <h2 class="font-headline text-3xl text-primary">{{ __('admin.dashboard.governance_queue') }}</h2>
                </div>
                <a class="text-sm font-bold text-secondary hover:underline" href="{{ route('admin.messages.index') }}">{{ __('admin.dashboard.view_all') }}</a>
            </div>
            <div class="admin-card divide-y divide-slate-100 !p-2">
                @forelse ($messageQueue as $message)
                    <a href="{{ route('admin.messages.show', $message) }}" class="block rounded-lg p-4 hover:bg-surface-low">
                        <span class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" />
                            <time class="text-xs text-slate-500">{{ $message->created_at?->diffForHumans() }}</time>
                        </span>
                        <strong class="block truncate font-headline text-xl text-primary">{{ $message->subject }}</strong>
                        <span class="mt-1 block truncate text-xs text-slate-500">{{ $message->sender_email }}</span>
                    </a>
                @empty
                    <p class="p-6 text-center text-sm text-slate-500">{{ __('admin.dashboard.queue_empty') }}</p>
                @endforelse
            </div>
            @endif

            @if ($dashboardAccess['news'])
            <div class="admin-card mt-8">
                <div class="mb-5 flex items-center gap-3">
                    <span class="material-symbols-outlined text-secondary">newspaper</span>
                    <h2 class="font-headline text-2xl text-primary">{{ __('admin.nav.news') }}</h2>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['draft', 'scheduled', 'published', 'archived'] as $status)
                        <a href="{{ route('admin.news.index', ['status' => $status]) }}" class="rounded-xl bg-surface-low p-4 hover:bg-surface-high">
                            <span class="block text-xs font-semibold text-slate-500">{{ __('news.statuses.'.$status) }}</span>
                            <strong class="mt-1 block font-headline text-3xl text-primary">{{ (int) ($newsCounts[$status] ?? 0) }}</strong>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <p class="mt-5 flex items-start gap-2 rounded-xl border border-teal-100 bg-teal-50/60 p-4 text-xs leading-5 text-teal-900">
                <span class="material-symbols-outlined text-[18px]">database</span>
                {{ __('admin.dashboard.data_source_note') }}
            </p>
        </section>
        @endif
    </div>
    @elseif ($metrics === [])
        <div class="admin-card flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-surface-high text-primary">
                <span class="material-symbols-outlined">lock_person</span>
            </span>
            <p class="text-sm leading-6 text-slate-600">{{ __('admin.dashboard.permission_scoped') }}</p>
        </div>
    @endif
@endsection
