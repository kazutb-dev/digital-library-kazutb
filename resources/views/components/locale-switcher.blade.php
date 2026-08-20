@props([
    'variant' => 'light',
    'align' => 'right',
    'showLabel' => true,
    // Kept as a compatibility shim for older call sites while every layout
    // migrates to the explicit variant API.
    'compact' => false,
])
@php
    $activeLocale = app()->getLocale();
    $resolvedVariant = $compact ? 'compact' : $variant;
    $resolvedVariant = in_array($resolvedVariant, ['light', 'dark', 'compact', 'mobile'], true)
        ? $resolvedVariant
        : 'light';
    $resolvedAlign = $align === 'left' ? 'left' : 'right';
    $showLabel = (bool) $showLabel;
@endphp

@once
<style>
    .locale-switcher{position:relative;display:inline-flex;flex:none;font-family:'Manrope','Google Sans',Arial,sans-serif}
    .locale-switcher>summary{list-style:none}
    .locale-switcher>summary::-webkit-details-marker{display:none}
    .locale-switcher__trigger{display:inline-flex;height:2.5rem;min-width:4.75rem;cursor:pointer;align-items:center;justify-content:center;gap:.45rem;border:1px solid rgba(15,76,129,.18);border-radius:.65rem;background:#fff;padding:.45rem .7rem;color:#123e68;box-shadow:0 2px 9px rgba(0,31,63,.1);font-size:.75rem;font-weight:800;line-height:1;letter-spacing:.045em;white-space:nowrap;transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}
    .locale-switcher__trigger:hover{border-color:rgba(15,76,129,.42);box-shadow:0 4px 14px rgba(0,31,63,.14)}
    .locale-switcher__trigger:focus-visible{outline:3px solid rgba(15,118,110,.32);outline-offset:2px}
    .locale-switcher[open] .locale-switcher__trigger{border-color:#0f4c81;box-shadow:0 0 0 2px rgba(15,76,129,.1)}
    .locale-switcher__globe{display:block;width:1.15rem;height:1.15rem;flex:none;fill:none;stroke:#0f6eaa;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .locale-switcher__menu{position:absolute;z-index:250;top:calc(100% + .5rem);min-width:11.25rem;border:1px solid #e1e5e8;border-radius:.7rem;background:#fff;padding:.35rem;box-shadow:0 18px 42px rgba(0,6,19,.16)}
    .locale-switcher__menu--right{right:0}.locale-switcher__menu--left{left:0}
    .locale-switcher__option{display:flex;width:100%;min-height:2.5rem;cursor:pointer;align-items:center;justify-content:space-between;gap:.75rem;border:0;border-radius:.45rem;background:transparent;padding:.55rem .7rem;color:#263542;text-align:left;font:inherit;font-size:.84rem}
    .locale-switcher__option:hover,.locale-switcher__option:focus-visible{background:#f3f5f4;color:#075e5e;outline:none}
    .locale-switcher__option[aria-current=true]{background:transparent;color:#075e5e;font-weight:800}
    .locale-switcher__check{display:block;width:1rem;height:1rem;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}
    .locale-switcher__label{white-space:nowrap}
    .locale-switcher--dark .locale-switcher__trigger{width:38px;min-width:0;height:38px;padding:0;border:0!important;border-radius:999px;background:transparent!important;box-shadow:none!important;color:rgba(255,255,255,.92)}
    .locale-switcher--dark .locale-switcher__trigger:hover,
    .locale-switcher--dark .locale-switcher__trigger:focus,
    .locale-switcher--dark .locale-switcher__trigger:focus-visible,
    .locale-switcher--dark .locale-switcher__trigger:active{background:transparent!important;box-shadow:none!important;border:0!important;color:#fff}
    .site-header.is-solid .locale-switcher--dark .locale-switcher__trigger{color:var(--hdr-ink)}
    .site-header.is-solid .locale-switcher--dark .locale-switcher__trigger:hover,
    .site-header.is-solid .locale-switcher--dark .locale-switcher__trigger:focus,
    .site-header.is-solid .locale-switcher--dark .locale-switcher__trigger:focus-visible,
    .site-header.is-solid .locale-switcher--dark .locale-switcher__trigger:active{color:var(--hdr-accent)}
    .locale-switcher--dark[open] .locale-switcher__trigger{border:0!important;box-shadow:none!important;background:transparent!important}
    .locale-switcher--dark .locale-switcher__globe{stroke:currentColor}
    .locale-switcher--mobile .locale-switcher__trigger{height:2.25rem;min-width:4.35rem;padding-inline:.55rem}
    @media(max-width:420px){.locale-switcher__trigger{height:2.25rem;min-width:4.25rem;padding-inline:.5rem}.locale-switcher__globe{width:1.05rem;height:1.05rem}}
    @media print{.locale-switcher{display:none!important}}
</style>
<script>
    document.addEventListener('click', function (event) {
        document.querySelectorAll('details[data-locale-switcher][open]').forEach(function (switcher) {
            if (!switcher.contains(event.target)) switcher.removeAttribute('open');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('details[data-locale-switcher][open]').forEach(function (switcher) {
            switcher.removeAttribute('open');
            switcher.querySelector('summary')?.focus();
        });
    });
    document.addEventListener('toggle', function (event) {
        if (!event.target.matches?.('details[data-locale-switcher]')) return;
        event.target.querySelector('summary')?.setAttribute('aria-expanded', event.target.open ? 'true' : 'false');
    }, true);
    document.addEventListener('submit', function (event) {
        const switcher = event.target.closest?.('details[data-locale-switcher]');
        if (switcher) switcher.removeAttribute('open');
    });
</script>
@endonce

<details {{ $attributes->class(['locale-switcher', 'locale-switcher--'.$resolvedVariant]) }} data-locale-switcher data-locale-variant="{{ $resolvedVariant }}">
    <summary
        class="locale-switcher__trigger"
        aria-label="{{ __('shell.language_switcher') }}"
        aria-haspopup="menu"
        aria-expanded="false"
    >
        {{-- Exact line icon retained from the original public navbar. --}}
        <svg class="locale-switcher__globe" data-locale-globe viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="8.5"></circle>
            <path d="M3.5 12h17"></path>
            <path d="M12 3.5c2.6 2.2 4 5 4 8.5s-1.4 6.3-4 8.5c-2.6-2.2-4-5-4-8.5s1.4-6.3 4-8.5Z"></path>
        </svg>
        @if($showLabel)
            <span class="locale-switcher__label">{{ __('locale.names.'.$activeLocale) }}</span>
        @endif
    </summary>
    <div class="locale-switcher__menu locale-switcher__menu--{{ $resolvedAlign }}" role="menu" aria-label="{{ __('shell.language_switcher') }}">
        @foreach (\App\Support\LocaleResolver::SUPPORTED as $locale)
            <form method="POST" action="{{ route('locale.update') }}" role="none">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                <button
                    type="submit"
                    role="menuitem"
                    class="locale-switcher__option"
                    @if($activeLocale === $locale) aria-current="true" @endif
                >
                    <span>{{ __('locale.names.'.$locale) }}</span>
                    @if($activeLocale === $locale)
                        <svg class="locale-switcher__check" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5.5 12.5l4 4 9-10"></path></svg>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</details>
