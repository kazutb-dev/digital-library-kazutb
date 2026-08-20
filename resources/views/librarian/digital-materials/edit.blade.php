@extends('layouts.librarian')
@section('title', $material->title)
@section('content')
<x-admin.flash />
<x-admin.page-header :eyebrow="__('digital.ui.eyebrow')" :title="$material->title" :subtitle="__('digital.statuses.'.$material->workflow_status)">
    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.digital-materials.index') }}">{{ __('common.actions.back') }}</a>
</x-admin.page-header>
<div class="grid gap-6 xl:grid-cols-[1fr_360px]">
    <form class="admin-card grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('librarian.digital-materials.update', $material) }}">
        @csrf @method('PATCH')
        <label><span class="admin-label">{{ __('digital.fields.type') }}</span><select class="admin-input" name="material_type">@foreach(\App\Models\Catalog\ElectronicMaterial::MATERIAL_TYPES as $type)<option value="{{ $type }}" @selected($material->material_type === $type)>{{ __('digital.types.'.$type) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('digital.fields.language') }}</span><select class="admin-input" name="language">@foreach(['kk', 'ru', 'en'] as $language)<option value="{{ $language }}" @selected($material->language === $language)>{{ __('locale.names.'.$language) }}</option>@endforeach</select></label>
        <label class="sm:col-span-2"><span class="admin-label">{{ __('digital.fields.title') }}</span><input class="admin-input" name="title" value="{{ old('title', $material->title) }}" required></label>
        <label class="sm:col-span-2"><span class="admin-label">{{ __('digital.fields.description') }}</span><textarea class="admin-input" name="description">{{ old('description', $material->description) }}</textarea></label>
        <label><span class="admin-label">{{ __('digital.fields.source') }}</span><input class="admin-input" name="source" value="{{ old('source', $material->source) }}"></label>
        <label><span class="admin-label">{{ __('digital.fields.rights_holder') }}</span><input class="admin-input" name="rights_holder" value="{{ old('rights_holder', $material->rights_holder) }}"></label>
        <label><span class="admin-label">{{ __('digital.fields.copyright') }}</span><select class="admin-input" name="copyright_status">@foreach(\App\Models\Catalog\ElectronicMaterial::COPYRIGHT_STATUSES as $status)<option value="{{ $status }}" @selected($material->copyright_status === $status)>{{ __('digital.copyright.'.$status) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('digital.fields.licence') }}</span><input class="admin-input" name="licence_type" value="{{ old('licence_type', $material->licence_type) }}"></label>
        <label><span class="admin-label">{{ __('digital.fields.access') }}</span><select class="admin-input" name="access_level">@foreach(\App\Models\Catalog\ElectronicMaterial::ACCESS_LEVELS as $level)<option value="{{ $level }}" @selected($material->access_level === $level)>{{ __('digital.access.'.$level) }}</option>@endforeach</select></label>
        @foreach(['preview_policy' => ['none', 'metadata', 'sample', 'full'], 'download_policy' => ['disabled', 'allowed'], 'print_policy' => ['disabled', 'allowed'], 'copy_policy' => ['disabled', 'allowed']] as $field => $options)
            <label><span class="admin-label">{{ __('digital.fields.'.$field) }}</span><select class="admin-input" name="{{ $field }}">@foreach($options as $option)<option value="{{ $option }}" @selected($material->{$field} === $option)>{{ __('digital.options.'.$option) }}</option>@endforeach</select></label>
        @endforeach
        <input type="hidden" name="campus_only" value="0">
        <label><input type="checkbox" name="campus_only" value="1" @checked($material->campus_only)> {{ __('digital.fields.campus_only') }}</label>
        <label><span class="admin-label">{{ __('digital.fields.embargo_until') }}</span><input class="admin-input" type="date" name="embargo_until" value="{{ $material->embargo_until?->format('Y-m-d') }}"></label>
        <button class="admin-btn admin-btn-primary sm:col-span-2">{{ __('common.actions.save_changes') }}</button>
    </form>
    <aside class="space-y-4">
        <section class="admin-card">
            <h2 class="font-headline text-xl">{{ __('digital.ui.workflow') }}</h2>
            @foreach(\App\Services\Digital\DigitalMaterialWorkflow::TRANSITIONS[$material->workflow_status] ?? [] as $target)
                <form class="mt-3" method="POST" action="{{ route('librarian.digital-materials.transition', $material) }}">@csrf
                    <input type="hidden" name="status" value="{{ $target }}">
                    <textarea class="admin-input mb-2" name="reason" placeholder="{{ __('digital.ui.reason') }}"></textarea>
                    <button class="admin-btn admin-btn-secondary w-full">{{ __('digital.statuses.'.$target) }}</button>
                </form>
            @endforeach
        </section>
        <section class="admin-card">
            <h2 class="font-headline text-xl">{{ __('digital.ui.versions') }}</h2>
            @forelse($material->versions as $version)
                <p class="mt-2 break-all text-sm">v{{ $version->version_number }} · {{ $version->original_filename }} · {{ $version->checksum_sha256 }}</p>
            @empty
                <p class="mt-2 text-sm text-slate-500">{{ __('digital.ui.no_versions') }}</p>
            @endforelse
        </section>
    </aside>
</div>
@endsection
