@extends('layouts.admin')

@section('title', __('admin.imports.preview_title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.imports.eyebrow')"
        :title="__('admin.imports.preview_title')"
        :subtitle="__('admin.imports.preview_subtitle', ['file' => $fileName])"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.imports.form', $type) }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('admin.imports.choose_other_file') }}
        </a>
    </x-admin.page-header>

    @if ($errors->any())
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <div>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="admin-card">
            <small class="block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.imports.stats.create') }}</small>
            <strong class="mt-1 block font-headline text-4xl text-emerald-700">{{ $stats['create'] }}</strong>
        </div>
        <div class="admin-card">
            <small class="block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.imports.stats.update') }}</small>
            <strong class="mt-1 block font-headline text-4xl text-primary">{{ $stats['update'] }}</strong>
        </div>
        <div class="admin-card">
            <small class="block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.imports.stats.error') }}</small>
            <strong class="mt-1 block font-headline text-4xl {{ $stats['error'] > 0 ? 'text-red-700' : 'text-slate-400' }}">{{ $stats['error'] }}</strong>
        </div>
    </div>

    <section class="admin-card mb-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.imports.columns.line') }}</th>
                        <th>{{ __('admin.imports.columns.action') }}</th>
                        <th>{{ $type === 'users' ? __('common.fields.email') : __('admin.imports.columns.title') }}</th>
                        <th>{{ $type === 'users' ? __('common.fields.name') : __('admin.imports.columns.url') }}</th>
                        <th>{{ $type === 'users' ? __('common.fields.role') : __('admin.imports.columns.resource_type') }}</th>
                        <th>{{ __('admin.imports.columns.problems') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['bg-red-50/60' => $row['action'] === 'error'])>
                            <td class="font-mono text-xs text-slate-500">{{ $row['line'] }}</td>
                            <td>
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide',
                                    'bg-emerald-100 text-emerald-800' => $row['action'] === 'create',
                                    'bg-sky-100 text-sky-800' => $row['action'] === 'update',
                                    'bg-red-100 text-red-800' => $row['action'] === 'error',
                                ])>{{ __('admin.imports.actions.'.$row['action']) }}</span>
                            </td>
                            @if ($type === 'users')
                                <td class="font-mono text-xs">{{ $row['attributes']['email'] }}</td>
                                <td class="text-sm">{{ $row['attributes']['name'] }}</td>
                                <td class="font-mono text-xs">{{ $row['attributes']['role'] }}</td>
                            @else
                                <td class="text-sm">{{ $row['attributes']['title'] }}</td>
                                <td class="break-all font-mono text-xs">{{ $row['attributes']['url'] }}</td>
                                <td class="font-mono text-xs">{{ $row['attributes']['resource_type'] }}</td>
                            @endif
                            <td>
                                @if ($row['errors'] === [])
                                    <span class="text-xs text-slate-400">—</span>
                                @else
                                    <ul class="space-y-1 text-xs text-red-700">
                                        @foreach ($row['errors'] as $rowError)
                                            <li>{{ $rowError }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($stats['error'] > 0)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="material-symbols-outlined text-[20px]">warning</span>
            {{ __('admin.imports.fix_errors_first') }}
        </div>
    @else
        <form method="POST" action="{{ route('admin.imports.commit', $type) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button class="admin-btn admin-btn-primary" type="submit">
                <span class="material-symbols-outlined text-[19px]">upload_file</span>
                {{ __('admin.imports.commit_action', ['count' => $stats['create'] + $stats['update']]) }}
            </button>
        </form>
    @endif
@endsection
