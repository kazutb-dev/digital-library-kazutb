@extends('layouts.public')

@section('title', 'Сервисы библиотеки — Digital Library')

@section('content')
  <section class="page-hero">
    <div class="container">
      <div class="eyebrow">Библиотечные сервисы</div>
      <h1>Что предлагает библиотека</h1>
      <p>Современные цифровые и традиционные библиотечные сервисы для студентов, преподавателей и исследователей академического сообщества.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head">
        <div>
          <h2>Основные сервисы</h2>
          <p>Ключевые возможности для работы с библиотечным фондом.</p>
        </div>
      </div>

      <div class="feature-grid">
        <div class="feature-card">
          <div class="icon">🔎</div>
          <h3>Поиск по каталогу</h3>
          <p>Полнотекстовый поиск по каталогу библиотеки с фильтрацией по языку, году издания, наличию. Более 50 000 единиц фонда доступны для поиска онлайн.</p>
        </div>

        <div class="feature-card">
          <div class="icon">📚</div>
          <h3>Выдача и возврат книг</h3>
          <p>Оформление выдачи литературы на абонементе. Читатели могут видеть текущие выдачи и сроки возврата в личном кабинете.</p>
        </div>

        <div class="feature-card">
          <div class="icon">📦</div>
          <h3>Бронирование литературы</h3>
          <p>Возможность забронировать книгу онлайн и получить уведомление, когда она будет доступна для получения в библиотеке.</p>
        </div>

        <div class="feature-card">
          <div class="icon">🔄</div>
          <h3>Продление выдач</h3>
          <p>Продление срока выдачи книг через личный кабинет без необходимости посещения библиотеки. До трёх продлений на одну выдачу.</p>
        </div>

        <div class="feature-card">
          <div class="icon">👤</div>
          <h3>Личный кабинет читателя</h3>
          <p>Личный профиль с информацией о текущих выдачах, бронированиях, истории обслуживания и статусе читательского билета.</p>
        </div>

        <div class="feature-card">
          <div class="icon">📊</div>
          <h3>Учёт и отчётность</h3>
          <p>Библиотечная система ведёт учёт книговыдачи, посещений, использования фонда и формирует отчёты для администрации.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head section-head-centered">
        <div>
          <h2>Цифровые ресурсы</h2>
          <p>Электронные базы данных и цифровые коллекции, доступные пользователям библиотеки.</p>
        </div>
      </div>

      <div class="feature-grid services-centered-grid">
        <div class="feature-card">
          <div class="icon">💻</div>
          <h3>Электронная библиотека</h3>
          <p>Доступ к электронным учебникам, методическим пособиям и научным изданиям в цифровом формате с контролируемым просмотром.</p>
        </div>

        <div class="feature-card">
          <div class="icon">🌐</div>
          <h3>Научные базы данных</h3>
          <p>Подписка на ведущие академические базы данных: Scopus, Web of Science, eLibrary, Springer и другие ресурсы для исследований.</p>
        </div>

        <div class="feature-card">
          <div class="icon">📖</div>
          <h3>Удалённый доступ</h3>
          <p>Электронные ресурсы и подписки доступны не только в кампусе, но и удалённо — из любой точки для авторизованных пользователей.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head section-head-centered">
        <div>
          <h2>Поддержка и консультации</h2>
          <p>Помощь библиотекарей и специалистов для эффективной работы с информацией.</p>
        </div>
      </div>

      <div class="feature-grid services-centered-grid">
        <div class="feature-card">
          <div class="icon">🧠</div>
          <h3>Библиографические консультации</h3>
          <p>Помощь в поиске источников, оформлении библиографических списков и работе с каталогами и базами данных.</p>
        </div>

        <div class="feature-card">
          <div class="icon">🎓</div>
          <h3>Поддержка исследований</h3>
          <p>Консультации по работе с научными базами, навигации по академическим ресурсам и подбору литературы для дипломных и научных работ.</p>
        </div>

        <div class="feature-card">
          <div class="icon">📝</div>
          <h3>Информационная грамотность</h3>
          <p>Обучение навыкам работы с информацией: эффективный поиск, оценка источников, правильное цитирование и использование электронных ресурсов.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-section">
    <div class="container">
      <h2>Начните работу с библиотекой</h2>
      <p>Найдите нужную литературу или войдите в личный кабинет.</p>
      <div class="cta-buttons">
        <a href="/catalog" class="btn btn-primary">Открыть каталог</a>
        <a href="/account" class="btn btn-ghost">Личный кабинет</a>
      </div>
    </div>
  </section>
@endsection

@section('head')
<style>
  .services-centered-grid {
    grid-template-columns: repeat(auto-fit, minmax(260px, 320px));
    justify-content: center;
  }
</style>
@endsection
