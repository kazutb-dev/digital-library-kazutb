@extends('layouts.admin')

@section('title', 'Feedback Inbox — KazUTB Smart Library Admin')

@section('content')
  <div class="flex flex-col lg:flex-row gap-8 xl:gap-10">
    <section class="w-full lg:w-5/12 flex flex-col gap-5">
      <header class="mb-1">
        <h1 class="font-headline text-[3.5rem] leading-none text-primary mb-2">Inbox</h1>
        <p class="font-body text-base text-on-surface-variant">Institutional Feedback &amp; Requests</p>
      </header>

      <div class="flex gap-2 overflow-x-auto pb-2">
        <button type="button" class="px-4 py-2 rounded-full bg-primary text-white font-label text-xs tracking-wide whitespace-nowrap">All Entries</button>
        <button type="button" class="px-4 py-2 rounded-full border border-outline-variant/20 text-on-surface font-label text-xs tracking-wide whitespace-nowrap hover:bg-surface-container-high transition-colors">Request</button>
        <button type="button" class="px-4 py-2 rounded-full border border-outline-variant/20 text-on-surface font-label text-xs tracking-wide whitespace-nowrap hover:bg-surface-container-high transition-colors">Complaint</button>
        <button type="button" class="px-4 py-2 rounded-full border border-outline-variant/20 text-on-surface font-label text-xs tracking-wide whitespace-nowrap hover:bg-surface-container-high transition-colors">Improvement Suggestion</button>
        <button type="button" class="px-4 py-2 rounded-full border border-outline-variant/20 text-on-surface font-label text-xs tracking-wide whitespace-nowrap hover:bg-surface-container-high transition-colors">Question</button>
        <button type="button" class="px-4 py-2 rounded-full border border-outline-variant/20 text-on-surface font-label text-xs tracking-wide whitespace-nowrap hover:bg-surface-container-high transition-colors">Other</button>
      </div>

      <div class="space-y-4">
        <article class="bg-surface-container-lowest rounded-lg p-5 border-l-4 border-secondary shadow-[0_8px_24px_-4px_rgba(0,31,63,0.04)]">
          <div class="flex items-start justify-between mb-2">
            <span class="text-[0.65rem] uppercase tracking-wider font-bold text-secondary bg-secondary-container/30 px-2 py-1 rounded">Request</span>
            <span class="text-xs text-on-surface-variant">10:42 AM</span>
          </div>
          <h2 class="font-headline text-[2rem] leading-tight text-primary mb-2">Access to Rare Archives</h2>
          <p class="text-sm text-on-surface-variant mb-3">I am requesting temporary access to the 19th-century manuscript collection in Vault C for doctoral source validation.</p>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-surface-container-high flex items-center justify-center text-xs font-bold text-primary">EK</div>
            <span class="text-sm font-medium text-on-surface">Dr. Elena Kurmangaziyeva</span>
          </div>
        </article>

        <article class="bg-surface-container-low rounded-lg p-5 hover:bg-surface-container-lowest transition-colors">
          <div class="flex items-start justify-between mb-2">
            <span class="text-[0.65rem] uppercase tracking-wider font-bold text-error bg-error-container/40 px-2 py-1 rounded">Complaint</span>
            <span class="text-xs text-on-surface-variant">Yesterday</span>
          </div>
          <h2 class="font-headline text-[2rem] leading-tight text-primary mb-2">Study Room Booking Conflict</h2>
          <p class="text-sm text-on-surface-variant mb-3">The system allowed double booking for Room 4B during examination week, causing a scheduling dispute.</p>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-surface-container-high flex items-center justify-center text-xs font-bold text-primary">MS</div>
            <span class="text-sm font-medium text-on-surface">Madi Saparbayev</span>
          </div>
        </article>

        <article class="bg-surface-container-low rounded-lg p-5 hover:bg-surface-container-lowest transition-colors">
          <div class="flex items-start justify-between mb-2">
            <span class="text-[0.65rem] uppercase tracking-wider font-bold text-primary bg-primary-container/15 px-2 py-1 rounded">Improvement Suggestion</span>
            <span class="text-xs text-on-surface-variant">Apr 19</span>
          </div>
          <h2 class="font-headline text-[2rem] leading-tight text-primary mb-2">Extended Weekend Hours</h2>
          <p class="text-sm text-on-surface-variant mb-3">Please consider extending Saturday reading hall access until 21:00 during thesis defense month.</p>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-surface-container-high flex items-center justify-center text-xs font-bold text-primary">AT</div>
            <span class="text-sm font-medium text-on-surface">Aruzhan Tulegen</span>
          </div>
        </article>

        <article class="bg-surface-container-low rounded-lg p-5 hover:bg-surface-container-lowest transition-colors">
          <div class="flex items-start justify-between mb-2">
            <span class="text-[0.65rem] uppercase tracking-wider font-bold text-on-primary-fixed-variant bg-primary-fixed/50 px-2 py-1 rounded">Question</span>
            <span class="text-xs text-on-surface-variant">Apr 17</span>
          </div>
          <h2 class="font-headline text-[2rem] leading-tight text-primary mb-2">APA 7 Workshop Availability</h2>
          <p class="text-sm text-on-surface-variant mb-3">Does the library offer guidance sessions for APA 7 citation formatting before graduation audits?</p>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-surface-container-high flex items-center justify-center text-xs font-bold text-primary">ZG</div>
            <span class="text-sm font-medium text-on-surface">Zhanar Ganiyeva</span>
          </div>
        </article>

        <article class="bg-surface-container-low rounded-lg p-5 hover:bg-surface-container-lowest transition-colors">
          <div class="flex items-start justify-between mb-2">
            <span class="text-[0.65rem] uppercase tracking-wider font-bold text-on-surface-variant bg-surface-container-highest px-2 py-1 rounded">Other</span>
            <span class="text-xs text-on-surface-variant">Apr 12</span>
          </div>
          <h2 class="font-headline text-[2rem] leading-tight text-primary mb-2">Lost and Found Inquiry</h2>
          <p class="text-sm text-on-surface-variant mb-3">I may have left a black USB drive in Reading Hall B. Please advise if it was registered at reception.</p>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-surface-container-high flex items-center justify-center text-xs font-bold text-primary">RU</div>
            <span class="text-sm font-medium text-on-surface">Rauan Utebayev</span>
          </div>
        </article>
      </div>
    </section>

    <section class="w-full lg:w-7/12 bg-surface-container-lowest rounded-xl p-6 lg:p-8 shadow-[0_24px_48px_-12px_rgba(0,31,63,0.04)] flex flex-col lg:sticky lg:top-24 lg:h-[calc(100vh-12rem)]">
      <div class="border-b border-outline-variant/20 pb-6 mb-6">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-lg font-bold text-primary">EK</div>
            <div>
              <h2 class="font-headline text-4xl leading-tight text-primary">Dr. Elena K.</h2>
              <p class="text-sm text-on-surface-variant">Faculty • History Department</p>
            </div>
          </div>
          <div class="flex gap-2">
            <button type="button" class="p-2 rounded hover:bg-surface-container-high text-on-surface-variant transition-colors" title="Forward to another unit">
              <span class="material-symbols-outlined">forward_to_inbox</span>
            </button>
            <button type="button" class="p-2 rounded hover:bg-surface-container-high text-on-surface-variant transition-colors" title="Archive request">
              <span class="material-symbols-outlined">inventory_2</span>
            </button>
          </div>
        </div>

        <div class="bg-surface rounded-lg p-4 flex items-start justify-between gap-4">
          <div>
            <div class="text-xs text-on-surface-variant mb-1">Subject</div>
            <div class="font-headline text-3xl leading-tight text-primary">Access to Rare Archives</div>
          </div>
          <div class="text-right">
            <div class="text-xs text-on-surface-variant mb-1">Status</div>
            <div class="inline-flex items-center gap-1 text-secondary font-medium text-sm">
              <span class="material-symbols-outlined text-base">pending_actions</span>
              Under Review
            </div>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto pr-1 space-y-6">
        <div>
          <div class="flex items-center gap-2 text-xs text-on-surface-variant mb-3">
            <span class="material-symbols-outlined text-[14px]">mail</span>
            Received Oct 14, 2023, 10:42 AM
          </div>
          <div class="bg-surface rounded-lg rounded-tl-none p-5 text-on-surface leading-relaxed text-base space-y-4">
            <p>To the Library Administration,</p>
            <p>I am writing to formally request temporary clearance to access the restricted 19th-century regional manuscript collection located in Vault C.</p>
            <p>My current doctoral research requires cross-referencing primary sources related to local governance structures from 1850-1890. Standard digitized copies do not provide the necessary marginalia detail.</p>
            <p>Thank you for considering this request.</p>
          </div>
        </div>

        <div class="flex items-center gap-4">
          <div class="flex-1 h-px bg-outline-variant/20"></div>
          <div class="text-xs text-on-surface-variant flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">info</span>
            System assigned to Special Collections
          </div>
          <div class="flex-1 h-px bg-outline-variant/20"></div>
        </div>

        <div class="pl-10">
          <div class="flex justify-end items-center gap-2 text-xs text-on-surface-variant mb-2">
            Internal Note • Just now
            <span class="material-symbols-outlined text-[14px]">visibility_off</span>
          </div>
          <div class="bg-surface-container-high rounded-lg rounded-tr-none p-4 text-sm text-on-surface border-r-2 border-primary-container">
            Checked Vault C preservation schedule. Requested manuscripts are in conservation; expected controlled access window opens in approximately 2 weeks.
          </div>
        </div>
      </div>

      <div class="mt-6 pt-4 border-t border-outline-variant/20">
        <div class="flex items-center gap-5 mb-3">
          <button type="button" class="text-sm font-medium text-primary border-b-2 border-primary pb-1">Reply to User</button>
          <button type="button" class="text-sm font-medium text-on-surface-variant border-b-2 border-transparent pb-1 hover:text-primary transition-colors">Add Internal Note</button>
        </div>

        <div class="bg-surface-container-highest rounded-lg overflow-hidden border-b border-outline-variant/20 focus-within:border-secondary transition-colors">
          <textarea rows="3" class="w-full bg-transparent border-0 focus:ring-0 text-sm text-on-surface placeholder:text-on-surface-variant p-4 resize-none" placeholder="Draft official response..."></textarea>
          <div class="flex items-center justify-between p-3 bg-surface/60">
            <div class="flex items-center gap-2">
              <button type="button" class="p-1.5 rounded hover:bg-surface-container-high text-on-surface-variant" title="Attach file">
                <span class="material-symbols-outlined text-[20px]">attach_file</span>
              </button>
              <button type="button" class="p-1.5 rounded hover:bg-surface-container-high text-on-surface-variant" title="Insert template">
                <span class="material-symbols-outlined text-[20px]">article</span>
              </button>
            </div>
            <button type="button" class="bg-gradient-to-r from-primary to-primary-container text-on-primary rounded px-6 py-2 text-sm font-medium inline-flex items-center gap-2 hover:opacity-90 transition-opacity">
              Send Response
              <span class="material-symbols-outlined text-base">send</span>
            </button>
          </div>
        </div>
      </div>
    </section>
  </div>
@endsection
