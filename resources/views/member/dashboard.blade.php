@extends('layouts.member', ['title' => __('librarian.member.common.eyebrow').' — '.__('common.app_name')])

@php
  $memberReader = $memberReader ?? session('library.user');
  $displayName = $memberReader['display_name'] ?? ($memberReader['name'] ?? ($memberReader['login'] ?? auth()->user()?->name));
  $memberCapabilities = $memberCapabilities ?? [];

  $services = collect([
      ['label' => __('librarian.member.dashboard.services.catalog'), 'href' => '/catalog', 'icon' => 'menu_book'],
      ['label' => __('librarian.member.dashboard.services.resources'), 'href' => '/resources', 'icon' => 'travel_explore'],
      ['label' => __('librarian.member.dashboard.services.contacts'), 'href' => route('member.messages'), 'icon' => 'contact_support', 'visible' => (bool) ($memberCapabilities['messages'] ?? false)],
  ])->filter(fn (array $service): bool => (bool) ($service['visible'] ?? true))->values();

  $stats = collect([
      ['label' => __('librarian.member.dashboard.stats.loans'), 'value' => $openLoans->count(), 'href' => route('member.loans'), 'tone' => 'plain', 'visible' => (bool) ($memberCapabilities['loans'] ?? false)],
      ['label' => __('librarian.member.dashboard.stats.overdue'), 'value' => $overdueCount, 'href' => route('member.loans'), 'tone' => $overdueCount > 0 ? 'error' : 'plain', 'visible' => (bool) ($memberCapabilities['loans'] ?? false)],
      ['label' => __('librarian.member.dashboard.stats.reservations'), 'value' => $activeReservations->count(), 'href' => route('member.reservations'), 'tone' => 'plain', 'visible' => (bool) ($memberCapabilities['reservations'] ?? false)],
      ['label' => __('librarian.member.dashboard.stats.fines'), 'value' => number_format($pendingFinesTotal, 0, ',', ' '), 'href' => route('member.fines'), 'tone' => $pendingFinesTotal > 0 ? 'error' : 'plain', 'visible' => (bool) ($memberCapabilities['fines'] ?? false)],
      ['label' => __('librarian.member.dashboard.stats.unread'), 'value' => $unreadNotifications, 'href' => route('member.notifications'), 'tone' => $unreadNotifications > 0 ? 'secondary' : 'plain', 'visible' => (bool) ($memberCapabilities['notifications'] ?? false)],
      ['label' => __('librarian.member.dashboard.stats.shortlist'), 'value' => $shortlistTotal, 'href' => route('member.collections.index'), 'tone' => 'plain', 'visible' => (bool) ($memberCapabilities['collections'] ?? false)],
  ])->filter(fn (array $stat): bool => (bool) ($stat['visible'] ?? false))->values();
@endphp

@section('content')
  @include('member.partials.flash')

  <!-- Hero -->
  <header class="mb-10 md:mb-14">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">{{ __('librarian.member.common.eyebrow') }}</span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] leading-tight text-primary tracking-tight mb-3">
      {{ __('librarian.member.dashboard.title', ['name' => $displayName]) }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.dashboard.subtitle') }}
    </p>
  </header>

  @if ($blocked)
    <div class="mb-8 flex items-start gap-3 rounded-xl bg-error-container px-5 py-4 text-on-error-container" role="alert">
      <span class="material-symbols-outlined text-[20px] mt-0.5">block</span>
      <div class="font-body text-sm leading-relaxed">
        <p>{{ __('librarian.member.dashboard.blocked_notice') }}</p>
        @if ($profile->block_reason)
          <p class="mt-1 opacity-80">{{ __('librarian.circulation.block_reason') }}: {{ $profile->block_reason }}</p>
        @endif
      </div>
    </div>
  @elseif ($overdueBlocked)
    <div class="mb-8 flex items-start gap-3 rounded-xl bg-error-container/60 px-5 py-4 text-on-error-container" role="alert">
      <span class="material-symbols-outlined text-[20px] mt-0.5">running_with_errors</span>
      <p class="font-body text-sm leading-relaxed">{{ __('librarian.member.dashboard.overdue_notice') }}</p>
    </div>
  @endif

  @if($restrictions->isNotEmpty() || $readyReservationsCount > 0 || $dueSoonCount > 0)
    <section class="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="attention-title">
      <h2 id="attention-title" class="font-headline text-xl text-amber-950">{{ __('librarian.member.dashboard.priority') }}</h2>
      <ul class="mt-3 space-y-2 text-sm text-amber-950">
        @foreach($restrictions as $restriction)<li class="flex gap-2"><span aria-hidden="true">•</span><span>{{ $restriction['message'] }}</span></li>@endforeach
        @if($readyReservationsCount>0)<li><a class="font-semibold underline" href="{{ route('member.reservations') }}">{{ __('librarian.reservations.statuses.ready_for_pickup') }}: {{ $readyReservationsCount }}</a></li>@endif
        @if($dueSoonCount>0 && $overdueCount===0)<li><a class="font-semibold underline" href="{{ route('member.loans') }}">{{ __('librarian.member.dashboard.stats.loans') }}: {{ $dueSoonCount }}</a></li>@endif
      </ul>
    </section>
  @endif

  <!-- Reader ticket -->
  @if ($memberCapabilities['card'] ?? false)
  <section class="mb-10 bg-surface-container-lowest rounded-xl p-6 md:p-8" aria-label="{{ __('librarian.member.dashboard.ticket_card') }}">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div>
        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest mb-1">{{ __('librarian.circulation.ticket') }}</p>
        <p class="font-headline text-2xl text-primary">{{ $profile->ticket_number ?: '—' }}</p>
      </div>
      <div>
        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest mb-1">{{ __('librarian.circulation.category') }}</p>
        <p class="font-body text-base text-on-surface font-medium">
          {{ __('librarian.circulation.reader_categories.'.$profile->category) }}
        </p>
      </div>
      <div>
        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest mb-1">{{ __('librarian.circulation.reader_status') }}</p>
        <p class="font-body text-base font-medium {{ $blocked ? 'text-error' : 'text-secondary' }}">
          {{ __('librarian.circulation.reader_statuses.'.$profile->status) }}
        </p>
      </div>
      @if ($memberCapabilities['loans'] ?? false)
      <div>
        <p class="font-label text-xs text-on-surface-variant uppercase tracking-widest mb-1">{{ __('librarian.circulation.limits') }}</p>
        <p class="font-body text-base text-on-surface font-medium">
          {{ __('librarian.member.dashboard.limit_hint', ['used' => $openLoans->count(), 'max' => $maxLoans]) }}
        </p>
        <p class="font-body text-xs text-on-surface-variant mt-0.5">
          {{ __('librarian.circulation.loans_remaining', ['count' => $loansRemaining]) }}
        </p>
      </div>
      @endif
    </div>
  </section>
  @endif

  <!-- Counters -->
  <section class="mb-10 grid grid-cols-2 lg:grid-cols-6 gap-4" aria-label="{{ __('librarian.member.common.eyebrow') }}">
    @foreach ($stats as $stat)
      @php
        $valueTone = match ($stat['tone']) {
            'error' => 'text-error',
            'secondary' => 'text-secondary',
            default => 'text-primary',
        };
      @endphp
      <a href="{{ $stat['href'] }}" class="bg-surface-container-lowest rounded-xl px-5 py-5 hover:bg-surface-container-high transition-colors duration-300 block">
        <p class="font-headline text-3xl {{ $valueTone }} leading-none mb-2">{{ $stat['value'] }}</p>
        <p class="font-label text-[11px] text-on-surface-variant uppercase tracking-widest leading-snug">{{ $stat['label'] }}</p>
      </a>
    @endforeach
  </section>

  <div class="grid grid-cols-12 gap-6 md:gap-10">

    <!-- Nearest due date -->
    @if ($memberCapabilities['loans'] ?? false)
    <section class="col-span-12 lg:col-span-8 bg-surface-container-lowest rounded-xl p-8 md:p-10 flex flex-col justify-between">
      <span class="font-label text-xs text-secondary uppercase tracking-widest font-semibold block mb-4">{{ __('librarian.member.dashboard.priority') }}</span>

      @if ($priorityLoan === null)
        <p class="font-body text-on-surface-variant leading-relaxed mb-6">{{ __('librarian.member.dashboard.priority_empty') }}</p>
        <div>
          <a href="/catalog" class="bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md transition-opacity hover:opacity-90 inline-flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">menu_book</span>
            <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
          </a>
        </div>
      @else
        @php
          $record = $priorityLoan->copy?->bibliographicRecord;
          $overdueDays = $priorityLoan->overdueDays();
          $daysLeft = $priorityLoan->daysRemaining();
        @endphp
        <div class="flex items-start justify-between gap-6 mb-8">
          <div class="min-w-0">
            <h2 class="font-headline text-2xl md:text-3xl text-primary mb-2 leading-tight">
              {{ $record?->title ?: __('common.catalog.title_unknown') }}
            </h2>
            <p class="font-body text-on-surface-variant text-sm">
              {{ $record?->primary_author ?: __('librarian.member.common.unknown_author') }}
            </p>
            <p class="font-body text-on-surface-variant text-xs mt-1">
              {{ $record?->publisher ?: '—' }}@if ($record?->publication_year), {{ $record->publication_year }}@endif
              · {{ __('librarian.copies.fields.inventory_number') }}: {{ $priorityLoan->copy?->inventory_number ?: '—' }}
            </p>
          </div>
          <div class="shrink-0 px-4 py-2 rounded-md {{ $overdueDays > 0 ? 'bg-error-container' : 'bg-secondary-container/50' }}">
            <p class="font-label text-[11px] uppercase tracking-widest {{ $overdueDays > 0 ? 'text-on-error-container' : 'text-on-secondary-container' }} mb-0.5">
              {{ __('librarian.circulation.due_date') }}
            </p>
            <p class="font-body text-sm font-semibold {{ $overdueDays > 0 ? 'text-on-error-container' : 'text-on-secondary-container' }}">
              {{ $priorityLoan->due_at?->format('d.m.Y') ?? '—' }}
            </p>
            <p class="font-body text-xs {{ $overdueDays > 0 ? 'text-on-error-container' : 'text-on-secondary-container' }}">
              @if ($overdueDays > 0)
                {{ __('librarian.circulation.overdue_days', ['count' => $overdueDays]) }}
              @else
                {{ __('librarian.circulation.days_left', ['count' => $daysLeft]) }}
              @endif
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
          <a href="{{ route('member.loans') }}" class="bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md transition-opacity hover:opacity-90 inline-flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">event_repeat</span>
            <span>{{ __('librarian.member.loans.title') }}</span>
          </a>
          <a href="/catalog" class="text-secondary font-body font-medium text-sm px-6 py-3 rounded-md ring-1 ring-outline-variant/20 hover:bg-surface-variant transition-colors inline-flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">menu_book</span>
            <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
          </a>
        </div>
      @endif
    </section>
    @endif

    <!-- Library services -->
    <section class="col-span-12 lg:col-span-4 bg-primary-container rounded-xl p-8 md:p-10 text-on-primary-container flex flex-col">
      <h3 class="font-headline text-2xl text-on-primary mb-8 tracking-tight">{{ __('librarian.member.dashboard.sections.services') }}</h3>
      <ul class="space-y-5 flex-1">
        @foreach ($services as $node)
          <li>
            <a href="{{ $node['href'] }}" class="flex items-center justify-between group pb-2 border-b border-on-primary-container/20 last:border-transparent">
              <span class="font-body text-on-primary font-medium inline-flex items-center gap-3 group-hover:text-secondary-fixed transition-colors">
                <span class="material-symbols-outlined text-[20px]">{{ $node['icon'] }}</span>
                <span>{{ $node['label'] }}</span>
              </span>
              <span class="material-symbols-outlined text-on-primary-container group-hover:text-secondary-fixed transition-transform transform group-hover:translate-x-1">arrow_forward</span>
            </a>
          </li>
        @endforeach
      </ul>
    </section>

    <!-- Active reservations -->
    @if ($memberCapabilities['reservations'] ?? false)
    <section class="col-span-12 lg:col-span-7 mt-2">
      <div class="flex items-end justify-between mb-5">
        <h3 class="font-headline text-2xl md:text-3xl text-primary">{{ __('librarian.member.dashboard.sections.reservations') }}</h3>
        <a href="{{ route('member.reservations') }}" class="font-body text-sm text-secondary font-medium hover:underline inline-flex items-center gap-1">
          {{ __('librarian.member.common.view_all') }} <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </a>
      </div>
      @if ($activeReservations->isEmpty())
        <p class="bg-surface-container-lowest rounded-xl p-6 font-body text-sm text-on-surface-variant">
          {{ __('librarian.member.dashboard.reservations_empty') }}
        </p>
      @else
        <ul class="space-y-3">
          @foreach ($activeReservations->take(4) as $reservation)
            <li class="bg-surface-container-lowest rounded-xl p-5 flex items-start justify-between gap-4">
              <div class="min-w-0">
                <p class="font-headline text-lg text-primary leading-snug">
                  {{ $reservation->bibliographicRecord?->title ?: __('common.catalog.title_unknown') }}
                </p>
                <p class="font-body text-xs text-on-surface-variant mt-0.5">
                  {{ $reservation->bibliographicRecord?->primary_author ?: __('librarian.member.common.unknown_author') }}
                </p>
                <p class="font-body text-xs text-on-surface-variant mt-1">
                  @if ($reservation->queue_position !== null)
                    {{ __('librarian.reservations.queue_position', ['position' => $reservation->queue_position]) }}
                  @elseif ($reservation->expires_at !== null)
                    {{ __('librarian.reservations.expires_at') }}: {{ $reservation->expires_at->format('d.m.Y') }}
                  @else
                    {{ __('librarian.reservations.assigned_copy') }}: {{ $reservation->assignedCopy?->inventory_number ?: __('librarian.reservations.no_copy_assigned') }}
                  @endif
                </p>
                @if ($reservation->assignedCopy)
                  <p class="font-body text-xs font-semibold text-on-surface-variant mt-1">
                    {{ __('librarian.copies.fields.inventory_number') }}: {{ $reservation->assignedCopy->inventory_number }}
                  </p>
                @endif
              </div>
              <span class="shrink-0 inline-flex items-center px-3 py-1 rounded-full font-label text-[11px] tracking-widest uppercase {{ $reservation->status === 'ready_for_pickup' ? 'bg-secondary-container/50 text-on-secondary-container' : 'bg-surface-variant text-on-surface-variant' }}">
                {{ __('librarian.reservations.statuses.'.$reservation->status) }}
              </span>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
    @endif

    <!-- Recent notifications -->
    @if ($memberCapabilities['notifications'] ?? false)
    <section class="col-span-12 lg:col-span-5 mt-2">
      <div class="flex items-end justify-between mb-5">
        <h3 class="font-headline text-2xl md:text-3xl text-primary">{{ __('librarian.member.dashboard.sections.notifications') }}</h3>
        <a href="{{ route('member.notifications') }}" class="font-body text-sm text-secondary font-medium hover:underline inline-flex items-center gap-1">
          {{ __('librarian.member.common.view_all') }} <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </a>
      </div>
      @if ($recentNotifications->isEmpty())
        <p class="bg-surface-container-lowest rounded-xl p-6 font-body text-sm text-on-surface-variant">
          {{ __('librarian.member.dashboard.notifications_empty') }}
        </p>
      @else
        <ul class="space-y-3">
          @foreach ($recentNotifications as $notification)
            <li class="bg-surface-container-lowest rounded-xl p-5 flex items-start gap-3 {{ $notification->read_at === null ? 'border-l-4 border-secondary' : '' }}">
              <span class="material-symbols-outlined text-[20px] text-on-surface-variant mt-0.5">notifications</span>
              <div class="min-w-0">
                <p class="font-body text-sm text-primary font-semibold leading-snug">{{ $notification->localizedTitle() }}</p>
                @if ($notification->localizedBody())
                  <p class="font-body text-xs text-on-surface-variant mt-1 leading-relaxed">{{ \Illuminate\Support\Str::limit($notification->localizedBody(), 140) }}</p>
                @endif
                <p class="font-label text-[11px] text-outline uppercase tracking-wider mt-2">{{ $notification->created_at?->format('d.m.Y') ?? '—' }}</p>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
    @endif

    <!-- Shortlist preview -->
    @if (($memberCapabilities['collections'] ?? false) && ($memberCapabilities['shortlist'] ?? false))
    <section class="col-span-12 mt-4">
      <div class="flex items-end justify-between mb-5">
        <h3 class="font-headline text-2xl md:text-3xl text-primary">{{ __('librarian.member.dashboard.sections.shortlist') }}</h3>
        <a href="{{ route('member.collections.index') }}" class="font-body text-sm text-secondary font-medium hover:underline inline-flex items-center gap-1">
          {{ __('librarian.member.common.view_all') }} <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @forelse ($shortlistItems as $item)
          <article class="bg-surface-container-lowest rounded-xl p-6 flex flex-col">
            <div class="w-full h-32 rounded-md mb-5 bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary text-4xl">menu_book</span>
            </div>
            <h4 class="font-headline text-lg text-primary leading-tight mb-2">{{ $item['title'] ?? __('common.catalog.title_unknown') }}</h4>
            <p class="font-body text-xs text-on-surface-variant mb-4">{{ $item['author'] ?: __('librarian.member.common.unknown_author') }}</p>
            <span class="mt-auto font-label text-xs font-semibold {{ $item['record'] !== null ? 'text-secondary' : 'text-outline' }} uppercase tracking-wider">
              @if ($item['record'] !== null)
                {{ __('librarian.member.shortlist.available_copies', ['count' => $item['available_copies'] ?? 0]) }}
              @else
                {{ __('librarian.member.shortlist.types.'.($item['type'] ?? 'book')) }}
              @endif
            </span>
          </article>
        @empty
          <p class="col-span-full bg-surface-container-lowest rounded-xl p-6 font-body text-sm text-on-surface-variant">
            {{ __('librarian.member.dashboard.shortlist_empty') }}
          </p>
        @endforelse
        <a href="/catalog" class="bg-surface-container-lowest rounded-xl p-6 hover:bg-surface-container-high transition-colors duration-300 flex items-center justify-center border-2 border-dashed border-outline-variant/30 hover:border-secondary/50 min-h-[12rem]">
          <div class="text-center">
            <span class="material-symbols-outlined text-outline-variant text-4xl mb-2">add_circle</span>
            <p class="font-body text-sm font-medium text-on-surface-variant">{{ __('librarian.member.common.back_to_catalog') }}</p>
          </div>
        </a>
      </div>
    </section>
    @endif

    @if($recommendations->isNotEmpty())
      <section class="col-span-12 mt-4" aria-labelledby="recommendations-title">
        <div class="flex items-end justify-between mb-5">
          <div>
            <h3 id="recommendations-title" class="font-headline text-2xl md:text-3xl text-primary">{{ __('librarian.member.dashboard.sections.recommendations') }}</h3>
            <p class="mt-1 text-sm text-on-surface-variant">{{ __('librarian.member.dashboard.recommendations_hint') }}</p>
          </div>
          <a href="{{ route('member.search') }}" class="font-body text-sm text-secondary font-medium hover:underline">{{ __('librarian.member_portal.search.title') }}</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          @foreach($recommendations as $record)
            <article class="rounded-xl bg-surface-container-lowest p-6">
              <p class="mb-2 font-label text-[11px] uppercase tracking-widest text-secondary">{{ __('librarian.member.dashboard.recommendation_reasons.'.$record->recommendation_reason) }}</p>
              <h4 class="font-headline text-xl text-primary"><a class="hover:text-secondary" href="/book/{{ $record->isbn ?: $record->getKey() }}">{{ $record->title }}</a></h4>
              <p class="mt-1 text-sm text-on-surface-variant">{{ $record->primary_author ?: __('librarian.member.common.unknown_author') }}</p>
              <p class="mt-4 text-xs font-semibold text-secondary">{{ __('librarian.member.shortlist.available_copies', ['count' => $record->available_copies_count]) }}</p>
            </article>
          @endforeach
        </div>
      </section>
    @endif

  </div>
@endsection
