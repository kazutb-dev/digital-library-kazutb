@extends('layouts.librarian')

@section('title', __('operations.acquisitions.title'))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="admin-kicker">{{ __('operations.acquisitions.kicker') }}</p><h1 class="font-headline text-4xl text-primary">{{ __('operations.acquisitions.title') }}</h1><p class="mt-2 max-w-3xl text-slate-600">{{ __('operations.acquisitions.description') }}</p></div>
        <div class="flex flex-wrap gap-3">
            @can('catalog.create_record')
                <a class="admin-btn admin-btn-primary" href="{{ route('librarian.catalog.create', ['return_to' => 'acquisitions']) }}">{{ __('librarian.catalog.new_record') }}</a>
            @endcan
            @can('ksu.view')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.ksu.index') }}">{{ __('operations.ksu.title') }}</a>
            @endcan
        </div>
    </header>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="admin-card">
        <form method="GET" action="{{ route('librarian.acquisitions.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
            <label><span class="admin-label">{{ __('operations.common.search') }}</span><input class="admin-input" type="search" name="q" value="{{ request('q') }}"></label>
            <label><span class="admin-label">{{ __('operations.common.status') }}</span><select class="admin-input" name="status"><option value="">{{ __('operations.common.all') }}</option>@foreach(\App\Models\Operations\AcquisitionBatch::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('operations.acquisitions.statuses.'.$status) }}</option>@endforeach</select></label>
            <button class="admin-btn admin-btn-secondary self-end" type="submit">{{ __('operations.common.filter') }}</button>
        </form>
    </section>

    @if($canManage)
    <details class="admin-card" @if($errors->any()) open @endif>
        <summary class="cursor-pointer font-headline text-2xl text-primary">{{ __('operations.acquisitions.new_batch') }}</summary>
        <form method="POST" action="{{ route('librarian.acquisitions.store') }}" class="mt-5 space-y-5" data-acquisition-form>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label><span class="admin-label">{{ __('operations.acquisitions.batch_number') }}</span><input class="admin-input" name="batch_number" value="{{ old('batch_number') }}" placeholder="{{ __('operations.acquisitions.automatic_number') }}"></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.received_at') }}</span><input class="admin-input" type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" required></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.source') }}</span><select class="admin-input" name="acquisition_source" required>@foreach(\App\Models\Catalog\BookCopy::ACQUISITION_SOURCES as $source)<option value="{{ $source }}" @selected(old('acquisition_source', 'purchase') === $source)>{{ __('operations.acquisitions.sources.'.$source) }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.currency') }}</span><select class="admin-input" name="currency">@foreach(['KZT','USD','EUR'] as $currency)<option @selected(old('currency', 'KZT') === $currency)>{{ $currency }}</option>@endforeach</select></label>
                <label class="sm:col-span-2"><span class="admin-label">{{ __('operations.acquisitions.supplier') }}</span><input class="admin-input" name="supplier_name" value="{{ old('supplier_name') }}"></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.branch') }}</span><select class="admin-input" name="branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((string) old('fund_id') === (string) $fund->id)>{{ $fund->name }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('operations.acquisitions.order') }}</span><select class="admin-input" name="acquisition_order_id"><option value="">—</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected((string) old('acquisition_order_id') === (string) $order->id)>{{ $order->order_number }}</option>@endforeach</select></label>
                <label class="sm:col-span-2 lg:col-span-3"><span class="admin-label">{{ __('operations.acquisitions.notes') }}</span><textarea class="admin-input" name="notes" rows="2">{{ old('notes') }}</textarea></label>
            </div>

            <datalist id="operations-records">@foreach($records as $record)<option value="{{ $record->id }}">{{ $record->title }} @if($record->primary_author) — {{ $record->primary_author }}@endif</option>@endforeach</datalist>
            <div><h2 class="font-headline text-2xl text-primary">{{ __('operations.acquisitions.items') }}</h2><p class="text-sm text-slate-600">{{ __('operations.acquisitions.record_search_hint') }}</p></div>
            <div class="space-y-4" data-lines>
                @foreach(old('items', [null]) as $index => $oldItem)
                    @include('librarian.acquisitions._item-fields', ['itemIndex' => $index, 'item' => $oldItem])
                @endforeach
            </div>
            <template data-line-template>@include('librarian.acquisitions._item-fields', ['itemIndex' => '__INDEX__', 'item' => null])</template>
            <div class="flex flex-wrap gap-3"><button class="admin-btn admin-btn-secondary" type="button" data-add-line>{{ __('operations.acquisitions.add_line') }}</button><button class="admin-btn admin-btn-primary" type="submit">{{ __('operations.acquisitions.save_draft') }}</button></div>
        </form>
    </details>
    @endif

    <section class="admin-card overflow-hidden">
        <div class="overflow-x-auto"><table class="admin-table min-w-[850px]"><thead><tr><th>{{ __('operations.acquisitions.batch') }}</th><th>{{ __('operations.acquisitions.received_at') }}</th><th>{{ __('operations.acquisitions.supplier') }}</th><th>{{ __('operations.common.status') }}</th><th>{{ __('operations.acquisitions.titles') }}</th><th>{{ __('operations.acquisitions.copies') }}</th><th>{{ __('operations.acquisitions.total') }}</th><th></th></tr></thead><tbody>
        @forelse($batches as $batch)<tr><td class="font-semibold text-primary">{{ $batch->batch_number }}</td><td>{{ $batch->received_at?->format('d.m.Y') }}</td><td>{{ $batch->supplier_name ?: '—' }}</td><td>{{ __('operations.acquisitions.statuses.'.$batch->status) }}</td><td>{{ $batch->title_count }}</td><td>{{ $batch->copy_count }}</td><td>{{ number_format((float) $batch->total_amount, 2, ',', ' ') }} {{ $batch->currency }}</td><td><a class="font-semibold text-secondary hover:underline" href="{{ route('librarian.acquisitions.show', $batch) }}">{{ __('operations.common.open') }}</a></td></tr>
        @empty<tr><td colspan="8" class="py-8 text-center text-slate-500">{{ __('operations.acquisitions.empty') }}</td></tr>@endforelse
        </tbody></table></div><div class="mt-5">{{ $batches->links() }}</div>
    </section>
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
