@extends('layouts.member', ['title' => 'Member dashboard — KazUTB Smart Library'])

@php
  $memberReader = $memberReader ?? session('library.user');
  $displayName = $memberReader['display_name'] ?? ($memberReader['name'] ?? ($memberReader['login'] ?? 'Reader'));
  $profileType = $memberReader['profile_type'] ?? 'student';

  $salutation = match ($profileType) {
      'teacher' => 'Welcome back, ' . $displayName . '.',
      'employee' => 'Welcome, ' . $displayName . '.',
      default => 'Good day, ' . $displayName . '.',
  };

  // Representative placeholder data. Backend wiring lands in a later phase.
  $priorityLoan = [
      'title' => 'Introduction to Algorithms',
      'author' => 'T. H. Cormen, C. E. Leiserson, R. L. Rivest, C. Stein',
      'edition' => 'Third Edition · MIT Press',
      'due_in_days' => 3,
      'call_number' => 'QA76.6 .C662 2009',
  ];

  $researchNodes = [
      ['label' => 'Full catalog', 'href' => '/catalog', 'icon' => 'menu_book'],
      ['label' => 'External resources', 'href' => '/resources', 'icon' => 'travel_explore'],
      ['label' => 'Contact the library', 'href' => '/contacts', 'icon' => 'contact_support'],
      ['label' => 'Reference librarian', 'href' => '/contacts', 'icon' => 'record_voice_over'],
  ];

  $shortlistPreview = [
      ['title' => 'Clean Architecture', 'author' => 'Robert C. Martin', 'status' => 'Available', 'tone' => 'secondary'],
      ['title' => 'The Pragmatic Programmer', 'author' => 'Andrew Hunt, David Thomas', 'status' => 'On loan', 'tone' => 'outline'],
      ['title' => 'Database System Concepts', 'author' => 'A. Silberschatz, H. F. Korth, S. Sudarshan', 'status' => 'Digital copy', 'tone' => 'secondary'],
  ];
@endphp

@section('content')
  <!-- Hero -->
  <header class="mb-12 md:mb-16">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">Member dashboard</span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] leading-tight text-primary tracking-tight mb-3">{{ $salutation }}</h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      This is your personal library workspace at KazUTB. Track your reservations, curate a literature shortlist, and reach the university collection at a glance.
    </p>
  </header>

  <!-- Bento grid -->
  <div class="grid grid-cols-12 gap-6 md:gap-10">

    <!-- Priority loan card (span 8) -->
    <section class="col-span-12 lg:col-span-8 bg-surface-container-lowest rounded-xl p-8 md:p-10 flex flex-col justify-between transition-colors duration-500 hover:bg-surface-container-high group">
      <div class="flex items-start justify-between gap-6 mb-8">
        <div class="min-w-0">
          <span class="font-label text-xs text-secondary uppercase tracking-widest font-semibold block mb-3">Priority action</span>
          <h2 class="font-headline text-2xl md:text-3xl text-primary mb-2 leading-tight">{{ $priorityLoan['title'] }}</h2>
          <p class="font-body text-on-surface-variant text-sm">{{ $priorityLoan['author'] }}</p>
          <p class="font-body text-on-surface-variant text-xs mt-1">{{ $priorityLoan['edition'] }} · {{ $priorityLoan['call_number'] }}</p>
        </div>
        <div class="shrink-0 bg-error-container/40 px-4 py-2 rounded-md">
          <span class="font-body text-sm font-semibold text-on-error-container">Due in {{ $priorityLoan['due_in_days'] }} days</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-3 items-end">
        <a href="{{ route('member.reservations') }}" class="bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-medium text-sm px-6 py-3 rounded-md transition-opacity hover:opacity-90 inline-flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">event_repeat</span>
          <span>Manage my loans</span>
        </a>
        <a href="/catalog" class="text-secondary font-body font-medium text-sm px-6 py-3 rounded-md ring-1 ring-outline-variant/20 hover:bg-surface-variant transition-colors inline-flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">menu_book</span>
          <span>Back to catalog</span>
        </a>
      </div>
      <p class="mt-6 text-xs text-on-surface-variant italic">Sample loan shown for presentation — real borrower data appears once the circulation backend is wired in.</p>
    </section>

    <!-- Research nodes (span 4) -->
    <section class="col-span-12 lg:col-span-4 bg-primary-container rounded-xl p-8 md:p-10 text-on-primary-container flex flex-col">
      <h3 class="font-headline text-2xl text-on-primary mb-8 tracking-tight">Research nodes</h3>
      <ul class="space-y-5 flex-1">
        @foreach ($researchNodes as $node)
          <li>
            <a href="{{ $node['href'] }}" class="flex items-center justify-between group cursor-pointer pb-2 border-b border-on-primary-container/20 last:border-transparent">
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

    <!-- Shortlist preview (span 12) -->
    <section class="col-span-12 mt-4 md:mt-8">
      <div class="flex items-end justify-between mb-6 md:mb-8">
        <h3 class="font-headline text-2xl md:text-3xl text-primary">From your shortlist</h3>
        <a href="{{ route('member.list') }}" class="font-body text-sm text-secondary font-medium hover:underline inline-flex items-center gap-1">
          View all <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
        @foreach ($shortlistPreview as $item)
          <article class="bg-surface-container-lowest rounded-xl p-6 transition-colors duration-500 hover:bg-surface-container-high group cursor-pointer">
            <div class="w-full h-40 rounded-md mb-6 shadow-sm group-hover:shadow-md transition-shadow bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary text-4xl">menu_book</span>
            </div>
            <div class="flex flex-col h-full justify-between">
              <div>
                <h4 class="font-headline text-lg text-primary leading-tight mb-2">{{ $item['title'] }}</h4>
                <p class="font-body text-xs text-on-surface-variant mb-4">{{ $item['author'] }}</p>
              </div>
              <span class="font-label text-xs font-semibold {{ $item['tone'] === 'secondary' ? 'text-secondary' : 'text-outline' }} uppercase tracking-wider">{{ $item['status'] }}</span>
            </div>
          </article>
        @endforeach
        <!-- Explore catalog CTA -->
        <a href="/catalog" class="bg-surface-container-lowest rounded-xl p-6 transition-colors duration-500 hover:bg-surface-container-high group flex items-center justify-center border-2 border-dashed border-outline-variant/30 hover:border-secondary/50 min-h-[16rem]">
          <div class="text-center">
            <span class="material-symbols-outlined text-outline-variant text-4xl mb-2 group-hover:text-secondary transition-colors">add_circle</span>
            <p class="font-body text-sm font-medium text-on-surface-variant group-hover:text-secondary transition-colors">Explore the catalog</p>
          </div>
        </a>
      </div>
    </section>

  </div>
@endsection
