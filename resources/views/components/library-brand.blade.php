@props([
    'variant' => 'cabinet',
    'href' => '/',
])
@php
    $resolvedVariant = in_array($variant, ['public', 'cabinet', 'sidebar', 'auth', 'compact', 'print'], true)
        ? $variant
        : 'cabinet';
@endphp

@once
<style>
    .library-brand{display:inline-flex;min-width:0;align-items:center;gap:.75rem;color:inherit;text-decoration:none;font-family:'Manrope','Google Sans',Arial,sans-serif}
    .library-brand__logo{display:block;width:3.25rem;height:3.25rem;flex:none;object-fit:contain;aspect-ratio:1}
    .library-brand__copy{display:flex;min-width:0;flex-direction:column;gap:.18rem;line-height:1.16}
    .library-brand__library{color:#072d52;font-size:.96rem;font-style:normal;font-weight:800;letter-spacing:-.01em}
    .library-brand__university{display:-webkit-box;max-width:16.5rem;overflow:hidden;color:#526371;font-size:.64rem;font-style:normal;font-weight:600;line-height:1.28;-webkit-box-orient:vertical;-webkit-line-clamp:3}
    .library-brand--sidebar{width:100%;align-items:flex-start}
    .library-brand--sidebar .library-brand__logo{width:3.5rem;height:3.5rem}
    .library-brand--sidebar .library-brand__university{max-width:10.75rem}
    .library-brand--auth{align-items:center}
    .library-brand--auth .library-brand__logo{width:4rem;height:4rem}
    .library-brand--auth .library-brand__library{color:#fff;font-size:1.2rem}
    .library-brand--auth .library-brand__university{max-width:22rem;color:rgba(255,255,255,.78);font-size:.72rem}
    .library-brand--compact .library-brand__logo{width:2.65rem;height:2.65rem}
    .library-brand--compact .library-brand__university{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .library-brand--print{color:#000}
    .library-brand--print .library-brand__logo{width:3.5rem;height:3.5rem;filter:grayscale(1)}
    @media(max-width:1180px){.library-brand--cabinet .library-brand__university{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}}
    @media(max-width:520px){.library-brand--cabinet .library-brand__logo{width:2.5rem;height:2.5rem}.library-brand--cabinet .library-brand__library{max-width:8.75rem;font-size:.82rem}.library-brand--compact .library-brand__copy{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}}
</style>
@endonce

@if($resolvedVariant === 'public')
    <a href="{{ $href }}" {{ $attributes->class(['hdr-brand']) }} aria-label="{{ __('brand.home_aria') }}" data-library-brand="public">
        <img class="hdr-brand__mark" src="{{ asset(config('library_branding.logo')) }}" alt="{{ __('brand.logo_alt') }}" width="72" height="72" loading="eager" decoding="async">
        <span class="hdr-brand__text">
            <span class="hdr-brand__name">{{ __('brand.library.name') }}</span>
            <span class="hdr-brand__org">{{ __('brand.university.full') }}</span>
        </span>
    </a>
@else
    <a href="{{ $href }}" {{ $attributes->class(['library-brand', 'library-brand--'.$resolvedVariant]) }} aria-label="{{ __('brand.home_aria') }}" data-library-brand="{{ $resolvedVariant }}">
        <img src="{{ asset(config('library_branding.logo')) }}" alt="{{ __('brand.logo_alt') }}" class="library-brand__logo" width="512" height="512" decoding="async">
        <span class="library-brand__copy">
            <strong class="library-brand__library">{{ __('brand.library.name') }}</strong>
            <span class="library-brand__university">{{ __('brand.university.full') }}</span>
        </span>
    </a>
@endif
