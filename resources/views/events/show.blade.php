@extends('layouts.public')

@php
    $lang = request()->query('lang', 'ru');
    $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';

    $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
        if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
            $query['lang'] = $lang;
        }
        $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
        return $path . ($qs !== '' ? ('?' . $qs) : '');
    };

    $copy = $chrome[$lang];
    $baseI18n = $event['i18n'][$lang];
    $detailI18n = $detail['i18n'][$lang];
    $indexCopy = $indexChrome[$lang];
@endphp

@section('title', $baseI18n['title'] . ' — KazUTB Smart Library')

@section('head')
<style>
    .event-detail {
        --primary: #000613;
        --secondary: #006a6a;
        --on-secondary-fixed-variant: #004f4f;
        --on-surface: #191c1d;
        --on-surface-variant: #43474e;
        --surface: #f8f9fa;
        --surface-container-lowest: #ffffff;
        --surface-container-low: #f3f4f5;
        --surface-container: #edeeef;
        --surface-container-high: #e7e8e9;
        --surface-container-highest: #e1e3e4;
        --surface-variant: #e1e3e4;
        --outline-variant: #c4c6cf;
        --primary-container: #001f3f;
        --tertiary-fixed: #d1e4fb;
        --on-tertiary-fixed: #091d2e;
        --primary-fixed-dim: #afc8f0;

        background: var(--surface);
        color: var(--on-surface);
        padding: 64px 0 96px;
        font-family: 'Manrope', sans-serif;
    }
    .event-detail__container {
        max-width: 1152px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .event-detail__breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--on-surface-variant);
        margin-bottom: 32px;
    }
    .event-detail__breadcrumb a {
        color: var(--on-surface-variant);
        text-decoration: none;
        transition: color .2s ease;
    }
    .event-detail__breadcrumb a:hover {
        color: var(--secondary);
    }
    .event-detail__breadcrumb .material-symbols-outlined {
        font-size: 16px;
    }
    .event-detail__breadcrumb-current {
        color: var(--on-surface);
        font-weight: 500;
    }
    .event-detail__back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--secondary);
        font-weight: 500;
        font-size: 14px;
        text-decoration: none;
        margin-bottom: 32px;
        transition: transform .2s ease;
    }
    .event-detail__back:hover {
        transform: translateX(-2px);
    }
    .event-detail__back .material-symbols-outlined {
        font-size: 20px;
    }

    .event-detail__hero {
        display: flex;
        flex-direction: column;
        gap: 48px;
        margin-bottom: 72px;
    }
    @media (min-width: 1024px) {
        .event-detail__hero {
            flex-direction: row;
            align-items: flex-start;
        }
        .event-detail__hero-body { flex: 1; padding-right: 32px; }
        .event-detail__hero-visual { width: 45%; flex-shrink: 0; }
    }
    .event-detail__categories {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
    }
    .event-detail__category {
        background: var(--surface-container-high);
        color: var(--on-surface);
        padding: 4px 12px;
        border-radius: 2px;
        font-size: 13px;
        font-weight: 500;
        letter-spacing: 0.02em;
    }
    .event-detail__category--secondary {
        background: var(--tertiary-fixed);
        color: var(--on-tertiary-fixed);
    }
    .event-detail__title {
        font-family: 'Newsreader', serif;
        font-size: clamp(40px, 5.5vw, 56px);
        line-height: 1.1;
        color: var(--primary);
        letter-spacing: -0.01em;
        margin-bottom: 32px;
        font-weight: 400;
    }
    .event-detail__lead {
        font-size: 20px;
        color: var(--on-surface-variant);
        line-height: 1.6;
        margin-bottom: 40px;
        max-width: 640px;
    }
    .event-detail__meta-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 32px;
        padding: 24px;
        background: var(--surface-container-low);
        border-top: 1px solid rgba(196,198,207,0.3);
        border-bottom: 1px solid rgba(196,198,207,0.3);
        border-radius: 4px;
        margin-bottom: 32px;
    }
    .event-detail__meta-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .event-detail__meta-item .material-symbols-outlined {
        color: var(--secondary);
        margin-top: 2px;
    }
    .event-detail__meta-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--primary);
        margin: 0 0 2px;
    }
    .event-detail__meta-value {
        font-size: 13px;
        color: var(--on-surface-variant);
        margin: 0;
    }
    .event-detail__cta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    .event-detail__cta-primary {
        background: linear-gradient(to right, var(--primary), var(--primary-container));
        color: #ffffff;
        border: none;
        padding: 12px 32px;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: opacity .2s ease;
    }
    .event-detail__cta-primary:hover {
        opacity: .9;
    }
    .event-detail__cta-secondary {
        background: transparent;
        border: 1px solid rgba(196,198,207,0.35);
        color: var(--secondary);
        padding: 12px 24px;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background .2s ease;
    }
    .event-detail__cta-secondary:hover {
        background: var(--surface-variant);
    }

    .event-detail__visual {
        aspect-ratio: 4 / 5;
        background: linear-gradient(145deg, var(--surface-container-high) 0%, var(--surface-container-highest) 60%, var(--primary-container) 140%);
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .event-detail__visual::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,6,19,0.5), transparent 60%);
        pointer-events: none;
    }
    .event-detail__visual-glyph {
        font-size: 96px;
        color: var(--secondary);
        opacity: 0.35;
    }

    .event-detail__grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 48px;
    }
    @media (min-width: 1024px) {
        .event-detail__grid {
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 64px;
        }
        .event-detail__main { grid-column: span 8 / span 8; }
        .event-detail__aside {
            grid-column: span 4 / span 4;
            position: sticky;
            top: 96px;
            align-self: start;
        }
    }
    .event-detail__section-heading {
        font-family: 'Newsreader', serif;
        font-size: 28px;
        color: var(--primary);
        margin: 0 0 24px;
        font-weight: 500;
    }
    .event-detail__about {
        margin-bottom: 64px;
    }
    .event-detail__about-body p {
        font-size: 16px;
        line-height: 1.6;
        color: var(--on-surface);
        margin: 0 0 24px;
    }

    .event-detail__agenda {
        background: var(--surface-container-lowest);
        padding: 32px;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        margin-bottom: 64px;
    }
    .event-detail__agenda::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--secondary);
    }
    .event-detail__agenda-list {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }
    .event-detail__agenda-item {
        display: flex;
        gap: 24px;
    }
    .event-detail__agenda-time {
        width: 80px;
        flex-shrink: 0;
        font-weight: 500;
        color: var(--primary);
    }
    .event-detail__agenda-title {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 4px;
        color: var(--on-surface);
    }
    .event-detail__agenda-note {
        font-size: 14px;
        color: var(--on-surface-variant);
        margin: 0;
        line-height: 1.5;
    }

    .event-detail__aside section + section {
        margin-top: 48px;
    }
    .event-detail__aside-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--on-surface-variant);
        margin: 0 0 24px;
    }
    .event-detail__speaker {
        background: var(--surface-container-low);
        padding: 24px;
        border-radius: 12px;
    }
    .event-detail__speaker-head {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 16px;
    }
    .event-detail__speaker-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: var(--surface-container-highest);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--secondary);
    }
    .event-detail__speaker-avatar .material-symbols-outlined {
        font-size: 32px;
    }
    .event-detail__speaker-name {
        font-family: 'Newsreader', serif;
        font-size: 20px;
        color: var(--primary);
        margin: 0 0 4px;
        font-weight: 500;
    }
    .event-detail__speaker-role {
        font-size: 13px;
        color: var(--secondary);
        margin: 0;
    }
    .event-detail__speaker-bio {
        font-size: 14px;
        line-height: 1.6;
        color: var(--on-surface-variant);
        margin: 0;
    }
    .event-detail__materials-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .event-detail__material {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        border-radius: 8px;
        transition: background .2s ease;
    }
    .event-detail__material:hover {
        background: var(--surface-container-low);
    }
    .event-detail__material .material-symbols-outlined {
        color: var(--on-surface-variant);
        margin-top: 2px;
    }
    .event-detail__material-title {
        font-size: 14px;
        font-weight: 500;
        color: var(--primary);
        margin: 0 0 4px;
    }
    .event-detail__material-meta {
        font-size: 12px;
        color: var(--on-surface-variant);
        margin: 0;
    }
    .event-detail__share {
        padding-top: 24px;
        border-top: 1px solid rgba(196,198,207,0.3);
    }
    .event-detail__share-label {
        font-size: 14px;
        color: var(--on-surface-variant);
        margin: 0 0 16px;
        font-weight: 500;
    }
    .event-detail__share-buttons {
        display: flex;
        gap: 8px;
    }
    .event-detail__share-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--surface-container-high);
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--on-surface);
        transition: background .2s ease;
    }
    .event-detail__share-btn:hover {
        background: var(--surface-variant);
    }

    .event-detail__related {
        margin-top: 96px;
        padding-top: 64px;
        border-top: 1px solid rgba(196,198,207,0.3);
    }
    .event-detail__related-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 40px;
    }
    .event-detail__related-head h2 {
        margin: 0;
    }
    .event-detail__related-link {
        color: var(--secondary);
        font-weight: 500;
        text-decoration: none;
    }
    .event-detail__related-link:hover {
        text-decoration: underline;
    }
    .event-detail__related-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
    }
    @media (min-width: 768px) {
        .event-detail__related-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    .event-detail__related-card {
        display: flex;
        flex-direction: column;
        background: var(--surface-container-lowest);
        padding: 24px;
        border-radius: 12px;
        height: 100%;
        text-decoration: none;
        color: inherit;
        transition: transform .3s ease, background .3s ease;
    }
    .event-detail__related-card:hover {
        background: var(--surface-container-high);
        transform: translateY(-4px);
    }
    .event-detail__related-card--primary {
        background: var(--primary-container);
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .event-detail__related-card--primary::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(0,6,19,0.5), transparent);
        pointer-events: none;
    }
    .event-detail__related-category {
        font-size: 12px;
        font-weight: 600;
        color: var(--secondary);
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin: 0 0 16px;
    }
    .event-detail__related-card--primary .event-detail__related-category {
        color: var(--primary-fixed-dim);
        position: relative;
        z-index: 1;
    }
    .event-detail__related-title {
        font-family: 'Newsreader', serif;
        font-size: 20px;
        color: var(--primary);
        margin: 0 0 12px;
        font-weight: 500;
        line-height: 1.3;
    }
    .event-detail__related-card--primary .event-detail__related-title {
        color: #ffffff;
        position: relative;
        z-index: 1;
    }
    .event-detail__related-card:hover .event-detail__related-title {
        color: var(--secondary);
    }
    .event-detail__related-card--primary:hover .event-detail__related-title {
        color: #ffffff;
    }
    .event-detail__related-excerpt {
        font-size: 14px;
        color: var(--on-surface-variant);
        line-height: 1.5;
        margin: 0 0 24px;
        flex-grow: 1;
    }
    .event-detail__related-card--primary .event-detail__related-excerpt {
        color: #d1d5db;
        position: relative;
        z-index: 1;
    }
    .event-detail__related-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--on-surface-variant);
        padding-top: 16px;
        margin-top: auto;
        border-top: 1px solid rgba(196,198,207,0.2);
    }
    .event-detail__related-card--primary .event-detail__related-meta {
        color: var(--primary-fixed-dim);
        border-top-color: rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }
    .event-detail__related-meta .material-symbols-outlined {
        font-size: 16px;
    }
</style>
@endsection

@section('content')
<article class="event-detail"
         data-page="events-detail"
         data-event-slug="{{ $event['slug'] }}"
         data-event-category="{{ $event['category_slug'] }}">
    <div class="event-detail__container">

        <nav aria-label="Breadcrumb"
             class="event-detail__breadcrumb"
             data-section="event-detail-breadcrumb">
            <a href="{{ $routeWithLang('/') }}">{{ $copy['breadcrumb_discovery'] }}</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <a href="{{ $routeWithLang('/events') }}">{{ $copy['breadcrumb_events'] }}</a>
            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            <span class="event-detail__breadcrumb-current">{{ $baseI18n['title'] }}</span>
        </nav>

        <a href="{{ $routeWithLang('/events') }}"
           class="event-detail__back"
           data-test-id="event-detail-back">
            <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            {{ $copy['back_to_events'] }}
        </a>

        <header class="event-detail__hero" data-section="event-detail-hero">
            <div class="event-detail__hero-body">
                <div class="event-detail__categories">
                    <span class="event-detail__category">{{ $baseI18n['category'] }}</span>
                    <span class="event-detail__category event-detail__category--secondary">{{ $detailI18n['secondary_category'] }}</span>
                </div>
                <h1 class="event-detail__title">{{ $baseI18n['title'] }}</h1>
                <p class="event-detail__lead">{{ $detailI18n['subtitle'] }}</p>

                <div class="event-detail__meta-grid">
                    <div class="event-detail__meta-item" data-meta="datetime">
                        <span class="material-symbols-outlined" aria-hidden="true">calendar_today</span>
                        <div>
                            <p class="event-detail__meta-label">{{ $copy['meta_datetime'] }}</p>
                            <p class="event-detail__meta-value">
                                <time datetime="{{ $event['iso_date'] }}">{{ $baseI18n['date_month_day'] }}, {{ $event['iso_date'] !== '' ? substr($event['iso_date'], 0, 4) : '' }}</time>
                            </p>
                            <p class="event-detail__meta-value">{{ $detailI18n['date_time_range'] }}</p>
                        </div>
                    </div>
                    <div class="event-detail__meta-item" data-meta="venue">
                        <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
                        <div>
                            <p class="event-detail__meta-label">{{ $copy['meta_venue'] }}</p>
                            <p class="event-detail__meta-value">{{ $baseI18n['venue'] }}</p>
                        </div>
                    </div>
                    <div class="event-detail__meta-item" data-meta="capacity">
                        <span class="material-symbols-outlined" aria-hidden="true">group</span>
                        <div>
                            <p class="event-detail__meta-label">{{ $copy['meta_capacity'] }}</p>
                            <p class="event-detail__meta-value">{{ $detailI18n['capacity_label'] }}</p>
                            <p class="event-detail__meta-value">{{ $detailI18n['capacity_note'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="event-detail__cta-row">
                    <button type="button"
                            class="event-detail__cta-primary"
                            data-test-id="event-detail-register">
                        <span class="material-symbols-outlined" aria-hidden="true">how_to_reg</span>
                        {{ $copy['cta_register'] }}
                    </button>
                    <button type="button"
                            class="event-detail__cta-secondary"
                            data-test-id="event-detail-save">
                        <span class="material-symbols-outlined" aria-hidden="true">bookmark_add</span>
                        {{ $copy['cta_save'] }}
                    </button>
                </div>
            </div>

            <div class="event-detail__hero-visual">
                <div class="event-detail__visual" aria-hidden="true">
                    <span class="material-symbols-outlined event-detail__visual-glyph">auto_stories</span>
                </div>
            </div>
        </header>

        <div class="event-detail__grid">
            <div class="event-detail__main">
                <section class="event-detail__about" data-section="event-detail-about">
                    <h2 class="event-detail__section-heading">{{ $copy['section_about'] }}</h2>
                    <div class="event-detail__about-body">
                        @foreach ($detailI18n['about'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </section>

                <section class="event-detail__agenda" data-section="event-detail-agenda">
                    <h2 class="event-detail__section-heading">{{ $copy['section_agenda'] }}</h2>
                    <ol class="event-detail__agenda-list">
                        @foreach ($detailI18n['agenda'] as $row)
                            <li class="event-detail__agenda-item" data-agenda-slot>
                                <div class="event-detail__agenda-time">{{ $row['time'] }}</div>
                                <div>
                                    <h3 class="event-detail__agenda-title">{{ $row['title'] }}</h3>
                                    <p class="event-detail__agenda-note">{{ $row['note'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="event-detail__aside">
                <section data-section="event-detail-speaker">
                    <h3 class="event-detail__aside-label">
                        <span class="material-symbols-outlined" aria-hidden="true">mic</span>
                        {{ $copy['section_speaker'] }}
                    </h3>
                    <div class="event-detail__speaker">
                        <div class="event-detail__speaker-head">
                            <div class="event-detail__speaker-avatar" aria-hidden="true">
                                <span class="material-symbols-outlined">person</span>
                            </div>
                            <div>
                                <h4 class="event-detail__speaker-name">{{ $detailI18n['speaker']['name'] }}</h4>
                                <p class="event-detail__speaker-role">{{ $detailI18n['speaker']['role'] }}</p>
                            </div>
                        </div>
                        <p class="event-detail__speaker-bio">{{ $detailI18n['speaker']['bio'] }}</p>
                    </div>
                </section>

                <section data-section="event-detail-materials">
                    <h3 class="event-detail__aside-label">
                        <span class="material-symbols-outlined" aria-hidden="true">menu_book</span>
                        {{ $copy['section_materials'] }}
                    </h3>
                    <ul class="event-detail__materials-list">
                        @foreach ($detailI18n['materials'] as $material)
                            <li class="event-detail__material" data-material-slot>
                                <span class="material-symbols-outlined" aria-hidden="true">article</span>
                                <div>
                                    <p class="event-detail__material-title">{{ $material['title'] }}</p>
                                    <p class="event-detail__material-meta">{{ $material['meta'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="event-detail__share" data-section="event-detail-share">
                    <p class="event-detail__share-label">{{ $copy['section_share'] }}</p>
                    <div class="event-detail__share-buttons">
                        <button type="button" class="event-detail__share-btn" aria-label="Email">
                            <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                        </button>
                        <button type="button" class="event-detail__share-btn" aria-label="Copy link">
                            <span class="material-symbols-outlined" aria-hidden="true">link</span>
                        </button>
                    </div>
                </section>
            </aside>
        </div>

        @if (! empty($relatedEvents))
            <section class="event-detail__related" data-section="event-detail-related">
                <div class="event-detail__related-head">
                    <h2 class="event-detail__section-heading">{{ $copy['section_related'] }}</h2>
                    <a href="{{ $routeWithLang('/events') }}" class="event-detail__related-link">{{ $copy['view_all_events'] }}</a>
                </div>
                <div class="event-detail__related-grid">
                    @foreach ($relatedEvents as $idx => $related)
                        @php($relatedI18n = $related['i18n'][$lang])
                        <a href="{{ $routeWithLang('/events/' . $related['slug']) }}"
                           class="event-detail__related-card {{ $idx === 2 ? 'event-detail__related-card--primary' : '' }}"
                           data-related-slot
                           data-related-slug="{{ $related['slug'] }}">
                            <p class="event-detail__related-category">{{ $relatedI18n['category'] }}</p>
                            <h3 class="event-detail__related-title">{{ $relatedI18n['title'] }}</h3>
                            <p class="event-detail__related-excerpt">{{ $relatedI18n['description'] }}</p>
                            <div class="event-detail__related-meta">
                                <span class="material-symbols-outlined" aria-hidden="true">{{ $idx === 2 ? 'location_on' : 'calendar_today' }}</span>
                                <span>{{ $idx === 2 ? $relatedI18n['venue'] : ($relatedI18n['date_month_day'] . ', ' . substr($related['iso_date'], 0, 4)) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</article>
@endsection
