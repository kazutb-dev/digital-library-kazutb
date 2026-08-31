@extends('layouts.librarian')

@section('title', __('copy_writeoff.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header :eyebrow="__('librarian.copies.eyebrow')" :title="__('copy_writeoff.title')" :subtitle="__('copy_writeoff.subtitle')">
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.index') }}">{{ __('common.actions.back') }}</a>
    </x-admin.page-header>

    <form class="admin-card mx-auto max-w-5xl" method="POST" action="{{ route('librarian.copies.write-off.store') }}">
        @csrf
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('copy_writeoff.warning') }}</div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <label class="lg:row-span-3"><span class="admin-label">{{ __('copy_writeoff.fields.copy_codes') }}</span><textarea class="admin-input min-h-64 font-mono" name="copy_codes" maxlength="50000" required autofocus placeholder="{{ __('copy_writeoff.placeholder') }}">{{ old('copy_codes') }}</textarea><span class="mt-1 block text-xs text-slate-500">{{ __('copy_writeoff.hint') }}</span></label>
            <label><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_date') }}</span><input class="admin-input" type="date" name="writeoff_date" value="{{ old('writeoff_date', now()->toDateString()) }}" required></label>
            <label><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_act') }}</span><input class="admin-input" name="writeoff_act" maxlength="128" value="{{ old('writeoff_act') }}" required></label>
            <label><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_reason') }}</span><textarea class="admin-input" name="writeoff_reason" minlength="5" maxlength="2000" required>{{ old('writeoff_reason') }}</textarea></label>
        </div>
        <label class="mt-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><input class="mt-1" type="checkbox" name="confirmed" value="1" required><span>{{ __('copy_writeoff.confirmation') }}</span></label>
        <div class="mt-5 flex justify-end"><button class="admin-btn admin-btn-primary" type="submit"><span class="material-symbols-outlined text-[19px]">inventory_2</span>{{ __('copy_writeoff.action') }}</button></div>
    </form>
@endsection
