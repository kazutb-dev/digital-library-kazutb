@php
    $errorCode = (string) ($errorCode ?? 500);
    $errorTitle = __('errors.pages.'.$errorCode.'.title');
    $requestId = request()->attributes->get('request_id');
@endphp

@section('title', $errorTitle.' — '.__('brand.library.name'))

@section('content')
<section class="public-error" aria-labelledby="error-title">
  <div class="public-error__code" aria-hidden="true">{{ $errorCode }}</div>
  <p class="public-error__eyebrow">{{ __('errors.eyebrow') }}</p>
  <h1 id="error-title">{{ $errorTitle }}</h1>
  <p>{{ __('errors.pages.'.$errorCode.'.body') }}</p>
  @if(is_string($requestId) && $requestId !== '')
    <p class="public-error__request">{{ __('errors.request_id', ['id' => $requestId]) }}</p>
  @endif
  <div class="public-error__actions">
    <a class="btn btn-primary" href="/">{{ __('errors.home') }}</a>
    <button class="btn btn-ghost" type="button" onclick="history.back()">{{ __('errors.back') }}</button>
  </div>
</section>
@endsection

@section('head')
<style>
  .public-error { min-height: 68vh; display: grid; place-content: center; justify-items: start; padding: clamp(64px, 10vw, 140px) var(--page-inset); background: #fff; border-bottom: 1px solid #e3e6e5; }
  .public-error__code { font: 700 clamp(88px, 18vw, 220px)/.72 'Literata', serif; color: #eef1f0; letter-spacing: -.08em; }
  .public-error__eyebrow { margin: 28px 0 12px; color: #078a84; font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
  .public-error h1 { margin: 0; font: 700 clamp(36px, 5vw, 62px)/1.02 'Literata', serif; letter-spacing: -.045em; }
  .public-error > p { max-width: 58ch; margin: 18px 0 0; color: #5c6866; }
  .public-error__request { font-family: ui-monospace, monospace; font-size: .78rem; overflow-wrap: anywhere; }
  .public-error__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
</style>
@endsection
