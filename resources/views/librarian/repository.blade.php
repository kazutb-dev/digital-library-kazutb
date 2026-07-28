@extends('layouts.librarian', ['title' => 'Scientific Works Moderation Queue — KazUTB Smart Library'])

@php
  $submissions = [
      [
          'status' => 'Under Review',
          'status_dot' => 'bg-secondary',
          'status_tone' => 'bg-surface-container-lowest text-primary-container',
          'submitted' => 'Submitted 2 days ago',
          'submitted_icon' => 'schedule',
          'title' => 'Analysis of Deep Learning Architectures in Natural Language Processing for Turkic Languages',
          'authors' => 'Assel Nurgaliyeva, Timur Khasanov',
          'abstract' => "This paper presents a comprehensive evaluation of transformer-based architectures fine-tuned on Kazakh and other Turkic languages. We identify unique morphological challenges and propose a modified attention mechanism that improves contextual embedding alignment.",
          'tags' => ['UDC: 004.8:81\'322', 'Dissertation', 'Computer Science'],
          'assigned' => 'Assigned: Dr. Smirnov',
          'primary_cta' => 'Review Details',
          'secondary_cta' => 'PDF',
          'secondary_icon' => 'picture_as_pdf',
          'state' => 'under_review',
      ],
      [
          'status' => 'Pending Initial',
          'status_dot' => 'bg-outline',
          'status_tone' => 'bg-surface-container text-on-surface-variant',
          'submitted' => 'Submitted 4 hours ago',
          'submitted_icon' => 'schedule',
          'title' => 'Economic Impacts of Renewable Energy Transition in Central Asia: A Decade Review',
          'authors' => 'Bakhytzhan Ospanov',
          'abstract' => 'A retrospective analysis of policy shifts and economic indicators across Central Asian republics from 2010 to 2020, focusing on the friction between established fossil fuel economies and emerging green infrastructure investments.',
          'tags' => ['UDC: 338.2:620.9(575)', 'Journal Article', 'Economics'],
          'assigned' => null,
          'primary_cta' => 'Review Details',
          'secondary_cta' => 'Claim Task',
          'secondary_icon' => 'assignment_turned_in',
          'state' => 'pending_initial',
      ],
      [
          'status' => 'Approved',
          'status_dot' => null,
          'status_icon' => 'check_circle',
          'status_tone' => 'bg-surface-container-lowest text-on-surface opacity-60',
          'submitted' => 'Approved yesterday',
          'submitted_icon' => 'history',
          'title' => 'Structural Integrity of Sustainable Concrete Alternatives in Seismic Zones',
          'authors' => 'Diana Kim, Almas Serikov',
          'abstract' => 'Investigating geopolymer concrete resilience under simulated high-frequency seismic stress…',
          'tags' => [],
          'assigned' => null,
          'primary_cta' => 'View in Catalog',
          'secondary_cta' => null,
          'state' => 'approved',
      ],
  ];
@endphp

@section('content')
  <div class="max-w-7xl mx-auto w-full flex flex-col gap-16">
    <!-- Page Header -->
    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-8">
      <div class="max-w-2xl">
        <h1 class="font-headline text-5xl md:text-[3.5rem] leading-tight text-primary-container tracking-tight mb-4">Moderation Queue</h1>
        <p class="font-body text-lg text-on-surface-variant leading-relaxed">Review and curate scholarly submissions for the institutional repository. Ensure academic standards and metadata accuracy before publication.</p>
      </div>
      <div class="flex items-center gap-4 bg-surface-container-lowest rounded-full p-2 pr-4 shadow-sm">
        <div class="relative bg-surface-container-high rounded-full inline-flex items-center px-4 py-2 w-64 border-b border-outline-variant/20">
          <span class="material-symbols-outlined text-outline text-sm mr-2">search</span>
          <input class="bg-transparent border-none outline-none text-sm font-body text-on-surface w-full placeholder:text-outline/70 focus:ring-0 p-0" placeholder="Search authors, UDC, titles..." type="text" />
        </div>
        <button type="button" class="inline-flex items-center gap-2 text-secondary font-medium text-sm hover:text-primary-container transition-colors">
          <span class="material-symbols-outlined text-[18px]">filter_list</span>
          <span>Filter</span>
        </button>
      </div>
    </header>

    <!-- Queue -->
    <section class="flex flex-col gap-8" aria-label="Moderation queue">
      @foreach ($submissions as $item)
        @php
          $isApproved = $item['state'] === 'approved';
          $isPending = $item['state'] === 'pending_initial';
          $cardBase = match ($item['state']) {
              'under_review' => 'bg-surface-container-low',
              'pending_initial' => 'bg-surface-container-lowest border-l-4 border-l-secondary/40',
              'approved' => 'bg-surface-container-low/50 opacity-80 hover:opacity-100',
              default => 'bg-surface-container-low',
          };
        @endphp
        <article class="{{ $cardBase }} rounded-lg p-6 flex flex-col md:flex-row gap-8 hover:bg-surface-container-high transition-colors duration-300 relative overflow-hidden">
          <div class="flex-1 flex flex-col gap-4">
            <div class="flex items-center gap-3 mb-2 flex-wrap">
              <span class="{{ $item['status_tone'] }} text-[0.65rem] uppercase tracking-widest font-bold py-1 px-3 rounded-full border border-outline-variant/20 inline-flex items-center gap-1.5">
                @if (!empty($item['status_dot']))
                  <span class="w-1.5 h-1.5 rounded-full {{ $item['status_dot'] }}"></span>
                @elseif (!empty($item['status_icon']))
                  <span class="material-symbols-outlined text-[12px]">{{ $item['status_icon'] }}</span>
                @endif
                <span>{{ $item['status'] }}</span>
              </span>
              <span class="font-body text-xs text-on-surface-variant inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">{{ $item['submitted_icon'] }}</span>
                <span>{{ $item['submitted'] }}</span>
              </span>
            </div>

            <div>
              <h3 class="font-headline {{ $isApproved ? 'text-xl text-outline' : 'text-2xl text-primary-container' }} mb-2 leading-snug pr-12">{{ $item['title'] }}</h3>
              <p class="font-body text-sm {{ $isApproved ? 'text-outline' : 'text-on-surface-variant' }} font-medium">{{ $item['authors'] }}</p>
            </div>

            <p class="font-body text-sm {{ $isApproved ? 'text-outline/70 italic' : 'text-outline' }} leading-relaxed line-clamp-2 max-w-3xl">
              {{ $item['abstract'] }}
            </p>

            @if (!empty($item['tags']))
              <div class="flex flex-wrap gap-2 mt-2">
                @foreach ($item['tags'] as $tag)
                  <span class="font-body text-xs text-outline bg-surface-container rounded-md px-2 py-1">{{ $tag }}</span>
                @endforeach
              </div>
            @endif
          </div>

          <div class="flex flex-row md:flex-col justify-end items-stretch md:items-end gap-3 md:w-48 border-t md:border-t-0 md:border-l border-surface-dim pt-4 md:pt-0 md:pl-6">
            <button type="button" class="w-full bg-gradient-to-r from-primary to-primary-container text-on-primary py-2 px-4 rounded-md font-body font-medium text-sm hover:opacity-90 transition-opacity inline-flex justify-center items-center gap-2">
              <span>{{ $item['primary_cta'] }}</span>
            </button>

            @if (!empty($item['secondary_cta']))
              <button type="button" class="w-full bg-transparent border border-outline-variant/20 text-{{ $isPending ? 'primary-container' : 'secondary' }} py-2 px-4 rounded-md font-body font-medium text-sm hover:bg-surface-variant transition-colors inline-flex justify-center items-center gap-2">
                @if (!empty($item['secondary_icon']))
                  <span class="material-symbols-outlined text-[16px]">{{ $item['secondary_icon'] }}</span>
                @endif
                <span>{{ $item['secondary_cta'] }}</span>
              </button>
            @endif

            @if (!empty($item['assigned']))
              <div class="mt-auto hidden md:flex items-center gap-2 text-xs text-outline">
                <span class="material-symbols-outlined text-[14px]">assignment_ind</span>
                <span>{{ $item['assigned'] }}</span>
              </div>
            @endif
          </div>
        </article>
      @endforeach
    </section>
  </div>
@endsection
