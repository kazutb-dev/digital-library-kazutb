@extends('layouts.admin')

@section('title', __('admin.error_log.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.error_log.eyebrow')"
        :title="__('admin.error_log.title')"
        :subtitle="__('admin.error_log.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.logs.index') }}">
            <span class="material-symbols-outlined text-[19px]">gavel</span>
            {{ __('admin.nav.audit_logs') }}
        </a>
    </x-admin.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a
            href="{{ route('admin.error-log.index') }}"
            @class([
                'admin-btn',
                'admin-btn-primary' => $level === null,
                'admin-btn-secondary' => $level !== null,
            ])
        >{{ __('common.filters.all') }}</a>
        @foreach ($levelCounts as $levelName => $count)
            <a
                href="{{ route('admin.error-log.index', ['level' => $levelName]) }}"
                @class([
                    'admin-btn',
                    'admin-btn-primary' => $level === $levelName,
                    'admin-btn-secondary' => $level !== $levelName,
                ])
            >
                {{ __('admin.error_log.levels.'.$levelName) }}
                <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    <section class="admin-card mb-8 overflow-hidden p-0">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-headline text-2xl text-primary">{{ __('admin.error_log.laravel_log') }}</h2>
            <p class="text-xs text-slate-500">{{ __('admin.error_log.read_only_note') }}</p>
        </div>

        @if (! $logAvailable)
            <p class="px-5 py-16 text-center text-sm text-slate-500">{{ __('admin.error_log.log_unavailable') }}</p>
        @elseif ($entries === [])
            <div class="py-16 text-center">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">task_alt</span>
                <p class="text-sm text-slate-500">{{ __('admin.error_log.empty') }}</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($entries as $entry)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="whitespace-nowrap font-mono text-xs text-slate-500">{{ $entry['timestamp'] }}</span>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide',
                                'bg-red-100 text-red-800' => in_array($entry['level'], ['emergency', 'alert', 'critical', 'error'], true),
                                'bg-amber-100 text-amber-800' => $entry['level'] === 'warning',
                                'bg-slate-100 text-slate-600' => ! in_array($entry['level'], ['emergency', 'alert', 'critical', 'error', 'warning'], true),
                            ])>{{ __('admin.error_log.levels.'.$entry['level']) }}</span>
                            <span class="rounded-full bg-surface-low px-2.5 py-0.5 font-mono text-[11px] text-slate-500">{{ $entry['environment'] }}</span>
                        </div>
                        <p class="mt-2 break-all font-mono text-sm leading-6 text-slate-800">{{ $entry['message'] }}</p>
                        @if ($entry['trace'] !== '')
                            <details class="mt-2">
                                <summary class="inline-flex cursor-pointer items-center gap-1 text-xs font-semibold text-secondary hover:underline">
                                    <span class="material-symbols-outlined text-[16px]">unfold_more</span>
                                    {{ __('admin.error_log.show_trace') }}
                                </summary>
                                <pre class="mt-2 max-h-96 overflow-auto rounded-xl bg-primary p-4 font-mono text-xs leading-5 text-slate-200">{{ $entry['trace'] }}</pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="admin-card overflow-hidden p-0">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center">
            <h2 class="font-headline text-2xl text-primary">{{ __('admin.error_log.failed_jobs') }}</h2>
            <p class="text-xs text-slate-500">{{ __('admin.error_log.failed_jobs_hint') }}</p>
        </div>

        @if (! $failedJobsAvailable)
            <p class="px-5 py-10 text-center text-sm text-slate-500">{{ __('common.not_configured') }}</p>
        @elseif ($failedJobs->isEmpty())
            <div class="py-12 text-center">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">task_alt</span>
                <p class="text-sm text-slate-500">{{ __('admin.error_log.no_failed_jobs') }}</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($failedJobs as $job)
                    <li class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="font-mono text-xs text-slate-500">{{ $job->failed_at }}</span>
                            <span class="rounded-full bg-surface-low px-2.5 py-0.5 font-mono text-[11px] text-slate-500">{{ $job->queue }}</span>
                            <span class="font-mono text-xs text-slate-600">{{ $job->uuid }}</span>
                        </div>
                        <details class="mt-2">
                            <summary class="inline-flex cursor-pointer items-center gap-1 text-xs font-semibold text-secondary hover:underline">
                                <span class="material-symbols-outlined text-[16px]">unfold_more</span>
                                {{ __('admin.error_log.show_trace') }}
                            </summary>
                            <pre class="mt-2 max-h-96 overflow-auto rounded-xl bg-primary p-4 font-mono text-xs leading-5 text-slate-200">{{ $job->exception }}</pre>
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
