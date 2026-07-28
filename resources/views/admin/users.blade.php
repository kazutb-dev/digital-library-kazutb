@extends('layouts.admin')

@section('title', 'User & Role Management — KazUTB Smart Library Admin')

@section('head')
<style>
  .drawer-scroll::-webkit-scrollbar { width: 6px; }
  .drawer-scroll::-webkit-scrollbar-track { background: transparent; }
  .drawer-scroll::-webkit-scrollbar-thumb { background-color: #c4c6cf; border-radius: 4px; }
</style>
@endsection

@section('content')
{{-- Page Header --}}
<div class="mb-12">
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
    <div>
      <h2 class="font-headline text-4xl lg:text-5xl text-primary tracking-tight leading-tight">User &amp; Role Management</h2>
      <p class="font-body text-base text-on-surface-variant mt-2 max-w-2xl">Manage institutional identities, audit role-based access controls, and monitor active session states across the KazUTB Smart Library platform.</p>
    </div>
    <div class="flex gap-4 shrink-0">
      <button class="px-6 py-2.5 rounded-md border border-outline-variant/20 text-primary font-medium hover:bg-surface-container-low transition-colors flex items-center gap-2">
        <span class="material-symbols-outlined text-[20px]">download</span>
        Export Roster
      </button>
      <button class="px-6 py-2.5 rounded-md bg-gradient-to-r from-primary to-primary-container text-on-primary font-medium hover:opacity-90 transition-opacity flex items-center gap-2">
        <span class="material-symbols-outlined text-[20px]">person_add</span>
        Provision Identity
      </button>
    </div>
  </div>
</div>

{{-- Filters Section --}}
<div class="mb-8 bg-surface-container-low p-6 rounded-lg border-none">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <div class="flex flex-col gap-2">
      <label class="text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Primary Role</label>
      <div class="relative">
        <select id="filter-role" class="w-full appearance-none bg-surface-container-highest border-b border-outline-variant/20 rounded-t-md px-4 py-2.5 text-on-surface focus:border-secondary focus:ring-0 transition-colors text-sm">
          <option value="">All Roles</option>
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
          <option value="employee">Employee</option>
          <option value="librarian">Librarian</option>
          <option value="admin">Administrator</option>
        </select>
        <span class="material-symbols-outlined absolute right-3 top-2.5 text-outline pointer-events-none">arrow_drop_down</span>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-xs text-on-surface-variant uppercase tracking-wider font-semibold">Department / College</label>
      <div class="relative">
        <select id="filter-department" class="w-full appearance-none bg-surface-container-highest border-b border-outline-variant/20 rounded-t-md px-4 py-2.5 text-on-surface focus:border-secondary focus:ring-0 transition-colors text-sm">
          <option value="">All Departments</option>
          <option>School of Engineering</option>
          <option>College of Humanities</option>
          <option>Business Administration</option>
          <option>Library Sciences</option>
          <option>IT Systems</option>
          <option>Special Collections</option>
        </select>
        <span class="material-symbols-outlined absolute right-3 top-2.5 text-outline pointer-events-none">arrow_drop_down</span>
      </div>
    </div>

    <div class="flex flex-col gap-2">
      <label class="text-xs text-on-surface-variant uppercase tracking-wider font-semibold">CRM Sync Status</label>
      <div class="relative">
        <select id="filter-status" class="w-full appearance-none bg-surface-container-highest border-b border-outline-variant/20 rounded-t-md px-4 py-2.5 text-on-surface focus:border-secondary focus:ring-0 transition-colors text-sm">
          <option value="">All States</option>
          <option value="active">Active &amp; Synced</option>
          <option value="pending">Pending Verification</option>
          <option value="suspended">Suspended</option>
        </select>
        <span class="material-symbols-outlined absolute right-3 top-2.5 text-outline pointer-events-none">arrow_drop_down</span>
      </div>
    </div>

    <div class="flex items-end">
      <button id="btn-clear-filters" class="w-full px-6 py-2.5 rounded-md border border-outline-variant/20 text-primary font-medium hover:bg-surface-variant transition-colors flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[20px]">filter_list_off</span>
        Clear Filters
      </button>
    </div>
  </div>
</div>

{{-- Directory Table Card --}}
<div class="bg-surface-container-lowest rounded-xl shadow-[0_4px_24px_rgba(0,31,63,0.04)] overflow-hidden border-none">
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse" id="users-table">
      <thead>
        <tr class="bg-surface-container-low text-on-surface-variant text-xs uppercase tracking-wider">
          <th class="px-6 py-4 font-semibold whitespace-nowrap">User Identity</th>
          <th class="px-6 py-4 font-semibold whitespace-nowrap">System Role</th>
          <th class="px-6 py-4 font-semibold whitespace-nowrap">Department</th>
          <th class="px-6 py-4 font-semibold whitespace-nowrap">Last Access</th>
          <th class="px-6 py-4 font-semibold whitespace-nowrap">Status</th>
          <th class="px-6 py-4 font-semibold whitespace-nowrap text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-surface-container-high/50 text-sm" id="users-tbody">
        {{-- Row 1 — Teacher --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group user-row"
            data-role="teacher" data-department="School of Engineering" data-status="active"
            data-name="Dr. Robert Chen" data-email="r.chen@kazutb.edu.kz" data-role-label="Teacher"
            data-last-access="Today, 09:41" data-sync="Healthy" data-sync-time="2026-04-20 09:12 UTC" data-guid="a8f9-4b2c-91e8"
            data-perm-archive="1" data-perm-identity="0" data-perm-acquisition="0"
            onclick="openDrawer(this)">
          <td class="px-6 py-5">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 rounded-full bg-tertiary-container text-on-tertiary flex items-center justify-center font-headline font-bold text-lg">DR</div>
              <div>
                <div class="font-semibold text-primary group-hover:text-secondary transition-colors">Dr. Robert Chen</div>
                <div class="text-sm text-on-surface-variant">r.chen@kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-container text-on-primary text-xs font-semibold tracking-wide">
              <span class="material-symbols-outlined text-[14px]">school</span>
              Teacher
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">School of Engineering</td>
          <td class="px-6 py-5 text-on-surface-variant">Today, 09:41</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-secondary">
              <div class="w-2 h-2 rounded-full bg-secondary"></div>
              <span class="text-sm font-medium">Active Sync</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>

        {{-- Row 2 — Student --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group user-row"
            data-role="student" data-department="College of Humanities" data-status="active"
            data-name="Amina Kasymova" data-email="a.kasymova@student.kazutb.edu.kz" data-role-label="Student"
            data-last-access="Yesterday, 14:20" data-sync="Healthy" data-sync-time="2026-04-19 14:20 UTC" data-guid="b7e1-3a4f-c82d"
            data-perm-archive="1" data-perm-identity="0" data-perm-acquisition="0"
            onclick="openDrawer(this)">
          <td class="px-6 py-5">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 rounded-full bg-surface-container-highest text-on-surface flex items-center justify-center font-headline font-bold text-lg">AK</div>
              <div>
                <div class="font-semibold text-primary group-hover:text-secondary transition-colors">Amina Kasymova</div>
                <div class="text-sm text-on-surface-variant">a.kasymova@student.kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold tracking-wide border border-outline-variant/20">
              <span class="material-symbols-outlined text-[14px]">local_library</span>
              Student
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">College of Humanities</td>
          <td class="px-6 py-5 text-on-surface-variant">Yesterday, 14:20</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-secondary">
              <div class="w-2 h-2 rounded-full bg-secondary"></div>
              <span class="text-sm font-medium">Active Sync</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>

        {{-- Row 3 — Admin (selected / active indicator) --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group bg-surface-container-lowest relative user-row"
            data-role="admin" data-department="IT Systems" data-status="active"
            data-name="Sarah Jenkins" data-email="s.jenkins@kazutb.edu.kz" data-role-label="Administrator"
            data-last-access="Current Session" data-sync="Healthy" data-sync-time="2026-04-20 09:12 UTC" data-guid="c4d2-8e1a-f73b"
            data-perm-archive="1" data-perm-identity="1" data-perm-acquisition="0"
            onclick="openDrawer(this)">
          <td class="px-6 py-5 relative">
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-secondary hidden md:block"></div>
            <div class="flex items-center gap-4 md:pl-3">
              <div class="h-10 w-10 rounded-full bg-tertiary-container text-on-tertiary flex items-center justify-center font-headline font-bold text-lg">SJ</div>
              <div>
                <div class="font-semibold text-primary group-hover:text-secondary transition-colors">Sarah Jenkins</div>
                <div class="text-sm text-on-surface-variant">s.jenkins@kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-tertiary-container text-on-tertiary text-xs font-semibold tracking-wide">
              <span class="material-symbols-outlined text-[14px]">admin_panel_settings</span>
              Administrator
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">IT Systems</td>
          <td class="px-6 py-5 text-on-surface-variant">Current Session</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-secondary">
              <div class="w-2 h-2 rounded-full bg-secondary"></div>
              <span class="text-sm font-medium">Active Sync</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>

        {{-- Row 4 — Librarian (suspended) --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group opacity-75 user-row"
            data-role="librarian" data-department="Special Collections" data-status="suspended"
            data-name="Marcus Johnson" data-email="m.johnson@kazutb.edu.kz" data-role-label="Librarian"
            data-last-access="Oct 12, 2025" data-sync="Suspended" data-sync-time="2025-10-12 16:30 UTC" data-guid="d9a3-5f7c-e24b"
            data-perm-archive="1" data-perm-identity="0" data-perm-acquisition="0"
            onclick="openDrawer(this)">
          <td class="px-6 py-5">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 rounded-full bg-surface-variant text-outline flex items-center justify-center font-headline font-bold text-lg">MJ</div>
              <div>
                <div class="font-semibold text-outline line-through group-hover:text-primary transition-colors">Marcus Johnson</div>
                <div class="text-sm text-on-surface-variant">m.johnson@kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold tracking-wide border border-outline-variant/20">
              <span class="material-symbols-outlined text-[14px]">menu_book</span>
              Librarian
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">Special Collections</td>
          <td class="px-6 py-5 text-on-surface-variant">Oct 12, 2025</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-error">
              <div class="w-2 h-2 rounded-full bg-error"></div>
              <span class="text-sm font-medium">Suspended</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>

        {{-- Row 5 — Employee --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group user-row"
            data-role="employee" data-department="Business Administration" data-status="active"
            data-name="Nurzhan Omarov" data-email="n.omarov@kazutb.edu.kz" data-role-label="Employee"
            data-last-access="Apr 18, 2026" data-sync="Healthy" data-sync-time="2026-04-18 11:45 UTC" data-guid="e5b8-2d6f-a19c"
            data-perm-archive="1" data-perm-identity="0" data-perm-acquisition="1"
            onclick="openDrawer(this)">
          <td class="px-6 py-5">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-headline font-bold text-lg">NO</div>
              <div>
                <div class="font-semibold text-primary group-hover:text-secondary transition-colors">Nurzhan Omarov</div>
                <div class="text-sm text-on-surface-variant">n.omarov@kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold tracking-wide border border-outline-variant/20">
              <span class="material-symbols-outlined text-[14px]">badge</span>
              Employee
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">Business Administration</td>
          <td class="px-6 py-5 text-on-surface-variant">Apr 18, 2026</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-secondary">
              <div class="w-2 h-2 rounded-full bg-secondary"></div>
              <span class="text-sm font-medium">Active Sync</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>

        {{-- Row 6 — Student (pending) --}}
        <tr class="hover:bg-surface-container-low transition-colors cursor-pointer group user-row"
            data-role="student" data-department="School of Engineering" data-status="pending"
            data-name="Aisha Bekturova" data-email="a.bekturova@student.kazutb.edu.kz" data-role-label="Student"
            data-last-access="Pending" data-sync="Pending" data-sync-time="—" data-guid="f2c7-9e3a-d48f"
            data-perm-archive="0" data-perm-identity="0" data-perm-acquisition="0"
            onclick="openDrawer(this)">
          <td class="px-6 py-5">
            <div class="flex items-center gap-4">
              <div class="h-10 w-10 rounded-full bg-surface-container-highest text-on-surface flex items-center justify-center font-headline font-bold text-lg">AB</div>
              <div>
                <div class="font-semibold text-primary group-hover:text-secondary transition-colors">Aisha Bekturova</div>
                <div class="text-sm text-on-surface-variant">a.bekturova@student.kazutb.edu.kz</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold tracking-wide border border-outline-variant/20">
              <span class="material-symbols-outlined text-[14px]">local_library</span>
              Student
            </span>
          </td>
          <td class="px-6 py-5 text-on-surface-variant">School of Engineering</td>
          <td class="px-6 py-5 text-on-surface-variant">Pending</td>
          <td class="px-6 py-5">
            <div class="flex items-center gap-2 text-[#b08000]">
              <div class="w-2 h-2 rounded-full bg-[#b08000]"></div>
              <span class="text-sm font-medium">Pending Verification</span>
            </div>
          </td>
          <td class="px-6 py-5 text-right">
            <button class="text-outline hover:text-primary transition-colors p-1" onclick="event.stopPropagation(); openDrawer(this.closest('tr'));">
              <span class="material-symbols-outlined">more_vert</span>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- Pagination Footer --}}
  <div class="px-6 py-4 border-t border-surface-container-high flex items-center justify-between bg-surface-container-lowest">
    <span class="text-sm text-on-surface-variant" id="pagination-info">Showing 1 to 6 of 2,451 identities</span>
    <div class="flex gap-2">
      <button class="p-2 rounded-md border border-outline-variant/20 text-outline hover:bg-surface-container-low disabled:opacity-50 transition-colors" disabled>
        <span class="material-symbols-outlined text-[20px]">chevron_left</span>
      </button>
      <button class="p-2 rounded-md border border-outline-variant/20 text-primary hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
      </button>
    </div>
  </div>
</div>

{{-- Identity Dossier Drawer (right-side overlay) --}}
<aside id="identity-drawer" class="fixed top-0 right-0 h-full w-full md:w-[480px] bg-surface-container-lowest/95 backdrop-blur-2xl shadow-[-8px_0_48px_rgba(0,31,63,0.08)] z-50 transform translate-x-full transition-transform duration-500 ease-in-out border-l border-white/20 flex flex-col">
  {{-- Drawer Header --}}
  <div class="px-8 py-6 border-b border-surface-container-high/50 flex justify-between items-start">
    <div>
      <h3 class="font-headline text-2xl text-primary">Identity Dossier</h3>
      <p class="text-sm text-on-surface-variant mt-1">CRM / Active Directory Sync Record</p>
    </div>
    <button onclick="closeDrawer()" class="p-2 rounded-full hover:bg-surface-container-high transition-colors text-outline hover:text-primary">
      <span class="material-symbols-outlined">close</span>
    </button>
  </div>

  {{-- Drawer Content (Scrollable) --}}
  <div class="flex-1 overflow-y-auto p-8 drawer-scroll space-y-10">
    {{-- Profile Hero --}}
    <div class="flex items-center gap-6">
      <div id="drawer-avatar" class="h-20 w-20 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-headline font-bold text-3xl border-4 border-surface shadow-sm"></div>
      <div>
        <h4 id="drawer-name" class="font-body text-xl font-bold text-primary"></h4>
        <div class="flex items-center gap-2 mt-1">
          <span id="drawer-role-badge" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"></span>
          <span id="drawer-department" class="text-sm text-on-surface-variant"></span>
        </div>
      </div>
    </div>

    {{-- Directory Integration Status Card --}}
    <div class="bg-surface-container-low rounded-xl p-6 border-none">
      <h5 class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold mb-4">Directory Integration</h5>
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-sm text-on-surface">Sync Status</span>
          <div id="drawer-sync-status" class="flex items-center gap-2 text-secondary">
            <span class="material-symbols-outlined text-[18px]">cloud_sync</span>
            <span class="font-medium text-sm">Healthy</span>
          </div>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-sm text-on-surface">Last Sync Check</span>
          <span id="drawer-sync-time" class="text-sm text-on-surface-variant font-mono"></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-sm text-on-surface">Object GUID</span>
          <span id="drawer-guid" class="text-xs text-outline font-mono bg-surface-container-high px-2 py-1 rounded"></span>
        </div>
      </div>
    </div>

    {{-- System Permissions Bento Grid --}}
    <div>
      <h5 class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold mb-4">System Permissions</h5>
      <div class="grid grid-cols-2 gap-4">
        {{-- Global Archive Permission --}}
        <div id="perm-archive" class="bg-surface border border-outline-variant/20 rounded-lg p-4 flex flex-col gap-3 hover:bg-surface-container-low transition-colors cursor-pointer group">
          <div class="flex justify-between items-start">
            <span class="material-symbols-outlined text-secondary bg-secondary-fixed/30 p-1.5 rounded-md">account_balance</span>
            <div class="perm-toggle w-8 h-4 rounded-full relative">
              <div class="perm-toggle-dot w-3 h-3 bg-white rounded-full absolute top-0.5"></div>
            </div>
          </div>
          <div>
            <div class="font-semibold text-sm text-primary">Global Archive</div>
            <div class="perm-desc text-xs text-on-surface-variant mt-0.5">Read / Write / Delete</div>
          </div>
        </div>

        {{-- Identity Provisioning Permission --}}
        <div id="perm-identity" class="bg-surface border border-outline-variant/20 rounded-lg p-4 flex flex-col gap-3 hover:bg-surface-container-low transition-colors cursor-pointer group">
          <div class="flex justify-between items-start">
            <span class="material-symbols-outlined text-primary bg-primary-fixed/50 p-1.5 rounded-md">group_add</span>
            <div class="perm-toggle w-8 h-4 rounded-full relative">
              <div class="perm-toggle-dot w-3 h-3 bg-white rounded-full absolute top-0.5"></div>
            </div>
          </div>
          <div>
            <div class="font-semibold text-sm text-primary">Identity Prov.</div>
            <div class="perm-desc text-xs text-on-surface-variant mt-0.5">Full Delegation</div>
          </div>
        </div>

        {{-- Acquisition Approvals Permission --}}
        <div id="perm-acquisition" class="bg-surface border border-outline-variant/20 rounded-lg p-4 flex flex-col gap-3 hover:bg-surface-container-low transition-colors cursor-pointer group">
          <div class="flex justify-between items-start">
            <span class="material-symbols-outlined text-outline bg-surface-container-high p-1.5 rounded-md">payments</span>
            <div class="perm-toggle w-8 h-4 rounded-full relative">
              <div class="perm-toggle-dot w-3 h-3 bg-white rounded-full absolute top-0.5"></div>
            </div>
          </div>
          <div>
            <div class="font-semibold text-sm text-primary">Acquisition Approvals</div>
            <div class="perm-desc text-xs text-on-surface-variant mt-0.5">No Access</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Drawer Footer Actions --}}
  <div class="p-6 border-t border-surface-container-high/50 bg-surface-container-lowest flex gap-3">
    <button class="flex-1 py-2.5 rounded-md border border-error/30 text-error font-medium hover:bg-error-container/20 transition-colors bg-transparent">
      Suspend Access
    </button>
    <button class="flex-1 py-2.5 rounded-md bg-gradient-to-r from-primary to-primary-container text-on-primary font-medium hover:opacity-90 transition-opacity shadow-sm shadow-primary/20">
      Edit Identity
    </button>
  </div>
</aside>

{{-- Drawer Backdrop --}}
<div id="drawer-backdrop" class="fixed inset-0 bg-black/20 z-40 hidden" onclick="closeDrawer()"></div>

<script>
  // --- Drawer logic ---
  function openDrawer(row) {
    const drawer = document.getElementById('identity-drawer');
    const backdrop = document.getElementById('drawer-backdrop');
    const d = row.dataset;

    // Name + initials
    const nameParts = d.name.split(' ');
    const initials = nameParts.map(p => p[0]).join('').toUpperCase().slice(0, 2);
    document.getElementById('drawer-avatar').textContent = initials;
    document.getElementById('drawer-name').textContent = d.name;
    document.getElementById('drawer-department').textContent = d.department;

    // Role badge
    const badge = document.getElementById('drawer-role-badge');
    badge.textContent = d.roleLabel;
    badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold';
    if (d.role === 'admin') {
      badge.classList.add('bg-tertiary-container', 'text-on-tertiary');
    } else if (d.role === 'librarian') {
      badge.classList.add('bg-surface-container-high', 'text-on-surface', 'border', 'border-outline-variant/20');
    } else {
      badge.classList.add('bg-surface-container-high', 'text-on-surface', 'border', 'border-outline-variant/20');
    }

    // Sync status
    const syncEl = document.getElementById('drawer-sync-status');
    if (d.sync === 'Healthy') {
      syncEl.innerHTML = '<span class="material-symbols-outlined text-[18px]">cloud_sync</span><span class="font-medium text-sm">Healthy</span>';
      syncEl.className = 'flex items-center gap-2 text-secondary';
    } else if (d.sync === 'Suspended') {
      syncEl.innerHTML = '<span class="material-symbols-outlined text-[18px]">cloud_off</span><span class="font-medium text-sm">Suspended</span>';
      syncEl.className = 'flex items-center gap-2 text-error';
    } else {
      syncEl.innerHTML = '<span class="material-symbols-outlined text-[18px]">sync_problem</span><span class="font-medium text-sm">Pending</span>';
      syncEl.className = 'flex items-center gap-2 text-[#b08000]';
    }

    document.getElementById('drawer-sync-time').textContent = d.syncTime;
    document.getElementById('drawer-guid').textContent = d.guid;

    // Permissions
    setPermission('perm-archive', d.permArchive === '1');
    setPermission('perm-identity', d.permIdentity === '1');
    setPermission('perm-acquisition', d.permAcquisition === '1');

    // Show
    drawer.classList.remove('translate-x-full');
    drawer.classList.add('translate-x-0');
    backdrop.classList.remove('hidden');
  }

  function closeDrawer() {
    const drawer = document.getElementById('identity-drawer');
    const backdrop = document.getElementById('drawer-backdrop');
    drawer.classList.remove('translate-x-0');
    drawer.classList.add('translate-x-full');
    backdrop.classList.add('hidden');
  }

  function setPermission(id, enabled) {
    const el = document.getElementById(id);
    const toggle = el.querySelector('.perm-toggle');
    const dot = el.querySelector('.perm-toggle-dot');
    const desc = el.querySelector('.perm-desc');
    if (enabled) {
      el.classList.remove('opacity-60');
      toggle.className = 'perm-toggle w-8 h-4 bg-secondary rounded-full relative';
      dot.className = 'perm-toggle-dot w-3 h-3 bg-white rounded-full absolute right-0.5 top-0.5';
    } else {
      el.classList.add('opacity-60');
      toggle.className = 'perm-toggle w-8 h-4 bg-surface-container-high rounded-full relative';
      dot.className = 'perm-toggle-dot w-3 h-3 bg-white rounded-full absolute left-0.5 top-0.5 shadow-sm';
    }
  }

  // --- Filter logic ---
  function applyFilters() {
    const role = document.getElementById('filter-role').value;
    const dept = document.getElementById('filter-department').value;
    const status = document.getElementById('filter-status').value;
    const rows = document.querySelectorAll('.user-row');
    let visible = 0;

    rows.forEach(row => {
      const d = row.dataset;
      let show = true;
      if (role && d.role !== role) show = false;
      if (dept && d.department !== dept) show = false;
      if (status && d.status !== status) show = false;
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    document.getElementById('pagination-info').textContent =
      'Showing ' + visible + ' of 2,451 identities';
  }

  document.getElementById('filter-role').addEventListener('change', applyFilters);
  document.getElementById('filter-department').addEventListener('change', applyFilters);
  document.getElementById('filter-status').addEventListener('change', applyFilters);

  document.getElementById('btn-clear-filters').addEventListener('click', () => {
    document.getElementById('filter-role').value = '';
    document.getElementById('filter-department').value = '';
    document.getElementById('filter-status').value = '';
    applyFilters();
  });

  // Close drawer on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDrawer();
  });
</script>
@endsection
