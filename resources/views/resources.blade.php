@extends('layouts.public')

@php
    $activePage = $activePage ?? 'resources';
    $resources = collect($resources ?? []);
    $resourceTypes = \App\Models\ExternalResource::TYPES;
    $audienceOptions = \App\Models\ExternalResource::AUDIENCES;
    $typeCounts = collect($resourceTypes)->mapWithKeys(
        fn (string $type): array => [$type => $resources->where('resource_type', $type)->count()]
    );
    $locale = app()->getLocale();
    $locale = in_array($locale, ['kk', 'ru', 'en'], true) ? $locale : 'kk';
    $emptyCopy = [
        'ru' => [
            'title' => 'Ресурсы пока не опубликованы.',
            'body' => 'Проверенные электронные ресурсы появятся здесь после публикации библиотекой.',
        ],
        'kk' => [
            'title' => 'Ресурстар әзірге жарияланбаған.',
            'body' => 'Тексерілген электрондық ресурстар кітапхана жариялағаннан кейін осы жерде пайда болады.',
        ],
        'en' => [
            'title' => 'No resources are published yet.',
            'body' => 'Verified electronic resources will appear here after the library publishes them.',
        ],
    ][$locale];
    $withLang = static function (string $path): string {
        $locale = app()->getLocale();
        return $locale === 'kk' ? $path : $path.'?'.http_build_query(['lang' => $locale]);
    };
@endphp

@section('title', __('external_resources.page.title'))
@section('meta_description', __('external_resources.page.lead'))

@section('content')
<div class="external-resources public-page" data-external-resources>
    <header class="external-resources__hero public-page__intro public-page__intro--functional" data-section="resources-canonical-hero">
        <div class="external-resources__hero-copy">
            <p class="external-resources__eyebrow">{{ __('external_resources.page.eyebrow') }}</p>
            <h1>{{ __('external_resources.page.heading') }}</h1>
            <p>{{ __('external_resources.page.lead') }}</p>
        </div>
        <div class="external-resources__hero-stat" aria-label="{{ __('external_resources.page.all') }}">
            <strong>{{ $resources->count() }}</strong>
            <span>{{ __('external_resources.page.all') }}</span>
        </div>
    </header>

    <section class="external-resources__directory" data-section="resources-canonical-main">
        @if ($resources->isEmpty())
            <div class="external-resources__empty external-resources__empty--initial" data-test-id="resources-canonical-empty">
                <span class="material-symbols-outlined" aria-hidden="true">library_books</span>
                <h2>{{ $emptyCopy['title'] }}</h2>
                <p>{{ $emptyCopy['body'] }}</p>
            </div>
        @else
        <div class="external-resources__toolbar">
            <label class="external-resources__search">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <input type="search" data-resource-search placeholder="{{ __('external_resources.page.search') }}" autocomplete="off">
            </label>

            <nav class="external-resources__filters" aria-label="{{ __('external_resources.page.all') }}">
                <button class="is-active" type="button" data-resource-filter="all" aria-pressed="true">
                    {{ __('external_resources.page.all') }} <span>{{ $resources->count() }}</span>
                </button>
                @foreach ($resourceTypes as $type)
                    @continue(($typeCounts[$type] ?? 0) === 0)
                    <button type="button" data-resource-filter="{{ $type }}" aria-pressed="false">
                        {{ __('external_resources.types.'.$type) }} <span>{{ $typeCounts[$type] }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="external-resources__facets" data-resource-facets>
                <label>
                    <span>{{ __('external_resources.filters.audience') }}</span>
                    <select data-resource-facet="audience">
                        <option value="all">{{ __('external_resources.filters.all') }}</option>
                        @foreach ($audienceOptions as $audience)
                            <option value="{{ $audience }}">{{ __('external_resources.audiences.'.$audience) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('external_resources.filters.access_scope') }}</span>
                    <select data-resource-facet="accessScope">
                        <option value="all">{{ __('external_resources.filters.all') }}</option>
                        @foreach (['guest', 'authenticated', 'campus', 'remote'] as $scope)
                            <option value="{{ $scope }}">{{ __('external_resources.filters.access_scopes.'.$scope) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('external_resources.filters.content') }}</span>
                    <select data-resource-facet="content">
                        <option value="all">{{ __('external_resources.filters.all') }}</option>
                        @foreach ($resources->pluck('content_types')->flatten()->filter()->unique()->sort()->values() as $contentType)
                            <option value="{{ $contentType }}">{{ __('external_resources.content_types.'.$contentType) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>{{ __('external_resources.filters.status') }}</span>
                    <select data-resource-facet="status">
                        <option value="all">{{ __('external_resources.filters.all') }}</option>
                        @foreach (['active', 'expiring_soon', 'expired'] as $filterStatus)
                            <option value="{{ $filterStatus }}">{{ __('external_resources.statuses.'.$filterStatus) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <p class="external-resources__result-count" data-resource-count>
                {{ __('external_resources.page.results', ['count' => $resources->count()]) }}
            </p>
        </div>

        <div class="external-resources__layout">
            <div class="external-resources__groups">
                @php
                    $visibleTypeIndex = 0;
                @endphp
                @foreach ($resourceTypes as $type)
                    @php
                        $typeResources = $resources->where('resource_type', $type)->values();
                    @endphp
                    @continue($typeResources->isEmpty())
                    @php
                        $visibleTypeIndex++;
                    @endphp
                    <section
                        class="external-resources__group"
                        data-resource-group="{{ $type }}"
                        @if($type === 'licensed') data-section="resources-canonical-premium" @endif
                        @if($type === 'open_access') data-section="resources-canonical-open-access" @endif
                    >
                        <header class="external-resources__group-head">
                            <div>
                                <p class="external-resources__group-index">{{ str_pad((string) $visibleTypeIndex, 2, '0', STR_PAD_LEFT) }}</p>
                                <h2>{{ __('external_resources.types.'.$type) }}</h2>
                                <p>{{ __('external_resources.type_descriptions.'.$type) }}</p>
                            </div>
                            <span>{{ $typeResources->count() }}</span>
                        </header>

                        <div class="external-resources__cards">
                            @foreach ($typeResources as $resource)
                                @php
                                    $status = $resource['status'] ?? 'inactive';
                                    $expiry = $resource['expiry_date'] ?? null;
                                    $audiences = (array) ($resource['available_roles'] ?? []);
                                    $contentTypes = (array) ($resource['content_types'] ?? []);
                                    $instructions = trim((string) ($resource['access_instructions'] ?? $resource['notes'] ?? ''));
                                    $isUnavailable = in_array($status, ['expired', 'inactive'], true)
                                        || ($resource['health_status'] ?? 'unchecked') === 'unavailable';
                                    $canOpen = (bool) ($resource['can_open'] ?? false);
                                    $accessDenial = (string) ($resource['access_denial'] ?? ($isUnavailable ? 'unavailable' : 'restricted'));
                                    $detailHref = $withLang($resource['detail_url']);
                                    $loginQuery = ['redirect' => $detailHref];
                                    if ($locale !== 'kk') {
                                        $loginQuery['lang'] = $locale;
                                    }
                                    $loginHref = '/login?'.http_build_query($loginQuery);
                                    $opensNewTab = ($resource['resource_type'] ?? null) !== 'internal';
                                @endphp
                                <article
                                    class="external-resource-card"
                                    data-resource-card
                                    data-resource-type="{{ $type }}"
                                    data-resource-status="{{ $status }}"
                                    data-resource-audiences="{{ implode('|', $audiences) }}"
                                    data-resource-content="{{ implode('|', $contentTypes) }}"
                                    data-resource-guest="{{ ! empty($resource['guest_access']) ? '1' : '0' }}"
                                    data-resource-authenticated="{{ ! empty($resource['login_required']) ? '1' : '0' }}"
                                    data-resource-campus="{{ (! empty($resource['campus_only']) || ($resource['access_type'] ?? null) === 'campus') ? '1' : '0' }}"
                                    data-resource-slug="{{ $resource['slug'] }}"
                                    data-test-id="resources-card-{{ $resource['slug'] }}"
                                >
                                    <div class="external-resource-card__top">
                                        <div class="external-resource-card__logo">
                                            @if (! empty($resource['logo']))
                                                <img src="{{ $resource['logo'] }}" alt="" loading="lazy" decoding="async">
                                            @else
                                                <span aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $resource['title'], 0, 1)) }}</span>
                                            @endif
                                        </div>
                                        <div class="external-resource-card__badges">
                                            <span class="external-resource-card__status external-resource-card__status--{{ $status }}">
                                                <span aria-hidden="true"></span>{{ __('external_resources.statuses.'.$status) }}
                                            </span>
                                            <span class="external-resource-card__access">
                                                <span class="material-symbols-outlined" aria-hidden="true">{{ ($resource['access_type'] ?? 'open') === 'open' ? 'public' : (($resource['access_type'] ?? '') === 'campus' ? 'location_on' : 'key') }}</span>
                                                {{ __('external_resources.access_types.'.($resource['access_type'] ?? 'open').'.label') }}
                                            </span>
                                            @if (($resource['health_status'] ?? 'unchecked') === 'unavailable')
                                                <span class="external-resource-card__status external-resource-card__status--inactive">
                                                    <span aria-hidden="true"></span>{{ __('external_resources.health.unavailable') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="external-resource-card__heading">
                                        <h3><a href="{{ $detailHref }}">{{ $resource['title'] }}</a></h3>
                                        @if (! empty($resource['provider']))
                                            <p>{{ __('external_resources.labels.provider') }}: {{ $resource['provider'] }}</p>
                                        @endif
                                    </div>
                                    <p class="external-resource-card__description">{{ $resource['description'] }}</p>

                                    <div class="external-resource-card__fact">
                                        <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                                        <div>
                                            <strong>{{ __('external_resources.expiry.label') }}</strong>
                                            <span>
                                                @if ($type === 'open_access' || $type === 'internal')
                                                    {{ __('external_resources.expiry.unlimited') }}
                                                @elseif ($expiry)
                                                    {{ __('external_resources.expiry.until', ['date' => date('d.m.Y', strtotime($expiry))]) }}
                                                @else
                                                    {{ __('external_resources.expiry.not_specified') }}
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    @if ($contentTypes !== [])
                                        <div class="external-resource-card__section">
                                            <strong>{{ __('external_resources.labels.content') }}</strong>
                                            <div class="external-resource-card__chips">
                                                @foreach ($contentTypes as $contentType)
                                                    <span>{{ __('external_resources.content_types.'.$contentType) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($audiences !== [])
                                        <div class="external-resource-card__section">
                                            <strong>{{ __('external_resources.labels.audiences') }}</strong>
                                            <div class="external-resource-card__audiences">
                                                @foreach ($audiences as $audience)
                                                    <span>
                                                        <span class="material-symbols-outlined" aria-hidden="true">{{ $audience === 'guest' ? 'person' : ($audience === 'student' ? 'school' : ($audience === 'teacher' ? 'co_present' : 'local_library')) }}</span>
                                                        {{ __('external_resources.audiences.'.$audience) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($instructions !== '')
                                        <details class="external-resource-card__instructions">
                                            <summary>
                                                <span class="material-symbols-outlined" aria-hidden="true">help</span>
                                                {{ __('external_resources.labels.instructions') }}
                                                <span class="material-symbols-outlined external-resource-card__chevron" aria-hidden="true">expand_more</span>
                                            </summary>
                                            <p>{{ $instructions }}</p>
                                        </details>
                                    @endif

                                    <div class="external-resource-card__footer">
                                        <a class="external-resource-card__details" href="{{ $detailHref }}">
                                            {{ __('external_resources.page.details') }}
                                        </a>
                                        @if ($canOpen)
                                            <a
                                                class="external-resource-card__button"
                                                href="{{ $resource['url'] }}"
                                                @if($opensNewTab) target="_blank" rel="noopener noreferrer" @endif
                                                data-test-id="resources-link-{{ $resource['slug'] }}"
                                            >
                                                {{ __('external_resources.page.open') }}
                                                <span class="material-symbols-outlined" aria-hidden="true">{{ $opensNewTab ? 'open_in_new' : 'arrow_forward' }}</span>
                                            </a>
                                        @elseif ($accessDenial === 'sign_in')
                                            <a class="external-resource-card__button" href="{{ $loginHref }}" data-test-id="resources-sign-in-{{ $resource['slug'] }}">
                                                {{ __('external_resources.page.sign_in') }}
                                                <span class="material-symbols-outlined" aria-hidden="true">login</span>
                                            </a>
                                        @else
                                            <span class="external-resource-card__button external-resource-card__button--disabled" aria-disabled="true">
                                                {{ __('external_resources.page.'.($accessDenial === 'campus' ? 'campus_required' : ($accessDenial === 'restricted' ? 'restricted' : 'unavailable'))) }}
                                            </span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="external-resources__empty" data-resource-empty hidden>
                    <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
                    <p>{{ __('external_resources.page.empty') }}</p>
                </div>
            </div>

            <aside class="external-resources__help" data-section="resources-canonical-sidebar">
                <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
                <h2>{{ __('external_resources.page.help_title') }}</h2>
                <p>{{ __('external_resources.page.help_text') }}</p>
                <a href="{{ $withLang('/contacts') }}" data-test-id="resources-canonical-off-campus-cta">
                    {{ __('external_resources.page.help_link') }}
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
            </aside>
        </div>
        @endif
    </section>
</div>
@endsection

@section('head')
<style>
    .external-resource-card__title,
    .external-resource-card__description,
    .external-resource-card__provider { overflow-wrap:anywhere; }

    .external-resources { color:#122229; background:#f5f7f6; min-height:70vh; }
    .external-resources__hero { padding:clamp(3.5rem,8vw,7rem) max(1.25rem,calc((100vw - 1320px)/2)); background:linear-gradient(120deg,#062f34 0%,#0b4a4d 58%,#153d49 100%); color:#fff; display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:3rem; }
    .external-resources__hero-copy { max-width:900px; }
    .external-resources__eyebrow { margin:0 0 1rem; color:#8ee1d8; text-transform:uppercase; letter-spacing:.18em; font-size:.75rem; font-weight:800; }
    .external-resources__hero h1 { margin:0; max-width:880px; font:500 clamp(2.65rem,5.5vw,5rem)/.98 'Newsreader',serif; letter-spacing:-.035em; }
    .external-resources__hero-copy>p:last-child { max-width:760px; margin:1.5rem 0 0; color:#d5e5e3; font-size:1.05rem; line-height:1.75; }
    .external-resources__hero-stat { min-width:155px; padding:1.5rem; border:1px solid rgba(255,255,255,.22); background:rgba(255,255,255,.08); backdrop-filter:blur(8px); }
    .external-resources__hero-stat strong { display:block; font:500 3.2rem/1 'Newsreader',serif; }
    .external-resources__hero-stat span { display:block; margin-top:.45rem; color:#d5e5e3; font-size:.78rem; }
    .external-resources__directory { padding:clamp(2rem,5vw,5rem) max(1.25rem,calc((100vw - 1320px)/2)) 6rem; }
    .external-resources__toolbar { display:grid; gap:1.25rem; margin-bottom:3rem; }
    .external-resources__search { height:58px; display:flex; align-items:center; gap:.8rem; padding:0 1.15rem; background:#fff; border:1px solid #d8e0de; box-shadow:0 10px 30px rgba(20,47,51,.05); }
    .external-resources__search span { color:#34736f; }
    .external-resources__search input { flex:1; min-width:0; border:0; outline:0; background:transparent; font:inherit; color:#122229; }
    .external-resources__filters { display:flex; flex-wrap:wrap; gap:.65rem; }
    .external-resources__filters button { border:1px solid #cad5d2; background:transparent; color:#38504f; padding:.7rem 1rem; cursor:pointer; font:700 .76rem/1.2 'Manrope',sans-serif; transition:.2s ease; }
    .external-resources__filters button span { margin-left:.35rem; opacity:.65; }
    .external-resources__filters button:hover,.external-resources__filters button.is-active { background:#075c5c; border-color:#075c5c; color:#fff; }
    .external-resources__facets { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem; }
    .external-resources__facets label>span { display:block;margin-bottom:.35rem;color:#526562;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em; }
    .external-resources__facets select { width:100%;min-height:43px;padding:.6rem .75rem;border:1px solid #cad5d2;background:#fff;color:#2f4745;font:700 .74rem 'Manrope',sans-serif; }
    .external-resources__result-count { margin:0; color:#667876; font-size:.82rem; }
    .external-resources__layout { display:grid; grid-template-columns:minmax(0,1fr) 290px; gap:2.5rem; align-items:start; }
    .external-resources__groups { min-width:0; }
    .external-resources__group { margin-bottom:4.5rem; scroll-margin-top:110px; }
    .external-resources__group[hidden] { display:none; }
    .external-resources__group-head { display:flex; justify-content:space-between; gap:1.5rem; align-items:start; padding-bottom:1.25rem; margin-bottom:1.35rem; border-bottom:1px solid #cad5d2; }
    .external-resources__group-index { margin:0 0 .35rem; color:#0a7773; font:800 .68rem/1 'Manrope',sans-serif; letter-spacing:.16em; }
    .external-resources__group-head h2 { margin:0; font:500 clamp(1.8rem,3vw,2.5rem)/1.05 'Newsreader',serif; }
    .external-resources__group-head p:not(.external-resources__group-index) { max-width:700px; margin:.6rem 0 0; color:#60716f; line-height:1.6; font-size:.88rem; }
    .external-resources__group-head>span { min-width:38px; height:38px; border-radius:50%; display:grid; place-items:center; background:#dcebe8; color:#075c5c; font-weight:800; font-size:.78rem; }
    .external-resources__cards { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .external-resource-card { min-width:0; display:flex; flex-direction:column; padding:1.4rem; background:#fff; border:1px solid #dde4e2; box-shadow:0 12px 34px rgba(30,58,59,.05); transition:transform .2s ease,box-shadow .2s ease; }
    .external-resource-card:hover { transform:translateY(-3px); box-shadow:0 18px 42px rgba(30,58,59,.1); }
    .external-resource-card[hidden] { display:none; }
    .external-resource-card__top { display:flex; justify-content:space-between; gap:1rem; align-items:start; }
    .external-resource-card__logo { width:62px; height:62px; flex:0 0 62px; display:grid; place-items:center; overflow:hidden; border-radius:12px; background:#eef3f1; color:#075c5c; font:700 1.6rem 'Newsreader',serif; }
    .external-resource-card__logo img { width:100%; height:100%; object-fit:contain; padding:.45rem; }
    .external-resource-card__badges { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:.4rem; }
    .external-resource-card__status,.external-resource-card__access { display:inline-flex; align-items:center; gap:.35rem; padding:.4rem .55rem; font-size:.66rem; font-weight:800; line-height:1.1; background:#edf2f1; color:#445a58; }
    .external-resource-card__status>span { width:7px; height:7px; border-radius:50%; background:#70817f; }
    .external-resource-card__status--active>span { background:#15945c; }
    .external-resource-card__status--expiring_soon>span { background:#d88916; }
    .external-resource-card__status--expired>span,.external-resource-card__status--inactive>span { background:#c44141; }
    .external-resource-card__access .material-symbols-outlined { font-size:.9rem; }
    .external-resource-card__heading { margin-top:1.25rem; }
    .external-resource-card__heading h3 { margin:0; font:600 1.5rem/1.12 'Newsreader',serif; }
    .external-resource-card__heading h3 a { color:inherit;text-decoration:none; }
    .external-resource-card__heading h3 a:hover { color:#08706d;text-decoration:underline; }
    .external-resource-card__heading p { margin:.45rem 0 0; color:#71817f; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
    .external-resource-card__description { margin:1rem 0 1.15rem; color:#526562; font-size:.84rem; line-height:1.68; }
    .external-resource-card__fact { display:flex; gap:.75rem; padding:.8rem; background:#f4f7f6; }
    .external-resource-card__fact>.material-symbols-outlined { color:#0a7773; font-size:1.2rem; }
    .external-resource-card__fact strong,.external-resource-card__fact span { display:block; }
    .external-resource-card__fact strong { margin-bottom:.2rem; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; }
    .external-resource-card__fact span { color:#617371; font-size:.75rem; line-height:1.4; }
    .external-resource-card__section { margin-top:1rem; }
    .external-resource-card__section>strong { display:block; margin-bottom:.55rem; color:#344a48; font-size:.7rem; text-transform:uppercase; letter-spacing:.07em; }
    .external-resource-card__chips,.external-resource-card__audiences { display:flex; flex-wrap:wrap; gap:.4rem; }
    .external-resource-card__chips>span { padding:.35rem .55rem; border:1px solid #d7e0de; color:#536764; font-size:.68rem; }
    .external-resource-card__audiences>span { display:inline-flex; align-items:center; gap:.3rem; color:#536764; font-size:.7rem; }
    .external-resource-card__audiences .material-symbols-outlined { font-size:1rem; color:#0a7773; }
    .external-resource-card__instructions { margin-top:1rem; border-top:1px solid #e3e9e7; border-bottom:1px solid #e3e9e7; }
    .external-resource-card__instructions summary { display:flex; align-items:center; gap:.45rem; padding:.85rem 0; cursor:pointer; color:#075c5c; font-size:.78rem; font-weight:800; list-style:none; }
    .external-resource-card__instructions summary::-webkit-details-marker { display:none; }
    .external-resource-card__instructions summary .material-symbols-outlined { font-size:1.1rem; }
    .external-resource-card__chevron { margin-left:auto; transition:transform .2s; }
    .external-resource-card__instructions[open] .external-resource-card__chevron { transform:rotate(180deg); }
    .external-resource-card__instructions p { margin:0; padding:0 0 1rem; color:#526562; font-size:.78rem; line-height:1.65; white-space:pre-line; }
    .external-resource-card__footer { margin-top:auto; padding-top:1.2rem;display:grid;gap:.55rem; }
    .external-resource-card__details { display:flex;min-height:40px;align-items:center;justify-content:center;border:1px solid #afc4c0;color:#075c5c;text-decoration:none;font-size:.76rem;font-weight:800; }
    .external-resource-card__button { min-height:44px; display:flex; justify-content:center; align-items:center; gap:.5rem; padding:.75rem 1rem; background:#075c5c; color:#fff; text-decoration:none; font-size:.78rem; font-weight:800; }
    .external-resource-card__button:hover { background:#043f42; }
    .external-resource-card__button .material-symbols-outlined { font-size:1rem; }
    .external-resource-card__button--disabled { background:#e4e8e7; color:#7b8987; }
    .external-resources__help { position:sticky; top:120px; padding:1.5rem; background:#dcebe8; border-top:4px solid #0a7773; }
    .external-resources__help>.material-symbols-outlined { width:46px; height:46px; display:grid; place-items:center; border-radius:50%; background:#fff; color:#0a7773; }
    .external-resources__help h2 { margin:1.1rem 0 .6rem; font:600 1.55rem/1.1 'Newsreader',serif; }
    .external-resources__help p { margin:0; color:#506563; font-size:.8rem; line-height:1.65; }
    .external-resources__help a { display:inline-flex; align-items:center; gap:.35rem; margin-top:1.1rem; color:#075c5c; font-size:.76rem; font-weight:800; }
    .external-resources__help a .material-symbols-outlined { font-size:1rem; }
    .external-resources__empty { padding:4rem 1rem; text-align:center; color:#657774; }
    .external-resources__empty .material-symbols-outlined { font-size:2.5rem; }
    .external-resources__empty h2 { margin:.75rem 0 .5rem; color:#122229; font:600 1.55rem/1.2 'Newsreader',serif; }
    .external-resources__empty p { margin:0; line-height:1.6; }
    .external-resources__empty--initial { max-width:760px; margin:0 auto; padding:3rem 1.25rem; border:1px solid #d8e0de; background:#fff; }
    @media (max-width:1050px) { .external-resources__layout { grid-template-columns:1fr; } .external-resources__help { position:static; } }
    @media (max-width:900px) { .external-resources__facets { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:760px) { .external-resources__hero { grid-template-columns:1fr; } .external-resources__hero-stat { width:max-content; } .external-resources__cards { grid-template-columns:1fr; } }
    @media (max-width:520px) { .external-resources__facets { grid-template-columns:1fr; } }
    @media (max-width:520px) { .external-resources__hero,.external-resources__directory { padding-left:1rem; padding-right:1rem; } .external-resource-card__top { display:block; } .external-resource-card__badges { justify-content:flex-start; margin-top:.8rem; } }
</style>
@endsection

@section('scripts')
<script>
(() => {
    const root = document.querySelector('[data-external-resources]');
    if (!root) return;

    const input = root.querySelector('[data-resource-search]');
    const buttons = [...root.querySelectorAll('[data-resource-filter]')];
    const cards = [...root.querySelectorAll('[data-resource-card]')];
    const groups = [...root.querySelectorAll('[data-resource-group]')];
    const count = root.querySelector('[data-resource-count]');
    const empty = root.querySelector('[data-resource-empty]');
    const facets = [...root.querySelectorAll('[data-resource-facet]')];
    let activeType = 'all';

    const apply = () => {
        const query = (input?.value || '').trim().toLocaleLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const typeMatch = activeType === 'all' || card.dataset.resourceType === activeType;
            const searchMatch = query === '' || card.textContent.toLocaleLowerCase().includes(query);
            const values = Object.fromEntries(facets.map((facet) => [facet.dataset.resourceFacet, facet.value]));
            const audienceMatch = !values.audience || values.audience === 'all' || (card.dataset.resourceAudiences || '').split('|').includes(values.audience);
            const contentMatch = !values.content || values.content === 'all' || (card.dataset.resourceContent || '').split('|').includes(values.content);
            const statusMatch = !values.status || values.status === 'all' || card.dataset.resourceStatus === values.status;
            const accessMatch = !values.accessScope || values.accessScope === 'all'
                || (values.accessScope === 'guest' && card.dataset.resourceGuest === '1')
                || (values.accessScope === 'authenticated' && card.dataset.resourceAuthenticated === '1')
                || (values.accessScope === 'campus' && card.dataset.resourceCampus === '1')
                || (values.accessScope === 'remote' && card.dataset.resourceCampus !== '1');
            card.hidden = !(typeMatch && searchMatch && audienceMatch && contentMatch && statusMatch && accessMatch);
            if (!card.hidden) visible += 1;
        });

        groups.forEach((group) => {
            group.hidden = ![...group.querySelectorAll('[data-resource-card]')].some((card) => !card.hidden);
        });
        if (count) count.textContent = @json(__('external_resources.page.results', ['count' => '__COUNT__'])).replace('__COUNT__', String(visible));
        if (empty) empty.hidden = visible !== 0;
    };

    input?.addEventListener('input', apply);
    facets.forEach((facet) => facet.addEventListener('change', apply));
    buttons.forEach((button) => button.addEventListener('click', () => {
        activeType = button.dataset.resourceFilter || 'all';
        buttons.forEach((candidate) => {
            const active = candidate === button;
            candidate.classList.toggle('is-active', active);
            candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        apply();
    }));
})();
</script>
@endsection
