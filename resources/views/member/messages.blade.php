@extends('layouts.member', ['title' => 'Messages — KazUTB Smart Library'])

@php
  // Canonical message categories (see PROJECT_CONTEXT §Authenticated contact):
  //   request · complaint · improvement · question · other
  $categories = [
      ['value' => 'request',     'label' => 'Request',      'icon' => 'library_books',  'checked' => true],
      ['value' => 'question',    'label' => 'Question',     'icon' => 'help_center',    'checked' => false],
      ['value' => 'improvement', 'label' => 'Improvement',  'icon' => 'lightbulb',      'checked' => false],
      ['value' => 'complaint',   'label' => 'Complaint',    'icon' => 'report_problem', 'checked' => false],
      ['value' => 'other',       'label' => 'Other',        'icon' => 'forum',          'checked' => false],
  ];

  // Representative placeholder history — real messages arrive when the
  // contact backend for the member module is wired up. Canonical statuses:
  //   open · in_review · resolved · archived
  $history = [
      [
          'category' => 'request',
          'category_label' => 'Request',
          'category_icon' => 'library_books',
          'subject' => 'Доступ к архиву журналов по экономике',
          'excerpt' => 'Прошу открыть доступ к архивным выпускам журнала «Вопросы экономики» за 2018–2020 годы для работы над магистерской диссертацией.',
          'status' => 'in_review',
          'status_label' => 'In review',
          'date' => 'Apr 18, 2026',
          'ref' => 'KZ-MSG-2026-0412',
          'accent' => true,
      ],
      [
          'category' => 'question',
          'category_label' => 'Question',
          'category_icon' => 'help_center',
          'subject' => 'Hours of the reading room during Nauryz',
          'excerpt' => 'Could you clarify the reading room schedule during the Nauryz holiday week? I plan to work with restricted materials that day.',
          'status' => 'resolved',
          'status_label' => 'Resolved',
          'date' => 'Mar 14, 2026',
          'ref' => 'KZ-MSG-2026-0318',
          'accent' => false,
      ],
      [
          'category' => 'improvement',
          'category_label' => 'Improvement',
          'category_icon' => 'lightbulb',
          'subject' => 'Предложение по добавлению книг по data engineering',
          'excerpt' => 'Было бы полезно пополнить коллекцию современными изданиями по проектированию данных и инженерии ML-платформ.',
          'status' => 'open',
          'status_label' => 'Open',
          'date' => 'Mar 02, 2026',
          'ref' => 'KZ-MSG-2026-0241',
          'accent' => false,
      ],
      [
          'category' => 'complaint',
          'category_label' => 'Complaint',
          'category_icon' => 'report_problem',
          'subject' => 'Reservation system timed out twice',
          'excerpt' => 'The reservation system reported an error while I was trying to place a hold on the latest issue of the socio-economic journal.',
          'status' => 'resolved',
          'status_label' => 'Resolved',
          'date' => 'Feb 09, 2026',
          'ref' => 'KZ-MSG-2026-0174',
          'accent' => false,
      ],
  ];

  $openCount = collect($history)->whereIn('status', ['open', 'in_review'])->count();
@endphp

@section('content')
  <!-- Header -->
  <header class="mb-12 md:mb-16">
    <div class="inline-flex items-center gap-2 px-3 py-1 bg-surface-container-high rounded-full mb-6">
      <span class="w-2 h-2 rounded-full bg-secondary"></span>
      <span class="font-label text-xs text-on-surface-variant tracking-widest uppercase">{{ $openCount }} awaiting reply</span>
    </div>
    <h1 class="font-headline text-4xl md:text-[3.5rem] text-primary tracking-tight leading-none mb-4">Messages</h1>
    <p class="font-body text-base md:text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      Submit requests, questions, or suggestions directly to the KazUTB library administration. All correspondence is logged against your reader account and routed to the appropriate curator.
    </p>
  </header>

  <!-- Placeholder disclosure -->
  <p class="font-body italic text-sm text-on-surface-variant mb-10 max-w-3xl">
    The composer below is a representative draft UI. Submission is not persisted yet — backend wiring for the member contact workflow lands in a later phase.
  </p>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16">
    <!-- Composer -->
    <section class="lg:col-span-7">
      <div class="bg-surface-container-lowest p-6 md:p-8 rounded-lg shadow-[0_24px_48px_rgba(0,6,19,0.04)]">
        <h2 class="text-xl md:text-2xl font-headline text-primary mb-6 border-b border-outline-variant/20 pb-4">Draft a new message</h2>
        <form class="space-y-6" onsubmit="event.preventDefault();">
          <!-- Category -->
          <div class="space-y-3">
            <label class="block text-sm font-semibold text-primary font-body uppercase tracking-wider">Nature of inquiry</label>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
              @foreach ($categories as $cat)
                <label class="cursor-pointer relative">
                  <input {{ $cat['checked'] ? 'checked' : '' }} class="peer sr-only" name="category" type="radio" value="{{ $cat['value'] }}" />
                  <div class="p-4 bg-surface rounded-md border border-outline-variant/20 peer-checked:border-secondary peer-checked:bg-secondary/5 transition-all duration-300 text-center">
                    <span class="material-symbols-outlined block text-2xl mb-1 text-on-surface-variant peer-checked:text-secondary transition-colors">{{ $cat['icon'] }}</span>
                    <span class="text-sm font-medium text-primary block">{{ $cat['label'] }}</span>
                  </div>
                </label>
              @endforeach
            </div>
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-sm font-semibold text-primary font-body uppercase tracking-wider mb-2" for="messageSubject">Subject</label>
            <input class="w-full bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 px-4 py-3 text-primary placeholder-on-surface-variant/50 transition-colors duration-300" id="messageSubject" name="subject" placeholder="e.g. Access to economics journal archive" type="text" />
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-semibold text-primary font-body uppercase tracking-wider mb-2" for="messageBody">Message</label>
            <textarea class="w-full bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 px-4 py-3 text-primary placeholder-on-surface-variant/50 transition-colors duration-300 resize-none" id="messageBody" name="body" placeholder="Provide context or specific details regarding your inquiry…" rows="6"></textarea>
          </div>

          <div class="flex items-start gap-3 text-xs text-on-surface-variant">
            <span class="material-symbols-outlined text-sm">info</span>
            <p class="max-w-lg">
              Your message is routed to the library administration. A reply will arrive both in the Notifications tab and to your institutional email.
            </p>
          </div>

          <!-- Actions -->
          <div class="flex justify-end pt-2">
            <button class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-8 py-3 rounded-md font-medium text-sm hover:opacity-90 transition-opacity flex items-center gap-2 shadow-[0_8px_16px_rgba(0,6,19,0.1)]" type="submit">
              Submit inquiry
              <span class="material-symbols-outlined text-sm">send</span>
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- History -->
    <section class="lg:col-span-5">
      <div class="flex items-end justify-between mb-6 border-b border-outline-variant/20 pb-4">
        <h2 class="text-xl md:text-2xl font-headline text-primary">Correspondence ledger</h2>
        <span class="text-secondary text-sm font-medium flex items-center gap-1">
          View archive <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </span>
      </div>
      <div class="space-y-5">
        @foreach ($history as $msg)
          @php
            $statusClass = match ($msg['status']) {
                'open' => 'bg-secondary/10 text-secondary',
                'in_review' => 'bg-primary-fixed/40 text-primary',
                'resolved' => 'bg-surface-container-high text-on-surface-variant',
                'archived' => 'bg-surface-container-highest text-on-surface-variant/80',
                default => 'bg-surface-container-high text-on-surface-variant',
            };
          @endphp
          <article class="bg-surface p-5 md:p-6 rounded-lg border border-outline-variant/10 relative overflow-hidden {{ $msg['accent'] ? 'bg-surface-container-lowest' : '' }}">
            @if ($msg['accent'])
              <div class="absolute top-0 left-0 w-1 h-full bg-secondary"></div>
            @endif
            <div class="flex justify-between items-start mb-3 gap-3 {{ $msg['accent'] ? 'pl-2' : '' }}">
              <div class="flex items-center gap-2 min-w-0">
                <span class="material-symbols-outlined text-on-surface-variant text-lg">{{ $msg['category_icon'] }}</span>
                <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">{{ $msg['category_label'] }}</span>
              </div>
              <span class="px-2.5 py-1 text-[10px] font-bold rounded-sm uppercase tracking-widest {{ $statusClass }}">
                {{ $msg['status_label'] }}
              </span>
            </div>
            <h3 class="text-base md:text-lg font-headline text-primary mb-2 {{ $msg['accent'] ? 'pl-2' : '' }}">{{ $msg['subject'] }}</h3>
            <p class="text-sm text-on-surface-variant mb-4 {{ $msg['accent'] ? 'pl-2' : '' }}">{{ $msg['excerpt'] }}</p>
            <div class="flex items-center gap-4 text-xs text-on-surface-variant/70 {{ $msg['accent'] ? 'pl-2' : '' }}">
              <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">calendar_today</span> {{ $msg['date'] }}</span>
              <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">tag</span> {{ $msg['ref'] }}</span>
            </div>
          </article>
        @endforeach
      </div>
    </section>
  </div>
@endsection
