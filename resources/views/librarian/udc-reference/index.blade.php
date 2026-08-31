@extends('layouts.librarian')

@section('title', __('librarian.udc_reference.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header
        :eyebrow="__('librarian.udc_reference.eyebrow')"
        :title="__('librarian.udc_reference.title')"
        :subtitle="__('librarian.udc_reference.subtitle', ['count' => number_format($unverifiedCount, 0, ',', ' ')])"
    />

    <form class="admin-card mb-6 flex gap-3" method="GET">
        <input class="admin-input" name="search" value="{{ $search }}" placeholder="{{ __('librarian.udc_reference.search_placeholder') }}">
        <button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.udc_reference.search') }}</button>
    </form>

    <div class="space-y-4">
        @foreach ($codes as $code)
            <form class="admin-card" method="POST" action="{{ route('librarian.udc-reference.update', $code) }}">
                @csrf
                @method('PATCH')
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <strong class="font-mono text-xl text-primary">{{ $code->code }}</strong>
                    @if ($code->parent)
                        <span class="text-xs text-slate-500">{{ __('librarian.udc_reference.parent') }}: {{ $code->parent->code }}</span>
                    @endif
                    <span class="rounded px-2 py-1 text-xs {{ $code->is_verified ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                        {{ $code->is_verified ? __('librarian.udc_reference.verified') : __('librarian.udc_reference.needs_review') }}
                    </span>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <label>
                        <span class="admin-label">{{ __('librarian.udc_reference.description_ru') }}</span>
                        <input class="admin-input" name="description" required value="{{ $code->description }}">
                    </label>
                    <label>
                        <span class="admin-label">{{ __('librarian.udc_reference.department') }}</span>
                        <input class="admin-input" name="department" value="{{ $code->department }}">
                    </label>
                    <label>
                        <span class="admin-label">{{ __('librarian.udc_reference.description_kk') }}</span>
                        <input class="admin-input" name="description_kk" value="{{ $code->description_kk }}">
                    </label>
                    <label>
                        <span class="admin-label">{{ __('librarian.udc_reference.description_en') }}</span>
                        <input class="admin-input" name="description_en" value="{{ $code->description_en }}">
                    </label>
                </div>
                <div class="mt-4 flex items-center justify-between gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_verified" value="1" @checked($code->is_verified)>
                        {{ __('librarian.udc_reference.verified_checkbox') }}
                    </label>
                    <button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.udc_reference.save') }}</button>
                </div>
            </form>
        @endforeach
    </div>

    <div class="mt-6">{{ $codes->links() }}</div>
@endsection
