@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $activePage = $activePage ?? 'contacts';
  $copy = $contacts[$lang];

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk') $query['lang'] = $lang;
      $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));
      return $path.($queryString !== '' ? '?'.$queryString : '');
  };

  $memberMessagesPath = $routeWithLang('/dashboard/messages');
  $contactSessionRole = mb_strtolower(trim((string) data_get(session('library.user'), 'role', '')));
  $contactCanonicalRole = mb_strtolower(trim((string) data_get(session('library.user'), 'canonical_role', '')));
  if ($contactCanonicalRole === '') {
      $contactCanonicalRole = $contactSessionRole === 'reader' ? 'member' : $contactSessionRole;
  }
  $requestPath = session('library.user')
      ? match ($contactCanonicalRole) {
          'admin' => $routeWithLang('/admin/messages'),
          'member' => $memberMessagesPath,
          default => $routeWithLang('/librarian/messages'),
      }
      : $routeWithLang('/login', ['redirect' => $memberMessagesPath]);
  $requestCopy = [
      'ru' => [
          'eyebrow' => 'Обращение в библиотеку',
          'title' => 'Отправить официальный запрос',
          'body' => 'Обращения регистрируются в личном кабинете: там сохраняются номер запроса, ответы библиотеки и история переписки.',
          'cta' => session('library.user') ? 'Открыть обращения' : 'Войти, чтобы отправить обращение',
          'rooms' => 'Библиотечные точки',
          'staff' => 'Сотрудники библиотеки',
          'related' => 'Полезно перед визитом',
      ],
      'kk' => [
          'eyebrow' => 'Кітапханаға өтініш',
          'title' => 'Ресми сұрау жіберу',
          'body' => 'Өтініштер жеке кабинетте тіркеледі: онда сұрау нөмірі, кітапхана жауаптары және хат алмасу тарихы сақталады.',
          'cta' => session('library.user') ? 'Өтініштерді ашу' : 'Өтініш жіберу үшін кіру',
          'rooms' => 'Кітапхана нүктелері',
          'staff' => 'Кітапхана қызметкерлері',
          'related' => 'Келмес бұрын пайдалы ақпарат',
      ],
      'en' => [
          'eyebrow' => 'Library inquiry',
          'title' => 'Submit an official request',
          'body' => 'Requests are registered in the member portal, where the ticket number, library replies, and conversation history remain available.',
          'cta' => session('library.user') ? 'Open requests' : 'Sign in to submit a request',
          'rooms' => 'Library service points',
          'staff' => 'Library staff',
          'related' => 'Useful before your visit',
      ],
  ][$lang];
@endphp

@section('title', $copy['hero_title_a'].' '.$copy['hero_title_b'].' — '.__('brand.university.full'))
@section('meta_description', $copy['hero_body'])

@section('content')
<div class="public-page contacts-page">
  <header class="public-page__intro public-page__intro--functional" data-section="contacts-canonical-hero">
    <div class="public-container public-page__intro-grid">
      <div>
        <p class="public-eyebrow">{{ $copy['hero_eyebrow'] }}</p>
        <h1 class="public-page__title">{{ $copy['hero_title_a'] }} {{ $copy['hero_title_b'] }}</h1>
        <p class="public-page__lead">{{ $copy['hero_body'] }}</p>
      </div>
      <dl class="public-page__summary" aria-label="{{ $copy['hours_label'] }}">
        <div><dt>{{ $copy['hours_label'] }}</dt><dd>{{ $copy['hours_rows'][0]['hours'] }}</dd></div>
        <div><dt>{{ $copy['location_title'] }}</dt><dd>{{ $copy['location_address_line_a'] }}</dd></div>
      </dl>
    </div>
  </header>

  <div class="public-page__body">
    <div class="public-container public-stack">
      <section aria-labelledby="support-heading" data-section="contacts-canonical-support">
        <div class="public-section-head">
          <p class="public-eyebrow">{{ $copy['support_heading'] }}</p>
          <h2 id="support-heading">{{ $copy['support_heading'] }}</h2>
        </div>
        <div class="public-card-grid public-card-grid--three contacts-page__channels">
          @foreach($copy['support_channels'] as $channel)
            <article class="public-card" data-support-channel data-channel-slug="{{ $channel['slug'] }}">
              <span class="public-card__icon material-symbols-outlined" aria-hidden="true">{{ $channel['icon'] }}</span>
              <h3>{{ $channel['title'] }}</h3>
              <p>{{ $channel['body'] }}</p>
              <div class="public-card__meta">
                @if(!empty($channel['email']))
                  <a href="mailto:{{ $channel['email'] }}" data-test-id="contacts-canonical-channel-email-{{ $channel['slug'] }}">{{ $channel['email'] }}</a>
                @endif
                @if(!empty($channel['phone']))
                  <a href="tel:{{ preg_replace('/[^+0-9]/', '', $channel['phone']) }}">{{ $channel['phone'] }}</a>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      </section>

      <section class="contacts-page__visit-grid" aria-labelledby="location-heading" data-section="contacts-canonical-location">
        <article class="public-panel contacts-page__location">
          <p class="public-eyebrow">{{ $copy['location_title'] }}</p>
          <h2 id="location-heading">{{ $copy['location_address_line_a'] }}</h2>
          <p>{{ $copy['location_address_line_b'] }}</p>
          <div class="contacts-page__contact-list">
            <a href="tel:{{ preg_replace('/[^+0-9]/', '', $copy['location_phone']) }}"><span class="material-symbols-outlined" aria-hidden="true">call</span>{{ $copy['location_phone'] }}</a>
            <a href="mailto:{{ $copy['location_email'] }}"><span class="material-symbols-outlined" aria-hidden="true">mail</span>{{ $copy['location_email'] }}</a>
            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($copy['location_address_line_a']) }}" target="_blank" rel="noopener noreferrer" data-test-id="contacts-canonical-directions"><span class="material-symbols-outlined" aria-hidden="true">directions</span>{{ $copy['location_directions_cta'] }}</a>
          </div>
        </article>
        <article class="public-panel contacts-page__hours">
          <p class="public-eyebrow">{{ $copy['hours_label'] }}</p>
          <h2>{{ $copy['hours_label'] }}</h2>
          <dl>
            @foreach($copy['hours_rows'] as $row)
              <div><dt>{{ $row['days'] }}</dt><dd>{{ $row['hours'] }}</dd></div>
            @endforeach
          </dl>
          <a href="{{ $copy['hours_source_url'] }}" target="_blank" rel="noopener noreferrer" data-test-id="contacts-official-hours-source">{{ $copy['hours_source_label'] }}<span class="material-symbols-outlined" aria-hidden="true">open_in_new</span></a>
        </article>
      </section>

      @if(!empty($copy['fund_rooms']))
        <section aria-labelledby="rooms-heading" data-section="contacts-canonical-fund-rooms">
          <div class="public-section-head">
            <p class="public-eyebrow">{{ $requestCopy['rooms'] }}</p>
            <h2 id="rooms-heading">{{ $copy['wayfinding_title'] }}</h2>
            <p>{{ $copy['wayfinding_body'] }}</p>
          </div>
          <div class="public-card-grid public-card-grid--three">
            @foreach($copy['fund_rooms'] as $room)
              <article class="public-card contacts-page__room" data-room-slot>
                <div class="contacts-page__room-meta"><strong>{{ $copy['room_prefix'] }} {{ $room['room'] }}</strong><span>{{ $room['floor'] }}</span></div>
                <h3>{{ $room['fund_label'] }}</h3>
                <p>{{ $room['short_description'] }}</p>
                <small>{{ $room['access_note'] }}</small>
              </article>
            @endforeach
          </div>
        </section>
      @endif

      <section class="public-callout contacts-page__request" data-section="contacts-canonical-inquiry-form">
        <div>
          <p class="public-eyebrow">{{ $requestCopy['eyebrow'] }}</p>
          <h2>{{ $requestCopy['title'] }}</h2>
          <p>{{ $requestCopy['body'] }}</p>
        </div>
        <a class="public-button public-button--primary" href="{{ $requestPath }}" data-test-id="contacts-canonical-inquiry-cta">{{ $requestCopy['cta'] }}<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
      </section>

      @if(!empty($copy['staff']))
        <section aria-labelledby="staff-heading" data-section="contacts-canonical-staff">
          <div class="public-section-head">
            <p class="public-eyebrow">{{ $requestCopy['staff'] }}</p>
            <h2 id="staff-heading">{{ $copy['staff_heading'] }}</h2>
            <p>{{ $copy['staff_note'] }}</p>
          </div>
          <div class="public-card-grid public-card-grid--three">
            @foreach($copy['staff'] as $member)
              <article class="public-card contacts-page__person" data-staff-slot data-staff-slug="{{ $member['slug'] }}">
                <span class="contacts-page__initials" aria-hidden="true">{{ $member['initials'] }}</span>
                <div><h3>{{ $member['name'] }}</h3><p>{{ $member['role'] }}</p></div>
              </article>
            @endforeach
          </div>
        </section>
      @endif

      <section aria-labelledby="related-heading" data-section="contacts-canonical-visit-rules">
        <div class="public-section-head"><p class="public-eyebrow">{{ $requestCopy['related'] }}</p><h2 id="related-heading">{{ $copy['visit_title'] }}</h2><p>{{ $copy['visit_body'] }}</p></div>
        <div class="public-link-grid">
          <a href="{{ $routeWithLang('/rules') }}" data-test-id="contacts-canonical-link-rules"><strong>{{ $copy['visit_link_rules_title'] }}</strong><span>{{ $copy['visit_link_rules_body'] }}</span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
          <a href="{{ $routeWithLang('/leadership') }}" data-test-id="contacts-canonical-link-leadership"><strong>{{ $copy['visit_link_leadership_title'] }}</strong><span>{{ $copy['visit_link_leadership_body'] }}</span><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
