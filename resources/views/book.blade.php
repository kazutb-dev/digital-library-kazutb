@php
    $lang = in_array(app()->getLocale(), ['ru', 'kk', 'en'], true) ? app()->getLocale() : 'kk';
    $bookBootstrap = is_array($bookBootstrap ?? null) ? $bookBootstrap : null;
    $bookTitle = trim((string) data_get($bookBootstrap, 'title.display', data_get($bookBootstrap, 'title.raw', '')));
    $bookTitleSuffix = __('brand.library.name');
    $bookPageTitle = [
        'ru' => 'Библиографическая запись',
        'kk' => 'Библиографиялық жазба',
        'en' => 'Bibliographic record',
    ][$lang].' — '.$bookTitleSuffix;
    if ($bookTitle !== '') {
        $bookPageTitle = $bookTitle.' — '.$bookTitleSuffix;
    }
    $bookMetaDescription = [
        'ru' => $bookTitle !== ''
            ? $bookTitle.' — наличие и библиографическое описание в каталоге научной библиотеки.'
            : 'Карточка издания в каталоге научной библиотеки.',
        'kk' => $bookTitle !== ''
            ? $bookTitle.' — ғылыми кітапхана каталогындағы библиографиялық сипаттама және қолжетімділік.'
            : 'Ғылыми кітапхана каталогындағы басылым карточкасы.',
        'en' => $bookTitle !== ''
            ? $bookTitle.' — bibliographic details and availability in the Scientific Library catalogue.'
            : 'Publication details in the Scientific Library catalogue.',
    ][$lang];
    $catalogHref = $lang === 'kk' ? '/catalog' : ('/catalog?lang=' . $lang);
    $loginHref = $lang === 'kk' ? '/login' : ('/login?lang=' . $lang);
    $bookCanonicalQuery = [];
    foreach (request()->query() as $key => $value) {
        $normalisedKey = mb_strtolower((string) $key);
        if ($normalisedKey === 'lang'
            || str_starts_with($normalisedKey, 'utm_')
            || in_array($normalisedKey, ['gclid', 'fbclid', 'yclid'], true)
            || ! is_scalar($value)
            || trim((string) $value) === '') {
            continue;
        }
        $bookCanonicalQuery[(string) $key] = (string) $value;
    }
    ksort($bookCanonicalQuery);
    $bookCanonicalPath = '/'.ltrim(request()->path(), '/');
    $bookUrlForLocale = static function (string $locale) use ($bookCanonicalPath, $bookCanonicalQuery): string {
        $query = $bookCanonicalQuery;
        if ($locale !== 'kk') {
            $query['lang'] = $locale;
        }

        return url($bookCanonicalPath).($query === [] ? '' : '?'.http_build_query($query));
    };

    // Reservation entry point (Master.md 13.1, 31.1). The control is rendered
    // only when there is a real canonical record to reserve AND the signed-in
    // account is an ordinary reader holding `reservation.create` — the same
    // gate the POST route enforces, so the button is never a dead end.
    $reserveRecordId = data_get($bookBootstrap, 'id');
    $sessionRole = mb_strtolower(trim((string) data_get(session('library.user'), 'role', '')));
    // Drafts are excluded here because CabinetController::storeReservation()
    // aborts 404 on them (9.1). Without this the button rendered on every
    // incomplete record — the bulk of the imported catalogue — and dead-ended.
    $canReserveRecord = $reserveRecordId !== null
        && (bool) data_get($bookBootstrap, 'viewer.canReserve', false)
        && $sessionRole === 'reader'
        && (bool) auth()->user()?->can('reservation.create');
    $queueEnabled = ! \Illuminate\Support\Facades\Schema::hasTable('settings')
        || (bool) \App\Models\Setting::valueFor('reservation_queue_enabled', true);
    $pickupBranches = \Illuminate\Support\Facades\Schema::hasTable('branches')
        ? \App\Models\Branch::query()->active()->ordered()->get(['id', 'name'])
        : collect();
    $canReserveRecord = $canReserveRecord && $pickupBranches->isNotEmpty();
    $showGuestReserveLogin = session('library.user') === null
        && (bool) data_get($bookBootstrap, 'viewer.reservationEligible', false);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $bookPageTitle }}</title>
    <meta name="description" content="{{ $bookMetaDescription }}" />
    <link rel="canonical" href="{{ $bookUrlForLocale($lang) }}" />
    @foreach(['kk', 'ru', 'en'] as $alternateLocale)
        <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $bookUrlForLocale($alternateLocale) }}" />
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $bookUrlForLocale('kk') }}" />
    @include('partials.favicons')
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="/fonts/fonts.css">
    <link rel="stylesheet" href="/css/shell.css">
    <style>
            --type-display: clamp(40px, 4.6vw, 54px);
            --type-title: clamp(24px, 2.6vw, 36px);
            --type-body: 15px;
            --type-meta: 12px;
            --space-1: 8px;
            --space-2: 12px;
            --space-3: 16px;
            --space-4: 24px;
        :root {
            --bg: #f8f9fa;
            --surface: #ffffff;
            --surface-soft: #f3f4f5;
            align-content: start;
            --border: rgba(195, 198, 209, .55);
            --text: #191c1d;
            --muted: #43474f;
            --blue: #001e40;
            font-size: var(--type-display);
            --violet: #453000;
            --pink: #2a1c00;
            --gold: #e9c176;
            --success: #14696d;
            text-wrap: balance;
            --warning: #5d4201;
            --shadow: 0 12px 32px rgba(25, 28, 29, .04);
            --shadow-soft: 0 6px 16px rgba(25, 28, 29, .03);
            --radius-xl: 8px;
            color: #425164;
            --radius-md: 4px;
            --container: 1536px;
            text-wrap: balance;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Manrope', system-ui, sans-serif;
            color: var(--text);
            background: #f8f9fa;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }

        .container {
            width: min(100% - 32px, var(--container));
            margin: 0 auto;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--border);
        }
            padding: 12px;
        .nav {
            min-height: 84px;
            display: flex;
            gap: 6px;
            justify-content: space-between;
            gap: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            font-variant-numeric: tabular-nums;
            gap: 14px;
            font-weight: 900;
            letter-spacing: -.3px;
            cursor: pointer;
            font-variant-numeric: tabular-nums;
        }

        .brand small {
            display: block;
            color: var(--muted);
            margin-top: 3px;
            font-weight: 500;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
            font-weight: 600;
            color: #334155;
        }

        .nav-links a:hover { color: var(--blue); }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            border: 0;
            cursor: pointer;
            font: inherit;
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: transform .18s cubic-bezier(0.2, 0.8, 0.2, 1), background .18s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .28s cubic-bezier(0.2, 0.8, 0.2, 1), border-color .18s cubic-bezier(0.2, 0.8, 0.2, 1);
            font-size: var(--type-body);
            font-weight: 700;
        }

        .btn:hover { transform: translate3d(0, -1px, 0); }

        .btn-primary {
            color: white;
            background: linear-gradient(135deg, var(--blue), #003366);
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary {
            color: white;
            background: linear-gradient(135deg, var(--cyan), #1b6d71);
            box-shadow: var(--shadow-soft);
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
            box-shadow: none;
        }

        .page {
            padding: 34px 0 70px;
        }

        .breadcrumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 22px;
        }

        .breadcrumbs span:last-child {
            color: var(--text);
            font-weight: 600;
        }

        .layout {
            display: grid;
            grid-template-columns: .88fr 1.12fr;
            gap: 22px;
            align-items: start;
        }

        .card {
            background: rgba(255,255,255,.98);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-soft);
            border-radius: var(--radius-xl);
            position: relative;
            overflow: hidden;
            transition: transform .28s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .28s cubic-bezier(0.2, 0.8, 0.2, 1), border-color .18s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .card:hover {
            transform: translate3d(0, -2px, 0);
            box-shadow: 0 16px 34px rgba(25, 28, 29, .05);
            border-color: rgba(0, 30, 64, .12);
        }

        .book-panel {
            padding: 26px;
            position: sticky;
            top: var(--shell-sticky-offset);
        }

        .book-cover-wrap {
            position: relative;
            border-radius: var(--radius-xl);
            min-height: 580px;
            padding: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at top right, rgba(20,105,109,.10), transparent 24%),
                radial-gradient(circle at bottom left, rgba(0,30,64,.08), transparent 24%),
                linear-gradient(180deg, #f3f4f5 0%, #edeeef 100%);
            overflow: hidden;
            perspective: 1400px;
        }

        .book-cover-wrap::after {
            content: "";
            position: absolute;
            inset: 18px;
            border-radius: calc(var(--radius-xl) - 2px);
            border: 1px solid rgba(255,255,255,.46);
            pointer-events: none;
        }

        .book-mockup {
            width: 310px;
            max-width: 100%;
            height: 450px;
            border-radius: var(--radius-lg);
            padding: 26px 24px 26px 30px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            background: linear-gradient(180deg, #003366 0%, #001e40 100%);
            box-shadow: 0 24px 44px rgba(25, 28, 29, .12);
            overflow: hidden;
            transform-style: preserve-3d;
            transition: transform .32s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .32s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .book-panel:hover .book-mockup {
            transform: translate3d(0, -4px, 0) rotateY(-4deg) rotateX(1deg);
            box-shadow: 0 22px 42px rgba(25, 28, 29, .10);
        }

        .book-mockup::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 14px;
            background: rgba(0,0,0,.18);
        }

        .book-mockup::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,0));
        }

        .cover-top {
            position: absolute;
            top: 28px;
            left: 30px;
            right: 24px;
            z-index: 1;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255,255,255,.55);
        }

        .cover-title {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #f1d08e;
            font-size: 40px;
            line-height: .95;
            letter-spacing: -1.3px;
            max-width: 220px;
        }

        .cover-author {
            position: relative;
            z-index: 1;
            margin-top: 18px;
            color: rgba(255,255,255,.72);
            font-size: 15px;
            font-weight: 500;
        }

        .cover-badge {
            position: absolute;
            right: 22px;
            top: 22px;
            z-index: 1;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(10px);
        }

        .mini-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 18px;
        }

        .mini-action {
            padding: 14px 12px;
            border-radius: var(--radius-lg);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(243,244,245,.94));
            border: 1px solid var(--border);
            text-align: center;
            color: #334155;
            box-shadow: var(--shadow-soft);
        }

        .mini-action strong {
            display: block;
            font-size: 18px;
            color: var(--blue);
            letter-spacing: -.03em;
        }

        .mini-action span {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .details-card {
            padding: 28px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .badge {
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid transparent;
        }

        .badge-blue {
            background: rgba(0,30,64,.06);
            color: var(--blue);
            border-color: rgba(0,30,64,.10);
        }

        .badge-green {
            background: rgba(20,105,109,.08);
            color: var(--success);
            border-color: rgba(20,105,109,.12);
        }

        .title {
            margin: 0;
            font-size: clamp(34px, 5vw, 56px);
            line-height: .98;
            letter-spacing: -1.8px;
            max-width: 760px;
        }

        .subtitle {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.8;
            max-width: 840px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin: 28px 0;
        }

        .meta-item {
            padding: 18px;
            border-radius: var(--radius-xl);
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(243,244,245,.94));
            border: 1px solid var(--border);
            transition: transform .22s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .22s cubic-bezier(0.2, 0.8, 0.2, 1), border-color .18s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .meta-item:hover {
            transform: translate3d(0, -2px, 0);
            box-shadow: 0 12px 26px rgba(25, 28, 29, .04);
            border-color: rgba(20,105,109,.18);
        }

        .meta-label {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .meta-value {
            display: block;
            font-weight: 800;
            font-size: 17px;
            color: var(--text);
        }

        .section-title {
            margin: 0 0 14px;
            font-size: 24px;
            letter-spacing: -.6px;
        }

        .text-block {
            color: var(--muted);
            line-height: 1.9;
            font-size: 16px;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 24px 0 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 24px;
        }

        .info-card {
            padding: 22px;
        }

        .info-list {
            display: grid;
            gap: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child { border-bottom: 0; padding-bottom: 0; }

        .info-row span:first-child {
            color: var(--muted);
        }

        .info-row span:last-child {
            font-weight: 700;
            text-align: right;
        }

        .status-box {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-radius: var(--radius-xl);
            margin-top: 20px;
            background: linear-gradient(135deg, rgba(22,163,74,.08), rgba(6,182,212,.06));
            border: 1px solid rgba(22,163,74,.12);
        }

        .status-box.unavailable {
            background: linear-gradient(135deg, rgba(239,68,68,.08), rgba(236,72,153,.06));
            border-color: rgba(239,68,68,.12);
        }

        .status-box strong {
            display: block;
            margin-bottom: 6px;
            font-size: 18px;
        }

        .status-box p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .status-pill {
            padding: 10px 14px;
            border-radius: 999px;
            background: #fff;
            color: var(--success);
            font-weight: 800;
            white-space: nowrap;
            border: 1px solid rgba(22,163,74,.12);
        }

        .status-pill.unavailable {
            color: #dc2626;
            border-color: rgba(239,68,68,.12);
        }

        .cards-section {
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .cards-section .book-card:nth-child(2) {
            transform: translate3d(0, 10px, 0);
        }

        .cards-section .book-card:nth-child(2):hover {
            transform: translate3d(0, 4px, 0);
        }

        .book-card {
            padding: 18px;
            border-radius: var(--radius-xl);
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: none;
            transition: transform .28s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .28s cubic-bezier(0.2, 0.8, 0.2, 1), border-color .18s cubic-bezier(0.2, 0.8, 0.2, 1), background .18s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .book-card:hover {
            transform: translate3d(0, -2px, 0);
            box-shadow: 0 16px 34px rgba(25, 28, 29, .05);
            border-color: rgba(0,30,64,.12);
            background: rgba(248,249,250,.98);
        }

        .book-preview {
            height: 220px;
            border-radius: var(--radius-xl);
            padding: 18px;
            display: flex;
            align-items: flex-end;
            background: linear-gradient(180deg, #2d4268 0%, #223758 100%);
            position: relative;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .book-card:nth-child(2) .book-preview {
            background: linear-gradient(180deg, #8f1f1f 0%, #6d1111 100%);
        }

        .book-card:nth-child(3) .book-preview {
            background: linear-gradient(180deg, #205f43 0%, #134935 100%);
        }

        .book-preview::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 10px;
            background: rgba(0,0,0,.18);
        }

        .book-preview small {
            position: absolute;
            left: 18px;
            top: 18px;
            color: rgba(255,255,255,.58);
            font-size: 11px;
            letter-spacing: .16em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .book-preview h4 {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #f1d08e;
            font-size: 24px;
            line-height: 1;
            letter-spacing: -.8px;
            max-width: 150px;
        }

        .book-card-title {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .book-card p {
            margin: 0 0 16px;
            color: var(--muted);
            line-height: 1.7;
            font-size: 14px;
        }

        .book-card .btn {
            width: 100%;
        }

        .locations-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 10px;
        }

        .locations-table th,
        .locations-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .locations-table th {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 600;
        }

        .locations-table .avail-count {
            font-weight: 700;
            color: var(--success);
        }

        .locations-table .zero-count {
            color: var(--muted);
        }

        .authors-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .author-chip {
            display: inline-flex;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            font-size: 13px;
        }

        .review-notice {
            margin-top: 12px;
            padding: 12px 16px;
            border-radius: 8px;
            background: #fef3c7;
            border: 1px solid rgba(245, 158, 11, .2);
            font-size: 13px;
            color: #92400e;
        }

        .review-notice .reason-badge {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ffedd5;
            border: 1px solid rgba(154, 52, 18, .15);
            font-size: 11px;
            color: #9a3412;
            margin: 0 2px;
        }

        .classification-section {
            margin-top: 16px;
            padding: 16px;
            background: linear-gradient(135deg, rgba(0,30,64,.03), rgba(20,105,109,.05));
            border: 1px solid rgba(0,30,64,.10);
            border-radius: var(--radius-xl);
        }

        .classification-section h4 {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--blue);
            letter-spacing: .02em;
            font-family: 'Newsreader', Georgia, serif;
        }

        .classification-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .classification-chip {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color .2s ease, border-color .2s ease, color .2s ease;
        }

        .classification-chip.specialization {
            background: rgba(42,28,0,.06);
            color: var(--violet);
            border: 1px solid rgba(42,28,0,.12);
        }

        .classification-chip.department {
            background: rgba(0,30,64,.06);
            color: var(--blue);
            border: 1px solid rgba(0,30,64,.12);
        }

        .classification-chip.faculty {
            background: rgba(20,105,109,.08);
            color: var(--cyan);
            border: 1px solid rgba(20,105,109,.14);
        }

        .classification-chip:hover {
            transform: none;
            box-shadow: none;
            background: rgba(243,244,245,.96);
        }

        .loading {
            position: relative;
            text-align: center;
            padding: 2rem 1.5rem;
            font-size: 1.05rem;
            color: var(--muted);
            border-radius: var(--radius-xl);
            border: 1px dashed rgba(195,198,209,.7);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(243,244,245,.94));
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .loading::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 0%, rgba(255,255,255,.45) 45%, transparent 100%);
            transform: translateX(-120%);
            animation: loadingSweep 2.8s linear infinite;
            pointer-events: none;
            opacity: .75;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes loadingSweep { to { transform: translateX(120%); } }
        .spinner {
            display: inline-block;
            width: 32px; height: 32px;
            border: 3px solid #e5e7eb;
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        .error {
            position: relative;
            background: linear-gradient(180deg, rgba(255,248,248,.98), rgba(255,240,240,.95));
            border: 1px solid rgba(186,26,26,.18);
            color: #8a1d1d;
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-soft);
            margin: 2rem 0;
            text-align: center;
        }

        .digital-materials-section {
            margin-top: 16px;
        }
        .dm-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            background: rgba(0,30,64,.02);
            border: 1px solid rgba(0,30,64,.10);
            border-radius: var(--radius-xl);
            margin-bottom: 10px;
            transition: transform .22s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow .22s cubic-bezier(0.2, 0.8, 0.2, 1), border-color .18s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .dm-card:hover {
            transform: translate3d(0, -1px, 0);
            box-shadow: 0 12px 26px rgba(25, 28, 29, .04);
            border-color: rgba(20,105,109,.16);
        }
        .dm-info {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .dm-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--blue), #214c6f);
            color: #fff;
        }
        .dm-label { font-weight: 700; font-size: 15px; margin: 0 0 2px; }
        .dm-meta { font-size: 13px; color: var(--muted, #64748b); }
        .dm-actions { flex-shrink: 0; }
        .dm-locked {
            font-size: 13px;
            color: var(--muted, #64748b);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Stitch-like detail layout overrides */
        .detail-shell {
            display: grid;
            grid-template-columns: clamp(320px, 31vw, 390px) minmax(0, 1fr);
            gap: 32px;
            align-items: start;
            max-width: 1280px;
            margin: 0 auto;
        }

        .detail-left {
            display: grid;
            gap: 14px;
        }

        .book-detail-shell .catalog-card-media {
            width: 100%;
            max-width: 15rem;
            min-height: 25.5rem;
            align-self: stretch;
            margin-inline: auto;
            overflow: visible;
            background: transparent;
            box-shadow: none;
        }

        .book-detail-shell .catalog-card-book {
            position: relative;
            width: 100%;
            min-height: 25.5rem;
            height: 100%;
            perspective: 1800px;
        }

        .book-detail-shell .catalog-card-book__stack {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 25.5rem;
            transform-style: preserve-3d;
        }

        .book-detail-shell .catalog-card-book__pages {
            position: absolute;
            inset: 0.3rem 0.15rem 0.3rem 0.4rem;
            border-radius: 0 0.75rem 0.75rem 0;
            overflow: hidden;
            background: linear-gradient(90deg, #f3ead7 0%, #fffdfa 18%, #f3ede2 100%);
            box-shadow: inset 0 0 0 1px rgba(120, 96, 58, 0.12), 0 14px 30px rgba(15, 23, 42, 0.16);
            opacity: 0;
            transform: translateX(0.35rem) scaleX(0.985);
            transition: opacity 0.25s ease, transform 0.4s ease;
        }

        .book-detail-shell .catalog-card-book:hover .catalog-card-book__pages {
            opacity: 1;
            transform: translateX(0) scaleX(1);
        }

        .book-detail-shell .catalog-card-book__pages::before {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(90deg, rgba(120,96,58,0.04) 0, rgba(120,96,58,0.04) 2px, transparent 2px, transparent 6px);
            opacity: 0.9;
            pointer-events: none;
        }

        .book-detail-shell .catalog-card-book__pages::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 2;
            background: linear-gradient(90deg, rgba(244,238,227,0.98) 0%, rgba(244,238,227,0.94) 42%, rgba(244,238,227,0.3) 72%, rgba(244,238,227,0) 100%);
            transition: opacity 0.25s ease;
            pointer-events: none;
        }

        .book-detail-shell .catalog-card-book:hover .catalog-card-book__pages::after {
            opacity: 0;
        }

        .book-detail-shell .catalog-card-book__page-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.65rem;
            height: 100%;
            padding: 0.75rem;
        }

        .book-detail-shell .catalog-card-book__page-label {
            font-size: 0.58rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #8b6b3f;
        }

        .book-detail-shell .catalog-card-book__page-text {
            margin: 0;
            color: #3b3428;
            font-size: 0.68rem;
            line-height: 1.45;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 6;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
            hyphens: auto;
        }

        .book-detail-shell .catalog-card-book__page-meta {
            display: grid;
            gap: 0.3rem;
        }

        .book-detail-shell .catalog-card-book__page-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.4rem;
            padding-top: 0.28rem;
            border-top: 1px solid rgba(120, 96, 58, 0.14);
        }

        .book-detail-shell .catalog-card-book__page-row span {
            font-size: 0.53rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #8b6b3f;
        }

        .book-detail-shell .catalog-card-book__page-row strong {
            font-size: 0.6rem;
            color: #2f2b25;
            text-align: right;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .book-detail-shell .catalog-card-book__cover {
            position: absolute;
            inset: 0;
            border-radius: 0.35rem 0.75rem 0.75rem 0.35rem;
            overflow: hidden;
            transform-origin: left center;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.22);
            border-left: 2px solid rgba(0, 0, 0, 0.16);
            cursor: pointer;
            isolation: isolate;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .book-detail-shell .catalog-card-book__cover::before {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            width: 2px;
            background: rgba(255,255,255,0.18);
            z-index: 3;
            pointer-events: none;
        }

        .book-detail-shell .catalog-card-book:hover .catalog-card-book__cover {
            transform: rotateY(-90deg) translateX(-1px);
        }

        .book-detail-shell .catalog-card-book__cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.16) 0%, rgba(255,255,255,0.02) 45%, rgba(0,0,0,0.12) 100%);
            pointer-events: none;
            z-index: 2;
        }

        .book-detail-shell .catalog-card-book__cover-art {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0.28;
            z-index: 0;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .book-detail-shell .catalog-card-book__cover-shell {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.75rem;
            height: 100%;
            padding: 0.8rem 0.8rem 0.9rem;
        }

        .book-detail-shell .catalog-card-book__eyebrow {
            display: inline-flex;
            max-width: 100%;
            padding: 0.25rem 0.45rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.88);
            font-size: 0.58rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .book-detail-shell .catalog-card-book__title {
            margin: 0.5rem 0 0;
            color: #f4dda2;
            font-family: 'Newsreader', serif;
            font-size: 1.62rem;
            line-height: 1.02;
            letter-spacing: -0.02em;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
            hyphens: auto;
        }

        .book-detail-shell .catalog-card-book__author {
            margin: 0.35rem 0 0;
            color: rgba(255,255,255,0.82);
            font-size: 0.88rem;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            overflow-wrap: anywhere;
            word-break: break-word;
            hyphens: auto;
        }

        .book-detail-shell .catalog-card-book__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .book-detail-shell .catalog-card-book__meta span {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.42rem;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.9);
            font-size: 0.66rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .book-detail-shell #detail-metadata-panel .meta-list {
            font-size: 1rem;
        }

        .book-detail-shell #detail-availability-summary p,
        .book-detail-shell #detail-availability-summary strong,
        .book-detail-shell #detail-metadata-panel .meta-list,
        .book-detail-shell #access-formats p,
        .book-detail-shell #access-formats h3,
        .book-detail-shell #detail-abstract > div {
            font-size: 1.05rem;
        }

        .book-detail-shell #detail-abstract > div {
            max-width: 64ch;
        }

        .book-detail-shell .book-intro-panel {
            display: grid;
            gap: 1.25rem;
        }

        .book-detail-shell .book-detail-columns {
            display: grid;
            gap: 1.25rem;
        }

        .book-detail-shell .catalog-card-book--navy .catalog-card-book__cover {
            background:
                radial-gradient(circle at 18% 20%, rgba(159, 198, 255, 0.26), transparent 28%),
                linear-gradient(135deg, #163450 0%, #0c2138 100%);
        }

        .book-detail-shell .catalog-card-book--wine .catalog-card-book__cover {
            background:
                linear-gradient(145deg, rgba(255,255,255,0.08), transparent 34%),
                linear-gradient(135deg, #6f1d2d 0%, #441019 100%);
        }

        .book-detail-shell .catalog-card-book--forest .catalog-card-book__cover {
            background:
                radial-gradient(circle at 82% 18%, rgba(145, 239, 198, 0.22), transparent 26%),
                linear-gradient(135deg, #1e5a46 0%, #12372a 100%);
        }

        .book-detail-shell .catalog-card-book--wood .catalog-card-book__cover {
            background:
                linear-gradient(135deg, rgba(255,255,255,0.1), transparent 42%),
                repeating-linear-gradient(90deg, rgba(255, 228, 196, 0.08) 0 10px, transparent 10px 18px),
                linear-gradient(135deg, #6c4428 0%, #3d2416 100%);
        }

        .book-detail-shell .catalog-card-book--plum .catalog-card-book__cover {
            background:
                radial-gradient(circle at 20% 78%, rgba(255, 220, 245, 0.18), transparent 28%),
                linear-gradient(135deg, #54406d 0%, #2e2240 100%);
        }

        .book-detail-shell .catalog-card-book--navy .catalog-card-book__eyebrow,
        .book-detail-shell .catalog-card-book--forest .catalog-card-book__eyebrow {
            color: rgba(225, 245, 255, 0.9);
            background: rgba(255,255,255,0.1);
        }

        .book-detail-shell .catalog-card-book--wine .catalog-card-book__eyebrow,
        .book-detail-shell .catalog-card-book--plum .catalog-card-book__eyebrow {
            color: rgba(255, 232, 236, 0.92);
            background: rgba(255,255,255,0.12);
        }

        .book-detail-shell .catalog-card-book--wood .catalog-card-book__eyebrow {
            color: rgba(255, 241, 226, 0.92);
            background: rgba(255,255,255,0.14);
        }

        .book-detail-shell .catalog-card-book--navy .catalog-card-book__title,
        .book-detail-shell .catalog-card-book--forest .catalog-card-book__title {
            color: #f4f9ff;
        }

        .book-detail-shell .catalog-card-book--wine .catalog-card-book__title,
        .book-detail-shell .catalog-card-book--plum .catalog-card-book__title {
            color: #ffe7ec;
        }

        .book-detail-shell .catalog-card-book--wood .catalog-card-book__title {
            color: #fff1d8;
        }

        @media (prefers-reduced-motion: reduce) {
            .book-detail-shell .catalog-card-book__cover {
                transition: none;
            }
        }

        .storage-card {
            border: 1px solid #d8dde3;
            background: #fff;
            padding: 14px;
        }

        .storage-card h4 {
            margin: 0 0 8px;
            color: #293344;
            font-size: 12px;
            letter-spacing: .09em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .storage-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #edf1f5;
            font-size: 13px;
        }

        .storage-item:last-child { border-bottom: 0; }

        .storage-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 2px;
            white-space: nowrap;
        }

        .storage-pill.available { background: #d6f2ea; color: #0d766e; }
        .storage-pill.unavailable { background: #fce8e8; color: #a73a4a; }

        .detail-main {
            display: grid;
            gap: 20px;
        }

        .crumb-line {
            font-size: 11px;
            letter-spacing: .06em;
            color: #667384;
            text-transform: uppercase;
            font-weight: 700;
        }

        .detail-title {
            margin: 4px 0 0;
            font-family: 'Newsreader', Georgia, serif;
            font-size: clamp(40px, 4.6vw, 54px);
            line-height: 1.02;
            color: #0a2247;
            letter-spacing: -.35px;
            max-width: 900px;
        }

        .detail-subline {
            margin: 0;
            font-size: clamp(24px, 2.9vw, 30px);
            color: #334155;
            font-style: italic;
            line-height: 1.28;
        }

        .detail-subline .edition {
            font-style: normal;
            color: #16717d;
            margin-left: 8px;
            font-size: 18px;
            font-weight: 500;
        }

        .access-banner {
            position: relative;
            overflow: hidden;
            border: 1px solid #0f2d55;
            background: linear-gradient(135deg, #0c2f57 0%, #0a294d 78%);
            color: #fff;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
        }

        .access-banner::after {
            content: "";
            position: absolute;
            right: -30px;
            top: -20px;
            width: 130px;
            height: 130px;
            border: 1px solid rgba(197, 214, 238, .22);
            border-radius: 28px;
            transform: rotate(45deg);
        }

        .access-banner h4 {
            margin: 0 0 4px;
            font-family: 'Newsreader', Georgia, serif;
            font-size: 34px;
            letter-spacing: -.5px;
            line-height: .95;
        }

        .access-banner p {
            margin: 0;
            color: rgba(230, 236, 243, .92);
            font-size: 13px;
            line-height: 1.45;
            max-width: 430px;
        }

        .access-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .access-actions .btn {
            min-height: 40px;
            border-radius: 0;
            padding: 0 14px;
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .access-actions .btn-primary {
            background: #ffffff;
            color: #0b2a52;
        }

        .access-actions .btn-secondary {
            background: transparent;
            border: 1px solid rgba(220, 228, 240, .48);
            color: #fff;
            box-shadow: none;
        }

        .dual-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 1fr;
            gap: 24px;
            padding-top: 4px;
        }

        .dual-grid--single {
            grid-template-columns: 1fr;
        }

        .section-head {
            margin: 0 0 12px;
            font-size: 13px;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: #334155;
            font-weight: 800;
            border-bottom: 1px solid #d8dde3;
            padding-bottom: 8px;
        }

        .desc-text {
            color: #4b5563;
            line-height: 1.65;
            font-size: 16px;
            margin: 0;
            max-width: 560px;
        }

        .meta-list {
            display: grid;
            gap: 0;
        }

        .meta-line {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid #e7ebf0;
            font-size: 14px;
        }

        .meta-line span:first-child {
            color: #64748b;
            font-size: 11px;
            letter-spacing: .05em;
            text-transform: uppercase;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .meta-line span:last-child {
            color: #111827;
            font-weight: 700;
            text-align: right;
            max-width: 66%;
            overflow-wrap: anywhere;
        }

        .quick-facts {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .fact-card {
            border: 1px solid #d8dde3;
            background: #f8fafc;
            padding: 10px;
            min-height: 96px;
            display: grid;
            align-content: start;
            gap: 4px;
        }

        .fact-card span {
            color: #64748b;
            font-size: 11px;
            letter-spacing: .05em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .fact-card strong {
            color: #0b2a55;
            font-size: 28px;
            line-height: 1;
            font-family: 'Newsreader', Georgia, serif;
            overflow-wrap: anywhere;
        }

        .fact-card small {
            color: #5a6673;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
        }

        .licensed {
            margin-top: 22px;
        }

        .licensed-items {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .licensed-chip {
            border: 1px solid #d8dde3;
            background: #f8fafc;
            padding: 10px 11px;
            font-size: 11px;
            color: #1f395d;
            font-weight: 700;
        }

        .similar-wrap {
            margin-top: 10px;
        }

        .similar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .similar-head h3 {
            margin: 0;
            font-family: 'Newsreader', Georgia, serif;
            color: #0a2247;
            font-size: clamp(34px, 4.2vw, 42px);
            letter-spacing: -.3px;
            line-height: 1.05;
        }

        .similar-head a {
            color: #0f766e;
            font-size: 13px;
            font-weight: 700;
        }

        .similar-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .similar-card {
            border: 1px solid #d8dde3;
            background: #fff;
            overflow: hidden;
        }

        .similar-image {
            height: 158px;
            background: linear-gradient(180deg, #244166, #10233d);
            position: relative;
        }

        .similar-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,.15), rgba(255,255,255,0) 45%);
        }

        .similar-card:nth-child(2) .similar-image { background: linear-gradient(180deg, #75522e, #3f2816); }
        .similar-card:nth-child(3) .similar-image { background: linear-gradient(180deg, #5b6774, #313a46); }

        .similar-body {
            padding: 10px;
        }

        .similar-title {
            margin: 0;
            font-size: 34px;
            line-height: .95;
            color: #0a2247;
            font-family: 'Newsreader', Georgia, serif;
            letter-spacing: -.4px;
        }

        .similar-meta {
            margin-top: 5px;
            color: #7b8795;
            font-size: 10px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        /* Catalog card parity for detail/similar sections */
        .catalog-book-card {
            position: relative;
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, rgba(255,255,255,.99), rgba(245,247,248,.96));
            border: 1px solid rgba(195,198,209,.7);
            box-shadow: 0 10px 24px rgba(25,28,29,.03);
            padding: 14px;
            overflow: hidden;
        }

        .catalog-book-stage {
            position: relative;
            height: 390px;
            margin-bottom: 12px;
        }

        .catalog-book-body {
            position: absolute;
            inset: 0;
            z-index: 0;
            border-radius: 2px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(250,250,250,.94));
            border: 1px solid rgba(195,198,209,.3);
        }

        .catalog-book-body-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            padding-top: 6px;
            border-top: 1px solid rgba(124, 110, 84, .14);
            font-size: 11px;
        }

        .catalog-book-body-row span {
            color: #7c6e54;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .catalog-book-body-row strong {
            color: #403623;
            font-size: 11px;
            text-align: right;
            max-width: 58%;
            word-break: break-word;
        }

        .catalog-book-cover {
            position: absolute;
            inset: 0;
            z-index: 2;
            border-radius: 2px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.06), 0 12px 24px rgba(25,28,29,.1);
            isolation: isolate;
        }

        .catalog-book-cover::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.04), transparent 42%, rgba(0,0,0,.06) 100%);
            pointer-events: none;
        }

        .catalog-cover-top {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }

        .catalog-cover-year {
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,.09);
            color: rgba(255,255,255,.88);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .catalog-cover-code {
            padding: 5px 10px;
            border-radius: 999px;
            background: rgba(255,255,255,.09);
            color: rgba(255,255,255,.88);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .catalog-cover-kicker {
            color: rgba(255,255,255,.64);
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .catalog-cover-title {
            margin: 6px 0 0;
            color: #f2d79b;
            font-family: 'Newsreader', Georgia, serif;
            font-size: clamp(30px, 3vw, 42px);
            line-height: 1;
            letter-spacing: -.45px;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
            hyphens: auto;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .catalog-cover-subline {
            margin-top: 6px;
            color: rgba(255,255,255,.84);
            font-size: 13px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .catalog-cover-isbn {
            color: rgba(255,255,255,.68);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .catalog-cover-isbn strong {
            display: block;
            margin-top: 3px;
            color: #fff;
            letter-spacing: .02em;
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
            overflow-wrap: anywhere;
        }

        .catalog-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }

        .catalog-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 2px;
            font-size: 11px;
            font-weight: 800;
            color: #1f3552;
            background: #eef2f7;
            border: 1px solid rgba(195,198,209,.7);
        }

        .catalog-tag.green {
            color: #0f766e;
            background: #dff5ee;
        }

        .catalog-copy h3 {
            margin: 0 0 8px;
            font-size: clamp(28px, 2.8vw, 40px);
            font-family: 'Newsreader', Georgia, serif;
            line-height: 1.03;
            color: #0b2a55;
            overflow-wrap: anywhere;
        }

        .catalog-copy p {
            margin: 0;
            color: #4f5f71;
            line-height: 1.5;
            font-size: 13px;
        }

        .catalog-book-info {
            margin-top: 10px;
            display: grid;
            gap: 0;
        }

        .catalog-book-info-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid #e7ebf0;
            font-size: 13px;
        }

        .catalog-book-info-row span:first-child {
            color: #5f6d7d;
        }

        .catalog-book-info-row span:last-child {
            color: #374151;
            font-weight: 700;
            text-align: right;
            max-width: 62%;
            overflow-wrap: anywhere;
        }

        .catalog-missing {
            margin-top: 8px;
            color: #9f1239;
            font-size: 13px;
            line-height: 1.5;
        }

        .catalog-tone-navy { background: linear-gradient(180deg, #2d4268 0%, #223758 100%); }
        .catalog-tone-wine { background: linear-gradient(180deg, #8f1f1f 0%, #6d1111 100%); }
        .catalog-tone-forest { background: linear-gradient(180deg, #205f43 0%, #134935 100%); }

        .catalog-book-card--mini .catalog-book-stage {
            height: 268px;
        }

        .catalog-book-card--mini {
            height: 100%;
            min-height: 430px;
        }

        .catalog-book-card--mini .catalog-cover-title {
            font-size: 24px;
            -webkit-line-clamp: 2;
        }

        .catalog-book-card--mini .catalog-cover-subline {
            font-size: 11px;
        }

        .catalog-book-card--mini .catalog-cover-isbn strong {
            font-size: 18px;
        }

        .catalog-book-card--mini .catalog-copy h3 {
            font-size: 20px;
            line-height: 1.28;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .catalog-book-card--mini .catalog-copy p {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .content-panels {
            display: grid;
            gap: var(--space-4);
        }

        .content-panel {
            border: 1px solid #d8dde3;
            background: #fff;
            padding: 14px 16px;
        }

        .description-card {
            border: 1px solid #d8dde3;
            background: #fdfefe;
            padding: 14px 16px;
        }

        .description-text {
            margin: 0;
            color: #334155;
            font-size: var(--type-body);
            line-height: 1.7;
            white-space: pre-line;
            max-width: 68ch;
        }

        .similar-link {
            display: block;
            color: inherit;
            text-decoration: none;
            height: 100%;
        }

        .similar-link:focus-visible .catalog-book-card {
            outline: 2px solid #0f766e;
            outline-offset: 2px;
        }

        .similar-link .catalog-book-card {
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .similar-link:hover .catalog-book-card {
            transform: translateY(-2px);
            border-color: rgba(11, 42, 85, .25);
            box-shadow: 0 14px 28px rgba(25, 28, 29, .06);
        }

        .desc-text strong {
            color: #0b2a55;
            font-weight: 700;
        }

        .meta-aux {
            margin-top: 12px;
            display: grid;
            gap: 8px;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(11,42,85,.06);
            color: #0b2a55;
            font-size: 11px;
            font-weight: 700;
            margin-right: 6px;
            margin-bottom: 6px;
        }

        @media (max-width: 1120px) {
            .detail-shell,
            .dual-grid {
                grid-template-columns: 1fr;
            }

            .content-panels {
                gap: var(--space-3);
            }

            .catalog-book-stage {
                height: 360px;
            }

            .quick-facts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .similar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .layout,
            .info-grid,
            .cards-section,
            .meta-grid {
                grid-template-columns: 1fr 1fr;
            }

            .layout { grid-template-columns: 1fr; }
            .book-panel { position: static; }
            .meta-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 860px) {
            .nav-links { display: none; }
            .meta-grid,
            .info-grid,
            .cards-section,
            .mini-actions {
                grid-template-columns: 1fr;
            }

            .action-row {
                flex-direction: column;
            }

            .action-row .btn,
            .nav-actions .btn {
                width: 100%;
                min-height: 44px;
            }

            .nav-actions { display: none; }
            .book-cover-wrap { min-height: 360px; }
            .book-mockup {
                width: 220px;
                height: 320px;
            }
            .cover-title { font-size: 28px; }
            .mobile-toggle { display: inline-grid; place-items: center; min-width: 44px; min-height: 44px; }
        }

        @media (max-width: 560px) {
            .detail-title {
                font-size: 36px;
                line-height: 1.05;
            }

            .detail-subline {
                font-size: 22px;
                line-height: 1.35;
            }

            .access-banner {
                flex-direction: column;
                align-items: flex-start;
            }

            .catalog-book-stage {
                height: 318px;
            }

            .quick-facts {
                grid-template-columns: 1fr;
            }

            .access-actions {
                width: 100%;
            }

            .access-actions .btn {
                flex: 1 1 auto;
            }

            .similar-grid {
                grid-template-columns: 1fr;
            }

            .meta-line {
                display: grid;
                gap: 4px;
            }

            .meta-line span:first-child {
                min-width: 0;
            }

            .meta-line span:last-child {
                text-align: left;
                max-width: 100%;
            }

            .container { width: min(100% - 20px, var(--container)); }
            .nav { min-height: 64px; }
            .brand-text { font-size: 13px; }
            .brand-text small { font-size: 10.5px; }
            .title { letter-spacing: -1px; font-size: 24px; }
            .details-card,
            .book-panel,
            .info-card { padding: 18px; }
            .book-cover-wrap { padding: 16px; min-height: 280px; }
            .book-mockup { width: 180px; height: 260px; }
            .cover-title { font-size: 22px; }
            .meta-item { padding: 14px; }
        }
    </style>
    <link rel="stylesheet" href="/css/public-unified.css">
    <style>
        body.site-shell.book-detail-shell .page {
            width: 100%;
            max-width: var(--page-max);
            margin-inline: auto;
            padding: 84px var(--page-pad) 70px;
        }

        body.site-shell.book-detail-shell .container {
            width: 100%;
            max-width: none;
            margin: 0;
        }

        @media (max-width: 560px) {
            body.site-shell.book-detail-shell .page {
                padding: 88px var(--page-pad) 56px;
            }
        }
    </style>
</head>
<body class="site-shell book-detail-shell" data-library-tailwind-radius="compact">
    <a class="skip-link" href="#main-content">{{ ['ru' => 'К основному содержанию', 'kk' => 'Негізгі мазмұнға өту', 'en' => 'Skip to main content'][$lang] }}</a>
    @include('partials.navbar', ['activePage' => 'catalog'])

    <main class="page" id="main-content" tabindex="-1">
        <div class="container">
            <div class="sr-only">{{ ['ru' => 'Просмотр книги', 'kk' => 'Кітапты қарау', 'en' => 'Book view'][$lang] }}</div>
            @if (session('success'))
                <div role="status" style="margin:0 0 16px; padding:12px 14px; border-radius:8px; font-size:13px; background:#d1fae5; color:#065f46; border:1px solid #a7f3d0;">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div role="alert" style="margin:0 0 16px; padding:12px 14px; border-radius:8px; font-size:13px; background:#fee2e2; color:#991b1b; border:1px solid #fecaca;">
                    @foreach ($errors->all() as $message)
                        <p style="margin:0;">{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            @if ($canReserveRecord)
                {{-- Submitted by the reserve button inside the JS-rendered detail
                     panel via its `form` attribute; kept in static markup so the
                     CSRF token and record id never depend on client scripting. --}}
                <form id="member-reserve-form" method="POST" action="{{ route('member.reservations.store') }}" style="display:none;">
                    @csrf
                    <input type="hidden" name="bibliographic_record_id" value="{{ $reserveRecordId }}" />
                </form>
            @endif

            <div id="loading" class="loading"><div class="spinner"></div><p style="margin:8px 0 0;">{{ ['ru' => 'Загрузка информации о книге...', 'kk' => 'Кітап туралы ақпарат жүктелуде...', 'en' => 'Loading book details...'][$lang] }}</p></div>
            <div id="error" class="error" style="display: none;"></div>
            <div id="content"></div>
        </div>
    </main>

    @include('partials.footer')

    <script>
        const isbn = @json($bookIsbn ?? '') || window.location.pathname.split('/').pop();
        const BOOK_DB_API_ENDPOINT = '/api/v1/book-db/';
        const BOOK_BOOTSTRAP = @json($bookBootstrap);
        const BOOK_LANG = @json($lang);
        const BOOK_TITLE_SUFFIX = @json($bookTitleSuffix);
        const CATALOG_URL = @json($catalogHref);
        const LOGIN_URL = @json($loginHref);
        const IS_AUTHENTICATED = @json(session('library.user') !== null);
        const BOOK_I18N_MAP = {
            ru: {
                notFound: 'Книга не найдена', genericError: 'Ошибка', loadFailed: 'Не удалось загрузить сведения о книге. Попробуйте позже.', backToCatalog: 'Вернуться в каталог', untitled: 'Без названия', unknownAuthor: 'Неизвестный автор',
                publisherMissing: 'Издатель не указан', isbnMissing: 'ISBN не указан', yearMissing: 'Год не указан', languageMissing: 'Язык неизвестен',
                unit: 'Подразделение', campus: 'Корпус', servicePoint: 'Библиотечная точка', hallSigla: 'Зал / сигла хранения', exactShelf: 'Полка / стеллаж / секция', available: 'Доступно сейчас', locationsUnavailable: 'Информация о местах хранения недоступна', dueDate: 'Вернуть до', activeLoan: 'Эта книга у вас на руках',
                home: 'Главная', catalog: 'Каталог', abstract: 'Аннотация', subjectTerms: 'Предметные рубрики', languageMedia: 'Язык и носитель', editionLabel: 'Редакция', editionMissing: 'Не указана', callNumber: 'Шифр хранения',
                coverTop: 'Каталог', availableNow: ' Доступно сейчас', unavailableNow: ' Недоступно', invalidIsbn: 'ISBN не валиден', authors: 'Авторы', author: 'Автор', publicationYear: 'Год издания', language: 'Язык',
                availableShort: 'Доступно', totalCopies: 'Всего экземпляров', issuedCopies: 'Выдано', copySummary: '{available}', copyAvailable: 'Экземпляр доступен для выдачи', copyAvailableBody: 'Сейчас доступно: {available}.',
                allCheckedOut: 'Все экземпляры выданы', allCheckedOutBody: 'Свободных экземпляров сейчас нет.', inStock: 'В наличии', unavailable: 'Недоступно',
                reserve: 'Забронировать', joinQueue: 'Встать в очередь', pickup: 'Место получения', signInToReserve: 'Войдите для бронирования', shortlistAdd: '☆ В подборку', shortlistAdded: '★ В подборке', cite: 'Цитировать', characteristics: 'Характеристики',
                publisher: 'Издательство', originalTitle: 'Оригинальное название', publicationLanguage: 'Язык издания', resourceType: 'Тип ресурса', authorMark: 'Авторский знак', keywords: 'Ключевые слова', relatedMaterials: 'Связанные материалы', similarByUdc: 'Похожие материалы в этом разделе', availableNowLabel: 'Доступно сейчас', availabilityByPoint: 'Наличие по пунктам выдачи', locationLabel: 'Локация', accessAndFormat: 'Доступ и формат',
                digitalMaterials: ' Электронные материалы', open: 'Открыть', login: 'Войти', checking: ' Проверка...', reservedReady: ' Уже забронировано', reserving: ' Бронирование...', noCopies: 'Нет экземпляров', reservationUnavailable: 'Бронирование недоступно',
                reservedState: ' Забронировано ({status})', readyForPickup: 'готово к выдаче', waiting: 'ожидание', reserveSuccess: 'Книга успешно забронирована!', validUntil: 'Действует до {date}.', followStatus: 'Следите за статусом в кабинете.', description: 'Аннотация', metadata: 'Метаданные', licensedResources: 'Лицензированные ссылки и ресурсы', similarResources: 'Похожие академические ресурсы', browseMore: 'Смотреть ещё', statusInStorage: 'Статус в фонде', allCollections: 'Основная коллекция', physicalAvailable: 'Физический экземпляр доступен для выдачи', physicalUnavailable: 'Физический фонд есть, но свободных экземпляров сейчас нет', digitalOpen: 'Цифровой просмотр открыт', digitalRestricted: 'Цифровой доступ ограничен', digitalCampus: 'Только из сети университета', digitalAuthenticated: 'После входа в кабинет',
                licenseTerms: 'Условия использования', accessFormats: 'Форматы доступа', printCopy: 'Печатный экземпляр', digitalVersion: 'Электронная версия', digitalChecking: 'Проверяем наличие электронной версии…', digitalNone: 'Электронной версии у этого издания пока нет.', digitalNoDownload: 'Скачивание запрещено правообладателем — доступно только чтение онлайн.', digitalFullscreen: 'Открыть на весь экран', digitalLevelPublic: 'Открыто всем', digitalLevelReaders: 'Для читателей библиотеки', digitalClose: 'Закрыть просмотрщик', reserveFailed: 'Не удалось создать бронирование.', networkError: 'Ошибка сети. Попробуйте ещё раз.',
                digitalRead: 'Читать онлайн', digitalExternal: 'Открыть у источника', digitalDownload: 'Скачать', digitalSignIn: 'Войти для доступа'
            },
            kk: {
                notFound: 'Кітап табылмады', genericError: 'Қате', loadFailed: 'Кітап туралы мәліметтерді жүктеу мүмкін болмады. Кейінірек қайталап көріңіз.', backToCatalog: 'Каталогқа оралу', untitled: 'Атауы жоқ', unknownAuthor: 'Автор белгісіз',
                publisherMissing: 'Баспа көрсетілмеген', isbnMissing: 'ISBN көрсетілмеген', yearMissing: 'Жылы көрсетілмеген', languageMissing: 'Тілі белгісіз',
                unit: 'Бөлім', campus: 'Корпус', servicePoint: 'Кітапхана нүктесі', hallSigla: 'Зал / сақтау сигласы', exactShelf: 'Сөре / стеллаж / секция', available: 'Қазір қолжетімді', locationsUnavailable: 'Сақталу орындары туралы ақпарат жоқ', dueDate: 'Қайтару мерзімі', activeLoan: 'Бұл кітап сізде',
                home: 'Басты бет', catalog: 'Каталог', abstract: 'Аңдатпа', subjectTerms: 'Пәндік айдарлар', languageMedia: 'Тіл және тасымалдағыш', editionLabel: 'Басылым', editionMissing: 'Көрсетілмеген', callNumber: 'Сақтау шифры',
                coverTop: 'Каталог', availableNow: ' Қазір қолжетімді', unavailableNow: ' Қолжетімсіз', invalidIsbn: 'ISBN жарамсыз', authors: 'Авторлар', author: 'Автор', publicationYear: 'Басылым жылы', language: 'Тіл',
                availableShort: 'Қолжетімді', totalCopies: 'Барлық дана', issuedCopies: 'Берілген', copySummary: '{available}', copyAvailable: 'Данасы берілуге қолжетімді', copyAvailableBody: 'Қазір қолжетімді: {available}.',
                allCheckedOut: 'Барлық даналар берілген', allCheckedOutBody: 'Қазір бос дана жоқ.', inStock: 'Қолда бар', unavailable: 'Қолжетімсіз',
                reserve: 'Брондау', joinQueue: 'Кезекке тұру', pickup: 'Алу орны', signInToReserve: 'Брондау үшін кіріңіз', shortlistAdd: '☆ Топтамаға', shortlistAdded: '★ Топтамада', cite: 'Дәйексөз', characteristics: 'Сипаттамалар',
                publisher: 'Баспа', originalTitle: 'Түпнұсқа атауы', publicationLanguage: 'Басылым тілі', resourceType: 'Ресурс түрі', authorMark: 'Авторлық белгі', keywords: 'Кілт сөздер', relatedMaterials: 'Байланысты материалдар', similarByUdc: 'Осы бөлімдегі ұқсас материалдар', availableNowLabel: 'Қазір қолжетімді', availabilityByPoint: 'Берілім нүктелері бойынша қолжетімділік', locationLabel: 'Локация', accessAndFormat: 'Қолжетімділік пен формат',
                digitalMaterials: ' Электрондық материалдар', open: 'Ашу', login: 'Кіру', checking: ' Тексеру...', reservedReady: ' Бұрыннан брондалған', reserving: ' Брондау...', noCopies: 'Дана жоқ', reservationUnavailable: 'Брондау қолжетімсіз',
                reservedState: ' Брондалған ({status})', readyForPickup: 'беруге дайын', waiting: 'күту', reserveSuccess: 'Кітап сәтті брондалды!', validUntil: '{date} дейін жарамды.', followStatus: 'Күйін кабинеттен бақылаңыз.', description: 'Аңдатпа', metadata: 'Метадеректер', licensedResources: 'Лицензиялық сілтемелер мен ресурстар', similarResources: 'Ұқсас академиялық ресурстар', browseMore: 'Тағы көру', statusInStorage: 'Қордағы мәртебе', allCollections: 'Негізгі қор', physicalAvailable: 'Физикалық дана берілуге қолжетімді', physicalUnavailable: 'Физикалық қор бар, бірақ бос дана жоқ', digitalOpen: 'Цифрлық қарау ашық', digitalRestricted: 'Цифрлық қолжетімділік шектеулі', digitalCampus: 'Тек университет желісінде', digitalAuthenticated: 'Кабинетке кіргеннен кейін',
                licenseTerms: 'Пайдалану шарттары', accessFormats: 'Қолжетімділік форматтары', printCopy: 'Басылған дана', digitalVersion: 'Электрондық нұсқа', digitalChecking: 'Электрондық нұсқаның бар-жоғы тексерілуде…', digitalNone: 'Бұл басылымның электрондық нұсқасы әзірге жоқ.', digitalNoDownload: 'Жүктеп алуға құқық иесі рұқсат бермеген — тек онлайн оқуға болады.', digitalFullscreen: 'Толық экранда ашу', digitalLevelPublic: 'Барлығына ашық', digitalLevelReaders: 'Кітапхана оқырмандарына', digitalClose: 'Қарау терезесін жабу', reserveFailed: 'Брондауды жасау мүмкін болмады.', networkError: 'Желі қатесі. Қайта көріңіз.',
                digitalRead: 'Онлайн оқу', digitalExternal: 'Дереккөзде ашу', digitalDownload: 'Жүктеп алу', digitalSignIn: 'Қолжетімділік үшін кіру'
            },
            en: {
                notFound: 'Book not found', genericError: 'Error', loadFailed: 'Book details could not be loaded. Please try again later.', backToCatalog: 'Back to Catalog', untitled: 'Untitled', unknownAuthor: 'Unknown author',
                publisherMissing: 'Publisher not specified', isbnMissing: 'ISBN not provided', yearMissing: 'Year not specified', languageMissing: 'Language unknown',
                unit: 'Unit', campus: 'Building', servicePoint: 'Library point', hallSigla: 'Room / storage sigla', exactShelf: 'Shelf / stack / section', available: 'Available now', locationsUnavailable: 'Location details are unavailable', dueDate: 'Due date', activeLoan: 'You currently have this book',
                home: 'Home', catalog: 'Catalog', abstract: 'Abstract', subjectTerms: 'Subject Terms', languageMedia: 'Language & Media', editionLabel: 'Edition', editionMissing: 'Not specified', callNumber: 'Call Number',
                coverTop: 'Catalog', availableNow: ' Available now', unavailableNow: ' Unavailable', invalidIsbn: 'Invalid ISBN', authors: 'Authors', author: 'Author', publicationYear: 'Publication year', language: 'Language',
                availableShort: 'Available', totalCopies: 'Total copies', issuedCopies: 'On loan', copySummary: '{available}', copyAvailable: 'A copy is available for checkout', copyAvailableBody: 'Available now: {available}.',
                allCheckedOut: 'All copies are checked out', allCheckedOutBody: 'No copies are free right now.', inStock: 'In stock', unavailable: 'Unavailable',
                reserve: 'Reserve', joinQueue: 'Join queue', pickup: 'Pickup location', signInToReserve: 'Sign in to reserve', shortlistAdd: '☆ Add to shortlist', shortlistAdded: '★ In shortlist', cite: 'Cite', characteristics: 'Details',
                publisher: 'Publisher', originalTitle: 'Original title', publicationLanguage: 'Publication language', resourceType: 'Resource type', authorMark: 'Author mark', keywords: 'Keywords', relatedMaterials: 'Related materials', similarByUdc: 'Similar materials in this section', availableNowLabel: 'Available now', availabilityByPoint: 'Availability by service point', locationLabel: 'Location', accessAndFormat: 'Access and format',
                digitalMaterials: ' Digital materials', open: 'Open', login: 'Sign in', checking: ' Checking...', reservedReady: ' Already reserved', reserving: ' Reserving...', noCopies: 'No copies', reservationUnavailable: 'Reservation unavailable',
                reservedState: ' Reserved ({status})', readyForPickup: 'ready for pickup', waiting: 'waiting', reserveSuccess: 'The book has been reserved successfully!', validUntil: 'Valid until {date}.', followStatus: 'Track the status in your account.', description: 'Abstract', metadata: 'Metadata', licensedResources: 'Licensed references & resources', similarResources: 'Similar Academic Resources', browseMore: 'Browse More', statusInStorage: 'Status in Storage', allCollections: 'Main Collection', physicalAvailable: 'A physical copy is available for checkout', physicalUnavailable: 'Physical holding exists, but no copy is free right now', digitalOpen: 'Digital viewer available', digitalRestricted: 'Digital access restricted', digitalCampus: 'Campus network only', digitalAuthenticated: 'After sign-in',
                licenseTerms: 'Terms of use', accessFormats: 'Access formats', printCopy: 'Print copy', digitalVersion: 'Digital edition', digitalChecking: 'Checking for a digital edition…', digitalNone: 'This title has no digital edition yet.', digitalNoDownload: 'Downloading is not permitted by the rights holder — reading online only.', digitalFullscreen: 'Open full screen', digitalLevelPublic: 'Open to everyone', digitalLevelReaders: 'Library readers only', digitalClose: 'Close the viewer', reserveFailed: 'Unable to create the reservation.', networkError: 'Network error. Please try again.',
                digitalRead: 'Read online', digitalExternal: 'Open at source', digitalDownload: 'Download', digitalSignIn: 'Sign in for access'
            }
        };
        const BOOK_I18N = BOOK_I18N_MAP[BOOK_LANG] || BOOK_I18N_MAP.ru;
        const BOOK_RECOVERY_I18N = @json(__('catalog_recovery.public'));
        const BOOK_UDC_LABEL = { ru: 'УДК', kk: 'ӘОЖ', en: 'UDC' }[BOOK_LANG] || 'УДК';
        const BOOK_RESOURCE_TYPE_LABELS = {
            ru: { book: 'Книга', textbook: 'Учебник', study_guide: 'Учебное пособие', methodical: 'Методическое пособие', journal: 'Журнал', periodical: 'Периодическое издание', dissertation: 'Диссертация', abstract: 'Автореферат', article: 'Статья', publication: 'Научная публикация', ebook: 'Электронная книга', digital_document: 'Электронный документ', _default: 'Документ' },
            kk: { book: 'Кітап', textbook: 'Оқулық', study_guide: 'Оқу құралы', methodical: 'Әдістемелік құрал', journal: 'Журнал', periodical: 'Мерзімді басылым', dissertation: 'Диссертация', abstract: 'Автореферат', article: 'Мақала', publication: 'Ғылыми жарияланым', ebook: 'Электрондық кітап', digital_document: 'Электрондық құжат', _default: 'Құжат' },
            en: { book: 'Book', textbook: 'Textbook', study_guide: 'Study guide', methodical: 'Methodological guide', journal: 'Journal', periodical: 'Periodical', dissertation: 'Dissertation', abstract: 'Thesis abstract', article: 'Article', publication: 'Scholarly publication', ebook: 'E-book', digital_document: 'Digital document', _default: 'Document' }
        };

        function withLang(path) {
            const url = new URL(path, window.location.origin);
            if (BOOK_LANG !== 'kk' && !url.searchParams.has('lang')) {
                url.searchParams.set('lang', BOOK_LANG);
            }
            return `${url.pathname}${url.search}`;
        }

        function formatResourceType(value) {
            const labels = BOOK_RESOURCE_TYPE_LABELS[BOOK_LANG] || BOOK_RESOURCE_TYPE_LABELS.ru;
            const key = normalizeText(value, '').toLowerCase();
            return labels[key] || labels._default;
        }

        async function loadBook() {
            const loading = document.getElementById('loading');
            const error = document.getElementById('error');
            const content = document.getElementById('content');

            try {
                if (BOOK_BOOTSTRAP) {
                    renderBook(BOOK_BOOTSTRAP);
                    loading.style.display = 'none';
                    return;
                }

                const book = await fetchBookWithFallback(isbn);

                if (!book) {
                    throw new Error(BOOK_I18N.notFound);
                }

                renderBook(book);
                loading.style.display = 'none';
            } catch (err) {
                loading.style.display = 'none';
                error.style.display = 'block';
                const publicMessage = err?.message === BOOK_I18N.notFound
                    ? BOOK_I18N.notFound
                    : BOOK_I18N.loadFailed;
                error.innerHTML = `
                    <strong>${BOOK_I18N.genericError}:</strong> ${publicMessage}
                    <div style="margin-top: 1rem;">
                        <a href="${CATALOG_URL}" class="btn btn-ghost">${BOOK_I18N.backToCatalog}</a>
                    </div>
                `;
            }
        }

        async function fetchBookWithFallback(identifier) {
            const encodedIdentifier = encodeURIComponent(identifier);
            const endpoints = [
                `${BOOK_DB_API_ENDPOINT}${encodedIdentifier}`,
            ];

            let lastError = null;

            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint);

                    if (!response.ok) {
                        if (response.status === 404) {
                            lastError = new Error(BOOK_I18N.notFound);
                            continue;
                        }

                        throw new Error(BOOK_I18N.loadFailed);
                    }

                    const result = await response.json();
                    if (result?.data) {
                        return result.data;
                    }
                } catch (error) {
                    lastError = error;
                }
            }

            throw lastError || new Error(BOOK_I18N.notFound);
        }

        function normalizeText(value, fallback = '') {
            if (!value) return fallback;
            if (typeof value !== 'string') return fallback;
            return value.trim() || fallback;
        }

        function isNoiseText(value) {
            const text = normalizeText(value, '');
            return text !== '' && /^(?:текст|text)$/i.test(text);
        }

        function pickMeaningfulText(...values) {
            for (const value of values) {
                const text = normalizeText(value, '');
                if (text === '' || isNoiseText(text)) continue;
                return text;
            }
            return '';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatLocationLabel(location) {
            const serviceCode = String(location?.servicePoint?.code || '').trim().toLowerCase();
            const serviceName = String(location?.servicePoint?.name || '').trim();
            const campusCode = String(location?.campus?.code || '').trim().toLowerCase();
            const unitCode = String(location?.institutionUnit?.code || '').trim().toLowerCase();
            const unitName = String(location?.institutionUnit?.name || '').trim();

            if (serviceCode === 'economics-desk' || campusCode === 'economics-desk') return BOOK_LANG === 'en' ? 'Economics Library' : (BOOK_LANG === 'kk' ? 'Экономика кітапханасы' : 'Экономическая библиотека');
            if (serviceCode === 'technology-desk' || campusCode === 'technology-desk') return BOOK_LANG === 'en' ? 'Technology Library' : (BOOK_LANG === 'kk' ? 'Технологиялық кітапхана' : 'Технологическая библиотека');
            if (serviceCode === 'college' || unitCode === 'college') return BOOK_LANG === 'en' ? 'College Library' : (BOOK_LANG === 'kk' ? 'Колледж кітапханасы' : 'Библиотека колледжа');
            if (['scientific-library', 'reading-room'].includes(serviceCode) || ['scientific-library', 'reading-room'].includes(campusCode)) return BOOK_LANG === 'en' ? 'Scientific Library' : (BOOK_LANG === 'kk' ? 'Ғылыми кітапхана' : 'Научная библиотека');

            return serviceName || unitName || BOOK_I18N.locationsUnavailable;
        }

        function splitIntoParagraphs(text, extra = '') {
            const pieces = String(text || '')
                .split(/\n+/)
                .map((item) => item.trim())
                .filter(Boolean);

            if (pieces.length >= 2) return pieces.slice(0, 2);
            if (pieces.length === 1 && extra) return [pieces[0], extra];
            if (pieces.length === 1) return pieces;
            return extra ? [extra] : [];
        }

        function formatInteger(value) {
            return new Intl.NumberFormat(BOOK_LANG === 'kk' ? 'kk-KZ' : (BOOK_LANG === 'en' ? 'en-US' : 'ru-RU'))
                .format(Number(value || 0));
        }

        function formatCopyCount(value) {
            const count = Number(value || 0);
            if (BOOK_LANG === 'kk') return `${formatInteger(count)} дана`;
            if (BOOK_LANG === 'en') return `${formatInteger(count)} ${count === 1 ? 'copy' : 'copies'}`;
            const mod10 = count % 10;
            const mod100 = count % 100;
            const noun = mod10 === 1 && mod100 !== 11
                ? 'экземпляр'
                : ((mod10 >= 2 && mod10 <= 4) && !(mod100 >= 12 && mod100 <= 14) ? 'экземпляра' : 'экземпляров');
            return `${formatInteger(count)} ${noun}`;
        }

        function copyCitation(text) {
            if (!text) return;
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(text).catch(() => {});
            }
        }

        function renderBook(book) {
            const content = document.getElementById('content');
            const rawTitle = normalizeText(book?.title?.display || book?.title?.raw, BOOK_I18N.untitled);
            const originalTitle = normalizeText(book?.title?.original || book?.title?.raw, '');
            const rawSubtitle = pickMeaningfulText(book?.title?.subtitle);
            const rawAuthor = normalizeText(book?.primaryAuthor || BOOK_I18N.unknownAuthor);
            const rawPublisher = normalizeText(book?.publisher?.name || BOOK_I18N.publisherMissing);
            const rawIsbn = normalizeText(book?.isbn?.raw || BOOK_I18N.isbnMissing);
            const rawIssn = normalizeText(book?.issn?.raw, '');
            const rawYear = normalizeText(book?.publicationYear || BOOK_I18N.yearMissing);
            const rawLanguage = normalizeText(book?.language?.label, BOOK_I18N.languageMissing);
            const publicationPlace = pickMeaningfulText(book?.publicationPlace);
            const responsibility = pickMeaningfulText(book?.statementOfResponsibility);
            const editionStatement = pickMeaningfulText(book?.editionStatement);
            const physicalDescription = [book?.physicalExtent, book?.physicalDetails, book?.dimensions, book?.accompanyingMaterial]
                .map((value) => pickMeaningfulText(value))
                .filter(Boolean)
                .join(' ; ');
            const seriesDescription = [book?.seriesTitle, book?.seriesNumber]
                .map((value) => pickMeaningfulText(value))
                .filter(Boolean)
                .join(' ; ');
            const partDescription = [book?.volume, book?.issue, book?.partNumber, book?.partTitle]
                .map((value) => pickMeaningfulText(value))
                .filter(Boolean)
                .join(' · ');
            const bbkCode = pickMeaningfulText(book?.bbkCode);
            const localClassification = pickMeaningfulText(book?.localClassification);
            const materialDesignation = pickMeaningfulText(book?.materialDesignation);
            const controlNumber = pickMeaningfulText(book?.controlNumber);
            const countryCodeRaw = pickMeaningfulText(book?.countryCode);
            const countryCode = countryCodeRaw
                ? (BOOK_RECOVERY_I18N?.countries?.[countryCodeRaw.toLowerCase()] || countryCodeRaw.toUpperCase())
                : '';
            const ksuLiteratureType = pickMeaningfulText(book?.ksuLiteratureType);
            const faculty = pickMeaningfulText(book?.faculty);
            const department = pickMeaningfulText(book?.department);
            const disciplines = pickMeaningfulText(book?.disciplines);
            const specialty = pickMeaningfulText(book?.specialty);
            const recordCreatedOn = pickMeaningfulText(book?.recordCreatedOn);
            // A subtitle is real metadata, but it is not an abstract. Keep the
            // abstract section absent when the source has no annotation.
            const rawDescription = pickMeaningfulText(book?.annotation);
            const available = Number(book?.copies?.available || 0);
            const issued = Number(book?.copies?.issued || 0);
            const total = Number(book?.copies?.total || 0);
            const authors = Array.isArray(book?.authors) ? book.authors : [];
            const contributors = Array.isArray(book?.contributors) ? book.contributors : [];
            const authorList = authors
                .map((author) => normalizeText(author?.name || author?.displayName || ''))
                .filter(Boolean);
            if (authorList.length === 0) {
                authorList.push(rawAuthor);
            }
            const locations = Array.isArray(book?.availability?.locations) ? book.availability.locations : [];
            const keywords = Array.isArray(book?.keywords) ? book.keywords.map((item) => normalizeText(item, '')).filter(Boolean) : [];
            const relatedMaterials = Array.isArray(book?.relatedMaterials) ? book.relatedMaterials : [];
            const similarMaterials = Array.isArray(book?.similarMaterials) ? book.similarMaterials : [];
            const viewerAuthenticated = book?.viewer?.authenticated === true;
            const activeLoan = book?.viewer?.activeLoan || null;
            const dueDate = activeLoan?.dueAt
                ? new Intl.DateTimeFormat(BOOK_LANG === 'kk' ? 'kk-KZ' : (BOOK_LANG === 'en' ? 'en-GB' : 'ru-RU'), {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                }).format(new Date(activeLoan.dueAt))
                : '';
            const classification = Array.isArray(book?.classification) ? book.classification.filter((item) => normalizeText(item?.label)) : [];
            const udcRaw = normalizeText(book?.udc?.raw, '');
            const udcDescription = normalizeText(book?.udc?.description, '');
            const udcText = normalizeText(book?.udc?.display, '') || udcDescription || udcRaw;
            const isAvailable = available > 0;
            const editionText = editionStatement || BOOK_I18N.editionMissing;
            const primaryLocation = locations.length ? formatLocationLabel(locations[0]) : BOOK_I18N.locationsUnavailable;
            const accessSummaryText = total > 0 ? (isAvailable ? BOOK_I18N.physicalAvailable : BOOK_I18N.physicalUnavailable) : BOOK_I18N.noCopies;
            const descriptionText = rawDescription;
            const descriptionParagraphs = descriptionText ? splitIntoParagraphs(descriptionText) : [];
            // ГОСТ-shaped reference built from the real MARC values. Parts the
            // source left empty are dropped rather than printed as dashes, and
            // the publisher — previously missing entirely — is included.
            const citePart = (value) => {
                const text = String(value ?? '').trim();
                return (text === '' || text === '—') ? '' : text;
            };
            // "Author. Title. — Publisher, Year. — ISBN x." — matches
            // App\Services\BibliographyFormatter::formatBookEntry(), so a cited
            // card and an exported reading list read identically. Built from the
            // *Raw source values, not the raw* display strings above: those
            // already fell back to "author not specified"/"year missing"
            // labels, which must never appear inside a reference.
            const citeText = (() => {
                const segments = [];
                if (citePart(book?.primaryAuthorRaw)) {
                    segments.push(citePart(book?.primaryAuthorRaw));
                }
                segments.push(citePart(book?.title?.display || book?.title?.raw) || BOOK_I18N.untitled);

                if (citePart(book?.editionStatement)) {
                    segments.push(citePart(book?.editionStatement));
                }
                const publisherStatement = [citePart(book?.publicationPlace), citePart(book?.publisherRaw)]
                    .filter(Boolean)
                    .join(': ');
                const imprint = [publisherStatement, citePart(book?.publicationYear)].filter(Boolean).join(', ');
                if (imprint) {
                    segments.push(`— ${imprint}`);
                }
                if (citePart(book?.isbn?.raw)) {
                    segments.push(`— ISBN ${citePart(book?.isbn?.raw)}`);
                }
                if (citePart(book?.issn?.raw)) {
                    segments.push(`— ISSN ${citePart(book?.issn?.raw)}`);
                }

                return `${segments.join('. ')}.`;
            })();
            const contributorText = contributors
                .map((contributor) => {
                    const name = normalizeText(contributor?.name, '');
                    if (!name) return '';
                    const role = normalizeText(contributor?.role, 'other');
                    const roleLabel = BOOK_RECOVERY_I18N?.roles?.[role] || BOOK_RECOVERY_I18N?.roles?.other || '';
                    return roleLabel ? `${name} (${roleLabel})` : name;
                })
                .filter(Boolean)
                .join(' · ');
            const metadataRows = [
                ...(originalTitle && originalTitle !== rawTitle ? [{ label: BOOK_I18N.originalTitle, value: originalTitle }] : []),
                ...(responsibility ? [{ label: BOOK_RECOVERY_I18N.responsibility, value: responsibility }] : []),
                ...(publicationPlace ? [{ label: BOOK_RECOVERY_I18N.publication_place, value: publicationPlace }] : []),
                { label: BOOK_I18N.publisher, value: rawPublisher },
                ...(editionStatement ? [{ label: BOOK_RECOVERY_I18N.edition, value: editionStatement }] : []),
                { label: BOOK_I18N.authors, value: authorList.slice(0, 3).join(' · ') },
                ...(contributorText ? [{ label: BOOK_RECOVERY_I18N.contributors, value: contributorText }] : []),
                { label: 'ISBN', value: rawIsbn },
                ...(rawIssn ? [{ label: 'ISSN', value: rawIssn }] : []),
                ...(physicalDescription ? [{ label: BOOK_RECOVERY_I18N.physical_description, value: physicalDescription }] : []),
                ...(seriesDescription ? [{ label: BOOK_RECOVERY_I18N.series, value: seriesDescription }] : []),
                ...(partDescription ? [{ label: BOOK_RECOVERY_I18N.part, value: partDescription }] : []),
                ...(udcText ? [{ label: BOOK_UDC_LABEL, value: udcText }] : []),
                ...(bbkCode ? [{ label: BOOK_RECOVERY_I18N.bbk, value: bbkCode }] : []),
                ...(localClassification ? [{ label: BOOK_RECOVERY_I18N.local_classification, value: localClassification }] : []),
                ...(materialDesignation ? [{ label: BOOK_RECOVERY_I18N.material_designation, value: materialDesignation }] : []),
                ...(controlNumber ? [{ label: BOOK_RECOVERY_I18N.control_number, value: controlNumber }] : []),
                ...(countryCode ? [{ label: BOOK_RECOVERY_I18N.country_code, value: countryCode }] : []),
                ...(ksuLiteratureType ? [{ label: BOOK_RECOVERY_I18N.ksu_literature_type, value: ksuLiteratureType }] : []),
                ...(faculty ? [{ label: BOOK_RECOVERY_I18N.faculty, value: faculty }] : []),
                ...(department ? [{ label: BOOK_RECOVERY_I18N.department, value: department }] : []),
                ...(disciplines ? [{ label: BOOK_RECOVERY_I18N.disciplines, value: disciplines }] : []),
                ...(specialty ? [{ label: BOOK_RECOVERY_I18N.specialty, value: specialty }] : []),
                ...(recordCreatedOn ? [{ label: BOOK_RECOVERY_I18N.record_created_on, value: recordCreatedOn }] : []),
                { label: BOOK_I18N.publicationLanguage, value: rawLanguage },
                { label: BOOK_I18N.resourceType, value: formatResourceType(book?.resourceType) },
                // 090h is empty in the imported MARC source for all but a
                // handful of records, so an always-on row would print a dash
                // on nearly every card. Show it only when it carries a value.
                ...(normalizeText(book?.authorMark, '') ? [{ label: BOOK_I18N.authorMark, value: normalizeText(book?.authorMark, '') }] : []),
                { label: BOOK_I18N.totalCopies, value: formatCopyCount(total) },
                { label: BOOK_I18N.availableNowLabel, value: formatInteger(available) },
                { label: BOOK_I18N.issuedCopies, value: formatInteger(issued) },
            ];
            const metadataRowsHtml = metadataRows
                .map((row) => `<div class="meta-line"><span>${escapeHtml(row.label)}</span><span>${escapeHtml(row.value)}</span></div>`)
                .join('');
            const authorChipsHtml = authorList.length > 1
                ? `<div class="flex flex-wrap gap-2 mt-2">${authorList.slice(0, 4).map((author) => `<span class="px-2 py-1 rounded-full text-xs bg-surface-container-high text-on-surface">${escapeHtml(author)}</span>`).join('')}</div>`
                : '';
            const subjectHtml = classification.length
                ? classification.slice(0, 4).map((item) => `<span class="bg-surface-container-high px-4 py-2 rounded-full text-xs font-medium">${escapeHtml(item.label)}</span>`).join('')
                : '';
            const keywordHtml = keywords.length
                ? `<div class="mt-4"><p class="mb-2 text-xs font-bold uppercase tracking-widest text-on-surface-variant">${escapeHtml(BOOK_I18N.keywords)}</p><div class="flex flex-wrap gap-2">${keywords.slice(0, 12).map((keyword) => `<span class="rounded-full border border-outline-variant/30 px-3 py-1 text-xs">${escapeHtml(keyword)}</span>`).join('')}</div></div>`
                : '';
            const relatedHtml = relatedMaterials.length
                ? `<div class="mt-12 pt-8 border-t border-surface-container-high/70">
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-secondary">${escapeHtml(BOOK_I18N.relatedMaterials)}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">${relatedMaterials.map((related) => {
                        const relatedIdentifier = normalizeText(related?.isbn, '') || normalizeText(related?.id, '');
                        const relatedMeta = [related?.author, related?.publicationYear].filter(Boolean).join(' · ');
                        return `<a class="rounded-lg border border-outline-variant/20 bg-surface-container-low/40 p-4 hover:border-secondary" href="${withLang(`/book/${encodeURIComponent(relatedIdentifier)}`)}"><strong class="block text-primary">${escapeHtml(related?.title || BOOK_I18N.untitled)}</strong>${relatedMeta ? `<span class="mt-1 block text-xs text-on-surface-variant">${escapeHtml(relatedMeta)}</span>` : ''}</a>`;
                    }).join('')}</div>
                </div>`
                : '';
            const similarHtml = similarMaterials.length
                ? `<div class="mt-12 pt-8 border-t border-surface-container-high/70" data-udc-recommendations>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-secondary">${escapeHtml(BOOK_I18N.similarByUdc)}</h3>
                    <div class="grid gap-3 sm:grid-cols-2">${similarMaterials.map((related) => {
                        const relatedIdentifier = normalizeText(related?.isbn, '') || normalizeText(related?.id, '');
                        const relatedMeta = [related?.author, related?.publicationYear].filter(Boolean).join(' · ');
                        return `<a class="rounded-lg border border-outline-variant/20 bg-surface-container-low/40 p-4 hover:border-secondary" href="${withLang(`/book/${encodeURIComponent(relatedIdentifier)}`)}"><strong class="block text-primary">${escapeHtml(related?.title || BOOK_I18N.untitled)}</strong>${relatedMeta ? `<span class="mt-1 block text-xs text-on-surface-variant">${escapeHtml(relatedMeta)}</span>` : ''}</a>`;
                    }).join('')}</div>
                </div>`
                : '';
            const tableRows = locations.length
                ? locations.map((location) => {
                    const pointName = location?.servicePoint?.name || '—';
                    const publicPointName = formatLocationLabel(location);
                    const address = location?.address ? ` · ${location.address}` : '';
                    const sigla = location?.storageSigla
                        ? `${pointName} (${location.storageSigla})`
                        : pointName;

                    return `
                    <tr>
                        <td>${escapeHtml(publicPointName)}</td>
                        ${viewerAuthenticated ? `<td data-exact-location>${escapeHtml(`${location?.campus?.name || '—'}${address}`)}</td>` : ''}
                        ${viewerAuthenticated ? `<td data-exact-location>${escapeHtml(pointName)}</td>` : ''}
                        ${viewerAuthenticated ? `<td data-exact-location>${escapeHtml(sigla)}</td>` : ''}
                        ${viewerAuthenticated ? `<td data-exact-location>${escapeHtml(location?.shelf || '—')}</td>` : ''}
                        <td>${formatCopyCount(location?.copies?.total || 0)}</td>
                        <td class="${Number(location?.copies?.available || 0) > 0 ? 'avail-count' : 'zero-count'}">${formatInteger(location?.copies?.available || 0)}</td>
                        <td>${formatInteger(location?.copies?.issued || 0)}</td>
                    </tr>
                `;
                }).join('')
                : `<tr><td colspan="${viewerAuthenticated ? '8' : '4'}">${escapeHtml(BOOK_I18N.locationsUnavailable)}</td></tr>`;
            const activeLoanHtml = activeLoan && dueDate
                ? `<div class="mb-8 rounded-lg border border-secondary/30 bg-secondary/10 px-5 py-4" data-reader-due-date>
                    <p class="font-bold text-primary">${escapeHtml(BOOK_I18N.activeLoan)}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">${escapeHtml(BOOK_I18N.dueDate)}: <strong>${escapeHtml(dueDate)}</strong></p>
                </div>`
                : '';
            const coverTones = ['catalog-card-book--navy', 'catalog-card-book--wine', 'catalog-card-book--forest', 'catalog-card-book--wood', 'catalog-card-book--plum'];
            const coverSeed = `${rawIsbn || rawTitle || rawAuthor}`;
            const coverToneIndex = coverSeed
                ? Array.from(coverSeed).reduce((sum, char) => sum + char.charCodeAt(0), 0) % coverTones.length
                : 0;
            const coverTone = coverTones[coverToneIndex];
            const coverUrl = normalizeText(book?.coverPath || book?.coverUrl || book?.cover?.medium || book?.cover?.small || '', '');
            const coverUrlStyle = coverUrl ? encodeURI(coverUrl).replace(/'/g, '%27') : '';
            const coverDescription = descriptionText;

            document.title = `${rawTitle} — ${BOOK_TITLE_SUFFIX}`;

            content.innerHTML = `
                <section id="book-detail-page" class="w-full py-8 md:py-14 min-h-screen">
                    <div class="mb-10">
                        <a class="flex items-center gap-2 text-secondary font-medium group" href="${CATALOG_URL}">
                            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform" aria-hidden="true">arrow_back</span>
                            <span class="text-sm font-label tracking-wide">${BOOK_I18N.backToCatalog}</span>
                        </a>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 xl:gap-12 items-start">
                        <div class="lg:col-span-4 xl:col-span-3">
                            <div class="bg-surface-container-low p-4 rounded-xl">
                                <div data-detail-cover class="catalog-card-media">
                                    <div class="catalog-card-book ${coverTone} ${coverUrl !== '' ? 'has-art' : ''}">
                                        <div class="catalog-card-book__stack">
                                            <div class="catalog-card-book__pages" aria-hidden="true">
                                                <div class="catalog-card-book__page-content">
                                                    <div>
                                                        <div class="catalog-card-book__page-label">${escapeHtml(rawPublisher.substring(0, 28) || BOOK_I18N.coverTop)}</div>
                                                        ${coverDescription ? `<p class="catalog-card-book__page-text">${escapeHtml(coverDescription)}</p>` : ''}
                                                    </div>
                                                    <div class="catalog-card-book__page-meta">
                                                        <div class="catalog-card-book__page-row"><span>ISBN</span><strong>${escapeHtml(rawIsbn)}</strong></div>
                                                        <div class="catalog-card-book__page-row"><span>UDC</span><strong>${escapeHtml(udcText)}</strong></div>
                                                        <div class="catalog-card-book__page-row"><span>${BOOK_I18N.language}</span><strong>${escapeHtml(rawLanguage)}</strong></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="catalog-card-book__cover">
                                                ${coverUrlStyle !== '' ? `<div class="catalog-card-book__cover-art" style="background-image: url('${coverUrlStyle}');"></div>` : ''}
                                                <div class="catalog-card-book__cover-shell">
                                                    <div>
                                                        <span class="catalog-card-book__eyebrow">${escapeHtml(rawPublisher.substring(0, 28) || BOOK_I18N.coverTop)}</span>
                                                        <h2 class="catalog-card-book__title">${escapeHtml(rawTitle)}</h2>
                                                        <p class="catalog-card-book__author">${escapeHtml(rawAuthor)}</p>
                                                    </div>
                                                    <div class="catalog-card-book__meta">
                                                        <span>${escapeHtml(rawYear)}</span>
                                                        <span>${escapeHtml(rawLanguage)}</span>
                                                        <span>${escapeHtml(rawIsbn)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="lg:col-span-8 xl:col-span-9">
                            <div class="w-full book-detail-columns">
                                <section class="book-intro-panel rounded-2xl border border-outline-variant/20 bg-surface-container-low/55 p-6 md:p-8 shadow-[0_10px_24px_rgba(15,23,42,0.04)]">
                                    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,340px)] xl:items-start">
                                        <div class="space-y-6">
                                            <div>
                                                <h1 class="text-5xl md:text-6xl text-primary font-headline font-bold leading-tight italic" style="margin-bottom:35px;">${escapeHtml(rawTitle)}</h1>
                                                ${rawSubtitle ? `<p class="text-lg leading-relaxed text-on-surface-variant max-w-3xl">${escapeHtml(rawSubtitle)}</p>` : ''}
                                            </div>

                                            <div id="detail-bibliographic-grid" class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                                                <div>
                                                    <p class="text-xs text-on-surface-variant font-label uppercase tracking-widest mb-1">${BOOK_I18N.author}</p>
                                                    <p class="text-lg text-primary font-semibold">${escapeHtml(authorList[0])}</p>
                                                    ${authorChipsHtml}
                                                </div>
                                                <div>
                                                    <p class="text-xs text-on-surface-variant font-label uppercase tracking-widest mb-1">${BOOK_I18N.publicationYear}</p>
                                                    <p class="text-lg text-primary font-semibold">${escapeHtml(rawYear)}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-on-surface-variant font-label uppercase tracking-widest mb-1">${BOOK_I18N.editionLabel}</p>
                                                    <p class="text-lg text-primary font-semibold">${escapeHtml(editionText)}</p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-on-surface-variant font-label uppercase tracking-widest mb-1">ISBN</p>
                                                    <p class="text-lg text-primary font-semibold">${escapeHtml(rawIsbn)}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <aside class="space-y-4 xl:sticky xl:top-6">
                                            <div id="detail-availability-summary" class="rounded-xl border border-outline-variant/20 bg-white p-5">
                                                <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest mb-3">${BOOK_I18N.statusInStorage}</h3>
                                                <p class="text-sm leading-relaxed text-on-surface-variant mb-5">${escapeHtml(accessSummaryText)}</p>
                                                <div class="space-y-3 text-sm">
                                                    <div class="flex items-start justify-between gap-3 border-b border-outline-variant/20 pb-2">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.locationLabel}</span>
                                                        <strong class="text-right text-on-surface">${escapeHtml(primaryLocation)}</strong>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.totalCopies}</span>
                                                        <strong class="text-right text-on-surface" data-testid="book-total-copies">${escapeHtml(formatCopyCount(total))}</strong>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.availableNowLabel}</span>
                                                        <strong class="text-right ${isAvailable ? 'text-secondary' : 'text-error'}">${escapeHtml(formatInteger(available))}</strong>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.issuedCopies}</span>
                                                        <strong class="text-right text-on-surface">${escapeHtml(formatInteger(issued))}</strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-xl border border-outline-variant/20 bg-white p-5">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <span class="material-symbols-outlined text-secondary" aria-hidden="true">sell</span>
                                                    <h3 class="text-sm font-bold text-on-surface">${BOOK_I18N.characteristics}</h3>
                                                </div>
                                                <div class="grid gap-3 text-sm">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.publicationYear}</span>
                                                        <strong class="text-right text-on-surface">${escapeHtml(rawYear)}</strong>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.language}</span>
                                                        <strong class="text-right text-on-surface">${escapeHtml(rawLanguage)}</strong>
                                                    </div>
                                                    ${udcText ? `<div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_UDC_LABEL}</span>
                                                        <strong class="text-right text-on-surface">${escapeHtml(udcText)}</strong>
                                                    </div>` : ''}
                                                    <div class="flex items-start justify-between gap-3">
                                                        <span class="text-on-surface-variant">${BOOK_I18N.availableNowLabel}</span>
                                                        <strong class="text-right ${isAvailable ? 'text-secondary' : 'text-error'}">${escapeHtml(formatInteger(available))}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </aside>
                                    </div>

                                    <div id="detail-actions" class="flex flex-wrap gap-4">
                                        <div id="digital-materials-slot" class="flex flex-wrap gap-4"></div>
                                        <button class="border border-outline-variant text-secondary px-8 py-4 rounded-lg flex items-center gap-3 hover:bg-surface-container-low transition-all" id="book-shortlist-btn" onclick="toggleBookShortlist()" type="button">
                                            <span class="material-symbols-outlined" aria-hidden="true">bookmark_add</span>
                                            <span class="font-bold tracking-tight">${BOOK_I18N.shortlistAdd}</span>
                                        </button>
                                        <button class="border border-outline-variant text-on-surface px-8 py-4 rounded-lg flex items-center gap-3 hover:bg-surface-container-low transition-all" onclick='copyCitation(${JSON.stringify(citeText)})' type="button">
                                            <span class="material-symbols-outlined" aria-hidden="true">format_quote</span>
                                            <span class="font-bold tracking-tight">${BOOK_I18N.cite}</span>
                                        </button>
                                        @if($canReserveRecord)
                                        <label class="min-w-56"><span class="mb-1 block text-xs font-bold uppercase tracking-wide text-on-surface-variant">${BOOK_I18N.pickup}</span><select class="w-full rounded-lg border border-outline-variant bg-white px-3 py-3" name="pickup_branch_id" form="member-reserve-form">@foreach($pickupBranches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                                        <button class="border border-outline-variant text-on-surface px-8 py-4 rounded-lg flex items-center gap-3 hover:bg-surface-container-low transition-all" form="member-reserve-form" type="submit" title="{{ __('librarian.member.reserve.hint') }}" ${!isAvailable && !@json($queueEnabled) ? 'disabled aria-disabled="true"' : ''}>
                                            <span class="material-symbols-outlined" aria-hidden="true">event_available</span>
                                            <span class="font-bold tracking-tight">${isAvailable ? BOOK_I18N.reserve : (@json($queueEnabled) ? BOOK_I18N.joinQueue : BOOK_I18N.unavailableNow)}</span>
                                        </button>
                                        @elseif($showGuestReserveLogin)
                                        <a class="border border-outline-variant text-on-surface px-8 py-4 rounded-lg flex items-center gap-3 hover:bg-surface-container-low transition-all" href="{{ $loginHref }}">
                                            <span class="material-symbols-outlined" aria-hidden="true">login</span>
                                            <span class="font-bold tracking-tight">${BOOK_I18N.signInToReserve}</span>
                                        </a>
                                        @endif
                                    </div>
                                </section>

                                ${activeLoanHtml}

                                <div id="detail-metadata-panel" class="grid grid-cols-1 xl:grid-cols-5 gap-6 rounded-xl border border-outline-variant/20 bg-surface-container-low/40 p-5 md:p-6">
                                    <div class="xl:col-span-3">
                                        <h2 class="text-2xl text-primary font-headline font-bold mb-4">${BOOK_I18N.metadata}</h2>
                                        <div class="meta-list">${metadataRowsHtml}</div>
                                    </div>
                                    <div class="xl:col-span-2 rounded-lg border border-outline-variant/20 bg-white p-5">
                                        <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest mb-3">${BOOK_I18N.languageMedia}</h3>
                                        <p class="text-sm leading-relaxed text-on-surface-variant">
                                            ${BOOK_I18N.language}: ${escapeHtml(rawLanguage)}<br/>
                                            ${BOOK_I18N.accessAndFormat}: ${escapeHtml(accessSummaryText)}<br/>
                                            ${udcText ? `UDC: ${escapeHtml(udcText)}` : ''}
                                        </p>
                                    </div>
                                </div>

                                ${descriptionParagraphs.length ? `<div id="detail-abstract" class="space-y-6">
                                    <h2 class="text-2xl text-primary font-headline font-bold">${BOOK_I18N.abstract}</h2>
                                    <div class="text-lg leading-relaxed text-on-surface-variant space-y-6 max-w-3xl">
                                        ${descriptionParagraphs.map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`).join('')}
                                    </div>
                                </div>` : ''}

                                {{-- ДИР 4.4 — print and electronic holdings are
                                     two different things a reader acts on, so they
                                     get one block each instead of a merged blob.
                                     The digital card is filled by
                                     loadDigitalMaterials() once the API answers. --}}
                                <div id="access-formats" class="mt-12 pt-12 border-t border-surface-container-high">
                                    <h2 class="text-2xl text-primary font-headline font-bold mb-6">${BOOK_I18N.accessFormats}</h2>
                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                                        <div class="rounded-2xl border border-outline-variant/20 bg-white p-5 md:p-6 shadow-[0_8px_20px_rgba(15,23,42,0.03)]">
                                            <div class="flex items-center gap-2 mb-3">
                                                <span class="material-symbols-outlined text-secondary" aria-hidden="true">menu_book</span>
                                                <h3 class="text-sm font-bold text-on-surface">${BOOK_I18N.printCopy}</h3>
                                            </div>
                                            <p class="text-sm leading-relaxed text-on-surface-variant mb-4">${escapeHtml(accessSummaryText)}</p>
                                            <div class="space-y-2 text-sm">
                                                <div class="flex items-start justify-between gap-3">
                                                    <span class="text-on-surface-variant">${BOOK_I18N.locationLabel}</span>
                                                    <strong class="text-right text-on-surface">${escapeHtml(primaryLocation)}</strong>
                                                </div>
                                                <div class="flex items-start justify-between gap-3">
                                                    <span class="text-on-surface-variant">${BOOK_I18N.availableNowLabel}</span>
                                                    <strong class="text-right ${isAvailable ? 'text-secondary' : 'text-error'}">${escapeHtml(String(available))}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="digital-format-card" class="rounded-2xl border border-outline-variant/20 bg-white p-5 md:p-6 shadow-[0_8px_20px_rgba(15,23,42,0.03)]" hidden>
                                            <div class="flex items-center gap-2 mb-3">
                                                <span class="material-symbols-outlined text-secondary" aria-hidden="true">auto_stories</span>
                                                <h3 class="text-sm font-bold text-on-surface">${BOOK_I18N.digitalVersion}</h3>
                                            </div>
                                            <div id="digital-format-body" class="space-y-3 text-sm text-on-surface-variant">
                                                <p>${BOOK_I18N.digitalNone}</p>
                                            </div>
                                        </div>

                                        <div class="xl:col-span-2">
                                            {{-- The viewer is mounted here on demand: an
                                                 iframe of /digital-viewer/{id} reuses the
                                                 PDF.js reader with its access checks and
                                                 saved reading position, instead of a second
                                                 renderer that could disagree with it. --}}
                                            <div id="inline-viewer-holder" class="mt-2" hidden>
                                                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                                                    <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest" id="inline-viewer-title"></h3>
                                                    <div class="flex items-center gap-3">
                                                        {{-- No placeholder href: the real
                                                             viewer URL is set by JS when the
                                                             frame is mounted, and a dead
                                                             "#" link is worse than none. --}}
                                                        <a id="inline-viewer-fullscreen" class="text-sm font-bold text-secondary hover:underline" target="_blank" rel="noopener">${BOOK_I18N.digitalFullscreen}</a>
                                                        <button id="inline-viewer-close" type="button" class="text-sm text-on-surface-variant hover:text-primary">${BOOK_I18N.digitalClose}</button>
                                                    </div>
                                                </div>
                                                <iframe
                                                    id="inline-viewer-frame"
                                                    title="${BOOK_I18N.digitalVersion}"
                                                    class="w-full rounded-lg border border-outline-variant/30 bg-surface-container-low"
                                                    style="height: 80vh; min-height: 520px;"
                                                    loading="lazy"
                                                ></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                ${(subjectHtml || keywordHtml) ? `<div class="mt-16 pt-16 border-t border-surface-container-high grid grid-cols-1 md:grid-cols-2 gap-12">
                                    <div>
                                        <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest mb-6">${BOOK_I18N.subjectTerms}</h3>
                                        <div class="flex flex-wrap gap-2">${subjectHtml}</div>
                                        ${keywordHtml}
                                    </div>
                                    <div>
                                        <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest mb-6">${BOOK_I18N.languageMedia}</h3>
                                        <p class="text-on-surface-variant text-sm leading-relaxed">
                                            ${BOOK_I18N.language}: ${escapeHtml(rawLanguage)}<br/>
                                            ${BOOK_I18N.accessAndFormat}: ${escapeHtml(accessSummaryText)}<br/>
                                            ${udcText ? `UDC: ${escapeHtml(udcText)}` : ''}
                                        </p>
                                    </div>
                                </div>` : ''}

                                <div class="mt-12 pt-8 border-t border-surface-container-high/70">
                                    <h3 class="text-xs text-secondary font-label font-bold uppercase tracking-widest mb-4">${BOOK_I18N.availabilityByPoint}</h3>
                                    <div class="overflow-x-auto">
                                        <table class="locations-table" id="locations-table">
                                            <thead>
                                                <tr>
                                                    <th>${BOOK_I18N.locationLabel}</th>
                                                    ${viewerAuthenticated ? `<th>${BOOK_I18N.campus}</th>` : ''}
                                                    ${viewerAuthenticated ? `<th>${BOOK_I18N.servicePoint}</th>` : ''}
                                                    ${viewerAuthenticated ? `<th>${BOOK_I18N.hallSigla}</th>` : ''}
                                                    ${viewerAuthenticated ? `<th>${BOOK_I18N.exactShelf}</th>` : ''}
                                                    <th>${BOOK_I18N.totalCopies}</th>
                                                    <th>${BOOK_I18N.available}</th>
                                                    <th>${BOOK_I18N.issuedCopies}</th>
                                                </tr>
                                            </thead>
                                            <tbody>${tableRows}</tbody>
                                        </table>
                                    </div>
                                </div>
                                ${relatedHtml}
                                ${similarHtml}
                            </div>
                        </div>
                    </div>
                </section>
            `;

            loadDigitalMaterials(book.id);
        }

        async function loadDigitalMaterials(documentId) {
            const slot = document.getElementById('digital-materials-slot');
            if (!slot || !documentId) return;

            try {
                const resp = await fetch(`/api/v1/documents/${encodeURIComponent(documentId)}/digital-materials`);
                if (!resp.ok) return;

                const result = await resp.json();
                const materials = result?.data || [];

                // No digital copy for this record: drop the placeholder button
                // rather than leaving a disabled control the reader can't use.
                if (materials.length === 0) {
                    slot.innerHTML = '';
                    return;
                }

                // Library-held material opens in the controlled reader; material
                // hosted elsewhere can only be linked at its source. Prefer the
                // former so the primary button is a real reading action.
                const readable = materials.find((m) => m.canAccess && m.viewerUrl && !m.isExternal);
                const external = materials.find((m) => m.canAccess && m.viewerUrl && m.isExternal);
                const downloadable = materials.find((m) => m.downloadUrl);
                const blocked = materials.find((m) => !m.canAccess);
                const licenses = Array.from(new Set(materials
                    .map((material) => normalizeText(material?.licenseTerms || '', ''))
                    .filter(Boolean)));
                const actions = [];

                if (readable) {
                    actions.push(`<a href="${escapeHtml(readable.viewerUrl)}" class="btn btn-primary">${BOOK_I18N.digitalRead}</a>`);
                } else if (external) {
                    actions.push(`<a href="${escapeHtml(external.viewerUrl)}" class="btn btn-primary" rel="noopener noreferrer" target="_blank">${BOOK_I18N.digitalExternal}</a>`);
                }

                if (downloadable) {
                    actions.push(`<a href="${escapeHtml(downloadable.downloadUrl)}" class="btn btn-secondary">${BOOK_I18N.digitalDownload}</a>`);
                }

                // Only offer an access route when something is actually blocked —
                // a permanently visible "request access" button on an open record
                // reads as a dead control.
                if (blocked) {
                    // Offer sign-in only where it would actually help. On a
                    // `restricted` record the reader needs a library permission,
                    // not a session, so pointing them at the login form would
                    // send them round a loop that ends in the same refusal.
                    const signInHelps = !IS_AUTHENTICATED
                        && (blocked.accessLevel === 'authenticated' || blocked.accessLevel === 'campus');

                    actions.push(signInHelps
                        ? `<a href="${escapeHtml(LOGIN_URL)}" class="btn btn-secondary" title="${escapeHtml(blocked.accessDeniedReason || '')}">${BOOK_I18N.digitalSignIn}</a>`
                        : `<span class="inline-flex items-center rounded-lg border border-outline-variant px-4 py-2 text-sm text-on-surface-variant" role="status">${escapeHtml(blocked.accessDeniedReason || BOOK_I18N.digitalRestricted)}</span>`);
                }

                // Buttons only. The access chip and the licence paragraph used to
                // live here too, inside a nested right-aligned grid dropped into
                // the flex row of #detail-actions — which is what made the status
                // pill float free of the button and pushed the shortlist/cite
                // controls onto their own line. Both facts now belong to the
                // "Форматы доступа" card, which states them once and in context.
                slot.innerHTML = actions.join('');

                renderDigitalFormatCard(materials, readable, external, downloadable, blocked, licenses);
            } catch (_) {
                // silent — digital materials are supplementary
            }
        }

        /**
         * Fills the "Электронная версия" card in the Access formats section and
         * wires the inline viewer. Reading happens on this page; downloading only
         * appears where allow_download permits it.
         */
        function renderDigitalFormatCard(materials, readable, external, downloadable, blocked, licenses) {
            const card = document.getElementById('digital-format-card');
            const body = document.getElementById('digital-format-body');
            if (!body || !card) return;

            if (!materials || materials.length === 0) {
                return;
            }

            card.hidden = false;

            const item = readable || external || blocked || materials[0];
            const rows = [];

            rows.push(`<p class="text-sm text-on-surface mb-3">
                <strong>${escapeHtml(item.title || '')}</strong>
                <span class="text-on-surface-variant"> · ${escapeHtml(String(item.fileType || '').toUpperCase())} · ${escapeHtml(item.fileSize || '')}</span>
            </p>`);

            if (readable) {
                // Access level sits on the same flex row as the buttons and is
                // centred against them (items-center), so it reads as a label of
                // this control rather than a chip floating beside it. Only the
                // *level* is stated — that access is open is already evident from
                // the button being there at all.
                const levelLabel = readable.accessLevel === 'public'
                    ? BOOK_I18N.digitalLevelPublic
                    : (readable.accessLevel === 'campus'
                        ? BOOK_I18N.digitalCampus
                        : (readable.accessLevel === 'restricted'
                            ? BOOK_I18N.digitalRestricted
                            : BOOK_I18N.digitalLevelReaders));

                rows.push(`<div class="flex flex-wrap items-center gap-3 mb-3">
                    <button type="button" id="digital-read-inline"
                        data-viewer="${escapeHtml(readable.viewerUrl)}"
                        data-title="${escapeHtml(readable.title || '')}"
                        class="bg-gradient-to-r from-primary to-primary-container text-on-primary px-5 py-2.5 rounded-lg flex items-center gap-2 hover:opacity-95 transition-all font-bold text-sm">
                        <span class="material-symbols-outlined text-[19px]" aria-hidden="true">auto_stories</span>${escapeHtml(BOOK_I18N.digitalRead)}
                    </button>
                    ${downloadable ? `<a href="${escapeHtml(downloadable.downloadUrl)}"
                        class="border border-outline-variant text-on-surface px-5 py-2.5 rounded-lg flex items-center gap-2 hover:bg-surface-container-low transition-all font-bold text-sm">
                        <span class="material-symbols-outlined text-[19px]" aria-hidden="true">download</span>${escapeHtml(BOOK_I18N.digitalDownload)}
                    </a>` : ''}
                    <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-3 py-1 text-xs font-bold text-on-surface-variant">
                        <span class="material-symbols-outlined text-[15px]" aria-hidden="true">${readable.accessLevel === 'public' ? 'public' : 'badge'}</span>
                        ${escapeHtml(levelLabel)}
                    </span>
                </div>`);
                // allow_download=false is a licence decision, so say so rather
                // than leaving the reader hunting for a missing button.
                if (!downloadable) {
                    rows.push(`<p class="text-xs text-on-surface-variant mb-2">
                        <span class="material-symbols-outlined align-middle text-[15px]" aria-hidden="true">lock</span>
                        ${escapeHtml(BOOK_I18N.digitalNoDownload)}
                    </p>`);
                }
            } else if (external) {
                rows.push(`<a href="${escapeHtml(external.viewerUrl)}" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-sm font-bold text-secondary hover:underline mb-3">
                    <span class="material-symbols-outlined text-[19px]" aria-hidden="true">open_in_new</span>${escapeHtml(BOOK_I18N.digitalExternal)}
                </a>`);
            } else if (blocked) {
                const signInHelps = !IS_AUTHENTICATED
                    && (blocked.accessLevel === 'authenticated' || blocked.accessLevel === 'campus');

                rows.push(`<div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 mb-3">
                    <p class="flex items-center gap-2 text-sm font-bold text-amber-900">
                        <span class="material-symbols-outlined text-[19px]" aria-hidden="true">lock</span>
                        ${escapeHtml(blocked.accessLevel === 'restricted' ? BOOK_I18N.digitalRestricted : BOOK_I18N.digitalAuthenticated)}
                    </p>
                    <p class="mt-1 text-xs text-amber-900">${escapeHtml(blocked.accessDeniedReason || '')}</p>
                    ${signInHelps ? `<a href="${escapeHtml(LOGIN_URL)}" class="mt-2 inline-flex items-center gap-1 text-sm font-bold text-amber-900 underline">
                        <span class="material-symbols-outlined text-[17px]" aria-hidden="true">login</span>${escapeHtml(BOOK_I18N.digitalSignIn)}
                    </a>` : ''}
                </div>`);
            }

            if (licenses && licenses.length > 0) {
                rows.push(`<p class="text-xs text-on-surface-variant">
                    <strong>${escapeHtml(BOOK_I18N.licenseTerms)}:</strong> ${licenses.map(escapeHtml).join(' · ')}
                </p>`);
            }

            body.innerHTML = rows.join('');

            const readButton = document.getElementById('digital-read-inline');
            if (readButton) {
                readButton.addEventListener('click', function () {
                    mountInlineViewer(readButton.dataset.viewer, readButton.dataset.title);
                });
            }
        }

        function mountInlineViewer(viewerUrl, title) {
            const holder = document.getElementById('inline-viewer-holder');
            const frame = document.getElementById('inline-viewer-frame');
            const heading = document.getElementById('inline-viewer-title');
            const fullscreen = document.getElementById('inline-viewer-fullscreen');
            const close = document.getElementById('inline-viewer-close');
            if (!holder || !frame) return;

            // `embedded=1` lets the viewer page drop its own chrome when it is
            // rendered inside this card.
            frame.src = viewerUrl + (viewerUrl.includes('?') ? '&' : '?') + 'embedded=1';
            if (heading) heading.textContent = title || '';
            if (fullscreen) fullscreen.href = viewerUrl;
            holder.hidden = false;
            holder.scrollIntoView({ behavior: 'smooth', block: 'start' });

            if (close && !close.dataset.bound) {
                close.dataset.bound = '1';
                close.addEventListener('click', function () {
                    holder.hidden = true;
                    frame.src = 'about:blank';
                });
            }
        }

        @if(session('library.user'))
        document.getElementById('shared-logout-btn')?.addEventListener('click', async () => {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                await fetch('/api/v1/logout', {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
            } catch (_) {}
            localStorage.removeItem('library.auth.user');
            window.location.href = withLang('/login');
        });
        @endif

        // --- Shortlist integration ---
        const SHORTLIST_API = '/api/v1/shortlist';
        let bookShortlisted = false;
        let currentBookData = null;

        async function checkBookShortlist(identifier) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const res = await fetch(`${SHORTLIST_API}/check`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ identifiers: [identifier] }),
                });
                if (res.ok) {
                    const json = await res.json();
                    bookShortlisted = !!(json.data && json.data[identifier]);
                    updateShortlistButton();
                }
            } catch (e) { /* silent */ }
        }

        function updateShortlistButton() {
            const btn = document.getElementById('book-shortlist-btn');
            if (!btn) return;
            if (bookShortlisted) {
                btn.innerHTML = BOOK_I18N.shortlistAdded;
                btn.style.background = 'rgba(20,105,109,.08)';
                btn.style.borderColor = 'var(--cyan)';
                btn.style.color = 'var(--cyan)';
            } else {
                btn.innerHTML = BOOK_I18N.shortlistAdd;
                btn.style.background = '';
                btn.style.borderColor = 'var(--cyan)';
                btn.style.color = 'var(--cyan)';
            }
        }

        async function toggleBookShortlist() {
            if (!currentBookData) return;
            const identifier = currentBookData.identifier;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (bookShortlisted) {
                try {
                    const res = await fetch(`${SHORTLIST_API}/${encodeURIComponent(identifier)}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        bookShortlisted = false;
                        updateShortlistButton();
                    }
                } catch (e) { console.error(e); }
            } else {
                try {
                    const res = await fetch(SHORTLIST_API, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(currentBookData),
                    });
                    if (res.ok || res.status === 201 || res.status === 409) {
                        bookShortlisted = true;
                        updateShortlistButton();
                    }
                } catch (e) { console.error(e); }
            }
        }

        // Patch renderBook to capture data and check shortlist
        const _origRenderBook = renderBook;
        renderBook = function(book) {
            _origRenderBook(book);
            const identifier = (book?.isbn?.raw || book?.id || isbn);
            currentBookData = {
                identifier: identifier,
                title: normalizeText(book?.title?.display || book?.title?.raw, BOOK_I18N.untitled),
                author: normalizeText(book?.primaryAuthor),
                publisher: normalizeText(book?.publisher?.name),
                year: normalizeText(book?.publicationYear),
                language: normalizeText(book?.language?.raw),
                isbn: normalizeText(book?.isbn?.raw),
                available: book?.copies?.available || 0,
                total: book?.copies?.total || 0,
            };
            checkBookShortlist(identifier);
        };

        loadBook();

    </script>
</body>
</html>
