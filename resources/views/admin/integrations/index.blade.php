@extends('layouts.admin')

@section('title', __('admin.integrations.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('admin.integrations.title')" :subtitle="__('admin.integrations.subtitle')">
        <a href="{{ route('admin.settings.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">settings</span>{{ __('admin.nav.settings') }}
        </a>
    </x-admin.page-header>

    @if (session('integration_check'))
        @php
            $check = session('integration_check');
            $checkName = match ($check['key']) {
                'crm' => __('admin.integrations.ldap'),
                'database' => __('admin.integrations.database'),
                'storage' => __('admin.integrations.storage'),
                default => $check['key'],
            };
        @endphp
        <div @class([
            'mb-6 flex items-start gap-3 rounded-xl border p-4 text-sm',
            'border-emerald-200 bg-emerald-50 text-emerald-900' => $check['ok'],
            'border-red-200 bg-red-50 text-red-900' => ! $check['ok'],
        ])>
            <span class="material-symbols-outlined text-[20px]">{{ $check['ok'] ? 'check_circle' : 'error' }}</span>
            <span>
                <strong>{{ $checkName }}</strong> · {{ $check['ok'] ? __('common.status.healthy') : __('common.status.unavailable') }}
                · {{ $check['duration_ms'] }} ms
                @if($check['detail']) · {{ $check['detail'] }} @endif
            </span>
        </div>
    @endif

    <section class="mb-9">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            @foreach ($integrations as $integration)
                @php
                    $name = match ($integration['key']) {
                        'crm' => __('admin.integrations.ldap'),
                        'database' => __('admin.integrations.database'),
                        'storage' => __('admin.integrations.storage'),
                        default => $integration['name'],
                    };
                @endphp
                <article class="admin-card flex flex-col">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-low text-secondary">
                            <span class="material-symbols-outlined">{{ match ($integration['key']) { 'crm' => 'badge', 'database' => 'database', default => 'hard_drive' } }}</span>
                        </span>
                        <x-admin.status-badge
                            :status="$integration['configured'] ? 'configured' : 'not_configured'"
                            :label="$integration['configured'] ? __('common.configured') : __('common.not_configured')"
                        />
                    </div>
                    <h2 class="font-headline text-2xl text-primary">{{ $name }}</h2>
                    <p class="mt-2 min-h-10 text-sm leading-5 text-slate-500">{{ $integration['mode'] }}</p>
                    <dl class="mt-5 rounded-xl bg-surface-low p-4">
                        <dt class="text-xs text-slate-500">{{ __('admin.integrations.endpoint') }}</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ $integration['endpoint'] ?: __('common.not_configured') }}</dd>
                    </dl>
                    @unless ($integration['configured'])
                        <p class="mt-3 text-xs leading-5 text-amber-700">{{ __('admin.integrations.not_configured_hint') }}</p>
                    @endunless
                    <form method="POST" action="{{ route('admin.integrations.check') }}" class="mt-auto pt-5">
                        @csrf
                        <input type="hidden" name="integration" value="{{ $integration['key'] }}">
                        <button class="admin-btn admin-btn-secondary w-full" type="submit">
                            <span class="material-symbols-outlined text-[18px]">network_check</span>{{ __('common.actions.retry') }}
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>

    @php
        $readinessPending = collect($readinessChecklist)->where('ok', false);
    @endphp
    <section class="admin-card mb-9">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-secondary">{{ __('common.status.read_only') }}</p>
                <h2 class="mt-2 font-headline text-3xl text-primary">{{ __('admin.readiness.title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('admin.readiness.subtitle') }}</p>
            </div>
            @if ($readinessPending->isEmpty())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-800">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('admin.readiness.all_clear') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1.5 text-xs font-bold text-red-800">
                    <span class="material-symbols-outlined text-[16px]">report</span>
                    {{ __('admin.readiness.pending_count', ['count' => $readinessPending->count()]) }}
                </span>
            @endif
        </div>
        <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($readinessChecklist as $item)
                <li @class([
                    'flex items-start gap-3 rounded-xl border p-4',
                    'border-emerald-200 bg-emerald-50/50' => $item['ok'],
                    'border-red-200 bg-red-50/60' => ! $item['ok'] && $item['severity'] === 'blocker',
                    'border-amber-200 bg-amber-50/60' => ! $item['ok'] && $item['severity'] === 'warning',
                ])>
                    <span @class([
                        'material-symbols-outlined mt-0.5 text-[20px]',
                        'text-emerald-600' => $item['ok'],
                        'text-red-600' => ! $item['ok'] && $item['severity'] === 'blocker',
                        'text-amber-600' => ! $item['ok'] && $item['severity'] === 'warning',
                    ])>{{ $item['ok'] ? 'check_circle' : ($item['severity'] === 'blocker' ? 'cancel' : 'warning') }}</span>
                    <span>
                        <strong class="block text-sm text-slate-800">{{ __('admin.readiness.items.'.$item['key']) }}</strong>
                        <span class="mt-0.5 block text-xs leading-4 text-slate-500">
                            {{ $item['ok'] ? __('admin.readiness.status_ok') : __('admin.readiness.items.'.$item['key'].'_hint') }}
                        </span>
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="mt-5 flex items-start gap-2 rounded-xl bg-surface-low p-4 text-xs leading-5 text-slate-600">
            <span class="material-symbols-outlined text-[18px] text-secondary">info</span>
            {{ __('admin.readiness.deployment_note') }}
        </p>
    </section>

    <div class="grid grid-cols-1 gap-7 xl:grid-cols-12">
        <section class="admin-card xl:col-span-7">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-secondary">{{ __('common.status.read_only') }}</p>
                    <h2 class="mt-2 font-headline text-3xl text-primary">{{ __('admin.security.title') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('admin.security.subtitle') }}</p>
                </div>
                <span class="material-symbols-outlined text-3xl text-secondary">shield_lock</span>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ([
                    __('admin.security.demo_login') => $security['demo_login_enabled'] ? __('common.status.enabled') : __('common.status.disabled'),
                    __('admin.security.active_admins') => $security['active_admins'],
                    __('admin.security.failed_logins_24h') => $security['failed_logins_24h'],
                    __('admin.security.debug_mode') => $security['debug_enabled'] ? __('common.status.enabled') : __('common.status.disabled'),
                    __('admin.security.https') => $security['https'] ? __('common.status.enabled') : __('common.status.disabled'),
                    __('settings.security.environment_value') => $security['environment'],
                ] as $label => $securityValue)
                    <div class="rounded-xl border border-slate-100 p-4">
                        <small class="block text-slate-500">{{ $label }}</small>
                        <strong class="mt-1 block text-sm">{{ $securityValue }}</strong>
                    </div>
                @endforeach
            </div>
            <p class="mt-5 flex items-start gap-2 rounded-xl bg-surface-low p-4 text-xs leading-5 text-slate-600">
                <span class="material-symbols-outlined text-[18px] text-secondary">info</span>
                {{ __('admin.security.read_only_notice') }}
            </p>
        </section>

        <section class="admin-card !bg-primary text-white xl:col-span-5">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-200">{{ __('admin.nav.backups') }}</p>
                    <h2 class="mt-2 font-headline text-3xl">{{ __('admin.backups.title') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-white/60">{{ __('admin.backups.subtitle') }}</p>
                </div>
                <span class="material-symbols-outlined text-3xl text-cyan-200">backup</span>
            </div>
            <dl class="space-y-3">
                <div class="rounded-xl bg-white/[.08] p-4">
                    <dt class="text-xs text-white/55">{{ __('admin.backups.database_backup') }}</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $backup['provider'] ?: __('admin.backups.not_reported') }}</dd>
                </div>
                <div class="rounded-xl bg-white/[.08] p-4">
                    <dt class="text-xs text-white/55">{{ __('admin.backups.retention') }}</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $backup['schedule'] ?: __('admin.backups.not_reported') }}</dd>
                </div>
                <div class="rounded-xl bg-white/[.08] p-4">
                    <dt class="text-xs text-white/55">{{ __('admin.backups.last_successful_backup') }}</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $backup['last_success_at'] ?: __('admin.backups.not_reported') }}</dd>
                </div>
                <div class="rounded-xl bg-white/[.08] p-4">
                    <dt class="text-xs text-white/55">{{ __('admin.backups.recovery_runbook') }}</dt>
                    <dd class="mt-1 break-all text-sm font-semibold">{{ $backup['recovery_runbook'] ?: __('admin.backups.not_reported') }}</dd>
                </div>
            </dl>
            <p class="mt-5 text-xs leading-5 text-amber-100">{{ __('admin.backups.restore_warning') }}</p>
        </section>
    </div>
@endsection
