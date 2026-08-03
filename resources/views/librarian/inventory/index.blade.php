@extends('layouts.librarian')

@section('title', __('librarian.inventory.title'))

@section('content')
<div class="space-y-6">
    <header><p class="admin-kicker">{{ __('librarian.inventory.kicker') }}</p><h1 class="font-headline text-4xl text-primary">{{ __('librarian.inventory.title') }}</h1><p class="mt-2 text-slate-600">{{ __('librarian.inventory.description') }}</p></header>
    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">{{ __('librarian.inventory.new_session') }}</h2>
        <form method="POST" action="{{ route('librarian.inventory.store') }}" class="mt-4 grid gap-4 md:grid-cols-5">@csrf
            <label><span class="admin-label">{{ __('librarian.inventory.branch') }}</span><select class="admin-input" name="branch_id" required><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.fund') }}</span><select class="admin-input" name="fund_id"><option value="">{{ __('librarian.inventory.all_funds') }}</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('librarian.inventory.room') }}</span><input class="admin-input" name="room"></label>
            <label><span class="admin-label">{{ __('librarian.inventory.shelf') }}</span><input class="admin-input" name="shelf_range"></label>
            <label><span class="admin-label">{{ __('librarian.inventory.date') }}</span><input class="admin-input" type="date" name="inventory_date" value="{{ now()->toDateString() }}" required></label>
            <button class="admin-btn admin-btn-primary md:col-span-5" type="submit">{{ __('librarian.inventory.create') }}</button>
        </form>
    </section>
    <section class="admin-card overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('librarian.inventory.number') }}</th><th>{{ __('librarian.inventory.zone') }}</th><th>{{ __('librarian.inventory.status') }}</th><th>{{ __('librarian.inventory.expected') }}</th><th>{{ __('librarian.inventory.found') }}</th><th>{{ __('librarian.inventory.missing') }}</th></tr></thead><tbody>
        @forelse($sessions as $session)<tr><td><a class="font-semibold text-secondary" href="{{ route('librarian.inventory.show',$session) }}">{{ $session->session_number }}</a></td><td>{{ $session->branch?->name }} · {{ $session->fund?->name ?? __('librarian.inventory.all_funds') }} · {{ $session->shelf_range ?: '—' }}</td><td>{{ __('librarian.inventory.statuses.'.$session->status) }}</td><td>{{ $session->expected_count }}</td><td>{{ $session->found_count }}</td><td>{{ $session->missing_count }}</td></tr>@empty<tr><td colspan="6">{{ __('librarian.inventory.empty') }}</td></tr>@endforelse
    </tbody></table>{{ $sessions->links() }}</section>
</div>
@endsection
