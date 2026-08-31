@extends('layouts.librarian')

@section('title', __('librarian.inventory.title'))

@section('content')
<div class="space-y-6">
    <header><p class="admin-kicker">{{ __('librarian.inventory.kicker') }}</p><h1 class="font-headline text-4xl text-primary">{{ __('librarian.inventory.title') }}</h1><p class="mt-2 text-slate-600">{{ __('librarian.inventory.description') }}</p></header>
    <section class="admin-card">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-headline text-2xl text-primary">{{ __('librarian.inventory.location_problems') }}</h2><p class="mt-1 text-sm text-slate-600">{{ __('librarian.inventory.location_known_only') }}</p></div><strong class="text-sm text-slate-600">{{ number_format($locationSummary['copies'], 0, ',', ' ') }} {{ __('librarian.inventory.copies') }}</strong></div>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['without_point','without_fund','without_room','without_section','without_shelf','without_storage_code','point_fund_conflicts'] as $metric)
                <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">{{ __('librarian.inventory.'.$metric) }}</dt><dd class="mt-1 text-xl font-bold text-primary">{{ number_format($locationSummary[$metric], 0, ',', ' ') }}</dd></div>
            @endforeach
        </dl>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('librarian.inventory.branch') }}</th><th>{{ __('librarian.inventory.fund') }}</th><th>{{ __('librarian.inventory.room') }}</th><th>{{ __('librarian.inventory.section') }}</th><th>{{ __('librarian.inventory.shelf') }}</th><th>{{ __('librarian.inventory.storage_code') }}</th><th>{{ __('librarian.inventory.copies') }}</th><th>{{ __('librarian.inventory.completeness') }}</th></tr></thead><tbody>@foreach($locationZones as $zone)<tr><td>{{ $zone->point ?: '—' }}</td><td>{{ $zone->fund ?: '—' }}</td><td>{{ $zone->room ?: '—' }}</td><td>{{ $zone->section ?: '—' }}</td><td>{{ $zone->shelf ?: '—' }}</td><td>{{ $zone->storage_code ?: '—' }}</td><td>{{ $zone->copies }}</td><td>{{ $zone->location_complete }} / {{ $zone->copies }}</td></tr>@endforeach</tbody></table></div>
    </section>
    @can('inventory.create')
    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">{{ __('librarian.inventory.new_session') }}</h2>
        <form method="POST" action="{{ route('librarian.inventory.store') }}" class="mt-4 grid gap-4 md:grid-cols-4">@csrf
            <label><span class="admin-label">{{ __('librarian.inventory.scope') }}</span><select class="admin-input" name="scope_type" required>@foreach(['all','branch','fund','sigla','service_point'] as $scope)<option value="{{ $scope }}" @selected(old('scope_type','branch')===$scope)>{{ __('librarian.inventory.scopes.'.$scope) }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.branch') }}</span><select class="admin-input" name="branch_id"><option value="">{{ __('librarian.inventory.any_branch') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.fund') }}</span><select class="admin-input" name="fund_id"><option value="">{{ __('librarian.inventory.all_funds') }}</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.storage_code') }}</span><input class="admin-input" name="storage_sigla" list="inventory-siglas" value="{{ old('storage_sigla') }}"><datalist id="inventory-siglas">@foreach($siglas as $sigla)<option value="{{ $sigla }}"></option>@endforeach</datalist></label>
            <label><span class="admin-label">{{ __('librarian.inventory.service_point') }}</span><input class="admin-input" name="service_point_code" list="inventory-points" value="{{ old('service_point_code') }}"><datalist id="inventory-points">@foreach($servicePoints as $point)<option value="{{ $point }}"></option>@endforeach</datalist></label>
            <label><span class="admin-label">{{ __('librarian.inventory.room') }}</span><input class="admin-input" name="room"></label>
            <label><span class="admin-label">{{ __('librarian.inventory.section') }}</span><input class="admin-input" name="section"></label>
            <label><span class="admin-label">{{ __('librarian.inventory.shelf') }}</span><input class="admin-input" name="shelf_range"></label>
            <label><span class="admin-label">{{ __('librarian.inventory.session_size') }}</span><select class="admin-input" name="pilot_limit"><option value="">{{ __('librarian.inventory.full_scope') }}</option>@foreach([10,20,50,100,500] as $size)<option value="{{ $size }}">{{ __('librarian.inventory.pilot_count',['count'=>$size]) }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.date') }}</span><input class="admin-input" type="date" name="inventory_date" value="{{ now()->toDateString() }}" required></label>
            <button class="admin-btn admin-btn-primary md:col-span-4" type="submit">{{ __('librarian.inventory.create') }}</button>
        </form>
    </section>
    @endcan
    <section class="admin-card overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('librarian.inventory.number') }}</th><th>{{ __('librarian.inventory.zone') }}</th><th>{{ __('librarian.inventory.status') }}</th><th>{{ __('librarian.inventory.expected') }}</th><th>{{ __('librarian.inventory.found') }}</th><th>{{ __('librarian.inventory.missing') }}</th></tr></thead><tbody>
        @forelse($sessions as $session)<tr><td><a class="font-semibold text-secondary" href="{{ route('librarian.inventory.show',$session) }}">{{ $session->session_number }}</a></td><td>{{ __('librarian.inventory.scopes.'.($session->scope_type ?: 'branch')) }} · {{ $session->branch?->name ?? __('librarian.inventory.any_branch') }} · {{ $session->fund?->name ?? $session->storage_sigla ?? $session->service_point_code ?? __('librarian.inventory.all_funds') }} · {{ $session->shelf_range ?: '—' }}</td><td>{{ __('librarian.inventory.statuses.'.$session->status) }}</td><td>{{ $session->expected_count }}</td><td>{{ $session->found_count }}</td><td>{{ $session->missing_count }}</td></tr>@empty<tr><td colspan="6">{{ __('librarian.inventory.empty') }}</td></tr>@endforelse
    </tbody></table>{{ $sessions->links() }}</section>
</div>
@endsection
