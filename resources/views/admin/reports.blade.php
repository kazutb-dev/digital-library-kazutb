@extends('layouts.admin')

@section('title', 'Reports & Analytics — KazUTB Smart Library Admin')

@php
  $metrics = [
    ['label' => 'Total Circulation', 'value' => '124 592', 'icon' => 'auto_stories', 'delta' => '+12.4%', 'deltaLabel' => 'vs previous academic term', 'trend' => 'up'],
    ['label' => 'Active Digital Patrons', 'value' => '8 941', 'icon' => 'devices', 'delta' => '+5.2%', 'deltaLabel' => 'vs previous academic term', 'trend' => 'up'],
    ['label' => 'Acquisition Fund Utilization', 'value' => '68%', 'icon' => 'account_balance', 'delta' => null, 'deltaLabel' => 'On track for fiscal year', 'trend' => 'flat'],
    ['label' => 'Controlled Digital Views', 'value' => '3 412', 'icon' => 'visibility', 'delta' => '+8.9%', 'deltaLabel' => 'vs previous academic term', 'trend' => 'up'],
  ];

  $trend = [
    ['month' => 'Сен', 'height' => 40, 'tone' => 'bg-primary/10'],
    ['month' => 'Окт', 'height' => 55, 'tone' => 'bg-primary/30'],
    ['month' => 'Ноя', 'height' => 85, 'tone' => 'bg-secondary/80'],
    ['month' => 'Дек', 'height' => 60, 'tone' => 'bg-primary/40'],
    ['month' => 'Янв', 'height' => 45, 'tone' => 'bg-primary/20'],
    ['month' => 'Фев', 'height' => 70, 'tone' => 'bg-primary/60'],
    ['month' => 'Мар', 'height' => 82, 'tone' => 'bg-primary/70'],
    ['month' => 'Апр', 'height' => 95, 'tone' => 'bg-primary/80'],
  ];

  $allocation = [
    ['label' => 'Licensed Digital Journals', 'pct' => 45, 'tone' => 'bg-primary'],
    ['label' => 'Physical Monographs', 'pct' => 30, 'tone' => 'bg-primary/70'],
    ['label' => 'Database Subscriptions', 'pct' => 15, 'tone' => 'bg-secondary'],
    ['label' => 'Rare & Special Collections', 'pct' => 10, 'tone' => 'bg-outline'],
  ];

  $topCollections = [
    ['name' => 'Central Asian Studies Archive', 'faculty' => 'School of Humanities', 'accesses' => '12 450', 'delta' => '+18%', 'trend' => 'up'],
    ['name' => 'Applied Engineering Journals', 'faculty' => 'School of Engineering', 'accesses' => '9 874', 'delta' => '+11%', 'trend' => 'up'],
    ['name' => 'Kazakh Legal Codex Series', 'faculty' => 'School of Law', 'accesses' => '8 211', 'delta' => '+5%', 'trend' => 'up'],
    ['name' => 'Biomedical Research Serials', 'faculty' => 'School of Life Sciences', 'accesses' => '7 904', 'delta' => '0%', 'trend' => 'flat'],
    ['name' => 'Economic Theory Classics', 'faculty' => 'School of Business & Economics', 'accesses' => '6 540', 'delta' => '−2%', 'trend' => 'down'],
    ['name' => 'Digital Humanities Corpora', 'faculty' => 'Interdisciplinary', 'accesses' => '5 321', 'delta' => '+24%', 'trend' => 'up'],
  ];

  $branchBreakdown = [
    ['name' => 'Main Library — Astana Campus', 'loans' => '72 140', 'returns' => '70 998', 'overdue' => '412'],
    ['name' => 'Engineering Reading Hall', 'loans' => '28 705', 'returns' => '28 201', 'overdue' => '163'],
    ['name' => 'Rare Collections Vault C', 'loans' => '1 486', 'returns' => '1 480', 'overdue' => '2'],
    ['name' => 'Digital Lab & Repository Hub', 'loans' => '22 261', 'returns' => '22 098', 'overdue' => '87'],
  ];

  $reportDispatches = [
    ['title' => 'Monthly Circulation Summary', 'cadence' => 'Monthly · 1st business day', 'next' => 'May 1, 2026', 'channel' => 'Governance mailing list'],
    ['title' => 'Acquisition Fund Utilization', 'cadence' => 'Quarterly', 'next' => 'Jul 1, 2026', 'channel' => 'Rector\'s office · CFO'],
    ['title' => 'Stewardship Review Digest', 'cadence' => 'Weekly · Fridays', 'next' => 'Apr 24, 2026', 'channel' => 'Librarians Council'],
    ['title' => 'Digital Viewer Access Audit', 'cadence' => 'Monthly', 'next' => 'May 5, 2026', 'channel' => 'Governance mailing list'],
  ];
@endphp

@section('content')
  {{-- Hero --}}
  <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6">
    <div>
      <h1 class="font-headline text-5xl md:text-6xl leading-none text-primary mb-4 tracking-tight">Reports &amp; Analytics</h1>
      <p class="font-body text-lg text-on-surface-variant max-w-2xl leading-relaxed">
        Institutional insight across circulation, holdings utilization, digital access, and stewardship workload for the KazUTB Smart Library network.
      </p>
    </div>
    <div class="flex gap-3 flex-wrap">
      <button type="button" class="flex items-center gap-2 px-4 py-2 bg-transparent border border-outline-variant/30 text-secondary hover:bg-surface-variant transition-colors rounded-md">
        <span class="material-symbols-outlined text-sm">filter_list</span>
        <span class="text-sm font-semibold">Filter</span>
      </button>
      <button type="button" class="flex items-center gap-2 px-4 py-2 border border-outline-variant/30 text-on-surface hover:bg-surface-variant transition-colors rounded-md">
        <span class="material-symbols-outlined text-sm">date_range</span>
        <span class="text-sm font-semibold">Academic Year '25–'26</span>
      </button>
      <button type="button" class="flex items-center gap-2 px-5 py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md hover:opacity-90 transition-opacity shadow-sm">
        <span class="material-symbols-outlined text-sm">download</span>
        <span class="text-sm font-semibold">Export Report</span>
      </button>
    </div>
  </div>

  {{-- Key Metrics --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
    @foreach ($metrics as $m)
      <div class="bg-surface-container-lowest p-6 rounded-xl shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)] hover:bg-surface-container-high transition-colors duration-300">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-on-surface-variant text-xs font-semibold uppercase tracking-wider">{{ $m['label'] }}</h3>
          <span class="material-symbols-outlined text-secondary opacity-80">{{ $m['icon'] }}</span>
        </div>
        <div class="font-headline text-4xl text-primary leading-none mb-3">{{ $m['value'] }}</div>
        <div class="flex items-center gap-1 text-sm {{ $m['trend'] === 'up' ? 'text-secondary' : 'text-on-surface-variant opacity-70' }}">
          @if ($m['trend'] === 'up')
            <span class="material-symbols-outlined text-xs">trending_up</span>
            <span><span class="font-semibold">{{ $m['delta'] }}</span> · {{ $m['deltaLabel'] }}</span>
          @else
            <span>{{ $m['deltaLabel'] }}</span>
          @endif
        </div>
      </div>
    @endforeach
  </div>

  {{-- Charts bento --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">

    {{-- Circulation Trends --}}
    <div class="lg:col-span-2 bg-surface-container-lowest rounded-xl p-8 shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)] relative overflow-hidden group">
      <div class="flex justify-between items-center mb-8 flex-wrap gap-4">
        <div>
          <h2 class="font-headline text-2xl text-primary">Circulation Trends</h2>
          <p class="text-xs text-on-surface-variant mt-1 uppercase tracking-wider">Monthly aggregate · loans + digital opens</p>
        </div>
        <select class="bg-transparent border-b border-outline-variant/20 text-sm py-1 focus:ring-0 focus:border-secondary text-on-surface-variant pr-8" aria-label="Academic period">
          <option>Academic Year '25–'26</option>
          <option>Fall Semester '25</option>
          <option>Spring Semester '26</option>
        </select>
      </div>

      <div class="h-64 flex items-end gap-3 pt-4 border-l border-b border-outline-variant/20 relative pl-6 pb-6">
        <div class="absolute left-0 bottom-6 -ml-1 text-[10px] text-on-surface-variant opacity-50">0k</div>
        <div class="absolute left-0 top-1/2 -ml-1 text-[10px] text-on-surface-variant opacity-50">50k</div>
        <div class="absolute left-0 top-0 -ml-2 text-[10px] text-on-surface-variant opacity-50">100k</div>
        <div class="absolute w-full h-px bg-outline-variant/10 top-0 left-0"></div>
        <div class="absolute w-full h-px bg-outline-variant/10 top-1/2 left-0"></div>

        @foreach ($trend as $bar)
          <div class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full {{ $bar['tone'] }} rounded-t-sm transition-colors" style="height: {{ $bar['height'] }}%"></div>
            <span class="text-[10px] text-on-surface-variant opacity-60">{{ $bar['month'] }}</span>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Side: Allocation + Insight --}}
    <div class="flex flex-col gap-8">
      <div class="bg-surface-container-lowest rounded-xl p-8 shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)]">
        <h2 class="font-headline text-xl text-primary mb-6">Resource Allocation</h2>
        <div class="space-y-4">
          @foreach ($allocation as $a)
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-on-surface-variant">{{ $a['label'] }}</span>
                <span class="font-semibold text-primary">{{ $a['pct'] }}%</span>
              </div>
              <div class="w-full bg-surface-container h-2 rounded-full overflow-hidden">
                <div class="{{ $a['tone'] }} h-full rounded-full" style="width: {{ $a['pct'] }}%"></div>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="bg-gradient-to-br from-primary to-primary-container rounded-xl p-8 text-on-primary shadow-[0_4px_24px_-4px_rgba(0,31,63,0.2)]">
        <span class="material-symbols-outlined mb-4 text-secondary-fixed">lightbulb</span>
        <h3 class="font-headline text-xl mb-2">Curator's Insight</h3>
        <p class="text-sm text-on-primary/80 leading-relaxed font-body">
          Digital journal access is peaking in the School of Engineering. Consider reviewing current IEEE and Scopus database subscriptions before the next fiscal quarter to consolidate the acquisition fund.
        </p>
      </div>
    </div>
  </div>

  {{-- Branch breakdown --}}
  <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)] overflow-hidden mb-12">
    <div class="p-6 border-b border-surface-container flex justify-between items-center bg-surface/50 flex-wrap gap-4">
      <div>
        <h2 class="font-headline text-xl text-primary">Circulation by Branch</h2>
        <p class="text-xs text-on-surface-variant mt-1 uppercase tracking-wider">Cumulative · academic year to date</p>
      </div>
      <button type="button" class="text-secondary text-sm font-semibold flex items-center gap-1 hover:text-primary transition-colors">
        <span class="material-symbols-outlined text-[18px]">open_in_new</span>
        View Operational Dashboard
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="text-xs text-on-surface-variant uppercase tracking-wider border-b border-surface-container bg-surface/30">
            <th class="px-6 py-4 font-semibold">Branch</th>
            <th class="px-6 py-4 font-semibold text-right">Loans</th>
            <th class="px-6 py-4 font-semibold text-right">Returns</th>
            <th class="px-6 py-4 font-semibold text-right">Overdue</th>
          </tr>
        </thead>
        <tbody class="text-sm">
          @foreach ($branchBreakdown as $b)
            <tr class="border-b border-surface-container last:border-0 hover:bg-surface-container-low transition-colors">
              <td class="px-6 py-4 font-semibold text-primary">{{ $b['name'] }}</td>
              <td class="px-6 py-4 text-right font-mono text-primary">{{ $b['loans'] }}</td>
              <td class="px-6 py-4 text-right font-mono text-on-surface-variant">{{ $b['returns'] }}</td>
              <td class="px-6 py-4 text-right font-mono text-error">{{ $b['overdue'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Top collections --}}
  <div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)] overflow-hidden mb-12">
    <div class="p-6 border-b border-surface-container flex justify-between items-center bg-surface/50 flex-wrap gap-4">
      <div>
        <h2 class="font-headline text-xl text-primary">Top Performing Collections</h2>
        <p class="text-xs text-on-surface-variant mt-1 uppercase tracking-wider">By authenticated digital access · trailing 90 days</p>
      </div>
      <div class="relative">
        <span class="material-symbols-outlined absolute left-0 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
        <input class="bg-transparent border-b border-outline-variant/20 pl-6 pr-4 py-1 text-sm focus:ring-0 focus:border-secondary w-64 text-on-surface placeholder:text-on-surface-variant/60 outline-none" placeholder="Search collections..." type="text" />
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="text-xs text-on-surface-variant uppercase tracking-wider border-b border-surface-container bg-surface/30">
            <th class="px-6 py-4 font-semibold">Collection</th>
            <th class="px-6 py-4 font-semibold">Faculty / Program</th>
            <th class="px-6 py-4 font-semibold text-right">Accesses</th>
            <th class="px-6 py-4 font-semibold text-right">Trend</th>
          </tr>
        </thead>
        <tbody class="text-sm">
          @foreach ($topCollections as $c)
            <tr class="border-b border-surface-container last:border-0 hover:bg-surface-container-low transition-colors">
              <td class="px-6 py-4 font-semibold text-primary">{{ $c['name'] }}</td>
              <td class="px-6 py-4 text-on-surface-variant">{{ $c['faculty'] }}</td>
              <td class="px-6 py-4 text-right font-mono text-primary">{{ $c['accesses'] }}</td>
              <td class="px-6 py-4 text-right
                @if ($c['trend'] === 'up') text-secondary
                @elseif ($c['trend'] === 'down') text-error
                @else text-outline
                @endif">
                <span class="material-symbols-outlined text-sm align-middle">
                  @if ($c['trend'] === 'up') arrow_upward
                  @elseif ($c['trend'] === 'down') arrow_downward
                  @else horizontal_rule
                  @endif
                </span>
                {{ $c['delta'] }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Scheduled dispatches --}}
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
    <div class="lg:col-span-2 bg-surface-container-lowest rounded-xl p-8 shadow-[0_4px_24px_-4px_rgba(0,31,63,0.04)]">
      <div class="flex justify-between items-end mb-6 gap-4 flex-wrap">
        <div>
          <h2 class="font-headline text-xl text-primary">Scheduled Report Dispatches</h2>
          <p class="text-xs text-on-surface-variant mt-1 uppercase tracking-wider">Automated governance deliveries</p>
        </div>
        <button type="button" class="text-secondary text-sm font-semibold flex items-center gap-1 hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-[18px]">schedule_send</span> Configure Dispatch
        </button>
      </div>
      <div class="space-y-3">
        @foreach ($reportDispatches as $r)
          <div class="flex items-center justify-between gap-4 p-4 bg-surface rounded-lg hover:bg-surface-container-high transition-colors">
            <div>
              <h3 class="font-semibold text-primary text-sm">{{ $r['title'] }}</h3>
              <p class="text-xs text-on-surface-variant mt-0.5">{{ $r['cadence'] }} · recipient: {{ $r['channel'] }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-on-surface-variant uppercase tracking-wider">Next dispatch</p>
              <p class="text-sm font-semibold text-primary">{{ $r['next'] }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="bg-surface-container-low p-6 rounded-xl border border-outline-variant/10">
      <h2 class="font-headline text-xl text-primary font-bold mb-2">Data Readiness</h2>
      <p class="text-sm text-on-surface-variant mb-6">Freshness of the aggregation pipeline feeding this report surface.</p>
      <div class="space-y-5">
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="text-on-surface-variant font-medium">Circulation warehouse</span>
            <span class="text-primary font-semibold">up to date</span>
          </div>
          <p class="text-xs text-on-surface-variant/80">Last sync: 2 hours ago · 12 min avg lag</p>
        </div>
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="text-on-surface-variant font-medium">Digital access logs</span>
            <span class="text-primary font-semibold">up to date</span>
          </div>
          <p class="text-xs text-on-surface-variant/80">Last sync: 8 mins ago</p>
        </div>
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="text-on-surface-variant font-medium">Stewardship queue metrics</span>
            <span class="text-error font-semibold">lagging</span>
          </div>
          <p class="text-xs text-on-surface-variant/80">Last sync: 19 hours ago · rebuild scheduled tonight</p>
        </div>
      </div>
    </div>
  </div>

  <div class="h-16"></div>
@endsection
