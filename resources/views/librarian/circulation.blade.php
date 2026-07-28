@extends('layouts.librarian', ['title' => 'Circulation Desk — KazUTB Smart Library'])

@php
  $deskWindow = 'Desk Active: 09:00 — 18:00';

  $reader = [
      'name' => 'Aruzhan Kasymova',
      'program' => 'Undergraduate • Computer Science',
      'reader_id' => 'KUTB-2021-4829',
      'loan_limit' => '2 / 5 Items',
      'fines' => '0.00 KZT',
      'status' => 'Active',
      'avatar_initial' => 'А',
  ];

  $currentLoans = [
      [
          'title' => 'Operating System Concepts',
          'author' => 'Silberschatz, A.',
          'barcode' => '10048291',
          'due' => 'Due: 24 Oct 2026 (in 5 days)',
          'state' => 'active',
          'action' => 'Renew',
      ],
      [
          'title' => 'Introduction to Algorithms',
          'author' => 'Cormen, T.',
          'barcode' => '10039284',
          'due' => 'Overdue: 15 Oct 2026 (4 days late)',
          'state' => 'overdue',
          'action' => 'Return',
      ],
  ];

  $recentTransactions = [
      [
          'kind' => 'issue',
          'icon' => 'outbox',
          'title' => 'Principles of Economics',
          'body' => 'Issued to: Aibek N. (ID: 9482) • 2m ago',
      ],
      [
          'kind' => 'return',
          'icon' => 'move_to_inbox',
          'title' => 'Data Structures in C++',
          'body' => 'Returned by: Zhanel T. (ID: 1104) • 15m ago',
      ],
      [
          'kind' => 'issue',
          'icon' => 'outbox',
          'title' => 'Linear Algebra Done Right',
          'body' => 'Issued to: Madina S. (ID: 7731) • 42m ago',
      ],
  ];
@endphp

@section('content')
  <div class="max-w-7xl mx-auto w-full flex flex-col gap-8">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-baseline md:justify-between gap-4 mb-2">
      <div>
        <h1 class="font-headline text-4xl text-primary-container mb-2">Circulation Desk</h1>
        <p class="font-body text-on-surface-variant">Manage checkouts, returns, and reader accounts.</p>
      </div>
      <div class="text-sm font-body text-on-surface-variant inline-flex items-center gap-2 bg-surface-container-low px-3 py-1.5 rounded-full self-start md:self-auto">
        <span class="material-symbols-outlined text-sm">schedule</span>
        <span>{{ $deskWindow }}</span>
      </div>
    </div>

    <!-- Two-column workstation -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Left: Rapid Scan + Recent -->
      <div class="lg:col-span-5 flex flex-col gap-8">
        <!-- Rapid Scan -->
        <section class="bg-surface-container-lowest p-6 rounded-xl shadow-sm flex flex-col gap-6">
          <h2 class="font-headline text-2xl text-primary-container border-b border-surface-container-high pb-4">Rapid Scan</h2>

          <form class="space-y-6" onsubmit="return false;" aria-label="Circulation scan inputs">
            <div class="relative bg-surface-container-low p-4 rounded-lg border border-outline-variant/20 focus-within:border-secondary transition-colors">
              <label class="block text-xs font-bold text-on-surface-variant mb-1 uppercase tracking-wider" for="circ-reader-id">Reader ID / Barcode</label>
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-secondary">badge</span>
                <input id="circ-reader-id" autofocus class="w-full bg-transparent border-none focus:ring-0 text-primary-container font-body text-lg placeholder-outline p-0" placeholder="Scan or type ID..." type="text" autocomplete="off" />
              </div>
            </div>

            <div class="relative bg-surface-container-low p-4 rounded-lg border border-outline-variant/20 focus-within:border-secondary transition-colors">
              <label class="block text-xs font-bold text-on-surface-variant mb-1 uppercase tracking-wider" for="circ-item-barcode">Item Barcode</label>
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-secondary">barcode_scanner</span>
                <input id="circ-item-barcode" class="w-full bg-transparent border-none focus:ring-0 text-primary-container font-body text-lg placeholder-outline p-0" placeholder="Scan item barcode..." type="text" autocomplete="off" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-2">
              <button type="button" class="bg-gradient-to-br from-primary to-primary-container text-on-primary py-4 rounded-md font-medium text-lg inline-flex items-center justify-center gap-2 hover:opacity-90 transition-opacity">
                <span class="material-symbols-outlined">outbox</span>
                <span>Issue</span>
              </button>
              <button type="button" class="bg-surface-container text-primary-container py-4 rounded-md font-medium text-lg inline-flex items-center justify-center gap-2 hover:bg-surface-container-high transition-colors border border-outline-variant/20">
                <span class="material-symbols-outlined">move_to_inbox</span>
                <span>Return</span>
              </button>
            </div>
          </form>
        </section>

        <!-- Recent Transactions -->
        <section class="bg-surface-container-lowest p-6 rounded-xl shadow-sm flex-1">
          <div class="flex justify-between items-center mb-6">
            <h2 class="font-headline text-xl text-primary-container">Recent Transactions</h2>
            <a href="{{ route('librarian.circulation') }}" class="text-secondary text-sm font-medium hover:underline">View All</a>
          </div>
          <ul class="space-y-4" aria-label="Recent transactions">
            @foreach ($recentTransactions as $tx)
              <li class="flex items-start gap-4 p-3 hover:bg-surface-container-low rounded-lg transition-colors group">
                <div class="w-8 h-8 rounded-full {{ $tx['kind'] === 'issue' ? 'bg-tertiary-fixed text-on-tertiary-fixed' : 'bg-surface-container-highest text-on-surface' }} flex items-center justify-center flex-shrink-0 mt-1">
                  <span class="material-symbols-outlined text-sm">{{ $tx['icon'] }}</span>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-primary-container text-sm group-hover:text-secondary transition-colors">{{ $tx['title'] }}</p>
                  <p class="text-xs text-on-surface-variant">{{ $tx['body'] }}</p>
                </div>
              </li>
            @endforeach
          </ul>
        </section>
      </div>

      <!-- Right: Active Reader Profile -->
      <div class="lg:col-span-7 flex flex-col gap-8">
        <section class="bg-surface-container-lowest p-8 rounded-xl shadow-sm flex-1 flex flex-col">
          <!-- Header Profile -->
          <div class="flex items-start gap-6 pb-8 border-b border-surface-container-high mb-8">
            <div class="w-24 h-24 rounded-lg bg-primary-container text-on-primary flex items-center justify-center flex-shrink-0 relative">
              <span class="font-headline text-4xl font-bold">{{ $reader['avatar_initial'] }}</span>
              <div class="absolute bottom-0 inset-x-0 h-1 bg-secondary rounded-b-lg"></div>
            </div>
            <div class="flex-1">
              <div class="flex justify-between items-start">
                <div>
                  <h2 class="font-headline text-3xl text-primary-container mb-1">{{ $reader['name'] }}</h2>
                  <p class="text-on-surface-variant font-medium text-sm inline-flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[16px]">school</span>
                    <span>{{ $reader['program'] }}</span>
                  </p>
                </div>
                <div class="bg-tertiary-fixed text-on-tertiary-fixed px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">{{ $reader['status'] }}</div>
              </div>
              <dl class="flex flex-wrap gap-6 mt-2 text-sm">
                <div class="flex flex-col">
                  <dt class="text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">Reader ID</dt>
                  <dd class="font-mono font-medium text-primary-container">{{ $reader['reader_id'] }}</dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs text-on-surface-variant uppercase tracking-wider mb-0.5">Limit</dt>
                  <dd class="font-medium text-primary-container">{{ $reader['loan_limit'] }}</dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs text-error uppercase tracking-wider mb-0.5">Fines</dt>
                  <dd class="font-medium text-error">{{ $reader['fines'] }}</dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Current Loans -->
          <div class="flex-1 flex flex-col">
            <h3 class="font-headline text-xl text-primary-container mb-6 inline-flex items-center gap-2">
              <span class="material-symbols-outlined">library_books</span>
              <span>Current Loans ({{ count($currentLoans) }})</span>
            </h3>
            <ul class="space-y-4" aria-label="Current loans">
              @foreach ($currentLoans as $loan)
                @php $isOverdue = $loan['state'] === 'overdue'; @endphp
                <li class="{{ $isOverdue ? 'bg-error-container/20 border-l-4 border-error' : 'bg-surface border-l-4 border-secondary' }} p-5 rounded-r-lg flex justify-between items-center group transition-colors hover:{{ $isOverdue ? 'bg-error-container/30' : 'bg-surface-container-low' }}">
                  <div class="flex gap-4 items-center">
                    <div class="w-12 h-16 bg-surface-container-high rounded overflow-hidden flex items-center justify-center">
                      <span class="material-symbols-outlined text-outline">menu_book</span>
                    </div>
                    <div>
                      <h4 class="font-bold text-primary-container text-base">{{ $loan['title'] }}</h4>
                      <p class="text-xs text-on-surface-variant mb-1">{{ $loan['author'] }} • Barcode: {{ $loan['barcode'] }}</p>
                      <div class="flex items-center gap-2 text-xs {{ $isOverdue ? 'font-bold text-error' : 'font-medium text-on-surface-variant' }}">
                        <span class="material-symbols-outlined text-[14px]">{{ $isOverdue ? 'warning' : 'event' }}</span>
                        <span>{{ $loan['due'] }}</span>
                      </div>
                    </div>
                  </div>
                  <button type="button" class="px-4 py-2 text-sm font-medium text-primary-container hover:bg-surface-variant rounded transition-colors border border-outline-variant/20">{{ $loan['action'] }}</button>
                </li>
              @endforeach
            </ul>
          </div>

          <!-- Context actions -->
          <div class="mt-8 pt-6 border-t border-surface-container-high flex justify-end gap-4">
            <button type="button" class="text-primary-container px-6 py-2 rounded font-medium hover:bg-surface-container-high transition-colors text-sm">Clear Session</button>
            <button type="button" class="text-secondary px-6 py-2 rounded font-medium hover:bg-secondary/10 transition-colors text-sm border border-outline-variant/20">View Full Profile</button>
          </div>
        </section>
      </div>
    </div>
  </div>
@endsection
