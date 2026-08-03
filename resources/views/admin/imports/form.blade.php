@extends('layouts.admin')

@section('title', __('admin.imports.types.'.$type.'.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.imports.eyebrow')"
        :title="__('admin.imports.types.'.$type.'.title')"
        :subtitle="__('admin.imports.types.'.$type.'.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ $type === 'users' ? route('admin.users.index') : route('admin.external-resources.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
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

    <div class="grid grid-cols-1 gap-7 lg:grid-cols-2">
        <form
            method="POST"
            action="{{ route('admin.imports.preview', $type) }}"
            enctype="multipart/form-data"
            class="admin-card"
        >
            @csrf
            <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('admin.imports.upload_title') }}</h2>
            <label>
                <span class="admin-label">{{ __('admin.imports.file_label') }}</span>
                <input class="admin-input" type="file" name="file" accept=".csv,text/csv" required>
            </label>
            <p class="mt-2 text-xs text-slate-500">{{ __('admin.imports.file_hint') }}</p>
            <button class="admin-btn admin-btn-primary mt-5" type="submit">
                <span class="material-symbols-outlined text-[19px]">preview</span>
                {{ __('admin.imports.preview_action') }}
            </button>
        </form>

        <section class="admin-card">
            <h2 class="mb-4 font-headline text-2xl text-primary">{{ __('admin.imports.format_title') }}</h2>
            <p class="mb-3 text-sm leading-6 text-slate-600">{{ __('admin.imports.types.'.$type.'.format_note') }}</p>
            <pre class="overflow-x-auto rounded-xl bg-primary p-4 font-mono text-xs leading-6 text-slate-200">{{ __('admin.imports.types.'.$type.'.example') }}</pre>
            <ul class="mt-4 space-y-2 text-xs leading-5 text-slate-600">
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    {{ __('admin.imports.rule_transaction') }}
                </li>
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    {{ __('admin.imports.rule_audit') }}
                </li>
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-[16px] text-secondary">check</span>
                    {{ __('admin.imports.types.'.$type.'.rule_matching') }}
                </li>
            </ul>
        </section>
    </div>
@endsection
