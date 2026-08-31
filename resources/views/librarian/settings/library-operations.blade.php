@extends('layouts.librarian')

@php
    $value = static fn (string $key, mixed $default = null): mixed => old($key, $settings->get($key)?->value ?? $default);
@endphp

@section('title', __('library_settings.title'))

@section('content')
<div class="space-y-6">
    <header>
        <p class="admin-kicker">{{ __('library_settings.kicker') }}</p>
        <h1 class="font-headline text-4xl text-primary">{{ __('library_settings.title') }}</h1>
        <p class="mt-2 max-w-3xl text-slate-600">{{ __('library_settings.description') }}</p>
    </header>

    @if(session('success'))<div class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-teal-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('librarian.settings.library-operations.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        <section class="admin-card">
            <h2 class="font-headline text-2xl text-primary">{{ __('library_settings.circulation') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    'max_active_loans' => [5, 1, 100],
                    'standard_loan_period_days' => [14, 1, 365],
                    'renewal_period_days' => [14, 1, 365],
                    'max_renewals' => [1, 0, 20],
                    'max_active_reservations' => [3, 1, 100],
                    'reservation_hold_days' => [1, 1, 30],
                    'fine_per_overdue_day' => [0, 0, 100000],
                ] as $key => [$default, $min, $max])
                    <label>
                        <span class="admin-label">{{ __('library_settings.fields.'.$key) }}</span>
                        <input class="admin-input" type="number" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" required value="{{ $value($key, $default) }}">
                    </label>
                @endforeach
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="renewal_allowed" value="0">
                    <input type="checkbox" name="renewal_allowed" value="1" @checked((bool)$value('renewal_allowed', true))>
                    <span>{{ __('library_settings.fields.renewal_allowed') }}</span>
                </label>
            </div>
        </section>

        <section class="admin-card">
            <h2 class="font-headline text-2xl text-primary">{{ __('library_settings.numbering') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('library_settings.numbering_help') }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="inventory_numbering_enabled" value="0">
                    <input type="checkbox" name="inventory_numbering_enabled" value="1" @checked((bool)$value('inventory_numbering_enabled', true))>
                    <span>{{ __('library_settings.fields.inventory_numbering_enabled') }}</span>
                </label>
                <label>
                    <span class="admin-label">{{ __('library_settings.fields.inventory_number_prefix') }}</span>
                    <input class="admin-input font-mono" name="inventory_number_prefix" maxlength="24" required value="{{ $value('inventory_number_prefix', 'INV') }}">
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="barcode_generation_enabled" value="0">
                    <input type="checkbox" name="barcode_generation_enabled" value="1" @checked((bool)$value('barcode_generation_enabled', true))>
                    <span>{{ __('library_settings.fields.barcode_generation_enabled') }}</span>
                </label>
                <label>
                    <span class="admin-label">{{ __('library_settings.fields.barcode_prefix') }}</span>
                    <input class="admin-input font-mono" name="barcode_prefix" maxlength="24" required value="{{ $value('barcode_prefix', 'KAZUTB') }}">
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="ksu_numbering_enabled" value="0">
                    <input type="checkbox" name="ksu_numbering_enabled" value="1" @checked((bool)$value('ksu_numbering_enabled', true))>
                    <span>{{ __('library_settings.fields.ksu_numbering_enabled') }}</span>
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <input type="hidden" name="ksu_yearly_reset" value="1">
                    <input type="checkbox" checked disabled>
                    <span>{{ __('library_settings.fields.ksu_yearly_reset') }}</span>
                </label>
            </div>
        </section>

        <section class="admin-card">
            <h2 class="font-headline text-2xl text-primary">{{ __('library_settings.inventory') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label>
                    <span class="admin-label">{{ __('library_settings.fields.inventory_batch_scan_limit') }}</span>
                    <input class="admin-input" type="number" name="inventory_batch_scan_limit" min="10" max="100000" required value="{{ $value('inventory_batch_scan_limit', 5000) }}">
                </label>
                <label>
                    <span class="admin-label">{{ __('library_settings.fields.default_service_point') }}</span>
                    <input class="admin-input" name="default_service_point" maxlength="64" value="{{ $value('default_service_point', '') }}">
                </label>
                <label>
                    <span class="admin-label">{{ __('library_settings.fields.default_sigla') }}</span>
                    <input class="admin-input" name="default_sigla" maxlength="64" value="{{ $value('default_sigla', '') }}">
                </label>
            </div>
        </section>

        <div class="flex justify-end"><button class="admin-btn admin-btn-primary" type="submit">{{ __('library_settings.save') }}</button></div>
    </form>
</div>
@endsection
