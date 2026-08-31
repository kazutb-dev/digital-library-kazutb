@extends('layouts.librarian')

@section('title', __('operations.ksu.conflicts'))

@section('content')
@php
    $groupResolveAction = \Illuminate\Support\Facades\Route::has('librarian.ksu.conflicts.resolve-group')
        ? route('librarian.ksu.conflicts.resolve-group')
        : '#';
    $range = static function ($from, $to): string {
        if ($from === null && $to === null) {
            return '—';
        }
        if ((string) $from === (string) $to || $to === null) {
            return (string) $from;
        }

        return $from.' — '.$to;
    };
    $dateRange = static function ($from, $to) use ($range): string {
        $format = static fn ($value): ?string => $value === null
            ? null
            : \Illuminate\Support\Carbon::parse($value)->format('d.m.Y');

        return $range($format($from), $format($to));
    };
@endphp
<div class="space-y-6">
    <header>
        <a class="text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.ksu.index') }}">← {{ __('operations.common.back') }}</a>
        <p class="admin-kicker mt-3">{{ __('operations.ksu.kicker') }}</p>
        <h1 class="font-headline text-4xl text-primary">{{ __('operations.ksu.conflicts') }}</h1>
        <p class="mt-2 max-w-3xl text-slate-600">{{ __('operations.ksu.conflicts_description') }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a class="admin-btn {{ $grouped ? 'admin-btn-primary' : 'admin-btn-secondary' }}" href="{{ route('librarian.ksu.conflicts', array_merge(request()->except(['page', 'groups_page']), ['view' => 'grouped'])) }}">{{ __('operations.ksu.grouped_view') }}</a>
            <a class="admin-btn {{ $grouped ? 'admin-btn-secondary' : 'admin-btn-primary' }}" href="{{ route('librarian.ksu.conflicts', array_merge(request()->except(['page', 'groups_page']), ['view' => 'individual'])) }}">{{ __('operations.ksu.individual_view') }}</a>
        </div>
    </header>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="admin-card">
        <form method="GET" action="{{ route('librarian.ksu.conflicts') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_12rem_12rem_12rem_auto]">
            <input type="hidden" name="view" value="{{ $grouped ? 'grouped' : 'individual' }}">
            <label><span class="admin-label">{{ __('operations.common.search') }}</span><input class="admin-input" type="search" name="q" value="{{ request('q') }}"></label>
            <label><span class="admin-label">{{ __('operations.common.status') }}</span><select class="admin-input" name="status">@foreach(['open','resolved','ignored'] as $status)<option value="{{ $status }}" @selected(request('status', 'open') === $status)>{{ $status }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('operations.ksu.kind') }}</span><select class="admin-input" name="kind"><option value="">{{ __('operations.common.all') }}</option>@foreach($kinds as $kind)<option value="{{ $kind }}" @selected(request('kind', $grouped ? 'unresolved_link' : '') === $kind)>{{ $kind }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('operations.ksu.book') }}</span><select class="admin-input" name="book"><option value="">{{ __('operations.common.all') }}</option>@foreach($books as $book)<option value="{{ $book->id }}" @selected((string) request('book') === (string) $book->id)>{{ $book->code }}</option>@endforeach</select></label>
            <button class="admin-btn admin-btn-secondary self-end" type="submit">{{ __('operations.common.filter') }}</button>
        </form>
    </section>

    @if($grouped)
        <section class="space-y-4">
            @forelse($groups as $group)
                <article class="admin-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">{{ __('operations.ksu.group_count', ['count' => number_format($group->conflict_count, 0, ',', ' ')]) }}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 font-mono text-sm font-bold text-primary">{{ $group->ksu_number_raw ?? __('operations.ksu.group_raw_empty') }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">{{ __('operations.ksu.group_preserves_raw') }}</p>
                        </div>
                        @if(! $group->valid_historical_number)
                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">{{ __('operations.ksu.invalid_historical_number') }}</span>
                        @endif
                    </div>

                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <div><dt class="admin-label">{{ __('operations.ksu.source_inventory_range') }}</dt><dd>{{ $range($group->source_inv_min, $group->source_inv_max) }}</dd></div>
                        <div><dt class="admin-label">{{ __('operations.ksu.source_document_range') }}</dt><dd>{{ $range($group->source_doc_min, $group->source_doc_max) }}</dd></div>
                        <div><dt class="admin-label">{{ __('operations.ksu.registration_date_range') }}</dt><dd>{{ $dateRange($group->registration_date_from, $group->registration_date_to) }}</dd></div>
                        <div><dt class="admin-label">{{ __('operations.ksu.queued_date_range') }}</dt><dd>{{ $dateRange($group->queued_at_from, $group->queued_at_to) }}</dd></div>
                    </dl>

                    <div class="mt-5 overflow-x-auto">
                        <h2 class="font-headline text-lg text-primary">{{ __('operations.ksu.examples') }}</h2>
                        <table class="admin-table mt-2 min-w-[720px]">
                            <thead><tr><th>ID</th><th>{{ __('operations.ksu.source_inventory') }}</th><th>{{ __('operations.ksu.source_document') }}</th><th>{{ __('operations.acquisitions.inventory_number') }}</th><th>{{ __('operations.common.date') }}</th><th>{{ __('operations.ksu.reason') }}</th></tr></thead>
                            <tbody>
                            @foreach($group->examples as $example)
                                @php($candidateCopy = $example->copy ?? $example->sourceCopy)
                                <tr>
                                    <td>#{{ $example->id }}</td>
                                    <td>{{ $example->source_inv_id ?? '—' }}</td>
                                    <td>{{ $example->source_doc_id ?? '—' }}</td>
                                    <td>{{ $candidateCopy?->inventory_number ?? '—' }}</td>
                                    <td>{{ $candidateCopy?->registration_date?->format('d.m.Y') ?? '—' }}</td>
                                    <td>{{ $example->reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(request('status', 'open') === 'open' && $canManage)
                        <form method="POST" action="{{ $groupResolveAction }}" class="mt-5 rounded-xl bg-slate-50 p-4">
                            @csrf
                            <input type="hidden" name="ksu_number_raw" value="{{ $group->ksu_number_raw }}">
                            <div class="grid gap-3 lg:grid-cols-[minmax(16rem,1fr)_minmax(20rem,2fr)]">
                                <label>
                                    <span class="admin-label">{{ __('operations.ksu.existing_entry') }}</span>
                                    <select class="admin-input" name="ksu_entry_id">
                                        <option value="">{{ __('operations.common.not_set') }}</option>
                                        @foreach($existingEntries as $entry)
                                            <option value="{{ $entry->id }}">{{ $entry->entry_number }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    <span class="admin-label">{{ __('operations.ksu.resolution_note') }}</span>
                                    <textarea class="admin-input" name="resolution_note" rows="3" required></textarea>
                                </label>
                            </div>
                            <p class="mt-3 text-xs text-slate-600">{{ __('operations.ksu.historical_strict_hint') }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button class="admin-btn admin-btn-primary" name="action" value="link_existing" type="submit" @disabled($existingEntries->isEmpty())>{{ __('operations.ksu.link_existing') }}</button>
                                @if($group->valid_historical_number)
                                    <button class="admin-btn admin-btn-secondary" name="action" value="create_historical" type="submit">{{ __('operations.ksu.create_historical') }}</button>
                                @endif
                                <button class="admin-btn admin-btn-secondary" name="action" value="ignore" type="submit">{{ __('operations.ksu.mark_source_error') }}</button>
                                <button class="admin-btn admin-btn-secondary" name="action" value="leave_unresolved" type="submit" formnovalidate>{{ __('operations.ksu.leave_unresolved') }}</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="admin-card text-center text-slate-500">{{ __('operations.ksu.no_groups') }}</div>
            @endforelse
        </section>
        <div>{{ $groups->links() }}</div>
    @else
        <section class="space-y-4">
            @forelse($conflicts as $conflict)
                <article class="admin-card">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,28rem)]">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">{{ $conflict->kind }}</span><span class="text-xs text-slate-500">#{{ $conflict->id }} · {{ $conflict->book?->code ?: '—' }}</span></div>
                            <dl class="mt-4 grid gap-3 sm:grid-cols-2"><div><dt class="admin-label">{{ __('operations.ksu.raw_number') }}</dt><dd>{{ $conflict->ksu_number_raw ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('operations.ksu.source_inventory') }}</dt><dd>{{ $conflict->source_inv_id ?: '—' }}</dd></div><div class="sm:col-span-2"><dt class="admin-label">{{ __('operations.ksu.reason') }}</dt><dd class="whitespace-pre-wrap">{{ $conflict->reason ?: '—' }}</dd></div></dl>
                        </div>
                        @if($conflict->status === 'open' && $canManage)
                            <form method="POST" action="{{ route('librarian.ksu.conflicts.resolve', $conflict) }}" class="rounded-xl bg-slate-50 p-4">
                                @csrf
                                <label><span class="admin-label">{{ __('operations.ksu.copy_id') }}</span><input class="admin-input" type="number" min="1" name="book_copy_id" value="{{ $conflict->book_copy_id }}"></label>
                                <label class="mt-3 block"><span class="admin-label">{{ __('operations.ksu.resolution_note') }}</span><textarea class="admin-input" name="resolution_note" rows="3" required></textarea></label>
                                <div class="mt-3 flex flex-wrap gap-2"><button class="admin-btn admin-btn-primary" name="status" value="resolved" type="submit">{{ __('operations.ksu.resolve') }}</button><button class="admin-btn admin-btn-secondary" name="status" value="ignored" type="submit">{{ __('operations.ksu.ignore') }}</button></div>
                            </form>
                        @else
                            <div class="rounded-xl bg-slate-50 p-4"><p class="font-semibold">{{ $conflict->status }}</p><p class="mt-2 text-sm text-slate-600">{{ $conflict->resolution_note }}</p></div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="admin-card text-center text-slate-500">{{ __('operations.ksu.no_conflicts') }}</div>
            @endforelse
        </section>
        <div>{{ $conflicts->links() }}</div>
    @endif
</div>
@endsection
