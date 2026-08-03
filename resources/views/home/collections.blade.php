{{--
  Section 4 — Library collections.

  Ten subject sections of the fund, each carrying its real UDC index and
  linking into the filtered catalog. As in the faculty section, the item count
  appears only when the catalog can answer for that index.
--}}
@php
  $co = $sections[$lang]['collections'];
  $udcLabel = $lang === 'ru' ? 'УДК' : ($lang === 'kk' ? 'ӘОЖ' : 'UDC');
@endphp

<section class="hs hs-section" data-section="homepage-collections">
  <header class="hs-head">
    <div class="hs-head__copy">
      <p class="hs-kicker">{{ $co['kicker'] }}</p>
      <h2 class="hs-title">{{ $co['title'] }}</h2>
      <p class="hs-lead">{{ $co['lead'] }}</p>
    </div>
    <a class="hs-link" href="{{ $withLang('/catalog') }}">
      {{ $co['all'] }}
      <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </a>
  </header>

  <div class="hs-collections">
    @foreach($sections['collections'] as $collection)
      @php
        $code = $collection['udc'];
        $count = $udcCounts[$code] ?? null;
      @endphp
      <a class="hs-collection" href="{{ $withLang('/catalog', ['udc' => $code]) }}">
        <span class="hs-collection__top">
          <span class="hs-collection__icon" aria-hidden="true">
            <span class="material-symbols-outlined">{{ $collection['icon'] }}</span>
          </span>
          <span class="hs-collection__udc">{{ $udcLabel }} {{ $code }}</span>
        </span>

        <h3 class="hs-collection__name">{{ $co['names'][$code] }}</h3>

        @if($count)
          <span class="hs-collection__count">{{ number_format($count, 0, '.', ' ') }} {{ $co['count_label'] }}</span>
        @endif

        <p class="hs-collection__desc">{{ $co['descriptions'][$code] }}</p>
      </a>
    @endforeach
  </div>
</section>
