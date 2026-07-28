@extends('layouts.public', ['activePage' => 'shortlist'])

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $withLang = function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }

      $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));
      return $path . ($queryString !== '' ? ('?' . $queryString) : '');
  };

  $copy = [
      'ru' => [
          'title' => 'Исследовательская подборка — Digital Library',
          'hero_title' => 'Исследовательская подборка',
          'hero_body' => 'Подборка академических материалов для активных рабочих сессий. Сохраняйте источники, организуйте цитирование и готовьте проектные библиографии в спокойной исследовательской среде.',
          'loading' => 'Загрузка исследовательской подборки...',
          'empty_title' => 'Подборка пока пуста',
          'empty_body' => 'Добавьте книги из каталога и электронные ресурсы, чтобы собрать рабочий список для курса, темы или исследовательской задачи.',
          'open_catalog' => 'Вернуться в каталог',
          'resources' => 'Изучить ресурсы',
          'browse_subjects' => 'Открыть направления',
          'back_to_account' => '← Вернуться в кабинет',
          'draft_title_label' => 'Рабочее название',
          'draft_title_placeholder' => 'Например: Подборка для дисциплины «Информатика»',
          'draft_notes_label' => 'Заметки исследователя',
          'draft_notes_placeholder' => 'Семестр, группа, комментарии или заметка для себя...',
          'summary_title' => 'Сводка подборки',
          'total_label' => 'Всего записей',
          'digital_label' => 'Цифровые',
          'physical_label' => 'Печатные',
          'smart_export_title' => 'Умный экспорт',
          'smart_export_body' => 'Сформируйте библиографический список для всех сохранённых материалов и перенесите его в рабочий документ или силлабус.',
          'format' => 'Формат',
          'format_numbered' => 'Нумерованный',
          'format_grouped' => 'По разделам',
          'format_syllabus' => 'Для силлабуса',
          'copy_text' => 'Экспорт списка цитирования',
          'print' => 'Печать',
          'clear' => 'Очистить подборку',
          'librarian_note_title' => 'Заметка библиотекаря',
          'librarian_note' => '«Сохранённые материалы синхронизируются между устройствами. Для печатных экземпляров лучше оформлять резерв не позднее чем за сутки до визита в кампус.»',
          'continue_title' => 'Продолжить поиск',
          'continue_body' => 'Готовы найти больше материалов для исследования? Просмотрите каталог и подключённые академические ресурсы.',
      ],
      'kk' => [
          'title' => 'Зерттеу іріктемесі — Digital Library',
          'hero_title' => 'Зерттеу іріктемесі',
          'hero_body' => 'Белсенді жұмыс сессияларына арналған академиялық материалдар жинағы. Дереккөздерді сақтап, дәйексөздерді реттеп, жоба библиографиясын тыныш зерттеу кеңістігінде дайындаңыз.',
          'loading' => 'Зерттеу іріктемесі жүктелуде...',
          'empty_title' => 'Іріктеме әзірге бос',
          'empty_body' => 'Курсқа, тақырыпқа немесе зерттеу міндетіне арналған жұмыс тізімін жинау үшін каталогтан кітаптар мен электрондық ресурстарды қосыңыз.',
          'open_catalog' => 'Каталогқа оралу',
          'resources' => 'Ресурстарды ашу',
          'browse_subjects' => 'Бағыттарды қарау',
          'back_to_account' => '← Кабинетке оралу',
          'draft_title_label' => 'Жұмыс атауы',
          'draft_title_placeholder' => 'Мысалы: «Информатика» пәніне арналған іріктеме',
          'draft_notes_label' => 'Зерттеуші ескертпесі',
          'draft_notes_placeholder' => 'Семестр, топ, түсініктеме немесе өзіңізге белгі...',
          'summary_title' => 'Іріктеме жиынтығы',
          'total_label' => 'Барлығы',
          'digital_label' => 'Цифрлық',
          'physical_label' => 'Баспа',
          'smart_export_title' => 'Смарт экспорт',
          'smart_export_body' => 'Барлық сақталған материалдар бойынша библиографиялық тізім дайындап, оны силлабусқа немесе жұмыс құжатына көшіріңіз.',
          'format' => 'Пішім',
          'format_numbered' => 'Нөмірленген',
          'format_grouped' => 'Бөлімдер бойынша',
          'format_syllabus' => 'Силлабусқа',
          'copy_text' => 'Дәйексөздер тізімін экспорттау',
          'print' => 'Басып шығару',
          'clear' => 'Іріктемені тазарту',
          'librarian_note_title' => 'Кітапханашы ескертпесі',
          'librarian_note' => '«Сақталған материалдар құрылғылар арасында синхрондалады. Баспа даналарын кампусқа келерден кемінде бір күн бұрын брондаған дұрыс.»',
          'continue_title' => 'Іздеуді жалғастыру',
          'continue_body' => 'Зерттеу үшін тағы материалдар керек пе? Каталог пен қосылған академиялық ресурстарды қараңыз.',
      ],
      'en' => [
          'title' => 'Research Shortlist — Digital Library',
          'hero_title' => 'Research Shortlist',
          'hero_body' => 'A curated set of academic materials for active working sessions. Save sources, organize citations, and prepare project bibliographies in a focused scholarly environment.',
          'loading' => 'Loading research shortlist...',
          'empty_title' => 'The shortlist is empty',
          'empty_body' => 'Add catalog books and electronic resources to build a working list for a course, topic, or research task.',
          'open_catalog' => 'Return to Catalog',
          'resources' => 'Explore Resources',
          'browse_subjects' => 'Browse Disciplines',
          'back_to_account' => '← Return to account',
          'draft_title_label' => 'Working title',
          'draft_title_placeholder' => 'For example: Reading list for “Computer Science”',
          'draft_notes_label' => 'Research notes',
          'draft_notes_placeholder' => 'Semester, group, comments, or a note for yourself...',
          'summary_title' => 'Shortlist Summary',
          'total_label' => 'Total Items',
          'digital_label' => 'Digital',
          'physical_label' => 'Physical',
          'smart_export_title' => 'Smart Export',
          'smart_export_body' => 'Generate a bibliography for all saved materials and move it into your working document or syllabus.',
          'format' => 'Format',
          'format_numbered' => 'Numbered',
          'format_grouped' => 'Grouped',
          'format_syllabus' => 'Syllabus',
          'copy_text' => 'Export Citation List',
          'print' => 'Print',
          'clear' => 'Clear shortlist',
          'librarian_note_title' => 'Librarian’s Note',
          'librarian_note' => '“Your saved items sync across devices. For physical copies, reserve them at least one day before your campus visit.”',
          'continue_title' => 'Continue your discovery',
          'continue_body' => 'Ready to find more materials for your research? Continue through the catalog and connected academic resources.',
      ],
  ][$lang];
@endphp

@section('title', $copy['title'])

@section('content')
  <section class="research-shortlist-page" data-shortlist-page>
    <div class="shortlist-shell">
      <header class="research-shortlist-hero" data-shortlist-hero>
        <h1>{{ $copy['hero_title'] }}</h1>
        <p>{{ $copy['hero_body'] }}</p>
      </header>

      <div id="shortlist-loading" class="shortlist-state shortlist-state--loading">
        <div class="shortlist-spinner" aria-hidden="true"></div>
        <p>{{ $copy['loading'] }}</p>
      </div>

      <div id="shortlist-empty" class="shortlist-state shortlist-state--empty" style="display:none;">
        <h2>{{ $copy['empty_title'] }}</h2>
        <p>{{ $copy['empty_body'] }}</p>
        <div class="shortlist-state-actions">
          <a href="{{ $withLang('/catalog') }}" class="shortlist-btn shortlist-btn--light">{{ $copy['open_catalog'] }}</a>
          <a href="{{ $withLang('/resources') }}" class="shortlist-btn shortlist-btn--dark">{{ $copy['resources'] }}</a>
          <a href="{{ $withLang('/discover') }}" class="shortlist-btn shortlist-btn--ghost">{{ $copy['browse_subjects'] }}</a>
        </div>
      </div>

      <div id="shortlist-content" style="display:none;">
        <div class="research-shortlist-layout">
          <div class="research-shortlist-list-column" data-shortlist-items>
            @if(session('library.user'))
              <div class="account-return-link-wrap">
                <a href="{{ $withLang('/dashboard') }}" class="account-return-link">{{ $copy['back_to_account'] }}</a>
              </div>
            @endif

            <div id="draft-meta-block" class="working-draft-card">
              <div class="working-draft-toolbar">
                <div id="draft-persistence-badge" class="draft-persistence-badge" style="display:none;"></div>
                <button type="button" class="draft-clear-link" onclick="clearShortlist()">{{ $copy['clear'] }}</button>
              </div>
              <div class="working-draft-fields">
                <div class="draft-field-group">
                  <label for="draft-title" class="draft-label">{{ $copy['draft_title_label'] }}</label>
                  <input type="text" id="draft-title" class="draft-input" placeholder="{{ $copy['draft_title_placeholder'] }}" maxlength="500">
                </div>
                <div class="draft-field-group">
                  <label for="draft-notes" class="draft-label">{{ $copy['draft_notes_label'] }}</label>
                  <textarea id="draft-notes" class="draft-textarea" placeholder="{{ $copy['draft_notes_placeholder'] }}" maxlength="2000" rows="2"></textarea>
                </div>
              </div>
              <div id="draft-save-status" class="draft-save-status"></div>
            </div>

            <div id="shortlist-items-list" class="shortlist-item-stack"></div>
          </div>

          <aside class="research-shortlist-sidebar" data-shortlist-sidebar>
            <div class="shortlist-summary-card">
              <h3>{{ $copy['summary_title'] }}</h3>
              <div class="summary-metric-row">
                <span>{{ $copy['total_label'] }}</span>
                <strong id="shortlist-summary-total">00</strong>
              </div>
              <div class="summary-metric-row">
                <span>{{ $copy['digital_label'] }}</span>
                <strong id="shortlist-summary-digital">00</strong>
              </div>
              <div class="summary-metric-row">
                <span>{{ $copy['physical_label'] }}</span>
                <strong id="shortlist-summary-physical">00</strong>
              </div>
            </div>

            <div class="smart-export-card" data-smart-export>
              <span class="material-symbols-outlined smart-export-icon" aria-hidden="true">auto_awesome</span>
              <h4>{{ $copy['smart_export_title'] }}</h4>
              <p>{{ $copy['smart_export_body'] }}</p>
              <div class="bibliography-format-controls">
                <label class="bibliography-format-label" for="bib-format">{{ $copy['format'] }}</label>
                <select id="bib-format" class="bibliography-format-select" onchange="loadExport()">
                  <option value="numbered">{{ $copy['format_numbered'] }}</option>
                  <option value="grouped" selected>{{ $copy['format_grouped'] }}</option>
                  <option value="syllabus">{{ $copy['format_syllabus'] }}</option>
                </select>
              </div>
              <div id="bibliography-text" class="bibliography-preview"></div>
              <div class="smart-export-actions">
                <button class="shortlist-btn shortlist-btn--teal" onclick="copyBibliography()" id="copy-bib-btn">{{ $copy['copy_text'] }}</button>
                <button class="shortlist-btn shortlist-btn--ghost" onclick="window.print()">{{ $copy['print'] }}</button>
              </div>
            </div>

            <div class="librarian-note-card">
              <h4>{{ $copy['librarian_note_title'] }}</h4>
              <p>{{ $copy['librarian_note'] }}</p>
            </div>
          </aside>
        </div>

        <section class="research-shortlist-bridge" data-shortlist-bridge>
          <div>
            <h3>{{ $copy['continue_title'] }}</h3>
            <p>{{ $copy['continue_body'] }}</p>
          </div>
          <div class="bridge-actions">
            <a href="{{ $withLang('/catalog') }}" class="shortlist-btn shortlist-btn--light">{{ $copy['open_catalog'] }}</a>
            <a href="{{ $withLang('/resources') }}" class="shortlist-btn shortlist-btn--dark">{{ $copy['resources'] }}</a>
          </div>
        </section>
      </div>
    </div>
  </section>
@endsection

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
  .research-shortlist-page {
    background: #f8f9fa;
  }

  .shortlist-shell {
    max-width: 1440px;
    margin: 0 auto;
    padding: 3rem 1.25rem 5rem;
  }

  .research-shortlist-hero {
    max-width: 52rem;
    margin-bottom: 3rem;
  }

  .research-shortlist-hero h1 {
    margin: 0 0 1rem;
    font-family: 'Newsreader', serif;
    font-size: clamp(2.8rem, 5vw, 4.2rem);
    line-height: .96;
    letter-spacing: -.03em;
    color: #000613;
  }

  .research-shortlist-hero p {
    margin: 0;
    max-width: 44rem;
    font-size: 1.1rem;
    line-height: 1.7;
    color: #43474e;
  }

  .research-shortlist-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: 2rem;
    align-items: start;
  }

  .research-shortlist-list-column,
  .research-shortlist-sidebar {
    min-width: 0;
  }

  .account-return-link-wrap {
    margin-bottom: 1rem;
  }

  .account-return-link {
    color: #001f3f;
    font-size: .95rem;
    font-weight: 700;
    text-decoration: none;
  }

  .working-draft-card {
    margin-bottom: 1.5rem;
    padding: 1rem 1.1rem;
    border-radius: 1rem;
    background: #f3f4f5;
  }

  .working-draft-toolbar {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    align-items: center;
    margin-bottom: .75rem;
    flex-wrap: wrap;
  }

  .working-draft-fields {
    display: grid;
    gap: .75rem;
  }

  .draft-field-group {
    display: flex;
    flex-direction: column;
    gap: .3rem;
  }

  .draft-label {
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #5f6368;
  }

  .draft-input,
  .draft-textarea {
    width: 100%;
    padding: .7rem 0;
    border: 0;
    border-bottom: 1px solid rgba(116,119,127,.35);
    background: transparent;
    font-size: .95rem;
    color: #191c1d;
    resize: vertical;
  }

  .draft-input:focus,
  .draft-textarea:focus {
    outline: none;
    border-bottom-color: #006a6a;
  }

  .draft-save-status {
    min-height: 1rem;
    margin-top: .35rem;
    font-size: .75rem;
    color: #5f6368;
  }

  .draft-persistence-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .3rem .65rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .04em;
  }

  .draft-persistence-badge.persistent {
    background: rgba(0,106,106,.1);
    color: #006a6a;
  }

  .draft-persistence-badge.session-only {
    background: rgba(0,31,63,.08);
    color: #001f3f;
  }

  .draft-clear-link {
    border: 0;
    background: transparent;
    color: #ba1a1a;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
  }

  .shortlist-item-stack {
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
  }

  .shortlist-card {
    background: #ffffff;
    border-radius: 1rem;
    padding: 1.75rem;
    transition: background .35s ease, transform .35s ease, box-shadow .35s ease;
  }

  .shortlist-card:hover {
    background: #eceeef;
    transform: translateY(-2px);
    box-shadow: 0 18px 36px rgba(0, 6, 19, .04);
  }

  .shortlist-card-inner {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1fr);
    gap: 1.25rem;
    align-items: start;
  }

  .shortlist-cover {
    width: 5.5rem;
    height: 7.75rem;
    border-radius: .35rem;
    overflow: hidden;
    display: flex;
    align-items: end;
    justify-content: start;
    padding: .7rem;
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
  }

  .shortlist-cover.is-book {
    background: linear-gradient(180deg, #24364f 0%, #102038 100%);
  }

  .shortlist-cover.is-external {
    background: linear-gradient(180deg, #0e5f67 0%, #09333d 100%);
  }

  .shortlist-card-top {
    display: flex;
    justify-content: space-between;
    gap: .75rem;
    align-items: start;
    margin-bottom: .35rem;
    flex-wrap: wrap;
  }

  .shortlist-type-label {
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #006a6a;
  }

  .shortlist-type-label.is-book {
    color: #5f6368;
  }

  .shortlist-added-badge {
    padding: .18rem .4rem;
    border-radius: .3rem;
    background: #f3f4f5;
    color: #6d7278;
    font-size: .7rem;
  }

  .shortlist-card-title {
    margin: 0 0 .25rem;
    font-family: 'Newsreader', serif;
    font-size: clamp(1.6rem, 2.7vw, 2.35rem);
    line-height: 1.05;
    color: #000613;
  }

  .shortlist-card-title a {
    color: inherit;
    text-decoration: none;
  }

  .shortlist-card-meta {
    margin: 0 0 .85rem;
    color: #43474e;
    font-size: .92rem;
  }

  .shortlist-card-meta strong {
    color: #000613;
    font-weight: 800;
  }

  .shortlist-card-snippet {
    margin: 0 0 1.15rem;
    max-width: 42rem;
    color: #43474e;
    line-height: 1.7;
  }

  .shortlist-card-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }

  .shortlist-status {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .88rem;
    font-weight: 700;
    color: #006a6a;
  }

  .shortlist-status.is-book {
    color: #191c1d;
  }

  .shortlist-status .material-symbols-outlined {
    font-size: 1rem;
  }

  .shortlist-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
  }

  .shortlist-action {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .55rem .8rem;
    border: 0;
    border-radius: .5rem;
    background: transparent;
    color: #191c1d;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: background .2s ease, color .2s ease, opacity .2s ease;
  }

  .shortlist-action:hover {
    background: rgba(0, 6, 19, .05);
  }

  .shortlist-action--dark {
    background: #000613;
    color: #ffffff;
  }

  .shortlist-action--danger {
    color: #ba1a1a;
  }

  .research-shortlist-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  .shortlist-summary-card,
  .librarian-note-card {
    padding: 1.5rem;
    border-radius: 1rem;
    background: #f3f4f5;
  }

  .shortlist-summary-card {
    border-left: 2px solid #000613;
  }

  .shortlist-summary-card h3,
  .smart-export-card h4 {
    margin: 0 0 1rem;
    font-family: 'Newsreader', serif;
    font-size: 1.45rem;
    color: #000613;
  }

  .summary-metric-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .8rem 0;
    color: #5f6368;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
  }

  .summary-metric-row strong {
    color: #000613;
    font-family: 'Newsreader', serif;
    font-size: 1.8rem;
    font-weight: 500;
    letter-spacing: normal;
  }

  .summary-metric-row + .summary-metric-row {
    border-top: 1px solid rgba(116,119,127,.16);
  }

  .smart-export-card {
    padding: 1.5rem;
    border-radius: 1rem;
    background: linear-gradient(180deg, #001f3f 0%, #000613 100%);
    color: #d7e5f7;
  }

  .smart-export-card h4 {
    color: #ffffff;
    margin-bottom: .45rem;
  }

  .smart-export-card p {
    margin: 0 0 1rem;
    font-size: .9rem;
    line-height: 1.6;
  }

  .smart-export-icon {
    display: inline-block;
    margin-bottom: .5rem;
    color: #afc8f0;
    font-size: 1.8rem;
  }

  .bibliography-format-controls {
    display: grid;
    gap: .35rem;
    margin-bottom: .85rem;
  }

  .bibliography-format-label {
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #afc8f0;
  }

  .bibliography-format-select {
    width: 100%;
    padding: .65rem .8rem;
    border: 1px solid rgba(175,200,240,.22);
    border-radius: .5rem;
    background: rgba(255,255,255,.06);
    color: #ffffff;
  }

  .bibliography-preview {
    min-height: 7rem;
    padding: .85rem;
    border-radius: .6rem;
    background: rgba(255,255,255,.06);
    color: #eef4fb;
    font-size: .82rem;
    line-height: 1.65;
    white-space: pre-wrap;
  }

  .smart-export-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .55rem;
    margin-top: 1rem;
  }

  .librarian-note-card h4 {
    margin: 0 0 .75rem;
    color: #6d7278;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
  }

  .librarian-note-card p {
    margin: 0;
    font-family: 'Newsreader', serif;
    font-size: 1rem;
    line-height: 1.65;
    font-style: italic;
    color: #5f6368;
  }

  .research-shortlist-bridge {
    margin-top: 4rem;
    padding: 2rem;
    border-top: 1px solid rgba(116,119,127,.14);
    background: #f3f4f5;
    border-radius: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .research-shortlist-bridge h3 {
    margin: 0 0 .4rem;
    font-family: 'Newsreader', serif;
    font-size: clamp(1.8rem, 2.8vw, 2.4rem);
    color: #000613;
  }

  .research-shortlist-bridge p {
    margin: 0;
    max-width: 38rem;
    color: #43474e;
  }

  .bridge-actions,
  .shortlist-state-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
  }

  .shortlist-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: .9rem 1.1rem;
    border-radius: .55rem;
    border: 0;
    text-decoration: none;
    font-size: .84rem;
    font-weight: 800;
    cursor: pointer;
    transition: opacity .2s ease, background .2s ease;
  }

  .shortlist-btn--light {
    background: #ffffff;
    color: #000613;
  }

  .shortlist-btn--dark {
    background: #000613;
    color: #ffffff;
  }

  .shortlist-btn--ghost {
    background: transparent;
    color: #001f3f;
  }

  .shortlist-btn--teal {
    width: 100%;
    background: #006a6a;
    color: #ffffff;
  }

  .shortlist-state {
    padding: 3rem 1.25rem;
    text-align: center;
    border-radius: 1rem;
    background: #ffffff;
  }

  .shortlist-state h2 {
    margin: 0 0 .75rem;
    font-family: 'Newsreader', serif;
    font-size: 2rem;
    color: #000613;
  }

  .shortlist-state p {
    max-width: 34rem;
    margin: 0 auto 1rem;
    color: #43474e;
    line-height: 1.7;
  }

  .shortlist-spinner {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    border: 3px solid rgba(0,31,63,.14);
    border-top-color: #006a6a;
    animation: spin .7s linear infinite;
  }

  @keyframes spin { to { transform: rotate(360deg); } }

  @media (max-width: 1024px) {
    .research-shortlist-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 680px) {
    .shortlist-shell {
      padding-inline: 1rem;
    }

    .shortlist-card {
      padding: 1rem;
    }

    .shortlist-card-inner {
      grid-template-columns: 1fr;
    }

    .shortlist-cover {
      width: 100%;
      max-width: 6rem;
    }

    .shortlist-card-footer,
    .research-shortlist-bridge {
      flex-direction: column;
      align-items: stretch;
    }

    .shortlist-card-actions,
    .bridge-actions,
    .shortlist-state-actions,
    .smart-export-actions {
      flex-direction: column;
    }

    .shortlist-action,
    .shortlist-btn {
      width: 100%;
      justify-content: center;
    }
  }

  @media print {
    .research-shortlist-hero,
    .working-draft-card,
    .research-shortlist-bridge,
    .research-shortlist-sidebar,
    .shortlist-action,
    .shortlist-btn,
    .account-return-link-wrap {
      display: none !important;
    }

    .research-shortlist-layout {
      display: block;
    }

    .shortlist-card {
      background: #fff;
      box-shadow: none;
      break-inside: avoid;
    }
  }
</style>
@endsection

@section('scripts')
<script>
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;
  const API_BASE = '/api/v1/shortlist';
  const SHORTLIST_LANG = @json($lang);
  const SHORTLIST_I18N_MAP = {!! json_encode([
    'ru' => [
      'untitled' => 'Без названия',
      'electronic' => 'Электронный ресурс',
      'physical' => 'Печатный экземпляр',
      'viewDetails' => 'Подробнее',
      'cite' => 'Цитата',
      'reserve' => 'Резерв',
      'openResource' => 'Открыть ресурс',
      'remove' => 'Убрать',
      'campus' => 'Только в кампусе',
      'remote_auth' => 'Доступ по авторизации',
      'open' => 'Открытый доступ',
      'loadFailed' => 'Не удалось загрузить подборку',
      'exportFailed' => 'Не удалось подготовить экспорт',
      'clearConfirm' => 'Очистить всю подборку?',
      'copied' => '✓ Скопировано',
      'itemCopied' => '✓ Цитата скопирована',
      'savedToAccount' => 'Синхронизируется с аккаунтом',
      'sessionOnly' => 'Только в текущей сессии',
      'saving' => 'Сохранение...',
      'saved' => '✓ Сохранено',
      'saveError' => 'Ошибка сохранения',
      'networkError' => 'Ошибка сети',
      'instantAccess' => 'Мгновенный цифровой доступ',
      'catalogStatus' => 'Проверить наличие в каталоге',
      'citationFallback' => 'Библиографический текст появится здесь после загрузки списка.',
      'addedPrefix' => 'Добавлено',
      'addedRecently' => 'Добавлено недавно',
      'addedYesterday' => 'Добавлено вчера',
      'bookSnippet' => 'Печатное издание доступно для проверки по каталожной записи, резервирования и последующего цитирования.',
      'externalSnippet' => 'Электронный источник подключён к рабочей подборке и готов для перехода и цитирования.',
    ],
    'kk' => [
      'untitled' => 'Атауы жоқ',
      'electronic' => 'Электрондық ресурс',
      'physical' => 'Баспа данасы',
      'viewDetails' => 'Толығырақ',
      'cite' => 'Дәйексөз',
      'reserve' => 'Брондау',
      'openResource' => 'Ресурсты ашу',
      'remove' => 'Алып тастау',
      'campus' => 'Тек кампуста',
      'remote_auth' => 'Авторизация арқылы',
      'open' => 'Ашық қолжетімділік',
      'loadFailed' => 'Іріктемені жүктеу мүмкін болмады',
      'exportFailed' => 'Экспортты дайындау мүмкін болмады',
      'clearConfirm' => 'Бүкіл іріктемені тазарту керек пе?',
      'copied' => '✓ Көшірілді',
      'itemCopied' => '✓ Дәйексөз көшірілді',
      'savedToAccount' => 'Аккаунтпен синхрондалады',
      'sessionOnly' => 'Тек ағымдағы сессияда',
      'saving' => 'Сақталуда...',
      'saved' => '✓ Сақталды',
      'saveError' => 'Сақтау қатесі',
      'networkError' => 'Желі қатесі',
      'instantAccess' => 'Лезде цифрлық қолжетімділік',
      'catalogStatus' => 'Қолжетімділікті каталогтан тексеру',
      'citationFallback' => 'Библиографиялық мәтін тізім жүктелгеннен кейін осында шығады.',
      'addedPrefix' => 'Қосылды',
      'addedRecently' => 'Жақында қосылды',
      'addedYesterday' => 'Кеше қосылды',
      'bookSnippet' => 'Баспа басылымын каталог жазбасы арқылы тексеруге, брондауға және дәйексөзге енгізуге болады.',
      'externalSnippet' => 'Электрондық дереккөз жұмыс іріктемесіне қосылған және ашуға, дәйексөздеуге дайын.',
    ],
    'en' => [
      'untitled' => 'Untitled',
      'electronic' => 'Electronic Resource',
      'physical' => 'Physical Copy',
      'viewDetails' => 'View Details',
      'cite' => 'Cite',
      'reserve' => 'Reserve',
      'openResource' => 'Open Resource',
      'remove' => 'Remove',
      'campus' => 'Campus only',
      'remote_auth' => 'Authenticated access',
      'open' => 'Open access',
      'loadFailed' => 'Unable to load the shortlist',
      'exportFailed' => 'Unable to prepare the export',
      'clearConfirm' => 'Clear the entire shortlist?',
      'copied' => '✓ Copied',
      'itemCopied' => '✓ Citation copied',
      'savedToAccount' => 'Synced to account',
      'sessionOnly' => 'Session only',
      'saving' => 'Saving...',
      'saved' => '✓ Saved',
      'saveError' => 'Save failed',
      'networkError' => 'Network error',
      'instantAccess' => 'Instant Digital Access',
      'catalogStatus' => 'Check live catalog availability',
      'citationFallback' => 'Bibliography text will appear here after the shortlist loads.',
      'addedPrefix' => 'Added',
      'addedRecently' => 'Added recently',
      'addedYesterday' => 'Added yesterday',
      'bookSnippet' => 'This print title is ready for live catalog review, reservation, and citation export.',
      'externalSnippet' => 'This electronic source is attached to your working shortlist and ready for access and citation.',
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
  const SHORTLIST_I18N = SHORTLIST_I18N_MAP[SHORTLIST_LANG] || SHORTLIST_I18N_MAP.ru;
  const LOCALE_MAP = { ru: 'ru-RU', kk: 'kk-KZ', en: 'en-US' };

  let currentItems = [];
  let draftSaveTimer = null;

  function withLang(path) {
    const url = new URL(path, window.location.origin);
    if (SHORTLIST_LANG !== 'ru' && !url.searchParams.has('lang')) {
      url.searchParams.set('lang', SHORTLIST_LANG);
    }
    return url.pathname + url.search;
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  function padCount(value) {
    return String(value ?? 0).padStart(2, '0');
  }

  function formatAddedLabel(addedAt) {
    if (!addedAt) return SHORTLIST_I18N.addedRecently;

    const date = new Date(addedAt);
    if (Number.isNaN(date.getTime())) return SHORTLIST_I18N.addedRecently;

    const diffHours = Math.abs(Date.now() - date.getTime()) / 36e5;
    if (diffHours < 36) return SHORTLIST_I18N.addedYesterday;

    return `${SHORTLIST_I18N.addedPrefix} ${date.toLocaleDateString(LOCALE_MAP[SHORTLIST_LANG] || 'en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })}`;
  }

  function buildCitationText(item, index = null) {
    const parts = [];
    if (item.author) parts.push(item.author);
    parts.push(item.title || SHORTLIST_I18N.untitled);
    if (item.publisher) parts.push(item.publisher);
    if (item.year) parts.push(item.year);

    const body = `${parts.filter(Boolean).join('. ')}.`;
    return index === null ? body : `${index + 1}. ${body}`;
  }

  function getSnippet(item) {
    return item.type === 'external_resource'
      ? SHORTLIST_I18N.externalSnippet
      : SHORTLIST_I18N.bookSnippet;
  }

  function getStatusText(item) {
    if (item.type === 'external_resource') {
      return SHORTLIST_I18N[item.access_type] || SHORTLIST_I18N.instantAccess;
    }

    if (typeof item.available === 'number' && typeof item.total === 'number') {
      return `${SHORTLIST_I18N.catalogStatus} · ${item.available}/${item.total}`;
    }

    return SHORTLIST_I18N.catalogStatus;
  }

  function renderItemCard(item, index) {
    const identifier = item.identifier || '';
    const isExternal = item.type === 'external_resource';
    const detailHref = isExternal && item.url ? item.url : withLang(`/book/${encodeURIComponent(identifier)}`);
    const detailTarget = isExternal && item.url ? ' target="_blank" rel="noopener"' : '';
    const leadMeta = item.author || item.provider || '';
    const trailingMeta = [item.year, item.publisher].filter(Boolean).join(' • ');
    const metaMarkup = leadMeta
      ? `<strong>${escapeHtml(leadMeta)}</strong>${trailingMeta ? ` • ${escapeHtml(trailingMeta)}` : ''}`
      : escapeHtml(trailingMeta);

    return `
      <article class="shortlist-card" data-shortlist-item>
        <div class="shortlist-card-inner">
          <div class="shortlist-cover ${isExternal ? 'is-external' : 'is-book'}">${isExternal ? 'Digital' : 'Print'}</div>
          <div>
            <div class="shortlist-card-top">
              <span class="shortlist-type-label ${isExternal ? '' : 'is-book'}">${isExternal ? SHORTLIST_I18N.electronic : SHORTLIST_I18N.physical}</span>
              <span class="shortlist-added-badge">${escapeHtml(formatAddedLabel(item.addedAt))}</span>
            </div>
            <h2 class="shortlist-card-title"><a href="${detailHref}"${detailTarget}>${escapeHtml(item.title || SHORTLIST_I18N.untitled)}</a></h2>
            <p class="shortlist-card-meta">${metaMarkup}</p>
            <p class="shortlist-card-snippet">${escapeHtml(getSnippet(item))}</p>
            <div class="shortlist-card-footer">
              <div class="shortlist-status ${isExternal ? '' : 'is-book'}">
                <span class="material-symbols-outlined">${isExternal ? 'check_circle' : 'location_on'}</span>
                ${escapeHtml(getStatusText(item))}
              </div>
              <div class="shortlist-card-actions">
                <a class="shortlist-action" href="${detailHref}"${detailTarget}><span class="material-symbols-outlined">visibility</span>${SHORTLIST_I18N.viewDetails}</a>
                <button class="shortlist-action" type="button" onclick='copyItemCitation(${JSON.stringify(identifier)})'><span class="material-symbols-outlined">format_quote</span>${SHORTLIST_I18N.cite}</button>
                <a class="shortlist-action ${isExternal ? '' : 'shortlist-action--dark'}" href="${detailHref}"${detailTarget}><span class="material-symbols-outlined">${isExternal ? 'open_in_new' : 'bookmark'}</span>${isExternal ? SHORTLIST_I18N.openResource : SHORTLIST_I18N.reserve}</a>
                <button class="shortlist-action shortlist-action--danger" type="button" onclick='removeItem(${JSON.stringify(identifier)})'><span class="material-symbols-outlined">delete</span>${SHORTLIST_I18N.remove}</button>
              </div>
            </div>
          </div>
        </div>
      </article>
    `;
  }

  async function loadShortlist() {
    const loading = document.getElementById('shortlist-loading');
    const empty = document.getElementById('shortlist-empty');
    const content = document.getElementById('shortlist-content');

    try {
      const res = await fetch(API_BASE, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!res.ok) throw new Error(SHORTLIST_I18N.loadFailed);

      const json = await res.json();
      const items = (Array.isArray(json.data) ? json.data : []).slice().sort((a, b) => {
        const left = a.addedAt ? new Date(a.addedAt).getTime() : 0;
        const right = b.addedAt ? new Date(b.addedAt).getTime() : 0;
        return right - left;
      });

      currentItems = items;
      loading.style.display = 'none';

      if (items.length === 0) {
        empty.style.display = 'block';
        content.style.display = 'none';
        return;
      }

      empty.style.display = 'none';
      content.style.display = 'block';

      const digital = items.filter((item) => item.type === 'external_resource').length;
      const physical = items.length - digital;

      document.getElementById('shortlist-summary-total').textContent = padCount(items.length);
      document.getElementById('shortlist-summary-digital').textContent = padCount(digital);
      document.getElementById('shortlist-summary-physical').textContent = padCount(physical);

      document.getElementById('shortlist-items-list').innerHTML = items.map(renderItemCard).join('');
      loadExport();
    } catch (err) {
      loading.style.display = 'none';
      empty.style.display = 'block';
      console.error('Shortlist load error:', err);
    }
  }

  async function loadExport() {
    const format = document.getElementById('bib-format')?.value || 'grouped';
    const block = document.getElementById('bibliography-text');
    if (!block) return;

    try {
      const res = await fetch(`${API_BASE}/export?format=${encodeURIComponent(format)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!res.ok) throw new Error(SHORTLIST_I18N.exportFailed);
      const json = await res.json();
      block.textContent = json.data?.text || SHORTLIST_I18N.citationFallback;
    } catch (err) {
      block.textContent = currentItems.length
        ? currentItems.map((item, index) => buildCitationText(item, index)).join('\n')
        : SHORTLIST_I18N.citationFallback;
    }
  }

  async function removeItem(identifier) {
    try {
      const res = await fetch(`${API_BASE}/${encodeURIComponent(identifier)}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN,
        },
        credentials: 'same-origin',
      });

      if (res.ok) loadShortlist();
    } catch (err) {
      console.error('Remove error:', err);
    }
  }

  async function clearShortlist() {
    if (!confirm(SHORTLIST_I18N.clearConfirm)) return;

    try {
      await fetch(`${API_BASE}/clear`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN,
        },
        credentials: 'same-origin',
      });
      loadShortlist();
    } catch (err) {
      console.error('Clear error:', err);
    }
  }

  function copyTextWithFeedback(text, button, successLabel) {
    if (!text) return;

    const showCopied = () => {
      if (!button) return;
      const original = button.innerHTML;
      button.innerHTML = successLabel;
      setTimeout(() => { button.innerHTML = original; }, 1800);
    };

    navigator.clipboard.writeText(text).then(showCopied).catch(() => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      showCopied();
    });
  }

  function copyBibliography() {
    const text = document.getElementById('bibliography-text')?.textContent?.trim();
    copyTextWithFeedback(text, document.getElementById('copy-bib-btn'), SHORTLIST_I18N.copied);
  }

  function copyItemCitation(identifier) {
    const item = currentItems.find((entry) => entry.identifier === identifier);
    if (!item) return;

    copyTextWithFeedback(buildCitationText(item), null, SHORTLIST_I18N.itemCopied);
  }

  async function loadDraftMeta() {
    try {
      const res = await fetch(`${API_BASE}/summary`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;

      const json = await res.json();
      const draft = json.data?.draft || {};
      const titleInput = document.getElementById('draft-title');
      const notesInput = document.getElementById('draft-notes');

      if (titleInput && draft.title) titleInput.value = draft.title;
      if (notesInput && draft.notes) notesInput.value = draft.notes;

      const badge = document.getElementById('draft-persistence-badge');
      if (badge) {
        badge.textContent = draft.persistent ? SHORTLIST_I18N.savedToAccount : SHORTLIST_I18N.sessionOnly;
        badge.className = `draft-persistence-badge ${draft.persistent ? 'persistent' : 'session-only'}`;
        badge.style.display = '';
      }
    } catch (err) {
      console.error('Draft meta load error:', err);
    }
  }

  function saveDraftMeta() {
    clearTimeout(draftSaveTimer);
    const statusEl = document.getElementById('draft-save-status');
    if (statusEl) statusEl.textContent = SHORTLIST_I18N.saving;

    draftSaveTimer = setTimeout(async () => {
      const title = document.getElementById('draft-title')?.value || '';
      const notes = document.getElementById('draft-notes')?.value || '';

      try {
        const res = await fetch(`${API_BASE}/draft`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN,
          },
          credentials: 'same-origin',
          body: JSON.stringify({ title: title || null, notes: notes || null }),
        });

        if (statusEl) {
          statusEl.textContent = res.ok ? SHORTLIST_I18N.saved : SHORTLIST_I18N.saveError;
          setTimeout(() => { statusEl.textContent = ''; }, 2500);
        }
      } catch (err) {
        if (statusEl) statusEl.textContent = SHORTLIST_I18N.networkError;
      }
    }, 700);
  }

  document.getElementById('draft-title')?.addEventListener('input', saveDraftMeta);
  document.getElementById('draft-notes')?.addEventListener('input', saveDraftMeta);

  loadShortlist();
  loadDraftMeta();
</script>
@endsection
