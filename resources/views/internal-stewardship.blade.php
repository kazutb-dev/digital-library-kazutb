<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Stewardship — Internal</title>
    <style>
        :root {
            --bg: #f8f9fa;
            --paper: rgba(255, 255, 255, 0.96);
            --ink: #191c1d;
            --muted: #43474f;
            --line: rgba(195, 198, 209, 0.55);
            --accent: #001e40;
            --accent-soft: rgba(0, 30, 64, 0.05);
            --danger: #ba1a1a;
            --danger-soft: rgba(186, 26, 26, 0.08);
            --warn: #5d4201;
            --warn-soft: rgba(93, 66, 1, 0.10);
            --ok: #14696d;
            --ok-soft: rgba(20, 105, 109, 0.10);
            --shadow: 0 12px 32px rgba(25, 28, 29, 0.04);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(0, 30, 64, 0.04), transparent 20%),
                linear-gradient(180deg, #fbfcfc 0%, var(--bg) 100%);
        }

        a { color: inherit; }

        .shell {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .hero, .panel {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .hero { padding: 28px; }
        .panel { padding: 22px; margin-top: 20px; }

        .eyebrow {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 13px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 {
            margin: 16px 0 8px;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.05;
            font-weight: 600;
            font-family: 'Newsreader', Georgia, serif;
            letter-spacing: -0.03em;
            color: var(--accent);
        }

        h2 { margin: 0 0 14px; font-size: 20px; }

        .subtitle, .meta, .empty { color: var(--muted); }

        /* Navigation row */
        .nav-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            font-size: 15px;
            transition: background-color .18s ease, border-color .18s ease, transform .18s ease;
        }

        .nav-link:hover {
            transform: translate3d(0, -1px, 0);
            border-color: rgba(0,30,64,.14);
            background: rgba(243,244,245,.96);
        }

        .nav-link.primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        /* Tab bar */
        .tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            border: 1px solid var(--line);
            border-bottom: none;
            background: #fff;
            color: var(--muted);
            font: inherit;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, transform .15s ease;
        }

        .tab-btn:hover {
            transform: translate3d(0, -1px, 0);
        }

        .tab-btn.active {
            background: var(--paper);
            color: var(--accent);
            font-weight: 700;
            border-color: var(--accent);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Summary cards grid */
        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .metric {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
            min-height: 110px;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }

        .metric:hover {
            transform: translate3d(0, -2px, 0);
            border-color: rgba(20,105,109,.18);
            box-shadow: 0 12px 24px rgba(25, 28, 29, 0.04);
        }

        .metric-label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .metric-value {
            margin-top: 10px;
            font-size: 34px;
            font-weight: 700;
            line-height: 1;
        }

        .metric-note {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .metric.alert {
            background: linear-gradient(180deg, #fff8f7 0%, #fff 100%);
        }

        .metric.soft {
            background: linear-gradient(180deg, #f8fcfd 0%, #fff 100%);
        }

        .metric.accent-bg {
            background: linear-gradient(180deg, var(--accent-soft) 0%, #fff 100%);
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
            margin-bottom: 16px;
        }

        .field {
            display: grid;
            gap: 6px;
            min-width: 160px;
        }

        .field label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .select, .input, .button {
            min-height: 44px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            padding: 0 14px;
            font: inherit;
            font-size: 14px;
        }

        .button {
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            padding: 0 18px;
        }

        .button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .button.secondary {
            background: #fff;
            color: var(--ink);
            border-color: var(--line);
        }

        .button.small {
            min-height: 36px;
            padding: 0 12px;
            font-size: 13px;
            border-radius: 10px;
        }

        .button.danger {
            background: var(--danger);
            border-color: var(--danger);
            color: #fff;
        }

        .button.warn-outline {
            background: #fff;
            border-color: var(--warn);
            color: var(--warn);
        }

        /* Table */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid var(--line);
            background: #fff;
            margin: 1px 2px;
        }

        .badge.reason {
            background: var(--warn-soft);
            color: var(--warn);
            border-color: rgba(154, 52, 18, 0.15);
        }

        .badge.entity {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: rgba(18, 69, 89, 0.15);
        }

        .truncate-id {
            font-family: monospace;
            font-size: 12px;
            color: var(--muted);
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }

        /* Pager */
        .pager {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-top: 16px;
        }

        .pager-actions {
            display: flex;
            gap: 8px;
        }

        /* Inline action form */
        .action-row td {
            background: #fafaf7;
            border-bottom: 2px solid var(--accent);
            padding: 14px 10px;
        }

        .action-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }

        .action-form .field { min-width: 200px; }

        .action-form textarea {
            width: 100%;
            min-height: 60px;
            border-radius: 10px;
            border: 1px solid var(--line);
            padding: 10px;
            font: inherit;
            font-size: 14px;
            resize: vertical;
        }

        /* Status / feedback */
        .status-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .status-chip {
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            border: 1px solid var(--line);
            background: #fff;
        }

        .status-chip.ok {
            background: var(--ok-soft);
            color: var(--ok);
            border-color: rgba(22, 101, 52, 0.18);
        }

        .status-chip.warn {
            background: var(--warn-soft);
            color: var(--warn);
            border-color: rgba(154, 52, 18, 0.18);
        }

        .error-box {
            margin-top: 16px;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(153, 27, 27, 0.15);
            display: none;
        }

        .success-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 8px;
            background: var(--ok-soft);
            color: var(--ok);
            border: 1px solid rgba(22, 101, 52, 0.2);
            font-size: 14px;
            z-index: 1000;
            opacity: 0;
            transform: translate3d(0, 10px, 0);
            transition: opacity 0.3s, transform 0.3s;
            pointer-events: none;
        }

        .success-toast.visible {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        /* Reason code list in overview */
        .reason-list { display: grid; gap: 8px; }

        .reason-item {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
            font-size: 14px;
        }

        .reason-item-left {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .reason-count {
            font-weight: 700;
            font-size: 16px;
        }

        .progress-row { margin-bottom: 18px; }
        .progress-row .progress-label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; }
        .progress-row .progress-label strong { font-weight: 700; }
        .progress-bar-track { height: 16px; background: var(--bg); border-radius: 999px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 999px; transition: width .5s ease; }

        /* Bulk action bar */
        .bulk-bar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 8px;
            background: var(--accent-soft);
            border: 1px solid rgba(18, 69, 89, 0.2);
        }

        .bulk-bar.visible { display: flex; flex-wrap: wrap; }

        .bulk-bar .bulk-count {
            font-weight: 700;
            font-size: 14px;
            color: var(--accent);
            white-space: nowrap;
        }

        .bulk-bar .bulk-note {
            flex: 1;
            min-width: 200px;
        }

        .bulk-bar .bulk-note input {
            width: 100%;
            min-height: 38px;
            border-radius: 10px;
            border: 1px solid var(--line);
            padding: 0 12px;
            font: inherit;
            font-size: 13px;
        }

        th .cb-all, td .cb-row { width: 18px; height: 18px; cursor: pointer; }

        @media (max-width: 920px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .shell { width: min(100% - 20px, 1200px); }
            .hero, .panel { padding: 18px; border-radius: 8px; }
            .cards { grid-template-columns: 1fr; }
            .pager { flex-direction: column; align-items: flex-start; }
            .tab-btn { font-size: 13px; padding: 8px 14px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="eyebrow">Internal Data Stewardship</div>
            <h1>Очереди проверки данных</h1>
            <p class="subtitle">
                Рабочая страница библиотекаря для просмотра и разрешения issues по копиям, документам и читателям.
            </p>
            <div class="nav-row">
                <a class="nav-link primary" href="/internal/dashboard">Dashboard</a>
                <a class="nav-link" href="/internal/review">Quality Issues</a>
                <a class="nav-link" href="/catalog">Каталог</a>
            </div>
            <div class="status-row" id="status-row">
                <div class="status-chip">Загрузка…</div>
            </div>
            <div class="error-box" id="global-error"></div>
        </section>

        <nav class="tab-bar" id="tab-bar">
            <button class="tab-btn active" data-tab="overview">Обзор</button>
            <button class="tab-btn" data-tab="copies">Копии</button>
            <button class="tab-btn" data-tab="documents">Документы</button>
            <button class="tab-btn" data-tab="readers">Читатели</button>
        </nav>

        <!-- ═══════ OVERVIEW TAB ═══════ -->
        <section class="tab-content active" id="tab-overview">
            <div class="panel">
                <h2>📊 Общее здоровье данных</h2>
                <div class="cards" id="health-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <h2>📋 Задачи проверки (Review Tasks)</h2>
                <div class="cards" id="task-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <h2>🏷 Флаги качества (Data Quality Flags)</h2>
                <div class="cards" id="flag-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <h2>🔍 Top Reason Codes (все сущности)</h2>
                <div class="reason-list" id="top-reasons">
                    <div class="reason-item"><span class="meta">Загрузка…</span></div>
                </div>
            </div>
            <div class="panel">
                <h2>📈 Прогресс по типу сущности</h2>
                <div id="entity-progress">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
        </section>

        <!-- ═══════ COPIES TAB ═══════ -->
        <section class="tab-content" id="tab-copies">
            <div class="panel">
                <h2>Copy Review Summary</h2>
                <div class="cards" id="copy-summary-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <div class="bulk-bar" id="copy-bulk-bar">
                    <span class="bulk-count" id="copy-bulk-count">0 выбрано</span>
                    <div class="bulk-note"><input id="copy-bulk-note" type="text" placeholder="Заметка для всех (опционально)"></div>
                    <button class="button small" onclick="bulkResolveCopies()">Решить выбранные</button>
                    <button class="button small secondary" onclick="clearCopySelection()">Отменить</button>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label for="copy-reason-filter">Reason Code</label>
                        <input id="copy-reason-filter" class="input" type="text" placeholder="e.g. MISSING_DOCUMENT">
                    </div>
                    <button class="button" onclick="loadCopyQueue(1)">Применить</button>
                    <button class="button secondary" onclick="document.getElementById('copy-reason-filter').value='';loadCopyQueue(1)">Сбросить</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="cb-all" id="copy-cb-all" onchange="toggleAllCopies(this.checked)"></th>
                                <th>Copy ID</th>
                                <th>Документ</th>
                                <th>Филиал</th>
                                <th>Сигла</th>
                                <th>Reason Codes</th>
                                <th>Обновлено</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="copy-queue-body">
                            <tr><td colspan="7" class="empty">Загрузка…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pager">
                    <div id="copy-page-info" class="meta"></div>
                    <div class="pager-actions">
                        <button id="copy-prev" class="button secondary small" onclick="loadCopyQueue(copyPage-1)">← Prev</button>
                        <button id="copy-next" class="button secondary small" onclick="loadCopyQueue(copyPage+1)">Next →</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════ DOCUMENTS TAB ═══════ -->
        <section class="tab-content" id="tab-documents">
            <div class="panel">
                <h2>Document Review Summary</h2>
                <div class="cards" id="doc-summary-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <h2>Каталожное обогащение</h2>
                <div class="cards" id="enrichment-stats-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
                <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="button small" onclick="bulkValidateAll()">Перевалидировать все ISBN</button>
                    <button class="button small secondary" onclick="checkIsbnPrompt()">Проверить ISBN вручную</button>
                </div>
                <div id="enrichment-status" style="margin-top: 8px;"></div>
            </div>
            <div class="panel">
                <div class="bulk-bar" id="doc-bulk-bar">
                    <span class="bulk-count" id="doc-bulk-count">0 выбрано</span>
                    <div class="bulk-note"><input id="doc-bulk-note" type="text" placeholder="Заметка для всех (опционально)"></div>
                    <button class="button small" onclick="bulkResolveDocs()">Решить выбранные</button>
                    <button class="button small danger" onclick="showDocBulkFlag()">Флаг выбранным</button>
                    <button class="button small secondary" onclick="clearDocSelection()">Отменить</button>
                </div>
                <div id="doc-bulk-flag-bar" class="bulk-bar">
                    <span class="bulk-count">Массовый флаг</span>
                    <div class="field" style="min-width:200px">
                        <input id="doc-bulk-flag-codes" class="input" type="text" placeholder="Reason codes (через запятую)" style="min-height:38px;font-size:13px">
                    </div>
                    <div class="bulk-note"><input id="doc-bulk-flag-note" type="text" placeholder="Заметка флага (опционально)"></div>
                    <button class="button small danger" onclick="bulkFlagDocs()">Отправить флаг</button>
                    <button class="button small secondary" onclick="hideDocBulkFlag()">Отмена</button>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label for="doc-reason-filter">Reason Code</label>
                        <input id="doc-reason-filter" class="input" type="text" placeholder="e.g. DUPLICATE_ISBN">
                    </div>
                    <button class="button" onclick="loadDocQueue(1)">Применить</button>
                    <button class="button secondary" onclick="document.getElementById('doc-reason-filter').value='';loadDocQueue(1)">Сбросить</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="cb-all" id="doc-cb-all" onchange="toggleAllDocs(this.checked)"></th>
                                <th>Document ID</th>
                                <th>Заголовок</th>
                                <th>ISBN</th>
                                <th>Reason Codes</th>
                                <th>Обновлено</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="doc-queue-body">
                            <tr><td colspan="6" class="empty">Загрузка…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pager">
                    <div id="doc-page-info" class="meta"></div>
                    <div class="pager-actions">
                        <button id="doc-prev" class="button secondary small" onclick="loadDocQueue(docPage-1)">← Prev</button>
                        <button id="doc-next" class="button secondary small" onclick="loadDocQueue(docPage+1)">Next →</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════ READERS TAB ═══════ -->
        <section class="tab-content" id="tab-readers">
            <div class="panel">
                <h2>Reader Review Summary</h2>
                <div class="cards" id="reader-summary-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
            </div>
            <div class="panel">
                <h2>Нормализация контактов</h2>
                <div class="cards" id="contact-stats-cards">
                    <div class="metric soft"><div class="metric-label">Загрузка…</div></div>
                </div>
                <div style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="button small" onclick="bulkNormalizeContacts()">Перенормализовать контакты</button>
                    <button class="button small secondary" onclick="validateContactPrompt()">Проверить контакт</button>
                </div>
                <div id="contact-norm-status" style="margin-top: 8px;"></div>
            </div>
            <div class="panel">
                <div class="bulk-bar" id="reader-bulk-bar">
                    <span class="bulk-count" id="reader-bulk-count">0 выбрано</span>
                    <div class="bulk-note"><input id="reader-bulk-note" type="text" placeholder="Заметка для всех (опционально)"></div>
                    <button class="button small" onclick="bulkResolveReaders()">Решить выбранные</button>
                    <button class="button small secondary" onclick="clearReaderSelection()">Отменить</button>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label for="reader-reason-filter">Reason Code</label>
                        <input id="reader-reason-filter" class="input" type="text" placeholder="e.g. MISSING_CONTACT">
                    </div>
                    <button class="button" onclick="loadReaderQueue(1)">Применить</button>
                    <button class="button secondary" onclick="document.getElementById('reader-reason-filter').value='';loadReaderQueue(1)">Сбросить</button>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="cb-all" id="reader-cb-all" onchange="toggleAllReaders(this.checked)"></th>
                                <th>Reader ID</th>
                                <th>ФИО</th>
                                <th>Код (legacy)</th>
                                <th>Регистрация</th>
                                <th>Reason Codes</th>
                                <th>Обновлено</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="reader-queue-body">
                            <tr><td colspan="7" class="empty">Загрузка…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pager">
                    <div id="reader-page-info" class="meta"></div>
                    <div class="pager-actions">
                        <button id="reader-prev" class="button secondary small" onclick="loadReaderQueue(readerPage-1)">← Prev</button>
                        <button id="reader-next" class="button secondary small" onclick="loadReaderQueue(readerPage+1)">Next →</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="success-toast" id="toast"></div>

    <script>
        // ═══════════════════════════════════════
        // Helpers
        // ═══════════════════════════════════════
        function esc(text) {
            const d = document.createElement('div');
            d.textContent = String(text ?? '');
            return d.innerHTML;
        }

        function fmt(n) {
            const v = Number(n ?? 0);
            return Number.isFinite(v) ? v.toLocaleString('ru-RU') : '0';
        }

        function shortId(uuid) {
            return uuid ? uuid.substring(0, 8) + '…' : '—';
        }

        function shortDate(iso) {
            if (!iso) return '—';
            try {
                const d = new Date(iso);
                return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
            } catch { return iso; }
        }

        function reasonBadges(codes) {
            if (!Array.isArray(codes) || !codes.length) return '<span class="meta">—</span>';
            return codes.map(c => `<span class="badge reason">${esc(c)}</span>`).join('');
        }

        function metricHtml(label, value, note, cls) {
            return `<div class="metric ${cls || ''}">
                <div class="metric-label">${esc(label)}</div>
                <div class="metric-value">${esc(fmt(value))}</div>
                ${note ? `<div class="metric-note">${esc(note)}</div>` : ''}
            </div>`;
        }

        async function api(url, methodOrOpts, body) {
            let opts = {};
            if (typeof methodOrOpts === 'string') {
                opts = { method: methodOrOpts };
                if (body !== undefined) opts.body = JSON.stringify(body);
            } else if (methodOrOpts) {
                opts = methodOrOpts;
            }
            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                ...opts,
            });
            if (!resp.ok) {
                const b = await resp.json().catch(() => ({}));
                throw { status: resp.status, ...b };
            }
            return resp.json();
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.classList.add('visible');
            setTimeout(() => t.classList.remove('visible'), 3000);
        }

        function showError(msg) {
            const e = document.getElementById('global-error');
            e.textContent = msg;
            e.style.display = 'block';
            setTimeout(() => { e.style.display = 'none'; }, 8000);
        }

        // ═══════════════════════════════════════
        // Tab navigation
        // ═══════════════════════════════════════
        const tabLoadedFlags = { overview: false, copies: false, documents: false, readers: false };

        document.getElementById('tab-bar').addEventListener('click', (e) => {
            const btn = e.target.closest('.tab-btn');
            if (!btn) return;
            const tab = btn.dataset.tab;

            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');

            if (!tabLoadedFlags[tab]) {
                tabLoadedFlags[tab] = true;
                if (tab === 'copies') { loadCopySummary(); loadCopyQueue(1); }
                if (tab === 'documents') { loadDocSummary(); loadEnrichmentStats(); loadDocQueue(1); }
                if (tab === 'readers') { loadReaderSummary(); loadContactStats(); loadReaderQueue(1); }
            }
        });

        // ═══════════════════════════════════════
        // OVERVIEW TAB
        // ═══════════════════════════════════════
        async function loadOverview() {
            try {
                const data = await api('/api/v1/internal/review/stewardship-metrics');
                const d = data.data || {};
                const health = d.overallHealth || {};
                const byEntity = d.byEntity || {};
                const tasks = d.reviewTasks || {};
                const flags = d.dataQualityFlags || {};
                const topRC = d.topIssues || [];

                // Health cards
                const healthColor = health.healthPercent >= 90 ? '#16a34a' : (health.healthPercent >= 70 ? '#d97706' : '#dc2626');
                document.getElementById('health-cards').innerHTML = [
                    `<div class="metric" style="background: ${healthColor}; color: white;">
                        <div class="metric-value">${health.healthPercent || 0}%</div>
                        <div class="metric-label">Здоровье данных</div>
                    </div>`,
                    metricHtml('Всего сущностей', health.totalEntities, 'копии + документы + читатели', 'soft'),
                    metricHtml('Чистых', health.cleanEntities, 'без замечаний', 'accent-bg'),
                    metricHtml('Требуют проверки', health.unresolvedEntities, 'нерешённых', 'alert'),
                ].join('');

                // Task cards
                const taskCompletionPct = tasks.total > 0 ? ((tasks.completed / tasks.total) * 100).toFixed(1) : '0';
                document.getElementById('task-cards').innerHTML = [
                    metricHtml('Всего задач', tasks.total, '', 'soft'),
                    metricHtml('Открытых', tasks.open, 'ожидают проверки', 'warn-soft'),
                    metricHtml('Решено', tasks.completed, `${taskCompletionPct}% от всех`, 'accent-bg'),
                    metricHtml('Отменено', tasks.cancelled, '', 'soft'),
                ].join('');

                // Flag cards
                const bySeverity = flags.bySeverity || {};
                const byStatus = flags.byStatus || {};
                document.getElementById('flag-cards').innerHTML = [
                    metricHtml('Всего флагов', flags.total, '', 'soft'),
                    metricHtml('Открытых', byStatus.open, '', 'alert'),
                    metricHtml('High', bySeverity.high, 'серьёзные', 'alert'),
                    metricHtml('Medium', bySeverity.medium, 'средние', 'warn-soft'),
                ].join('');

                // Top reason codes
                if (topRC.length) {
                    document.getElementById('top-reasons').innerHTML = topRC.map(r => `
                        <div class="reason-item">
                            <div class="reason-item-left">
                                <span class="badge reason">${esc(r.reasonCode)}</span>
                                ${(r.entities || []).map(e => `<span class="badge entity">${esc(e)}</span>`).join('')}
                            </div>
                            <span class="reason-count">${fmt(r.count)}</span>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('top-reasons').innerHTML = '<div class="reason-item"><span class="meta">Нет данных</span></div>';
                }

                // Entity progress bars
                const entities = [
                    { key: 'copies', label: 'Копии', data: byEntity.copies },
                    { key: 'documents', label: 'Документы', data: byEntity.documents },
                    { key: 'readers', label: 'Читатели', data: byEntity.readers },
                ];
                document.getElementById('entity-progress').innerHTML = entities.map(e => {
                    const pct = e.data?.healthPercent ?? 0;
                    const color = pct >= 90 ? '#16a34a' : (pct >= 70 ? '#d97706' : '#dc2626');
                    return `
                        <div class="progress-row">
                            <div class="progress-label">
                                <strong>${e.label}</strong>
                                <span>${pct}% чистых (${fmt(e.data?.clean || 0)} из ${fmt(e.data?.total || 0)})</span>
                            </div>
                            <div class="progress-bar-track">
                                <div class="progress-bar-fill" style="width: ${pct}%; background: ${color};"></div>
                            </div>
                        </div>
                    `;
                }).join('');

                document.getElementById('status-row').innerHTML = '<div class="status-chip ok">Stewardship API: OK</div>';
            } catch (err) {
                document.getElementById('status-row').innerHTML = '<div class="status-chip warn">Stewardship API: ошибка загрузки</div>';
                showError('Не удалось загрузить stewardship metrics: ' + (err.message || JSON.stringify(err)));
            }
        }

        // ═══════════════════════════════════════
        // COPIES TAB
        // ═══════════════════════════════════════
        let copyPage = 1;
        let copyTotalPages = 1;

        async function loadCopySummary() {
            try {
                const data = await api('/api/v1/internal/review/copies-summary');
                const d = data.data || {};
                const topRC = d.topReasonCodes || [];
                document.getElementById('copy-summary-cards').innerHTML = [
                    metricHtml('Всего копий', d.totalCopies, '', 'soft'),
                    metricHtml('Требуют проверки', d.needsReviewCount, '', 'alert'),
                    metricHtml('Решено', d.resolvedCount, '', 'accent-bg'),
                    metricHtml('Top Reason', topRC[0]?.reasonCode || '—', topRC[0] ? `${fmt(topRC[0].count)} записей` : '', 'soft'),
                ].join('');
            } catch { /* summary is optional */ }
        }

        async function loadCopyQueue(page) {
            page = Math.max(1, page || 1);
            copyPage = page;
            const reason = document.getElementById('copy-reason-filter').value.trim();
            const params = new URLSearchParams({ page, limit: 20 });
            if (reason) params.set('reason_code', reason);

            try {
                const data = await api(`/api/v1/internal/review/copies?${params}`);
                const items = data.data || [];
                const meta = data.meta || {};
                copyTotalPages = meta.totalPages || 1;

                document.getElementById('copy-page-info').textContent = `Стр. ${meta.page || page} из ${copyTotalPages} (всего: ${fmt(meta.total)})`;
                document.getElementById('copy-prev').disabled = copyPage <= 1;
                document.getElementById('copy-next').disabled = copyPage >= copyTotalPages;

                if (!items.length) {
                    document.getElementById('copy-queue-body').innerHTML = '<tr><td colspan="8" class="empty">Нет копий в очереди проверки.</td></tr>';
                    return;
                }

                document.getElementById('copy-queue-body').innerHTML = items.map(item => {
                    const id = item.copyIdentity?.id || '';
                    return `
                    <tr id="copy-row-${id}">
                        <td><input type="checkbox" class="cb-row" data-entity="copy" data-id="${esc(id)}" onchange="updateCopyBulk()"></td>
                        <td><span class="truncate-id" title="${esc(id)}">${esc(shortId(id))}</span></td>
                        <td>${esc(item.parentDocument?.titleRaw || '—')}</td>
                        <td>${esc(item.branch?.branchId ? shortId(item.branch.branchId) : '—')}</td>
                        <td>${esc(item.location?.siglaId ? shortId(item.location.siglaId) : '—')}</td>
                        <td>${reasonBadges(item.lifecycle?.reviewReasonCodes)}</td>
                        <td>${esc(shortDate(item.updatedAt))}</td>
                        <td>
                            <button class="button small" onclick="toggleCopyResolve('${esc(id)}')">Решить</button>
                        </td>
                    </tr>
                    <tr class="action-row" id="copy-action-${id}" style="display:none">
                        <td colspan="8">
                            <div class="action-form">
                                <div class="field" style="flex:1">
                                    <label>Заметка (опционально)</label>
                                    <textarea id="copy-note-${id}" placeholder="Описание решения…"></textarea>
                                </div>
                                <button class="button small" onclick="resolveCopy('${esc(id)}')">Подтвердить</button>
                                <button class="button small secondary" onclick="toggleCopyResolve('${esc(id)}')">Отмена</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            } catch (err) {
                document.getElementById('copy-queue-body').innerHTML = `<tr><td colspan="8" class="empty">Ошибка загрузки: ${esc(err.message || '')}</td></tr>`;
            }
        }

        function toggleCopyResolve(id) {
            const row = document.getElementById('copy-action-' + id);
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }

        async function resolveCopy(id) {
            const note = document.getElementById('copy-note-' + id)?.value || '';
            try {
                await api(`/api/v1/internal/review/copies/${id}/resolve`, {
                    method: 'POST',
                    body: JSON.stringify({ resolution_note: note || null }),
                });
                showToast('Копия помечена как решённая');
                loadCopySummary();
                loadCopyQueue(copyPage);
                loadOverview();
            } catch (err) {
                showError('Ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ═══════════════════════════════════════
        // DOCUMENTS TAB
        // ═══════════════════════════════════════
        let docPage = 1;
        let docTotalPages = 1;

        async function loadDocSummary() {
            try {
                const data = await api('/api/v1/internal/review/documents-summary');
                const d = data.data || {};
                const topRC = d.topReasonCodes || [];
                document.getElementById('doc-summary-cards').innerHTML = [
                    metricHtml('Всего документов', d.totalDocuments, '', 'soft'),
                    metricHtml('Требуют проверки', d.needsReviewCount, '', 'alert'),
                    metricHtml('Решено', d.resolvedCount, '', 'accent-bg'),
                    metricHtml('Top Reason', topRC[0]?.reasonCode || '—', topRC[0] ? `${fmt(topRC[0].count)} записей` : '', 'soft'),
                ].join('');
            } catch { /* summary is optional */ }
        }

        async function loadDocQueue(page) {
            page = Math.max(1, page || 1);
            docPage = page;
            const reason = document.getElementById('doc-reason-filter').value.trim();
            const params = new URLSearchParams({ page, limit: 20 });
            if (reason) params.set('reason_code', reason);

            try {
                const data = await api(`/api/v1/internal/review/documents?${params}`);
                const items = data.data || [];
                const meta = data.meta || {};
                docTotalPages = meta.totalPages || 1;

                document.getElementById('doc-page-info').textContent = `Стр. ${meta.page || page} из ${docTotalPages} (всего: ${fmt(meta.total)})`;
                document.getElementById('doc-prev').disabled = docPage <= 1;
                document.getElementById('doc-next').disabled = docPage >= docTotalPages;

                if (!items.length) {
                    document.getElementById('doc-queue-body').innerHTML = '<tr><td colspan="7" class="empty">Нет документов в очереди проверки.</td></tr>';
                    return;
                }

                document.getElementById('doc-queue-body').innerHTML = items.map(item => {
                    const id = item.documentIdentity?.id || '';
                    return `
                    <tr id="doc-row-${id}">
                        <td><input type="checkbox" class="cb-row" data-entity="doc" data-id="${esc(id)}" onchange="updateDocBulk()"></td>
                        <td><span class="truncate-id" title="${esc(id)}">${esc(shortId(id))}</span></td>
                        <td>${esc(item.title?.titleRaw || item.title?.titleNormalized || '—')}</td>
                        <td>${esc(item.documentIdentity?.isbnRaw || item.documentIdentity?.isbnNormalized || '—')}</td>
                        <td>${reasonBadges(item.lifecycle?.reviewReasonCodes)}</td>
                        <td>${esc(shortDate(item.updatedAt))}</td>
                        <td>
                            <button class="button small" onclick="toggleDocAction('${esc(id)}')">Решить</button>
                            <button class="button small warn-outline" onclick="toggleDocFlag('${esc(id)}')">Флаг</button>
                            <button class="button small secondary" onclick="lookupDoc('${esc(id)}')">Обогатить</button>
                        </td>
                    </tr>
                    <tr class="action-row" id="doc-enrich-${id}" style="display:none">
                        <td colspan="7">
                            <div class="action-form" id="doc-enrich-content-${id}">
                                <span class="meta">Загрузка OpenLibrary…</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="action-row" id="doc-resolve-${id}" style="display:none">
                        <td colspan="7">
                            <div class="action-form">
                                <div class="field" style="flex:1">
                                    <label>Заметка решения (опционально)</label>
                                    <textarea id="doc-resolve-note-${id}" placeholder="Описание решения…"></textarea>
                                </div>
                                <button class="button small" onclick="resolveDoc('${esc(id)}')">Подтвердить</button>
                                <button class="button small secondary" onclick="toggleDocAction('${esc(id)}')">Отмена</button>
                            </div>
                        </td>
                    </tr>
                    <tr class="action-row" id="doc-flag-${id}" style="display:none">
                        <td colspan="7">
                            <div class="action-form">
                                <div class="field">
                                    <label>Reason Codes (через запятую)</label>
                                    <input id="doc-flag-codes-${id}" class="input" type="text" placeholder="DUPLICATE_ISBN, MISSING_AUTHOR">
                                </div>
                                <div class="field" style="flex:1">
                                    <label>Заметка (опционально)</label>
                                    <textarea id="doc-flag-note-${id}" placeholder="Почему документ нужно проверить…"></textarea>
                                </div>
                                <button class="button small danger" onclick="flagDoc('${esc(id)}')">Отправить флаг</button>
                                <button class="button small secondary" onclick="toggleDocFlag('${esc(id)}')">Отмена</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            } catch (err) {
                document.getElementById('doc-queue-body').innerHTML = `<tr><td colspan="7" class="empty">Ошибка загрузки: ${esc(err.message || '')}</td></tr>`;
            }
        }

        function toggleDocAction(id) {
            const row = document.getElementById('doc-resolve-' + id);
            document.getElementById('doc-flag-' + id).style.display = 'none';
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }

        function toggleDocFlag(id) {
            const row = document.getElementById('doc-flag-' + id);
            document.getElementById('doc-resolve-' + id).style.display = 'none';
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }

        async function resolveDoc(id) {
            const note = document.getElementById('doc-resolve-note-' + id)?.value || '';
            try {
                await api(`/api/v1/internal/review/documents/${id}/resolve`, {
                    method: 'POST',
                    body: JSON.stringify({ resolution_note: note || null }),
                });
                showToast('Документ помечен как решённый');
                loadDocSummary();
                loadDocQueue(docPage);
                loadOverview();
            } catch (err) {
                showError('Ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        async function flagDoc(id) {
            const codesRaw = document.getElementById('doc-flag-codes-' + id)?.value || '';
            const codes = codesRaw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
            if (!codes.length) { showError('Укажите хотя бы один reason code'); return; }
            const note = document.getElementById('doc-flag-note-' + id)?.value || '';
            try {
                await api(`/api/v1/internal/review/documents/${id}/flag`, {
                    method: 'POST',
                    body: JSON.stringify({ reason_codes: codes, flag_note: note || null }),
                });
                showToast('Документ отмечен для проверки');
                loadDocSummary();
                loadDocQueue(docPage);
                loadOverview();
            } catch (err) {
                showError('Ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ═══════════════════════════════════════
        // READERS TAB
        // ═══════════════════════════════════════
        let readerPage = 1;
        let readerTotalPages = 1;

        async function loadReaderSummary() {
            try {
                const data = await api('/api/v1/internal/review/readers-summary');
                const d = data.data || {};
                const topRC = d.topReasonCodes || [];
                document.getElementById('reader-summary-cards').innerHTML = [
                    metricHtml('Всего читателей', d.totalReaders, '', 'soft'),
                    metricHtml('Требуют проверки', d.needsReviewCount, '', 'alert'),
                    metricHtml('Решено', d.resolvedCount, '', 'accent-bg'),
                    metricHtml('Top Reason', topRC[0]?.reasonCode || '—', topRC[0] ? `${fmt(topRC[0].count)} записей` : '', 'soft'),
                ].join('');
            } catch { /* summary is optional */ }
        }

        async function loadReaderQueue(page) {
            page = Math.max(1, page || 1);
            readerPage = page;
            const reason = document.getElementById('reader-reason-filter').value.trim();
            const params = new URLSearchParams({ page, limit: 20 });
            if (reason) params.set('reason_code', reason);

            try {
                const data = await api(`/api/v1/internal/review/readers?${params}`);
                const items = data.data || [];
                const meta = data.meta || {};
                readerTotalPages = meta.totalPages || 1;

                document.getElementById('reader-page-info').textContent = `Стр. ${meta.page || page} из ${readerTotalPages} (всего: ${fmt(meta.total)})`;
                document.getElementById('reader-prev').disabled = readerPage <= 1;
                document.getElementById('reader-next').disabled = readerPage >= readerTotalPages;

                if (!items.length) {
                    document.getElementById('reader-queue-body').innerHTML = '<tr><td colspan="8" class="empty">Нет читателей в очереди проверки.</td></tr>';
                    return;
                }

                document.getElementById('reader-queue-body').innerHTML = items.map(item => {
                    const id = item.readerIdentity?.id || '';
                    return `
                    <tr id="reader-row-${id}">
                        <td><input type="checkbox" class="cb-row" data-entity="reader" data-id="${esc(id)}" onchange="updateReaderBulk()"></td>
                        <td><span class="truncate-id" title="${esc(id)}">${esc(shortId(id))}</span></td>
                        <td>${esc(item.readerIdentity?.fullName || '—')}</td>
                        <td>${esc(item.readerIdentity?.legacyCode || '—')}</td>
                        <td>${esc(shortDate(item.lifecycle?.registrationAt))}</td>
                        <td>${reasonBadges(item.lifecycle?.reviewReasonCodes)}</td>
                        <td>${esc(shortDate(item.updatedAt))}</td>
                        <td>
                            <button class="button small" onclick="toggleReaderResolve('${esc(id)}')">Решить</button>
                            <button class="button small secondary" onclick="loadReaderContacts('${esc(id)}')">Контакты</button>
                        </td>
                    </tr>
                    <tr class="action-row" id="reader-contacts-${id}" style="display:none">
                        <td colspan="8">
                            <div class="action-form" id="reader-contacts-content-${id}">
                                <span class="meta">Загрузка контактов…</span>
                            </div>
                        </td>
                    </tr>
                    <tr class="action-row" id="reader-action-${id}" style="display:none">
                        <td colspan="8">
                            <div class="action-form">
                                <div class="field" style="flex:1">
                                    <label>Заметка (опционально)</label>
                                    <textarea id="reader-note-${id}" placeholder="Описание решения…"></textarea>
                                </div>
                                <button class="button small" onclick="resolveReader('${esc(id)}')">Подтвердить</button>
                                <button class="button small secondary" onclick="toggleReaderResolve('${esc(id)}')">Отмена</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            } catch (err) {
                document.getElementById('reader-queue-body').innerHTML = `<tr><td colspan="8" class="empty">Ошибка загрузки: ${esc(err.message || '')}</td></tr>`;
            }
        }

        function toggleReaderResolve(id) {
            const row = document.getElementById('reader-action-' + id);
            row.style.display = row.style.display === 'none' ? '' : 'none';
        }

        async function resolveReader(id) {
            const note = document.getElementById('reader-note-' + id)?.value || '';
            try {
                await api(`/api/v1/internal/review/readers/${id}/resolve`, {
                    method: 'POST',
                    body: JSON.stringify({ resolution_note: note || null }),
                });
                showToast('Читатель помечен как решённый');
                loadReaderSummary();
                loadReaderQueue(readerPage);
                loadOverview();
            } catch (err) {
                showError('Ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ═══════════════════════════════════════
        // BULK SELECTION & ACTIONS
        // ═══════════════════════════════════════

        function getSelectedIds(entity) {
            return [...document.querySelectorAll(`.cb-row[data-entity="${entity}"]:checked`)].map(cb => cb.dataset.id);
        }

        // ── Copies bulk ──
        function toggleAllCopies(checked) {
            document.querySelectorAll('.cb-row[data-entity="copy"]').forEach(cb => { cb.checked = checked; });
            updateCopyBulk();
        }

        function updateCopyBulk() {
            const ids = getSelectedIds('copy');
            const bar = document.getElementById('copy-bulk-bar');
            document.getElementById('copy-bulk-count').textContent = `${ids.length} выбрано`;
            bar.classList.toggle('visible', ids.length > 0);
        }

        function clearCopySelection() {
            document.querySelectorAll('.cb-row[data-entity="copy"]').forEach(cb => { cb.checked = false; });
            document.getElementById('copy-cb-all').checked = false;
            updateCopyBulk();
        }

        async function bulkResolveCopies() {
            const ids = getSelectedIds('copy');
            if (!ids.length) return;
            const note = document.getElementById('copy-bulk-note').value.trim() || null;
            try {
                const result = await api('/api/v1/internal/review/copies/bulk-resolve', {
                    method: 'POST',
                    body: JSON.stringify({ ids, resolution_note: note }),
                });
                const s = result.summary || {};
                showToast(`Копии: ${s.succeeded} решено, ${s.failed} ошибок`);
                clearCopySelection();
                loadCopySummary();
                loadCopyQueue(copyPage);
                loadOverview();
            } catch (err) {
                showError('Bulk resolve ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ── Documents bulk ──
        function toggleAllDocs(checked) {
            document.querySelectorAll('.cb-row[data-entity="doc"]').forEach(cb => { cb.checked = checked; });
            updateDocBulk();
        }

        function updateDocBulk() {
            const ids = getSelectedIds('doc');
            const bar = document.getElementById('doc-bulk-bar');
            document.getElementById('doc-bulk-count').textContent = `${ids.length} выбрано`;
            bar.classList.toggle('visible', ids.length > 0);
        }

        function clearDocSelection() {
            document.querySelectorAll('.cb-row[data-entity="doc"]').forEach(cb => { cb.checked = false; });
            document.getElementById('doc-cb-all').checked = false;
            updateDocBulk();
            hideDocBulkFlag();
        }

        function showDocBulkFlag() {
            document.getElementById('doc-bulk-flag-bar').classList.add('visible');
        }

        function hideDocBulkFlag() {
            document.getElementById('doc-bulk-flag-bar').classList.remove('visible');
        }

        async function bulkResolveDocs() {
            const ids = getSelectedIds('doc');
            if (!ids.length) return;
            const note = document.getElementById('doc-bulk-note').value.trim() || null;
            try {
                const result = await api('/api/v1/internal/review/documents/bulk-resolve', {
                    method: 'POST',
                    body: JSON.stringify({ ids, resolution_note: note }),
                });
                const s = result.summary || {};
                showToast(`Документы: ${s.succeeded} решено, ${s.failed} ошибок`);
                clearDocSelection();
                loadDocSummary();
                loadDocQueue(docPage);
                loadOverview();
            } catch (err) {
                showError('Bulk resolve ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        async function bulkFlagDocs() {
            const ids = getSelectedIds('doc');
            if (!ids.length) return;
            const codesRaw = document.getElementById('doc-bulk-flag-codes').value || '';
            const codes = codesRaw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
            if (!codes.length) { showError('Укажите хотя бы один reason code'); return; }
            const note = document.getElementById('doc-bulk-flag-note').value.trim() || null;
            try {
                const result = await api('/api/v1/internal/review/documents/bulk-flag', {
                    method: 'POST',
                    body: JSON.stringify({ ids, reason_codes: codes, flag_note: note }),
                });
                const s = result.summary || {};
                showToast(`Документы: ${s.succeeded} отмечено, ${s.failed} ошибок`);
                clearDocSelection();
                loadDocSummary();
                loadDocQueue(docPage);
                loadOverview();
            } catch (err) {
                showError('Bulk flag ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ── Readers bulk ──
        function toggleAllReaders(checked) {
            document.querySelectorAll('.cb-row[data-entity="reader"]').forEach(cb => { cb.checked = checked; });
            updateReaderBulk();
        }

        function updateReaderBulk() {
            const ids = getSelectedIds('reader');
            const bar = document.getElementById('reader-bulk-bar');
            document.getElementById('reader-bulk-count').textContent = `${ids.length} выбрано`;
            bar.classList.toggle('visible', ids.length > 0);
        }

        function clearReaderSelection() {
            document.querySelectorAll('.cb-row[data-entity="reader"]').forEach(cb => { cb.checked = false; });
            document.getElementById('reader-cb-all').checked = false;
            updateReaderBulk();
        }

        async function bulkResolveReaders() {
            const ids = getSelectedIds('reader');
            if (!ids.length) return;
            const note = document.getElementById('reader-bulk-note').value.trim() || null;
            try {
                const result = await api('/api/v1/internal/review/readers/bulk-resolve', {
                    method: 'POST',
                    body: JSON.stringify({ ids, resolution_note: note }),
                });
                const s = result.summary || {};
                showToast(`Читатели: ${s.succeeded} решено, ${s.failed} ошибок`);
                clearReaderSelection();
                loadReaderSummary();
                loadReaderQueue(readerPage);
                loadOverview();
            } catch (err) {
                showError('Bulk resolve ошибка: ' + (err.message || err.error || 'Unknown'));
            }
        }

        // ═══════════════════════════════════════
        // Reader contact functions
        // ═══════════════════════════════════════
        async function loadContactStats() {
            try {
                const data = await api('/api/v1/internal/reader-contacts/stats');
                const d = data.data || {};
                document.getElementById('contact-stats-cards').innerHTML = [
                    metricHtml('Всего контактов', d.totalContacts, '', 'soft'),
                    metricHtml('Заглушки', d.placeholderCount, 'нет значения', 'alert'),
                    metricHtml('Валидный формат', d.validFormatCount, '', 'accent-bg'),
                    metricHtml('Невалидный', d.invalidFormatCount, '', 'warn-soft'),
                    metricHtml('С email', d.readersWithValidEmail, `из ${fmt(d.totalReaders)} читателей`, 'accent-bg'),
                    metricHtml('Без email', d.readersWithoutEmail, '', 'alert'),
                ].join('');
            } catch { /* optional */ }
        }

        async function bulkNormalizeContacts() {
            const st = document.getElementById('contact-norm-status');
            st.innerHTML = '<span class="meta">Нормализация контактов…</span>';
            try {
                const data = await api('/api/v1/internal/reader-contacts/bulk-normalize', 'POST', { limit: 500 });
                const r = data.data || {};
                st.innerHTML = `<span class="badge entity">Обработано: ${r.processed} | Обновлено: ${r.updated} | Валидных: ${r.valid} | Невалидных: ${r.invalid}</span>`;
                loadContactStats();
            } catch (err) {
                st.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>`;
            }
        }

        async function validateContactPrompt() {
            const type = prompt('Тип контакта (EMAIL или PHONE):');
            if (!type) return;
            const value = prompt('Значение для проверки:');
            if (!value) return;
            const st = document.getElementById('contact-norm-status');
            try {
                const data = await api('/api/v1/internal/reader-contacts/validate', 'POST', { contact_type: type, value });
                const r = data.data || {};
                const badge = r.valid ? 'entity' : 'reason';
                st.innerHTML = `<span class="badge ${badge}">${esc(r.normalized)} — ${r.valid ? '✅ Валиден' : '❌ Невалиден'}</span>${r.error ? ` <span class="meta">${esc(r.error)}</span>` : ''}`;
            } catch (err) {
                st.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>`;
            }
        }

        async function loadReaderContacts(readerId) {
            const row = document.getElementById(`reader-contacts-${readerId}`);
            const content = document.getElementById(`reader-contacts-content-${readerId}`);
            if (!row || !content) return;

            if (row.style.display !== 'none') { row.style.display = 'none'; return; }
            row.style.display = '';
            content.innerHTML = '<span class="meta">Загрузка контактов…</span>';

            try {
                const data = await api(`/api/v1/internal/reader-contacts/${readerId}`);
                const r = data.data || {};
                const contacts = r.contacts || [];

                let html = '<div style="display:grid;gap:10px;width:100%">';
                html += `<div><strong>${esc(r.fullName || '—')}</strong> (код: ${esc(r.legacyCode || '—')})</div>`;

                if (contacts.length) {
                    html += '<table style="width:100%;font-size:13px"><thead><tr><th>Тип</th><th>Значение</th><th>Статус</th><th></th></tr></thead><tbody>';
                    contacts.forEach(c => {
                        const statusBadge = c.isPlaceholder ? '<span class="badge reason">заглушка</span>'
                            : c.isValidFormat ? '<span class="badge entity">✅ валиден</span>'
                            : '<span class="badge reason">❌ невалиден</span>';
                        html += `<tr>
                            <td>${esc(c.contactType)}</td>
                            <td>${esc(c.valueRaw || '—')} ${c.valueNormalized && c.valueNormalized !== c.valueRaw ? '<span class="meta">→ '+esc(c.valueNormalized)+'</span>' : ''}</td>
                            <td>${statusBadge}${c.isPrimary ? ' <span class="badge entity">основной</span>' : ''}</td>
                            <td><button class="button small secondary" onclick="editContactPrompt('${esc(c.id)}','${esc(readerId)}')">Изменить</button></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div class="meta">Нет контактов.</div>';
                }

                html += `<div style="display:flex;gap:8px;margin-top:8px">
                    <button class="button small" onclick="addContactPrompt('${esc(readerId)}')">+ Добавить контакт</button>
                    <button class="button small secondary" onclick="document.getElementById('reader-contacts-${esc(readerId)}').style.display='none'">Закрыть</button>
                </div></div>`;
                content.innerHTML = html;
            } catch (err) {
                content.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>
                    <button class="button small secondary" onclick="document.getElementById('reader-contacts-${readerId}').style.display='none'">Закрыть</button>`;
            }
        }

        async function editContactPrompt(contactId, readerId) {
            const value = prompt('Новое значение контакта:');
            if (value === null) return;
            try {
                await api(`/api/v1/internal/reader-contacts/${contactId}/update`, 'PUT', { value });
                showToast('Контакт обновлён');
                loadReaderContacts(readerId); // close
                loadReaderContacts(readerId); // reopen with fresh data
                loadContactStats();
                loadReaderSummary();
            } catch (err) {
                alert('Ошибка: ' + (err.message || ''));
            }
        }

        async function addContactPrompt(readerId) {
            const type = prompt('Тип (EMAIL или PHONE):', 'EMAIL');
            if (!type) return;
            const value = prompt('Значение:');
            if (!value) return;
            try {
                await api(`/api/v1/internal/reader-contacts/${readerId}/add`, 'POST', { contact_type: type, value });
                showToast('Контакт добавлен');
                loadReaderContacts(readerId); // close
                loadReaderContacts(readerId); // reopen
                loadContactStats();
                loadReaderSummary();
                loadReaderQueue(readerPage);
            } catch (err) {
                alert('Ошибка: ' + (err.message || ''));
            }
        }

        // ═══════════════════════════════════════
        // Enrichment functions
        // ═══════════════════════════════════════
        async function loadEnrichmentStats() {
            try {
                const data = await api('/api/v1/internal/enrichment/stats');
                const d = data.data || {};
                const g = d.gaps || {};
                document.getElementById('enrichment-stats-cards').innerHTML = [
                    metricHtml('Без ISBN', g.missingIsbn, `из ${fmt(d.totalDocuments)} документов`, 'alert'),
                    metricHtml('Невалидный ISBN', g.invalidIsbn, '', 'warn-soft'),
                    metricHtml('Валидный ISBN', g.validIsbn, '', 'accent-bg'),
                    metricHtml('Обогащаемых', d.enrichableByIsbn, 'есть ISBN, нет метаданных', 'soft'),
                    metricHtml('Нет года', g.missingYear, '', 'soft'),
                    metricHtml('Нет издателя', g.missingPublisher, '', 'soft'),
                ].join('');
            } catch { /* enrichment stats optional */ }
        }

        async function bulkValidateAll() {
            const st = document.getElementById('enrichment-status');
            st.innerHTML = '<span class="meta">Валидация ISBN… (до 500 за раз)</span>';
            try {
                const data = await api('/api/v1/internal/enrichment/bulk-validate', 'POST', { limit: 500 });
                const r = data.data || {};
                st.innerHTML = `<span class="badge entity">Обработано: ${r.processed} | Валидных: ${r.valid} | Невалидных: ${r.invalid}</span>`;
                loadEnrichmentStats();
            } catch (err) {
                st.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>`;
            }
        }

        async function checkIsbnPrompt() {
            const isbn = prompt('Введите ISBN для проверки:');
            if (!isbn) return;
            const st = document.getElementById('enrichment-status');
            try {
                const data = await api('/api/v1/internal/enrichment/check-isbn', 'POST', { isbn });
                const r = data.data || {};
                const badge = r.valid ? 'entity' : 'reason';
                st.innerHTML = `<span class="badge ${badge}">${esc(r.isbn)} — ${r.valid ? '✅ Валиден' : '❌ Невалиден'} (${esc(r.format || 'unknown')})</span>${r.error ? ` <span class="meta">${esc(r.error)}</span>` : ''}`;
            } catch (err) {
                st.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>`;
            }
        }

        async function lookupDoc(docId) {
            const row = document.getElementById(`doc-enrich-${docId}`);
            const content = document.getElementById(`doc-enrich-content-${docId}`);
            if (!row || !content) return;

            // Toggle visibility
            if (row.style.display !== 'none') { row.style.display = 'none'; return; }
            row.style.display = '';
            content.innerHTML = '<span class="meta">Поиск в OpenLibrary…</span>';

            try {
                const data = await api(`/api/v1/internal/enrichment/lookup/${docId}`);
                const r = data.data || {};
                const suggs = r.suggestions || [];
                const lookup = r.lookup || {};
                const meta = lookup.metadata || {};

                if (!lookup.found) {
                    content.innerHTML = `<span class="meta">Данные не найдены в OpenLibrary.</span>
                        <button class="button small secondary" onclick="document.getElementById('doc-enrich-${docId}').style.display='none'">Закрыть</button>`;
                    return;
                }

                let html = '<div style="display:grid;gap:10px;width:100%">';
                html += `<div><strong>OpenLibrary:</strong> ${esc(meta.title || '—')} ${meta.subtitle ? '— '+esc(meta.subtitle) : ''}</div>`;
                if (meta.authors?.length) html += `<div><strong>Авторы:</strong> ${meta.authors.map(a => esc(a)).join(', ')}</div>`;
                if (meta.publishYear) html += `<div><strong>Год:</strong> ${meta.publishYear}</div>`;
                if (meta.publishers?.length) html += `<div><strong>Издатели:</strong> ${meta.publishers.map(p => esc(p)).join(', ')}</div>`;
                if (meta.numberOfPages) html += `<div><strong>Страниц:</strong> ${meta.numberOfPages}</div>`;

                if (suggs.length) {
                    html += '<div style="margin-top:8px"><strong>Предложения обогащения:</strong></div>';
                    suggs.forEach((s, i) => {
                        const checked = s.confidence === 'high' ? 'checked' : '';
                        html += `<label style="display:flex;gap:8px;align-items:center;padding:4px 0">
                            <input type="checkbox" class="enrich-check" data-doc="${esc(docId)}" data-field="${esc(s.column)}" data-value="${esc(String(s.suggested))}" ${checked}>
                            <span>${esc(s.field)}: <strong>${esc(String(s.suggested))}</strong></span>
                            <span class="badge ${s.confidence === 'high' ? 'entity' : 'reason'}">${esc(s.confidence)}</span>
                        </label>`;
                    });
                    html += `<button class="button small" onclick="applyEnrichment('${esc(docId)}')">Применить выбранное</button>`;
                } else {
                    html += '<div class="meta" style="margin-top:8px">Нет предложений — текущие данные уже полные.</div>';
                }

                html += `<button class="button small secondary" onclick="document.getElementById('doc-enrich-${esc(docId)}').style.display='none'" style="margin-top:4px">Закрыть</button>`;
                html += '</div>';
                content.innerHTML = html;
            } catch (err) {
                content.innerHTML = `<span class="badge reason">Ошибка: ${esc(err.message || '')}</span>
                    <button class="button small secondary" onclick="document.getElementById('doc-enrich-${docId}').style.display='none'">Закрыть</button>`;
            }
        }

        async function applyEnrichment(docId) {
            const checks = document.querySelectorAll(`.enrich-check[data-doc="${docId}"]:checked`);
            if (!checks.length) { alert('Выберите хотя бы одно поле.'); return; }

            const fields = {};
            checks.forEach(cb => {
                let val = cb.dataset.value;
                if (cb.dataset.field === 'publication_year') val = parseInt(val, 10);
                if (cb.dataset.field === 'isbn_is_valid') val = val === 'true' || val === '1';
                fields[cb.dataset.field] = val;
            });

            try {
                const data = await api(`/api/v1/internal/enrichment/apply/${docId}`, 'POST', { fields, source: 'openlibrary' });
                const r = data.data || {};
                const content = document.getElementById(`doc-enrich-content-${docId}`);
                if (content) {
                    content.innerHTML = `<span class="badge entity">✅ Применено: ${(r.applied || []).join(', ')}</span>
                        ${r.skipped?.length ? `<span class="badge reason">Пропущено: ${r.skipped.join(', ')}</span>` : ''}
                        <button class="button small secondary" onclick="document.getElementById('doc-enrich-${docId}').style.display='none'" style="margin-left:8px">Закрыть</button>`;
                }
                loadEnrichmentStats();
            } catch (err) {
                alert('Ошибка применения: ' + (err.message || ''));
            }
        }

        // ═══════════════════════════════════════
        // Initial load
        // ═══════════════════════════════════════
        tabLoadedFlags.overview = true;
        loadOverview();
    </script>
</body>
</html>
