<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Review</title>
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

        .shell {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .hero,
        .panel {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 28px;
        }

        .panel {
            padding: 22px;
            margin-top: 20px;
        }

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

        .subtitle,
        .meta,
        .empty,
        .error-box {
            color: var(--muted);
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }

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

        .field {
            display: grid;
            gap: 6px;
            min-width: 180px;
        }

        .field label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .select,
        .button {
            min-height: 44px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            padding: 0 14px;
            font: inherit;
            transition: background-color .18s ease, border-color .18s ease, transform .18s ease;
        }

        .select:hover,
        .button:hover {
            transform: translate3d(0, -1px, 0);
        }

        .button {
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .button.secondary {
            background: #fff;
            color: var(--ink);
            border-color: var(--line);
        }

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

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 920px;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid var(--line);
            background: #fff;
        }

        .badge.high,
        .badge.critical {
            background: var(--danger-soft);
            color: var(--danger);
            border-color: rgba(153, 27, 27, 0.15);
        }

        .badge.medium {
            background: var(--warn-soft);
            color: var(--warn);
            border-color: rgba(154, 52, 18, 0.15);
        }

        .badge.low,
        .badge.open {
            background: var(--ok-soft);
            color: var(--ok);
            border-color: rgba(22, 101, 52, 0.15);
        }

        .pager {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-top: 18px;
        }

        .pager-actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 720px) {
            .shell { width: min(100% - 20px, 1200px); }
            .hero, .panel { padding: 18px; border-radius: 8px; }
            .pager { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="eyebrow">Internal Read-Only Review</div>
            <h1>Quality Issues Overview</h1>
            <p class="subtitle">
                Лёгкая внутренняя страница просмотра quality issues, читающая уже существующий DB-backed endpoint без write-операций.
            </p>
            <div class="nav-row">
                <a class="nav-link primary" href="/internal/dashboard">Вернуться к dashboard</a>
                <a class="nav-link" href="/catalog">Перейти в каталог</a>
            </div>
            <div class="status-row" id="status-row">
                <div class="status-chip">Загрузка issues…</div>
            </div>
            <div class="error-box" id="review-error"></div>
        </section>

        <section class="panel">
            <div class="toolbar">
                <div class="field">
                    <label for="severity-filter">Severity</label>
                    <select id="severity-filter" class="select">
                        <option value="">All</option>
                        <option value="critical">CRITICAL</option>
                        <option value="high">HIGH</option>
                        <option value="medium">MEDIUM</option>
                        <option value="low">LOW</option>
                    </select>
                </div>

                <div class="field">
                    <label for="status-filter">Status</label>
                    <select id="status-filter" class="select">
                        <option value="">All</option>
                        <option value="open">OPEN</option>
                    </select>
                </div>

                <button id="apply-filters" class="button">Apply Filters</button>
                <button id="reset-filters" class="button secondary">Reset</button>
            </div>
        </section>

        <section class="panel">
            <div class="meta" id="review-meta">Загрузка списка issues…</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Issue Code</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Source Schema</th>
                            <th>Source Table</th>
                            <th>Source Key</th>
                            <th>Summary</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody id="issues-body">
                        <tr>
                            <td colspan="8" class="empty">Загрузка данных…</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <div id="page-info" class="meta"></div>
                <div class="pager-actions">
                    <button id="prev-page" class="button secondary">Previous</button>
                    <button id="next-page" class="button secondary">Next</button>
                </div>
            </div>
        </section>
    </main>

    <script>
        const REVIEW_ISSUES_ENDPOINT = '/api/v1/review/issues';
        let currentPage = 1;
        const perPage = 20;
        let totalPages = 1;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text ?? '');
            return div.innerHTML;
        }

        function badgeClass(value) {
            const normalized = String(value || '').toLowerCase();
            return normalized;
        }

        function buildQuery() {
            const params = new URLSearchParams();
            const severity = document.getElementById('severity-filter').value;
            const status = document.getElementById('status-filter').value;

            if (severity) params.set('severity', severity);
            if (status) params.set('status', status);
            params.set('page', String(currentPage));
            params.set('limit', String(perPage));

            return params.toString();
        }

        async function fetchIssues() {
            const query = buildQuery();
            const response = await fetch(`${REVIEW_ISSUES_ENDPOINT}?${query}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        }

        function renderStatus(ok) {
            document.getElementById('status-row').innerHTML = `
                <div class="status-chip ${ok ? 'ok' : 'warn'}">Review issues endpoint: ${ok ? 'OK' : 'Unavailable'}</div>
            `;
        }

        function renderTable(payload) {
            const issues = Array.isArray(payload?.data) ? payload.data : [];
            const meta = payload?.meta || {};
            const tbody = document.getElementById('issues-body');
            const metaBox = document.getElementById('review-meta');
            const pageInfo = document.getElementById('page-info');

            totalPages = Number(meta.totalPages || meta.total_pages || 1);

            metaBox.textContent = `Total issues: ${Number(meta.total || 0).toLocaleString('ru-RU')}`;
            pageInfo.textContent = `Page ${meta.page || currentPage} of ${totalPages}`;

            if (!issues.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty">Issues not found for current filters.</td></tr>';
                return;
            }

            tbody.innerHTML = issues.map((item) => `
                <tr>
                    <td>${escapeHtml(item.issueCode)}</td>
                    <td><span class="badge ${badgeClass(item.severity)}">${escapeHtml(item.severity)}</span></td>
                    <td><span class="badge ${badgeClass(item.status)}">${escapeHtml(item.status)}</span></td>
                    <td>${escapeHtml(item.sourceSchema)}</td>
                    <td>${escapeHtml(item.sourceTable)}</td>
                    <td>${escapeHtml(item.sourceKey)}</td>
                    <td>${escapeHtml(item.summary)}</td>
                    <td>${escapeHtml(item.createdAt)}</td>
                </tr>
            `).join('');
        }

        function updatePagerButtons() {
            document.getElementById('prev-page').disabled = currentPage <= 1;
            document.getElementById('next-page').disabled = currentPage >= totalPages;
        }

        async function loadIssues() {
            const errorBox = document.getElementById('review-error');
            errorBox.style.display = 'none';

            try {
                const payload = await fetchIssues();
                renderStatus(true);
                renderTable(payload);
                updatePagerButtons();
            } catch (error) {
                renderStatus(false);
                document.getElementById('issues-body').innerHTML = '<tr><td colspan="8" class="empty">Не удалось загрузить issues.</td></tr>';
                errorBox.style.display = 'block';
                errorBox.textContent = 'Endpoint /api/v1/review/issues временно недоступен. Страница остаётся в read-only режиме.';
            }
        }

        document.getElementById('apply-filters').addEventListener('click', () => {
            currentPage = 1;
            loadIssues();
        });

        document.getElementById('reset-filters').addEventListener('click', () => {
            document.getElementById('severity-filter').value = '';
            document.getElementById('status-filter').value = '';
            currentPage = 1;
            loadIssues();
        });

        document.getElementById('prev-page').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage -= 1;
                loadIssues();
            }
        });

        document.getElementById('next-page').addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage += 1;
                loadIssues();
            }
        });

        loadIssues();
    </script>
</body>
</html>
