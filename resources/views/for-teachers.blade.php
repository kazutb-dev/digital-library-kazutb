@extends('layouts.public')

@section('title', 'Преподавателям — Digital Library')

@section('content')
  <section class="page-hero">
    <div class="container">
      <div class="eyebrow eyebrow--violet">Преподавателям</div>
      <h1>Инструменты для подготовки силлабуса и работы с литературой</h1>
      <p>Подберите литературу, соберите подборку для дисциплины, получите доступ к научным базам — всё в одном месте.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head">
        <div>
          <h2>Начните работу</h2>
          <p>Четыре основных направления для преподавателей.</p>
        </div>
      </div>

      <div class="action-groups">
        <div class="action-group action-group--primary">
          <div class="action-group-header">
            <div class="action-icon">📋</div>
            <div>
              <h3>Подборка литературы для силлабуса</h3>
              <p>Собирайте книги и электронные ресурсы в черновик списка литературы. Экспортируйте в нужном формате для вставки в силлабус.</p>
            </div>
          </div>
          <div class="action-links">
            <a href="/shortlist" class="btn btn-primary">Открыть подборку</a>
            <a href="/account" class="action-link">Мой кабинет →</a>
          </div>
        </div>

        <div class="action-group">
          <div class="action-group-header">
            <div class="action-icon">🔎</div>
            <div>
              <h3>Поиск и подбор литературы</h3>
              <p>Ищите по каталогу (50 000+ единиц), по направлениям подготовки или по ключевым словам.</p>
            </div>
          </div>
          <div class="action-links">
            <a href="/catalog" class="btn btn-ghost">Каталог</a>
            <a href="/discover" class="btn btn-ghost">По направлениям</a>
          </div>
        </div>

        <div class="action-group">
          <div class="action-group-header">
            <div class="action-icon">🌐</div>
            <div>
              <h3>Электронные ресурсы и научные базы</h3>
              <p>Лицензированные библиотеки, Scopus, Web of Science, РИНЦ, открытые коллекции — доступ из кампуса и удалённо.</p>
            </div>
          </div>
          <div class="action-links">
            <a href="/resources" class="btn btn-ghost">Все ресурсы</a>
          </div>
        </div>

        <div class="action-group">
          <div class="action-group-header">
            <div class="action-icon">💡</div>
            <div>
              <h3>Помощь и консультации</h3>
              <p>Справка об обеспеченности дисциплины, заявка на пополнение фонда, консультации библиографов.</p>
            </div>
          </div>
          <div class="action-links">
            <a href="/contacts" class="btn btn-ghost">Связаться</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head section-head-centered">
        <div>
          <h2>Подготовка силлабуса: как библиотека помогает</h2>
          <p>Пошаговый процесс работы с библиотекой при формировании списка литературы для учебной дисциплины.</p>
        </div>
      </div>

      <div class="syllabus-steps">
        <div class="step-card">
          <div class="step-number">1</div>
          <h3>Определите потребности</h3>
          <p>Сформулируйте перечень тем и направлений дисциплины. Используйте <a href="/discover">тематический поиск</a> для навигации по областям знаний. Определите, какие виды литературы нужны: учебники, монографии, справочные издания, научные журналы.</p>
        </div>

        <div class="step-card">
          <div class="step-number">2</div>
          <h3>Поиск в каталоге</h3>
          <p>Воспользуйтесь <a href="/catalog">электронным каталогом</a> для поиска имеющейся литературы. Фильтруйте по году, языку и наличию экземпляров.</p>
        </div>

        <div class="step-card">
          <div class="step-number">3</div>
          <h3>Проверьте электронные ресурсы</h3>
          <p>Изучите <a href="/resources">электронные ресурсы</a> — подписные базы и открытые коллекции могут дополнить или заменить печатные издания.</p>
        </div>

        <div class="step-card">
          <div class="step-number">4</div>
          <h3>Соберите подборку</h3>
          <p>Добавляйте найденные книги и электронные ресурсы в <a href="/shortlist">подборку литературы</a>. Из каталога и страниц книг нажимайте «В подборку» — все выбранные источники соберутся в один список. Дайте черновику название и добавьте заметки — это поможет вернуться к нему позже через <a href="/account">кабинет</a>.</p>
        </div>

        <div class="step-card">
          <div class="step-number">5</div>
          <h3>Экспортируйте список</h3>
          <p>В <a href="/shortlist">подборке</a> выберите формат списка литературы: нумерованный, по разделам или для силлабуса. Скопируйте текст одним нажатием или распечатайте готовый черновик для вставки в документ.</p>
        </div>

        <div class="step-card">
          <div class="step-number">6</div>
          <h3>Обратитесь к библиографу</h3>
          <p>Для углублённого подбора и проверки обеспеченности обратитесь в <a href="/contacts">информационно-библиографический отдел</a>. Специалисты помогут подготовить справку.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head section-head-centered">
        <div>
          <div class="eyebrow eyebrow--cyan">Электронные ресурсы</div>
          <h2>Ключевые базы для преподавателей</h2>
          <p>Электронные ресурсы, доступные преподавателям и сотрудникам университета.</p>
        </div>
      </div>

      <div class="resource-highlights" id="teacher-ext-resources">
        <div class="card resource-highlight-card">
          <div class="eyebrow eyebrow--blue">Загрузка...</div>
          <h3>Электронные ресурсы</h3>
          <p class="text-body" style="margin: 0;">Загрузка данных о доступных ресурсах...</p>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="container">
      <div class="section-head section-head-centered">
        <div>
          <h2>Часто задаваемые вопросы</h2>
          <p>Ответы на типичные вопросы преподавателей о работе с библиотекой.</p>
        </div>
      </div>

      <div class="teacher-faq">
        <details class="faq-item">
          <summary class="faq-question">Как подобрать литературу для нового курса?</summary>
          <div class="faq-answer">
            <p>Начните с поиска в <a href="/catalog">электронном каталоге</a> по ключевым словам и темам дисциплины. Добавляйте найденное в <a href="/shortlist">подборку</a> и экспортируйте в нужном формате. Для углублённого подбора обратитесь в информационно-библиографический отдел.</p>
          </div>
        </details>

        <details class="faq-item">
          <summary class="faq-question">Какие электронные ресурсы доступны?</summary>
          <div class="faq-answer">
            <p>Преподавателям доступны все подписные электронные ресурсы университета, включая IPR SMART, открытые научные коллекции и международные базы данных. Полный перечень — на странице <a href="/resources">Электронные ресурсы</a>.</p>
          </div>
        </details>

        <details class="faq-item">
          <summary class="faq-question">Можно ли заказать литературу, которой нет в фонде?</summary>
          <div class="faq-answer">
            <p>Да. Подайте заявку через отдел комплектования. Обратитесь по <a href="/contacts">контактам библиотеки</a> для оформления.</p>
          </div>
        </details>
      </div>
    </div>
  </section>

  {{-- CTA removed — action groups above are sufficient --}}
@endsection

@section('head')
<style>
  /* Action groups — task-oriented layout */
  .action-groups {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
  }

  .action-group {
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    display: flex;
    flex-direction: column;
    gap: 18px;
  }

  .action-group--primary {
    border: 1px solid rgba(0,30,64,.12);
    background: linear-gradient(135deg, rgba(0,30,64,.03), rgba(20,105,109,.05));
  }

  .action-group-header {
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }

  .action-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: rgba(0,30,64,.07);
  }

  .action-group--primary .action-icon {
    background: rgba(20,105,109,.10);
  }

  .action-group h3 {
    margin: 0 0 6px;
    font-size: 18px;
    font-weight: 700;
  }

  .action-group p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
  }

  .action-links {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .action-link {
    font-size: 14px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
  }
  .action-link:hover { color: var(--cyan); }

  .syllabus-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    counter-reset: step;
  }

  .step-card {
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    position: relative;
  }

  .step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--blue), var(--cyan));
    color: var(--text-inverse);
    font-weight: 800;
    font-size: 16px;
    margin-bottom: 14px;
  }

  .step-card h3 {
    margin: 0 0 10px;
    font-size: 18px;
    font-weight: 700;
  }

  .step-card p {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.6;
  }

  .step-card a {
    color: var(--blue);
    font-weight: 600;
    text-decoration: none;
  }

  .step-card a:hover { text-decoration: underline; }

  .resource-highlights {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
  }

  .resource-highlight-card {
    padding: 28px;
  }

  .resource-highlight-card h3 {
    margin: 8px 0 12px;
    font-size: 20px;
    font-weight: 800;
  }

  .teacher-faq {
    max-width: 800px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .faq-item {
    background: var(--surface-glass);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
  }

  .faq-question {
    padding: 20px 24px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background .2s;
  }

  .faq-question::-webkit-details-marker { display: none; }

  .faq-question::after {
    content: '+';
    font-size: 20px;
    font-weight: 300;
    color: var(--muted);
    transition: transform .2s;
  }

  details[open] .faq-question::after {
    content: '−';
  }

  .faq-question:hover {
    background: var(--bg-soft);
  }

  .faq-answer {
    padding: 0 24px 20px;
  }

  .faq-answer p {
    margin: 0;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.7;
  }

  .faq-answer a {
    color: var(--blue);
    font-weight: 600;
    text-decoration: none;
  }

  .faq-answer a:hover { text-decoration: underline; }

  @media (max-width: 680px) {
    .action-groups { grid-template-columns: 1fr; }
    .action-group { padding: 20px; }
    .action-group-header { flex-direction: column; gap: 10px; }
    .syllabus-steps { grid-template-columns: 1fr; }
    .resource-highlights { grid-template-columns: 1fr; }
    .step-card { padding: 20px; }
    .resource-highlight-card { padding: 20px; }
    .faq-question { padding: 16px 20px; font-size: 15px; }
    .faq-answer { padding: 0 20px 16px; }
  }
</style>
@endsection

@section('scripts')
<script>
(function() {
  const API_URL = '/api/v1/external-resources';

  const eyebrowColorMap = {
    electronic_library: 'blue',
    research_database: 'violet',
    open_access: 'green',
    analytics: 'pink'
  };

  function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  function formatExpiry(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const months = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];
    return `Действует до ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
  }

  async function loadTeacherResources() {
    const container = document.getElementById('teacher-ext-resources');
    try {
      const res = await fetch(API_URL, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('API error');

      const json = await res.json();
      const resources = (json.data || []).filter(r => r.status === 'active');
      const categories = json.meta?.categories || {};
      const accessTypes = json.meta?.access_types || {};

      // Show up to 4 highlighted resources, prioritizing licensed over open
      const licensed = resources.filter(r => r.access_type !== 'open');
      const open = resources.filter(r => r.access_type === 'open');
      const highlighted = [...licensed.slice(0, 3), ...open.slice(0, 1)].slice(0, 4);

      if (highlighted.length === 0) {
        container.innerHTML = '<p style="color:var(--muted);">Информация о внешних ресурсах временно недоступна.</p>';
        return;
      }

      container.innerHTML = highlighted.map(r => {
        const catInfo = categories[r.category] || {};
        const accInfo = accessTypes[r.access_type] || {};
        const color = eyebrowColorMap[r.category] || 'blue';

        return `
          <div class="card resource-highlight-card">
            <div class="eyebrow eyebrow--${color}">${escapeHtml(catInfo.label || r.category)}</div>
            <h3>${escapeHtml(r.title)}</h3>
            <p class="text-body" style="margin: 0 0 12px;">${escapeHtml(r.description)}</p>
            ${r.expiry_date
              ? `<span class="badge">${formatExpiry(r.expiry_date)}</span>`
              : `<span class="badge">${escapeHtml(accInfo.label || 'Доступно')}</span>`
            }
            ${r.url ? `<a href="${escapeHtml(r.url)}" target="_blank" rel="noopener" class="feature-link" style="display:block; margin-top:8px;">Перейти к ресурсу ↗</a>` : ''}
          </div>
        `;
      }).join('') + `
        <div class="card resource-highlight-card" style="display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
          <div style="font-size:36px; margin-bottom:12px;">📚</div>
          <h3>Все внешние ресурсы</h3>
          <p class="text-body" style="margin: 0 0 12px;">Полный перечень подписных баз, открытых коллекций и аналитических ресурсов.</p>
          <a href="/resources" class="feature-link">Открыть все ресурсы →</a>
        </div>
      `;
    } catch (e) {
      container.innerHTML = `
        <div class="card resource-highlight-card">
          <div class="eyebrow eyebrow--blue">Электронные ресурсы</div>
          <h3>Внешние платформы</h3>
          <p class="text-body" style="margin: 0 0 12px;">Подписные электронные библиотеки, научные базы данных и открытые образовательные ресурсы.</p>
          <a href="/resources" class="feature-link">Перейти к ресурсам →</a>
        </div>
      `;
    }
  }

  loadTeacherResources();
})();
</script>
@endsection
