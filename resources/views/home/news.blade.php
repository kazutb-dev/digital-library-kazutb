@if (($homepageNews ?? collect())->isNotEmpty())
  <section class="hs hs-section hs-section--ruled homepage-managed-news" data-section="homepage-managed-news">
    <header class="hs-head">
      <div class="hs-head__copy">
        <p class="hs-kicker">{{ __('news.homepage.eyebrow') }}</p>
        <h2 class="hs-title">{{ __('news.homepage.title') }}</h2>
        <p class="hs-lead">{{ __('news.homepage.subtitle') }}</p>
      </div>
      <a class="hs-link" href="{{ $withLang('/news') }}">
        {{ __('news.homepage.all') }}
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
      </a>
    </header>

    <div class="homepage-managed-news__grid">
      @foreach ($homepageNews as $homepageNewsItem)
        <article class="homepage-managed-news__card">
          @if ($homepageNewsItem->cover_image)
            <a class="homepage-managed-news__cover" href="{{ $withLang('/news/'.$homepageNewsItem->slug) }}">
              <img
                src="{{ asset('storage/'.$homepageNewsItem->cover_image) }}"
                alt="{{ __('news.cover.alt', ['title' => $homepageNewsItem->title]) }}"
                loading="lazy"
              >
            </a>
          @endif
          <div class="homepage-managed-news__body">
            <div class="homepage-managed-news__meta">
              <span>{{ \Illuminate\Support\Facades\Lang::has('news.categories.'.$homepageNewsItem->category) ? __('news.categories.'.$homepageNewsItem->category) : $homepageNewsItem->category }}</span>
              <time datetime="{{ $homepageNewsItem->publish_at?->toIso8601String() }}">
                {{ $homepageNewsItem->publish_at?->format('d.m.Y') }}
              </time>
            </div>
            <h3>
              <a href="{{ $withLang('/news/'.$homepageNewsItem->slug) }}">{{ $homepageNewsItem->title }}</a>
            </h3>
            <p>
              {{ $homepageNewsItem->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($homepageNewsItem->body), 180) }}
            </p>
            <a class="homepage-managed-news__read" href="{{ $withLang('/news/'.$homepageNewsItem->slug) }}">
              {{ __('common.actions.view_details') }}
              <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </a>
          </div>
        </article>
      @endforeach
    </div>
  </section>

  <style>
    .homepage-managed-news__grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
    }
    .homepage-managed-news__card {
      min-width: 0;
      overflow: hidden;
      border: 1px solid var(--hs-line);
      background: var(--hs-paper);
    }
    .homepage-managed-news__cover {
      display: block;
      aspect-ratio: 16 / 9;
      overflow: hidden;
      background: #eef3f3;
    }
    .homepage-managed-news__cover img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform .25s ease;
    }
    .homepage-managed-news__cover:hover img {
      transform: scale(1.025);
    }
    .homepage-managed-news__body {
      padding: 24px;
    }
    .homepage-managed-news__meta {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      color: var(--hs-teal-deep);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .homepage-managed-news__body h3 {
      margin: 16px 0 10px;
      color: var(--hs-navy);
      font-family: var(--font-display);
      font-size: clamp(22px, 2.1vw, 30px);
      line-height: 1.08;
    }
    .homepage-managed-news__body p {
      color: #526173;
      font-size: 14px;
      line-height: 1.65;
    }
    .homepage-managed-news__read {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      margin-top: 18px;
      color: var(--hs-teal-deep);
      font-size: 12px;
      font-weight: 800;
    }
    .homepage-managed-news__read .material-symbols-outlined {
      font-size: 17px;
    }
    @media (max-width: 900px) {
      .homepage-managed-news__grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
@endif
