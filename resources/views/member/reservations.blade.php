@extends('layouts.member', ['title' => __('librarian.member.reservations.title').' — '.__('common.app_name')])

@php
  $statusTone = [
      'ready_for_pickup' => 'bg-secondary-container/50 text-on-secondary-container',
      'confirmed' => 'bg-primary-fixed text-on-primary-fixed',
      'pending' => 'bg-surface-variant text-on-surface-variant border border-outline-variant/20',
      'fulfilled' => 'bg-surface-variant text-on-surface-variant',
      'cancelled' => 'bg-surface-variant text-on-surface-variant',
      'expired' => 'bg-error-container/50 text-on-error-container',
  ];
  $statusIcon = [
      'ready_for_pickup' => 'check_circle',
      'confirmed' => 'how_to_reg',
      'pending' => 'pending',
      'fulfilled' => 'outbox',
      'cancelled' => 'cancel',
      'expired' => 'timer_off',
  ];
@endphp

@section('content')
  @include('member.partials.flash')

  <header class="mb-10 md:mb-14">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">
        {{ __('librarian.member.reservations.count', ['count' => $activeReservations->count()]) }}
      </span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">
      {{ __('librarian.member.reservations.title') }}
    </h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      {{ __('librarian.member.reservations.subtitle', ['days' => $pickupHoldDays]) }}
    </p>
  </header>

  <!-- Active -->
  <section class="mb-14">
    <h2 class="font-headline text-2xl text-primary mb-6">{{ __('librarian.member.reservations.active') }}</h2>

    @if ($activeReservations->isEmpty())
      <div class="bg-surface-container-lowest rounded-xl p-10 text-center">
        <span class="material-symbols-outlined text-outline-variant text-5xl mb-4">bookmark_manager</span>
        <p class="font-body text-base text-on-surface-variant mb-6">{{ __('librarian.member.reservations.empty_active') }}</p>
        <a href="/catalog" class="inline-flex items-center gap-2 bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md hover:opacity-90 transition-opacity">
          <span class="material-symbols-outlined text-[18px]">menu_book</span>
          <span>{{ __('librarian.member.common.back_to_catalog') }}</span>
        </a>
      </div>
    @else
      <div class="flex flex-col space-y-6 md:space-y-8">
        @foreach ($activeReservations as $reservation)
          @php $record = $reservation->bibliographicRecord; @endphp
          <article class="bg-surface-container-lowest rounded-xl p-6 md:p-8 flex flex-col md:flex-row gap-6 md:gap-10 relative overflow-hidden">
            @if ($reservation->status === 'ready_for_pickup')
              <div class="absolute top-0 left-0 w-1.5 h-full bg-secondary opacity-80"></div>
            @endif

            <div class="shrink-0">
              <div class="w-28 md:w-32 h-40 md:h-48 rounded-md bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
                <span class="material-symbols-outlined text-on-primary text-5xl">menu_book</span>
              </div>
            </div>

            <div class="flex-1 min-w-0 flex flex-col justify-between">
              <div>
                <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full {{ $statusTone[$reservation->status] ?? 'bg-surface-variant text-on-surface-variant' }} font-label text-xs tracking-widest uppercase">
                    <span class="material-symbols-outlined text-[16px]">{{ $statusIcon[$reservation->status] ?? 'bookmark' }}</span>
                    <span>{{ __('librarian.reservations.statuses.'.$reservation->status) }}</span>
                  </span>
                  <span class="font-label text-xs text-on-surface-variant tracking-wider uppercase">
                    {{ __('librarian.member.reservations.created_at') }}: {{ $reservation->created_at?->format('d.m.Y') ?? '—' }}
                  </span>
                </div>

                <h3 class="font-headline text-2xl md:text-3xl text-primary leading-tight mb-1">
                  @if ($record !== null)
                    <a href="/book/{{ $record->isbn ?: $record->getKey() }}" class="hover:text-secondary transition-colors">{{ $record->title }}</a>
                  @else
                    {{ __('common.catalog.title_unknown') }}
                  @endif
                </h3>
                <p class="font-body text-base text-on-surface-variant font-medium mb-5">
                  {{ $record?->primary_author ?: __('librarian.member.common.unknown_author') }}
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                  <div>
                    <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">{{ __('librarian.reservations.assigned_copy') }}</p>
                    <p class="font-body text-sm text-on-surface">
                      {{ $reservation->assignedCopy?->inventory_number ?: __('librarian.reservations.no_copy_assigned') }}
                    </p>
                    @if ($reservation->assignedCopy?->branch?->name)
                      <p class="font-body text-xs text-on-surface-variant">{{ $reservation->assignedCopy->branch->name }}</p>
                    @endif
                  </div>
                  <div>
                    <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">
                      {{ \Illuminate\Support\Str::of(__('librarian.reservations.queue_position', ['position' => '']))->trim()->rtrim(':')->trim() }}
                    </p>
                    <p class="font-body text-sm text-on-surface">{{ $reservation->queue_position ?? '—' }}</p>
                    @php $waiting = (int) ($queueDepths[$reservation->bibliographic_record_id] ?? 0); @endphp
                    @if ($waiting > 0)
                      <p class="font-body text-xs text-on-surface-variant">{{ __('librarian.reservations.queue_depth', ['count' => $waiting]) }}</p>
                    @endif
                  </div>
                  <div>
                    <p class="font-label text-xs {{ $reservation->status === 'ready_for_pickup' ? 'text-secondary font-bold' : 'text-on-surface-variant' }} uppercase tracking-wider mb-0.5">{{ __('librarian.reservations.expires_at') }}</p>
                    <p class="font-body text-sm text-on-surface">{{ $reservation->expires_at?->format('d.m.Y') ?? '—' }}</p>
                  </div>
                </div>

                {{-- §8 — approximate availability estimate for a queued request.
                     Deliberately hedged: it is not a promised date. --}}
                @php $forecastDays = $forecasts[$reservation->id] ?? null; @endphp
                @if ($forecastDays !== null)
                  <div class="mb-5 flex items-start gap-2 rounded-md bg-surface-container-high px-4 py-3">
                    <span class="material-symbols-outlined text-[18px] text-secondary">timelapse</span>
                    <span class="font-body text-sm text-on-surface-variant">
                      <strong class="text-on-surface">{{ __('librarian.reservations.forecast') }}:</strong>
                      {{ __('librarian.reservations.forecast_value', ['days' => $forecastDays]) }}
                      <span class="block text-xs">{{ __('librarian.member.reservations.forecast_disclaimer') }}</span>
                    </span>
                  </div>
                @endif
              </div>

              <div class="flex flex-wrap items-center gap-3 mt-auto">
                @if ($reservation->isCancellable())
                  <form method="POST" action="{{ route('member.reservations.cancel', $reservation) }}" onsubmit="return confirm('{{ __('librarian.member.reservations.cancel_confirm') }}');">
                    @csrf
                    <button type="submit" class="px-6 py-2.5 rounded-md font-body font-medium text-sm text-error ring-1 ring-error/30 hover:bg-error-container/50 transition-colors inline-flex items-center gap-2">
                      <span class="material-symbols-outlined text-[18px]">cancel</span>
                      <span>{{ __('librarian.reservations.cancel') }}</span>
                    </button>
                  </form>
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
  </section>

  <!-- History -->
  <section>
    <h2 class="font-headline text-2xl text-primary mb-6">{{ __('librarian.member.reservations.past') }}</h2>

    @if ($pastReservations->isEmpty())
      <p class="bg-surface-container-lowest rounded-xl p-6 font-body text-sm text-on-surface-variant">
        {{ __('librarian.member.reservations.empty_past') }}
      </p>
    @else
      <div class="bg-surface-container-lowest rounded-xl overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[40rem]">
          <thead>
            <tr class="border-b border-outline-variant/20">
              <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.reservations.record') }}</th>
              <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.member.reservations.created_at') }}</th>
              <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.reservations.assigned_copy') }}</th>
              <th class="px-6 py-4 font-label text-xs text-on-surface-variant uppercase tracking-widest">{{ __('librarian.reservations.filters.status') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pastReservations as $reservation)
              <tr class="border-b border-outline-variant/10 last:border-0">
                <td class="px-6 py-4">
                  <p class="font-body text-sm text-primary font-medium">{{ $reservation->bibliographicRecord?->title ?: __('common.catalog.title_unknown') }}</p>
                  <p class="font-body text-xs text-on-surface-variant">{{ $reservation->bibliographicRecord?->primary_author ?: __('librarian.member.common.unknown_author') }}</p>
                </td>
                <td class="px-6 py-4 font-body text-sm text-on-surface-variant">{{ $reservation->created_at?->format('d.m.Y') ?? '—' }}</td>
                <td class="px-6 py-4 font-body text-sm text-on-surface-variant">{{ $reservation->assignedCopy?->inventory_number ?: '—' }}</td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full {{ $statusTone[$reservation->status] ?? 'bg-surface-variant text-on-surface-variant' }} font-label text-[11px] tracking-widest uppercase">
                    {{ __('librarian.reservations.statuses.'.$reservation->status) }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>
@endsection
