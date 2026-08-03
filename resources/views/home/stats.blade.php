{{--
  Section 5 — Library statistics.

  These three figures are institutional claims about the physical library
  (print stock, reading rooms, fund availability) supplied by the library
  itself; the platform cannot compute them from the current data model, so
  they live in config/homepage_sections.php rather than being derived.
  Replace them there when official figures are updated.
--}}
@php $st = $sections[$lang]['stats']; @endphp

<section class="hs hs-section hs-section--wash" data-section="homepage-statistics">
  <div class="hs-stats-layout">
    <div class="hs-head__copy">
      <p class="hs-kicker">{{ $st['kicker'] }}</p>
      <h2 class="hs-title">{{ $st['title'] }}</h2>
      <p class="hs-lead">{{ $st['lead'] }}</p>
    </div>

    <div class="hs-stats">
      @foreach($st['items'] as $item)
        <div class="hs-stat">
          <span class="hs-stat__number" aria-hidden="true">0{{ $loop->iteration }}</span>
          <span class="hs-stat__icon material-symbols-outlined" aria-hidden="true">{{ $item['icon'] }}</span>
          <span class="hs-stat__value">{{ $item['value'] }}</span>
          <span class="hs-stat__label">{{ $item['label'] }}</span>
        </div>
      @endforeach
    </div>
  </div>
</section>
