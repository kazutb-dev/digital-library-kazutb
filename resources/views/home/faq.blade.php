{{--
  Section 6 — FAQ.

  Answers restate the actual policy rather than generic copy: loan and renewal
  terms follow PROJECT_CONTEXT §13, reservation limits §12, digital access §19,
  and the service-point rooms §21.7. The AI answer states plainly that the
  feature is still in development (§34) and routes readers to the bibliographer.

  Native <details> keeps the accordion keyboard-accessible with no JavaScript.
--}}
@php $f = $sections[$lang]['faq']; @endphp

<section class="hs hs-section" data-section="homepage-faq">
  <header class="hs-head">
    <div class="hs-head__copy">
      <p class="hs-kicker">{{ $f['kicker'] }}</p>
      <h2 class="hs-title">{{ $f['title'] }}</h2>
      <p class="hs-lead">{{ $f['lead'] }}</p>
    </div>
  </header>

  <div class="hs-faq">
    @foreach($f['items'] as $item)
      <details class="hs-faq__item">
        <summary>
          {{ $item['q'] }}
          <span class="hs-faq__sign material-symbols-outlined" aria-hidden="true">expand_more</span>
        </summary>
        <p class="hs-faq__answer">{{ $item['a'] }}</p>
      </details>
    @endforeach
  </div>

  <div class="hs-faq__more">
    <a class="hs-link" href="{{ $withLang('/rules') }}#borrowing">
      {{ $f['more'] }}
      <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </a>
  </div>
</section>
