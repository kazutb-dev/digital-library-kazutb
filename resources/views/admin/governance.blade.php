@extends('layouts.admin')
@section('title', 'Governance & Logs — Admin Portal — KazUTB Smart Library')

@section('content')
  {{-- Page Header --}}
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
    <div class="max-w-2xl">
      <span class="text-secondary font-semibold text-sm tracking-widest uppercase mb-3 block">System Audit</span>
      <h1 class="font-headline text-4xl md:text-5xl text-primary font-bold tracking-tight leading-tight mb-4">Governance, Logs &amp; Monitoring</h1>
      <p class="text-on-surface-variant text-lg font-body leading-relaxed max-w-xl">Comprehensive oversight of system events, security flags, and institutional integration heartbeat.</p>
    </div>
    <div class="flex gap-4">
      <button class="bg-surface-container-highest text-on-surface hover:bg-surface-variant px-5 py-2.5 rounded-md text-sm font-semibold transition-colors duration-300 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">filter_list</span>
        Advanced Filter
      </button>
      <button class="border border-outline-variant/20 text-secondary hover:bg-surface-variant px-5 py-2.5 rounded-md text-sm font-semibold transition-colors duration-300 flex items-center gap-2">
        <span class="material-symbols-outlined text-lg">download</span>
        Export Report
      </button>
    </div>
  </div>

  {{-- Dashboard Grid: System Pulse + Security Flags --}}
  <div class="grid grid-cols-1 md:grid-cols-12 gap-8 mb-16">
    {{-- System Pulse --}}
    <div class="md:col-span-4 bg-surface-container-lowest rounded-xl p-8 flex flex-col justify-between">
      <div>
        <h3 class="font-body text-lg font-bold text-primary mb-2">System Pulse</h3>
        <p class="text-sm text-on-surface-variant mb-8">Real-time integration heartbeat and core service status.</p>
        <div class="space-y-6">
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-on-surface flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-secondary"></span> Authentication
            </span>
            <span class="text-xs text-on-surface-variant font-mono">100% Uptime</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-on-surface flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-secondary"></span> Catalog DB
            </span>
            <span class="text-xs text-on-surface-variant font-mono">14ms Latency</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-on-surface flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-error"></span> External API
            </span>
            <span class="text-xs text-error font-mono flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">warning</span> Degraded
            </span>
          </div>
        </div>
      </div>
    </div>

    {{-- Recent Security Flags --}}
    <div class="md:col-span-8 bg-surface-container-low rounded-xl p-8">
      <div class="flex justify-between items-end mb-8">
        <div>
          <h3 class="font-headline text-2xl font-semibold text-primary mb-1">Recent Security Flags</h3>
          <p class="text-sm text-on-surface-variant">Events requiring administrative review.</p>
        </div>
        <a href="#" class="text-secondary text-sm font-semibold hover:underline flex items-center gap-1">View All <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
      </div>
      <div class="space-y-4">
        {{-- Flag: Multiple Failed Logins --}}
        <div class="bg-surface-container-lowest p-4 rounded-lg flex items-start gap-4 transition-colors hover:bg-surface-container-highest cursor-pointer group" onclick="openEventDrawer(0)">
          <div class="bg-error-container text-on-error-container p-2 rounded-md shrink-0">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">gpp_maybe</span>
          </div>
          <div class="flex-1">
            <div class="flex justify-between items-start mb-1">
              <h4 class="text-base font-bold text-primary group-hover:text-secondary transition-colors">Multiple Failed Logins</h4>
              <span class="text-xs text-on-surface-variant font-mono">10:42 AM</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">5 consecutive failed attempts from IP 10.0.1.104 for User ID: A-4921.</p>
            <div class="flex gap-2">
              <span class="px-2 py-0.5 bg-surface-variant text-on-surface text-xs rounded font-medium">Auth</span>
              <span class="px-2 py-0.5 bg-error-container/50 text-on-error-container text-xs rounded font-medium">High Severity</span>
            </div>
          </div>
        </div>
        {{-- Flag: Policy Override Attempt --}}
        <div class="bg-surface-container-lowest p-4 rounded-lg flex items-start gap-4 transition-colors hover:bg-surface-container-highest cursor-pointer group" onclick="openEventDrawer(1)">
          <div class="bg-tertiary-fixed-dim text-on-tertiary-fixed p-2 rounded-md shrink-0">
            <span class="material-symbols-outlined">policy</span>
          </div>
          <div class="flex-1">
            <div class="flex justify-between items-start mb-1">
              <h4 class="text-base font-bold text-primary group-hover:text-secondary transition-colors">Policy Override Attempt</h4>
              <span class="text-xs text-on-surface-variant font-mono">08:15 AM</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">Attempted modification of restricted collection metadata by non-authorized role.</p>
            <div class="flex gap-2">
              <span class="px-2 py-0.5 bg-surface-variant text-on-surface text-xs rounded font-medium">Catalog</span>
              <span class="px-2 py-0.5 bg-tertiary-container/10 text-tertiary text-xs rounded font-medium">Medium Severity</span>
            </div>
          </div>
        </div>
        {{-- Flag: Reservation Escalation --}}
        <div class="bg-surface-container-lowest p-4 rounded-lg flex items-start gap-4 transition-colors hover:bg-surface-container-highest cursor-pointer group" onclick="openEventDrawer(2)">
          <div class="bg-primary-fixed text-on-primary-fixed p-2 rounded-md shrink-0">
            <span class="material-symbols-outlined">escalator_warning</span>
          </div>
          <div class="flex-1">
            <div class="flex justify-between items-start mb-1">
              <h4 class="text-base font-bold text-primary group-hover:text-secondary transition-colors">Reservation Escalation</h4>
              <span class="text-xs text-on-surface-variant font-mono">07:30 AM</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">Overdue reservation for restricted periodical escalated after 72h hold expiry.</p>
            <div class="flex gap-2">
              <span class="px-2 py-0.5 bg-surface-variant text-on-surface text-xs rounded font-medium">Circulation</span>
              <span class="px-2 py-0.5 bg-primary-fixed/50 text-on-primary-fixed text-xs rounded font-medium">Low Severity</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Comprehensive Audit Log --}}
  <div class="bg-surface-container-lowest rounded-xl pb-8 overflow-hidden">
    <div class="p-8 border-b border-outline-variant/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <h2 class="font-headline text-2xl font-semibold text-primary">Comprehensive Audit Log</h2>
      <div class="relative max-w-md w-full">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
        <input type="text" placeholder="Search logs by ID, Actor, or Event..." class="w-full pl-10 pr-4 py-2 bg-surface-container-high border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-sm font-body text-on-surface placeholder:text-outline transition-all rounded-t-md" id="auditSearchInput" oninput="filterAuditRows()" />
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse" id="auditTable">
        <thead>
          <tr class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider bg-surface-container-low border-b border-outline-variant/10">
            <th class="px-8 py-4 font-body">Timestamp</th>
            <th class="px-8 py-4 font-body">Event / Context</th>
            <th class="px-8 py-4 font-body">Actor</th>
            <th class="px-8 py-4 font-body">Severity</th>
            <th class="px-8 py-4 font-body text-right">Action</th>
          </tr>
        </thead>
        <tbody class="text-sm font-body divide-y divide-outline-variant/5" id="auditBody">
          {{-- Row 1: Record Modification --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="0">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-20<br>14:32:01 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">Record Modification: Collection Metadata</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Updated fields [Location, Status] for Asset ID: BK-9921</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-[10px] font-bold">EK</div>
                <span class="text-primary font-medium">E. Kassenov</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-container-highest text-on-surface-variant">
                <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Info
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(0)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
          {{-- Row 2: System Export --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="1">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-20<br>11:15:44 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">System Export: User Data</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Generated CSV export of 1,204 active records.</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-[10px] font-bold">SY</div>
                <span class="text-primary font-medium">System Auto</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary-container/20 text-secondary">
                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Notice
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(1)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
          {{-- Row 3: Authentication Failure --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="2">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-20<br>10:42:12 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">Authentication Failure</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Invalid credentials provided. Source: 10.0.1.104</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <span class="text-on-surface-variant text-xs font-mono">Unauthenticated</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-error-container/30 text-error">
                <span class="w-1.5 h-1.5 rounded-full bg-error"></span> Alert
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(2)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
          {{-- Row 4: Configuration Change --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="3">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-19<br>16:05:30 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">Configuration Change: API Keys</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Revoked external vendor API key #V-402.</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center text-[10px] font-bold">AA</div>
                <span class="text-primary font-medium">A. Admin</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-tertiary-container/10 text-tertiary">
                <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Warning
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(3)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
          {{-- Row 5: Role Change --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="4">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-19<br>14:20:08 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">User Role Change</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Role changed from student to librarian for User ID: U-3347</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-[10px] font-bold">NB</div>
                <span class="text-primary font-medium">N. Bekturova</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-secondary-container/20 text-secondary">
                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> Notice
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(4)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
          {{-- Row 6: Digital Access Policy --}}
          <tr class="hover:bg-surface-container-high transition-colors group audit-row" data-event-index="5">
            <td class="px-8 py-5 whitespace-nowrap font-mono text-on-surface-variant text-xs">2026-04-19<br>09:48:55 UTC</td>
            <td class="px-8 py-5">
              <div class="font-semibold text-primary mb-0.5 group-hover:text-secondary transition-colors">Digital Access Policy Update</div>
              <div class="text-on-surface-variant text-xs truncate max-w-xs">Restricted download access for repository collection RC-0118.</div>
            </td>
            <td class="px-8 py-5">
              <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center text-[10px] font-bold">AA</div>
                <span class="text-primary font-medium">A. Admin</span>
              </div>
            </td>
            <td class="px-8 py-5">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-tertiary-container/10 text-tertiary">
                <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span> Warning
              </span>
            </td>
            <td class="px-8 py-5 text-right">
              <button class="text-secondary hover:text-primary transition-colors" onclick="openEventDrawer(5)">
                <span class="material-symbols-outlined text-lg">visibility</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    {{-- Pagination --}}
    <div class="px-8 py-4 border-t border-outline-variant/10 flex justify-between items-center text-sm">
      <span class="text-on-surface-variant">Showing 1 to 6 of 1,284 entries</span>
      <div class="flex gap-2">
        <button class="px-3 py-1 text-outline" disabled>Previous</button>
        <button class="px-3 py-1 bg-surface-variant text-primary rounded font-medium">1</button>
        <button class="px-3 py-1 text-on-surface-variant hover:text-primary transition-colors">2</button>
        <button class="px-3 py-1 text-on-surface-variant hover:text-primary transition-colors">3</button>
        <button class="px-3 py-1 text-secondary hover:text-primary font-medium transition-colors">Next</button>
      </div>
    </div>
  </div>

{{-- Event Details Drawer --}}
<div id="eventDrawerOverlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-40 hidden" onclick="closeEventDrawer()"></div>
<div id="eventDrawer" class="fixed inset-y-0 right-0 w-full md:w-[28rem] bg-surface-container-lowest border-l border-outline-variant/10 shadow-2xl shadow-primary/10 z-50 flex flex-col translate-x-full transition-transform duration-300 ease-in-out">
  <div class="flex justify-between items-center p-8 pb-4 border-b border-outline-variant/10">
    <h3 class="font-headline text-xl text-primary font-bold">Event Details</h3>
    <button class="text-on-surface-variant hover:text-error transition-colors" onclick="closeEventDrawer()">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-8 space-y-6">
    <div>
      <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block mb-1">Event Type</span>
      <p class="font-medium text-primary" id="drawerEventType">—</p>
    </div>
    <div>
      <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block mb-1">Timestamp</span>
      <p class="font-mono text-sm text-primary" id="drawerTimestamp">—</p>
    </div>
    <div>
      <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block mb-1">Severity</span>
      <p class="font-medium text-primary" id="drawerSeverity">—</p>
    </div>
    <div>
      <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block mb-1">Actor Information</span>
      <div class="bg-surface-container p-3 rounded-md mt-2 flex items-center gap-3" id="drawerActorBlock">
        <div class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-xs font-bold" id="drawerActorInitials">—</div>
        <div>
          <p class="text-sm font-bold text-primary" id="drawerActorName">—</p>
          <p class="text-xs text-on-surface-variant font-mono" id="drawerActorMeta">—</p>
        </div>
      </div>
    </div>
    <div>
      <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider block mb-2">Raw Payload</span>
      <div class="bg-primary text-on-primary p-4 rounded-md font-mono text-xs overflow-x-auto whitespace-pre" id="drawerPayload">{ }</div>
    </div>
  </div>
  <div class="p-8 pt-4 border-t border-outline-variant/10">
    <button class="w-full bg-surface-variant text-primary py-2.5 rounded-md text-sm font-semibold hover:bg-outline-variant/20 transition-colors">Acknowledge Event</button>
  </div>
</div>

@push('scripts')
<script>
  // Placeholder event data for drawer
  const auditEvents = [
    {
      type: 'Record Modification',
      timestamp: '2026-04-20 14:32:01 UTC',
      severity: 'Info',
      actor: 'E. Kassenov',
      initials: 'EK',
      meta: 'ID: U-8821 | IP: 10.0.4.15',
      payload: JSON.stringify({
        event_id: 'evt_99214a',
        action: 'update',
        resource: 'catalog.asset',
        resource_id: 'BK-9921',
        changes: { status: ['available', 'reserved'], location: ['shelf_A2', 'hold_desk'] }
      }, null, 2)
    },
    {
      type: 'System Export',
      timestamp: '2026-04-20 11:15:44 UTC',
      severity: 'Notice',
      actor: 'System Auto',
      initials: 'SY',
      meta: 'ID: system | Automated',
      payload: JSON.stringify({
        event_id: 'evt_99210b',
        action: 'export',
        resource: 'users.csv',
        record_count: 1204,
        format: 'CSV'
      }, null, 2)
    },
    {
      type: 'Authentication Failure',
      timestamp: '2026-04-20 10:42:12 UTC',
      severity: 'Alert',
      actor: 'Unauthenticated',
      initials: '??',
      meta: 'IP: 10.0.1.104 | 5 attempts',
      payload: JSON.stringify({
        event_id: 'evt_99208c',
        action: 'auth_failure',
        source_ip: '10.0.1.104',
        target_user: 'A-4921',
        attempts: 5,
        blocked: true
      }, null, 2)
    },
    {
      type: 'Configuration Change',
      timestamp: '2026-04-19 16:05:30 UTC',
      severity: 'Warning',
      actor: 'A. Admin',
      initials: 'AA',
      meta: 'ID: U-0001 | IP: 10.0.1.10',
      payload: JSON.stringify({
        event_id: 'evt_99195d',
        action: 'revoke',
        resource: 'api_keys',
        key_id: 'V-402',
        vendor: 'external_catalog_sync'
      }, null, 2)
    },
    {
      type: 'User Role Change',
      timestamp: '2026-04-19 14:20:08 UTC',
      severity: 'Notice',
      actor: 'N. Bekturova',
      initials: 'NB',
      meta: 'ID: U-3347 | IP: 10.0.2.22',
      payload: JSON.stringify({
        event_id: 'evt_99190e',
        action: 'role_change',
        resource: 'user',
        user_id: 'U-3347',
        old_role: 'student',
        new_role: 'librarian'
      }, null, 2)
    },
    {
      type: 'Digital Access Policy Update',
      timestamp: '2026-04-19 09:48:55 UTC',
      severity: 'Warning',
      actor: 'A. Admin',
      initials: 'AA',
      meta: 'ID: U-0001 | IP: 10.0.1.10',
      payload: JSON.stringify({
        event_id: 'evt_99187f',
        action: 'policy_update',
        resource: 'repository.collection',
        collection_id: 'RC-0118',
        change: 'download_access_restricted'
      }, null, 2)
    }
  ];

  function openEventDrawer(index) {
    const ev = auditEvents[index] || auditEvents[0];
    document.getElementById('drawerEventType').textContent = ev.type;
    document.getElementById('drawerTimestamp').textContent = ev.timestamp;
    document.getElementById('drawerSeverity').textContent = ev.severity;
    document.getElementById('drawerActorName').textContent = ev.actor;
    document.getElementById('drawerActorInitials').textContent = ev.initials;
    document.getElementById('drawerActorMeta').textContent = ev.meta;
    document.getElementById('drawerPayload').textContent = ev.payload;
    document.getElementById('eventDrawer').classList.remove('translate-x-full');
    document.getElementById('eventDrawerOverlay').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeEventDrawer() {
    document.getElementById('eventDrawer').classList.add('translate-x-full');
    document.getElementById('eventDrawerOverlay').classList.add('hidden');
    document.body.style.overflow = '';
  }

  function filterAuditRows() {
    const q = document.getElementById('auditSearchInput').value.toLowerCase();
    document.querySelectorAll('.audit-row').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
</script>
@endpush
@endsection
