@extends('layouts.public')

@section('title', 'Новости и события — Digital Library')

@section('head')
<style>
  .news-hero-tabs {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 32px;
  }
  .news-hero-tabs .tab-btn {
    padding: 12px 24px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #fff;
    font: inherit;
    font-size: 15px;
    font-weight: 700;
    color: var(--muted);
    cursor: pointer;
    transition: all .2s;
  }
  .news-hero-tabs .tab-btn:hover { border-color: var(--blue); color: var(--blue); }
  .news-hero-tabs .tab-btn.active {
    background: linear-gradient(135deg, var(--blue), var(--cyan));
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 16px rgba(25,28,29,.04);
  }

  .news-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }

  .news-card {
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: transform .25s, box-shadow .25s;
  }
  .news-card:hover {
    transform: none;
    box-shadow: none;
  }
  .news-card-media {
    height: 200px;
    position: relative;
  }
  .news-card-media .media-gradient {
    position: absolute;
    inset: 0;
    border-radius: 0;
  }
  .news-card-media .media-category {
    position: absolute;
    top: 16px;
    left: 16px;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #fff;
    z-index: 1;
  }
  .news-card-body {
    padding: 24px;
  }
  .news-card-date {
    font-size: 13px;
    color: var(--muted);
    font-weight: 600;
    margin-bottom: 8px;
  }
  .news-card h3 {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.3;
  }
  .news-card p {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.65;
  }

  .featured-news {
    display: grid;
    grid-template-columns: 1.2fr .8fr;
    gap: 24px;
    align-items: stretch;
  }
  .featured-card {
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  .featured-card .fc-media {
    height: 280px;
    position: relative;
  }
  .featured-card .fc-body {
    padding: 28px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  .featured-card h2 {
    margin: 0 0 12px;
    font-size: 28px;
    font-weight: 900;
    letter-spacing: -0.5px;
    line-height: 1.2;
  }
  .featured-card p {
    margin: 0;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.7;
    flex: 1;
  }
  .featured-card .fc-date {
    margin-top: 16px;
    font-size: 13px;
    color: var(--muted);
    font-weight: 600;
  }

  .events-timeline {
    display: grid;
    gap: 16px;
  }
  .event-timeline-item {
    display: grid;
    grid-template-columns: 80px 1fr;
    gap: 20px;
    padding: 24px;
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    transition: transform .2s, box-shadow .2s;
  }
  .event-timeline-item:hover {
    transform: none;
    box-shadow: none;
  }
  .eti-date {
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px;
    text-align: center;
    background: linear-gradient(135deg, rgba(0,30,64,.05), rgba(20,105,109,.07));
    border: 1px solid rgba(0,30,64,.10);
  }
  .eti-date strong {
    display: block;
    font-size: 28px;
    line-height: 1;
    color: var(--text);
  }
  .eti-date span {
    font-size: 13px;
    color: #475569;
    text-transform: uppercase;
    font-weight: 600;
  }
  .eti-content h4 { margin: 0 0 6px; font-size: 18px; font-weight: 700; }
  .eti-content p { margin: 0 0 8px; color: var(--muted); font-size: 15px; line-height: 1.6; }
  .eti-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--muted);
    font-weight: 600;
  }
  .eti-meta span { display: flex; align-items: center; gap: 4px; }

  .announcements-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }
  .announcement-card {
    padding: 28px;
    border-radius: var(--radius-lg);
    border-left: 4px solid var(--blue);
    background: var(--surface-glass);
    border-top: 1px solid var(--border);
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
  }
  .announcement-card h4 { margin: 0 0 8px; font-size: 17px; font-weight: 700; }
  .announcement-card p { margin: 0 0 12px; color: var(--muted); font-size: 15px; line-height: 1.6; }
  .announcement-card .ann-date { font-size: 13px; color: var(--muted); font-weight: 600; }
  .announcement-card:nth-child(2) { border-left-color: #5d4201; }
  .announcement-card:nth-child(3) { border-left-color: var(--green); }

  @media (max-width: 900px) {
    .featured-news { grid-template-columns: 1fr; }
    .news-grid { grid-template-columns: 1fr; }
    .announcements-strip { grid-template-columns: 1fr; }
    .event-timeline-item { grid-template-columns: 70px 1fr; gap: 16px; padding: 20px; }
    .featured-card .fc-body { padding: 22px; }
    .featured-card h2 { font-size: 24px; }
    .news-card-body { padding: 20px; }
    .announcement-card { padding: 22px; }
  }

  @media (max-width: 680px) {
    .news-hero-tabs { gap: 8px; flex-wrap: wrap; }
    .news-hero-tabs .tab-btn { padding: 10px 16px; font-size: 13px; }
    .featured-card .fc-media { height: 200px; }
    .featured-card .fc-body { padding: 18px; }
    .featured-card h2 { font-size: 20px; }
    .featured-card p { font-size: 14px; }
    .news-card-media { height: 160px; }
    .news-card-body { padding: 16px; }
    .news-card h3 { font-size: 17px; }
    .news-card p { font-size: 14px; }
    .event-timeline-item { grid-template-columns: 60px 1fr; gap: 12px; padding: 16px; }
    .eti-date strong { font-size: 22px; }
    .eti-date span { font-size: 11px; }
    .eti-date { padding: 8px; border-radius: 12px; }
    .eti-content h4 { font-size: 16px; }
    .eti-content p { font-size: 14px; }
    .eti-meta { gap: 10px; font-size: 12px; }
    .announcement-card { padding: 18px; }
    .announcement-card h4 { font-size: 15px; }
    .announcement-card p { font-size: 14px; }
    .news-hero-tabs { margin-top: 24px; }
  }

  @media (max-width: 480px) {
    .news-hero-tabs .tab-btn { padding: 8px 12px; font-size: 12px; }
    .featured-card .fc-media { height: 160px; }
    .event-timeline-item { grid-template-columns: 1fr; }
    .eti-date { flex-direction: row; gap: 6px; justify-content: flex-start; padding: 10px 14px; }
    .eti-date strong { font-size: 18px; }
  }
</style>
@endsection

@section('content')
<div class="page-hero">
  <div class="container">
    <div class="eyebrow">Новости и события</div>
    <h1>Жизнь библиотеки</h1>
    <p>Актуальные новости, предстоящие события, объявления и обновления библиотечных сервисов.</p>
  </div>
</div>

<section class="page-section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Главное</h2>
        <p>Ключевые новости и обновления библиотеки.</p>
      </div>
    </div>

    <div class="featured-news">
      <div class="featured-card">
        <div class="fc-media" style="background: linear-gradient(135deg, rgba(0,30,64,.78), rgba(20,105,109,.56)), url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?auto=format&fit=crop&w=1200&q=80') center/cover;"></div>
        <div class="fc-body">
          <h2>Запуск цифровой платформы «Digital Library»</h2>
          <p>Запущена новая цифровая библиотечная платформа с онлайн-каталогом, личным кабинетом, системой бронирования и доступом к электронным ресурсам. Платформа заменяет устаревшие сценарии и формирует современную библиотечную инфраструктуру.</p>
          <div class="fc-date">📅 Апрель 2026</div>
        </div>
      </div>

      <div style="display:grid; gap:24px;">
        <div class="news-card">
          <div class="news-card-media" style="height:140px; background: linear-gradient(135deg, rgba(42,28,0,.72), rgba(93,66,1,.50)), url('https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=600&q=80') center/cover;">
            <span class="media-category" style="background:rgba(42,28,0,.82);">Ресурсы</span>
          </div>
          <div class="news-card-body">
            <div class="news-card-date">28 марта 2026</div>
            <h3>Открыт доступ к новым электронным базам</h3>
            <p>Подключены дополнительные научные базы для исследований и учёбы.</p>
          </div>
        </div>
        <div class="news-card">
          <div class="news-card-media" style="height:140px; background: linear-gradient(135deg, rgba(34,197,94,.7), rgba(6,182,212,.5)), url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=600&q=80') center/cover;">
            <span class="media-category" style="background:rgba(34,197,94,.85);">Фонд</span>
          </div>
          <div class="news-card-body">
            <div class="news-card-date">15 марта 2026</div>
            <h3>Поступили новые книги по IT и бизнесу</h3>
            <p>Обновлён фонд по востребованным направлениям обучения.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="page-section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Предстоящие события</h2>
        <p>Лекции, семинары, тренинги и мероприятия библиотеки.</p>
      </div>
    </div>

    <div class="events-timeline">
      <div class="event-timeline-item">
        <div class="eti-date"><strong>12</strong><span>апр</span></div>
        <div class="eti-content">
          <h4>Презентация новых поступлений</h4>
          <p>Обзор новых учебных и научных изданий по актуальным направлениям подготовки. Открыто для всех студентов и преподавателей.</p>
          <div class="eti-meta"><span>📍 Читальный зал №1</span><span>🕐 14:00</span></div>
        </div>
      </div>
      <div class="event-timeline-item">
        <div class="eti-date"><strong>18</strong><span>апр</span></div>
        <div class="eti-content">
          <h4>Тренинг: поиск научных статей в международных базах</h4>
          <p>Практическое занятие по работе с научными базами данных, индексами цитирования и методами эффективного поиска.</p>
          <div class="eti-meta"><span>📍 Компьютерный зал</span><span>🕐 10:00–12:00</span></div>
        </div>
      </div>
      <div class="event-timeline-item">
        <div class="eti-date"><strong>24</strong><span>апр</span></div>
        <div class="eti-content">
          <h4>Лекция по цифровой грамотности</h4>
          <p>Как быстро находить, хранить и использовать академические источники в учёбе и исследованиях. Практические советы и инструменты.</p>
          <div class="eti-meta"><span>📍 Актовый зал</span><span>🕐 15:00</span></div>
        </div>
      </div>
      <div class="event-timeline-item">
        <div class="eti-date"><strong>3</strong><span>мая</span></div>
        <div class="eti-content">
          <h4>Выставка: «Наука и исследования в публикациях»</h4>
          <p>Выставка научных работ, публикаций и исследовательских материалов академического сообщества за 2025–2026 гг.</p>
          <div class="eti-meta"><span>📍 Холл библиотеки</span><span>🕐 Весь день</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="page-section">
  <div class="container">
    <div class="section-head">
      <div>
        <h2>Объявления</h2>
        <p>Важные обновления и изменения в работе библиотеки.</p>
      </div>
    </div>

    <div class="announcements-strip">
      <div class="announcement-card">
        <h4>Изменён порядок выдачи литературы</h4>
        <p>С 1 апреля введён обновлённый регламент получения и возврата книг. Актуальные правила опубликованы в личном кабинете.</p>
        <div class="ann-date">📅 1 апреля 2026</div>
      </div>
      <div class="announcement-card">
        <h4>Расширены часы работы читального зала</h4>
        <p>В период сессии читальный зал работает до 20:00 в будние дни и с 10:00 до 17:00 в субботу.</p>
        <div class="ann-date">📅 20 марта 2026</div>
      </div>
      <div class="announcement-card">
        <h4>Обновление каталога</h4>
        <p>Завершена индексация нового поступления. В онлайн-каталоге доступны более 2000 новых записей.</p>
        <div class="ann-date">📅 10 марта 2026</div>
      </div>
    </div>
  </div>
</section>

<section class="page-section">
  <div class="container">
    <div class="news-grid">
      <div class="news-card">
        <div class="news-card-media" style="background: linear-gradient(135deg, rgba(0,30,64,.72), rgba(20,105,109,.50)), url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=600&q=80') center/cover;">
          <span class="media-category" style="background:rgba(0,30,64,.82);">Образование</span>
        </div>
        <div class="news-card-body">
          <div class="news-card-date">5 марта 2026</div>
          <h3>Программа информационной грамотности для первокурсников</h3>
          <p>Библиотека запускает серию вводных занятий для студентов первого курса: как пользоваться каталогом, электронными ресурсами и сервисами библиотеки.</p>
        </div>
      </div>
      <div class="news-card">
        <div class="news-card-media" style="background: linear-gradient(135deg, rgba(245,158,11,.7), rgba(236,72,153,.5)), url('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=600&q=80') center/cover;">
          <span class="media-category" style="background:rgba(245,158,11,.85);">Партнёрство</span>
        </div>
        <div class="news-card-body">
          <div class="news-card-date">20 февраля 2026</div>
          <h3>Сотрудничество с национальной академической библиотекой</h3>
          <p>Подписано соглашение о межбиблиотечном обмене и совместном доступе к электронным ресурсам с Национальной академической библиотекой РК.</p>
        </div>
      </div>
      <div class="news-card">
        <div class="news-card-media" style="background: linear-gradient(135deg, rgba(34,197,94,.7), rgba(6,182,212,.5)), url('https://images.unsplash.com/photo-1497633762265-9d179a990aa6?auto=format&fit=crop&w=600&q=80') center/cover;">
          <span class="media-category" style="background:rgba(34,197,94,.85);">Технологии</span>
        </div>
        <div class="news-card-body">
          <div class="news-card-date">10 февраля 2026</div>
          <h3>Тестирование системы онлайн-бронирования</h3>
          <p>Начато пилотное тестирование системы онлайн-бронирования книг. Студенты могут зарезервировать литературу через личный кабинет.</p>
        </div>
      </div>
      <div class="news-card">
        <div class="news-card-media" style="background: linear-gradient(135deg, rgba(20,105,109,.70), rgba(0,30,64,.50)), url('https://images.unsplash.com/photo-1532012197267-da84d127e765?auto=format&fit=crop&w=600&q=80') center/cover;">
          <span class="media-category" style="background:rgba(20,105,109,.82);">Наука</span>
        </div>
        <div class="news-card-body">
          <div class="news-card-date">1 февраля 2026</div>
          <h3>Мастер-класс по оформлению научных публикаций</h3>
          <p>Библиотека провела мастер-класс по правилам оформления научных статей для публикации в рецензируемых журналах.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="cta-section">
  <div class="container">
    <h2>Следите за обновлениями</h2>
    <p>Подписывайтесь на новости библиотеки и не пропускайте важные события и обновления сервисов.</p>
    <div class="cta-buttons">
      <a href="/resources" class="btn btn-primary">Электронные ресурсы</a>
      <a href="/contacts" class="btn btn-ghost">Связаться с нами</a>
    </div>
  </div>
</section>
@endsection
