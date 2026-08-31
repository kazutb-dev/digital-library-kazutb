@php
    $line = $item ?? null;
    $defaults = $numberingDefaults ?? [];
    $field = static fn (string $name, mixed $default = null): mixed => old('items.'.$itemIndex.'.'.$name, data_get($line, $name, $default));
    $listField = static function (string $name) use ($field): string {
        $value = $field($name);

        return is_array($value) ? implode("\n", $value) : (string) ($value ?? '');
    };
    $inventoryMode = str_replace('-', '_', (string) $field('inventory_number_mode', data_get($defaults, 'inventory_number_mode', 'auto')));
    $barcodeMode = str_replace('-', '_', (string) $field('barcode_mode', data_get($defaults, 'barcode_mode', 'auto')));
    $inventoryAutoEnabled = (bool) data_get($defaults, 'inventory_auto_enabled', true);
    $barcodeAutoEnabled = (bool) data_get($defaults, 'barcode_auto_enabled', true);
@endphp
<fieldset class="rounded-xl border border-slate-200 bg-slate-50/60 p-4" data-acquisition-line>
    <div class="mb-4 flex items-center justify-between gap-3">
        <legend class="font-semibold text-primary">{{ __('operations.acquisitions.record') }}</legend>
        <button class="text-sm font-semibold text-red-700 hover:underline" type="button" data-remove-line>{{ __('operations.acquisitions.remove_line') }}</button>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="sm:col-span-2">
            <span class="admin-label">{{ __('operations.acquisitions.record_id') }}</span>
            <input class="admin-input" type="number" min="1" list="operations-records" name="items[{{ $itemIndex }}][bibliographic_record_id]" value="{{ $field('bibliographic_record_id') }}" required>
            @if(data_get($line, 'title_snapshot'))<span class="mt-1 block text-xs text-slate-600">{{ data_get($line, 'title_snapshot') }}</span>@endif
        </label>
        <label><span class="admin-label">{{ __('operations.acquisitions.quantity') }}</span><input class="admin-input" type="number" min="1" max="10000" name="items[{{ $itemIndex }}][quantity]" value="{{ $field('quantity', 1) }}" required></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.unit_price') }}</span><input class="admin-input" type="number" min="0" step="0.01" name="items[{{ $itemIndex }}][unit_price]" value="{{ $field('unit_price', '0.00') }}" required></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.accounting_type') }}</span><select class="admin-input" name="items[{{ $itemIndex }}][accounting_type]" required>@foreach(['inventory','individual','non_inventory'] as $value)<option value="{{ $value }}" @selected($field('accounting_type', 'inventory') === $value)>{{ __('operations.acquisitions.accounting_types.'.$value) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.condition') }}</span><select class="admin-input" name="items[{{ $itemIndex }}][condition]" required>@foreach(['new','good','worn','damaged'] as $value)<option value="{{ $value }}" @selected($field('condition', 'new') === $value)>{{ __('operations.acquisitions.conditions.'.$value) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.access_restriction') }}</span><select class="admin-input" name="items[{{ $itemIndex }}][access_restriction]" required>@foreach(['free','reading_room','limited'] as $value)<option value="{{ $value }}" @selected($field('access_restriction', 'free') === $value)>{{ __('operations.acquisitions.access.'.$value) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.storage_sigla') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][storage_sigla]" value="{{ $field('storage_sigla', data_get($defaults, 'storage_sigla')) }}" maxlength="64"></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.service_point_code') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][service_point_code]" value="{{ $field('service_point_code', data_get($defaults, 'service_point_code')) }}" maxlength="64"></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.room') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][room]" value="{{ $field('room') }}" maxlength="128"></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.section') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][section]" value="{{ $field('section') }}" maxlength="128"></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.shelf') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][shelf_location]" value="{{ $field('shelf_location') }}" maxlength="255"></label>
        <label><span class="admin-label">{{ __('operations.acquisitions.shelf_index') }}</span><input class="admin-input" name="items[{{ $itemIndex }}][shelf_index]" value="{{ $field('shelf_index') }}" maxlength="255"></label>

        <label>
            <span class="admin-label">{{ __('operations.acquisitions.inventory_number_mode') }}</span>
            <select class="admin-input" name="items[{{ $itemIndex }}][inventory_number_mode]" data-inventory-mode required>
                <option value="auto" @selected($inventoryMode === 'auto') @disabled(!$inventoryAutoEnabled && $inventoryMode !== 'auto')>{{ __('operations.acquisitions.number_modes.auto') }}</option>
                <option value="manual_list" @selected($inventoryMode === 'manual_list')>{{ __('operations.acquisitions.number_modes.manual_list') }}</option>
                <option value="range" @selected($inventoryMode === 'range')>{{ __('operations.acquisitions.number_modes.range') }}</option>
            </select>
            @unless($inventoryAutoEnabled)<span class="mt-1 block text-xs text-amber-700">{{ __('operations.acquisitions.inventory_auto_disabled_hint') }}</span>@endunless
        </label>
        <label data-inventory-auto @if($inventoryMode !== 'auto') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.inventory_prefix') }}</span>
            <input class="admin-input" name="items[{{ $itemIndex }}][inventory_prefix]" value="{{ $field('inventory_prefix', data_get($defaults, 'inventory_prefix', 'INV')) }}" maxlength="24">
        </label>
        <label class="sm:col-span-2" data-inventory-manual @if($inventoryMode !== 'manual_list') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.manual_inventory_numbers') }}</span>
            <textarea class="admin-input" name="items[{{ $itemIndex }}][manual_inventory_numbers]" rows="4" placeholder="{{ __('operations.acquisitions.number_list_placeholder') }}">{{ $listField('manual_inventory_numbers') }}</textarea>
            <span class="mt-1 block text-xs text-slate-600">{{ __('operations.acquisitions.exact_quantity_hint') }}</span>
        </label>
        <label data-inventory-range @if($inventoryMode !== 'range') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.inventory_range_start') }}</span>
            <input class="admin-input" name="items[{{ $itemIndex }}][inventory_range_start]" value="{{ $field('inventory_range_start') }}" maxlength="64">
        </label>
        <label data-inventory-range @if($inventoryMode !== 'range') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.inventory_range_end') }}</span>
            <input class="admin-input" name="items[{{ $itemIndex }}][inventory_range_end]" value="{{ $field('inventory_range_end') }}" maxlength="64">
            <span class="mt-1 block text-xs text-slate-600">{{ __('operations.acquisitions.range_hint') }}</span>
        </label>

        <label>
            <span class="admin-label">{{ __('operations.acquisitions.barcode_mode') }}</span>
            <select class="admin-input" name="items[{{ $itemIndex }}][barcode_mode]" data-barcode-mode required>
                <option value="auto" @selected($barcodeMode === 'auto') @disabled(!$barcodeAutoEnabled && $barcodeMode !== 'auto')>{{ __('operations.acquisitions.barcode_modes.auto') }}</option>
                <option value="manual_list" @selected($barcodeMode === 'manual_list')>{{ __('operations.acquisitions.barcode_modes.manual_list') }}</option>
                <option value="none" @selected($barcodeMode === 'none')>{{ __('operations.acquisitions.barcode_modes.none') }}</option>
            </select>
            @unless($barcodeAutoEnabled)<span class="mt-1 block text-xs text-amber-700">{{ __('operations.acquisitions.barcode_auto_disabled_hint') }}</span>@endunless
        </label>
        <label data-barcode-auto @if($barcodeMode !== 'auto') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.barcode_prefix') }}</span>
            <input class="admin-input" name="items[{{ $itemIndex }}][barcode_prefix]" value="{{ $field('barcode_prefix', data_get($defaults, 'barcode_prefix', 'KAZUTB')) }}" maxlength="24">
        </label>
        <label class="sm:col-span-2" data-barcode-manual @if($barcodeMode !== 'manual_list') hidden @endif>
            <span class="admin-label">{{ __('operations.acquisitions.manual_barcodes') }}</span>
            <textarea class="admin-input" name="items[{{ $itemIndex }}][manual_barcodes]" rows="4" placeholder="{{ __('operations.acquisitions.number_list_placeholder') }}">{{ $listField('manual_barcodes') }}</textarea>
            <span class="mt-1 block text-xs text-slate-600">{{ __('operations.acquisitions.exact_quantity_hint') }}</span>
        </label>
        <label class="sm:col-span-2 lg:col-span-4"><span class="admin-label">{{ __('operations.acquisitions.notes') }}</span><textarea class="admin-input" name="items[{{ $itemIndex }}][notes]" rows="2">{{ $field('notes') }}</textarea></label>
    </div>
</fieldset>
