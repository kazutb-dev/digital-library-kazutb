@extends('layouts.member', ['title' => 'Notifications — KazUTB Smart Library'])

@php
  // Representative placeholder data — real notifications arrive when
  // the notification backend for the member module is wired up.
  //
  // Event vocabulary (see PROJECT_CONTEXT §Notifications):
  //   reservation.created · reservation.confirmed · reservation.ready_for_pickup
  //   reservation.expired · loan.due_soon · loan.overdue
  //   digital_access.granted · message.status_changed · system.announcement
  $notificationGroups = [
      [
          'label' => 'Today, April 21',
          'items' => [
              [
                  'event' => 'reservation.ready_for_pickup',
                  'icon' => 'book_online',
                  'title' => 'Reservation ready for pickup',
                  'body' => 'Your requested volume, <span class="italic font-headline">Introduction to Algorithms</span>, has been retrieved from the depository and is waiting at the main circulation desk.',
                  'time' => '10:42',
                  'unread' => true,
                  'tone' => 'primary',
                  'primary_cta' => ['label' => 'Pickup instructions', 'href' => route('member.reservations')],
                  'secondary_cta' => ['label' => 'Release hold', 'href' => '#'],
              ],
              [
                  'event' => 'digital_access.granted',
                  'icon' => 'lock_open',
                  'title' => 'Digital access granted',
                  'body' => 'You now have full access to the <span class="italic font-headline">IEEE Xplore</span> collection for the Spring 2026 term.',
                  'time' => '08:15',
                  'unread' => true,
                  'tone' => 'secondary',
                  'primary_cta' => null,
                  'secondary_cta' => ['label' => 'Open resource', 'href' => '/resources'],
              ],
          ],
      ],
      [
          'label' => 'Yesterday, April 20',
          'items' => [
              [
                  'event' => 'loan.overdue',
                  'icon' => 'warning',
                  'title' => 'Overdue notice',
                  'body' => 'The loan period for <span class="italic font-headline">Clean Architecture</span> expired yesterday. Please return the copy or request an extension.',
                  'time' => '14:30',
                  'unread' => false,
                  'tone' => 'error',
                  'primary_cta' => null,
                  'secondary_cta' => ['label' => 'Renew loan', 'href' => route('member.history')],
              ],
              [
                  'event' => 'message.status_changed',
                  'icon' => 'forum',
                  'title' => 'Reply from the library',
                  'body' => 'Your inquiry <span class="italic font-headline">«Доступ к архиву журналов по экономике»</span> has been marked as <strong>in review</strong> by the curation team.',
                  'time' => '11:05',
                  'unread' => false,
                  'tone' => 'neutral',
                  'primary_cta' => null,
                  'secondary_cta' => ['label' => 'Open conversation', 'href' => route('member.messages')],
              ],
          ],
      ],
      [
          'label' => 'April 18',
          'items' => [
              [
                  'event' => 'reservation.confirmed',
                  'icon' => 'how_to_reg',
                  'title' => 'Reservation confirmed',
                  'body' => 'A librarian confirmed your request for <span class="italic font-headline">Database System Concepts</span>. You will be notified when the copy is ready for pickup.',
                  'time' => '09:00',
                  'unread' => false,
                  'tone' => 'neutral',
                  'primary_cta' => null,
                  'secondary_cta' => null,
              ],
              [
                  'event' => 'system.announcement',
                  'icon' => 'system_update',
                  'title' => 'Scheduled maintenance',
                  'body' => 'The digital viewer will undergo scheduled maintenance on April 25 from 02:00 to 04:00 UTC. Offline access remains available.',
                  'time' => '08:00',
                  'unread' => false,
                  'tone' => 'neutral',
                  'primary_cta' => null,
                  'secondary_cta' => null,
              ],
          ],
      ],
  ];

  $unreadCount = collect($notificationGroups)
      ->flatMap(fn ($g) => $g['items'])
      ->where('unread', true)
      ->count();

  $tabs = [
      ['key' => 'all', 'label' => 'All alerts', 'active' => true],
      ['key' => 'reservations', 'label' => 'Reservations', 'active' => false],
      ['key' => 'access', 'label' => 'Access notices', 'active' => false],
      ['key' => 'system', 'label' => 'System', 'active' => false],
  ];
@endphp

@section('content')
  <!-- Header -->
  <header class="mb-12 md:mb-16 flex flex-col md:flex-row md:items-end md:justify-between gap-6">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
        <span class="w-2 h-2 rounded-full bg-secondary"></span>
        <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">{{ $unreadCount }} unread</span>
      </div>
      <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">Notifications</h1>
      <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
        Updates, alerts, and access notices from the KazUTB Smart Library. Reservation status, circulation reminders, digital access grants, and replies from the library team appear here.
      </p>
    </div>
    <div class="hidden md:flex gap-4">
      <span class="font-label text-sm text-secondary uppercase tracking-widest flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">done_all</span>
        Mark all as read
      </span>
    </div>
  </header>

  <!-- Placeholder disclosure -->
  <p class="font-body italic text-sm text-on-surface-variant mb-8 max-w-3xl">
    The alerts shown below are representative placeholders. Real notifications will appear once the member notification backend is wired up.
  </p>

  <!-- Tabs -->
  <div class="flex gap-6 md:gap-8 mb-10 md:mb-12 border-b border-outline-variant/20 pb-4 overflow-x-auto">
    @foreach ($tabs as $tab)
      @if ($tab['active'])
        <span class="text-primary font-bold text-sm tracking-wider uppercase whitespace-nowrap relative">
          {{ $tab['label'] }}
          <span class="absolute -bottom-4 left-0 w-full h-[2px] bg-secondary"></span>
        </span>
      @else
        <span class="text-on-surface-variant text-sm font-medium tracking-wider uppercase whitespace-nowrap">
          {{ $tab['label'] }}
        </span>
      @endif
    @endforeach
  </div>

  <!-- Feed -->
  <div class="space-y-12">
    @foreach ($notificationGroups as $group)
      <section>
        <h2 class="text-sm font-label uppercase tracking-widest text-on-surface-variant mb-6">{{ $group['label'] }}</h2>
        <div class="space-y-5">
          @foreach ($group['items'] as $item)
            @php
              $iconWrap = match ($item['tone']) {
                  'primary' => 'bg-primary-container/10 text-primary-container',
                  'secondary' => 'bg-secondary/10 text-secondary',
                  'error' => 'bg-error-container/30 text-error',
                  default => 'bg-surface-container-highest text-on-surface-variant',
              };
              $wrapperClass = $item['unread']
                  ? 'bg-surface-container-lowest shadow-[0_24px_48px_rgba(0,6,19,0.02)]'
                  : 'bg-surface border-b border-outline-variant/20';
            @endphp
            <div class="{{ $wrapperClass }} p-6 rounded-xl flex gap-5 md:gap-6 relative overflow-hidden">
              @if ($item['unread'])
                <div class="absolute left-0 top-0 bottom-0 w-1 bg-secondary"></div>
              @endif
              <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center {{ $iconWrap }}">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex justify-between items-start mb-1 gap-4">
                  <h3 class="text-base md:text-lg font-headline font-medium {{ $item['unread'] ? 'text-primary' : 'text-on-surface' }}">{{ $item['title'] }}</h3>
                  <span class="text-xs text-on-surface-variant font-label whitespace-nowrap">{{ $item['time'] }}</span>
                </div>
                <p class="{{ $item['unread'] ? 'text-on-surface' : 'text-on-surface-variant' }} text-sm md:text-base mb-3">
                  {!! $item['body'] !!}
                </p>
                @if (! empty($item['primary_cta']) || ! empty($item['secondary_cta']))
                  <div class="flex flex-wrap gap-3 mt-3">
                    @if (! empty($item['primary_cta']))
                      <a href="{{ $item['primary_cta']['href'] }}" class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-5 py-2 rounded-md text-sm font-medium hover:opacity-90 transition-opacity">
                        {{ $item['primary_cta']['label'] }}
                      </a>
                    @endif
                    @if (! empty($item['secondary_cta']))
                      <a href="{{ $item['secondary_cta']['href'] }}" class="text-on-surface-variant border-b border-outline-variant/30 hover:text-primary hover:border-primary transition-colors px-1 pb-1 text-sm font-medium">
                        {{ $item['secondary_cta']['label'] }}
                      </a>
                    @endif
                  </div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endforeach
  </div>
@endsection
