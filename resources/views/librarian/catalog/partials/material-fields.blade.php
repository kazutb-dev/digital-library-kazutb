{{-- Shared by the "add material" form and every inline edit form. $material is
     null when adding, so every value falls back to a sensible default. --}}
@php
    $isEdit = $material !== null;
    $prefill = static fn (string $field, $fallback = null) => $isEdit ? $material->{$field} : $fallback;
    $repopulate = $isEdit && old('material_id') == $material->id;
    $keep = static fn (string $field, $value) => $repopulate ? old($field, $value) : (! $isEdit ? old($field, $value) : $value);
@endphp

<label class="sm:col-span-2">
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.title') }} *</span>
    <input class="admin-input" type="text" name="title" required maxlength="500" value="{{ $keep('title', $prefill('title')) }}">
    @if ($repopulate || ! $isEdit)
        @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    @endif
</label>

<label>
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.file') }}</span>
    <input class="admin-input" type="file" name="file">
    <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.materials.fields.file_help') }}</span>
    @if ($repopulate || ! $isEdit)
        @error('file')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    @endif
</label>

<label>
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.external_url') }}</span>
    <input class="admin-input" type="url" name="external_url" maxlength="2048" placeholder="https://…" value="{{ $keep('external_url', $prefill('external_url')) }}">
    <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.materials.fields.external_url_help') }}</span>
    @if ($repopulate || ! $isEdit)
        @error('external_url')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    @endif
</label>

<label>
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.file_type') }} *</span>
    <select class="admin-input" name="file_type" required>
        @foreach (\App\Models\Catalog\ElectronicMaterial::FILE_TYPES as $type)
            <option value="{{ $type }}" @selected($keep('file_type', $prefill('file_type', 'pdf')) === $type)>
                {{ __('librarian.catalog.materials.file_types.'.$type) }}
            </option>
        @endforeach
    </select>
</label>

<label>
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.access_level') }} *</span>
    <select class="admin-input" name="access_level" required>
        @foreach (\App\Models\Catalog\ElectronicMaterial::ACCESS_LEVELS as $level)
            <option value="{{ $level }}" @selected($keep('access_level', $prefill('access_level', 'authenticated')) === $level)>
                {{ __('librarian.catalog.materials.access_levels.'.$level) }}
            </option>
        @endforeach
    </select>
</label>

<label class="sm:col-span-2">
    <span class="admin-label">{{ __('librarian.catalog.materials.fields.license_terms') }}</span>
    <textarea class="admin-input" name="license_terms" rows="2" maxlength="2000">{{ $keep('license_terms', $prefill('license_terms')) }}</textarea>
    <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.materials.fields.license_terms_help') }}</span>
    @if ($repopulate || ! $isEdit)
        @error('license_terms')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    @endif
</label>

<div class="flex flex-wrap gap-6 sm:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="allow_download" value="0">
        <input class="rounded border-slate-300" type="checkbox" name="allow_download" value="1" @checked((bool) $keep('allow_download', $prefill('allow_download', false)))>
        {{ __('librarian.catalog.materials.fields.allow_download') }}
    </label>

    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
        <input type="hidden" name="is_active" value="0">
        <input class="rounded border-slate-300" type="checkbox" name="is_active" value="1" @checked((bool) $keep('is_active', $prefill('is_active', true)))>
        {{ __('librarian.catalog.materials.fields.is_active') }}
    </label>
</div>
