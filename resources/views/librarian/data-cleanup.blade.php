@extends('layouts.librarian', ['title' => 'Data Stewardship & Cleanup — KazUTB Smart Library'])

@php
  $healthCards = [
      [
          'icon' => 'health_and_safety',
          'icon_tone' => 'text-outline',
          'title' => 'System Health Index',
          'subtitle' => 'Overall Catalog Integrity',
          'value' => '94.2%',
          'delta' => ['icon' => 'arrow_upward', 'text' => '0.5%', 'tone' => 'text-secondary'],
      ],
      [
          'icon' => 'error_outline',
          'icon_tone' => 'text-error',
          'title' => 'Critical Anomalies',
          'subtitle' => 'Action Required',
          'value' => '127',
          'delta' => ['icon' => null, 'text' => 'records', 'tone' => 'text-on-surface-variant'],
      ],
  ];

  $anomalyTypes = [
      'Missing ISBN/ISSN',
      'Malformed Author String',
      'Orphaned Authority Record',
      'Duplicate Entry Candidate',
  ];

  $collections = [
      'Main Catalog',
      'Scientific Repository',
      'Rare Books',
      'Faculty Reserves',
  ];

  $anomalies = [
      [
          'id' => 'B-8921',
          'title' => 'Quantum Mechanics and Path Integrals',
          'subject' => 'R.P. Feynman, A.R. Hibbs',
          'reason' => 'Missing ISBN/ISSN',
          'dot' => 'bg-error',
          'modified' => '2 days ago',
      ],
      [
          'id' => 'SR-401',
          'title' => 'Analysis of Eurasian Steppe Ecology',
          'subject' => 'Department of Biology, KazUTB',
          'reason' => 'Malformed Author String',
          'dot' => 'bg-secondary',
          'modified' => '5 days ago',
      ],
      [
          'id' => 'C-1102',
          'title' => 'Principles of Macroeconomics',
          'subject' => 'N. Gregory Mankiw',
          'reason' => 'Duplicate Entry Candidate',
          'dot' => 'bg-outline',
          'modified' => '1 week ago',
      ],
      [
          'id' => 'A-3305',
          'title' => 'Thermodynamics for Engineers',
          'subject' => 'Department of Physics, KazUTB',
          'reason' => 'Orphaned Authority Record',
          'dot' => 'bg-error',
          'modified' => '2 weeks ago',
      ],
  ];
@endphp

@section('content')
  <div class="max-w-7xl mx-auto w-full">
    <!-- Hero Header -->
    <header class="mb-16">
      <h1 class="font-headline text-5xl md:text-[3.5rem] leading-tight text-primary-container tracking-tight mb-4">Data Stewardship &amp; Cleanup</h1>
      <p class="font-body text-lg text-on-surface-variant max-w-2xl">Monitor catalog health, resolve metadata anomalies, and ensure data integrity across the library's main catalog and the scientific repository.</p>
    </header>

    <!-- Health Summary Bento Grid -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16" aria-label="Data health summary">
      @foreach ($healthCards as $card)
        <article class="bg-surface-container-lowest p-8 rounded-lg flex flex-col justify-between group hover:bg-surface-container-high transition-colors duration-500">
          <div>
            <span class="material-symbols-outlined {{ $card['icon_tone'] }} mb-4 text-3xl">{{ $card['icon'] }}</span>
            <h3 class="font-body text-lg font-bold text-primary-container mb-2">{{ $card['title'] }}</h3>
            <p class="text-xs text-on-surface-variant uppercase tracking-wider">{{ $card['subtitle'] }}</p>
          </div>
          <div class="mt-8 flex items-baseline gap-2">
            <span class="font-headline text-4xl text-primary-container">{{ $card['value'] }}</span>
            <span class="text-sm font-body {{ $card['delta']['tone'] }} inline-flex items-center gap-1">
              @if ($card['delta']['icon'])
                <span class="material-symbols-outlined text-sm">{{ $card['delta']['icon'] }}</span>
              @endif
              <span>{{ $card['delta']['text'] }}</span>
            </span>
          </div>
        </article>
      @endforeach

      <!-- Automated Batch Fix (dark highlight card) -->
      <article class="bg-gradient-to-br from-primary to-primary-container p-8 rounded-lg flex flex-col justify-between text-on-primary shadow-sm">
        <div>
          <span class="material-symbols-outlined text-primary-fixed-dim mb-4 text-3xl">autorenew</span>
          <h3 class="font-body text-lg font-bold mb-2 text-white">Automated Batch Fix</h3>
          <p class="text-sm font-body text-on-primary-container">Run structural corrections on common formatting errors across the catalog. Safe to preview before commit.</p>
        </div>
        <button type="button" class="mt-8 bg-surface-container-lowest text-primary-container py-2 px-4 rounded-md text-sm font-bold self-start hover:bg-surface-dim transition-colors">
          Initiate Sweep
        </button>
      </article>
    </section>

    <!-- Filters & Actions -->
    <div class="flex flex-col md:flex-row justify-between md:items-end mb-8 gap-4">
      <div class="flex flex-col md:flex-row gap-4">
        <div class="flex flex-col gap-2">
          <label for="anomaly-type" class="text-xs text-on-surface-variant uppercase tracking-wider">Anomaly Type</label>
          <select id="anomaly-type" class="bg-surface-container-lowest border border-outline-variant/20 text-on-surface text-sm rounded-md py-2 px-4 focus:outline-none focus:border-secondary pr-8">
            @foreach ($anomalyTypes as $type)
              <option>{{ $type }}</option>
            @endforeach
          </select>
        </div>
        <div class="flex flex-col gap-2">
          <label for="anomaly-collection" class="text-xs text-on-surface-variant uppercase tracking-wider">Collection</label>
          <select id="anomaly-collection" class="bg-surface-container-lowest border border-outline-variant/20 text-on-surface text-sm rounded-md py-2 px-4 focus:outline-none focus:border-secondary pr-8">
            @foreach ($collections as $collection)
              <option>{{ $collection }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <button type="button" class="border border-outline-variant/20 text-secondary py-2 px-6 rounded-md text-sm font-medium hover:bg-surface-container-highest transition-colors inline-flex items-center gap-2 self-start md:self-auto">
        <span class="material-symbols-outlined text-sm">filter_list</span>
        <span>Advanced Filters</span>
      </button>
    </div>

    <!-- Flagged Records List -->
    <section class="bg-surface-container-lowest rounded-lg overflow-hidden pb-4" aria-label="Flagged records queue">
      <div class="grid grid-cols-12 gap-4 px-8 py-4 border-b border-surface-container-high bg-surface-container-low text-xs text-on-surface-variant uppercase tracking-wider">
        <div class="col-span-1">ID</div>
        <div class="col-span-5">Title / Subject Context</div>
        <div class="col-span-3">Reason Code</div>
        <div class="col-span-2">Last Modified</div>
        <div class="col-span-1 text-right">Action</div>
      </div>

      @foreach ($anomalies as $anomaly)
        <div class="grid grid-cols-12 gap-4 px-8 py-6 items-center hover:bg-surface-container-high transition-colors duration-300 border-b border-surface-container-high last:border-b-0">
          <div class="col-span-1 text-sm font-mono text-outline">{{ $anomaly['id'] }}</div>
          <div class="col-span-5">
            <h4 class="font-headline text-base text-primary-container mb-1">{{ $anomaly['title'] }}</h4>
            <p class="text-sm font-body text-on-surface-variant">{{ $anomaly['subject'] }}</p>
          </div>
          <div class="col-span-3 flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full {{ $anomaly['dot'] }}"></span>
            <span class="text-sm font-body text-on-surface">{{ $anomaly['reason'] }}</span>
          </div>
          <div class="col-span-2 text-sm text-on-surface-variant">{{ $anomaly['modified'] }}</div>
          <div class="col-span-1 text-right">
            <button type="button" class="text-secondary hover:text-primary-container transition-colors font-medium text-sm">Review</button>
          </div>
        </div>
      @endforeach

      <div class="px-8 py-6 flex justify-center">
        <button type="button" class="text-sm font-body font-medium text-secondary hover:text-primary-container transition-colors inline-flex items-center gap-2">
          <span>Load More Anomalies</span>
          <span class="material-symbols-outlined text-sm">expand_more</span>
        </button>
      </div>
    </section>
  </div>
@endsection
