@extends('layouts.admin', ['title' => 'Governance Dashboard — KazUTB Smart Library'])

@php
  $metrics = [
      ['label' => 'Active Patrons', 'value' => '12,450', 'note' => 'Across all faculties this semester', 'icon' => 'group', 'delta' => '+4.2%', 'tone' => 'surface'],
      ['label' => 'Circulation Rate', 'value' => '8,291', 'note' => 'Active checkouts & digital leases', 'icon' => 'swap_horiz', 'delta' => '+1.8%', 'tone' => 'surface'],
      ['label' => 'Facility Reservations', 'value' => '142', 'note' => 'Study rooms & archive access requests', 'icon' => 'event_available', 'delta' => 'Pending Action', 'tone' => 'primary'],
  ];

  $healthItems = [
      ['title' => 'Main Database Cluster', 'subtitle' => 'KazUTB Core Index', 'status' => '12ms response', 'icon' => 'database'],
      ['title' => 'Digital Assets CDN', 'subtitle' => 'Controlled repository delivery', 'status' => '4ms response', 'icon' => 'cloud_sync'],
      ['title' => 'Authentication Gateway', 'subtitle' => 'CRM/SSO integration boundary', 'status' => 'Nominal', 'icon' => 'vpn_key'],
  ];

  $queueItems = [
      ['title' => 'Policy Update Approval', 'body' => 'Review proposed changes to digital lending limits for undergraduate students.', 'time' => '2 hours ago', 'icon' => 'gavel'],
      ['title' => 'Archive Transfer Request', 'body' => 'History Department requesting transfer of 19th Century periodicals to deep storage.', 'time' => 'Yesterday', 'icon' => 'inventory_2'],
  ];

  $recentActions = [
      ['title' => 'RBAC sync reviewed', 'body' => 'Administrator access matrix was checked against current staff identities.', 'time' => 'Today · 09:40'],
      ['title' => 'Catalog UI update published', 'body' => 'Animated catalog cover rollout is active in the public discovery surface.', 'time' => 'Today · 08:15'],
      ['title' => 'Monthly governance export prepared', 'body' => 'Operational summary for circulation and holdings is ready for review.', 'time' => 'Yesterday'],
  ];

  $alerts = [
      ['title' => 'Metadata backlog', 'body' => '27 records still need UDC normalization before the next stewardship wave.', 'tone' => 'amber'],
      ['title' => 'Feedback review pending', 'body' => '5 reader messages are waiting for admin triage and assignment.', 'tone' => 'teal'],
      ['title' => 'Integration notice', 'body' => 'No public API incident detected. CRM boundary remains nominal.', 'tone' => 'slate'],
  ];
@endphp

@section('content')
  <div class="mb-12 flex justify-between items-end gap-6">
    <div>
      <h1 class="font-headline text-[3.5rem] leading-tight text-primary tracking-tight">Governance Overview</h1>
      <p class="font-body text-[1rem] text-on-surface-variant mt-2 max-w-2xl">High-level telemetry for the KazUTB digital and physical academic collections. This is the primary governance and control layer inside the library platform.</p>
    </div>
    <div class="hidden md:flex gap-3">
      <a href="{{ route('admin.reports') }}" class="px-5 py-2 rounded-md border border-outline-variant/20 text-secondary font-medium text-sm flex items-center gap-2 hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined text-[18px]">download</span>
        <span>Export Report</span>
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
    @foreach ($metrics as $metric)
      <div class="{{ $metric['tone'] === 'primary' ? 'bg-gradient-to-br from-primary to-primary-container text-on-primary' : 'bg-surface-container-lowest text-on-surface' }} rounded-xl p-8 flex flex-col justify-between hover:bg-surface-container-low transition-colors duration-500 relative overflow-hidden min-h-[200px]">
        <div class="flex justify-between items-start mb-6 relative z-10">
          <div class="w-12 h-12 rounded-lg {{ $metric['tone'] === 'primary' ? 'bg-white/10 text-on-primary' : 'bg-surface-container-high text-primary' }} flex items-center justify-center">
            <span class="material-symbols-outlined">{{ $metric['icon'] }}</span>
          </div>
          <span class="text-xs font-bold {{ $metric['tone'] === 'primary' ? 'text-on-primary/80' : 'text-secondary bg-secondary-container/20' }} px-2 py-1 rounded-full">{{ $metric['delta'] }}</span>
        </div>
        <div class="relative z-10">
          <p class="font-body text-sm {{ $metric['tone'] === 'primary' ? 'text-on-primary/70' : 'text-on-surface-variant' }} mb-1 uppercase tracking-wider">{{ $metric['label'] }}</p>
          <h3 class="font-headline text-4xl {{ $metric['tone'] === 'primary' ? 'text-on-primary' : 'text-primary' }} font-medium">{{ $metric['value'] }}</h3>
          <p class="font-body text-xs {{ $metric['tone'] === 'primary' ? 'text-on-primary/70' : 'text-on-surface-variant' }} mt-2">{{ $metric['note'] }}</p>
        </div>
      </div>
    @endforeach
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 mb-16">
    <div class="lg:col-span-7 flex flex-col gap-12">
      <section>
        <div class="flex items-center gap-3 mb-6">
          <span class="material-symbols-outlined text-primary text-[28px]">monitor_heart</span>
          <h2 class="font-headline text-[1.75rem] text-primary">Platform Health</h2>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-6">
          <div class="flex items-center justify-between mb-8 pb-4 border-b border-surface-container-highest">
            <div>
              <h3 class="font-body text-lg font-bold text-primary">System Status</h3>
              <p class="text-sm text-on-surface-variant">All primary services operational</p>
            </div>
            <div class="flex items-center gap-2 text-secondary">
              <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-secondary opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-secondary"></span>
              </span>
              <span class="text-sm font-medium">99.9% Uptime</span>
            </div>
          </div>
          <div class="space-y-6">
            @foreach ($healthItems as $item)
              <div class="flex justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 rounded-md bg-surface-container-low flex items-center justify-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
                  </div>
                  <div>
                    <p class="font-medium text-primary text-sm">{{ $item['title'] }}</p>
                    <p class="text-xs text-on-surface-variant">{{ $item['subtitle'] }}</p>
                  </div>
                </div>
                <span class="text-sm text-on-surface-variant">{{ $item['status'] }}</span>
              </div>
            @endforeach
          </div>
        </div>
      </section>
    </div>

    <div class="lg:col-span-5 flex flex-col gap-12">
      <section>
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary text-[28px]">pending_actions</span>
            <h2 class="font-headline text-[1.75rem] text-primary">Governance Queue</h2>
          </div>
          <a href="{{ route('admin.logs') }}" class="text-secondary text-sm font-medium hover:underline">View All</a>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-2 flex flex-col gap-2">
          @foreach ($queueItems as $item)
            <div class="p-4 rounded-lg hover:bg-surface-container-low transition-colors flex gap-4 cursor-pointer">
              <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center flex-shrink-0 text-primary mt-1">
                <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
              </div>
              <div>
                <h4 class="font-bold text-sm text-primary mb-1">{{ $item['title'] }}</h4>
                <p class="text-xs text-on-surface-variant mb-3 line-clamp-2">{{ $item['body'] }}</p>
                <div class="flex gap-2">
                  <a href="{{ route('admin.logs') }}" class="text-xs font-medium text-secondary hover:text-primary transition-colors">Review</a>
                  <span class="text-outline-variant/50">•</span>
                  <span class="text-xs text-on-surface-variant">{{ $item['time'] }}</span>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </section>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
    <section class="lg:col-span-7">
      <div class="flex items-center gap-3 mb-6">
        <span class="material-symbols-outlined text-primary text-[28px]">history</span>
        <h2 class="font-headline text-[1.75rem] text-primary">Recent Administrative Actions</h2>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-2 flex flex-col gap-2">
        @foreach ($recentActions as $item)
          <div class="p-4 rounded-lg hover:bg-surface-container-low transition-colors">
            <div class="flex items-center justify-between gap-3 mb-2">
              <h4 class="font-bold text-sm text-primary">{{ $item['title'] }}</h4>
              <span class="text-xs text-on-surface-variant">{{ $item['time'] }}</span>
            </div>
            <p class="text-sm text-on-surface-variant">{{ $item['body'] }}</p>
          </div>
        @endforeach
      </div>
    </section>

    <section class="lg:col-span-5">
      <div class="flex items-center gap-3 mb-6">
        <span class="material-symbols-outlined text-primary text-[28px]">warning</span>
        <h2 class="font-headline text-[1.75rem] text-primary">Alerts & Issues</h2>
      </div>
      <div class="bg-surface-container-lowest rounded-xl p-2 flex flex-col gap-2">
        @foreach ($alerts as $item)
          <div class="p-4 rounded-lg border {{ $item['tone'] === 'amber' ? 'border-amber-200 bg-amber-50/70' : ($item['tone'] === 'teal' ? 'border-teal-200 bg-teal-50/70' : 'border-slate-200 bg-slate-50/70') }}">
            <h4 class="font-bold text-sm text-primary mb-1">{{ $item['title'] }}</h4>
            <p class="text-xs text-on-surface-variant leading-6">{{ $item['body'] }}</p>
          </div>
        @endforeach
      </div>
    </section>
  </div>
@endsection
