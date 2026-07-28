@extends('layouts.admin')

@section('title', 'System Settings — KazUTB Smart Library Admin')

@php
  $integrations = [
    [
      'initial' => 'J',
      'color' => 'bg-primary-container text-on-primary',
      'name' => 'JSTOR Data API',
      'meta' => 'Last synced: 2 hours ago',
      'status' => 'connected',
    ],
    [
      'initial' => 'E',
      'color' => 'bg-surface-tint text-on-primary',
      'name' => 'EBSCOhost Integration',
      'meta' => 'Last synced: 12 mins ago',
      'status' => 'connected',
    ],
    [
      'initial' => 'S',
      'color' => 'bg-secondary-container text-on-secondary-container',
      'name' => 'Scopus Metadata Feed',
      'meta' => 'Last synced: yesterday · rate-limited',
      'status' => 'degraded',
    ],
    [
      'initial' => 'K',
      'color' => 'bg-primary-fixed text-on-primary-fixed',
      'name' => 'KazNEB National Repository',
      'meta' => 'Last synced: 3 days ago',
      'status' => 'connected',
    ],
  ];

  $branches = [
    ['name' => 'Main Library — Astana Campus', 'code' => 'KZ-AST-01', 'fund' => 'General Fund · Reference Fund', 'capacity' => '120 seats · 14 stacks'],
    ['name' => 'Engineering Reading Hall', 'code' => 'KZ-AST-ENG', 'fund' => 'Technical Fund', 'capacity' => '48 seats · 6 stacks'],
    ['name' => 'Rare Collections Vault C', 'code' => 'KZ-AST-RC', 'fund' => 'Preservation Fund · Restricted', 'capacity' => '8 seats · supervised access'],
    ['name' => 'Digital Lab & Repository Hub', 'code' => 'KZ-AST-DL', 'fund' => 'Digital Assets Fund', 'capacity' => '36 workstations'],
  ];
@endphp

@section('content')
  {{-- Hero --}}
  <div class="mb-12">
    <h1 class="font-headline text-5xl md:text-6xl text-primary leading-none mb-4 tracking-tight">System Settings</h1>
    <p class="font-body text-lg text-on-surface-variant max-w-2xl leading-relaxed">
      Manage external integrations, institutional structure, and global authentication protocols across the KazUTB Smart Library network.
    </p>
  </div>

  {{-- Tabs (visual) --}}
  <div class="flex border-b border-outline-variant/20 mb-10 overflow-x-auto">
    <button type="button" class="px-6 py-3 font-body text-sm font-semibold text-secondary border-b-2 border-secondary whitespace-nowrap">Integrations</button>
    <button type="button" class="px-6 py-3 font-body text-sm font-medium text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap border-b-2 border-transparent">Institutional Structure</button>
    <button type="button" class="px-6 py-3 font-body text-sm font-medium text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap border-b-2 border-transparent">Authentication</button>
    <button type="button" class="px-6 py-3 font-body text-sm font-medium text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap border-b-2 border-transparent">Global Preferences</button>
  </div>

  {{-- Bento grid --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    {{-- Main column --}}
    <div class="lg:col-span-2 space-y-8">

      {{-- CRM Authentication --}}
      <section class="bg-surface-container-lowest p-8 rounded-xl shadow-[0_8px_30px_rgba(0,31,63,0.04)] relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-secondary/5 rounded-bl-[100px] -z-10 transition-transform duration-700 group-hover:scale-110"></div>
        <div class="flex justify-between items-start mb-6 gap-4">
          <div>
            <h2 class="font-body text-xl font-bold text-primary flex items-center gap-3">
              <span class="material-symbols-outlined text-secondary">sync_alt</span>
              CRM Authentication
            </h2>
            <p class="text-sm text-on-surface-variant mt-2 max-w-xl">
              Bridge to the KazUTB corporate CRM at 10.0.1.47 — authoritative identity source for all institutional logins.
            </p>
          </div>
          <span class="px-3 py-1 bg-secondary/10 text-secondary text-xs font-bold rounded-full uppercase tracking-wider whitespace-nowrap">Active</span>
        </div>

        <div class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-2 uppercase tracking-wide">Login Endpoint</label>
              <input type="text" readonly value="http://10.0.1.47/api/login"
                     class="w-full bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 px-4 py-3 text-sm text-on-surface outline-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-2 uppercase tracking-wide">Bearer Token (masked)</label>
              <div class="relative">
                <input type="password" value="************************"
                       class="w-full bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 px-4 py-3 text-sm text-on-surface outline-none pr-10" />
                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors" title="Reveal token">
                  <span class="material-symbols-outlined text-[20px]">visibility_off</span>
                </button>
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-2 uppercase tracking-wide">Device Name</label>
              <input type="text" readonly value="web"
                     class="w-full bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 px-4 py-3 text-sm text-on-surface outline-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-2 uppercase tracking-wide">Fallback Demo Login</label>
              <div class="flex items-center justify-between bg-surface-container-highest px-4 py-3 border-b border-outline-variant/20">
                <span class="text-sm text-on-surface">Enabled for staging</span>
                <button type="button" class="w-11 h-6 bg-secondary/30 rounded-full relative transition-colors" aria-pressed="true">
                  <span class="absolute right-1 top-1 w-4 h-4 bg-secondary rounded-full"></span>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-3 pt-2">
            <button type="button" class="px-6 py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-semibold text-sm hover:opacity-90 transition-opacity shadow-sm">Test Connection</button>
            <button type="button" class="px-6 py-2 border border-outline-variant/20 text-secondary rounded-md font-semibold text-sm hover:bg-surface-variant transition-colors">Rotate Bearer Token</button>
            <button type="button" class="px-6 py-2 border border-outline-variant/20 text-on-surface rounded-md font-semibold text-sm hover:bg-surface-variant transition-colors">View Sync Log</button>
          </div>
        </div>
      </section>

      {{-- External Research Repositories --}}
      <section class="bg-surface-container-lowest p-8 rounded-xl shadow-[0_8px_30px_rgba(0,31,63,0.04)]">
        <div class="flex justify-between items-end mb-6 gap-4">
          <div>
            <h2 class="font-body text-xl font-bold text-primary flex items-center gap-3">
              <span class="material-symbols-outlined text-secondary">public</span>
              Research Repositories
            </h2>
            <p class="text-sm text-on-surface-variant mt-2 max-w-xl">
              Configured endpoints for licensed academic databases and national repositories consulted through the federated search layer.
            </p>
          </div>
          <button type="button" class="text-secondary text-sm font-semibold flex items-center gap-1 hover:text-primary transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">add</span> Add Source
          </button>
        </div>

        <div class="space-y-3">
          @foreach ($integrations as $item)
            <div class="flex items-center justify-between p-4 bg-surface rounded-lg group hover:bg-surface-container-high transition-colors">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded flex items-center justify-center {{ $item['color'] }}">
                  <span class="font-headline font-bold">{{ $item['initial'] }}</span>
                </div>
                <div>
                  <h3 class="font-semibold text-primary text-sm">{{ $item['name'] }}</h3>
                  <p class="text-xs text-on-surface-variant mt-0.5">{{ $item['meta'] }}</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                @if ($item['status'] === 'degraded')
                  <span class="text-xs font-semibold text-error bg-error-container/40 px-2 py-1 rounded">Degraded</span>
                @else
                  <span class="text-xs font-semibold text-secondary bg-secondary/10 px-2 py-1 rounded">Connected</span>
                @endif
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button type="button" class="text-on-surface-variant hover:text-secondary" title="Edit source">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                  </button>
                  <button type="button" class="text-on-surface-variant hover:text-error" title="Remove source">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                  </button>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </section>

      {{-- Institutional Structure — branches & funds --}}
      <section class="bg-surface-container-lowest p-8 rounded-xl shadow-[0_8px_30px_rgba(0,31,63,0.04)]">
        <div class="flex justify-between items-end mb-6 gap-4">
          <div>
            <h2 class="font-body text-xl font-bold text-primary flex items-center gap-3">
              <span class="material-symbols-outlined text-secondary">account_tree</span>
              Institutional Structure
            </h2>
            <p class="text-sm text-on-surface-variant mt-2 max-w-xl">
              Library points, collection funds, and physical locations registered in the platform. These definitions govern shelving, holdings, and circulation routing.
            </p>
          </div>
          <button type="button" class="text-secondary text-sm font-semibold flex items-center gap-1 hover:text-primary transition-colors whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">add_location_alt</span> Register Point
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          @foreach ($branches as $branch)
            <div class="p-5 bg-surface rounded-lg border border-outline-variant/10 hover:border-secondary/40 transition-colors">
              <div class="flex items-start justify-between gap-3 mb-2">
                <h3 class="font-headline text-lg text-primary leading-tight">{{ $branch['name'] }}</h3>
                <span class="text-[10px] font-mono text-on-surface-variant bg-surface-container-high px-2 py-0.5 rounded whitespace-nowrap">{{ $branch['code'] }}</span>
              </div>
              <p class="text-xs text-on-surface-variant mb-1">
                <span class="font-semibold uppercase tracking-wider">Fund ·</span> {{ $branch['fund'] }}
              </p>
              <p class="text-xs text-on-surface-variant">
                <span class="font-semibold uppercase tracking-wider">Capacity ·</span> {{ $branch['capacity'] }}
              </p>
            </div>
          @endforeach
        </div>
      </section>
    </div>

    {{-- Side column --}}
    <div class="space-y-8">

      {{-- System health --}}
      <div class="bg-surface-container-lowest p-6 rounded-xl shadow-[0_8px_30px_rgba(0,31,63,0.04)]">
        <h2 class="font-headline text-xl text-primary font-bold mb-4">System Health</h2>
        <div class="space-y-5">
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-on-surface-variant font-medium">CRM API Rate Limit</span>
              <span class="text-primary font-bold">42%</span>
            </div>
            <div class="w-full bg-surface-container-highest h-1.5 rounded-full overflow-hidden">
              <div class="bg-secondary h-full rounded-full" style="width: 42%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-on-surface-variant font-medium">Repository Storage</span>
              <span class="text-primary font-bold">88%</span>
            </div>
            <div class="w-full bg-surface-container-highest h-1.5 rounded-full overflow-hidden">
              <div class="bg-primary h-full rounded-full" style="width: 88%"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-on-surface-variant font-medium">Search Index Coverage</span>
              <span class="text-primary font-bold">96%</span>
            </div>
            <div class="w-full bg-surface-container-highest h-1.5 rounded-full overflow-hidden">
              <div class="bg-secondary h-full rounded-full" style="width: 96%"></div>
            </div>
          </div>
        </div>
      </div>

      {{-- Operational policies --}}
      <div class="bg-surface-container-lowest p-6 rounded-xl shadow-[0_8px_30px_rgba(0,31,63,0.04)]">
        <h2 class="font-headline text-xl text-primary font-bold mb-4">Operational Policies</h2>
        <div class="space-y-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-primary">Controlled Digital Viewer</p>
              <p class="text-xs text-on-surface-variant">No download; inline streamed PDFs only.</p>
            </div>
            <button type="button" class="w-11 h-6 bg-secondary/30 rounded-full relative" aria-pressed="true">
              <span class="absolute right-1 top-1 w-4 h-4 bg-secondary rounded-full"></span>
            </button>
          </div>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-primary">Campus-only Licensed Content</p>
              <p class="text-xs text-on-surface-variant">Restrict external DB access to institutional IP ranges.</p>
            </div>
            <button type="button" class="w-11 h-6 bg-secondary/30 rounded-full relative" aria-pressed="true">
              <span class="absolute right-1 top-1 w-4 h-4 bg-secondary rounded-full"></span>
            </button>
          </div>
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-primary">Guest Shortlist Persistence</p>
              <p class="text-xs text-on-surface-variant">Keep session shortlists for unauthenticated visitors.</p>
            </div>
            <button type="button" class="w-11 h-6 bg-surface-container-high rounded-full relative" aria-pressed="false">
              <span class="absolute left-1 top-1 w-4 h-4 bg-outline rounded-full"></span>
            </button>
          </div>
        </div>
      </div>

      {{-- Maintenance --}}
      <div class="bg-surface-container-low p-6 rounded-xl border border-outline-variant/10">
        <h2 class="font-headline text-xl text-primary font-bold mb-2">Maintenance</h2>
        <p class="text-sm text-on-surface-variant mb-6">Perform routine system cache clears and index rebuilds.</p>
        <div class="space-y-3">
          <button type="button" class="w-full flex items-center justify-between p-3 bg-surface-container-lowest rounded-md text-sm font-semibold text-primary hover:bg-surface transition-colors shadow-sm">
            <span>Rebuild Search Index</span>
            <span class="material-symbols-outlined text-secondary text-[20px]">manage_search</span>
          </button>
          <button type="button" class="w-full flex items-center justify-between p-3 bg-surface-container-lowest rounded-md text-sm font-semibold text-primary hover:bg-surface transition-colors shadow-sm">
            <span>Clear Global Cache</span>
            <span class="material-symbols-outlined text-secondary text-[20px]">cleaning_services</span>
          </button>
          <button type="button" class="w-full flex items-center justify-between p-3 bg-surface-container-lowest rounded-md text-sm font-semibold text-primary hover:bg-surface transition-colors shadow-sm">
            <span>Recompute Holdings Snapshot</span>
            <span class="material-symbols-outlined text-secondary text-[20px]">inventory</span>
          </button>
          <button type="button" class="w-full flex items-center justify-between p-3 bg-surface-container-lowest rounded-md text-sm font-semibold text-primary hover:bg-surface transition-colors shadow-sm">
            <span>Dispatch Audit Log Export</span>
            <span class="material-symbols-outlined text-secondary text-[20px]">description</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="h-16"></div>
@endsection
