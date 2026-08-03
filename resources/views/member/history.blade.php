@extends('layouts.member', ['title' => __('librarian.member.history.title').' — '.__('common.app_name')])

@section('content')
  @include('member.partials.flash')

  <header class="mb-10 md:mb-14">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">
        {{ __('librarian.member.history.summary', ['returned' => $totalReturned, 'lost' => $totalLost]) }}
      </span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">
      {{ __('librarian.member.history.title') }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.history.subtitle') }}
    </p>
  </header>

  <form method="GET" class="mb-10 grid gap-4 rounded-xl bg-surface-container-lowest p-5 sm:grid-cols-2 lg:grid-cols-5" aria-label="{{ __('librarian.member.history.filters') }}">
    <label class="text-sm">{{ __('librarian.member.history.from') }}<input class="mt-1 w-full rounded-md border-outline-variant" type="date" name="from" value="{{ request('from') }}"></label>
    <label class="text-sm">{{ __('librarian.member.history.to') }}<input class="mt-1 w-full rounded-md border-outline-variant" type="date" name="to" value="{{ request('to') }}"></label>
    <label class="text-sm">{{ __('librarian.member.history.status') }}<select class="mt-1 w-full rounded-md border-outline-variant" name="status"><option value="">{{ __('librarian.member.history.all') }}</option><option value="returned" @selected(request('status')==='returned')>{{ __('librarian.member.history.returned_on_time') }}</option><option value="lost" @selected(request('status')==='lost')>{{ __('librarian.member.history.lost') }}</option></select></label>
    <label class="flex items-end gap-2 pb-3 text-sm"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> {{ __('librarian.member.history.only_overdue') }}</label>
    <button class="self-end rounded-md bg-primary px-5 py-3 text-sm font-semibold text-on-primary" type="submit">{{ __('librarian.member.history.apply_filters') }}</button>
  </form>

  @if($pastReservations->isNotEmpty())
    <section class="mb-10" aria-labelledby="reservation-history-title">
      <h2 id="reservation-history-title" class="mb-4 font-headline text-2xl text-primary">{{ __('librarian.member.reservations.past') }}</h2>
      <div class="grid gap-3 md:grid-cols-2">
        @foreach($pastReservations as $reservation)
          <article class="rounded-xl bg-surface-container-lowest p-5">
            <div class="flex justify-between gap-4"><h3 class="font-headline text-lg text-primary">{{ $reservation->bibliographicRecord?->title ?: __('common.catalog.title_unknown') }}</h3><span class="text-xs uppercase text-on-surface-variant">{{ __('librarian.reservations.statuses.'.$reservation->status) }}</span></div>
            <p class="mt-2 text-xs text-on-surface-variant">{{ $reservation->created_at?->format('d.m.Y') }}@if($reservation->pickupBranch) · {{ $reservation->pickupBranch->name }}@endif</p>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  @if ($loans->isEmpty())
    <div class="bg-surface-container-lowest rounded-xl p-10 text-center">
      <span class="material-symbols-outlined text-outline-variant text-5xl mb-4">history</span>
      <p class="font-body text-base text-on-surface-variant">{{ __('librarian.member.history.empty') }}</p>
    </div>
  @else
    <div class="space-y-6">
      @foreach ($loans as $loan)
        @php
          $copy = $loan->copy;
          $record = $copy?->bibliographicRecord;
          $lateDays = ($loan->due_at !== null && $loan->returned_at !== null && $loan->returned_at->gt($loan->due_at))
              ? (int) $loan->due_at->startOfDay()->diffInDays($loan->returned_at->startOfDay())
              : 0;
          $isLost = $loan->status === 'lost';
        @endphp
        <article class="bg-surface-container-low p-6 md:p-8 rounded-lg flex flex-col md:flex-row gap-6 md:gap-8 {{ $isLost ? 'border-l-4 border-error' : ($lateDays > 0 ? 'border-l-4 border-error/40' : '') }}">
          <div class="flex-shrink-0 w-full md:w-28 h-32 md:h-40 rounded-DEFAULT bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-4xl opacity-80">menu_book</span>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap justify-between items-start gap-4 mb-2">
              <h2 class="font-headline text-xl md:text-2xl text-primary leading-tight">
                @if ($record !== null)
                  <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="hover:text-secondary transition-colors">{{ $record->title }}</a>
                @else
                  {{ __('common.catalog.title_unknown') }}
                @endif
              </h2>
              @if ($isLost)
                <span class="inline-flex items-center px-3 py-1 bg-error-container text-on-error-container rounded-full font-label text-xs font-bold whitespace-nowrap">
                  {{ __('librarian.member.history.lost') }}
                </span>
              @elseif ($lateDays > 0)
                <span class="inline-flex items-center px-3 py-1 bg-error-container/50 text-on-error-container rounded-full font-label text-xs whitespace-nowrap">
                  {{ __('librarian.member.history.returned_late', ['count' => $lateDays]) }}
                </span>
              @else
                <span class="inline-flex items-center px-3 py-1 bg-secondary-container/40 text-on-secondary-container rounded-full font-label text-xs whitespace-nowrap">
                  {{ __('librarian.member.history.returned_on_time') }}
                </span>
              @endif
            </div>

            <p class="font-body text-base text-on-surface-variant mb-1">
              {{ $record?->primary_author ?: __('librarian.member.common.unknown_author') }}
            </p>
            <p class="font-label text-xs text-on-surface-variant/80 mb-4 uppercase tracking-wider">
              {{ $record?->publisher ?: '—' }}@if ($record?->publication_year), {{ $record->publication_year }}@endif
              · {{ __('librarian.copies.fields.inventory_number') }}: {{ $copy?->inventory_number ?: '—' }}
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4 max-w-2xl">
              <div>
                <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.member.loans.issued_at') }}</p>
                <p class="font-body text-sm text-on-surface">{{ $loan->issued_at?->format('d.m.Y') ?? '—' }}</p>
              </div>
              <div>
                <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.circulation.due_date') }}</p>
                <p class="font-body text-sm text-on-surface">{{ $loan->due_at?->format('d.m.Y') ?? '—' }}</p>
              </div>
              <div>
                <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.member.history.returned_at') }}</p>
                <p class="font-body text-sm text-on-surface">{{ $loan->returned_at?->format('d.m.Y') ?? '—' }}</p>
              </div>
              <div>
                <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.copies.fields.branch') }}</p>
                <p class="font-body text-sm text-on-surface">{{ $copy?->branch?->name ?: '—' }}</p>
              </div>
            </div>

            @if ($loan->fines->isNotEmpty())
              <div class="rounded-lg bg-surface p-4">
                <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-2">{{ __('librarian.member.history.linked_fines') }}</p>
                <ul class="space-y-1">
                  @foreach ($loan->fines as $fine)
                    <li class="flex flex-wrap items-center justify-between gap-3 font-body text-sm">
                      <span class="text-on-surface">
                        {{ __('librarian.fines.reasons.'.$fine->reason) }}
                        <span class="text-on-surface-variant text-xs">· {{ $fine->charged_at?->format('d.m.Y') ?? '—' }}</span>
                      </span>
                      <span class="inline-flex items-center gap-3">
                        <strong class="text-primary">{{ number_format((float) $fine->amount, 0, ',', ' ') }} ₸</strong>
                        <span class="font-label text-[11px] uppercase tracking-wider {{ $fine->status === 'pending' ? 'text-error' : 'text-on-surface-variant' }}">
                          {{ __('librarian.fines.statuses.'.$fine->status) }}
                        </span>
                      </span>
                    </li>
                  @endforeach
                </ul>
              </div>
            @endif
          </div>
        </article>
      @endforeach
    </div>

    <div class="mt-10">
      {{ $loans->links() }}
    </div>
  @endif

  <footer class="mt-14 pt-8 border-t border-outline-variant/20">
    <p class="font-body text-sm text-on-surface-variant max-w-2xl">
      <a href="{{ route('member.fines') }}" class="text-secondary hover:text-primary transition-colors">{{ __('librarian.member.fines.title') }}</a>
      ·
      <a href="{{ route('member.messages') }}" class="text-secondary hover:text-primary transition-colors">{{ __('librarian.member.notifications.links.messages') }}</a>
    </p>
  </footer>
@endsection
