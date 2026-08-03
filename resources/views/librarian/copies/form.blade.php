@extends('layouts.librarian')

@php
    $editing = isset($copy);
    $pageTitle = $editing ? __('librarian.copies.edit') : __('librarian.copies.create');
@endphp

@section('title', $pageTitle.' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $copyConditions = \App\Models\Catalog\BookCopy::CONDITIONS;
        $copyAccessRestrictions = \App\Models\Catalog\BookCopy::ACCESS_RESTRICTIONS;
        $copyStatuses = \App\Models\Catalog\BookCopy::STATUSES;

        $editedCopy = $editing ? $copy : null;

        $value = static function (string $field, $fallback = null) use ($editedCopy) {
            return old($field, $editedCopy?->{$field} ?? $fallback);
        };

        $cancelUrl = $editing
            ? route('librarian.copies.show', $copy)
            : route('librarian.copies.index');
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.copies.eyebrow')"
        :title="$pageTitle"
        :subtitle="$editing ? $copy->inventory_number : __('librarian.copies.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ $cancelUrl }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @if ($editing)
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.label', $copy) }}" target="_blank" rel="noopener">
                <span class="material-symbols-outlined text-[19px]">print</span>
                {{ __('librarian.copies.print_label') }}
            </a>
        @endif
    </x-admin.page-header>

    <form
        method="POST"
        action="{{ $editing ? route('librarian.copies.update', $copy) : route('librarian.copies.store') }}"
        class="space-y-6"
    >
        @csrf
        @if ($editing)
            @method('PATCH')
        @endif

        <section class="admin-card">
            <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                <span class="material-symbols-outlined text-secondary">menu_book</span>
                {{ __('librarian.copies.fields.record') }}
            </h2>

            @if ($editing)
                <div class="rounded-xl border border-slate-200 bg-surface-container-low px-4 py-3">
                    <p class="text-base font-semibold text-primary">
                        @if ($record)
                            @can('catalog.edit_record')
                                <a class="text-secondary hover:underline" href="{{ route('librarian.catalog.edit', $record) }}">{{ $record->title }}</a>
                            @else
                                {{ $record->title }}
                            @endcan
                        @else
                            —
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $record?->primary_author ?: '—' }}@if ($record?->publication_year), {{ $record->publication_year }}@endif
                    </p>
                </div>
            @elseif ($record)
                <input type="hidden" name="bibliographic_record_id" value="{{ $record->id }}">
                <div class="rounded-xl border border-slate-200 bg-surface-container-low px-4 py-3">
                    <p class="text-base font-semibold text-primary">{{ $record->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $record->primary_author ?: '—' }}@if ($record->publication_year), {{ $record->publication_year }}@endif
                    </p>
                </div>
                @error('bibliographic_record_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            @else
                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.record') }}</span>
                    <select class="admin-input" name="bibliographic_record_id" required>
                        <option value="">—</option>
                        @foreach ($records as $option)
                            <option value="{{ $option->id }}" @selected((int) old('bibliographic_record_id') === $option->id)>
                                {{ $option->title }}@if ($option->publication_year) ({{ $option->publication_year }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('bibliographic_record_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>
            @endif
        </section>

        <section class="admin-card">
            <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                <span class="material-symbols-outlined text-secondary">tag</span>
                {{ __('librarian.copies.fields.inventory_number') }}
            </h2>

            @unless ($editing)
                <div class="mb-5 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3">
                    <label class="block max-w-xs">
                        <span class="admin-label">{{ __('librarian.copies.fields.quantity') }}</span>
                        <input
                            class="admin-input"
                            type="number"
                            name="quantity"
                            min="1"
                            max="100"
                            step="1"
                            required
                            value="{{ old('quantity', 1) }}"
                        >
                        @error('quantity')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                    <p class="mt-2 text-xs leading-5 text-cyan-900">{{ __('librarian.copies.bulk_hint') }}</p>
                </div>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.inventory_number') }}</span>
                    <input class="admin-input font-mono" type="text" name="inventory_number" maxlength="64" required value="{{ $value('inventory_number') }}">
                    @error('inventory_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.barcode') }}</span>
                    <input class="admin-input font-mono" type="text" name="barcode" maxlength="64" value="{{ $value('barcode') }}">
                    @error('barcode')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.accounting_type') }}</span>
                    <input class="admin-input" type="text" name="accounting_type" maxlength="32" value="{{ $value('accounting_type') }}">
                    @error('accounting_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.ksu_number') }}</span>
                    <input class="admin-input" type="text" name="ksu_number" maxlength="64" value="{{ $value('ksu_number') }}">
                    @error('ksu_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.storage_sigla') }}</span>
                    <input class="admin-input" type="text" name="storage_sigla" maxlength="64" value="{{ $value('storage_sigla') }}">
                    @error('storage_sigla')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.access_restriction') }}</span>
                    <select class="admin-input" name="access_restriction" required>
                        @foreach ($copyAccessRestrictions as $restriction)
                            <option value="{{ $restriction }}" @selected($value('access_restriction', 'free') === $restriction)>
                                {{ __('librarian.copies.access_restrictions.'.$restriction) }}
                            </option>
                        @endforeach
                    </select>
                    @error('access_restriction')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                @if ($editing)
                    <label class="block">
                        <span class="admin-label">{{ __('librarian.copies.fields.status') }}</span>
                        <select class="admin-input" name="status" required>
                            @foreach ($copyStatuses as $status)
                                <option value="{{ $status }}" @selected($value('status') === $status)>
                                    {{ __('librarian.copies.statuses.'.$status) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                @endif
            </div>
        </section>

        <section class="admin-card">
            <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                <span class="material-symbols-outlined text-secondary">shelves</span>
                {{ __('librarian.copies.fields.shelf_location') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.branch') }}</span>
                    <select class="admin-input" name="branch_id">
                        <option value="">—</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $value('branch_id') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.fund') }}</span>
                    <select class="admin-input" name="fund_id">
                        <option value="">—</option>
                        @foreach ($funds as $fund)
                            <option value="{{ $fund->id }}" @selected((int) $value('fund_id') === $fund->id)>{{ $fund->name }}</option>
                        @endforeach
                    </select>
                    @error('fund_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.shelf_location') }}</span>
                    <input class="admin-input" type="text" name="shelf_location" maxlength="255" value="{{ $value('shelf_location') }}">
                    @error('shelf_location')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>
            </div>
        </section>

        <section class="admin-card">
            <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                <span class="material-symbols-outlined text-secondary">receipt_long</span>
                {{ __('librarian.copies.fields.acquisition_source') }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.price') }}</span>
                    <input class="admin-input tabular-nums" type="number" name="price" min="0" max="10000000" step="0.01" value="{{ $value('price') }}">
                    @error('price')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.acquisition_source') }}</span>
                    <input class="admin-input" type="text" name="acquisition_source" maxlength="255" value="{{ $value('acquisition_source') }}">
                    @error('acquisition_source')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.supplier_name') }}</span>
                    <input class="admin-input" type="text" name="supplier_name" maxlength="255" value="{{ $value('supplier_name') }}">
                    @error('supplier_name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.acquisition_date') }}</span>
                    <input
                        class="admin-input"
                        type="date"
                        name="acquisition_date"
                        value="{{ old('acquisition_date', $editing ? $copy->acquisition_date?->format('Y-m-d') : '') }}"
                    >
                    @error('acquisition_date')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="admin-label">{{ __('librarian.copies.fields.condition') }}</span>
                    <select class="admin-input" name="condition" required>
                        @foreach ($copyConditions as $condition)
                            <option value="{{ $condition }}" @selected($value('condition', 'new') === $condition)>
                                {{ __('librarian.copies.conditions.'.$condition) }}
                            </option>
                        @endforeach
                    </select>
                    @error('condition')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                @if ($editing)
                    <label class="block sm:col-span-2 xl:col-span-4">
                        <span class="admin-label">{{ __('librarian.copies.fields.defect_description') }}</span>
                        <textarea class="admin-input" name="defect_description" rows="4" maxlength="2000">{{ $value('defect_description') }}</textarea>
                        @error('defect_description')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                @endif
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <button class="admin-btn admin-btn-primary" type="submit">
                <span class="material-symbols-outlined text-[19px]">save</span>
                {{ $editing ? __('common.actions.save_changes') : __('common.actions.create') }}
            </button>
            <a class="admin-btn admin-btn-secondary" href="{{ $cancelUrl }}">{{ __('common.actions.cancel') }}</a>
        </div>
    </form>
@endsection
