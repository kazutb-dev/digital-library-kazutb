@extends('layouts.public')

@php
    $status = $resource['status'] ?? 'inactive';
    $expiry = $resource['expiry_date'] ?? null;
    $audiences = (array) ($resource['available_roles'] ?? []);
    $contentTypes = (array) ($resource['content_types'] ?? []);
    $instructions = trim((string) ($resource['access_instructions'] ?? ''));
    $healthStatus = $resource['health_status'] ?? 'unchecked';
    $opensNewTab = ($resource['resource_type'] ?? null) !== 'internal';
    $canOpen = (bool) ($resource['can_open'] ?? false);
    $accessDenial = (string) ($resource['access_denial'] ?? 'restricted');
    $localeQuery = app()->getLocale() === 'kk' ? '' : '?'.http_build_query(['lang' => app()->getLocale()]);
    $detailPath = route('resources.show', $resource['slug'], false).$localeQuery;
    $loginQuery = ['redirect' => $detailPath];
    if (app()->getLocale() !== 'kk') {
        $loginQuery['lang'] = app()->getLocale();
    }
    $loginHref = '/login?'.http_build_query($loginQuery);
@endphp

@section('title')
{{ $resource['title'].' — '.__('external_resources.page.title') }}
@endsection
@section('meta_description', $resource['description'])

@section('content')
<div class="resource-detail" data-resource-detail="{{ $resource['slug'] }}">
    <div class="resource-detail__breadcrumb">
        <a href="/resources{{ $localeQuery }}">{{ __('external_resources.detail.back') }}</a>
        <span aria-hidden="true">/</span>
        <span>{{ $resource['title'] }}</span>
    </div>

    <article class="resource-detail__shell">
        <header class="resource-detail__hero">
            <div class="resource-detail__logo">
                @if (! empty($resource['logo']))
                    <img src="{{ $resource['logo'] }}" alt="" decoding="async">
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $resource['title'], 0, 1)) }}</span>
                @endif
            </div>
            <div class="resource-detail__heading">
                <div class="resource-detail__badges">
                    <span class="resource-detail__badge resource-detail__badge--{{ $status }}">{{ __('external_resources.statuses.'.$status) }}</span>
                    <span class="resource-detail__badge">{{ __('external_resources.types.'.$resource['resource_type']) }}</span>
                    <span class="resource-detail__badge">{{ __('external_resources.access_types.'.($resource['access_type'] ?? 'open').'.label') }}</span>
                    @if($healthStatus === 'unavailable')
                        <span class="resource-detail__badge resource-detail__badge--inactive">{{ __('external_resources.health.unavailable') }}</span>
                    @endif
                </div>
                <h1>{{ $resource['title'] }}</h1>
                @if (! empty($resource['provider']))
                    <p class="resource-detail__provider">{{ __('external_resources.labels.provider') }}: {{ $resource['provider'] }}</p>
                @endif
                <p class="resource-detail__lead">{{ $resource['description'] }}</p>
            </div>
        </header>

        <div class="resource-detail__body">
            <div class="resource-detail__main">
                @if ($contentTypes !== [])
                <section>
                    <h2>{{ __('external_resources.labels.content') }}</h2>
                    <div class="resource-detail__chips">
                        @foreach ($contentTypes as $contentType)
                            <span>{{ __('external_resources.content_types.'.$contentType) }}</span>
                        @endforeach
                    </div>
                </section>
                @endif

                @if ($audiences !== [])
                <section>
                    <h2>{{ __('external_resources.labels.audiences') }}</h2>
                    <div class="resource-detail__audiences">
                        @foreach ($audiences as $audience)
                            <span><span class="material-symbols-outlined" aria-hidden="true">{{ $audience === 'guest' ? 'person' : ($audience === 'student' ? 'school' : ($audience === 'teacher' ? 'co_present' : 'local_library')) }}</span>{{ __('external_resources.audiences.'.$audience) }}</span>
                        @endforeach
                    </div>
                </section>
                @endif

                @if ($instructions !== '')
                <section>
                    <h2>{{ __('external_resources.labels.instructions') }}</h2>
                    <p class="resource-detail__instructions">{{ $instructions }}</p>
                </section>
                @endif
            </div>

            <aside class="resource-detail__aside">
                <dl>
                    <div>
                        <dt>{{ __('external_resources.expiry.label') }}</dt>
                        <dd>
                            @if (in_array($resource['resource_type'], ['open_access', 'internal'], true))
                                {{ __('external_resources.expiry.unlimited') }}
                            @elseif ($expiry)
                                {{ __('external_resources.expiry.until', ['date' => date('d.m.Y', strtotime($expiry))]) }}
                            @else
                                {{ __('external_resources.expiry.not_specified') }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('external_resources.labels.access') }}</dt>
                        <dd>{{ __('external_resources.access_types.'.($resource['access_type'] ?? 'open').'.description') }}</dd>
                    </div>
                </dl>

                @if ($canOpen)
                    <a class="resource-detail__open" href="{{ $resource['url'] }}" @if($opensNewTab) target="_blank" rel="noopener noreferrer" @endif>
                        {{ __('external_resources.page.open') }}
                        <span class="material-symbols-outlined" aria-hidden="true">{{ $opensNewTab ? 'open_in_new' : 'arrow_forward' }}</span>
                    </a>
                @elseif ($accessDenial === 'sign_in')
                    <a class="resource-detail__open" href="{{ $loginHref }}">
                        {{ __('external_resources.page.sign_in') }}
                        <span class="material-symbols-outlined" aria-hidden="true">login</span>
                    </a>
                @else
                    <span class="resource-detail__open resource-detail__open--disabled" aria-disabled="true">
                        {{ __('external_resources.page.'.($accessDenial === 'campus' ? 'campus_required' : ($accessDenial === 'restricted' ? 'restricted' : 'unavailable'))) }}
                    </span>
                @endif
                <a class="resource-detail__help" href="/contacts{{ $localeQuery }}">{{ __('external_resources.page.help_link') }}</a>
            </aside>
        </div>
    </article>
</div>
@endsection

@section('head')
<style>
    .resource-detail{min-height:70vh;background:#f4f7f6;color:#14282d;padding:2rem max(1.25rem,calc((100vw - 1180px)/2)) 6rem}.resource-detail__breadcrumb{display:flex;gap:.7rem;align-items:center;margin:1rem 0 2rem;color:#647775;font-size:.78rem}.resource-detail__breadcrumb a{color:#08706d;font-weight:800}.resource-detail__shell{background:#fff;border:1px solid #dbe4e1;box-shadow:0 20px 55px rgba(19,53,55,.07)}.resource-detail__hero{display:grid;grid-template-columns:120px minmax(0,1fr);gap:2rem;padding:clamp(1.5rem,5vw,4rem);background:linear-gradient(130deg,#083f42,#0a5958);color:#fff}.resource-detail__logo{width:120px;height:120px;border-radius:22px;background:#fff;display:grid;place-items:center;overflow:hidden;color:#08706d;font:700 2.5rem 'Newsreader',serif}.resource-detail__logo img{width:100%;height:100%;object-fit:contain;padding:1rem}.resource-detail__badges{display:flex;flex-wrap:wrap;gap:.5rem}.resource-detail__badge{padding:.45rem .65rem;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);font-size:.68rem;font-weight:800}.resource-detail__badge--expired,.resource-detail__badge--inactive{background:#7c3131}.resource-detail__heading h1{margin:1rem 0 .4rem;font:500 clamp(2.3rem,5vw,4.2rem)/1 'Newsreader',serif;letter-spacing:-.025em}.resource-detail__provider{margin:0;color:#b9dad7;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.resource-detail__lead{max-width:850px;margin:1.4rem 0 0;color:#d9e9e7;line-height:1.75}.resource-detail__body{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:3rem;padding:clamp(1.5rem,5vw,4rem)}.resource-detail__main{display:grid;gap:2.7rem}.resource-detail__main section{border-bottom:1px solid #e0e7e5;padding-bottom:2.4rem}.resource-detail__main h2{margin:0 0 1rem;font:600 1.7rem 'Newsreader',serif}.resource-detail__chips,.resource-detail__audiences{display:flex;flex-wrap:wrap;gap:.6rem}.resource-detail__chips span,.resource-detail__audiences>span{display:inline-flex;align-items:center;gap:.4rem;padding:.65rem .8rem;background:#edf4f2;color:#295653;font-size:.76rem;font-weight:750}.resource-detail__audiences .material-symbols-outlined{font-size:1rem}.resource-detail__instructions{margin:0;white-space:pre-line;color:#4d6260;line-height:1.8}.resource-detail__aside{align-self:start;padding:1.4rem;background:#f2f6f5}.resource-detail__aside dl{margin:0;display:grid;gap:1.2rem}.resource-detail__aside dl div{padding-bottom:1.1rem;border-bottom:1px solid #d8e2df}.resource-detail__aside dt{font-size:.67rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:#71827f}.resource-detail__aside dd{margin:.4rem 0 0;color:#263d3b;font-size:.82rem;line-height:1.55}.resource-detail__open{margin-top:1.4rem;min-height:50px;display:flex;align-items:center;justify-content:center;gap:.5rem;background:#07706d;color:#fff;font-weight:850;text-decoration:none}.resource-detail__open--disabled{background:#9daaa7}.resource-detail__help{display:block;margin-top:1rem;text-align:center;color:#07706d;font-size:.78rem;font-weight:800}@media(max-width:780px){.resource-detail__hero{grid-template-columns:1fr}.resource-detail__logo{width:88px;height:88px}.resource-detail__body{grid-template-columns:1fr}.resource-detail__aside{order:-1}}
    .resource-detail__breadcrumb span,.resource-detail__heading h1,.resource-detail__lead,.resource-detail__instructions,.resource-detail__aside dd{overflow-wrap:anywhere}
</style>
@endsection
