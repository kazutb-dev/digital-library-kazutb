@extends('layouts.librarian', ['title' => 'Librarian Overview — KazUTB Smart Library'])

@php
  $today = now();
  $operationalDay = (int) $today->dayOfYear;
  $dateLabel = $today->format('F j');

  $snapshotOverdue = [
      'label' => 'Overdue Items',
      'value' => 42,
      'note' => 'Requires automated notice dispatch or manual review.',
      'cta' => 'Process Batch',
      'href' => route('librarian.circulation'),
      'eyebrow' => 'Circulation',
      'icon' => 'sync_alt',
  ];

  $snapshotStacked = [
      [
          'eyebrow' => 'Data Integrity',
          'title' => 'Merge Conflicts',
          'body' => 'Duplicate catalog entries detected.',
          'value' => 18,
          'cta' => 'Review',
          'href' => route('librarian.data-cleanup'),
      ],
      [
          'eyebrow' => 'Reservations',
          'title' => 'Pending Pulls',
          'body' => 'Awaiting physical retrieval from stacks.',
          'value' => '07',
          'cta' => 'View List',
          'href' => '#',
      ],
  ];

  $repositoryFocus = [
      'title' => 'Dissertation Moderation',
      'body' => 'There are 5 newly uploaded thesis documents awaiting metadata verification and institutional approval before publication to the public directory.',
      'cta' => 'Begin Review Queue',
      'href' => '/internal/review',
      'count' => '05',
      'count_label' => 'Pending Review',
  ];

  $priorities = [
      [
          'icon' => 'inventory_2',
          'title' => 'Process New Acquisitions',
          'body' => 'Batch #8492 arrived this morning. Needs cataloging and physical labeling.',
      ],
      [
          'icon' => 'mop',
          'title' => 'Resolve Authority Records',
          'body' => '3 author name conflicts detected in the latest MARC import.',
      ],
      [
          'icon' => 'mail',
          'title' => 'Faculty Interlibrary Requests',
          'body' => '2 high-priority requests from the Engineering department pending dispatch.',
      ],
  ];
@endphp

@section('content')
  <!-- Morning Briefing header -->
  <div class="max-w-7xl mx-auto mb-16 relative">
    <h1 class="font-headline text-5xl md:text-6xl text-primary-container tracking-tight mb-4 -ml-1">Morning Briefing</h1>
    <p class="font-body text-lg text-on-surface-variant max-w-2xl">Good morning. The library is operating at nominal capacity. You have 14 pending tasks requiring attention across circulation and scientific review.</p>
    <div class="absolute top-2 right-0 hidden lg:flex flex-col items-end text-right">
      <span class="font-headline text-2xl text-primary-container">{{ $dateLabel }}</span>
      <span class="font-body text-sm text-outline tracking-widest uppercase mt-1">Operational Day {{ $operationalDay }}</span>
    </div>
  </div>

  <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
    <!-- Main operations column -->
    <div class="lg:col-span-8 space-y-16">
      <!-- Operational Status bento grid -->
      <section>
        <h2 class="font-headline text-3xl text-primary-container mb-8 border-b border-surface-container-high/50 pb-4">Operational Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Circulation high-priority card -->
          <article class="bg-surface-container-lowest rounded-xl p-8 hover:bg-surface-container-high transition-colors duration-500 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-6 opacity-10 group-hover:opacity-20 transition-opacity">
              <span class="material-symbols-outlined text-6xl text-primary-container">{{ $snapshotOverdue['icon'] }}</span>
            </div>
            <div class="relative z-10">
              <div class="flex items-center gap-2 mb-6">
                <span class="w-2 h-2 rounded-full bg-secondary"></span>
                <span class="font-body text-xs text-outline tracking-wider uppercase">{{ $snapshotOverdue['eyebrow'] }}</span>
              </div>
              <div class="font-headline text-6xl text-primary-container mb-2">{{ $snapshotOverdue['value'] }}</div>
              <h3 class="font-body text-lg font-semibold text-primary-container mb-1">{{ $snapshotOverdue['label'] }}</h3>
              <p class="text-sm text-on-surface-variant mb-6">{{ $snapshotOverdue['note'] }}</p>
              <a href="{{ $snapshotOverdue['href'] }}" class="inline-flex items-center gap-2 text-secondary text-sm font-medium hover:opacity-80 transition-opacity">
                {{ $snapshotOverdue['cta'] }} <span class="material-symbols-outlined text-sm">arrow_forward</span>
              </a>
            </div>
          </article>

          <!-- Stacked secondary cards -->
          <div class="flex flex-col gap-6">
            @foreach ($snapshotStacked as $card)
              <article class="bg-surface-container-lowest rounded-xl p-6 hover:bg-surface-container-high transition-colors duration-500 flex items-center justify-between">
                <div>
                  <div class="font-body text-xs text-outline tracking-wider uppercase mb-1">{{ $card['eyebrow'] }}</div>
                  <h3 class="font-body text-base font-semibold text-primary-container">{{ $card['title'] }}</h3>
                  <p class="text-xs text-on-surface-variant mt-1">{{ $card['body'] }}</p>
                </div>
                <div class="flex flex-col items-end flex-shrink-0 ml-4">
                  <span class="font-headline text-3xl text-primary-container">{{ $card['value'] }}</span>
                  <a href="{{ $card['href'] }}" class="text-secondary text-xs mt-1 hover:underline @if($card['href'] === '#') pointer-events-none opacity-50 @endif">{{ $card['cta'] }}</a>
                </div>
              </article>
            @endforeach
          </div>
        </div>
      </section>

      <!-- Scientific Repository focus -->
      <section class="bg-primary text-on-primary rounded-xl p-1 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary to-primary-container z-0"></div>
        <div class="relative z-10 p-8 md:p-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-8">
          <div class="max-w-md">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-secondary-container">school</span>
              <span class="font-body text-xs text-secondary-container tracking-wider uppercase">Scientific Repository</span>
            </div>
            <h2 class="font-headline text-3xl mb-3 text-white">{{ $repositoryFocus['title'] }}</h2>
            <p class="text-on-primary-container text-sm leading-relaxed mb-6">{{ $repositoryFocus['body'] }}</p>
            <a href="{{ $repositoryFocus['href'] }}" class="inline-flex bg-transparent border border-outline-variant/40 hover:bg-surface-container-lowest/10 text-white px-5 py-2.5 rounded-md font-body text-sm transition-colors duration-300">
              {{ $repositoryFocus['cta'] }}
            </a>
          </div>
          <div class="w-full md:w-auto bg-surface-container-lowest/5 backdrop-blur-sm rounded-lg p-6 border border-white/10">
            <div class="text-center">
              <div class="font-headline text-5xl text-white mb-2">{{ $repositoryFocus['count'] }}</div>
              <div class="text-xs text-on-primary-container uppercase tracking-widest">{{ $repositoryFocus['count_label'] }}</div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Today's Priorities sidebar -->
    <aside class="lg:col-span-4">
      <div class="bg-surface-container-low rounded-xl p-8 sticky top-8">
        <div class="flex items-center justify-between mb-8">
          <h2 class="font-headline text-2xl text-primary-container">Today's Priorities</h2>
          <span class="material-symbols-outlined text-outline">checklist</span>
        </div>
        <div class="space-y-8">
          @foreach ($priorities as $priority)
            <div class="group cursor-pointer">
              <div class="flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-surface-container-highest flex items-center justify-center flex-shrink-0 group-hover:bg-secondary/10 transition-colors">
                  <span class="material-symbols-outlined text-sm text-primary-container group-hover:text-secondary">{{ $priority['icon'] }}</span>
                </div>
                <div>
                  <h4 class="font-body font-semibold text-primary-container text-base group-hover:text-secondary transition-colors">{{ $priority['title'] }}</h4>
                  <p class="text-xs text-on-surface-variant mt-1 leading-relaxed">{{ $priority['body'] }}</p>
                </div>
              </div>
            </div>
          @endforeach
        </div>
        <div class="mt-12 pt-6 border-t border-outline-variant/30">
          <p class="text-xs text-outline italic">System sync completed recently. All operational metrics are current.</p>
        </div>
      </div>
    </aside>
  </div>
@endsection
