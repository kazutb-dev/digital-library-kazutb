@extends('layouts.librarian')

@section('title', __('librarian.overview.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $staffUser = auth()->user();
        $canonicalRole = (string) ($staffUser?->effectiveRole() ?? '');
        $canSee = static fn (array $permissions): bool => $permissions === []
            || ($staffUser?->canAny($permissions) ?? false);

        $circulationPermissions = ['circulation.issue', 'circulation.return'];
        $reservationPermissions = ['reservation.confirm'];
        $repositoryPermissions = ['repository.upload', 'repository.approve', 'repository.publish'];
        $cleanupPermissions = ['data_cleanup.access'];
        $catalogEditPermissions = ['catalog.edit_record'];

        // Every card points at the section that owns the figure, and is hidden
        // outright when the account lacks the permission guarding that section.
        $metricCards = [
            [
                'key' => 'active_loans',
                'icon' => 'sync_alt',
                'href' => route('librarian.circulation'),
                'permissions' => $circulationPermissions,
            ],
            [
                'key' => 'overdue',
                'icon' => 'running_with_errors',
                'href' => route('librarian.circulation'),
                'permissions' => $circulationPermissions,
                'alert' => true,
            ],
            [
                'key' => 'pending_pulls',
                'icon' => 'bookmark_manager',
                'href' => route('librarian.reservations.index'),
                'permissions' => $reservationPermissions,
            ],
            [
                'key' => 'ready_pickups',
                'icon' => 'inventory',
                'href' => route('librarian.reservations.index'),
                'permissions' => $reservationPermissions,
            ],
            [
                'key' => 'draft_records',
                'icon' => 'edit_note',
                'href' => route('librarian.data-cleanup'),
                'permissions' => $cleanupPermissions,
            ],
            [
                'key' => 'repository_pending',
                'icon' => 'school',
                'href' => route('librarian.repository'),
                'permissions' => $repositoryPermissions,
            ],
            [
                'key' => 'pending_fines',
                'icon' => 'payments',
                'href' => route('librarian.fines.index'),
                'permissions' => ['fines.view'],
            ],
            [
                'key' => 'unresolved_messages',
                'icon' => 'mail',
                'href' => route('librarian.messages.index'),
                'permissions' => ['messages.view_all', 'messages.view_assigned'],
            ],
            [
                'key' => 'visits_today',
                'icon' => 'sensor_door',
                'href' => route('librarian.reports.index', ['report' => 'users', 'preset' => 'day']),
                'permissions' => ['reports.view_ops'],
            ],
            [
                'key' => 'problem_copies',
                'icon' => 'build_circle',
                'href' => route('librarian.copies.index'),
                'permissions' => ['copies.edit'],
            ],
        ];

        $visibleMetricCards = array_values(array_filter(
            $metricCards,
            static fn (array $card): bool => $canSee($card['permissions']) && (int) ($metrics[$card['key']] ?? 0) > 0,
        ));
    @endphp

    <div class="mx-auto w-full max-w-7xl">
        <x-admin.page-header
            :eyebrow="__('librarian.overview.eyebrow')"
            :title="__('librarian.overview.title')"
            :subtitle="__('librarian.overview.subtitle')"
        >
            @can('circulation.issue')
                <a class="admin-btn admin-btn-primary" href="{{ route('librarian.circulation.issue') }}">
                    <span class="material-symbols-outlined text-[19px]">outbox</span>
                    {{ __('librarian.circulation.issue_title') }}
                </a>
            @endcan
            @can('circulation.return')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation.return') }}">
                    <span class="material-symbols-outlined text-[19px]">move_to_inbox</span>
                    {{ __('librarian.circulation.return_title') }}
                </a>
            @endcan
        </x-admin.page-header>

        @if ($canSee([...$circulationPermissions, 'reports.view_full']))
        <section class="admin-card mb-6 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[22px] text-secondary">today</span>
                <p class="text-sm leading-6 text-on-surface-variant">
                    {{ __('librarian.overview.today_summary', [
                        'issued' => number_format($metrics['issued_today'], 0, ',', ' '),
                        'returned' => number_format($metrics['returned_today'], 0, ',', ' '),
                    ]) }}
                </p>
            </div>
            <div class="flex shrink-0 gap-8 sm:gap-10">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-outline">
                        {{ __('librarian.overview.metrics.issued_today') }}
                    </div>
                    <div class="mt-1 font-headline text-3xl leading-none text-primary-container">
                        {{ number_format($metrics['issued_today'], 0, ',', ' ') }}
                    </div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-outline">
                        {{ __('librarian.overview.metrics.returned_today') }}
                    </div>
                    <div class="mt-1 font-headline text-3xl leading-none text-primary-container">
                        {{ number_format($metrics['returned_today'], 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if ($visibleMetricCards !== [])
            <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('librarian.overview.eyebrow') }}">
                @foreach ($visibleMetricCards as $card)
                    @php
                        $value = (int) ($metrics[$card['key']] ?? 0);
                        $isAlert = ($card['alert'] ?? false) && $value > 0;
                    @endphp
                    <a
                        href="{{ $card['href'] }}"
                        class="group flex flex-col justify-between rounded-xl border p-5 transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $isAlert ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span class="material-symbols-outlined text-[22px] {{ $isAlert ? 'text-error' : 'text-secondary' }}">{{ $card['icon'] }}</span>
                            <span class="material-symbols-outlined text-[18px] text-slate-300 transition-colors group-hover:text-secondary">arrow_forward</span>
                        </div>
                        <div class="mt-5">
                            <div class="font-headline text-4xl leading-none {{ $isAlert ? 'text-error' : 'text-primary-container' }}">
                                {{ number_format($value, 0, ',', ' ') }}
                            </div>
                            <div class="mt-2 text-sm font-semibold {{ $isAlert ? 'text-on-error-container' : 'text-primary-container' }}">
                                {{ __('librarian.overview.metrics.'.$card['key']) }}
                            </div>
                            <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                                {{ __('librarian.overview.metrics.'.$card['key'].'_hint') }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </section>
        @endif

        @if (is_array($operationalAnalytics ?? null))
            @php
                $operationalRole = $operationalAnalytics['role'];
                $operationalIcons = match($operationalRole) {
                    'acquisitions' => ['received_today' => 'move_to_inbox', 'received_month' => 'library_add', 'sources_month' => 'account_tree', 'processing_copies' => 'pending_actions', 'incomplete_records' => 'edit_note'],
                    'cataloguer' => ['incomplete_records' => 'edit_note', 'without_udc' => 'category', 'manual_review' => 'rate_review', 'duplicate_groups' => 'difference', 'data_quality_open' => 'fact_check'],
                    'bibliographer' => ['assigned_messages' => 'support_agent', 'bibliographic_requests' => 'manage_search', 'repository_published' => 'school', 'external_resources' => 'language', 'open_tasks' => 'task_alt'],
                    default => ['reservation_queue' => 'bookmark_manager', 'open_incidents' => 'report_problem', 'sla_messages' => 'timer_off', 'quality_issues' => 'fact_check', 'overdue_tasks' => 'event_busy'],
                };
                $operationalTarget = match($operationalRole) {
                    'acquisitions' => route('librarian.workspace.orders'),
                    'cataloguer' => route('librarian.data-cleanup'),
                    'bibliographer' => route('librarian.messages.index'),
                    default => route('librarian.workspace.tasks'),
                };
            @endphp
            <section class="admin-card mb-8" data-section="{{ $operationalRole }}-operational-dashboard">
                <header class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-secondary">{{ __('librarian.overview.roles.'.$operationalRole.'.eyebrow') }}</p>
                        <h2 class="mt-1 font-headline text-3xl text-primary">{{ __('librarian.overview.roles.'.$operationalRole.'.title') }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{{ __('librarian.overview.roles.'.$operationalRole.'.subtitle') }}</p>
                    </div>
                    <a class="admin-btn admin-btn-secondary shrink-0" href="{{ $operationalTarget }}">
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>{{ __('common.actions.open') }}
                    </a>
                </header>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ($operationalIcons as $key => $icon)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <span class="material-symbols-outlined text-[20px] text-secondary" aria-hidden="true">{{ $icon }}</span>
                            <strong class="mt-3 block font-headline text-3xl text-primary">{{ number_format((int) ($operationalAnalytics['cards'][$key] ?? 0), 0, ',', ' ') }}</strong>
                            <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">{{ __('librarian.overview.roles.'.$operationalRole.'.cards.'.$key) }}</span>
                        </div>
                    @endforeach
                </div>
                @if(collect($operationalAnalytics['distribution'])->isNotEmpty())
                <div class="mt-6 grid gap-5 {{ $operationalRole === 'acquisitions' ? 'lg:grid-cols-2' : '' }}">
                    <figure class="rounded-xl border border-slate-200 p-4">
                        <figcaption class="font-headline text-lg text-primary">{{ __('librarian.overview.roles.'.$operationalRole.'.distribution') }}</figcaption>
                        <ol class="mt-4 space-y-2">
                            @forelse ($operationalAnalytics['distribution'] as $point)
                                @php
                                    $operationalLabel = $operationalRole === 'acquisitions'
                                        ? (trans()->has('analytics.sources.'.$point['label']) ? __('analytics.sources.'.$point['label']) : $point['label'])
                                        : (trans()->has('librarian.catalog.resource_types.'.$point['label']) ? __('librarian.catalog.resource_types.'.$point['label']) : $point['label']);
                                @endphp
                                <li class="flex items-center justify-between gap-4 border-b border-slate-100 pb-2 text-sm last:border-0"><span>{{ $operationalLabel }}</span><strong class="text-primary">{{ $point['value'] }}</strong></li>
                            @empty
                                <li class="text-sm text-slate-500">{{ __('analytics.empty') }}</li>
                            @endforelse
                        </ol>
                    </figure>
                    @if ($operationalRole === 'acquisitions')
                        @php
                            $acquisitionTrend = collect($operationalAnalytics['trend']);
                            $acquisitionTrendMax = max(1, (int) $acquisitionTrend->max('value'));
                        @endphp
                        <figure class="rounded-xl border border-slate-200 p-4">
                            <figcaption class="font-headline text-lg text-primary">{{ __('librarian.overview.roles.acquisitions.trend') }}</figcaption>
                            <div class="mt-4 flex min-h-40 items-end gap-1.5 border-b border-l border-slate-300 px-2 pt-2" role="img" aria-label="{{ __('librarian.overview.roles.acquisitions.trend') }}">
                                @foreach ($acquisitionTrend as $point)
                                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end self-stretch" title="{{ $point['label'] }}: {{ $point['value'] }}">
                                        <span class="mb-1 text-[9px] font-bold text-slate-600">{{ $point['value'] }}</span>
                                        <span class="w-full min-w-1 rounded-t bg-secondary/80" style="height: {{ max(2, round(((int) $point['value'] / $acquisitionTrendMax) * 105)) }}px"></span>
                                        <span class="mt-1 max-w-full truncate text-[8px] text-slate-500">{{ $point['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <details class="mt-3 text-xs text-slate-600"><summary class="cursor-pointer font-semibold text-secondary">{{ __('librarian.overview.director.text_alternative') }}</summary><ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">@foreach ($acquisitionTrend as $point)<li>{{ $point['label'] }} — {{ $point['value'] }}</li>@endforeach</ul></details>
                        </figure>
                    @endif
                </div>
                @endif
            </section>
        @endif

        @if (is_array($executiveAnalytics ?? null))
            @php
                $executiveCards = [
                    'fund_total' => 'library_books',
                    'acquisitions_month' => 'library_add',
                    'issued_month' => 'calendar_month',
                    'visits_month' => 'sensor_door',
                    'overdue' => 'running_with_errors',
                    'active_readers_month' => 'groups',
                    'data_quality_open' => 'fact_check',
                    'digital_views_month' => 'visibility',
                ];
                $executiveDrilldowns = [
                    'fund_total' => 'fund-usage',
                    'acquisitions_month' => 'acquisitions',
                    'issued_month' => 'loans',
                    'visits_month' => 'visits',
                    'overdue' => 'overdue',
                    'active_readers_month' => 'users',
                    'data_quality_open' => 'data-quality',
                    'digital_views_month' => 'electronic-resources',
                ];
                $executiveReportQuery = [
                    'from' => $executiveAnalytics['period']['from'] ?? null,
                    'to' => $executiveAnalytics['period']['to'] ?? null,
                ];
                $alertReports = [
                    'overdue' => 'overdue', 'external_expired' => 'external-resources',
                    'external_expiring' => 'external-resources', 'data_quality_open' => 'data-quality',
                    'open_messages' => 'messages', 'problem_copies' => 'lost-damaged',
                    'message_sla_overdue' => 'messages',
                ];
                $trendKeys = ['issues', 'visits', 'active_readers', 'digital', 'acquisitions', 'repository', 'external'];
                $distributionKeys = ['fund_types', 'udc', 'message_sla'];
            @endphp

            <section class="admin-card mb-8" data-section="director-executive-dashboard">
                <header class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-secondary">{{ __('librarian.overview.director.eyebrow') }}</p>
                        <h2 class="mt-1 font-headline text-3xl text-primary">{{ __('librarian.overview.director.title') }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{{ __('librarian.overview.director.subtitle') }}</p>
                    </div>
                    <a class="admin-btn admin-btn-secondary shrink-0" href="{{ route('librarian.reports.index') }}">
                        <span class="material-symbols-outlined text-[18px]">monitoring</span>
                        {{ __('librarian.overview.director.open_reports') }}
                    </a>
                </header>

                <form class="mt-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_auto_auto_auto]" method="GET">
                    <label><span class="admin-label">{{ __('librarian.overview.director.period') }}</span><select class="admin-input" name="period" onchange="this.form.submit()">@foreach(['today','week','month','quarter','year','custom'] as $period)<option value="{{ $period }}" @selected(($executiveAnalytics['period']['key'] ?? 'month') === $period)>{{ __('librarian.overview.director.periods.'.$period) }}</option>@endforeach</select></label>
                    <label><span class="admin-label">{{ __('librarian.overview.director.from') }}</span><input class="admin-input" type="date" name="from" value="{{ request('from', $executiveAnalytics['period']['from'] ?? '') }}"></label>
                    <label><span class="admin-label">{{ __('librarian.overview.director.to') }}</span><input class="admin-input" type="date" name="to" value="{{ request('to', $executiveAnalytics['period']['to'] ?? '') }}"></label>
                    <label class="flex items-end gap-2 pb-3 text-sm font-semibold"><input type="checkbox" name="compare" value="1" @checked($executiveAnalytics['period']['compare'] ?? false) onchange="this.form.submit()">{{ __('librarian.overview.director.compare') }}</label>
                </form>

                <div class="mt-3 flex flex-wrap gap-2">
                    @can('reports.export')
                        @foreach(['pdf','xlsx','docx','csv'] as $format)
                            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.executive.export', ['format' => $format] + request()->only(['period','from','to','compare'])) }}">{{ strtoupper($format) }}</a>
                        @endforeach
                    @endcan
                    <button class="admin-btn admin-btn-secondary" type="button" onclick="window.print()">{{ __('common.actions.print') }}</button>
                </div>

                @if (($executiveAnalytics['alerts'] ?? []) !== [])
                    <div class="mt-5" role="status" aria-label="{{ __('librarian.overview.director.alerts') }}">
                        <h3 class="text-sm font-bold text-primary">{{ __('librarian.overview.director.alerts') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($executiveAnalytics['alerts'] as $alert)
                                <div class="rounded-xl border px-4 py-3 {{ $alert['severity'] === 'high' ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-950' }}">
                                    <strong class="block text-2xl">{{ number_format((int) $alert['value'], 0, ',', ' ') }}</strong>
                                    <span class="text-xs font-semibold">{{ __('librarian.overview.director.cards.'.$alert['key']) }}</span>
                                    <p class="mt-1 text-[11px]">{{ __('librarian.overview.director.threshold') }}: ≥ {{ $alert['threshold'] }}</p>
                                    <p class="mt-1 text-xs">{{ __('librarian.overview.director.recommendations.'.$alert['recommendation']) }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a class="text-xs font-bold underline" href="{{ route('librarian.reports.index', ['report' => $alertReports[$alert['key']]] + $executiveReportQuery) }}">{{ __('common.actions.open') }}</a>
                                        @if($alert['acknowledged'])
                                            <span class="rounded-full bg-white/70 px-2 py-1 text-[11px] font-bold">{{ __('librarian.overview.director.acknowledged') }}</span>
                                        @else
                                            <form method="POST" action="{{ route('librarian.executive.alerts.acknowledge') }}">@csrf<input type="hidden" name="alert_key" value="{{ $alert['key'] }}"><input type="hidden" name="scope_hash" value="{{ $alert['scope_hash'] }}"><button class="text-xs font-bold underline" type="submit">{{ __('librarian.overview.director.acknowledge') }}</button></form>
                                        @endif
                                    </div>
                                    @can('tasks.assign')
                                        <details class="mt-2 text-xs"><summary class="cursor-pointer font-bold">{{ __('librarian.overview.director.assign') }}</summary><form class="mt-2 grid gap-2" method="POST" action="{{ route('librarian.executive.alerts.assign') }}">@csrf<input type="hidden" name="alert_key" value="{{ $alert['key'] }}"><select class="admin-input" name="assigned_to" required aria-label="{{ __('workspace.fields.assigned_to') }}">@foreach($executiveStaff as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select><input class="admin-input" type="date" name="due_at" value="{{ now()->addDays(7)->toDateString() }}" required aria-label="{{ __('workspace.fields.due_at') }}"><select class="admin-input" name="priority" aria-label="{{ __('workspace.fields.priority') }}"><option value="high">{{ __('workspace.priorities.high') }}</option><option value="critical">{{ __('workspace.priorities.critical') }}</option><option value="normal">{{ __('workspace.priorities.normal') }}</option></select><textarea class="admin-input" name="comment" rows="2" maxlength="2000" placeholder="{{ __('workspace.fields.comment') }}" aria-label="{{ __('workspace.fields.comment') }}"></textarea><button class="admin-btn admin-btn-secondary" type="submit">{{ __('librarian.overview.director.create_task') }}</button></form></details>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($executiveCards as $key => $icon)
                        @php
                            $cardValue = $executiveAnalytics['cards'][$key] ?? 0;
                        @endphp
                        <a href="{{ route('librarian.reports.index', ['report' => $executiveDrilldowns[$key]] + $executiveReportQuery) }}" class="group rounded-xl border border-slate-200 bg-slate-50/60 p-4 transition hover:-translate-y-0.5 hover:border-secondary hover:shadow-sm">
                            <span class="material-symbols-outlined text-[20px] text-secondary" aria-hidden="true">{{ $icon }}</span>
                            <strong class="mt-3 block font-headline text-3xl text-primary">
                                {{ is_float($cardValue) ? number_format($cardValue, 2, ',', ' ') : number_format((int) $cardValue, 0, ',', ' ') }}
                            </strong>
                            <span class="mt-1 block text-xs font-semibold leading-5 text-slate-600">{{ __('librarian.overview.director.cards.'.$key) }}</span>
                            @if(isset($executiveAnalytics['comparison'][$key]))
                                @php
                                    $change = $executiveAnalytics['comparison'][$key];
                                @endphp
                                <span class="mt-2 block text-xs font-bold {{ $change['delta'] > 0 ? 'text-emerald-700' : ($change['delta'] < 0 ? 'text-red-700' : 'text-slate-500') }}">{{ $change['delta'] > 0 ? '+' : '' }}{{ $change['delta'] }} · {{ $change['percent'] === null ? '—' : $change['percent'].'%' }}</span>
                            @endif
                            <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-secondary">{{ __('librarian.overview.director.drill_down') }} <span class="material-symbols-outlined text-[14px]" aria-hidden="true">arrow_forward</span></span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-7 grid gap-5 lg:grid-cols-2">
                    @foreach ($trendKeys as $trendKey)
                        @php
                            $trend = collect($executiveAnalytics['trends'][$trendKey] ?? [])->take(-14)->values();
                            $trendMax = max(1, (int) $trend->max('value'));
                        @endphp
                        <figure class="rounded-xl border border-slate-200 p-4" aria-labelledby="director-chart-{{ $trendKey }}">
                            <figcaption id="director-chart-{{ $trendKey }}" class="font-headline text-lg text-primary">{{ __('librarian.overview.director.charts.'.$trendKey) }}</figcaption>
                            <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.chart_note') }}</p>
                            <div class="mt-4 flex min-h-40 items-end gap-1.5 border-b border-l border-slate-300 px-2 pt-2" role="img" aria-label="{{ __('librarian.overview.director.charts.'.$trendKey) }}">
                                @forelse ($trend as $point)
                                    <div class="flex min-w-0 flex-1 flex-col items-center justify-end self-stretch" title="{{ $point['label'] }}: {{ $point['value'] }}">
                                        <span class="mb-1 text-[9px] font-bold text-slate-600">{{ $point['value'] }}</span>
                                        <span class="w-full min-w-1 rounded-t bg-secondary/80" style="height: {{ max(2, round(((int) $point['value'] / $trendMax) * 105)) }}px"></span>
                                        <span class="mt-1 max-w-full truncate text-[8px] text-slate-500">{{ $point['label'] }}</span>
                                    </div>
                                @empty
                                    <p class="self-center p-6 text-sm text-slate-500">{{ __('analytics.empty') }}</p>
                                @endforelse
                            </div>
                            <details class="mt-3 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold text-secondary">{{ __('librarian.overview.director.text_alternative') }}</summary>
                                <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                                    @foreach ($trend as $point)<li>{{ $point['label'] }} — {{ $point['value'] }}</li>@endforeach
                                </ul>
                            </details>
                        </figure>
                    @endforeach
                </div>

                <div class="mt-7 grid gap-5 lg:grid-cols-3">
                    @foreach ($distributionKeys as $distributionKey)
                        @php
                            $distribution = collect($executiveAnalytics['distributions'][$distributionKey] ?? [])->map(function (array $point) use ($distributionKey): array {
                                $point['display_label'] = match ($distributionKey) {
                                    'fund_types' => trans()->has('librarian.catalog.resource_types.'.$point['label'])
                                        ? __('librarian.catalog.resource_types.'.$point['label'])
                                        : __('analytics.statuses.unknown'),
                                    'message_sla' => __('librarian.overview.director.sla.'.$point['label']),
                                    default => (string) $point['label'],
                                };

                                return $point;
                            });
                            $distributionMax = max(1, (int) $distribution->max('value'));
                        @endphp
                        <figure class="rounded-xl border border-slate-200 p-4" aria-labelledby="director-distribution-{{ $distributionKey }}">
                            <figcaption id="director-distribution-{{ $distributionKey }}" class="font-headline text-lg text-primary">{{ __('librarian.overview.director.charts.'.$distributionKey) }}</figcaption>
                            <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.distribution_note') }}</p>
                            <ol class="mt-4 space-y-3" role="img" aria-label="{{ __('librarian.overview.director.charts.'.$distributionKey) }}">
                                @forelse ($distribution as $point)
                                    <li>
                                        <div class="mb-1 flex items-center justify-between gap-3 text-xs">
                                            <span class="truncate" title="{{ $point['display_label'] }}">{{ $point['display_label'] }}</span>
                                            <strong class="shrink-0 text-primary">{{ number_format((int) $point['value'], 0, ',', ' ') }}</strong>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                            <span class="block h-full rounded-full bg-secondary/80" style="width: {{ max(1, round(((int) $point['value'] / $distributionMax) * 100)) }}%"></span>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-sm text-slate-500">{{ __('analytics.empty') }}</li>
                                @endforelse
                            </ol>
                            <details class="mt-3 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold text-secondary">{{ __('librarian.overview.director.text_alternative') }}</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($distribution as $point)<li>{{ $point['display_label'] }} — {{ $point['value'] }}</li>@endforeach
                                </ul>
                            </details>
                        </figure>
                    @endforeach
                </div>

                <section class="mt-6 rounded-xl border border-slate-200 p-4">
                    <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.top_resources') }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.top_resources_note') }}</p>
                    <ol class="mt-3 space-y-2">
                        @forelse ($executiveAnalytics['top_resources'] ?? [] as $resource)
                            <li class="flex items-start justify-between gap-4 border-b border-slate-100 pb-2 text-sm last:border-0">
                                <span>{{ $resource['label'] }}</span><strong class="shrink-0 text-primary">{{ $resource['value'] }}</strong>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">{{ __('analytics.empty') }}</li>
                        @endforelse
                    </ol>
                </section>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <section class="rounded-xl border border-slate-200 p-4">
                        <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.bottlenecks') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.bottlenecks_note') }}</p>
                        <ul class="mt-3 space-y-2">
                            @forelse($executiveAnalytics['bottlenecks'] ?? [] as $bottleneck)
                                <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm"><span>{{ __('librarian.overview.director.bottleneck_labels.'.$bottleneck['key']) }}</span><strong>{{ $bottleneck['value'] }}</strong></li>
                            @empty<li class="text-sm text-slate-500">{{ __('analytics.empty') }}</li>@endforelse
                        </ul>
                    </section>
                    <section class="rounded-xl border border-slate-200 p-4">
                        <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.staff_workload') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.staff_workload_note') }}</p>
                        <div class="mt-3 overflow-x-auto"><table class="admin-table min-w-[460px]"><thead><tr><th>{{ __('librarian.overview.director.employee') }}</th><th>{{ __('librarian.overview.director.assigned') }}</th><th>{{ __('librarian.overview.director.overdue_tasks') }}</th><th>{{ __('librarian.overview.director.completed') }}</th></tr></thead><tbody>@forelse($executiveAnalytics['staff_workload'] ?? [] as $staff)<tr><td>{{ $staff['name'] }}</td><td>{{ $staff['assigned'] }}</td><td>{{ $staff['overdue'] }}</td><td>{{ $staff['completed'] }}</td></tr>@empty<tr><td colspan="4">{{ __('analytics.empty') }}</td></tr>@endforelse</tbody></table></div>
                    </section>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <section class="rounded-xl border border-slate-200 p-4">
                        <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.unused_resources') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('librarian.overview.director.unused_method') }}</p>
                        <ol class="mt-3 space-y-2">@forelse($executiveAnalytics['unused_resources'] ?? [] as $resource)<li class="flex items-start justify-between gap-3 text-sm"><span>{{ $resource['label'] }}</span><span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs">{{ __('librarian.overview.director.unused_periods.'.$resource['period']) }}</span></li>@empty<li class="text-sm text-slate-500">{{ __('analytics.empty') }}</li>@endforelse</ol>
                    </section>
                    <section class="rounded-xl border border-slate-200 p-4">
                        <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.financial_control') }}</h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">{{ __('librarian.overview.director.cards.fines_charged_period') }}</dt><dd class="font-bold">{{ number_format((float)($executiveAnalytics['cards']['fines_charged_period'] ?? 0), 2, ',', ' ') }}</dd></div><div><dt class="text-slate-500">{{ __('librarian.overview.director.cards.acquisition_value_period') }}</dt><dd class="font-bold">{{ number_format((float)($executiveAnalytics['cards']['acquisition_value_period'] ?? 0), 2, ',', ' ') }}</dd></div></dl>
                        @if(!($executiveAnalytics['budget']['available'] ?? false))<p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ __('librarian.overview.director.budget_unavailable') }}</p>@endif
                    </section>
                </div>

                @php
                    $operationalGroups = [
                        'circulation' => ['returned_month','overdue_readers','average_overdue_days','oldest_overdue_days','outstanding_fines'],
                        'reservations' => ['reservations_queued','reservations_ready','reservations_expired_period','reservation_average_wait_hours'],
                        'digital_repository' => ['repository_published','repository_added_period','repository_usage_period','repository_restricted','digital_access_failures'],
                        'licences_access' => ['external_active','external_expiring','external_expired','external_unavailable','active_staff_accounts','privileged_role_assignments'],
                    ];
                @endphp
                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    @foreach($operationalGroups as $group => $metricKeys)
                        <section class="rounded-xl border border-slate-200 p-4">
                            <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.groups.'.$group) }}</h3>
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach($metricKeys as $metricKey)
                                    <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ __('librarian.overview.director.cards.'.$metricKey) }}</dt><dd class="mt-1 text-xl font-bold text-primary">{{ is_float($executiveAnalytics['cards'][$metricKey] ?? 0) ? number_format((float)$executiveAnalytics['cards'][$metricKey], 1, ',', ' ') : number_format((int)($executiveAnalytics['cards'][$metricKey] ?? 0), 0, ',', ' ') }}</dd></div>
                                @endforeach
                            </dl>
                        </section>
                    @endforeach
                </div>

                @php
                    $quickReports = ['fund-usage','acquisitions','users','electronic-resources','overdue','reservations','staff','fines','data-quality','external-resources','repository','news-events'];
                @endphp
                <section class="mt-6 rounded-xl border border-slate-200 p-4">
                    <h3 class="font-headline text-lg text-primary">{{ __('librarian.overview.director.quick_reports') }}</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach($quickReports as $reportCode)
                            <a class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-primary hover:border-secondary" href="{{ route('librarian.reports.index', ['report' => $reportCode] + $executiveReportQuery) }}"><span>{{ __('analytics.reports.'.$reportCode.'.title') }}</span><span class="material-symbols-outlined text-[16px] text-secondary" aria-hidden="true">arrow_forward</span></a>
                        @endforeach
                    </div>
                </section>
            </section>
        @endif

        @can('data_quality.view')
            <section class="admin-card mb-8">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.title') }}</h2><p class="text-sm text-slate-500">{{ __('data_quality.subtitle') }}</p></div>
                    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index') }}">{{ __('common.actions.open') }}</a>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach(['records_attention','copies_attention','high_priority','overdue'] as $metric)
                        <div class="rounded-xl border p-4"><div class="text-xs uppercase tracking-wide text-slate-500">{{ __('librarian.overview.director.quality.'.$metric) }}</div><strong class="mt-2 block font-headline text-3xl text-primary">{{ $qualityMetrics[$metric] }}</strong></div>
                    @endforeach
                </div>
            </section>
        @endcan

        <div class="grid gap-6 xl:grid-cols-2">
            @if ($canSee($circulationPermissions))
                <section class="admin-card overflow-hidden p-0">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                            <span class="material-symbols-outlined text-[20px] text-error">running_with_errors</span>
                            {{ __('librarian.overview.overdue_queue') }}
                        </h2>
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.circulation') }}">
                            {{ __('common.actions.open') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[620px]">
                            <thead>
                                <tr>
                                    <th>{{ __('librarian.reservations.reader') }}</th>
                                    <th>{{ __('librarian.reservations.record') }}</th>
                                    <th>{{ __('librarian.circulation.due_date') }}</th>
                                    <th>{{ __('librarian.overview.metrics.overdue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($overdueLoans as $loan)
                                    <tr>
                                        <td>
                                            <strong class="block text-sm text-primary-container">{{ $loan->reader?->name ?? '—' }}</strong>
                                            <span class="block text-xs text-slate-500">{{ $loan->reader?->email ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="block max-w-xs truncate text-sm text-on-surface" title="{{ $loan->copy?->bibliographicRecord?->title }}">
                                                {{ $loan->copy?->bibliographicRecord?->title ?? '—' }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ __('librarian.copies.fields.inventory_number') }}: {{ $loan->copy?->inventory_number ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-on-surface-variant">
                                            {{ $loan->due_at?->format('d.m.Y') ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                {{ __('librarian.circulation.overdue_days', ['count' => $loan->overdueDays()]) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-14 text-center">
                                            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">check_circle</span>
                                            <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_overdue') }}</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($canSee($reservationPermissions))
                <section class="admin-card overflow-hidden p-0">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                            <span class="material-symbols-outlined text-[20px] text-secondary">inventory</span>
                            {{ __('librarian.overview.ready_queue') }}
                        </h2>
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.reservations.index') }}">
                            {{ __('common.actions.open') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[560px]">
                            <thead>
                                <tr>
                                    <th>{{ __('librarian.reservations.reader') }}</th>
                                    <th>{{ __('librarian.reservations.record') }}</th>
                                    <th>{{ __('librarian.reservations.expires_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($readyReservations as $reservation)
                                    <tr>
                                        <td>
                                            <strong class="block text-sm text-primary-container">{{ $reservation->reader?->name ?? '—' }}</strong>
                                            <span class="block text-xs text-slate-500">{{ $reservation->reader?->email ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="block max-w-xs truncate text-sm text-on-surface" title="{{ $reservation->bibliographicRecord?->title }}">
                                                {{ $reservation->bibliographicRecord?->title ?? '—' }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ $reservation->bibliographicRecord?->primary_author ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-on-surface-variant">
                                            {{ $reservation->expires_at?->format('d.m.Y H:i') ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-14 text-center">
                                            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inbox</span>
                                            <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_ready') }}</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        @if ($canSee(array_merge($catalogEditPermissions, $cleanupPermissions)))
            <section class="admin-card mt-6 overflow-hidden p-0">
                <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                    <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                        <span class="material-symbols-outlined text-[20px] text-secondary">edit_note</span>
                        {{ __('librarian.overview.draft_queue') }}
                    </h2>
                    @can('data_cleanup.access')
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.data-cleanup') }}">
                            {{ __('librarian.nav.data_cleanup') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    @endcan
                </header>

                @forelse ($draftRecords as $record)
                    @php
                        $missingFields = $record->missingRequiredFields();
                        $missingLabels = array_map(
                            static fn (string $field): string => __('librarian.catalog.fields.'.$field),
                            $missingFields,
                        );
                    @endphp
                    <article class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @can('catalog.edit_record')
                                    <a class="font-headline text-lg leading-tight text-primary-container hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">
                                        {{ $record->title }}
                                    </a>
                                @else
                                    <span class="font-headline text-lg leading-tight text-primary-container">{{ $record->title }}</span>
                                @endcan
                                <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                            </div>
                            <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                                {{ $record->primary_author ?? '—' }} · {{ $record->publication_year ?? '—' }}
                            </p>
                            @if ($missingLabels !== [])
                                <p class="mt-2 text-xs leading-5 text-red-700">
                                    {{ __('librarian.catalog.draft_notice', ['fields' => implode(', ', $missingLabels)]) }}
                                </p>
                            @endif
                        </div>
                        @can('catalog.edit_record')
                            <a class="admin-btn admin-btn-secondary shrink-0 self-start" href="{{ route('librarian.catalog.edit', $record) }}">
                                <span class="material-symbols-outlined text-[19px]">edit</span>
                                {{ __('librarian.data_cleanup.fix_record') }}
                            </a>
                        @endcan
                    </article>
                @empty
                    <div class="py-14 text-center">
                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">task_alt</span>
                        <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_drafts') }}</span>
                    </div>
                @endforelse
            </section>
        @endif

        <p class="mt-6 flex items-start gap-2 text-xs leading-5 text-outline">
            <span class="material-symbols-outlined text-[16px]">info</span>
            {{ __('librarian.overview.data_source_note') }}
        </p>
    </div>
@endsection
