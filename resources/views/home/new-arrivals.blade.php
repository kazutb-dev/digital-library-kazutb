{{--
  Section 3 — New additions.

  Driven by the presentable, non-draft slice of the canonical catalogue,
  ordered by copy registration date. Real cover imagery is used when present;
  otherwise every record receives the same neutral bibliographic placeholder.

  The rail loops continuously; the duplicate set makes the movement seamless
  without controls.
--}}
@php
  $a = $sections[$lang]['arrivals'];
  $udcLabel = $lang === 'ru' ? 'УДК' : ($lang === 'kk' ? 'ӘОЖ' : 'UDC');
@endphp

<section class="hs hs-section hs-section--wash hs-section--fullbleed" data-section="homepage-new-arrivals">
  <div class="hs-section__inner">
    <header class="hs-head">
      <div class="hs-head__copy">
        <p class="hs-kicker">{{ $a['kicker'] }}</p>
        <h2 class="hs-title">{{ $a['title'] }}</h2>
        <p class="hs-lead">{{ $a['lead'] }}</p>
      </div>
    </header>
  </div>

  @if(count($newArrivals) > 0)
    <div class="hs-rail hs-rail--fullbleed" data-rail>
      <div class="hs-rail__track hs-rail__track--marquee" id="hs-arrivals-rail" tabindex="0" role="group" aria-label="{{ $a['title'] }}">
        @for($repeat = 0; $repeat < 2; $repeat++)
          @foreach($newArrivals as $index => $book)
          @php
            $title = trim((string) ($book['title']['display'] ?? $book['title']['raw'] ?? ''));
            $author = trim((string) ($book['primaryAuthor'] ?? ''));
            $publisher = trim((string) ($book['publisher']['name'] ?? ''));
            $year = $book['publicationYear'] ?? null;
            // Guests receive the human-readable UDC section; authenticated
            // users may also get the raw code from the presenter.
            $udc = trim((string) ($book['udc']['display'] ?? $book['udc']['description'] ?? $book['udc']['raw'] ?? ''));
            $available = (int) ($book['copies']['available'] ?? 0);
            $total = (int) ($book['copies']['total'] ?? 0);
            $coverPath = trim((string) ($book['coverPath'] ?? ''));
            $identifier = trim((string) ($book['isbn']['raw'] ?? '')) ?: (string) ($book['id'] ?? '');
            $detail = $withLang('/book/' . rawurlencode($identifier));
            $coverTones = ['hs-book--navy', 'hs-book--wine', 'hs-book--forest', 'hs-book--wood', 'hs-book--plum'];
            $coverTone = $coverTones[$index % count($coverTones)];
          @endphp

          <article class="hs-book {{ $coverTone }}" data-book-id="{{ $book['id'] ?? '' }}">
            <div class="hs-book__cover{{ $coverPath !== '' ? ' hs-book__cover--image' : ' hs-book__cover--placeholder' }}">
              @if($coverPath !== '')
                <img class="hs-book__cover-image" src="{{ $coverPath }}" alt="" loading="lazy" />
              @else
                <div class="hs-book__placeholder-copy" aria-label="{{ $title }}">
                  <span class="material-symbols-outlined hs-book__placeholder-icon" aria-hidden="true">menu_book</span>
                  <strong>{{ $title }}</strong>
                  @if($author !== '')
                    <small>{{ $author }}</small>
                  @endif
                </div>
              @endif
              <span class="hs-book__udc">{{ $udc !== '' ? $udcLabel . ' ' . $udc : $udcLabel }}</span>
            </div>

            <div class="hs-book__body">
              <h3 class="hs-book__title"><a href="{{ $detail }}">{{ $title }}</a></h3>

              @if($author !== '')
                <p class="hs-book__author">{{ $author }}</p>
              @endif

              <p class="hs-book__meta">
                {{ $publisher !== '' ? $publisher : $a['no_publisher'] }}@if($year) · {{ $year }}@endif
              </p>

              @if($total === 0)
                <span class="hs-book__status hs-book__status--pending">
                  <i aria-hidden="true"></i>{{ $a['no_holdings'] }}
                </span>
              @elseif($available > 0)
                <span class="hs-book__status">
                  <i aria-hidden="true"></i>{{ $a['available'] }}@if($total) — {{ $available }} {{ $a['copies'] }}@endif
                </span>
              @else
                <span class="hs-book__status hs-book__status--out">
                  <i aria-hidden="true"></i>{{ $a['unavailable'] }}
                </span>
              @endif

              <a class="hs-book__cta" href="{{ $detail }}">{{ $a['details'] }}</a>
            </div>
          </article>
          @endforeach
        @endfor
      </div>
    </div>
  @else
    <div class="hs-section__inner">
      <p class="hs-lead">{{ $a['empty'] }}</p>
    </div>
  @endif
</section>
