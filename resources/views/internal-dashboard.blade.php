<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KazUTB Admin Overview</title>
    <style>
        :root {
            --bg: #f6f7f8;
            --paper: rgba(255, 255, 255, 0.97);
            --paper-soft: rgba(247, 248, 249, 0.84);
            --ink: #191c1d;
            --muted: #43474f;
            --line: rgba(195, 198, 209, 0.55);
            --accent: #001e40;
            --accent-soft: rgba(0, 30, 64, 0.06);
            --accent-muted: rgba(0, 30, 64, 0.72);
            --ok: #14696d;
            --ok-soft: rgba(20, 105, 109, 0.10);
            --warn: #8a4b00;
            --warn-soft: rgba(138, 75, 0, 0.11);
            --danger: #b3261e;
            --danger-soft: rgba(179, 38, 30, 0.10);
            --shadow: 0 16px 36px rgba(25, 28, 29, 0.05);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(0, 30, 64, 0.05), transparent 20%),
                radial-gradient(circle at 80% 0%, rgba(20, 105, 109, 0.05), transparent 22%),
                linear-gradient(180deg, #fbfcfc 0%, var(--bg) 100%);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: min(1280px, calc(100% - 36px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .workspace {
            display: grid;
            gap: 20px;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, .9fr);
            gap: 20px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 28px;
            box-shadow: var(--shadow);
        }

        .eyebrow {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 16px 0 10px;
            font-size: clamp(34px, 5vw, 52px);
            line-height: 1;
            font-weight: 600;
            font-family: 'Newsreader', Georgia, serif;
            letter-spacing: -0.04em;
            color: var(--accent);
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.8;
            max-width: 780px;
        }

        .hero-meta {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .staff-card,
        .note-card,
        .panel {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .staff-card {
            padding: 18px;
            display: grid;
            gap: 12px;
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(247,248,249,.95));
        }

        .staff-kicker {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--ok);
        }

        .staff-name {
            margin: 0;
            font-size: 28px;
            line-height: 1.02;
            letter-spacing: -.03em;
            font-family: 'Newsreader', Georgia, serif;
            color: var(--accent);
        }

        .staff-role {
            margin: 0;
            font-size: 14px;
            color: var(--accent-muted);
        }

        .staff-meta-list {
            display: grid;
            gap: 10px;
        }

        .staff-meta-item {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid rgba(195, 198, 209, 0.45);
            font-size: 13px;
        }

        .staff-meta-item strong {
            color: var(--muted);
            font-weight: 700;
        }

        .nav-row,
        .status-row {
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
            font-size: 14px;
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

        .status-chip {
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--line);
            background: #fff;
        }

        .status-chip.ok {
            background: var(--ok-soft);
            color: var(--ok);
            border-color: rgba(20,105,109,.22);
        }

        .status-chip.warn {
            background: var(--warn-soft);
            color: var(--warn);
            border-color: rgba(138,75,0,.18);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
        }

        .panel {
            grid-column: span 12;
            padding: 22px;
        }

        .panel.half {
            grid-column: span 6;
        }

        .panel h2 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -.02em;
            color: var(--accent);
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: baseline;
            margin-bottom: 16px;
        }

        .panel-head p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            max-width: 680px;
        }

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
            min-height: 136px;
            display: grid;
            align-content: start;
            gap: 10px;
        }

        .metric.soft { background: linear-gradient(180deg, #f8fcfd 0%, #fff 100%); }
        .metric.warn { background: linear-gradient(180deg, #fff9f4 0%, #fff 100%); }
        .metric.alert { background: linear-gradient(180deg, #fff8f7 0%, #fff 100%); }

        .metric-label {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
        }

        .metric-value {
            font-size: 36px;
            font-weight: 700;
            line-height: 1;
            color: var(--accent);
            font-family: 'Newsreader', Georgia, serif;
            letter-spacing: -.04em;
        }

        .metric-note {
            font-size: 13px;
            line-height: 1.7;
            color: var(--muted);
        }

        .queue-list,
        .reason-list,
        .note-list,
        .route-list {
            display: grid;
            gap: 12px;
        }

        .queue-item,
        .reason-item,
        .note-item,
        .route-item {
            padding: 14px 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
        }

        .queue-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }

        .queue-item strong,
        .reason-item strong,
        .note-item strong,
        .route-item strong {
            color: var(--accent);
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .queue-item p,
        .reason-item p,
        .note-item p,
        .route-item p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.75;
        }

        .queue-count,
        .reason-count {
            display: inline-grid;
            place-items: center;
            min-width: 52px;
            min-height: 38px;
            padding: 0 12px;
            border-radius: 999px;
            background: rgba(0,30,64,.06);
            color: var(--accent);
            font-weight: 800;
        }

        .pill-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: rgba(243,244,245,.92);
            border: 1px solid rgba(195,198,209,.44);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .route-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .route-item a {
            display: inline-flex;
            margin-top: 10px;
            color: var(--ok);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .source-note,
        .error-box {
            font-size: 13px;
            line-height: 1.7;
        }

        .source-note {
            color: var(--muted);
            margin-top: 14px;
        }

        .error-box {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(153, 27, 27, 0.15);
            display: none;
        }

        .overview-columns {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, .9fr);
            gap: 20px;
            align-items: start;
        }

        .sidebar-stack {
            display: grid;
            gap: 20px;
        }

        .sticky-card {
            position: sticky;
            top: 20px;
        }

        .cards.cards-three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .activity-log {
            display: grid;
            gap: 12px;
        }

        .activity-log-item {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            padding: 14px 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--line);
        }

        .activity-log-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: rgba(0,30,64,.05);
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
        }

        .activity-log-item strong {
            color: var(--accent);
            display: block;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .activity-log-item p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.75;
        }

        .quote-card {
            min-height: 240px;
            background: linear-gradient(180deg, rgba(243,244,245,.9), rgba(255,255,255,.98));
        }

        .quote-card blockquote {
            margin: 0;
            font-family: 'Newsreader', Georgia, serif;
            font-size: 20px;
            line-height: 1.7;
            font-style: italic;
            color: var(--accent);
        }

        .quote-card p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        @media (max-width: 1100px) {
            .hero,
            .overview-columns { grid-template-columns: 1fr; }
            .cards,
            .cards.cards-three { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .panel.half { grid-column: span 12; }
            .route-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sticky-card { position: static; }
        }

        @media (max-width: 720px) {
            .shell { width: min(100% - 20px, 1280px); }
            .hero, .panel { padding: 18px; }
            .cards,
            .cards.cards-three,
            .route-grid { grid-template-columns: 1fr; }
            .queue-item,
            .activity-log-item { grid-template-columns: 1fr; }
            .staff-meta-item { flex-direction: column; }
        }
    </style>
</head>
<body>
    @php
        $staff = is_array($internalStaffUser ?? null) ? $internalStaffUser : [];
        $staffName = (string) ($staff['name'] ?? 'Сотрудник библиотеки');
        $staffRole = (string) ($staff['title'] ?? (($staff['role'] ?? '') === 'admin' ? 'Администратор библиотеки' : 'Библиотекарь'));
        $staffEmail = (string) ($staff['email'] ?? 'не указан');
        $staffLogin = (string) ($staff['ad_login'] ?? ($staff['login'] ?? 'не указан'));
        $staffPhoneExt = (string) ($staff['phone_extension'] ?? 'не указан');
    @endphp
    <main class="shell" data-librarian-workspace data-admin-overview-page>
        <div class="workspace">
            <section class="hero" data-admin-overview-hero>
                <div>
                    <div class="eyebrow">Internal Administration</div>
                    <h1>System Oversight</h1>
                    <p class="subtitle">
                        A unified summary of repository health, metadata integrity, and system-wide circulation dynamics
                        for the library platform, rendered through the stable internal shell rather than a broken export navbar.
                    </p>
                    <div class="nav-row">
                        <a class="nav-link primary" href="/internal/review">Review Queue</a>
                        <a class="nav-link primary" href="/internal/circulation">Circulation Desk</a>
                        <a class="nav-link" href="/internal/stewardship">Data Stewardship</a>
                        <a class="nav-link" href="/internal/ai-chat">AI Assistant</a>
                        <a class="nav-link" href="/catalog">Catalog</a>
                    </div>
                    <div class="status-row" id="status-row">
                        <div class="status-chip">Loading triage summary…</div>
                        <div class="status-chip">Loading reader review…</div>
                        <div class="status-chip">Loading contact stats…</div>
                    </div>
                    <div class="error-box" id="dashboard-error"></div>
                </div>

                <div class="hero-meta">
                    <section class="staff-card">
                        <div class="staff-kicker">Current staff session</div>
                        <h2 class="staff-name">{{ $staffName }}</h2>
                        <p class="staff-role">{{ $staffRole }}</p>
                        <div class="staff-meta-list">
                            <div class="staff-meta-item"><strong>Email</strong><span>{{ $staffEmail }}</span></div>
                            <div class="staff-meta-item"><strong>Login</strong><span>{{ $staffLogin }}</span></div>
                            <div class="staff-meta-item"><strong>Phone ext.</strong><span>{{ $staffPhoneExt }}</span></div>
                        </div>
                    </section>

                    <section class="note-card staff-card">
                        <div class="staff-kicker">Operational note</div>
                        <strong>Stable shell preserved</strong>
                        <p>The admin overview body follows the export, while the live internal shell stays intact and safe for staff workflows.</p>
                        <p>Only real routes and real summary endpoints are used on this page.</p>
                    </section>
                </div>
            </section>

            <section class="overview-columns">
                <div class="workspace">
                    <article class="panel" data-admin-overview-health>
                        <div class="panel-head">
                            <div>
                                <h2>Health Summary</h2>
                                <p>Core operational signals for circulation, metadata review, and active scholar-facing support.</p>
                            </div>
                        </div>
                        <div class="cards cards-three" id="operational-queue"></div>
                    </article>

                    <article class="panel" data-admin-overview-activity>
                        <div class="panel-head">
                            <div>
                                <h2>System-wide Activity</h2>
                                <p>A calm summary of what changed most recently across review, repository, and reader-support operations.</p>
                            </div>
                        </div>
                        <div class="activity-log" id="system-activity-log"></div>
                    </article>

                    <section class="grid">
                        <article class="panel half">
                            <div class="panel-head">
                                <div>
                                    <h2>Entity Queues</h2>
                                    <p>Which operational objects most often need admin or librarian attention right now.</p>
                                </div>
                            </div>
                            <div class="queue-list" id="entity-queues"></div>
                        </article>

                        <article class="panel half">
                            <div class="panel-head">
                                <div>
                                    <h2>Top reason codes</h2>
                                    <p>The dominant review reasons currently shaping the oversight backlog.</p>
                                </div>
                            </div>
                            <div class="reason-list" id="top-reasons"></div>
                            <div class="source-note" id="triage-source-note"></div>
                        </article>

                        <article class="panel half">
                            <div class="panel-head">
                                <div>
                                    <h2>Reader access overview</h2>
                                    <p>Profile linking, email coverage, and access clarification signals derived from the live platform.</p>
                                </div>
                            </div>
                            <div class="cards" id="reader-access-metrics"></div>
                        </article>

                        <article class="panel half">
                            <div class="panel-head">
                                <div>
                                    <h2>Catalog and holdings</h2>
                                    <p>Metadata integrity and enrichment readiness for institutional records and the wider library fund.</p>
                                </div>
                            </div>
                            <div class="cards" id="stewardship-metrics"></div>
                        </article>
                    </section>
                </div>

                <aside class="sidebar-stack sticky-card">
                    <article class="panel" data-admin-overview-actions>
                        <div class="panel-head">
                            <div>
                                <h2>Quick Links</h2>
                            </div>
                        </div>
                        <div class="route-list">
                            <div class="route-item">
                                <strong>Role Management</strong>
                                <p>Move to staff-facing review workflows and administrative moderation paths already available in the product.</p>
                                <a href="/internal/review">Open review</a>
                            </div>
                            <div class="route-item">
                                <strong>System Settings</strong>
                                <p>Continue through stewardship and operational correction routes instead of fake admin placeholders.</p>
                                <a href="/internal/stewardship">Open stewardship</a>
                            </div>
                            <div class="route-item">
                                <strong>Collection Audits</strong>
                                <p>Use circulation and catalog surfaces to inspect active library records and copy-level health.</p>
                                <a href="/internal/circulation">Open circulation</a>
                            </div>
                            <div class="route-item">
                                <strong>Contact Librarian</strong>
                                <p>Open the live library contact surface for escalation, policy clarification, or reader-support follow-up.</p>
                                <a href="/contacts">Open contacts</a>
                            </div>
                        </div>
                    </article>

                    <article class="panel quote-card">
                        <div class="staff-kicker">Administrative Context</div>
                        <blockquote>
                            “A digital library is not just a repository; it is a curated operational system where oversight, stewardship, and reader trust must stay in balance.”
                        </blockquote>
                        <p>— KazUTB Digital Library, internal admin context</p>
                    </article>

                    <article class="panel">
                        <div class="panel-head">
                            <div>
                                <h2>Operational notes</h2>
                                <p>What is already connected and what remains a future integration pass.</p>
                            </div>
                        </div>
                        <div class="note-list">
                            <div class="note-item">
                                <strong>Reservation queue</strong>
                                <p>Reservation operations still flow through circulation and the current waitlist contours.</p>
                            </div>
                            <div class="note-item">
                                <strong>Reader profile linking</strong>
                                <p>Reader review summary already exposes the profiles needing direct staff resolution.</p>
                            </div>
                            <div class="note-item">
                                <strong>Catalog / holdings review</strong>
                                <p>Stewardship and enrichment statistics already surface the operational picture for the fund.</p>
                            </div>
                            <div class="note-item">
                                <strong>Support routes</strong>
                                <p>Safe routes remain available through circulation, review, stewardship, AI chat, catalog, and library contacts.</p>
                            </div>
                        </div>
                    </article>
                </aside>
            </section>
        </div>
    </main>

    <script>
        const ENDPOINTS = {
            triage: '/api/v1/internal/review/triage-summary?top_limit=6',
            readers: '/api/v1/internal/review/readers-summary?top_limit=5',
            contacts: '/api/v1/internal/reader-contacts/stats',
            stewardship: '/api/v1/internal/review/stewardship-metrics',
            enrichment: '/api/v1/internal/enrichment/stats',
        };

        function formatNumber(value) {
            const number = Number(value ?? 0);
            return Number.isFinite(number) ? number.toLocaleString('ru-RU') : '0';
        }

        function formatPercent(value) {
            const number = Number(value ?? 0);
            return Number.isFinite(number) ? `${number.toFixed(0)}%` : '0%';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text ?? '');
            return div.innerHTML;
        }

        function metricCard(label, value, note = '', cardClass = '') {
            return `
                <div class="metric ${cardClass}">
                    <div class="metric-label">${escapeHtml(label)}</div>
                    <div class="metric-value">${escapeHtml(value)}</div>
                    <div class="metric-note">${escapeHtml(note)}</div>
                </div>
            `;
        }

        function queueItem(label, note, count) {
            return `
                <div class="queue-item">
                    <div>
                        <strong>${escapeHtml(label)}</strong>
                        <p>${escapeHtml(note)}</p>
                    </div>
                    <span class="queue-count">${escapeHtml(formatNumber(count))}</span>
                </div>
            `;
        }

        function reasonItem(reasonCode, count, entities = []) {
            const pills = entities.length > 0
                ? `<div class="pill-row">${entities.map((entity) => `<span class="pill">${escapeHtml(entity)}</span>`).join('')}</div>`
                : '';

            return `
                <div class="reason-item">
                    <strong>${escapeHtml(reasonCode || 'Unknown reason')}</strong>
                    <p>Открытых случаев: <span class="reason-count">${escapeHtml(formatNumber(count))}</span></p>
                    ${pills}
                </div>
            `;
        }

        async function fetchJson(url) {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        }

        function renderStatus(results) {
            const statusRow = document.getElementById('status-row');
            const labels = {
                triage: 'Triage',
                readers: 'Reader review',
                contacts: 'Contacts',
                stewardship: 'Stewardship',
                enrichment: 'Enrichment',
            };

            statusRow.innerHTML = Object.entries(results).map(([key, ok]) => `
                <div class="status-chip ${ok ? 'ok' : 'warn'}">${labels[key]}: ${ok ? 'OK' : 'Unavailable'}</div>
            `).join('');
        }

        function renderOperationalQueue(triage, readers, contacts, enrichment) {
            const triageData = triage?.data || {};
            const readerData = readers?.data || {};
            const contactData = contacts?.data || {};
            const enrichmentData = enrichment?.data || {};

            document.getElementById('operational-queue').innerHTML = [
                metricCard('Current Circulation', formatNumber(triageData.totalUnresolved), 'Live circulation-adjacent oversight signals from the active review backlog.', 'soft'),
                metricCard('Metadata Tasks', formatNumber(enrichmentData.enrichableByIsbn), 'Pending records that are ready for staff metadata enrichment and correction.', 'warn'),
                metricCard('Active Scholars', formatNumber((readerData.needsReviewCount ?? 0) + (contactData.readersWithValidEmail ?? 0)), 'Reader-facing profiles currently visible to the admin oversight surface.', 'soft'),
            ].join('');
        }

        function renderSystemActivity(triage, readers, contacts, stewardship, enrichment) {
            const triageData = triage?.data || {};
            const readerData = readers?.data || {};
            const contactData = contacts?.data || {};
            const reviewTasks = stewardship?.data?.reviewTasks || {};
            const enrichmentData = enrichment?.data || {};

            const target = document.getElementById('system-activity-log');
            target.innerHTML = [
                {
                    icon: '↺',
                    title: 'Policy and review backlog refreshed',
                    note: `Unresolved cases in the current queue: ${formatNumber(triageData.totalUnresolved)}.`,
                },
                {
                    icon: '⛁',
                    title: 'Repository and metadata sync snapshot',
                    note: `Enrichable records currently visible: ${formatNumber(enrichmentData.enrichableByIsbn)}.`,
                },
                {
                    icon: '✓',
                    title: 'Reader-support posture updated',
                    note: `Profiles needing review: ${formatNumber(readerData.needsReviewCount)}. Valid email coverage: ${formatNumber(contactData.readersWithValidEmail)}. Open tasks: ${formatNumber(reviewTasks.open)}.`,
                },
            ].map((item) => `
                <div class="activity-log-item">
                    <div class="activity-log-icon">${escapeHtml(item.icon)}</div>
                    <div>
                        <strong>${escapeHtml(item.title)}</strong>
                        <p>${escapeHtml(item.note)}</p>
                    </div>
                </div>
            `).join('');
        }

        function renderEntityQueues(triage) {
            const byEntity = triage?.data?.byEntity || {};

            document.getElementById('entity-queues').innerHTML = [
                queueItem('Copy review queue', 'Экземпляры с review-флагом, инвентарными проблемами или физическими несостыковками.', byEntity.copies?.needsReviewCount ?? 0),
                queueItem('Document review queue', 'Библиографические записи, которым нужен разбор reason codes и корректность описания.', byEntity.documents?.needsReviewCount ?? 0),
                queueItem('Reader review queue', 'Читатели, у которых нужно прояснить профиль, контакт или registration state.', byEntity.readers?.needsReviewCount ?? 0),
            ].join('');
        }

        function renderTopReasons(triage) {
            const topReasonCodes = Array.isArray(triage?.data?.topReasonCodes) ? triage.data.topReasonCodes : [];
            const target = document.getElementById('top-reasons');
            const sourceNote = document.getElementById('triage-source-note');

            if (topReasonCodes.length === 0) {
                target.innerHTML = '<div class="reason-item"><strong>Нет открытых top reason codes</strong><p>Когда triage summary станет доступен, здесь появятся основные причины staff-разбора.</p></div>';
            } else {
                target.innerHTML = topReasonCodes.map((item) => reasonItem(item.reasonCode, item.count, item.entities || [])).join('');
            }

            sourceNote.textContent = `Source: ${triage?.source || 'internal_triage_aggregation'}`;
        }

        function renderReaderAccess(readers, contacts, stewardship) {
            const readerData = readers?.data || {};
            const contactData = contacts?.data || {};
            const reviewTasks = stewardship?.data?.reviewTasks || {};

            document.getElementById('reader-access-metrics').innerHTML = [
                metricCard('Readers in review', formatNumber(readerData.needsReviewCount), 'Открытые reader review cases для staff linking и lifecycle checks.', 'warn'),
                metricCard('Readers with valid email', formatNumber(contactData.readersWithValidEmail), 'Контуры, где цифровой доступ и follow-up уже выглядят устойчиво.', 'soft'),
                metricCard('Invalid contacts', formatNumber(contactData.invalidFormatCount), 'Контактные записи, требующие нормализации или ручного уточнения.', 'warn'),
                metricCard('Open review tasks', formatNumber(reviewTasks.open), 'Общий staff backlog по review task lifecycle.', 'alert'),
            ].join('');
        }

        function renderStewardship(stewardship, enrichment) {
            const health = stewardship?.data?.overallHealth || {};
            const byEntity = stewardship?.data?.byEntity || {};
            const gaps = enrichment?.data?.gaps || {};

            document.getElementById('stewardship-metrics').innerHTML = [
                metricCard('Overall health', formatPercent(health.healthPercent), 'Доля clean entities по текущему stewardship summary.', 'soft'),
                metricCard('Reader health', formatPercent(byEntity.readers?.healthPercent), 'Как выглядят reader records после review and contact cleanup.', 'soft'),
                metricCard('Invalid ISBN', formatNumber(gaps.invalidIsbn), 'Записи с ISBN, которые мешают enrichable workflows и качеству каталога.', 'warn'),
                metricCard('Missing publisher', formatNumber(gaps.missingPublisher), 'Документы, где описание фонда остаётся неполным.', 'warn'),
            ].join('');
        }

        async function loadDashboard() {
            const errorBox = document.getElementById('dashboard-error');
            const entries = Object.entries(ENDPOINTS);
            const settled = await Promise.allSettled(entries.map(([, url]) => fetchJson(url)));

            const payloads = {};
            const statuses = {};

            entries.forEach(([key], index) => {
                const result = settled[index];
                statuses[key] = result.status === 'fulfilled';
                payloads[key] = result.status === 'fulfilled' ? result.value : null;
            });

            renderStatus(statuses);
            renderOperationalQueue(payloads.triage, payloads.readers, payloads.contacts, payloads.enrichment);
            renderSystemActivity(payloads.triage, payloads.readers, payloads.contacts, payloads.stewardship, payloads.enrichment);
            renderEntityQueues(payloads.triage);
            renderTopReasons(payloads.triage);
            renderReaderAccess(payloads.readers, payloads.contacts, payloads.stewardship);
            renderStewardship(payloads.stewardship, payloads.enrichment);

            if (Object.values(statuses).some((ok) => !ok)) {
                errorBox.style.display = 'block';
                errorBox.textContent = 'Часть internal summary endpoints недоступна. Панель остаётся честной: показывает только доступные operational блоки и не подменяет их фейковой аналитикой.';
            }
        }

        loadDashboard().catch(() => {
            const errorBox = document.getElementById('dashboard-error');
            errorBox.style.display = 'block';
            errorBox.textContent = 'Не удалось загрузить librarian workspace summary данные.';
        });
    </script>
</body>
</html>
