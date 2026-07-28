@extends('layouts.admin')

@section('title', 'News Management — KazUTB Smart Library Admin')

@php
  $newsEntries = [
    [
      'status' => 'Published',
      'status_tone' => 'published',
      'date_icon' => 'calendar_today',
      'date_label' => 'Apr 18, 2026',
      'featured' => true,
      'category' => 'Event',
      'title' => 'Digital Humanities Symposium — Call for Papers',
      'excerpt' => 'KazUTB Smart Library hosts the annual symposium on data science and historical archives. Submissions are open to graduate researchers across the institute.',
      'has_cover' => true,
      'cover_alt' => 'Editorial announcement tile with muted navy geometry on textured paper',
    ],
    [
      'status' => 'Draft',
      'status_tone' => 'draft',
      'date_icon' => 'edit',
      'date_label' => 'Last edited 2 hrs ago',
      'featured' => false,
      'category' => 'Update',
      'title' => 'Revised Access Protocols — Rare Books Collection',
      'excerpt' => 'Because of preservation work, physical access to the 18th–19th century manuscript reserve will require advanced booking and faculty sponsorship from the next academic term.',
      'has_cover' => false,
    ],
    [
      'status' => 'Scheduled',
      'status_tone' => 'scheduled',
      'date_icon' => 'schedule',
      'date_label' => 'Publishes Apr 28, 2026',
      'featured' => false,
      'category' => 'Announcement',
      'title' => 'Extended Reading Hall Hours for Thesis Defense Period',
      'excerpt' => 'The main reading hall and Digital Lab will operate until 23:00 from 5 to 20 May to support graduating cohorts during thesis defense preparation.',
      'has_cover' => false,
    ],
    [
      'status' => 'Published',
      'status_tone' => 'published',
      'date_icon' => 'calendar_today',
      'date_label' => 'Apr 10, 2026',
      'featured' => false,
      'category' => 'Schedule / Meeting',
      'title' => 'Faculty Librarians Council — Quarterly Session',
      'excerpt' => 'Agenda items include the controlled digital materials policy, the Vault C preservation report, and the semester acquisition roadmap for institutional subscriptions.',
      'has_cover' => false,
    ],
    [
      'status' => 'Archived',
      'status_tone' => 'archived',
      'date_icon' => 'inventory_2',
      'date_label' => 'Archived Mar 02, 2026',
      'featured' => false,
      'category' => 'Announcement',
      'title' => 'Winter Maintenance Window — Catalog Services',
      'excerpt' => 'Scheduled maintenance of the catalog gateway and CRM authentication bridge was completed. This notice is retained for audit reference only.',
      'has_cover' => false,
    ],
  ];

  $categoryOptions = ['Event', 'Announcement', 'Update', 'Schedule / Meeting'];
@endphp

@section('content')
  {{-- Editorial header --}}
  <div class="mb-16 md:ml-4 border-l-[3px] border-secondary pl-8 py-2">
    <p class="font-body text-sm font-semibold tracking-wider text-secondary uppercase mb-2">Editorial Desk</p>
    <h1 class="font-headline text-5xl md:text-6xl text-primary leading-tight font-bold max-w-3xl">News &amp; Announcements</h1>
    <p class="font-body text-lg text-on-surface-variant mt-4 max-w-2xl leading-relaxed">
      Curate institutional updates, academic events, scheduled meetings, and library advisories.
      Content composed here governs the public homepage news surface of KazUTB Smart Library.
    </p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 relative">
    {{-- LEFT: Publication Ledger --}}
    <section class="lg:col-span-7 xl:col-span-8 flex flex-col gap-6">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-headline text-2xl text-primary font-semibold">Publication Ledger</h2>
        <div class="flex gap-3">
          <button type="button" class="flex items-center gap-2 text-sm font-body text-primary hover:text-secondary transition-colors px-4 py-2 bg-surface-container-low rounded-md">
            <span class="material-symbols-outlined text-base">filter_list</span>
            Filter
          </button>
          <div class="relative">
            <input type="text" placeholder="Search entries..." class="bg-surface-container-highest border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-sm font-body px-4 py-2 rounded-t-md w-48 transition-all outline-none" />
            <span class="material-symbols-outlined absolute right-3 top-2.5 text-on-surface-variant text-[18px]">search</span>
          </div>
        </div>
      </div>

      {{-- State legend strip --}}
      <div class="flex flex-wrap gap-2 text-xs font-body">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface-container-high text-primary">
          <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
          Published
        </span>
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-secondary/10 text-secondary border border-secondary/20">
          <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
          Draft
        </span>
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-fixed/40 text-on-primary-fixed-variant">
          <span class="w-1.5 h-1.5 rounded-full bg-on-primary-fixed-variant"></span>
          Scheduled
        </span>
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-surface-container-highest text-on-surface-variant">
          <span class="w-1.5 h-1.5 rounded-full bg-on-surface-variant"></span>
          Archived
        </span>
      </div>

      @foreach ($newsEntries as $entry)
        <article class="bg-surface-container-lowest p-6 rounded-lg transition-all duration-300 hover:bg-surface-container-high group cursor-pointer relative overflow-hidden">
          @if ($entry['status_tone'] === 'draft')
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-secondary/40"></div>
          @elseif ($entry['status_tone'] === 'scheduled')
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-on-primary-fixed-variant/40"></div>
          @elseif ($entry['status_tone'] === 'archived')
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-outline-variant/50"></div>
          @endif

          <div class="flex items-start justify-between gap-4 {{ $entry['status_tone'] === 'published' ? '' : 'pl-2' }}">
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-3 mb-3">
                @php
                  $statusClass = match ($entry['status_tone']) {
                    'published' => 'bg-surface-container-high text-primary',
                    'draft' => 'bg-secondary/10 text-secondary border border-secondary/20',
                    'scheduled' => 'bg-primary-fixed/50 text-on-primary-fixed-variant',
                    'archived' => 'bg-surface-container-highest text-on-surface-variant',
                    default => 'bg-surface-container-high text-primary',
                  };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">{{ $entry['status'] }}</span>

                <span class="text-xs font-body text-on-surface-variant flex items-center gap-1">
                  <span class="material-symbols-outlined" style="font-size: 14px;">{{ $entry['date_icon'] }}</span>
                  {{ $entry['date_label'] }}
                </span>

                <span class="text-xs font-body text-on-surface-variant flex items-center gap-1">
                  <span class="material-symbols-outlined" style="font-size: 14px;">local_offer</span>
                  {{ $entry['category'] }}
                </span>

                @if ($entry['featured'])
                  <span class="text-xs font-body text-secondary flex items-center gap-1 font-semibold">
                    <span class="material-symbols-outlined" style="font-size: 14px;">campaign</span>
                    Homepage Featured
                  </span>
                @endif
              </div>

              <h3 class="font-headline text-xl text-primary font-semibold mb-2 group-hover:text-secondary transition-colors">{{ $entry['title'] }}</h3>
              <p class="font-body text-sm text-on-surface-variant line-clamp-2 leading-relaxed">{{ $entry['excerpt'] }}</p>

              <div class="mt-4 flex items-center gap-4 text-xs font-body text-on-surface-variant">
                <button type="button" class="inline-flex items-center gap-1 hover:text-primary transition-colors">
                  <span class="material-symbols-outlined text-[16px]">edit</span>
                  Edit
                </button>
                <button type="button" class="inline-flex items-center gap-1 hover:text-primary transition-colors">
                  <span class="material-symbols-outlined text-[16px]">visibility</span>
                  Preview
                </button>
                <button type="button" class="inline-flex items-center gap-1 hover:text-primary transition-colors">
                  <span class="material-symbols-outlined text-[16px]">history</span>
                  Revisions
                </button>
              </div>
            </div>

            @if (! empty($entry['has_cover']))
              <div class="flex-shrink-0">
                <div class="w-24 h-24 rounded-md bg-gradient-to-br from-primary-container to-primary flex items-center justify-center text-on-primary opacity-90 group-hover:opacity-100 transition-opacity" role="img" aria-label="{{ $entry['cover_alt'] ?? 'Announcement cover' }}">
                  <span class="material-symbols-outlined text-[36px]">auto_stories</span>
                </div>
              </div>
            @endif
          </div>
        </article>
      @endforeach

      <div class="flex justify-center mt-4">
        <button type="button" class="font-body text-sm text-primary hover:text-secondary font-semibold transition-colors flex items-center gap-2">
          Load Previous Entries
          <span class="material-symbols-outlined text-sm">expand_more</span>
        </button>
      </div>
    </section>

    {{-- RIGHT: Compose Dispatch --}}
    <aside class="lg:col-span-5 xl:col-span-4">
      <div class="sticky top-24 bg-surface-container-lowest/80 backdrop-blur-xl p-8 rounded-xl shadow-[0_24px_48px_-12px_rgba(0,6,19,0.04)] h-fit">
        <div class="flex items-center justify-between mb-8">
          <h2 class="font-headline text-2xl text-primary font-semibold">Compose Dispatch</h2>
          <button type="button" class="text-on-surface-variant hover:text-primary transition-colors" title="Close composer">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="space-y-6" onsubmit="return false;">
          <div>
            <label for="news-title" class="block text-xs font-body text-on-surface-variant mb-1 font-semibold uppercase tracking-wider">Headline</label>
            <input id="news-title" type="text" placeholder="Enter formal title..." class="w-full bg-transparent border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 font-headline text-xl text-primary px-0 py-3 transition-colors outline-none" />
          </div>

          <div>
            <label for="news-content" class="block text-xs font-body text-on-surface-variant mb-2 font-semibold uppercase tracking-wider">Editorial Content</label>
            <div class="bg-surface-container-low rounded-md p-2 mb-2 flex gap-2 border-b border-outline-variant/10">
              <button type="button" class="p-1.5 text-on-surface-variant hover:text-primary rounded hover:bg-surface-container-highest transition-colors" title="Bold"><span class="material-symbols-outlined text-sm">format_bold</span></button>
              <button type="button" class="p-1.5 text-on-surface-variant hover:text-primary rounded hover:bg-surface-container-highest transition-colors" title="Italic"><span class="material-symbols-outlined text-sm">format_italic</span></button>
              <button type="button" class="p-1.5 text-on-surface-variant hover:text-primary rounded hover:bg-surface-container-highest transition-colors" title="Link"><span class="material-symbols-outlined text-sm">link</span></button>
              <div class="w-px bg-outline-variant/20 my-1 mx-1"></div>
              <button type="button" class="p-1.5 text-on-surface-variant hover:text-primary rounded hover:bg-surface-container-highest transition-colors" title="Bulleted list"><span class="material-symbols-outlined text-sm">format_list_bulleted</span></button>
              <button type="button" class="p-1.5 text-on-surface-variant hover:text-primary rounded hover:bg-surface-container-highest transition-colors" title="Attach cover"><span class="material-symbols-outlined text-sm">image</span></button>
            </div>
            <textarea id="news-content" rows="8" placeholder="Draft the announcement..." class="w-full bg-transparent border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 font-body text-sm text-primary px-0 py-2 resize-none outline-none"></textarea>
          </div>

          <div class="space-y-4 pt-4 border-t border-outline-variant/10">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-body font-semibold text-primary">Feature on Homepage</p>
                <p class="text-xs font-body text-on-surface-variant">Pin to the public KazUTB library homepage.</p>
              </div>
              <button type="button" class="w-11 h-6 bg-surface-container-high rounded-full relative transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-secondary/50" aria-pressed="false">
                <span class="absolute left-1 top-1 w-4 h-4 bg-outline rounded-full transition-transform duration-300"></span>
              </button>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-body font-semibold text-primary">Schedule Publication</p>
                <p class="text-xs font-body text-on-surface-variant">Delay release until a specified date.</p>
              </div>
              <button type="button" class="w-11 h-6 bg-surface-container-high rounded-full relative transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-secondary/50" aria-pressed="false">
                <span class="absolute left-1 top-1 w-4 h-4 bg-outline rounded-full transition-transform duration-300"></span>
              </button>
            </div>

            <div>
              <label for="news-category" class="block text-xs font-body text-on-surface-variant mb-2 font-semibold uppercase tracking-wider">Category Tag</label>
              <select id="news-category" class="w-full bg-transparent border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 font-body text-sm text-primary px-0 py-2 outline-none">
                @foreach ($categoryOptions as $option)
                  <option>{{ $option }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label for="news-audience" class="block text-xs font-body text-on-surface-variant mb-2 font-semibold uppercase tracking-wider">Audience</label>
              <select id="news-audience" class="w-full bg-transparent border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 font-body text-sm text-primary px-0 py-2 outline-none">
                <option>Public — all readers</option>
                <option>Members only</option>
                <option>Faculty &amp; staff</option>
                <option>Internal librarians</option>
              </select>
            </div>
          </div>

          <div class="flex gap-4 pt-6">
            <button type="button" class="flex-1 bg-transparent border border-outline-variant/20 text-secondary font-body font-semibold py-3 rounded-md hover:bg-surface-variant transition-colors text-sm">
              Save Draft
            </button>
            <button type="button" class="flex-1 bg-gradient-to-r from-primary to-primary-container text-on-primary font-body font-semibold py-3 rounded-md hover:opacity-90 transition-opacity text-sm">
              Publish
            </button>
          </div>
        </form>
      </div>
    </aside>
  </div>
@endsection
