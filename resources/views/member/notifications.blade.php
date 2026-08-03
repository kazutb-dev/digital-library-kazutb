@extends('layouts.member', ['title' => __('librarian.member.notifications.title').' — '.__('common.app_name')])

@php
  use App\Http\Controllers\Member\NotificationController;

  $eventIcons = [
      'reservation_created' => 'book_online',
      'reservation_confirmed' => 'how_to_reg',
      'reservation_ready' => 'check_circle',
      'reservation_expired' => 'timer_off',
      'reservation_cancelled' => 'cancel',
      'loan_due_soon' => 'schedule',
      'loan_overdue' => 'warning',
      'loan_renewed' => 'event_repeat',
      'digital_access_granted' => 'lock_open',
      'news_published' => 'campaign',
      'message_received' => 'forum',
      'message_status_changed' => 'mark_email_read',
  ];

  $eventTones = [
      'reservation_ready' => 'secondary',
      'digital_access_granted' => 'secondary',
      'loan_renewed' => 'secondary',
      'reservation_expired' => 'error',
      'reservation_cancelled' => 'error',
      'loan_overdue' => 'error',
      'reservation_created' => 'primary',
      'reservation_confirmed' => 'primary',
      'loan_due_soon' => 'primary',
  ];

  // Family → the reader page that actually resolves the event. Real routes only.
  $familyOf = static function (string $eventType): ?string {
      foreach (NotificationController::FAMILIES as $family => $events) {
          if (in_array($eventType, $events, true)) {
              return $family;
          }
      }

      return null;
  };

  $filterTabs = [
      ['key' => '', 'label' => __('librarian.member.notifications.filters.all'), 'count' => $totalCount],
      ['key' => 'reservations', 'label' => __('librarian.member.notifications.filters.reservations'), 'count' => $familyCounts['reservations'] ?? 0],
      ['key' => 'loans', 'label' => __('librarian.member.notifications.filters.loans'), 'count' => $familyCounts['loans'] ?? 0],
      ['key' => 'digital', 'label' => __('librarian.member.notifications.filters.digital'), 'count' => $familyCounts['digital'] ?? 0],
      ['key' => 'library', 'label' => __('librarian.member.notifications.filters.library'), 'count' => $familyCounts['library'] ?? 0],
  ];
@endphp

@section('content')
  <header class="mb-10 flex flex-col gap-6 md:mb-14 md:flex-row md:items-end md:justify-between">
    <div>
      <div class="mb-6 inline-flex items-center gap-2 rounded-full bg-surface-container-high px-3 py-1">
        <span class="h-2 w-2 rounded-full {{ $unreadCount > 0 ? 'bg-secondary' : 'bg-outline-variant' }}"></span>
        <span class="font-label text-xs uppercase tracking-widest text-on-surface-variant">
          {{ $unreadCount > 0
              ? __('librarian.member.notifications.unread_count', ['count' => $unreadCount])
              : __('librarian.member.notifications.all_read') }}
        </span>
      </div>
      <h1 class="mb-6 font-headline text-4xl leading-none tracking-tight text-primary md:text-[3.5rem]">{{ __('librarian.member.notifications.title') }}</h1>
      <p class="max-w-2xl font-body text-base leading-relaxed text-on-surface-variant md:text-lg">{{ __('librarian.member.notifications.subtitle') }}</p>
    </div>

    @if ($unreadCount > 0)
      <form method="POST" action="{{ route('member.notifications.read-all') }}" class="shrink-0">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 rounded-md px-5 py-2.5 font-label text-sm uppercase tracking-widest text-secondary ring-1 ring-outline-variant/30 transition-colors hover:bg-surface-variant">
          <span class="material-symbols-outlined text-[18px]">done_all</span>
          <span>{{ __('librarian.member.notifications.mark_all_read') }}</span>
        </button>
      </form>
    @endif
  </header>

  @include('member.partials.flash')

  <nav class="mb-10 flex gap-2 overflow-x-auto border-b border-outline-variant/20 pb-4 md:mb-12" aria-label="{{ __('librarian.member.notifications.filters.aria') }}">
    @foreach ($filterTabs as $tab)
      @php $isActive = $activeType === $tab['key']; @endphp
      <a href="{{ $tab['key'] === '' ? route('member.notifications') : route('member.notifications', ['type' => $tab['key']]) }}"
         @if ($isActive) aria-current="page" @endif
         class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 font-label text-xs uppercase tracking-widest transition-colors {{ $isActive ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-primary' }}">
        <span>{{ $tab['label'] }}</span>
        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $isActive ? 'bg-white/20 text-on-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">{{ $tab['count'] }}</span>
      </a>
    @endforeach
  </nav>

  @if ($notificationGroups->isEmpty())
    <div class="rounded-xl bg-surface-container-lowest p-10 text-center md:p-16">
      <span class="material-symbols-outlined mb-4 text-5xl text-outline">notifications_off</span>
      <h2 class="mb-3 font-headline text-2xl text-primary">
        {{ $activeType === '' ? __('librarian.member.notifications.empty_title') : __('librarian.member.notifications.empty_filtered_title') }}
      </h2>
      <p class="mx-auto max-w-xl font-body text-sm leading-relaxed text-on-surface-variant">
        {{ $activeType === '' ? __('librarian.member.notifications.empty_body') : __('librarian.member.notifications.empty_filtered_body') }}
      </p>
      @if ($activeType !== '')
        <a href="{{ route('member.notifications') }}" class="mt-6 inline-flex items-center gap-2 font-body text-sm font-medium text-secondary hover:underline">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          <span>{{ __('librarian.member.notifications.filters.all') }}</span>
        </a>
      @endif
    </div>
  @else
    <div class="space-y-12">
      @foreach ($notificationGroups as $day => $items)
        <section>
          <h2 class="mb-6 font-label text-sm uppercase tracking-widest text-on-surface-variant">
            @if ($day === $today)
              {{ __('librarian.member.notifications.today') }} · {{ $day }}
            @elseif ($day === $yesterday)
              {{ __('librarian.member.notifications.yesterday') }} · {{ $day }}
            @else
              {{ $day }}
            @endif
          </h2>

          <div class="space-y-5">
            @foreach ($items as $item)
              @php
                $isUnread = $item->read_at === null;
                $tone = $eventTones[$item->event_type] ?? 'neutral';
                $iconWrap = match ($tone) {
                    'primary' => 'bg-primary-container/10 text-primary-container',
                    'secondary' => 'bg-secondary/10 text-secondary',
                    'error' => 'bg-error-container/30 text-error',
                    default => 'bg-surface-container-highest text-on-surface-variant',
                };
                $eventKey = 'librarian.member.notifications.events.'.$item->event_type;
                $eventLabel = \Illuminate\Support\Facades\Lang::has($eventKey)
                    ? __($eventKey)
                    : __('librarian.member.notifications.events.other');
                $family = $familyOf($item->event_type);
                $contextLink = match (true) {
                    $family === 'reservations' => ['href' => route('member.reservations'), 'label' => __('librarian.member.notifications.links.reservations'), 'icon' => 'book_online'],
                    $family === 'loans' => ['href' => route('member.history'), 'label' => __('librarian.member.notifications.links.history'), 'icon' => 'history'],
                    in_array($item->event_type, ['message_received', 'message_status_changed'], true) => ['href' => route('member.messages'), 'label' => __('librarian.member.notifications.links.messages'), 'icon' => 'chat_bubble'],
                    default => null,
                };
              @endphp

              <article class="relative flex gap-5 overflow-hidden rounded-xl p-6 md:gap-6 {{ $isUnread ? 'bg-surface-container-lowest shadow-[0_24px_48px_rgba(0,6,19,0.04)]' : 'border-b border-outline-variant/20 bg-surface' }}">
                @if ($isUnread)
                  <div class="absolute bottom-0 left-0 top-0 w-1 bg-secondary"></div>
                @endif

                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $iconWrap }}">
                  <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">{{ $eventIcons[$item->event_type] ?? 'notifications' }}</span>
                </div>

                <div class="min-w-0 flex-1">
                  <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-surface-container-high px-2.5 py-1 font-label text-[11px] uppercase tracking-wider text-on-surface-variant">{{ $eventLabel }}</span>
                    @if ($isUnread)
                      <span class="rounded-full bg-secondary/10 px-2.5 py-1 font-label text-[11px] font-bold uppercase tracking-wider text-secondary">{{ __('librarian.member.notifications.unread') }}</span>
                    @endif
                  </div>

                  <div class="mb-1 flex items-start justify-between gap-4">
                    <h3 class="font-headline text-base font-medium md:text-lg {{ $isUnread ? 'text-primary' : 'text-on-surface' }}">{{ $item->localizedTitle() }}</h3>
                    <time class="whitespace-nowrap font-label text-xs text-on-surface-variant" datetime="{{ $item->created_at?->toIso8601String() }}">
                      {{ $item->created_at?->format('H:i') ?? '—' }}
                    </time>
                  </div>

                  <p class="text-sm md:text-base {{ $isUnread ? 'text-on-surface' : 'text-on-surface-variant' }}">{{ $item->localizedBody() ?: '—' }}</p>

                  <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($contextLink !== null)
                      <a href="{{ $contextLink['href'] }}" class="inline-flex items-center gap-2 rounded-md px-4 py-2 font-body text-sm font-medium text-primary ring-1 ring-outline-variant/20 transition-colors hover:bg-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">{{ $contextLink['icon'] }}</span>
                        <span>{{ $contextLink['label'] }}</span>
                      </a>
                    @endif

                    @if ($isUnread)
                      <form method="POST" action="{{ route('member.notifications.read', $item) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md px-4 py-2 font-body text-sm font-medium text-secondary transition-colors hover:bg-surface-variant">
                          <span class="material-symbols-outlined text-[18px]">done</span>
                          <span>{{ __('librarian.member.notifications.mark_read') }}</span>
                        </button>
                      </form>
                    @endif
                  </div>
                </div>
              </article>
            @endforeach
          </div>
        </section>
      @endforeach
    </div>

    <div class="mt-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <p class="font-body text-xs text-on-surface-variant">{{ __('librarian.member.notifications.total', ['count' => $notifications->total()]) }}</p>
      <div>{{ $notifications->links() }}</div>
    </div>
  @endif
@endsection
