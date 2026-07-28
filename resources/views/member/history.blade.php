@extends('layouts.member', ['title' => 'Borrowing history — KazUTB Smart Library'])

@php
  // Representative placeholder data — actual borrowing history arrives when
  // the circulation backend for the member module is wired up.
  //
  // Status vocabulary for the historical record:
  //   returned · currently_borrowed · overdue_returned
  $historyGroups = [
      [
          'term' => 'Spring term 2026',
          'items' => [
              [
                  'title' => 'Typographic Systems of Design',
                  'author' => 'Kimberly Elam',
                  'edition' => 'Princeton Architectural Press, 2007',
                  'call_number' => 'Z246 .E43 2007',
                  'status' => 'currently_borrowed',
                  'status_label' => 'Currently on loan',
                  'accent' => true,
                  'branch' => 'KazUTB Central Library · Floor 1',
                  'dates' => [
                      ['label' => 'Borrowed', 'value' => 'Apr 01, 2026'],
                      ['label' => 'Due date', 'value' => 'Apr 28, 2026', 'tone' => 'secondary'],
                  ],
                  'actions' => [
                      ['label' => 'Renew loan', 'icon' => 'autorenew', 'tone' => 'primary'],
                  ],
              ],
              [
                  'title' => 'The Architecture of Complexity',
                  'author' => 'Herbert A. Simon',
                  'edition' => 'Proceedings of the American Philosophical Society, 1962',
                  'call_number' => 'Q300 .S56',
                  'status' => 'returned',
                  'status_label' => 'Returned',
                  'accent' => false,
                  'branch' => 'Faculty depository · Technology wing',
                  'dates' => [
                      ['label' => 'Borrowed', 'value' => 'Feb 12, 2026'],
                      ['label' => 'Returned', 'value' => 'Mar 04, 2026'],
                  ],
                  'actions' => [
                      ['label' => 'Request again', 'icon' => 'menu_book', 'tone' => 'secondary'],
                      ['label' => 'Cite', 'icon' => 'format_quote', 'tone' => 'neutral'],
                  ],
              ],
          ],
      ],
      [
          'term' => 'Autumn term 2025',
          'items' => [
              [
                  'title' => 'Clean Architecture',
                  'author' => 'Robert C. Martin',
                  'edition' => 'Pearson, 2017',
                  'call_number' => 'QA76.758 .M367 2017',
                  'status' => 'returned',
                  'status_label' => 'Returned',
                  'accent' => false,
                  'branch' => 'KazUTB Central Library · Floor 2',
                  'dates' => [
                      ['label' => 'Borrowed', 'value' => 'Oct 02, 2025'],
                      ['label' => 'Returned', 'value' => 'Oct 30, 2025'],
                  ],
                  'actions' => [
                      ['label' => 'Request again', 'icon' => 'menu_book', 'tone' => 'secondary'],
                      ['label' => 'Cite', 'icon' => 'format_quote', 'tone' => 'neutral'],
                  ],
              ],
              [
                  'title' => 'Database System Concepts',
                  'author' => 'A. Silberschatz, H. F. Korth, S. Sudarshan',
                  'edition' => 'McGraw-Hill, Seventh Edition',
                  'call_number' => 'QA76.9.D3 S5637 2020',
                  'status' => 'returned',
                  'status_label' => 'Returned',
                  'accent' => false,
                  'branch' => 'KazUTB Central Library · Reference hall',
                  'dates' => [
                      ['label' => 'Borrowed', 'value' => 'Sep 08, 2025'],
                      ['label' => 'Returned', 'value' => 'Sep 29, 2025'],
                  ],
                  'actions' => [
                      ['label' => 'Request again', 'icon' => 'menu_book', 'tone' => 'secondary'],
                  ],
              ],
          ],
      ],
  ];

  $totalItems = collect($historyGroups)->sum(fn ($g) => count($g['items']));
@endphp

@section('content')
  <!-- Header -->
  <header class="mb-12 md:mb-16 flex flex-col md:flex-row md:items-end md:justify-between gap-6">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
        <span class="w-2 h-2 rounded-full bg-secondary"></span>
        <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">{{ $totalItems }} items on record</span>
      </div>
      <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-6">Borrowing history</h1>
      <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
        A chronological record of the materials you have borrowed from the KazUTB collection. Active loans are highlighted — renewals and citation exports remain available for items you have returned.
      </p>
    </div>
    <div class="flex flex-wrap gap-3">
      <span class="inline-flex items-center gap-2 bg-surface-container-low px-4 py-2 rounded-DEFAULT border-b border-outline-variant/20">
        <span class="material-symbols-outlined text-on-surface-variant text-sm">filter_list</span>
        <span class="font-label text-sm text-on-surface">Filter by term</span>
      </span>
      <span class="inline-flex items-center gap-2 bg-surface-container-low px-4 py-2 rounded-DEFAULT border-b border-outline-variant/20">
        <span class="material-symbols-outlined text-on-surface-variant text-sm">sort</span>
        <span class="font-label text-sm text-on-surface">Sort chronological</span>
      </span>
    </div>
  </header>

  <!-- Placeholder disclosure -->
  <p class="font-body italic text-sm text-on-surface-variant mb-10 max-w-3xl">
    The records shown below are representative placeholders. Real borrowing history will appear once the circulation backend is wired up to the member module.
  </p>

  <!-- Timeline -->
  <div class="space-y-14">
    @foreach ($historyGroups as $group)
      <section>
        <h2 class="font-headline text-xl md:text-2xl text-on-surface-variant mb-6 md:mb-8 pl-0 md:pl-4 opacity-90">
          {{ $group['term'] }}
        </h2>
        <div class="space-y-6 pl-0 md:pl-4">
          @foreach ($group['items'] as $item)
            <article class="group bg-surface-container-low p-6 md:p-8 rounded-lg transition-all duration-500 ease-out shadow-[0_4px_24px_rgba(0,31,63,0.02)] hover:bg-surface-container-lowest hover:shadow-[0_12px_48px_rgba(0,31,63,0.06)] flex flex-col md:flex-row gap-6 md:gap-8 {{ $item['accent'] ? 'border-l-4 border-secondary' : '' }}">
              <div class="flex-shrink-0 w-full md:w-32 h-40 md:h-48 rounded-DEFAULT bg-gradient-to-br from-primary-fixed to-primary-container flex items-center justify-center">
                <span class="material-symbols-outlined text-on-primary text-5xl opacity-80">menu_book</span>
              </div>
              <div class="flex-1 flex flex-col justify-between">
                <div>
                  <div class="flex justify-between items-start mb-2 gap-4">
                    <h3 class="font-headline text-xl md:text-2xl text-primary leading-tight">{{ $item['title'] }}</h3>
                    @if ($item['status'] === 'currently_borrowed')
                      <span class="inline-flex items-center px-3 py-1 bg-secondary/10 text-secondary rounded-full font-label text-xs font-bold whitespace-nowrap">
                        {{ $item['status_label'] }}
                      </span>
                    @else
                      <span class="inline-flex items-center px-3 py-1 bg-surface-container-high rounded-full font-label text-xs text-on-surface-variant whitespace-nowrap">
                        {{ $item['status_label'] }}
                      </span>
                    @endif
                  </div>
                  <p class="font-body text-base text-on-surface-variant mb-1">{{ $item['author'] }}</p>
                  <p class="font-label text-xs text-on-surface-variant/80 mb-4 uppercase tracking-wider">
                    {{ $item['edition'] }} · {{ $item['call_number'] }}
                  </p>
                  <div class="grid grid-cols-2 gap-4 max-w-sm mb-4">
                    @foreach ($item['dates'] as $date)
                      <div>
                        <p class="font-label text-xs {{ ($date['tone'] ?? null) === 'secondary' ? 'text-secondary font-bold' : 'text-on-surface-variant' }} mb-1 uppercase tracking-wider">{{ $date['label'] }}</p>
                        <p class="font-body text-sm {{ ($date['tone'] ?? null) === 'secondary' ? 'text-primary font-medium' : 'text-on-surface' }}">{{ $date['value'] }}</p>
                      </div>
                    @endforeach
                  </div>
                  <p class="font-label text-xs text-on-surface-variant uppercase tracking-wider flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">location_on</span>
                    {{ $item['branch'] }}
                  </p>
                </div>
                <div class="flex flex-wrap gap-4 mt-5">
                  @foreach ($item['actions'] as $action)
                    @php
                      $toneClass = match ($action['tone']) {
                          'primary' => 'text-primary border-b border-outline-variant/40 hover:border-primary',
                          'secondary' => 'text-secondary hover:text-primary',
                          default => 'text-on-surface-variant hover:text-primary',
                      };
                    @endphp
                    <span class="font-label text-sm {{ $toneClass }} transition-colors flex items-center gap-1">
                      <span class="material-symbols-outlined text-sm">{{ $action['icon'] }}</span>
                      {{ $action['label'] }}
                    </span>
                  @endforeach
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </section>
    @endforeach
  </div>

  <!-- Footer note -->
  <footer class="mt-16 pt-8 border-t border-outline-variant/20">
    <p class="font-body text-sm text-on-surface-variant max-w-2xl">
      Need an older record? The KazUTB reference desk can retrieve borrowing history from prior academic years upon request — contact the library using the <a href="{{ route('member.messages') }}" class="text-secondary hover:text-primary transition-colors">Messages</a> workspace.
    </p>
  </footer>
@endsection
