@extends('layouts.member', ['title' => __('librarian.member.loans.title').' — '.__('common.app_name')])

@section('content')
  @include('member.partials.flash')

  <header class="mb-10 md:mb-14">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">
        {{ __('librarian.member.loans.count', ['count' => $loans->count()]) }}
      </span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">
      {{ __('librarian.member.loans.title') }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.loans.subtitle') }}
    </p>
    @unless ($renewalAllowed)
      <p class="mt-4 font-body text-sm text-on-surface-variant italic">{{ __('librarian.member.loans.renew_disabled') }}</p>
    @endunless
  </header>

  @if ($loans->isEmpty())
    <div class="bg-surface-container-lowest rounded-xl p-10 text-center">
      <span class="material-symbols-outlined text-outline-variant text-5xl mb-4">local_library</span>
      <p class="font-body text-base text-on-surface-variant mb-6">{{ __('librarian.member.loans.empty') }}</p>
      <a href="/catalog" class="inline-flex items-center gap-2 bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-[18px]">menu_book</span>
        <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
      </a>
    </div>
  @else
    <div class="flex flex-col space-y-6 md:space-y-8">
      @foreach ($loans as $loan)
        @php
          $copy = $loan->copy;
          $record = $copy?->bibliographicRecord;
          $overdueDays = $loan->overdueDays();
          $daysLeft = $loan->daysRemaining();
          $isOverdue = $overdueDays > 0;
          $renewalsLeft = $maxRenewals - (int) $loan->renewal_count;
          $renewalEligibility = $loan->getAttribute('renewal_eligibility') ?? ['allowed' => false, 'reason' => 'restricted'];
        @endphp
        <article class="bg-surface-container-lowest rounded-xl p-6 md:p-8 flex flex-col md:flex-row gap-6 md:gap-10 relative overflow-hidden {{ $isOverdue ? 'border-l-4 border-error' : '' }}">
          <div class="shrink-0">
            <div class="w-28 md:w-32 h-40 md:h-48 rounded-md bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary text-5xl">menu_book</span>
            </div>
          </div>

          <div class="flex-1 min-w-0 flex flex-col justify-between">
            <div>
              <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
                <div class="min-w-0">
                  <h2 class="font-headline text-2xl md:text-3xl text-primary leading-tight mb-1">
                    @if ($record !== null)
                      <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="hover:text-secondary transition-colors">{{ $record->title }}</a>
                    @else
                      {{ __('common.catalog.title_unknown') }}
                    @endif
                  </h2>
                  <p class="font-body text-base text-on-surface-variant font-medium">
                    {{ $record?->primary_author ?: __('librarian.member.common.unknown_author') }}
                  </p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full font-label text-xs tracking-widest uppercase {{ $isOverdue ? 'bg-error-container text-on-error-container' : 'bg-secondary-container/50 text-on-secondary-container' }}">
                  <span class="material-symbols-outlined text-[16px]">{{ $isOverdue ? 'running_with_errors' : 'schedule' }}</span>
                  @if ($isOverdue)
                    {{ __('librarian.circulation.overdue_days', ['count' => $overdueDays]) }}
                  @elseif ($daysLeft === 0)
                    {{ __('librarian.member.loans.due_today') }}
                  @else
                    {{ __('librarian.circulation.days_left', ['count' => $daysLeft]) }}
                  @endif
                </span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div>
                  <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.member.loans.issued_at') }}</p>
                  <p class="font-body text-sm text-on-surface">{{ $loan->issued_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <div>
                  <p class="font-label text-xs {{ $isOverdue ? 'text-error font-bold' : 'text-on-surface-variant' }} uppercase tracking-wider mb-0.5">{{ __('librarian.circulation.due_date') }}</p>
                  <p class="font-body text-sm {{ $isOverdue ? 'text-error font-semibold' : 'text-on-surface' }}">{{ $loan->due_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <div>
                  <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.copies.fields.inventory_number') }}</p>
                  <p class="font-body text-sm text-on-surface">{{ $copy?->inventory_number ?: '—' }}</p>
                </div>
                <div>
                  <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.copies.fields.branch') }}</p>
                  <p class="font-body text-sm text-on-surface">{{ $copy?->branch?->name ?: '—' }}</p>
                </div>
              </div>

              <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider">
                {{ __('librarian.member.loans.renewals', ['used' => (int) $loan->renewal_count, 'max' => $maxRenewals]) }}
                @if ($copy?->shelf_location)
                  · {{ __('librarian.copies.fields.shelf_location') }}: {{ $copy->shelf_location }}
                @endif
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-6">
              @if ($renewalAllowed && $renewalEligibility['allowed'])
                <form method="POST" action="{{ route('member.loans.renew', $loan) }}" onsubmit="this.querySelector('button').disabled=true; return confirm('{{ __('librarian.member.loans.renew_confirm') }}');">
                  @csrf
                  <input type="hidden" name="expected_due_at" value="{{ $loan->due_at?->toDateString() }}">
                  <button type="submit" class="px-6 py-2.5 rounded-md bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm transition-opacity hover:opacity-90 inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">autorenew</span>
                    <span>{{ __('librarian.circulation.renew') }}</span>
                  </button>
                </form>
              @elseif($renewalAllowed)
                <span class="font-body text-sm text-on-surface-variant">{{ __('librarian.member_portal.renew_blocked.'.$renewalEligibility['reason']) }}</span>
              @endif
              @if ($record !== null)
                <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="px-6 py-2.5 rounded-md font-body font-medium text-sm text-secondary ring-1 ring-outline-variant/20 hover:bg-surface-variant transition-colors inline-flex items-center gap-2">
                  <span class="material-symbols-outlined text-[18px]">description</span>
                  <span>{{ __('librarian.member.common.open_record') }}</span>
                </a>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection
