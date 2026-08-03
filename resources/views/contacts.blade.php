@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'contacts';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $copy = $contacts[$lang];
  $chromeTitle = [
      'ru' => 'Контакты — KazUTB',
      'kk' => 'Байланыс — KazUTB',
      'en' => 'Contacts — KazUTB',
  ][$lang];
@endphp

@section('title', $chromeTitle)

@section('content')
  <div class="contacts-canonical">
    {{-- Left (7fr): hero intro + support channels. Right (5fr): form + location. --}}
    {{-- Hero is the first child of the left column so it baseline-aligns with the form card. --}}
    <header class="hs hs-section contacts-canonical__hero-inline" data-section="contacts-canonical-hero">
      <div class="hs-head__copy">
        @isset($copy['hero_eyebrow'])
          <p class="hs-kicker">{{ $copy['hero_eyebrow'] }}</p>
        @endisset
        <h1 class="hs-title hs-title--display contacts-canonical__display">
          {{ $copy['hero_title_a'] }} {{ $copy['hero_title_b'] }}
        </h1>
        <p class="hs-lead contacts-canonical__lead">{{ $copy['hero_body'] }}</p>
      </div>
    </header>

    <div class="hs hs-section hs-section--wash hs-section--ruled contacts-canonical__grid">
      <div class="contacts-canonical__col-left-wrap">
        <section class="contacts-canonical__col-left" data-section="contacts-canonical-support">
        <h2 class="contacts-canonical__section-heading">{{ $copy['support_heading'] }}</h2>
        <div class="contacts-canonical__channels">
          @foreach($copy['support_channels'] as $channel)
            <article class="contacts-canonical__channel-card" data-support-channel data-channel-slug="{{ $channel['slug'] }}">
              <div class="contacts-canonical__channel-icon" aria-hidden="true">
                <span class="material-symbols-outlined">{{ $channel['icon'] }}</span>
              </div>
              <div class="contacts-canonical__channel-body">
                <h3 class="contacts-canonical__channel-title">{{ $channel['title'] }}</h3>
                <p class="contacts-canonical__channel-desc">{{ $channel['body'] }}</p>
                <div class="contacts-canonical__channel-contacts">
                  <div class="contacts-canonical__channel-contact">
                    <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                    <a href="mailto:{{ $channel['email'] }}" data-test-id="contacts-canonical-channel-email-{{ $channel['slug'] }}">{{ $channel['email'] }}</a>
                  </div>
                  <div class="contacts-canonical__channel-contact">
                    <span class="material-symbols-outlined" aria-hidden="true">call</span>
                    <span>{{ $channel['phone'] }}</span>
                  </div>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </section>
      </div>{{-- /.contacts-canonical__col-left-wrap --}}

      <aside class="contacts-canonical__col-right">
        {{-- Inquiry form — UI-only surface; submit opens mailto to main library inbox. --}}
        <section class="contacts-canonical__form-card" data-section="contacts-canonical-inquiry-form">
          <h3 class="contacts-canonical__card-heading">{{ $copy['form_title'] }}</h3>
          <p class="contacts-canonical__form-note">{{ $copy['form_note'] }}</p>
          <form class="contacts-canonical__inquiry-form"
                action="mailto:library@kazutb.edu.kz"
                method="post"
                enctype="text/plain"
                data-test-id="contacts-canonical-inquiry-form">
            <label class="contacts-canonical__field">
              <span>{{ $lang === 'kk' ? 'Аты-жөніңіз' : ($lang === 'en' ? 'Your name' : 'Ваше имя') }}</span>
              <input type="text" name="name" autocomplete="name" required>
            </label>
            <label class="contacts-canonical__field">
              <span>{{ $lang === 'kk' ? 'Тақырып' : ($lang === 'en' ? 'Subject' : 'Тема обращения') }}</span>
              <input type="text" name="subject" required>
            </label>
            <label class="contacts-canonical__field">
              <span>{{ $lang === 'kk' ? 'Хабарлама' : ($lang === 'en' ? 'Message' : 'Сообщение') }}</span>
              <textarea name="message" rows="5" required></textarea>
            </label>
            <button type="submit"
                    class="contacts-canonical__inquiry-submit"
                    data-test-id="contacts-canonical-inquiry-submit">
              <span class="material-symbols-outlined" aria-hidden="true">send</span>
              {{ $lang === 'kk' ? 'Хат жіберу' : ($lang === 'en' ? 'Send message' : 'Отправить сообщение') }}
            </button>
          </form>
          <div class="contacts-canonical__inquiry-list">
            <p class="contacts-canonical__inquiry-lead">{{ $lang === 'kk' ? 'Немесе тікелей электрондық поштаға жазыңыз:' : ($lang === 'en' ? 'Or email us directly at:' : 'Или напишите напрямую на электронные адреса:') }}</p>
            <div class="contacts-canonical__inquiry-items">
              @foreach($copy['support_channels'] as $channel)
                <div class="contacts-canonical__inquiry-item">
                  <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                  <a href="mailto:{{ $channel['email'] }}">{{ $channel['title'] }} — {{ $channel['email'] }}</a>
                </div>
              @endforeach
            </div>
          </div>
        </section>

        {{-- Location + hours card. --}}
        <section class="contacts-canonical__location-card" data-section="contacts-canonical-location">
          <h4 class="contacts-canonical__card-heading contacts-canonical__card-heading--with-icon">
            <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
            {{ $copy['location_title'] }}
          </h4>
          <p class="contacts-canonical__location-body">
            {{ $copy['location_address_line_a'] }}<br>
            {{ $copy['location_address_line_b'] }}
          </p>
          <div class="contacts-canonical__location-contact">
            <div class="contacts-canonical__channel-contact">
              <span class="material-symbols-outlined" aria-hidden="true">call</span>
              <span>{{ $copy['location_phone'] }}</span>
            </div>
            <div class="contacts-canonical__channel-contact">
              <span class="material-symbols-outlined" aria-hidden="true">mail</span>
              <a href="mailto:{{ $copy['location_email'] }}">{{ $copy['location_email'] }}</a>
            </div>
          </div>
          <a class="contacts-canonical__directions-link"
             href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($copy['location_address_line_a'] . ', ' . $copy['location_address_line_b']) }}"
             target="_blank"
             rel="noopener noreferrer"
             data-test-id="contacts-canonical-directions">
            <span class="material-symbols-outlined" aria-hidden="true">directions</span>
            {{ $copy['location_directions_cta'] }}
          </a>
          <div class="contacts-canonical__hours">
            <p class="contacts-canonical__hours-label">{{ $copy['hours_label'] }}</p>
            @foreach($copy['hours_rows'] as $row)
              <div class="contacts-canonical__hours-row">
                <span>{{ $row['days'] }}</span>
                <span>{{ $row['hours'] }}</span>
              </div>
            @endforeach
          </div>
        </section>
      </aside>
    </div>


    {{--
      Reading-room staff. Placed after the fund rooms and before the visit
      guidance: the reader has just seen which room holds what, so the next
      useful thing is who to ask there.

      Each card shows a portrait when one exists under public/ and the person's
      initials until then, so adding a photo is a file drop with no code change.
    --}}
    @if(!empty($copy['staff']))
      <section class="hs hs-section contacts-canonical__section contacts-canonical__staff" data-section="contacts-canonical-staff">
        <div class="contacts-canonical__staff-head">
          <span class="material-symbols-outlined" aria-hidden="true">badge</span>
          <h2 class="contacts-canonical__section-heading">{{ $copy['staff_heading'] }}</h2>
        </div>
        <p class="contacts-canonical__staff-note">{{ $copy['staff_note'] }}</p>

        <div class="contacts-canonical__staff-grid">
          @foreach($copy['staff'] as $member)
            @php
              $photoPath = trim((string) ($member['photo'] ?? ''));
              $hasPhoto = $photoPath !== '' && is_file(public_path($photoPath));
            @endphp
            <article class="contacts-canonical__staff-card" data-staff-slot data-staff-slug="{{ $member['slug'] }}">
              <div class="contacts-canonical__staff-media">
                @if($hasPhoto)
                  <img class="contacts-canonical__staff-photo"
                       src="/{{ ltrim($photoPath, '/') }}"
                       alt="{{ $member['name'] }}"
                       loading="lazy" decoding="async">
                @else
                  <span class="contacts-canonical__staff-photo--empty" aria-hidden="true">{{ $member['initials'] }}</span>
                @endif
              </div>
              <div class="contacts-canonical__staff-body">
                <h3 class="contacts-canonical__staff-name">{{ $member['name'] }}</h3>
                <p class="contacts-canonical__staff-role">{{ $member['role'] }}</p>
              </div>
            </article>
          @endforeach
        </div>
      </section>
    @endif

    {{-- Visit guidance + cross-links to /rules and /leadership. --}}
    <section class="hs hs-section hs-section--wash hs-section--ruled contacts-canonical__section contacts-canonical__visit" data-section="contacts-canonical-visit-rules">
      <div class="contacts-canonical__visit-grid">
        <div class="contacts-canonical__visit-copy">
          <h3 class="contacts-canonical__section-heading">{{ $copy['visit_title'] }}</h3>
          <p class="contacts-canonical__visit-body">{{ $copy['visit_body'] }}</p>
        </div>
        <ul class="contacts-canonical__visit-links">
          <li>
            <a class="contacts-canonical__visit-link" href="{{ $routeWithLang('/rules') }}" data-test-id="contacts-canonical-link-rules">
              <span class="contacts-canonical__visit-link-title">{{ $copy['visit_link_rules_title'] }}</span>
              <span class="contacts-canonical__visit-link-body">{{ $copy['visit_link_rules_body'] }}</span>
              <span class="material-symbols-outlined contacts-canonical__visit-link-arrow" aria-hidden="true">arrow_forward</span>
            </a>
          </li>
          <li>
            <a class="contacts-canonical__visit-link" href="{{ $routeWithLang('/leadership') }}" data-test-id="contacts-canonical-link-leadership">
              <span class="contacts-canonical__visit-link-title">{{ $copy['visit_link_leadership_title'] }}</span>
              <span class="contacts-canonical__visit-link-body">{{ $copy['visit_link_leadership_body'] }}</span>
              <span class="material-symbols-outlined contacts-canonical__visit-link-arrow" aria-hidden="true">arrow_forward</span>
            </a>
          </li>
        </ul>
      </div>
    </section>
  </div>
@endsection

@section('head')
<style>
  .contacts-canonical {
    max-width: 1280px;
    margin: 0 auto;
    padding: 80px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .contacts-canonical {
      padding: 96px 24px 96px;
    }
  }

  @media (min-width: 1024px) {
    .contacts-canonical {
      padding-left: 32px;
      padding-right: 32px;
    }
  }

  .contacts-canonical__section {
    margin-bottom: 80px;
  }

  .contacts-canonical__hero {
    position: relative;
    padding-top: 32px;
  }

  .contacts-canonical__hero-glow {
    position: absolute;
    top: 0;
    right: 0;
    width: 256px;
    height: 256px;
    background: rgba(0, 31, 63, 0.05);
    border-radius: 9999px;
    filter: blur(48px);
    z-index: -1;
    pointer-events: none;
  }

  .contacts-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 300;
    font-size: 44px;
    line-height: 1.08;
    letter-spacing: -0.02em;
    color: #000613;
    margin: 0 0 20px -2px;
  }

  @media (min-width: 768px) {
    .contacts-canonical__display {
      font-size: 56px;
    }
  }

  .contacts-canonical__display-accent {
    color: #001f3f;
    font-style: italic;
  }

  .contacts-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 17px;
    line-height: 1.62;
    color: #43474e;
    max-width: 680px;
    margin: 0;
  }

  .contacts-canonical__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
    margin-bottom: 80px;
  }

  @media (min-width: 1024px) {
    .contacts-canonical__grid {
      grid-template-columns: 7fr 5fr;
      gap: 48px;
      align-items: start;
    }
  }

  .contacts-canonical__section-heading {
    font-family: 'Newsreader', serif;
    font-size: 28px;
    line-height: 1.2;
    color: #000613;
    margin: 0 0 32px;
  }

  .contacts-canonical__col-left {
    display: flex;
    flex-direction: column;
  }

  .contacts-canonical__col-left-wrap {
    display: flex;
    flex-direction: column;
  }

  .contacts-canonical__hero-inline {
    position: relative;
    padding-top: 32px;
    margin-bottom: 56px;
  }

  .contacts-canonical__channels {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .contacts-canonical__channel-card {
    background: #ffffff;
    border: 1px solid rgba(196, 198, 207, 0.55);
    border-radius: 8px;
    padding: 32px;
    box-shadow: 0 10px 22px rgba(0, 6, 19, 0.03);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 24px;
  }

  .contacts-canonical__channel-card:hover {
    background: #f3f4f5;
    border-color: rgba(0, 106, 106, 0.35);
    box-shadow: 0 14px 28px rgba(0, 6, 19, 0.05);
  }

  .contacts-canonical__channel-icon {
    flex-shrink: 0;
    width: 56px;
    height: 56px;
    border-radius: 9999px;
    background: #f8f9fa;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #006a6a;
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  .contacts-canonical__channel-card:hover .contacts-canonical__channel-icon {
    background: #90efef;
    color: #006e6e;
  }

  .contacts-canonical__channel-icon .material-symbols-outlined {
    font-size: 28px;
  }

  .contacts-canonical__channel-body {
    flex: 1;
  }

  .contacts-canonical__channel-title {
    font-family: 'Manrope', sans-serif;
    font-weight: 700;
    font-size: 20px;
    line-height: 1.3;
    color: #000613;
    margin: 0 0 8px;
  }

  .contacts-canonical__channel-desc {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #43474e;
    margin: 0 0 20px;
  }

  .contacts-canonical__channel-contacts {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 16px;
  }

  @media (min-width: 640px) {
    .contacts-canonical__channel-contacts {
      flex-direction: row;
      gap: 24px;
    }
  }

  .contacts-canonical__channel-contact {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #191c1d;
  }

  .contacts-canonical__channel-contact .material-symbols-outlined {
    font-size: 18px;
    color: #74777f;
  }

  .contacts-canonical__channel-contact a {
    color: inherit;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s ease;
  }

  .contacts-canonical__channel-contact a:hover {
    border-color: #006a6a;
  }

  .contacts-canonical__col-right {
    display: flex;
    flex-direction: column;
    gap: 32px;
  }

  .contacts-canonical__form-card {
    background: #f3f4f5;
    padding: 32px;
    border-radius: 12px;
    border: 1px solid rgba(196, 198, 207, 0.55);
    box-shadow: 0 12px 28px rgba(0, 6, 19, 0.035);
  }

  .contacts-canonical__card-heading {
    font-family: 'Newsreader', serif;
    font-size: 24px;
    line-height: 1.25;
    color: #000613;
    margin: 0 0 16px;
    display: block;
  }

  .contacts-canonical__card-heading--with-icon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .contacts-canonical__card-heading--with-icon .material-symbols-outlined {
    color: #006a6a;
    font-size: 24px;
  }

  .contacts-canonical__form-note {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    margin: 0 0 20px;
    line-height: 1.5;
  }

  .contacts-canonical__inquiry-form {
    display: grid;
    gap: 16px;
    margin-bottom: 24px;
  }

  .contacts-canonical__field {
    display: grid;
    gap: 6px;
  }

  .contacts-canonical__field span {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #43474e;
  }

  .contacts-canonical__field input,
  .contacts-canonical__field textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid rgba(196, 198, 207, 0.8);
    border-radius: 6px;
    background: #ffffff;
    font-family: 'Manrope', sans-serif;
    font-size: 0.9375rem;
    color: #191c1d;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }

  .contacts-canonical__field input:focus,
  .contacts-canonical__field textarea:focus {
    outline: none;
    border-color: #006a6a;
    box-shadow: 0 0 0 3px rgba(0, 106, 106, 0.12);
  }

  .contacts-canonical__inquiry-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    background: #006a6a;
    border: 0;
    border-radius: 6px;
    color: #ffffff;
    font-family: 'Manrope', sans-serif;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
  }

  .contacts-canonical__inquiry-submit:hover {
    background: #00524f;
  }

  .contacts-canonical__inquiry-submit .material-symbols-outlined {
    font-size: 1.1rem;
  }

  .contacts-canonical__inquiry-list {
    padding: 16px 0 2px;
  }

  .contacts-canonical__inquiry-lead {
    font-size: 15px;
    line-height: 1.6;
    color: #43474e;
    margin: 0 0 16px;
  }

  .contacts-canonical__inquiry-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .contacts-canonical__inquiry-item {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    min-height: 36px;
  }

  .contacts-canonical__inquiry-item .material-symbols-outlined {
    font-size: 20px;
    color: #476083;
  }

  .contacts-canonical__inquiry-item a {
    color: #2f486a;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.2s ease, color 0.2s ease;
  }

  .contacts-canonical__inquiry-item a:hover,
  .contacts-canonical__inquiry-item a:focus-visible {
    color: #001f3f;
    border-bottom-color: rgba(0, 31, 63, 0.35);
  }

  .contacts-canonical__form {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .contacts-canonical__field {
    display: flex;
    flex-direction: column;
  }

  .contacts-canonical__field label {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: #191c1d;
    margin-bottom: 6px;
  }

  .contacts-canonical__field input,
  .contacts-canonical__field select,
  .contacts-canonical__field textarea {
    width: 100%;
    background: #e1e3e4;
    border: 0;
    border-bottom: 1px solid rgba(196, 198, 207, 0.35);
    border-radius: 6px 6px 0 0;
    padding: 12px 16px;
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    color: #191c1d;
    transition: border-color 0.2s ease;
  }

  .contacts-canonical__field textarea {
    resize: vertical;
    min-height: 96px;
  }

  .contacts-canonical__field input:focus,
  .contacts-canonical__field select:focus,
  .contacts-canonical__field textarea:focus {
    outline: none;
    border-bottom-color: #006a6a;
  }

  .contacts-canonical__form-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border-radius: 6px;
    border: 0;
    background: linear-gradient(to right, #000613, #001f3f);
    color: #ffffff;
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.2s ease;
  }

  .contacts-canonical__form-submit:hover {
    opacity: 0.9;
  }

  .contacts-canonical__form-submit .material-symbols-outlined {
    font-size: 18px;
  }

  .contacts-canonical__location-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 8px;
    border: 1px solid rgba(196, 198, 207, 0.55);
    box-shadow: 0 10px 22px rgba(0, 6, 19, 0.03);
  }

  .contacts-canonical__location-body {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #43474e;
    margin: 0 0 20px;
  }

  .contacts-canonical__location-contact {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 16px;
  }

  .contacts-canonical__directions-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid rgba(0, 106, 106, 0.28);
    background: rgba(0, 106, 106, 0.04);
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
  }

  .contacts-canonical__directions-link:hover {
    border-color: #006a6a;
    background: rgba(0, 106, 106, 0.08);
  }

  .contacts-canonical__directions-link .material-symbols-outlined {
    font-size: 18px;
  }

  .contacts-canonical__hours {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid rgba(196, 198, 207, 0.35);
  }

  .contacts-canonical__hours-label {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 600;
    color: #191c1d;
    margin: 0 0 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  .contacts-canonical__hours-row {
    display: flex;
    justify-content: space-between;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    color: #43474e;
    line-height: 1.6;
  }

  .contacts-canonical__staff-head {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
  }

  .contacts-canonical__staff-head .material-symbols-outlined {
    font-size: 28px;
    color: #000613;
  }

  .contacts-canonical__staff-head .contacts-canonical__section-heading {
    margin: 0;
  }

  .contacts-canonical__staff-note {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #43474e;
    margin: 0 0 32px;
    max-width: 720px;
  }


  /* ── Reading-room staff ──────────────────────────────────── */

  /* auto-FILL, not auto-fit: with only two people on the roster auto-fit would
     collapse the empty tracks and stretch each card to half the page, and a 4:5
     portrait that wide becomes absurdly tall. auto-fill keeps the track width,
     so cards stay portrait-sized and the row simply fills up as staff are added. */
  .contacts-canonical__staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
  }

  /* Single column below the point where a 210px track would squeeze the name. */
  @media (max-width: 479px) {
    .contacts-canonical__staff-grid {
      grid-template-columns: 1fr;
    }
  }

  .contacts-canonical__staff-card {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid rgba(196, 198, 207, 0.5);
    border-radius: 12px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
  }

  .contacts-canonical__staff-card:hover {
    border-color: rgba(0, 106, 106, 0.4);
    box-shadow: 0 6px 18px rgba(25, 28, 29, 0.06);
  }

  /* Fixed portrait ratio on the media box, not on the image: every card keeps
     the same height whether it carries a photo or the initials placeholder, so
     the grid does not go ragged while portraits are still being collected. */
  .contacts-canonical__staff-media {
    position: relative;
    aspect-ratio: 4 / 5;
    background: rgba(0, 106, 106, 0.08);
  }

  /* Full-width single-column card: keep the portrait shallower, because a 4:5
     crop at phone width would push the name below the fold. Declared after the
     base rule on purpose — same specificity, so source order decides. */
  @media (max-width: 479px) {
    .contacts-canonical__staff-media {
      aspect-ratio: 4 / 3;
    }
  }

  /* Absolutely positioned so the photo cannot drive the media box's height:
     in normal flow its intrinsic ratio wins over the box's aspect-ratio, and
     cards end up different heights depending on how each portrait was shot. */
  .contacts-canonical__staff-photo {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    /* Portraits will not all be framed alike; biasing the crop upwards keeps
       faces in view rather than chins. */
    object-position: center 25%;
    display: block;
  }

  .contacts-canonical__staff-photo--empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #006a6a;
    font-family: 'Newsreader', serif;
    font-size: 56px;
    font-weight: 600;
    letter-spacing: 0.02em;
  }

  .contacts-canonical__staff-body {
    padding: 24px;
    border-top: 1px solid rgba(196, 198, 207, 0.5);
  }

  .contacts-canonical__staff-name {
    font-family: 'Newsreader', serif;
    font-size: 22px;
    line-height: 1.3;
    color: #000613;
    margin: 0 0 8px;
  }

  .contacts-canonical__staff-role {
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #43474e;
    margin: 0;
  }

  .contacts-canonical__visit {
    background: #f3f4f5;
    border-radius: 12px;
    padding: 32px;
    border: 1px solid rgba(196, 198, 207, 0.5);
  }

  @media (min-width: 768px) {
    .contacts-canonical__visit {
      padding: 48px;
    }
  }

  .contacts-canonical__visit-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
  }

  @media (min-width: 1024px) {
    .contacts-canonical__visit-grid {
      grid-template-columns: 1fr 1fr;
      gap: 48px;
      align-items: start;
    }
  }

  .contacts-canonical__visit-body {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #43474e;
    margin: 0;
    max-width: 480px;
  }

  .contacts-canonical__visit-links {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .contacts-canonical__visit-link {
    display: grid;
    grid-template-columns: 1fr auto;
    grid-template-rows: auto auto;
    row-gap: 4px;
    column-gap: 16px;
    align-items: center;
    padding: 20px 24px;
    background: #ffffff;
    border: 1px solid rgba(196, 198, 207, 0.5);
    border-radius: 8px;
    text-decoration: none;
    color: #000613;
    transition: background-color 0.2s ease, border-color 0.2s ease;
  }

  .contacts-canonical__visit-link:hover {
    background: #000613;
    color: #ffffff;
    border-color: #000613;
  }

  @media (max-width: 640px) {
    .contacts-canonical__hero-inline {
      margin-bottom: 40px;
    }

    .contacts-canonical__channel-card,
    .contacts-canonical__form-card,
    .contacts-canonical__visit {
      padding: 24px;
    }

    .contacts-canonical__location-card {
      padding: 20px;
    }

    .contacts-canonical__visit-link {
      padding: 16px;
    }
  }

  .contacts-canonical__visit-link-title {
    font-family: 'Newsreader', serif;
    font-size: 20px;
    font-weight: 500;
    grid-column: 1;
    grid-row: 1;
  }

  .contacts-canonical__visit-link-body {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: inherit;
    opacity: 0.75;
    grid-column: 1;
    grid-row: 2;
  }

  .contacts-canonical__visit-link-arrow {
    grid-column: 2;
    grid-row: 1 / span 2;
    font-size: 20px;
    transition: transform 0.2s ease;
  }

  .contacts-canonical__visit-link:hover .contacts-canonical__visit-link-arrow {
    transform: translateX(4px);
  }
</style>
@endsection
