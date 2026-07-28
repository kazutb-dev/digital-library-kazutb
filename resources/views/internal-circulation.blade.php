<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Circulation Desk — Internal</title>
  <style>
    :root {
      --bg: #f8f9fa; --paper: rgba(255, 255, 255, 0.96); --ink: #191c1d; --muted: #43474f;
      --accent: #001e40; --accent-soft: rgba(0, 30, 64, 0.05);
      --ok: #14696d; --ok-bg: rgba(20, 105, 109, 0.10);
      --warn: #5d4201; --warn-bg: rgba(93, 66, 1, 0.10);
      --danger: #ba1a1a; --danger-bg: rgba(186, 26, 26, 0.08);
      --border: rgba(195, 198, 209, 0.55);
    }
    * { box-sizing: border-box; margin: 0; }
    body { font-family: 'Manrope', system-ui, sans-serif; color: var(--ink); background:
      radial-gradient(circle at top left, rgba(0, 30, 64, 0.04), transparent 20%),
      linear-gradient(180deg, #fbfcfc 0%, var(--bg) 100%); padding: 24px; }
    a { color: var(--accent); }

    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 32px; margin-bottom: 4px; font-family: 'Newsreader', Georgia, serif; font-weight: 600; color: var(--accent); }
    .page-header p { color: var(--muted); font-size: 15px; }

    .nav-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
    .nav-link { padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: 700; text-decoration: none;
      background: var(--paper); border: 1px solid var(--border); color: var(--ink); transition: .15s; }
    .nav-link:hover { border-color: var(--accent); background: var(--accent-soft); }
    .nav-link.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    @media (max-width: 920px) { .grid { grid-template-columns: 1fr; } }

    .panel { background: var(--paper); border: 1px solid var(--border); border-radius: 8px; padding: 24px; }
    .panel h2 { font-size: 20px; margin-bottom: 16px; }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 700; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .04em; }
    .form-input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font: inherit; font-size: 15px; background: #fff; }
    .form-input:focus { outline: 2px solid var(--accent); border-color: var(--accent); }

    .btn { padding: 12px 20px; border-radius: 8px; border: none; font: inherit; font-weight: 700; cursor: pointer; font-size: 14px; transition: background-color .15s ease, border-color .15s ease; }
    .btn:hover { transform: none; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-secondary { background: var(--paper); border: 1px solid var(--border); color: var(--ink); }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }

    .status-msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; display: none; }
    .status-msg.ok { display: block; background: var(--ok-bg); color: var(--ok); border: 1px solid #86efac; }
    .status-msg.error { display: block; background: var(--danger-bg); color: var(--danger); border: 1px solid #fca5a5; }
    .status-msg.warn { display: block; background: var(--warn-bg); color: var(--warn); border: 1px solid #fcd34d; }

    .loan-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .loan-table th { text-align: left; padding: 10px 12px; background: var(--accent-soft); color: var(--accent); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    .loan-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
    .loan-table tr:hover td { background: rgba(18,69,89,.03); }

    .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .badge-active { background: var(--accent-soft); color: var(--accent); }
    .badge-returned { background: var(--ok-bg); color: var(--ok); }
    .badge-overdue { background: var(--danger-bg); color: var(--danger); }

    .empty-msg { text-align: center; color: var(--muted); padding: 32px 16px; font-style: italic; }

    .full-width { grid-column: 1 / -1; }

    .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .summary-card { background: var(--accent-soft); border-radius: 8px; padding: 16px; text-align: center; transition: transform .18s ease, box-shadow .18s ease; }
    .summary-card:hover { transform: translate3d(0, -1px, 0); box-shadow: 0 10px 20px rgba(25, 28, 29, 0.04); }
    .summary-card .number { font-size: 28px; font-weight: 700; color: var(--accent); }
    .summary-card .label { font-size: 12px; color: var(--muted); margin-top: 4px; }
  </style>
</head>
<body>
  <div class="page-header">
    <h1>📚 Circulation Desk</h1>
    <p>Выдача и возврат книг, просмотр задолженностей читателей</p>
  </div>

  <div class="nav-row">
    <a class="nav-link" href="/internal/dashboard">Dashboard</a>
    <a class="nav-link" href="/internal/stewardship">Data Stewardship</a>
    <a class="nav-link" href="/internal/review">Quality Issues</a>
    <a class="nav-link active" href="/internal/circulation">Circulation Desk</a>
  </div>

  <div class="grid">
    <!-- Checkout Panel -->
    <div class="panel">
      <h2>📤 Выдача книги (Checkout)</h2>
      <div id="checkout-status" class="status-msg"></div>
      <div class="form-group">
        <label>Reader ID (UUID)</label>
        <input class="form-input" id="checkout-reader-id" type="text" placeholder="Введите ID читателя">
      </div>
      <div class="form-group">
        <label>Copy ID (UUID)</label>
        <input class="form-input" id="checkout-copy-id" type="text" placeholder="Введите ID экземпляра">
      </div>
      <div class="form-group">
        <label>Срок возврата (необязательно)</label>
        <input class="form-input" id="checkout-due-at" type="date">
      </div>
      <button class="btn btn-primary" id="checkout-btn" onclick="doCheckout()">Оформить выдачу</button>
    </div>

    <!-- Return Panel -->
    <div class="panel">
      <h2>📥 Возврат книги (Return)</h2>
      <div id="return-status" class="status-msg"></div>
      <div class="form-group">
        <label>Copy ID (UUID)</label>
        <input class="form-input" id="return-copy-id" type="text" placeholder="Введите ID экземпляра">
      </div>
      <button class="btn btn-danger" id="return-btn" onclick="doReturn()" style="margin-bottom: 16px;">Оформить возврат</button>

      <h2 style="margin-top: 16px;">🔍 Проверить экземпляр</h2>
      <div id="copy-check-status" class="status-msg"></div>
      <div class="form-group">
        <label>Copy ID для проверки</label>
        <input class="form-input" id="copy-check-id" type="text" placeholder="Проверить активную выдачу">
      </div>
      <button class="btn btn-secondary" onclick="checkCopyLoan()">Проверить</button>
    </div>

    <!-- Reader Lookup Panel -->
    <div class="panel full-width">
      <h2>👤 Выдачи читателя</h2>
      <div id="reader-status" class="status-msg"></div>
      <div class="btn-row" style="margin-bottom: 16px;">
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
          <input class="form-input" id="reader-lookup-id" type="text" placeholder="Reader ID (UUID)" onkeydown="if(event.key==='Enter') lookupReader()">
        </div>
        <select class="form-input" id="reader-status-filter" style="width: auto; min-width: 160px;">
          <option value="">Все выдачи</option>
          <option value="active">Только активные</option>
          <option value="returned">Только возвращённые</option>
        </select>
        <button class="btn btn-primary" onclick="lookupReader()">Загрузить</button>
      </div>

      <div id="reader-summary" class="summary-cards" style="display:none;">
        <div class="summary-card"><div class="number" id="stat-total">0</div><div class="label">Всего выдач</div></div>
        <div class="summary-card"><div class="number" id="stat-active">0</div><div class="label">Активных</div></div>
        <div class="summary-card"><div class="number" id="stat-overdue">0</div><div class="label">Просроченных</div></div>
        <div class="summary-card"><div class="number" id="stat-returned">0</div><div class="label">Возвращённых</div></div>
      </div>

      <div id="reader-loans-container">
        <div class="empty-msg">Введите Reader ID и нажмите «Загрузить» для просмотра выдач.</div>
      </div>
    </div>
  </div>

  <script>
    const BASE = '/api/v1/internal/circulation';

    function showStatus(elId, type, message) {
      const el = document.getElementById(elId);
      el.className = 'status-msg ' + type;
      el.textContent = message;
      el.style.display = 'block';
    }

    function clearStatus(elId) {
      const el = document.getElementById(elId);
      el.style.display = 'none';
    }

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function formatDate(isoString) {
      if (!isoString) return '—';
      try {
        return new Date(isoString).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
      } catch { return isoString; }
    }

    function loanStatusBadge(loan) {
      if (loan.isOverdue) return '<span class="badge badge-overdue">Просрочено</span>';
      if (loan.status === 'returned') return '<span class="badge badge-returned">Возвращено</span>';
      return '<span class="badge badge-active">Активна</span>';
    }

    async function doCheckout() {
      clearStatus('checkout-status');
      const readerId = document.getElementById('checkout-reader-id').value.trim();
      const copyId = document.getElementById('checkout-copy-id').value.trim();
      const dueAt = document.getElementById('checkout-due-at').value;

      if (!readerId || !copyId) {
        showStatus('checkout-status', 'error', 'Укажите Reader ID и Copy ID.');
        return;
      }

      const btn = document.getElementById('checkout-btn');
      btn.disabled = true;
      try {
        const body = { reader_id: readerId, copy_id: copyId };
        if (dueAt) body.due_at = dueAt;

        const resp = await fetch(`${BASE}/checkouts`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(body)
        });
        const data = await resp.json();

        if (resp.ok && data.success) {
          showStatus('checkout-status', 'ok', `✓ Выдача оформлена. Loan ID: ${data.data.id}. Возврат до: ${formatDate(data.data.dueAt)}`);
          document.getElementById('checkout-reader-id').value = '';
          document.getElementById('checkout-copy-id').value = '';
          document.getElementById('checkout-due-at').value = '';
        } else {
          showStatus('checkout-status', 'error', `Ошибка: ${data.message || data.error || 'Неизвестная ошибка'}`);
        }
      } catch (err) {
        showStatus('checkout-status', 'error', 'Ошибка сети: ' + err.message);
      } finally {
        btn.disabled = false;
      }
    }

    async function doReturn() {
      clearStatus('return-status');
      const copyId = document.getElementById('return-copy-id').value.trim();

      if (!copyId) {
        showStatus('return-status', 'error', 'Укажите Copy ID.');
        return;
      }

      const btn = document.getElementById('return-btn');
      btn.disabled = true;
      try {
        const resp = await fetch(`${BASE}/returns`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ copy_id: copyId })
        });
        const data = await resp.json();

        if (resp.ok && data.success) {
          showStatus('return-status', 'ok', `✓ Возврат оформлен. Loan ID: ${data.data.id}. Возвращено: ${formatDate(data.data.returnedAt)}`);
          document.getElementById('return-copy-id').value = '';
        } else {
          showStatus('return-status', 'error', `Ошибка: ${data.message || data.error || 'Неизвестная ошибка'}`);
        }
      } catch (err) {
        showStatus('return-status', 'error', 'Ошибка сети: ' + err.message);
      } finally {
        btn.disabled = false;
      }
    }

    async function checkCopyLoan() {
      clearStatus('copy-check-status');
      const copyId = document.getElementById('copy-check-id').value.trim();

      if (!copyId) {
        showStatus('copy-check-status', 'error', 'Укажите Copy ID.');
        return;
      }

      try {
        const resp = await fetch(`${BASE}/copies/${encodeURIComponent(copyId)}/active-loan`, {
          headers: { 'Accept': 'application/json' }
        });

        if (resp.status === 404) {
          showStatus('copy-check-status', 'ok', 'Нет активной выдачи на этот экземпляр.');
          return;
        }

        const data = await resp.json();
        if (resp.ok && data.data) {
          const loan = data.data;
          const overdueText = loan.isOverdue ? ' ⚠️ ПРОСРОЧЕНО' : '';
          showStatus('copy-check-status', 'warn',
            `Активная выдача: Loan ID ${loan.id}, Reader ${loan.readerId}, до ${formatDate(loan.dueAt)}${overdueText}`);
        } else {
          showStatus('copy-check-status', 'ok', 'Нет активной выдачи.');
        }
      } catch (err) {
        showStatus('copy-check-status', 'error', 'Ошибка: ' + err.message);
      }
    }

    async function lookupReader() {
      clearStatus('reader-status');
      const readerId = document.getElementById('reader-lookup-id').value.trim();
      const statusFilter = document.getElementById('reader-status-filter').value;

      if (!readerId) {
        showStatus('reader-status', 'error', 'Укажите Reader ID.');
        return;
      }

      const container = document.getElementById('reader-loans-container');
      container.innerHTML = '<div class="empty-msg">Загрузка...</div>';

      try {
        let url = `${BASE}/readers/${encodeURIComponent(readerId)}/loans`;
        if (statusFilter) url += `?status=${statusFilter}`;

        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });

        if (resp.status === 404) {
          showStatus('reader-status', 'error', 'Читатель не найден.');
          container.innerHTML = '<div class="empty-msg">Читатель не найден в системе.</div>';
          document.getElementById('reader-summary').style.display = 'none';
          return;
        }

        const data = await resp.json();
        const loans = data.data || [];

        // Compute stats
        const active = loans.filter(l => l.status === 'active');
        const overdue = loans.filter(l => l.isOverdue);
        const returned = loans.filter(l => l.status === 'returned');

        document.getElementById('stat-total').textContent = loans.length;
        document.getElementById('stat-active').textContent = active.length;
        document.getElementById('stat-overdue').textContent = overdue.length;
        document.getElementById('stat-returned').textContent = returned.length;
        document.getElementById('reader-summary').style.display = 'grid';

        if (loans.length === 0) {
          container.innerHTML = '<div class="empty-msg">У читателя нет выдач' + (statusFilter ? ' с данным статусом' : '') + '.</div>';
          return;
        }

        let html = `<table class="loan-table">
          <thead><tr>
            <th>Loan ID</th>
            <th>Copy ID</th>
            <th>Статус</th>
            <th>Выдано</th>
            <th>Срок</th>
            <th>Возвращено</th>
            <th>Действие</th>
          </tr></thead><tbody>`;

        for (const loan of loans) {
          const canReturn = loan.status === 'active';
          const canRenew = loan.status === 'active' && loan.renewCount < 3;
          html += `<tr>
            <td style="font-family: monospace; font-size: 12px;">${escapeHtml(loan.id?.substring(0, 8))}…</td>
            <td style="font-family: monospace; font-size: 12px;">${escapeHtml(loan.copyId?.substring(0, 8))}…</td>
            <td>${loanStatusBadge(loan)}${loan.renewCount > 0 ? ` <span style="font-size:11px;color:#6b7280;">(прод. ${loan.renewCount}/3)</span>` : ''}</td>
            <td>${formatDate(loan.issuedAt)}</td>
            <td>${formatDate(loan.dueAt)}</td>
            <td>${loan.returnedAt ? formatDate(loan.returnedAt) : '—'}</td>
            <td>
              ${canRenew ? `<button class="btn" style="padding:6px 12px; font-size:12px; background:#124559; color:#fff; border:1px solid #124559; border-radius:10px; cursor:pointer;" onclick="quickRenew('${escapeHtml(loan.id)}')">Продлить</button> ` : ''}
              ${canReturn ? `<button class="btn btn-danger" style="padding:6px 12px; font-size:12px;" onclick="quickReturn('${escapeHtml(loan.copyId)}')">Возврат</button>` : ''}
            </td>
          </tr>`;
        }

        html += '</tbody></table>';
        container.innerHTML = html;

        if (overdue.length > 0) {
          showStatus('reader-status', 'warn', `⚠️ У читателя ${overdue.length} просроченных выдач!`);
        }

      } catch (err) {
        showStatus('reader-status', 'error', 'Ошибка: ' + err.message);
        container.innerHTML = '<div class="empty-msg">Ошибка загрузки данных.</div>';
      }
    }

    async function quickReturn(copyId) {
      if (!confirm(`Оформить возврат экземпляра ${copyId.substring(0, 8)}…?`)) return;

      try {
        const resp = await fetch(`${BASE}/returns`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ copy_id: copyId })
        });
        const data = await resp.json();

        if (resp.ok && data.success) {
          showStatus('reader-status', 'ok', `✓ Возврат оформлен. Copy: ${copyId.substring(0, 8)}…`);
          lookupReader(); // Refresh the table
        } else {
          showStatus('reader-status', 'error', `Ошибка: ${data.message || data.error}`);
        }
      } catch (err) {
        showStatus('reader-status', 'error', 'Ошибка: ' + err.message);
      }
    }
    async function quickRenew(loanId) {
      if (!confirm(`Продлить выдачу ${loanId.substring(0, 8)}… на 14 дней?`)) return;

      try {
        const resp = await fetch(`${BASE}/loans/${loanId}/renew`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({})
        });
        const data = await resp.json();

        if (resp.ok && data.success) {
          showStatus('reader-status', 'ok', `✓ Продлено. Новый срок: ${formatDate(data.data.dueAt)}. Продлений: ${data.data.renewCount}/3`);
          lookupReader();
        } else {
          showStatus('reader-status', 'error', `Ошибка: ${data.message || data.error}`);
        }
      } catch (err) {
        showStatus('reader-status', 'error', 'Ошибка: ' + err.message);
      }
    }
  </script>
</body>
</html>
