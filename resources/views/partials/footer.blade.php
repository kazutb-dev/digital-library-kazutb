@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'ru';

  $footerHref = static function (string $href) use ($pageLang): string {
      if (str_starts_with($href, 'http') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) return $href;
      if ($pageLang === 'ru') return $href;
      return $href . (str_contains($href, '?') ? '&' : '?') . 'lang=' . $pageLang;
  };

  $footerCopy = [
    'ru' => [
      'brand' => 'Казахский университет технологии и бизнеса имени К. Кулажанова',
      'tagline' => 'Технологии · Бизнес · Инновации',
      'address' => ['010000, Казахстан, г. Астана,', 'Левый берег, район Нура,', 'ул. Кайыма Мухамедханова, 37А'],
      'accreditation' => ['Свидетельство №28/25KA0315 от 29.12.2025 г.', 'CAAAE — до 30.06.2033 г.'],
      'navigation' => 'Навигация',
      'updates' => 'Обновления',
      'institution' => 'Институт',
      'support' => 'Поддержка',
      'navigation_links' => [
        ['label' => 'Каталог', 'href' => '/catalog'],
        ['label' => 'Обзор фонда', 'href' => '/discover'],
        ['label' => 'Внешние ресурсы', 'href' => '/resources'],
        ['label' => 'Научный репозиторий', 'href' => '/repository'],
        ['label' => 'Моя подборка', 'href' => '/shortlist'],
      ],
      'updates_links' => [
        ['label' => 'Новости', 'href' => '/news'],
        ['label' => 'События', 'href' => '/events'],
      ],
      'institution_links' => [
        ['label' => 'О библиотеке', 'href' => '/about'],
        ['label' => 'Руководство', 'href' => '/leadership'],
        ['label' => 'Правила библиотеки', 'href' => '/rules'],
        ['label' => 'Контакты', 'href' => '/contacts'],
      ],
      'support_links' => [
        ['label' => 'Войти', 'href' => '/login'],
        ['label' => 'Личный кабинет', 'href' => '/dashboard'],
        ['label' => 'Платонус', 'href' => 'https://platonus.kaztbu.edu.kz/'],
        ['label' => 'Корпоративная почта', 'href' => 'https://mail.kaztbu.edu.kz/'],
        ['label' => 'Сайт университета', 'href' => 'https://kaztbu.edu.kz/'],
      ],
      'rights' => 'Все права защищены.',
      'entity' => 'АО «КазУТБ» · Астана, Казахстан',
    ],
    'kk' => [
      'brand' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
      'tagline' => 'Технологиялар · Бизнес · Инновациялар',
      'address' => ['010000, Қазақстан, Астана қ.,', 'Сол жағалау, Нұра ауданы,', 'Қайым Мұхамедханов к-сі, 37А'],
      'accreditation' => ['№28/25KA0315 куәлігі, 29.12.2025 ж.', 'CAAAE — 30.06.2033 ж. дейін'],
      'navigation' => 'Бөлімдер',
      'updates' => 'Жаңартулар',
      'institution' => 'Институт',
      'support' => 'Қолдау',
      'navigation_links' => [
        ['label' => 'Каталог', 'href' => '/catalog'],
        ['label' => 'Қорға шолу', 'href' => '/discover'],
        ['label' => 'Сыртқы ресурстар', 'href' => '/resources'],
        ['label' => 'Ғылыми репозиторий', 'href' => '/repository'],
        ['label' => 'Менің іріктемем', 'href' => '/shortlist'],
      ],
      'updates_links' => [
        ['label' => 'Жаңалықтар', 'href' => '/news'],
        ['label' => 'Іс-шаралар', 'href' => '/events'],
      ],
      'institution_links' => [
        ['label' => 'Кітапхана туралы', 'href' => '/about'],
        ['label' => 'Басшылық', 'href' => '/leadership'],
        ['label' => 'Кітапхана ережелері', 'href' => '/rules'],
        ['label' => 'Байланыс', 'href' => '/contacts'],
      ],
      'support_links' => [
        ['label' => 'Кіру', 'href' => '/login'],
        ['label' => 'Жеке кабинет', 'href' => '/dashboard'],
        ['label' => 'Платонус', 'href' => 'https://platonus.kaztbu.edu.kz/'],
        ['label' => 'Корпоративтік пошта', 'href' => 'https://mail.kaztbu.edu.kz/'],
        ['label' => 'Университет сайты', 'href' => 'https://kaztbu.edu.kz/'],
      ],
      'rights' => 'Барлық құқықтар қорғалған.',
      'entity' => '«ҚазТБУ» АҚ · Астана, Қазақстан',
    ],
    'en' => [
      'brand' => 'Kazakh University of Technology and Business named after K. Kulazhanov',
      'tagline' => 'Technology · Business · Innovation',
      'address' => ['010000, Astana, Kazakhstan,', 'Left Bank, Nura District,', '37A Kaiym Mukhamedkhanov Street'],
      'accreditation' => ['Certificate No. 28/25KA0315, 29 December 2025', 'CAAAE — valid until 30 June 2033'],
      'navigation' => 'Navigation',
      'updates' => 'Updates',
      'institution' => 'Institution',
      'support' => 'Support',
      'navigation_links' => [
        ['label' => 'Catalog', 'href' => '/catalog'],
        ['label' => 'Discover the Collection', 'href' => '/discover'],
        ['label' => 'External Resources', 'href' => '/resources'],
        ['label' => 'Scholarly Repository', 'href' => '/repository'],
        ['label' => 'My Shortlist', 'href' => '/shortlist'],
      ],
      'updates_links' => [
        ['label' => 'News', 'href' => '/news'],
        ['label' => 'Events', 'href' => '/events'],
      ],
      'institution_links' => [
        ['label' => 'About the Library', 'href' => '/about'],
        ['label' => 'Leadership', 'href' => '/leadership'],
        ['label' => 'Library Rules', 'href' => '/rules'],
        ['label' => 'Contacts', 'href' => '/contacts'],
      ],
      'support_links' => [
        ['label' => 'Sign in', 'href' => '/login'],
        ['label' => 'Member Dashboard', 'href' => '/dashboard'],
        ['label' => 'Platonus', 'href' => 'https://platonus.kaztbu.edu.kz/'],
        ['label' => 'Corporate mail', 'href' => 'https://mail.kaztbu.edu.kz/'],
        ['label' => 'University website', 'href' => 'https://kaztbu.edu.kz/'],
      ],
      'rights' => 'All rights reserved.',
      'entity' => 'KazUTB JSC · Astana, Kazakhstan',
    ],
  ][$pageLang];
@endphp

<style>
  .university-footer {
    --university-footer-ink: #102945;
    --university-footer-deep: #0b2037;
    --university-footer-surface: #f5f3ee;
    --university-footer-paper: #fbfaf6;
    --university-footer-accent: #09bab2;
    --university-footer-warm: #b38b4d;
    position: relative;
    width: 100%;
    margin-top: 0;
    padding: clamp(72px, 7vw, 100px) max(5vw, calc((100vw - 1440px) / 2)) 28px;
    color: rgba(16, 41, 69, .72);
    background:
      radial-gradient(circle at 12% 5%, rgba(9, 186, 178, .08), transparent 29%),
      radial-gradient(circle at 86% 0%, rgba(179, 139, 77, .08), transparent 26%),
      linear-gradient(180deg, var(--university-footer-surface), var(--university-footer-paper));
  }
  .university-footer::before {
    position: absolute;
    top: 0;
    right: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--university-footer-accent) 30%, var(--university-footer-warm) 70%, transparent);
    content: "";
  }
  .university-footer__grid {
    display: grid;
    grid-template-columns: minmax(260px, 1.35fr) repeat(4, minmax(150px, 1fr));
    gap: clamp(38px, 5vw, 72px);
    width: 100%;
    max-width: 1440px;
    margin: 0 auto;
  }
  .university-footer__identity {
    min-width: 0;
  }
  .university-footer__brand {
    display: flex;
    align-items: center;
    gap: 17px;
    margin-bottom: 26px;
  }
  .university-footer__logo {
    display: block;
    flex: 0 0 auto;
    width: 96px;
    height: 96px;
    padding: 2px;
    object-fit: contain;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 12px 34px rgba(16, 41, 69, .12);
  }
  .university-footer__brand-title {
    display: block;
    max-width: 285px;
    color: var(--university-footer-ink);
    font-size: 15px;
    font-weight: 850;
    letter-spacing: -.025em;
    line-height: 1.08;
    text-transform: uppercase;
  }
  .university-footer__tagline {
    display: block;
    margin-top: 6px;
    color: rgba(16, 41, 69, .56);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
  }
  .university-footer__address {
    margin: 0;
    color: rgba(16, 41, 69, .72);
    font-size: 13px;
    line-height: 1.75;
  }
  .university-footer__contact {
    display: block;
    width: fit-content;
    margin: 12px 0 0;
    color: rgba(16, 41, 69, .68);
    font-size: 13px;
  }
  .university-footer__contact--email {
    margin-top: 24px;
    color: var(--university-footer-accent);
  }
  .university-footer__contact:hover {
    color: var(--university-footer-ink);
  }
  .university-footer__accreditation {
    width: fit-content;
    margin-top: 25px;
    padding: 9px 12px;
    color: rgba(16, 41, 69, .48);
    border: 1px solid rgba(16, 41, 69, .08);
    font-size: 9px;
    line-height: 1.55;
  }
  .university-footer__column h2 {
    margin: 0 0 22px;
    padding-bottom: 14px;
    color: var(--university-footer-ink);
    border-bottom: 1px solid rgba(16, 41, 69, .1);
    font-family: "Literata", serif;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .university-footer__column a {
    display: block;
    width: fit-content;
    margin: 12px 0;
    color: rgba(16, 41, 69, .64);
    font-size: 13px;
    line-height: 1.5;
    transition: color .25s ease, transform .25s ease;
  }
  .university-footer__column a:hover {
    color: var(--university-footer-accent);
    transform: translateX(5px);
  }
  .university-footer__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
    max-width: 1440px;
    margin: clamp(48px, 5vw, 72px) auto 0;
    padding-top: 25px;
    color: rgba(16, 41, 69, .48);
    border-top: 1px solid rgba(16, 41, 69, .1);
    font-size: 11px;
    line-height: 1.6;
  }
  @media (max-width: 1279px) {
    .university-footer__grid {
      grid-template-columns: 1fr 1fr;
    }
    .university-footer__identity {
      grid-column: 1 / -1;
    }
  }
  @media (max-width: 700px) {
    .university-footer {
      padding: 66px 20px 24px;
    }
    .university-footer__grid {
      grid-template-columns: 1fr;
      gap: 34px;
    }
    .university-footer__identity {
      grid-column: auto;
    }
    .university-footer__brand {
      align-items: flex-start;
    }
    .university-footer__logo {
      width: 84px;
      height: 84px;
    }
    .university-footer__brand-title {
      font-size: 13px;
    }
    .university-footer__bottom {
      align-items: flex-start;
      flex-direction: column;
    }
  }
</style>

<footer class="university-footer">
  <div class="university-footer__grid">
    <div class="university-footer__identity">
      <a class="university-footer__brand" href="{{ $pageLang === 'ru' ? '/' : '/?lang=' . $pageLang }}">
        <img class="university-footer__logo" src="{{ asset('logo.png') }}" alt="{{ $footerCopy['brand'] }}" loading="lazy" decoding="async">
        <span>
          <span class="university-footer__brand-title">{{ $footerCopy['brand'] }}</span>
          <span class="university-footer__tagline">{{ $footerCopy['tagline'] }}</span>
        </span>
      </a>

      <p class="university-footer__address">
        @foreach($footerCopy['address'] as $line)
          {{ $line }}@if(! $loop->last)<br>@endif
        @endforeach
      </p>
      <a class="university-footer__contact university-footer__contact--email" href="mailto:info@kaztbu.edu.kz">info@kaztbu.edu.kz</a>
      <a class="university-footer__contact" href="tel:+77172697060">+7 7172 69-70-60</a>
      <a class="university-footer__contact" href="tel:+77752322266">+7 775 232-22-66</a>

      <p class="university-footer__accreditation">
        {{ $footerCopy['accreditation'][0] }}<br>
        {{ $footerCopy['accreditation'][1] }}
      </p>
    </div>

    @foreach([
      [$footerCopy['navigation'], $footerCopy['navigation_links']],
      [$footerCopy['updates'], $footerCopy['updates_links']],
      [$footerCopy['institution'], $footerCopy['institution_links']],
      [$footerCopy['support'], $footerCopy['support_links']],
    ] as [$heading, $links])
      <nav class="university-footer__column" aria-label="{{ $heading }}">
        <h2>{{ $heading }}</h2>
        @foreach($links as $link)
          <a href="{{ $footerHref($link['href']) }}" @if(str_starts_with($link['href'], 'http')) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
        @endforeach
      </nav>
    @endforeach
  </div>

  <div class="university-footer__bottom">
    <span>© {{ date('Y') }} {{ $footerCopy['brand'] }}. {{ $footerCopy['rights'] }}</span>
    <span>{{ $footerCopy['entity'] }}</span>
  </div>
</footer>
