@extends('layouts.librarian')

@section('title', $inventory->session_number)

@section('content')
<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="admin-kicker">{{ __('librarian.inventory.kicker') }}</p><h1 class="font-headline text-4xl text-primary">{{ $inventory->session_number }}</h1><p class="mt-2 text-slate-600">{{ $inventory->branch?->name }} · {{ $inventory->fund?->name ?? __('librarian.inventory.all_funds') }} · {{ $inventory->shelf_range ?: '—' }}</p></div><a class="admin-btn" href="{{ route('librarian.inventory.export',$inventory) }}">CSV</a></header>
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif
    <section class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">@foreach(['expected_count'=>'expected','found_count'=>'found','missing_count'=>'missing','misplaced_count'=>'misplaced','unknown_count'=>'unknown','duplicate_count'=>'duplicates'] as $field=>$label)<div class="admin-card"><strong class="font-headline text-3xl text-primary">{{ $inventory->$field }}</strong><span class="mt-1 block text-sm text-slate-500">{{ __('librarian.inventory.'.$label) }}</span></div>@endforeach</section>
    @if($inventory->status==='draft')<form method="POST" action="{{ route('librarian.inventory.start',$inventory) }}">@csrf<button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.inventory.start') }}</button></form>@endif
    @if($inventory->status==='running')
    <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('librarian.inventory.scan') }}</h2><form id="inventory-scan" method="POST" action="{{ route('librarian.inventory.scan',$inventory) }}" class="mt-4 flex gap-3">@csrf<input id="scan-code" class="admin-input" name="code" autocomplete="off" autofocus placeholder="{{ __('librarian.inventory.scan_placeholder') }}" required><button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.inventory.register_scan') }}</button></form><p class="mt-2 text-xs text-slate-500">{{ __('librarian.inventory.scan_help') }}</p></section>
    <form method="POST" action="{{ route('librarian.inventory.complete',$inventory) }}" onsubmit="return confirm('{{ __('librarian.inventory.complete_confirm') }}')">@csrf<button class="admin-btn" type="submit">{{ __('librarian.inventory.complete') }}</button></form>
    @endif
    @if($inventory->status==='review')<form method="POST" action="{{ route('librarian.inventory.approve',$inventory) }}" onsubmit="return confirm('{{ __('librarian.inventory.approve_confirm') }}')">@csrf<button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.inventory.approve') }}</button></form>@endif
    <section class="admin-card overflow-x-auto"><h2 class="font-headline text-2xl text-primary">{{ __('librarian.inventory.snapshot') }}</h2><table class="admin-table mt-4"><thead><tr><th>{{ __('librarian.inventory.code') }}</th><th>{{ __('librarian.inventory.book') }}</th><th>{{ __('librarian.inventory.expected_status') }}</th><th>{{ __('librarian.inventory.result') }}</th></tr></thead><tbody>@foreach($inventory->items as $item)<tr><td>{{ $item->copy?->inventory_number }}</td><td>{{ $item->copy?->bibliographicRecord?->title }}</td><td>{{ $item->expected_status }}</td><td>{{ __('librarian.inventory.results.'.$item->result) }}</td></tr>@endforeach</tbody></table></section>
</div>
<script>document.getElementById('inventory-scan')?.addEventListener('submit',function(){this.querySelector('button').disabled=true});</script>
@endsection
