@extends('layouts.librarian')
@section('title', __('librarian.nav.digital_materials'))
@section('content')
<x-admin.flash />
<x-admin.page-header :eyebrow="__('digital.ui.eyebrow')" :title="__('librarian.nav.digital_materials')" :subtitle="__('digital.ui.subtitle')" />
<form class="admin-card mb-6 grid gap-3 sm:grid-cols-3" method="GET">
    <input class="admin-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('digital.ui.search') }}">
    <select class="admin-input" name="status">
        <option value="">{{ __('digital.ui.all_statuses') }}</option>
        @foreach(\App\Models\Catalog\ElectronicMaterial::WORKFLOW_STATUSES as $status)
            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('digital.statuses.'.$status) }}</option>
        @endforeach
    </select>
    <button class="admin-btn admin-btn-primary">{{ __('common.actions.search') }}</button>
</form>
<div class="grid gap-4">
    @forelse($materials as $material)
        <article class="admin-card flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-secondary">{{ __('digital.types.'.$material->material_type) }} · {{ __('digital.statuses.'.$material->workflow_status) }}</p>
                <h2 class="font-headline text-xl text-primary">{{ $material->title }}</h2>
                <p class="text-sm text-slate-500">{{ $material->bibliographicRecord?->title }}</p>
            </div>
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.digital-materials.edit', $material) }}">{{ __('common.actions.edit') }}</a>
        </article>
    @empty
        <div class="admin-card text-slate-500">{{ __('common.pagination.no_results') }}</div>
    @endforelse
</div>
<div class="mt-6">{{ $materials->links() }}</div>
@endsection
