@extends('layouts.librarian')

@section('title', $batch->batch_number)

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><a class="text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.acquisitions.index') }}">← {{ __('operations.common.back') }}</a><p class="admin-kicker mt-3">{{ __('operations.acquisitions.batch') }}</p><h1 class="font-headline text-4xl text-primary">{{ $batch->batch_number }}</h1></div><span class="w-fit rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-primary">{{ __('operations.acquisitions.statuses.'.$batch->status) }}</span></header>
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="admin-card"><dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="admin-label">{{ __('operations.acquisitions.received_at') }}</dt><dd>{{ $batch->received_at?->format('d.m.Y') }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.source') }}</dt><dd>{{ __('operations.acquisitions.sources.'.$batch->acquisition_source) }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.supplier') }}</dt><dd>{{ $batch->supplier_name ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.branch') }}</dt><dd>{{ $batch->branch?->name ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.fund') }}</dt><dd>{{ $batch->fund?->name ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.titles') }} / {{ __('operations.acquisitions.copies') }}</dt><dd>{{ $batch->title_count }} / {{ $batch->copy_count }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.total') }}</dt><dd>{{ number_format((float) $batch->total_amount, 2, ',', ' ') }} {{ $batch->currency }}</dd></div><div><dt class="admin-label">{{ __('operations.acquisitions.ksu_entry') }}</dt><dd>@if($batch->ksuEntry)<a class="font-semibold text-secondary" href="{{ route('librarian.ksu.show', $batch->ksuEntry) }}">{{ $batch->ksuEntry->entry_number }}</a>@else—@endif</dd></div>
    </dl></section>

    @if($batch->status === 'draft' && $canManage)
    <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('operations.acquisitions.update_draft') }}</h2><form class="mt-5 space-y-5" method="POST" action="{{ route('librarian.acquisitions.update', $batch) }}" data-acquisition-form>@csrf @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><label><span class="admin-label">{{ __('operations.acquisitions.received_at') }}</span><input class="admin-input" type="date" name="received_at" value="{{ old('received_at', $batch->received_at?->toDateString()) }}" required></label><label><span class="admin-label">{{ __('operations.acquisitions.source') }}</span><select class="admin-input" name="acquisition_source">@foreach(\App\Models\Catalog\BookCopy::ACQUISITION_SOURCES as $source)<option value="{{ $source }}" @selected(old('acquisition_source', $batch->acquisition_source) === $source)>{{ __('operations.acquisitions.sources.'.$source) }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('operations.acquisitions.supplier') }}</span><input class="admin-input" name="supplier_name" value="{{ old('supplier_name', $batch->supplier_name) }}"></label><label><span class="admin-label">{{ __('operations.acquisitions.currency') }}</span><select class="admin-input" name="currency">@foreach(['KZT','USD','EUR'] as $currency)<option @selected(old('currency', $batch->currency) === $currency)>{{ $currency }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('operations.acquisitions.branch') }}</span><select class="admin-input" name="branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $batch->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('operations.acquisitions.fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((string) old('fund_id', $batch->fund_id) === (string) $fund->id)>{{ $fund->name }}</option>@endforeach</select></label><label class="sm:col-span-2"><span class="admin-label">{{ __('operations.acquisitions.notes') }}</span><textarea class="admin-input" name="notes">{{ old('notes', $batch->notes) }}</textarea></label></div>
        <div class="space-y-4" data-lines>@foreach(old('items', $batch->items) as $index => $item)@include('librarian.acquisitions._item-fields', ['itemIndex' => $index, 'item' => $item])@endforeach</div>
        <template data-line-template>@include('librarian.acquisitions._item-fields', ['itemIndex' => '__INDEX__', 'item' => null])</template>
        <div class="flex flex-wrap gap-3"><button class="admin-btn admin-btn-secondary" type="button" data-add-line>{{ __('operations.acquisitions.add_line') }}</button><button class="admin-btn admin-btn-primary" type="submit">{{ __('operations.acquisitions.update_draft') }}</button></div>
    </form></section>
    @endif

    @if($batch->status === 'draft')<section class="admin-card border border-amber-200 bg-amber-50"><p class="text-sm text-amber-900">{{ __('operations.acquisitions.confirm_warning') }}</p><div class="mt-4 flex flex-wrap gap-3">@if($canConfirm)<form method="POST" action="{{ route('librarian.acquisitions.confirm', $batch) }}">@csrf<button class="admin-btn admin-btn-primary" type="submit">{{ __('operations.acquisitions.confirm') }}</button></form>@endif @if($canManage)<form method="POST" action="{{ route('librarian.acquisitions.cancel', $batch) }}">@csrf<button class="admin-btn admin-btn-secondary" type="submit">{{ __('operations.acquisitions.cancel') }}</button></form>@endif</div></section>@endif

    @if($batch->status === 'confirmed')
    <section class="admin-card overflow-hidden"><h2 class="font-headline text-2xl text-primary">{{ __('operations.acquisitions.generated_copies') }}</h2><div class="mt-4 overflow-x-auto"><table class="admin-table min-w-[700px]"><thead><tr><th>{{ __('operations.acquisitions.record') }}</th><th>{{ __('operations.acquisitions.inventory_number') }}</th><th>{{ __('operations.acquisitions.barcode') }}</th><th>{{ __('operations.common.status') }}</th></tr></thead><tbody>
        @foreach($batch->items as $item)
            @foreach($item->copies as $copy)
            <tr><td>{{ $item->title_snapshot }}</td><td>{{ $copy->inventory_number }}</td><td>{{ $copy->barcode }}</td><td>{{ $copy->inventory_status }} / {{ $copy->circulation_status }}</td></tr>
            @endforeach
        @endforeach
    </tbody></table></div></section>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-acquisition-form]').forEach((form) => {
    const lines = form.querySelector('[data-lines]');
    const template = form.querySelector('[data-line-template]');
    const syncLine = (line) => {
        const inventoryMode = line.querySelector('[data-inventory-mode]')?.value;
        const barcodeMode = line.querySelector('[data-barcode-mode]')?.value;
        const toggle = (selector, active) => line.querySelectorAll(selector).forEach((group) => {
            group.hidden = !active;
            group.querySelectorAll('input, textarea').forEach((input) => {
                input.disabled = !active;
                input.required = active;
            });
        });
        toggle('[data-inventory-auto]', inventoryMode === 'auto');
        toggle('[data-inventory-manual]', inventoryMode === 'manual_list');
        toggle('[data-inventory-range]', inventoryMode === 'range');
        toggle('[data-barcode-auto]', barcodeMode === 'auto');
        toggle('[data-barcode-manual]', barcodeMode === 'manual_list');
    };
    let index = lines.querySelectorAll('[data-acquisition-line]').length;
    form.querySelector('[data-add-line]')?.addEventListener('click', () => {
        lines.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
        syncLine(lines.lastElementChild);
    });
    lines.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');
        if (button && lines.querySelectorAll('[data-acquisition-line]').length > 1) button.closest('[data-acquisition-line]').remove();
    });
    lines.addEventListener('change', (event) => {
        if (event.target.matches('[data-inventory-mode], [data-barcode-mode]')) syncLine(event.target.closest('[data-acquisition-line]'));
    });
    lines.querySelectorAll('[data-acquisition-line]').forEach(syncLine);
});
</script>
@endpush
