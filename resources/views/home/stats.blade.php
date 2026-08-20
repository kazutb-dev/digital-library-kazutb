@php
  $statsCopy = [
    'ru' => ['kicker' => 'Библиотека в цифрах', 'title' => 'Данные каталога', 'lead' => 'Показатели рассчитаны по текущим записям каталога, экземплярам фонда и опубликованному справочнику ресурсов.'],
    'kk' => ['kicker' => 'Кітапхана сандармен', 'title' => 'Каталог деректері', 'lead' => 'Көрсеткіштер каталог жазбалары, қор даналары және жарияланған ресурстар анықтамалығы бойынша есептеледі.'],
    'en' => ['kicker' => 'The library in numbers', 'title' => 'Catalogue data', 'lead' => 'Figures are calculated from current catalogue records, collection copies, and the published resource directory.'],
  ][$lang];
@endphp

@if(($homepageTruthStats ?? []) !== [])
<section class="hs hs-section hs-section--wash" data-section="homepage-statistics">
  <div class="hs-stats-layout">
    <div class="hs-head__copy">
      <p class="hs-kicker">{{ $statsCopy['kicker'] }}</p>
      <h2 class="hs-title">{{ $statsCopy['title'] }}</h2>
      <p class="hs-lead">{{ $statsCopy['lead'] }}</p>
    </div>

    <div class="hs-stats">
      @foreach($homepageTruthStats as $item)
        <div class="hs-stat" data-stat-source="{{ $item['source'] }}">
          <span class="hs-stat__number" aria-hidden="true">0{{ $loop->iteration }}</span>
          <span class="hs-stat__icon material-symbols-outlined" aria-hidden="true">{{ $item['icon'] }}</span>
          <span class="hs-stat__value">{{ $item['value'] }}</span>
          <span class="hs-stat__label">{{ $item['label'] }}</span>
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif
