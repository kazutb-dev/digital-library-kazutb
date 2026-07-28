@extends('layouts.member', ['title' => 'My literature shortlist — KazUTB Smart Library'])

@php
  // Representative shortlist. In later phases this is backed by
  // ShortlistStorageService (literature_drafts + literature_draft_items).
  $shortlist = [
      [
          'featured' => true,
          'type' => 'Monograph',
          'added' => 'Added 2 days ago',
          'title' => 'Introduction to Algorithms',
          'author' => 'T. H. Cormen, C. E. Leiserson, R. L. Rivest, C. Stein',
          'year' => 'MIT Press, 2009',
          'note' => 'Core reference for the algorithms and data structures course. Review chapters on graph algorithms before the seminar.',
      ],
      [
          'type' => 'Textbook',
          'added' => 'Added 5 days ago',
          'title' => 'Clean Architecture',
          'author' => 'Robert C. Martin',
          'year' => '2018',
      ],
      [
          'type' => 'Textbook',
          'added' => 'Added 1 week ago',
          'title' => 'The Pragmatic Programmer',
          'author' => 'Andrew Hunt, David Thomas',
          'year' => '20th Anniversary Edition, 2019',
      ],
      [
          'type' => 'Reference',
          'added' => 'Added 1 week ago',
          'title' => 'Database System Concepts',
          'author' => 'A. Silberschatz, H. F. Korth, S. Sudarshan',
          'year' => '7th Edition, 2019',
      ],
  ];

  $total = count($shortlist);
@endphp

@section('content')
  <!-- Header -->
  <header class="mb-12">
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-tight mb-4">My shortlist</h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      Your curated collection of reading material. Save titles from the catalog for later, open citations, or convert an item into a reservation when you are ready to borrow it.
    </p>
  </header>

  <!-- Filter / action bar -->
  <div class="flex flex-wrap items-center justify-between gap-4 mb-10 md:mb-12">
    <div class="flex gap-2">
      <button type="button" class="px-4 py-2 bg-surface-container-lowest text-primary rounded-md border-b border-outline-variant/20 text-sm font-semibold">All items ({{ $total }})</button>
      <button type="button" class="px-4 py-2 bg-transparent text-on-surface-variant rounded-md hover:bg-surface-variant transition-colors text-sm font-medium">Books</button>
      <button type="button" class="px-4 py-2 bg-transparent text-on-surface-variant rounded-md hover:bg-surface-variant transition-colors text-sm font-medium">Journals</button>
    </div>
    <div class="flex gap-4 items-center">
      <button type="button" class="flex items-center gap-2 text-sm font-semibold text-secondary hover:text-primary transition-colors">
        <span class="material-symbols-outlined text-lg">filter_list</span>
        <span>Filter</span>
      </button>
      <button type="button" class="flex items-center gap-2 text-sm font-semibold text-secondary hover:text-primary transition-colors">
        <span class="material-symbols-outlined text-lg">sort</span>
        <span>Sort: date added</span>
      </button>
    </div>
  </div>

  <!-- Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">

    @foreach ($shortlist as $item)
      @if (! empty($item['featured']))
        <!-- Featured item (2 columns) -->
        <article class="lg:col-span-2 bg-surface-container-lowest rounded-xl p-6 md:p-8 hover:bg-surface-container-high transition-colors duration-500 flex flex-col md:flex-row gap-6 md:gap-8 items-start group">
          <div class="w-full md:w-48 shrink-0 relative aspect-[2/3] rounded-lg overflow-hidden bg-gradient-to-br from-primary-fixed to-primary-container shadow-md flex items-center justify-center">
            <span class="material-symbols-outlined text-on-primary text-6xl">auto_stories</span>
          </div>
          <div class="flex-1 flex flex-col justify-between h-full">
            <div>
              <div class="flex items-center gap-2 mb-3">
                <span class="px-2 py-1 bg-surface-container text-on-surface-variant text-xs font-label rounded uppercase tracking-wider">{{ $item['type'] }}</span>
                <span class="text-xs text-on-surface-variant font-label">{{ $item['added'] }}</span>
              </div>
              <h2 class="text-2xl md:text-3xl font-headline text-primary mb-2 leading-tight">{{ $item['title'] }}</h2>
              <p class="text-on-surface-variant text-sm mb-1 font-body">{{ $item['author'] }}</p>
              <p class="text-on-surface-variant text-xs mb-4 font-body">{{ $item['year'] }}</p>
              @if (! empty($item['note']))
                <p class="text-on-surface text-sm leading-relaxed mb-6 font-body">{{ $item['note'] }}</p>
              @endif
            </div>
            <div class="flex flex-wrap gap-3 mt-auto">
              <button type="button" class="px-6 py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-semibold text-sm hover:opacity-90 transition-opacity">
                Reserve
              </button>
              <button type="button" class="px-6 py-2 bg-transparent text-secondary rounded-md font-semibold text-sm border border-outline-variant/20 hover:bg-surface-variant transition-colors inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">format_quote</span>
                <span>Cite</span>
              </button>
              <button type="button" class="px-4 py-2 text-on-surface-variant font-medium text-sm hover:text-error transition-colors rounded-md inline-flex items-center gap-1 ml-auto">
                <span class="material-symbols-outlined text-sm">bookmark_remove</span>
                <span>Remove</span>
              </button>
            </div>
          </div>
        </article>
      @else
        <!-- Standard item -->
        <article class="bg-surface-container-lowest rounded-xl p-6 hover:bg-surface-container-high transition-colors duration-500 flex flex-col group">
          <div class="flex justify-between items-start mb-4">
            <div class="w-20 h-28 shrink-0 relative rounded-md overflow-hidden bg-gradient-to-br from-primary-fixed to-primary-container shadow-sm flex items-center justify-center">
              <span class="material-symbols-outlined text-on-primary text-3xl">menu_book</span>
            </div>
            <button type="button" class="text-on-surface-variant hover:text-error transition-colors" aria-label="Remove from shortlist">
              <span class="material-symbols-outlined">bookmark_remove</span>
            </button>
          </div>
          <div class="flex-1">
            <span class="text-xs text-secondary font-label uppercase tracking-widest mb-1 block">{{ $item['type'] }}</span>
            <h3 class="text-lg font-headline text-primary mb-1 leading-snug">{{ $item['title'] }}</h3>
            <p class="text-on-surface-variant text-sm font-body">{{ $item['author'] }}</p>
            <p class="text-on-surface-variant text-xs font-body mb-4">{{ $item['year'] }}</p>
          </div>
          <div class="flex gap-3 mt-4 pt-4 border-t border-outline-variant/10">
            <button type="button" class="flex-1 py-2 bg-transparent text-secondary rounded-md font-semibold text-sm border border-outline-variant/20 hover:bg-surface-variant transition-colors text-center">
              Cite
            </button>
            <button type="button" class="flex-1 py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-semibold text-sm hover:opacity-90 transition-opacity text-center">
              Reserve
            </button>
          </div>
        </article>
      @endif
    @endforeach

    <!-- Empty-slot CTA -->
    <a href="/catalog" class="bg-surface-container-high rounded-xl p-8 border-l-4 border-secondary flex flex-col hover:bg-surface-container transition-colors">
      <div class="flex items-center gap-3 mb-6 text-primary">
        <span class="material-symbols-outlined text-2xl">edit_note</span>
        <h3 class="text-xl font-headline">Reading notes</h3>
      </div>
      <p class="text-sm font-body text-on-surface mb-6 leading-relaxed">
        Keep a short note alongside every saved item — a chapter to revisit, a citation to double-check, a reminder for the next seminar.
      </p>
      <div class="mt-auto">
        <span class="text-secondary font-semibold text-sm flex items-center gap-2 hover:text-primary transition-colors">
          <span>Browse the catalog</span>
          <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </span>
      </div>
    </a>

  </div>

  <p class="mt-12 text-xs text-on-surface-variant italic max-w-2xl">
    Shortlist entries shown here are representative placeholders. Once the shortlist service is wired into the member module, your real saved items from the catalog will appear in this grid.
  </p>
@endsection
