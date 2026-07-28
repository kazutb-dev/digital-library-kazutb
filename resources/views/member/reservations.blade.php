@extends('layouts.member', ['title' => 'My reservations — KazUTB Smart Library'])

@php
  // Representative placeholder data — real reservations arrive when
  // the circulation backend for the member module is wired up.
  // All status slugs match the canonical vocabulary:
  //   pending · confirmed · ready_for_pickup · fulfilled · cancelled · expired
  $reservations = [
      [
          'status' => 'ready_for_pickup',
          'status_label' => 'Ready for pickup',
          'status_icon' => 'check_circle',
          'status_tone' => 'secondary',
          'accent_bar' => true,
          'title' => 'Introduction to Algorithms',
          'author' => 'T. H. Cormen, C. E. Leiserson, R. L. Rivest, C. Stein',
          'meta_label' => 'Hold expires',
          'meta_value' => 'In 3 days',
          'blocks' => [
              ['icon' => 'location_on', 'label' => 'Pickup location', 'primary' => 'Main circulation desk', 'secondary' => 'KazUTB Central Library, Floor 1'],
              ['icon' => 'schedule', 'label' => 'Desk hours', 'primary' => '09:00 — 19:00', 'secondary' => 'Mon — Fri'],
          ],
          'primary_cta' => ['label' => 'Pickup instructions', 'href' => '#'],
          'secondary_cta' => ['label' => 'Release hold', 'href' => '#', 'tone' => 'neutral'],
      ],
      [
          'status' => 'confirmed',
          'status_label' => 'Confirmed',
          'status_icon' => 'how_to_reg',
          'status_tone' => 'primary-fixed',
          'accent_bar' => false,
          'title' => 'Clean Architecture',
          'author' => 'Robert C. Martin',
          'meta_label' => 'Requested',
          'meta_value' => 'Earlier this week',
          'notice' => [
              'icon' => 'local_shipping',
              'title' => 'Being transferred from depository',
              'body' => 'This copy is being moved from the faculty depository to the main circulation desk. You will be notified when it becomes ready for pickup.',
          ],
          'primary_cta' => null,
          'secondary_cta' => ['label' => 'Cancel request', 'href' => '#', 'tone' => 'danger'],
      ],
      [
          'status' => 'pending',
          'status_label' => 'Pending review',
          'status_icon' => 'pending',
          'status_tone' => 'neutral',
          'accent_bar' => false,
          'title' => 'Database System Concepts',
          'author' => 'A. Silberschatz, H. F. Korth, S. Sudarshan',
          'meta_label' => 'Requested',
          'meta_value' => 'Today',
          'notice' => [
              'icon' => 'info',
              'title' => 'Awaiting librarian confirmation',
              'body' => 'A librarian will confirm availability shortly. You will receive an email when the reservation is confirmed or declined.',
          ],
          'primary_cta' => null,
          'secondary_cta' => ['label' => 'Withdraw request', 'href' => '#', 'tone' => 'neutral'],
      ],
  ];

  $activeCount = collect($reservations)
      ->whereIn('status', ['pending', 'confirmed', 'ready_for_pickup'])
      ->count();
@endphp

@section('content')
  <!-- Header -->
  <header class="mb-12 md:mb-16">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">{{ $activeCount }} active requests</span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">My reservations</h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      Review the status of materials you have requested. Items marked as ready are held at the circulation desk for 72 hours before they return to the stacks.
    </p>
  </header>

  <!-- List -->
  <div class="flex flex-col space-y-10 md:space-y-12">
    @foreach ($reservations as $r)
      <article class="group bg-surface-container-lowest rounded-xl p-6 md:p-10 flex flex-col md:flex-row gap-8 md:gap-12 relative overflow-hidden transition-colors duration-500 ease-out hover:bg-surface-container-low">
        @if ($r['accent_bar'])
          <div class="absolute top-0 left-0 w-1.5 h-full bg-secondary opacity-80 rounded-l-xl"></div>
        @endif

        <!-- Cover placeholder -->
        <div class="shrink-0 relative">
          <div class="w-32 md:w-40 h-48 md:h-60 rounded-md overflow-hidden bg-gradient-to-br from-primary-fixed to-primary-container shadow-[0_12px_32px_rgba(0,6,19,0.08)] flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-5xl">menu_book</span>
          </div>
        </div>

        <!-- Body -->
        <div class="flex-1 flex flex-col justify-between py-2 min-w-0">
          <div>
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
              @php
                $statusPill = match ($r['status_tone']) {
                    'secondary' => 'bg-secondary-container/50 text-on-secondary-container',
                    'primary-fixed' => 'bg-primary-fixed text-on-primary-fixed',
                    default => 'bg-surface-variant text-on-surface-variant border border-outline-variant/20',
                };
              @endphp
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full {{ $statusPill }} font-label text-xs tracking-widest uppercase">
                <span class="material-symbols-outlined text-[16px]">{{ $r['status_icon'] }}</span>
                <span>{{ $r['status_label'] }}</span>
              </span>
              <span class="font-label text-xs text-on-surface-variant tracking-wider uppercase">
                {{ $r['meta_label'] }}: {{ $r['meta_value'] }}
              </span>
            </div>
            <h2 class="font-headline text-2xl md:text-3xl text-primary leading-tight mb-2 pr-4">{{ $r['title'] }}</h2>
            <p class="font-body text-base text-on-surface-variant font-medium mb-6">{{ $r['author'] }}</p>

            @if (! empty($r['blocks']))
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                @foreach ($r['blocks'] as $b)
                  <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[20px] text-on-surface-variant mt-0.5">{{ $b['icon'] }}</span>
                    <div>
                      <p class="font-label text-xs text-on-surface-variant tracking-wider uppercase mb-0.5">{{ $b['label'] }}</p>
                      <p class="font-body text-base text-primary font-medium">{{ $b['primary'] }}</p>
                      <p class="font-body text-sm text-on-surface-variant">{{ $b['secondary'] }}</p>
                    </div>
                  </div>
                @endforeach
              </div>
            @endif

            @if (! empty($r['notice']))
              <div class="flex items-start gap-3 mb-8 bg-surface p-4 rounded-lg">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant mt-0.5">{{ $r['notice']['icon'] }}</span>
                <div>
                  <p class="font-body text-base text-primary font-medium mb-1">{{ $r['notice']['title'] }}</p>
                  <p class="font-body text-sm text-on-surface-variant leading-relaxed">{{ $r['notice']['body'] }}</p>
                </div>
              </div>
            @endif
          </div>

          <div class="flex items-center gap-3 mt-auto flex-wrap">
            @if (! empty($r['primary_cta']))
              <a href="{{ $r['primary_cta']['href'] }}" class="px-6 py-2.5 rounded-md bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium transition-opacity hover:opacity-90">
                {{ $r['primary_cta']['label'] }}
              </a>
            @endif
            @if (! empty($r['secondary_cta']))
              @php
                $tone = $r['secondary_cta']['tone'] ?? 'neutral';
                $toneCls = $tone === 'danger'
                    ? 'text-error hover:bg-error-container/50'
                    : 'text-primary hover:bg-surface-variant';
              @endphp
              <a href="{{ $r['secondary_cta']['href'] }}" class="px-6 py-2.5 rounded-md font-body font-medium transition-colors {{ $toneCls }}">
                {{ $r['secondary_cta']['label'] }}
              </a>
            @endif
          </div>
        </div>
      </article>
    @endforeach
  </div>

  <p class="mt-12 text-xs text-on-surface-variant italic max-w-2xl">
    Reservations shown are representative placeholders. Live circulation data will populate this screen once the reservation backend for the member module is wired up in a subsequent phase.
  </p>
@endsection
