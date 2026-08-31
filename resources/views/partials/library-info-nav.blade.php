@php
  $libraryInfoLang = $lang ?? app()->getLocale();
  $libraryInfoLang = in_array($libraryInfoLang, ['kk', 'ru', 'en'], true) ? $libraryInfoLang : 'kk';
  $libraryInfoActive = $activePage ?? '';
  $libraryInfoRoute = $routeWithLang ?? static fn (string $path): string => $libraryInfoLang === 'kk' ? $path : $path.'?lang='.$libraryInfoLang;
  $libraryInfoCopy = [
    'ru' => [
      'label' => 'Информация о библиотеке',
      'items' => [
        ['contacts', 'Контакты', 'Адрес, режим работы и каналы связи', 'contact_support', '/contacts'],
        ['rules', 'Правила библиотеки', 'Выдача, работа с фондом и электронный доступ', 'menu_book', '/rules'],
        ['leadership', 'Руководство', 'Подтверждённые сведения о руководителе', 'badge', '/leadership'],
      ],
    ],
    'kk' => [
      'label' => 'Кітапхана туралы ақпарат',
      'count' => '3 бөлім',
      'items' => [
        ['contacts', 'Байланыс', 'Мекенжай, жұмыс уақыты және байланыс арналары', 'contact_support', '/contacts'],
        ['rules', 'Кітапхана ережелері', 'Беру, қорды пайдалану және электрондық қолжетімділік', 'menu_book', '/rules'],
        ['leadership', 'Басшылық', 'Кітапхана басшысы туралы расталған мәліметтер', 'badge', '/leadership'],
      ],
    ],
    'en' => [
      'label' => 'Library information',
      'count' => '3 sections',
      'items' => [
        ['contacts', 'Contacts', 'Address, opening hours, and contact channels', 'contact_support', '/contacts'],
        ['rules', 'Library rules', 'Borrowing, collection use, and digital access', 'menu_book', '/rules'],
        ['leadership', 'Leadership', 'Confirmed information about the director', 'badge', '/leadership'],
      ],
    ],
  ][$libraryInfoLang];
@endphp

<section class="library-info-switcher" data-section="{{ $libraryInfoNavSection ?? 'library-info-navigation' }}">
  <div class="library-info-switcher__head">
    <p>{{ $libraryInfoCopy['label'] }}</p>
  </div>
  <nav class="library-info-nav" aria-label="{{ $libraryInfoCopy['label'] }}">
    @foreach($libraryInfoCopy['items'] as [$key, $label, $description, $icon, $href])
      <a href="{{ $libraryInfoRoute($href) }}"
         class="library-info-nav__link"
         data-library-info-link="{{ $key }}"
         @if(($libraryInfoNavSection ?? null) === 'contacts-canonical-visit-rules' && in_array($key, ['rules', 'leadership'], true)) data-test-id="contacts-canonical-link-{{ $key }}" @endif
         @if($libraryInfoActive === $key) aria-current="page" @endif>
        <span class="library-info-nav__icon material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
        <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
        <span class="library-info-nav__arrow material-symbols-outlined" aria-hidden="true">arrow_forward</span>
      </a>
    @endforeach
  </nav>
</section>
