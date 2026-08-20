@php
  $repositoryCopy = [
    'ru' => ['kicker' => 'Научный репозиторий', 'title' => 'Последние научные работы', 'lead' => 'Проверенные публикации, диссертации и отчёты университета.', 'all' => 'Все работы', 'restricted' => 'Метаданные открыты', 'empty' => 'Опубликованные работы появятся после проверки и утверждения.'],
    'kk' => ['kicker' => 'Ғылыми репозиторий', 'title' => 'Соңғы ғылыми жұмыстар', 'lead' => 'Университеттің тексерілген жарияланымдары, диссертациялары және есептері.', 'all' => 'Барлық жұмыстар', 'restricted' => 'Метадеректер ашық', 'empty' => 'Жарияланған жұмыстар тексеріліп, бекітілгеннен кейін осында шығады.'],
    'en' => ['kicker' => 'Research repository', 'title' => 'Latest research works', 'lead' => 'Reviewed university publications, dissertations, and reports.', 'all' => 'All works', 'restricted' => 'Public metadata', 'empty' => 'Published works will appear here after review and approval.'],
  ][$lang];
@endphp
@if (($latestRepositoryWorks ?? collect())->isNotEmpty())
<section class="hs hs-section hs-section--fullbleed" data-section="homepage-repository-latest" aria-labelledby="homepage-repository-title">
  <div class="hs-section__inner">
    <header class="hs-head">
      <div class="hs-head__copy">
        <p class="hs-kicker">{{ $repositoryCopy['kicker'] }}</p>
        <h2 class="hs-title" id="homepage-repository-title">{{ $repositoryCopy['title'] }}</h2>
        <p class="hs-lead">{{ $repositoryCopy['lead'] }}</p>
      </div>
      <a class="hs-book__cta" href="{{ $withLang('/repository') }}">{{ $repositoryCopy['all'] }}</a>
    </header>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
      @foreach($latestRepositoryWorks as $work)
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" data-repository-work-id="{{ $work->getKey() }}">
          <p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ __('librarian.repository.work_types.'.$work->work_type) }}@if($work->year) · {{ $work->year }}@endif</p>
          <h3 class="mt-3 font-headline text-xl leading-tight text-primary"><a href="{{ $withLang('/repository/'.$work->getKey()) }}">{{ $work->title }}</a></h3>
          <p class="mt-2 text-sm text-slate-600">{{ collect($work->authors ?? [])->filter()->implode(' · ') }}</p>
          @if($work->access_policy !== 'full_public' || $work->status === 'embargoed')
            <p class="mt-4 text-xs font-semibold text-slate-500"><span class="material-symbols-outlined align-middle text-[16px]" aria-hidden="true">lock</span> {{ $repositoryCopy['restricted'] }}</p>
          @endif
        </article>
      @endforeach
    </div>
  </div>
</section>
@endif
