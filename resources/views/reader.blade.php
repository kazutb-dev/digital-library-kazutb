@php
    $lang = app()->getLocale();
    $readerTitle = [
        'ru' => 'Читалка — Digital Library',
        'kk' => 'Оқу көрінісі — Digital Library',
        'en' => 'Reader view — Digital Library',
    ][$lang] ?? 'Читалка — Digital Library';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $readerTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Newsreader:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --surface: #ffffff;
            --surface-low: #f3f4f5;
            --surface-high: #eef0f2;
            --ink: #191c1d;
            --muted: #43474f;
            --blue: #001e40;
            --cyan: #14696d;
            --line: rgba(195, 198, 209, 0.55);
            --shadow: 0 12px 32px rgba(25, 28, 29, 0.04);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, #fbfcfc 0%, #f8f9fa 100%);
            font-family: 'Manrope', system-ui, sans-serif;
            color: var(--ink);
        }

        img, a, button {
            -webkit-user-drag: none;
            user-drag: none;
        }

        .wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            padding: 16px;
            width: 100%;
            height: 100%;
        }

        .scene {
            perspective: 1400px;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .book {
            position: relative;
            width: 980px;
            max-width: 95vw;
            height: 620px;
            max-height: 82vh;
            border-radius: 10px;
        }

        .left-page {
            position: absolute;
            left: 0;
            top: 0;
            width: 50%;
            height: 100%;
            background: linear-gradient(180deg, #ffffff 0%, #f3f4f5 100%);
            border: 1px solid var(--line);
            border-right: none;
            border-radius: 10px 0 0 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            z-index: 1;
        }

        .left-page::before,
        .sheet-face::before {
            display: none;
        }

        .left-content,
        .page-content {
            position: relative;
            z-index: 2;
            padding: 30px 24px 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            user-select: text;
        }

        .left-content h2,
        .page-content h2 {
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1.1;
            margin-bottom: 14px;
            color: var(--blue);
            font-family: 'Newsreader', Georgia, serif;
            font-weight: 600;
        }

        .left-content p,
        .page-content p {
            font-size: 15px;
            line-height: 1.75;
            color: var(--muted);
            text-align: left;
            word-break: normal;
            overflow-wrap: break-word;
        }

        .page-number {
            font-size: 11px;
            text-align: left;
            color: var(--muted);
            margin-top: 20px;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .spine {
            display: none;
        }

        .page {
            position: absolute;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            transform-origin: left center;
            transform-style: preserve-3d;
            transition: transform 0.7s cubic-bezier(.77,0,.18,1), opacity 0.7s ease;
            cursor: pointer;
        }

        .page.flipped {
            transform: rotateY(-180deg);
            opacity: 0.96;
        }

        .sheet-face {
            position: absolute;
            inset: 0;
            background: #ffffff;
            overflow: hidden;
            border: 1px solid var(--line);
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .page-front {
            border-radius: 0 10px 10px 0;
            box-shadow: var(--shadow);
        }

        .page-back {
            border-radius: 10px 0 0 10px;
            box-shadow: var(--shadow);
            transform: rotateY(180deg);
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .controls button {
            border: 1px solid var(--line);
            padding: 11px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            background: #fff;
            color: var(--ink);
            transition: background .2s ease, border-color .2s ease, color .2s ease;
        }

        .controls button:hover {
            background: var(--surface-low);
            border-color: rgba(20, 105, 109, 0.22);
        }

        .controls button:last-child {
            background: linear-gradient(135deg, var(--blue), #003366);
            color: #fff;
            border-color: transparent;
        }

        .controls button:last-child:hover {
            background: linear-gradient(135deg, #001631, #002d59);
        }

        .controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .header-info {
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 100;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .close-btn {
            min-width: 40px;
            height: 40px;
            border-radius: 6px;
            background: rgba(255,255,255,.92);
            border: 1px solid var(--line);
            color: var(--ink);
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            transition: all .2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
        }

        .close-btn:hover {
            background: var(--surface-low);
        }

        .cabinet-btn {
            padding: 10px 16px;
            border-radius: 6px;
            background: linear-gradient(135deg, var(--blue), #003366);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
            transition: all .2s ease;
            display: none;
        }

        .book-info {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(10px);
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid var(--line);
            display: none;
        }

        .book-title-header {
            color: var(--blue);
            font-size: 13px;
            font-weight: 800;
            margin: 0;
            font-family: 'Newsreader', Georgia, serif;
        }

        .hint,
        .loading {
            color: var(--muted);
            font-size: 14px;
            text-align: center;
        }

        .error {
            color: #ba1a1a;
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .book {
                height: 460px;
            }

            .left-content,
            .page-content {
                padding: 24px 18px 18px;
            }

            .left-content h2,
            .page-content h2 {
                font-size: 22px;
                margin-bottom: 12px;
            }

            .left-content p,
            .page-content p {
                font-size: 13px;
                line-height: 1.65;
            }

            .header-info {
                flex-direction: column;
                gap: 6px;
            }
        }

        @media (max-width: 520px) {
            .book {
                height: 390px;
            }

            .controls button {
                padding: 10px 14px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="header-info">
        <button class="close-btn" id="closeBtn" title="{{ ['ru' => 'Закрыть', 'kk' => 'Жабу', 'en' => 'Close'][$lang] }}">✕</button>
        <button class="cabinet-btn" id="cabinetBtn" title="{{ ['ru' => 'Личный кабинет', 'kk' => 'Жеке кабинет', 'en' => 'Account'][$lang] }}">{{ ['ru' => 'Личный кабинет', 'kk' => 'Жеке кабинет', 'en' => 'Account'][$lang] }}</button>
    </div>

    <div class="wrapper">
        <div class="scene">
            <div class="book" id="book">
                <div class="left-page">
                    <div class="left-content" id="leftStatic">
                        <div>
                            <h2>{{ ['ru' => 'Загрузка...', 'kk' => 'Жүктелуде...', 'en' => 'Loading...'][$lang] }}</h2>
                            <p>{{ ['ru' => 'Подождите, пока книга загружается...', 'kk' => 'Кітап жүктелгенше күте тұрыңыз...', 'en' => 'Please wait while the book is loading...'][$lang] }}</p>
                        </div>
                        <div class="page-number">-</div>
                    </div>
                </div>

                <div class="spine"></div>
                <div id="pagesContainer"></div>
            </div>
        </div>

        <div class="controls">
            <button id="prevBtn" type="button">{{ ['ru' => '← Назад', 'kk' => '← Артқа', 'en' => '← Back'][$lang] }}</button>
            <button id="nextBtn" type="button">{{ ['ru' => 'Вперёд →', 'kk' => 'Алға →', 'en' => 'Next →'][$lang] }}</button>
        </div>

    </div>

    <script>
        const ME_ENDPOINT = '/api/v1/me';
        const isbn = "{{ $isbn }}";
        const CANONICAL_API = `/api/v1/book-db/${encodeURIComponent(isbn)}`;
        const FALLBACK_API = `/api/v1/catalog-external?q=${encodeURIComponent(isbn)}&limit=1`;
        const READER_LANG = @json($lang);
        const READER_I18N_MAP = {
            ru: {
                untitled: 'Без названия',
                authorMissing: 'Автор не указан',
                publisher: 'Издательство',
                descriptionDefault: 'Это издание представляет собой ценный ресурс для студентов, преподавателей и исследователей.',
                descriptionMissing: 'Описание отсутствует',
                titlePage: 'Титул',
                author: 'Автор',
                year: 'Год',
                aboutBook: 'О книге',
                cover: 'Обложка',
                contents: 'Содержание',
                introduction: 'Введение',
                concepts: 'Основные концепции',
                examples: 'Практические примеры',
                methodology: 'Методология',
                outcomes: 'Итоги',
                tapHint: 'Нажимайте на страницу или используйте кнопки, чтобы перелистывать её как настоящую.',
                book: 'Книга',
                readerSuffix: 'Читалка',
                loadError: 'Ошибка загрузки книги',
                bookNotFound: 'Книга не найдена',
                genericError: 'Не удалось загрузить книгу',
                coverSmall: 'Книга',
            },
            kk: {
                untitled: 'Атауы жоқ',
                authorMissing: 'Автор көрсетілмеген',
                publisher: 'Баспа',
                descriptionDefault: 'Бұл басылым студенттер, оқытушылар және зерттеушілер үшін құнды ресурс болып табылады.',
                descriptionMissing: 'Сипаттама жоқ',
                titlePage: 'Титул',
                author: 'Автор',
                year: 'Жыл',
                aboutBook: 'Кітап туралы',
                cover: 'Мұқаба',
                contents: 'Мазмұны',
                introduction: 'Кіріспе',
                concepts: 'Негізгі ұғымдар',
                examples: 'Практикалық мысалдар',
                methodology: 'Әдіснама',
                outcomes: 'Қорытынды',
                tapHint: 'Беттерді шынайы кітап сияқты парақтау үшін бетті басыңыз немесе батырмаларды қолданыңыз.',
                book: 'Кітап',
                readerSuffix: 'Оқу көрінісі',
                loadError: 'Кітапты жүктеу қатесі',
                bookNotFound: 'Кітап табылмады',
                genericError: 'Кітапты жүктеу мүмкін болмады',
                coverSmall: 'Кітап',
            },
            en: {
                untitled: 'Untitled',
                authorMissing: 'Author not specified',
                publisher: 'Publisher',
                descriptionDefault: 'This edition is a valuable resource for students, faculty, and researchers.',
                descriptionMissing: 'Description unavailable',
                titlePage: 'Title page',
                author: 'Author',
                year: 'Year',
                aboutBook: 'About the book',
                cover: 'Cover',
                contents: 'Contents',
                introduction: 'Introduction',
                concepts: 'Core concepts',
                examples: 'Practical examples',
                methodology: 'Methodology',
                outcomes: 'Outcomes',
                tapHint: 'Tap the page or use the buttons to flip through it like a real book.',
                book: 'Book',
                readerSuffix: 'Reader',
                loadError: 'Unable to load the book',
                bookNotFound: 'Book not found',
                genericError: 'Unable to load the book',
                coverSmall: 'Book',
            },
        };
        const READER_I18N = READER_I18N_MAP[READER_LANG] || READER_I18N_MAP.ru;

        function withLang(path) {
            const url = new URL(path, window.location.origin);
            if (READER_LANG !== 'ru' && !url.searchParams.has('lang')) {
                url.searchParams.set('lang', READER_LANG);
            }
            return `${url.pathname}${url.search}`;
        }

        let pages = [];
        let current = 0;
        let pageElements = [];
        let bookData = null;

        function normalizeText(value, fallback = '') {
            if (!value || typeof value !== 'string') return fallback;
            return value.trim() || fallback;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function generatePages(book) {
            const title = normalizeText(book?.title?.display || book?.title?.raw, READER_I18N.untitled);
            const author = normalizeText(book?.primaryAuthor, READER_I18N.authorMissing);
            const publisher = normalizeText(book?.publisher?.name, READER_I18N.publisher);
            const year = normalizeText(book?.year, '2026');
            const description = normalizeText(
                book?.description || READER_I18N.descriptionDefault,
                READER_I18N.descriptionMissing
            );

            return [
                {
                    front: {
                        title: READER_I18N.titlePage,
                        text: `${escapeHtml(title)}<br><br>${READER_I18N.author}: ${escapeHtml(author)}<br>${READER_I18N.publisher}: ${escapeHtml(publisher)}<br>${READER_I18N.year}: ${escapeHtml(year)}`,
                        num: READER_I18N.cover
                    },
                    back: {
                        title: READER_I18N.aboutBook,
                        text: escapeHtml(description),
                        num: "1"
                    }
                },
                {
                    front: {
                        title: READER_I18N.contents,
                        text: `<strong>1. Введение</strong><br>2. Основные концепции<br>3. Практические примеры<br>4. Методология исследования<br>5. Результаты и выводы<br>6. Заключение`,
                        num: "2"
                    },
                    back: {
                        title: READER_I18N.introduction,
                        text: "Данная книга представляет собой систематический обзор современных подходов и методов, применяемых в различных областях науки и практики. Каждый раздел сопровождается примерами и материалами для лучшего понимания.",
                        num: "3"
                    }
                },
                {
                    front: {
                        title: READER_I18N.concepts,
                        text: "Понимание фундаментальных принципов является ключом к успешному применению знаний. В этом разделе мы рассмотрим ключевые идеи и их практическое применение в современном мире.",
                        num: "4"
                    },
                    back: {
                        title: READER_I18N.examples,
                        text: "Теория приобретает смысл только в применении. Здесь представлены реальные примеры использования изученных концепций в различных ситуациях и контекстах.",
                        num: "5"
                    }
                },
                {
                    front: {
                        title: READER_I18N.methodology,
                        text: "Методологический подход обеспечивает структурированность исследования. В этом разделе объясняется, как был проведен анализ и какие методы использовались.",
                        num: "6"
                    },
                    back: {
                        title: READER_I18N.outcomes,
                        text: "Результаты исследования показывают важность комплексного подхода. Электронный формат позволяет вам удобно навигировать и изучать материал в своем темпе.",
                        num: "7"
                    }
                }
            ];
        }

        function renderPages() {
            const container = document.getElementById('pagesContainer');
            container.innerHTML = '';
            pageElements = [];

            pages.forEach((pageData, index) => {
                const pageEl = document.createElement('div');
                pageEl.className = 'page';
                pageEl.style.zIndex = 20 - index;

                const frontEl = document.createElement('div');
                frontEl.className = 'sheet-face page-front';
                frontEl.innerHTML = `
                    <div class="page-content">
                        <div>
                            <h2>${pageData.front.title}</h2>
                            <p>${pageData.front.text}</p>
                        </div>
                        <div class="page-number">${pageData.front.num}</div>
                    </div>
                `;

                const backEl = document.createElement('div');
                backEl.className = 'sheet-face page-back';
                backEl.innerHTML = `
                    <div class="page-content">
                        <div>
                            <h2>${pageData.back.title}</h2>
                            <p>${pageData.back.text}</p>
                        </div>
                        <div class="page-number">${pageData.back.num}</div>
                    </div>
                `;

                pageEl.appendChild(frontEl);
                pageEl.appendChild(backEl);

                pageEl.addEventListener('click', () => {
                    if (pageElements.indexOf(pageEl) === current) {
                        nextPage();
                    } else if (pageElements.indexOf(pageEl) === current - 1) {
                        prevPage();
                    }
                });

                container.appendChild(pageEl);
                pageElements.push(pageEl);
            });

            updateBook();
        }

        function updateLeftPage() {
            const leftStatic = document.getElementById('leftStatic');
            const pageIndex = Math.floor(current / 2);

            if (current === 0) {
                leftStatic.innerHTML = `
                    <div>
                        <h2>${bookData?.title?.display || bookData?.title?.raw || READER_I18N.book}</h2>
                        <p>${READER_I18N.tapHint}</p>
                    </div>
                    <div class="page-number">${READER_I18N.cover}</div>
                `;
            } else if (current > 0 && pages[current - 1]) {
                const pageData = pages[current - 1];
                leftStatic.innerHTML = `
                    <div>
                        <h2>${pageData.front.title}</h2>
                        <p>${pageData.front.text.replace(/<br>/g, ' ')}</p>
                    </div>
                    <div class="page-number">${pageData.front.num}</div>
                `;
            }
        }

        function updateBook() {
            pageElements.forEach((page, index) => {
                if (index < current) {
                    page.classList.add('flipped');
                    page.style.zIndex = index + 1;
                } else {
                    page.classList.remove('flipped');
                    page.style.zIndex = 20 - index;
                }
            });

            updateLeftPage();
            updateButtons();
        }

        function updateButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            prevBtn.disabled = current === 0;
            nextBtn.disabled = current >= pageElements.length;
        }

        async function updateAuthButtons() {
            const cabinetBtn = document.getElementById('cabinetBtn');
            if (!cabinetBtn) {
                return;
            }

            let authenticated = false;
            try {
                const response = await fetch(ME_ENDPOINT, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    authenticated = payload?.authenticated === true;
                }
            } catch (_) {
                authenticated = false;
            }

            if (authenticated) {
                cabinetBtn.style.display = 'inline-block';
            } else {
                cabinetBtn.style.display = 'none';
            }
        }

        function nextPage() {
            if (current < pageElements.length) {
                current++;
                updateBook();
            }
        }

        function prevPage() {
            if (current > 0) {
                current--;
                updateBook();
            }
        }

        document.getElementById('prevBtn').addEventListener('click', prevPage);
        document.getElementById('nextBtn').addEventListener('click', nextPage);
        document.getElementById('closeBtn')?.addEventListener('click', () => {
            window.history.back();
        });
        document.getElementById('cabinetBtn')?.addEventListener('click', () => {
            window.location.href = withLang('/dashboard');
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') nextPage();
            if (e.key === 'ArrowLeft') prevPage();
        });

        document.addEventListener('selectstart', (e) => e.preventDefault());
        document.addEventListener('dragstart', (e) => e.preventDefault());
        document.addEventListener('contextmenu', (e) => e.preventDefault());
        document.addEventListener('copy', (e) => e.preventDefault());
        document.addEventListener('cut', (e) => e.preventDefault());

        async function loadBook() {
            const wrapper = document.querySelector('.wrapper');

            try {
                // Try canonical DB-backed API first
                let response = await fetch(CANONICAL_API, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    const data = await response.json();
                    // Canonical endpoint returns { data: object, success: true }
                    bookData = data?.data || null;
                    // Normalize field differences: canonical uses publicationYear, legacy uses year
                    if (bookData && bookData.publicationYear && !bookData.year) {
                        bookData.year = String(bookData.publicationYear);
                    }
                } else {
                    // Fallback to external proxy (transitional compatibility)
                    console.warn('Canonical API failed, falling back to external proxy');
                    response = await fetch(FALLBACK_API, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error(READER_I18N.loadError);
                    const data = await response.json();
                    // Legacy endpoint returns { data: array }
                    bookData = data?.data?.[0] || null;
                }

                if (!bookData) throw new Error(READER_I18N.bookNotFound);

                const title = normalizeText(bookData?.title?.display || bookData?.title?.raw, READER_I18N.untitled);
                document.title = `${title} - ${READER_I18N.readerSuffix}`;

                pages = generatePages(bookData);
                renderPages();
            } catch (error) {
                console.error(error);
                wrapper.innerHTML = `<div class="error"><strong>${escapeHtml(READER_I18N.loadError)}:</strong> ${escapeHtml(error?.message || READER_I18N.genericError)}</div>`;
            }
        }

        loadBook();
        updateAuthButtons();
    </script>
</body>
</html>
</body>
</html>
