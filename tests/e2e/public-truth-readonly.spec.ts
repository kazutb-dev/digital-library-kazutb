import {
  expect,
  test as base,
  type APIRequestContext,
  type APIResponse,
  type Page,
} from '@playwright/test';

type BlockedRequest = {
  method: string;
  url: string;
  reason: 'unsafe-method' | 'stateful-get';
};

type ReadOnlyNetwork = {
  blocked: BlockedRequest[];
};

type CatalogRecord = {
  id: string;
  title?: { display?: string; raw?: string };
  primaryAuthor?: string | null;
  language?: { code?: string; raw?: string };
  isbn?: { raw?: string };
  copies?: { available?: number; total?: number };
  availability?: {
    locations?: Array<{ copies?: { total?: number; available?: number } }>;
  };
};

type CatalogPayload = {
  data: CatalogRecord[];
  meta: {
    page: number;
    per_page: number;
    total: number;
    total_pages?: number;
    totalPages?: number;
  };
};

type Facet = { value: string; count: number };

type FacetsPayload = {
  data: {
    total: number;
    languages: Facet[];
    years: { min: number | null; max: number | null };
  };
};

type LandingPayload = {
  hero: {
    stats: Array<{ value: string; label: string; source: string }>;
  };
  catalog: { total: number };
  content: {
    repository_published: number | null;
    news_published: number | null;
    events_published: number | null;
  };
};

type RuntimeProblems = {
  consoleErrors: string[];
  pageErrors: string[];
  failedRequests: string[];
  serverErrors: string[];
};

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);
const STATEFUL_GET_PATHS = [
  // These GET endpoints record card views/outbound clicks in the database.
  /^\/resources\/[a-z0-9][a-z0-9_-]*\/?$/i,
  /^\/resources\/\d+\/open\/?$/i,
  // Reader streams/downloads can update access/audit state and are out of scope.
  /^\/api\/v1\/(?:digital-materials|documents)\/[^/]+\/(?:stream|download)\/?$/i,
];

const PUBLIC_SURFACES = [
  '/',
  '/catalog',
  '/resources',
  '/repository',
  '/news',
  '/events',
  '/contacts',
  '/rules',
] as const;

const REQUIRED_VIEWPORTS = [
  { name: 'desktop-1920', width: 1920, height: 1080 },
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'tablet-landscape-1024', width: 1024, height: 768 },
  { name: 'tablet-portrait-768', width: 768, height: 1024 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'mobile-375', width: 375, height: 812 },
] as const;

function readOnlyBlockReason(method: string, url: string): BlockedRequest['reason'] | null {
  const normalizedMethod = method.toUpperCase();
  if (!SAFE_METHODS.has(normalizedMethod)) return 'unsafe-method';

  const pathname = new URL(url).pathname;
  if (normalizedMethod === 'GET' && STATEFUL_GET_PATHS.some((pattern) => pattern.test(pathname))) {
    return 'stateful-get';
  }

  return null;
}

const test = base.extend<{ readOnlyNetwork: ReadOnlyNetwork }>({
  readOnlyNetwork: [async ({ context }, use, testInfo) => {
    const blocked: BlockedRequest[] = [];

    await context.route('**/*', async (route) => {
      const request = route.request();
      const method = request.method().toUpperCase();
      const reason = readOnlyBlockReason(method, request.url());

      if (reason !== null) {
        blocked.push({
          method,
          url: request.url(),
          reason,
        });

        // A synthetic empty response keeps catalogue shortlist hydration from
        // producing a false console/request failure while still ensuring the
        // unsafe request never reaches the audited server.
        if (reason === 'unsafe-method') {
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            headers: {
              'cache-control': 'no-store',
              'x-kazutb-read-only-guard': 'blocked',
            },
            body: JSON.stringify({ success: true, data: {}, meta: { total: 0 } }),
          });
          return;
        }

        await route.abort('blockedbyclient');
        return;
      }

      await route.continue();
    });

    await use({ blocked });

    if (blocked.length > 0) {
      testInfo.annotations.push({
        type: 'read-only-guard',
        description: `${blocked.length} unsafe browser request(s) were blocked before reaching the server.`,
      });
    }
  }, { auto: true }],
});

function positiveInteger(name: string, fallback: number): number {
  const raw = process.env[name];
  if (raw === undefined) return fallback;

  const parsed = Number(raw);
  if (!Number.isSafeInteger(parsed) || parsed <= 0) {
    throw new Error(`${name} must be a positive integer.`);
  }

  return parsed;
}

function configuredQuery(name: string, fallback: string): string {
  const value = (process.env[name] ?? fallback).trim();
  if (value.length < 2 || value.length > 120) {
    throw new Error(`${name} must contain between 2 and 120 characters.`);
  }

  return value;
}

const EXPECTED_TITLES = positiveInteger('PLAYWRIGHT_PUBLIC_EXPECTED_TITLES', 9_562);
const EXPECTED_COPIES = positiveInteger('PLAYWRIGHT_PUBLIC_EXPECTED_COPIES', 50_907);
const EXPECTED_RESOURCES = positiveInteger('PLAYWRIGHT_PUBLIC_EXPECTED_RESOURCES', 6);
const LARGE_COPY_COUNT = positiveInteger('PLAYWRIGHT_PUBLIC_LARGE_COPY_COUNT', 701);
const LARGE_COPY_ISBN = (process.env.PLAYWRIGHT_PUBLIC_LARGE_COPY_ISBN ?? '9965-17-469-5').trim();

if (!/^[0-9Xx-]{8,24}$/.test(LARGE_COPY_ISBN)) {
  throw new Error('PLAYWRIGHT_PUBLIC_LARGE_COPY_ISBN is not a safe ISBN value.');
}

const SEARCH_SCENARIOS = [
  {
    locale: 'ru',
    language: 'ru',
    visibleLanguage: 'Русский',
    query: configuredQuery('PLAYWRIGHT_PUBLIC_RU_QUERY', 'словарь'),
  },
  {
    locale: 'kk',
    language: 'kk',
    visibleLanguage: 'Қазақша',
    query: configuredQuery('PLAYWRIGHT_PUBLIC_KK_QUERY', 'Қазақ'),
  },
  {
    locale: 'en',
    language: 'en',
    visibleLanguage: 'English',
    query: configuredQuery('PLAYWRIGHT_PUBLIC_EN_QUERY', 'English'),
  },
] as const;

if (!/[ӘәҒғҚқҢңӨөҰұҮүҺһІі]/u.test(SEARCH_SCENARIOS[1].query)) {
  throw new Error('PLAYWRIGHT_PUBLIC_KK_QUERY must retain at least one Kazakh national character.');
}

async function jsonGet<T>(request: APIRequestContext, path: string): Promise<T> {
  if (!path.startsWith('/api/v1/')) {
    throw new Error(`Read-only API helper rejected an unexpected path: ${path}`);
  }

  const response: APIResponse = await request.get(path, {
    headers: { Accept: 'application/json', 'X-KazUTB-Audit-Mode': 'read-only' },
    failOnStatusCode: false,
  });
  const body = await response.text();
  expect(response.status(), `${path} returned ${response.status()}: ${body.slice(0, 500)}`).toBe(200);
  expect(response.headers()['content-type'] ?? '').toContain('application/json');

  return JSON.parse(body) as T;
}

async function gotoReadOnly(page: Page, path: string): Promise<void> {
  const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
  expect(response, `${path} did not produce a document response`).not.toBeNull();
  expect(response?.status(), `${path} returned HTTP ${response?.status()}`).toBe(200);
}

function localizedPath(pathname: string, locale = 'ru'): string {
  const params = new URLSearchParams({ lang: locale });
  return `${pathname}?${params}`;
}

function collectRuntimeProblems(page: Page, applicationOrigin: string): RuntimeProblems {
  const problems: RuntimeProblems = {
    consoleErrors: [],
    pageErrors: [],
    failedRequests: [],
    serverErrors: [],
  };

  page.on('console', (message) => {
    if (message.type() === 'error') problems.consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => problems.pageErrors.push(error.message));
  page.on('requestfailed', (request) => {
    const requestURL = new URL(request.url());
    if (requestURL.origin !== applicationOrigin) return;
    if (readOnlyBlockReason(request.method(), request.url()) !== null) return;

    problems.failedRequests.push(
      `${request.method()} ${requestURL.pathname}${requestURL.search}: ${request.failure()?.errorText ?? 'failed'}`,
    );
  });
  page.on('response', (response) => {
    const responseURL = new URL(response.url());
    if (responseURL.origin === applicationOrigin && response.status() >= 500) {
      problems.serverErrors.push(`${response.status()} ${response.request().method()} ${responseURL.pathname}${responseURL.search}`);
    }
  });

  return problems;
}

function expectCleanRuntime(problems: RuntimeProblems, label: string): void {
  expect(problems.pageErrors, `${label}: uncaught page errors`).toEqual([]);
  expect(problems.consoleErrors, `${label}: console errors`).toEqual([]);
  expect(problems.failedRequests, `${label}: unexpected same-origin request failures`).toEqual([]);
  expect(problems.serverErrors, `${label}: same-origin HTTP >= 500`).toEqual([]);
}

function normalizedDigits(value: string | null): number {
  return Number((value ?? '').replace(/[^0-9]/g, ''));
}

test.describe('public runtime and responsive acceptance (strictly read-only)', () => {
  for (const viewport of REQUIRED_VIEWPORTS) {
    test(`all public surfaces are clean at ${viewport.name}`, async ({ context, baseURL }, testInfo) => {
      testInfo.setTimeout(120_000);
      expect(baseURL, 'The read-only config must provide a baseURL.').toBeTruthy();
      const applicationOrigin = new URL(baseURL!).origin;

      for (const surface of PUBLIC_SURFACES) {
        const page = await context.newPage();
        const label = `${surface} at ${viewport.width}x${viewport.height}`;

        try {
          await page.setViewportSize(viewport);
          const runtime = collectRuntimeProblems(page, applicationOrigin);
          const path = localizedPath(surface);
          const response = await page.goto(path, { waitUntil: 'networkidle' });

          expect(response, `${label}: missing document response`).not.toBeNull();
          expect(response?.status(), `${label}: document HTTP status`).toBe(200);
          await expect(page.locator('.site-header'), `${label}: public header`).toBeVisible();
          await expect(page.locator('main#main-content'), `${label}: public main`).toBeVisible();
          await expect(page.locator('footer.university-footer'), `${label}: public footer`).toBeVisible();
          await expect(page.locator('h1'), `${label}: canonical H1`).toHaveCount(1);
          await expect(page.locator('h1'), `${label}: visible H1`).toBeVisible();
          await expect(page.locator('html'), `${label}: requested locale`).toHaveAttribute('lang', 'ru');

          const geometry = await page.evaluate(async () => {
            await document.fonts.ready;
            const root = document.documentElement;
            const overflow = root.scrollWidth - root.clientWidth;
            const offenders = overflow > 1
              ? Array.from(document.querySelectorAll<HTMLElement>('body *'))
                .map((element) => {
                  const rect = element.getBoundingClientRect();
                  return {
                    tag: element.tagName.toLowerCase(),
                    id: element.id,
                    className: typeof element.className === 'string' ? element.className.slice(0, 100) : '',
                    left: Math.round(rect.left),
                    right: Math.round(rect.right),
                  };
                })
                .filter((item) => item.left < -1 || item.right > root.clientWidth + 1)
                .slice(0, 8)
              : [];

            return {
              clientWidth: root.clientWidth,
              scrollWidth: root.scrollWidth,
              overflow,
              offenders,
            };
          });

          expect(
            geometry.overflow,
            `${label}: horizontal overflow ${JSON.stringify(geometry)}`,
          ).toBeLessThanOrEqual(1);
          expectCleanRuntime(runtime, label);
        } finally {
          await page.close();
        }
      }
    });
  }

  for (const locale of ['ru', 'kk', 'en'] as const) {
    test(`desktop public surfaces render cleanly in ${locale.toUpperCase()}`, async ({ context, baseURL }, testInfo) => {
      testInfo.setTimeout(120_000);
      expect(baseURL, 'The read-only config must provide a baseURL.').toBeTruthy();
      const applicationOrigin = new URL(baseURL!).origin;

      for (const surface of PUBLIC_SURFACES) {
        const page = await context.newPage();
        const label = `${surface} (${locale}) at 1440x900`;

        try {
          await page.setViewportSize({ width: 1440, height: 900 });
          const runtime = collectRuntimeProblems(page, applicationOrigin);
          const response = await page.goto(localizedPath(surface, locale), { waitUntil: 'networkidle' });

          expect(response, `${label}: missing document response`).not.toBeNull();
          expect(response?.status(), `${label}: document HTTP status`).toBe(200);
          await expect(page.locator('html'), `${label}: requested locale`).toHaveAttribute('lang', locale);
          await expect(page.locator('main#main-content'), `${label}: public main`).toBeVisible();
          await expect(page.locator('h1'), `${label}: canonical H1`).toHaveCount(1);
          await expect(page.locator('h1'), `${label}: visible H1`).toBeVisible();

          const visibleText = await page.locator('body').innerText();
          const leakedKeys = visibleText.match(
            /\b(?:brand|common|events|external_resources|news|repository|shell|ui)\.[a-z][a-z0-9_.-]*/giu,
          ) ?? [];
          expect(leakedKeys, `${label}: raw translation keys`).toEqual([]);
          expect(visibleText, `${label}: translation framework fallback`).not.toMatch(
            /(?:translation missing|missing translation)/iu,
          );
          expectCleanRuntime(runtime, label);
        } finally {
          await page.close();
        }
      }
    });
  }
});

test.describe('public catalogue truth (strictly read-only)', () => {
  test('landing API and homepage publish the same source-backed figures', async ({ page, request }) => {
    const landing = await jsonGet<LandingPayload>(request, '/api/v1/landing');
    const stats = new Map(landing.hero.stats.map((stat) => [stat.source, normalizedDigits(stat.value)]));

    expect(landing.catalog.total).toBe(EXPECTED_TITLES);
    expect(stats.get('catalog_titles')).toBe(EXPECTED_TITLES);
    expect(stats.get('physical_copies')).toBe(EXPECTED_COPIES);
    expect(stats.get('published_resources')).toBe(EXPECTED_RESOURCES);
    expect(landing.hero.stats.find((stat) => stat.source === 'public_catalog_availability')?.value).toBe('24/7');
    expect(landing.content).toEqual({
      repository_published: 0,
      news_published: 0,
      events_published: 0,
    });

    await gotoReadOnly(page, '/?lang=ru');
    for (const [source, expected] of [
      ['catalog_titles', EXPECTED_TITLES],
      ['physical_copies', EXPECTED_COPIES],
      ['published_resources', EXPECTED_RESOURCES],
    ] as const) {
      const value = page.locator(`[data-section="homepage-hero-stats"] [data-stat-source="${source}"] strong`);
      await expect(value, `homepage ${source}`).toBeVisible();
      expect(normalizedDigits(await value.textContent()), `homepage ${source}`).toBe(expected);
    }
    await expect(
      page.locator('[data-section="homepage-hero-stats"] [data-stat-source="public_catalog_availability"] strong'),
    ).toHaveText('24/7');
  });

  test('facets cover the complete catalogue and agree with every language filter', async ({ request }) => {
    const facets = await jsonGet<FacetsPayload>(request, '/api/v1/catalog-facets');
    const catalogue = await jsonGet<CatalogPayload>(request, '/api/v1/catalog-db?limit=1');

    expect(facets.data.total).toBe(EXPECTED_TITLES);
    expect(catalogue.meta.total).toBe(EXPECTED_TITLES);
    expect(facets.data.languages.map((facet) => facet.value).sort()).toEqual(['en', 'kk', 'other', 'ru']);
    expect(facets.data.languages.every((facet) => Number.isInteger(facet.count) && facet.count >= 0)).toBeTruthy();
    expect(facets.data.languages.reduce((sum, facet) => sum + facet.count, 0)).toBe(facets.data.total);

    if (facets.data.years.min !== null && facets.data.years.max !== null) {
      expect(facets.data.years.min).toBeGreaterThanOrEqual(1400);
      expect(facets.data.years.max).toBeLessThanOrEqual(new Date().getUTCFullYear() + 1);
      expect(facets.data.years.min).toBeLessThanOrEqual(facets.data.years.max);
    }

    for (const facet of facets.data.languages) {
      const params = new URLSearchParams({
        language: facet.value,
        limit: String(Math.max(1, Math.min(100, facet.count || 1))),
      });
      const filtered = await jsonGet<CatalogPayload>(request, `/api/v1/catalog-db?${params}`);

      expect(filtered.meta.total, `language=${facet.value}`).toBe(facet.count);
      for (const record of filtered.data) {
        expect(record.language?.code, `record ${record.id} leaked raw language ${record.language?.raw}`).toBe(facet.value);
      }
    }
  });

  test('thirty real catalogue cards pass presentation sanity without changing metadata', async ({ page }) => {
    const sampledCards: Array<{
      title: string;
      author: string;
      year: string;
      language: string;
      available: number;
      total: number;
      detailURL: string;
      text: string;
    }> = [];

    for (let pageNumber = 1; pageNumber <= 6 && sampledCards.length < 30; pageNumber += 1) {
      const params = new URLSearchParams({ lang: 'ru', page: String(pageNumber) });
      await gotoReadOnly(page, `/catalog?${params}`);

      const cards = page.locator('[data-catalog-card]');
      await expect(cards.first(), `catalogue page ${pageNumber} must expose cards`).toBeVisible();
      const pageSample = await cards.evaluateAll((nodes) => nodes.map((node) => {
        const card = node as HTMLElement;
        const control = card.querySelector<HTMLElement>('[data-catalog-shortlist-button]');
        if (!control) throw new Error('A catalogue card has no presentation contract control.');

        return {
          title: (control.dataset.shortlistTitle ?? '').trim(),
          author: (
            control.dataset.shortlistAuthor
            || card.querySelector<HTMLElement>('.catalog-card-book__author')?.innerText
            || ''
          ).trim(),
          year: (control.dataset.shortlistYear ?? '').trim(),
          language: (control.dataset.shortlistLanguage ?? '').trim(),
          available: Number(control.dataset.shortlistAvailable ?? Number.NaN),
          total: Number(control.dataset.shortlistTotal ?? Number.NaN),
          detailURL: (control.dataset.shortlistUrl ?? '').trim(),
          text: (card.innerText ?? '').replace(/\s+/g, ' ').trim(),
        };
      }));

      sampledCards.push(...pageSample);
    }

    expect(sampledCards.length, 'The public catalogue must expose at least 30 cards to sample.').toBeGreaterThanOrEqual(30);

    for (const [index, card] of sampledCards.slice(0, 30).entries()) {
      const label = `catalogue sample ${index + 1}`;
      expect(card.title, `${label}: title`).not.toMatch(/^\s*(?:—|Untitled|Без названия)?\s*$/iu);
      expect(card.author, `${label}: author or honest fallback`).not.toMatch(/^\s*(?:—)?\s*$/u);
      expect(Number(card.year), `${label}: publication year`).toBeGreaterThanOrEqual(1400);
      expect(Number(card.year), `${label}: publication year`).toBeLessThanOrEqual(new Date().getUTCFullYear() + 1);
      expect(card.language, `${label}: human language label`).not.toMatch(/^(?:ru|rus|kk|kaz|en|eng|other)$/iu);
      expect(Number.isInteger(card.total), `${label}: total copies`).toBeTruthy();
      expect(Number.isInteger(card.available), `${label}: available copies`).toBeTruthy();
      expect(card.total, `${label}: total copies`).toBeGreaterThanOrEqual(0);
      expect(card.available, `${label}: available copies`).toBeGreaterThanOrEqual(0);
      expect(card.available, `${label}: available cannot exceed total`).toBeLessThanOrEqual(card.total);
      expect(card.text, `${label}: visible title`).toContain(card.title);
      expect(card.text, `${label}: visible author`).toContain(card.author);
      expect(card.text, `${label}: visible year`).toContain(card.year);
      expect(card.text, `${label}: visible language`).toContain(card.language);
      expect(card.text, `${label}: total-copy label`).toContain('Всего:');
      expect(card.text, `${label}: availability label`).toContain('Доступно');

      const detailURL = new URL(card.detailURL, page.url());
      expect(detailURL.origin, `${label}: detail origin`).toBe(new URL(page.url()).origin);
      expect(decodeURIComponent(detailURL.pathname), `${label}: detail route`).toMatch(/^\/book\/[^/]+$/u);
    }
  });

  for (const scenario of SEARCH_SCENARIOS) {
    test(`${scenario.language.toUpperCase()} Unicode search returns matching localized records`, async ({ page, request }) => {
      const params = new URLSearchParams({
        q: scenario.query,
        language: scenario.language,
        limit: '30',
      });
      const payload = await jsonGet<CatalogPayload>(request, `/api/v1/catalog-db?${params}`);

      expect(payload.meta.total).toBeGreaterThan(0);
      expect(payload.data.length).toBeGreaterThan(0);
      expect(payload.data.every((record) => record.language?.code === scenario.language)).toBeTruthy();
      expect(JSON.stringify(payload.data).toLocaleLowerCase()).toContain(scenario.query.toLocaleLowerCase());

      const browserParams = new URLSearchParams({
        lang: scenario.locale,
        q: scenario.query,
        language: scenario.language,
      });
      await gotoReadOnly(page, `/catalog?${browserParams}`);

      await expect(page.locator('html')).toHaveAttribute('lang', scenario.locale);
      await expect(page.locator('#catalog-search-input')).toHaveValue(scenario.query);
      await expect(page.locator(`[data-lang="${scenario.language}"].is-active`)).toBeVisible();

      const cards = page.locator('[data-catalog-card]');
      expect(await cards.count()).toBeGreaterThan(0);
      for (let index = 0; index < await cards.count(); index += 1) {
        await expect(cards.nth(index), `card ${index + 1} must show a human language label`).toContainText(
          scenario.visibleLanguage,
        );
      }

      const renderedTotal = await page.locator('#catalog-results-count .font-bold').nth(1).textContent();
      expect(normalizedDigits(renderedTotal)).toBe(payload.meta.total);
    });
  }

  test('ISBN search resolves the exact edition in both API and catalogue UI', async ({ page, request }) => {
    const params = new URLSearchParams({ isbn: LARGE_COPY_ISBN, limit: '10' });
    const payload = await jsonGet<CatalogPayload>(request, `/api/v1/catalog-db?${params}`);

    expect(payload.meta.total).toBeGreaterThan(0);
    expect(payload.data.some((record) => record.isbn?.raw === LARGE_COPY_ISBN)).toBeTruthy();

    const browserParams = new URLSearchParams({ lang: 'ru', isbn: LARGE_COPY_ISBN });
    await gotoReadOnly(page, `/catalog?${browserParams}`);
    await expect(page.locator('#advanced-isbn-input')).toHaveValue(LARGE_COPY_ISBN);
    await expect(page.locator(`[data-shortlist-isbn="${LARGE_COPY_ISBN}"]`).first()).toBeVisible();
  });

  test('the 701-copy edition is aggregated once and not expanded into copy rows', async ({ request }) => {
    const payload = await jsonGet<{ success: boolean; data: CatalogRecord }>(
      request,
      `/api/v1/book-db/${encodeURIComponent(LARGE_COPY_ISBN)}`,
    );

    expect(payload.success).toBeTruthy();
    expect(payload.data.isbn?.raw).toBe(LARGE_COPY_ISBN);
    expect(payload.data.copies?.total).toBe(LARGE_COPY_COUNT);
    expect(payload.data.copies?.available).toBeLessThanOrEqual(LARGE_COPY_COUNT);

    const locations = payload.data.availability?.locations ?? [];
    expect(locations.length).toBeGreaterThan(0);
    expect(locations.length).toBeLessThan(LARGE_COPY_COUNT);
    expect(locations.reduce((sum, location) => sum + Number(location.copies?.total ?? 0), 0)).toBe(LARGE_COPY_COUNT);
  });

  test('an unknown public book identifier returns an honest 404', async ({ page }) => {
    const response = await page.goto('/book/does-not-exist-public-gate?lang=en', { waitUntil: 'domcontentloaded' });

    expect(response, 'unknown book did not produce a document response').not.toBeNull();
    expect(response?.status()).toBe(404);
    await expect(page.locator('#book-detail-page')).toHaveCount(0);
  });

  for (const scenario of [
    {
      locale: 'ru',
      cardCount: /701\s+экземпляр(?!а|ов)/iu,
      detailTotal: /Всего экземпляров\s+701/iu,
    },
    {
      locale: 'kk',
      cardCount: /701\s+дана/iu,
      detailTotal: /Барлық дана\s+701/iu,
    },
    {
      locale: 'en',
      cardCount: /701\s+copies\b/iu,
      detailTotal: /Total copies\s+701/iu,
    },
  ] as const) {
    test(`large-copy card and detail use correct ${scenario.locale.toUpperCase()} copy wording`, async ({ page }) => {
      const catalogParams = new URLSearchParams({ lang: scenario.locale, isbn: LARGE_COPY_ISBN });
      await gotoReadOnly(page, `/catalog?${catalogParams}`);
      const largeCard = page.locator('[data-catalog-card]').filter({
        has: page.locator(`[data-shortlist-isbn="${LARGE_COPY_ISBN}"]`),
      }).first();
      await expect(largeCard).toBeVisible();
      await expect(largeCard).toContainText(scenario.cardCount);

      await gotoReadOnly(page, `/book/${encodeURIComponent(LARGE_COPY_ISBN)}?lang=${scenario.locale}`);

      const detail = page.locator('#book-detail-page');
      await expect(detail).toBeVisible();
      await expect(detail).toContainText(LARGE_COPY_ISBN);
      await expect(page.locator('#detail-availability-summary')).toContainText(scenario.detailTotal);

      const locationRows = page.locator('#locations-table tbody tr');
      expect(await locationRows.count()).toBeGreaterThan(0);
      expect(await locationRows.count()).toBeLessThan(20);
    });
  }
});

test.describe('honest empty public content states (strictly read-only)', () => {
  test('repository, news, and events expose compact zero states instead of fixture content', async ({ page }) => {
    await gotoReadOnly(page, '/repository?lang=ru');
    await expect(page.locator('.public-v2__hero-note strong')).toHaveText('0');
    await expect(page.locator('.repository-canonical__card')).toHaveCount(0);
    await expect(page.locator('[data-test-id="repository-canonical-empty"]')).toContainText(
      'Научный репозиторий формируется.',
    );
    await expect(page.locator('[data-test-id="repository-canonical-empty"]')).toContainText(
      'Материалы будут публиковаться после проверки и утверждения библиотекой.',
    );
    await expect(page.locator('[data-test-id="repository-filter-submit"]')).toHaveCount(0);

    await gotoReadOnly(page, '/news?lang=ru');
    await expect(page.locator('.public-v2__hero-note strong')).toHaveText('0');
    await expect(page.locator('[data-test-id="news-canonical-article"]')).toHaveCount(0);
    await expect(page.locator('[data-test-id="news-canonical-featured"]')).toHaveCount(0);
    await expect(page.locator('[data-test-id="news-canonical-empty"]')).toContainText('Публикаций пока нет.');
    await expect(page.locator('[data-test-id="news-canonical-empty"]')).toContainText('Следите за обновлениями библиотеки.');

    await gotoReadOnly(page, '/events?lang=ru');
    await expect(page.locator('.public-v2__hero-note strong')).toHaveText('0');
    await expect(page.locator('[data-event-slot]')).toHaveCount(0);
    await expect(page.locator('[data-test-id="events-canonical-empty"]')).toContainText('Ближайших мероприятий пока нет.');
    await expect(page.locator('[data-test-id="events-canonical-empty"]')).toContainText(
      'Когда библиотека опубликует новое мероприятие, оно появится здесь.',
    );
  });
});

test.describe('public navigation interactions (strictly read-only)', () => {
  test('homepage hero and header searches lead to real catalogue results with GET only', async ({ page, baseURL }) => {
    expect(baseURL).toBeTruthy();
    const runtime = collectRuntimeProblems(page, new URL(baseURL!).origin);

    await gotoReadOnly(page, '/?lang=ru');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-section="homepage-canonical-hero"]')).toBeVisible();
    await expect(page.locator('[data-section="homepage-hero-stats"] [data-stat-source="catalog_titles"]')).toBeVisible();
    await expect(page.locator('[data-section="homepage-hero-stats"] [data-stat-source="physical_copies"]')).toBeVisible();
    await expect(page.locator('[data-section="homepage-hero-stats"] [data-stat-source="public_catalog_availability"]')).toBeVisible();

    await page.locator('#homepage-search').fill('словарь');
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/catalog' && url.searchParams.get('q') === 'словарь'),
      page.locator('[data-test-id="homepage-canonical-search"] button[type="submit"]').click(),
    ]);
    await expect(page.locator('#catalog-search-input')).toHaveValue('словарь');
    await expect(page.locator('[data-catalog-card]').first()).toBeVisible();
    await page.waitForLoadState('networkidle');

    await gotoReadOnly(page, '/?lang=ru');
    await page.waitForLoadState('networkidle');
    await page.locator('[data-global-search] > summary').click();
    await expect(page.locator('.hdr-panel--search')).toBeVisible();
    await page.locator('#site-search-input').fill('словарь');
    await expect(page.locator('[data-search-results] a').first()).toBeVisible();
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/catalog' && url.searchParams.get('q') === 'словарь'),
      page.locator('#site-search-input').press('Enter'),
    ]);
    await expect(page.locator('#catalog-search-input')).toHaveValue('словарь');
    await expect(page.locator('[data-catalog-card]').first()).toBeVisible();
    await page.waitForLoadState('networkidle');

    expectCleanRuntime(runtime, 'homepage and catalogue search interactions');
  });

  test('contacts expose real channels and route an inquiry to the guest login flow', async ({ page, baseURL }) => {
    expect(baseURL).toBeTruthy();
    const runtime = collectRuntimeProblems(page, new URL(baseURL!).origin);

    await gotoReadOnly(page, '/contacts?lang=ru');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('[data-section="contacts-canonical-support"]')).toBeVisible();
    const contactMain = page.locator('main#main-content');
    const channels = page.locator('[data-support-channel]');
    const channelEmails = page.locator('[data-support-channel] a[href^="mailto:"]');
    const channelPhones = page.locator('[data-support-channel] a[href^="tel:"]');
    await expect(channels).toHaveCount(2);
    expect(await channelEmails.evaluateAll((nodes) => nodes.map((node) => (node as HTMLAnchorElement).href).sort())).toEqual([
      'mailto:info@kaztbu.edu.kz',
      'mailto:zh.pankey@kaztbu.edu.kz',
    ]);
    expect(await channelPhones.evaluateAll((nodes) => nodes.map((node) => (node as HTMLAnchorElement).href))).toEqual(
      ['tel:+77172697060'],
    );
    const libraryChannel = channels.filter({ has: page.locator('[href="mailto:zh.pankey@kaztbu.edu.kz"]') });
    await expect(libraryChannel).toHaveCount(1);
    await expect(libraryChannel.locator('a[href^="tel:"]')).toHaveCount(0);
    await expect(page.locator('main#main-content form')).toHaveCount(0);
    await expect(contactMain).toContainText('Астана, ул. Кайыма Мухамедханова, 37A');
    await expect(contactMain).toContainText('+7 (7172) 69-70-60');
    await expect(contactMain).toContainText('info@kaztbu.edu.kz');
    await expect(contactMain).toContainText('08:30 – 17:30');
    await expect(contactMain).not.toContainText('+7 (7172) 64-58-58');
    await expect(contactMain).not.toContainText('library@kazutb.edu.kz');
    await expect(contactMain).not.toContainText('support@kazutb.edu.kz');
    await expect(page.locator('[data-room-slot]')).toHaveCount(0);
    const verifiedStaff = page.locator('[data-staff-slot]');
    await expect(verifiedStaff).toHaveCount(1);
    await expect(verifiedStaff).toHaveAttribute('data-staff-slug', 'pankey-zh');
    await expect(verifiedStaff).toContainText('Панкей Ж.');
    await expect(contactMain).not.toContainText('Корпешова Эльмира Мауткановна');
    await expect(contactMain).not.toContainText('Сайлаубек Айман Бастарбекқызы');

    const hoursSource = page.locator('[data-test-id="contacts-official-hours-source"]');
    await expect(hoursSource).toHaveAttribute('href', 'https://www.kaztbu.edu.kz/biblioteka');
    await expect(hoursSource).toHaveAttribute('target', '_blank');
    const hoursSourceRel = (await hoursSource.getAttribute('rel'))?.split(/\s+/) ?? [];
    expect(hoursSourceRel).toEqual(expect.arrayContaining(['noopener', 'noreferrer']));

    const directions = page.locator('[data-test-id="contacts-canonical-directions"]');
    await expect(directions).toHaveAttribute('target', '_blank');
    const directionsRel = (await directions.getAttribute('rel'))?.split(/\s+/) ?? [];
    expect(directionsRel).toEqual(expect.arrayContaining(['noopener', 'noreferrer']));

    const inquiry = page.locator('[data-test-id="contacts-canonical-inquiry-cta"]');
    const inquiryHref = await inquiry.getAttribute('href');
    expect(inquiryHref).not.toBeNull();
    const inquiryURL = new URL(inquiryHref!, page.url());
    expect(inquiryURL.pathname).toBe('/login');
    expect(inquiryURL.searchParams.get('redirect')).toContain('/dashboard/messages');

    await inquiry.click();
    await expect(page).toHaveURL((url) => url.pathname === '/login');
    await expect(page.locator('#login-form')).toBeVisible();
    await page.waitForLoadState('networkidle');
    expectCleanRuntime(runtime, 'contacts and guest inquiry navigation');
  });

  test('locale switcher exposes RU, KK, and EN without submitting its stateful form', async ({ page, baseURL }) => {
    expect(baseURL).toBeTruthy();
    const runtime = collectRuntimeProblems(page, new URL(baseURL!).origin);

    await gotoReadOnly(page, '/rules?lang=ru');
    await page.waitForLoadState('networkidle');
    const switcher = page.locator('details[data-locale-switcher]').first();
    await switcher.locator(':scope > summary').click();
    const localeForms = switcher.locator('form');
    await expect(localeForms).toHaveCount(3);
    expect(await localeForms.locator('input[name="locale"]').evaluateAll((nodes) =>
      nodes.map((node) => (node as HTMLInputElement).value).sort(),
    )).toEqual(['en', 'kk', 'ru']);
    expect(await localeForms.evaluateAll((nodes) => nodes.map((node) => ({
      method: (node as HTMLFormElement).method,
      path: new URL((node as HTMLFormElement).action).pathname,
    })))).toEqual([
      { method: 'post', path: '/locale' },
      { method: 'post', path: '/locale' },
      { method: 'post', path: '/locale' },
    ]);
    await expect(localeForms.filter({ has: page.locator('input[value="ru"]') }).locator('button')).toHaveAttribute(
      'aria-current',
      'true',
    );

    // Direct query navigation validates each locale's public rendering while
    // deliberately avoiding the POST /locale preference mutation.
    for (const locale of ['ru', 'kk', 'en'] as const) {
      await gotoReadOnly(page, `/rules?lang=${locale}`);
      await page.waitForLoadState('networkidle');
      await expect(page.locator('html')).toHaveAttribute('lang', locale);
      await expect(page.locator('h1')).toBeVisible();
    }

    expectCleanRuntime(runtime, 'read-only locale switcher contract');
  });
});

test.describe('published resource directory (strictly read-only)', () => {
  test('all six cards expose unique, well-formed detail and access controls', async ({ page }) => {
    await gotoReadOnly(page, '/resources?lang=ru');

    const cards = page.locator('[data-resource-card]');
    await expect(cards).toHaveCount(EXPECTED_RESOURCES);

    const slugs = await cards.evaluateAll((nodes) =>
      nodes.map((node) => (node as HTMLElement).dataset.resourceSlug ?? ''),
    );
    expect(slugs.every((slug) => /^[a-z0-9][a-z0-9_-]*$/i.test(slug))).toBeTruthy();
    expect(new Set(slugs).size).toBe(EXPECTED_RESOURCES);

    for (let index = 0; index < EXPECTED_RESOURCES; index += 1) {
      const card = cards.nth(index);
      const slug = slugs[index];

      await expect(card.locator('h3')).not.toHaveText('');
      await expect(card.locator('.external-resource-card__description')).not.toHaveText('');

      const detailHref = await card.locator('.external-resource-card__details').getAttribute('href');
      expect(detailHref).not.toBeNull();
      const detailURL = new URL(detailHref!, page.url());
      expect(detailURL.origin).toBe(new URL(page.url()).origin);
      expect(decodeURIComponent(detailURL.pathname)).toBe(`/resources/${slug}`);

      const accessLink = card.locator('a.external-resource-card__button');
      const disabledAccess = card.locator('.external-resource-card__button--disabled[aria-disabled="true"]');
      expect((await accessLink.count()) + (await disabledAccess.count()), `${slug} needs exactly one access control`).toBe(1);

      if (await accessLink.count()) {
        const accessHref = await accessLink.getAttribute('href');
        expect(accessHref).not.toBeNull();
        const accessURL = new URL(accessHref!, page.url());
        expect(['http:', 'https:']).toContain(accessURL.protocol);
        expect(accessURL.username).toBe('');
        expect(accessURL.password).toBe('');

        if ((await accessLink.getAttribute('target')) === '_blank') {
          const rel = (await accessLink.getAttribute('rel'))?.split(/\s+/) ?? [];
          expect(rel).toEqual(expect.arrayContaining(['noopener', 'noreferrer']));
        }
      }
    }

    const iprCard = cards.filter({ has: page.locator('[data-test-id="resources-sign-in-ipr-smart"]') });
    await expect(iprCard, 'IPR SMART must remain present as a login-gated card').toHaveCount(1);
    await expect(iprCard).toHaveAttribute('data-resource-authenticated', '1');
    await expect(iprCard.locator('[data-test-id="resources-link-ipr-smart"]')).toHaveCount(0);
    const iprSignIn = iprCard.locator('[data-test-id="resources-sign-in-ipr-smart"]');
    await expect(iprSignIn).toHaveText(/\S+/u);
    const iprHref = await iprSignIn.getAttribute('href');
    expect(iprHref).not.toBeNull();
    const iprURL = new URL(iprHref!, page.url());
    expect(iprURL.origin).toBe(new URL(page.url()).origin);
    expect(iprURL.pathname).toBe('/login');
    expect(iprURL.searchParams.get('redirect')).toContain('/resources/ipr-smart');
    expect(iprHref).not.toContain('/resources/1/open');

    const unavailableKaznu = page.locator('[data-resource-card][data-resource-slug="kaznu-repository"]');
    await expect(unavailableKaznu).toHaveCount(1);
    await expect(unavailableKaznu.locator('.external-resource-card__button--disabled[aria-disabled="true"]')).toBeVisible();
    await expect(unavailableKaznu.locator('a.external-resource-card__button')).toHaveCount(0);

    // Do not follow detail/open links here. Both public resource controllers
    // deliberately record analytics, so navigation would make this suite write.
  });
});
