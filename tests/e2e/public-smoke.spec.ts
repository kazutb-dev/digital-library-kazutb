import { expect, test, type Page } from '@playwright/test';

const canonicalRoutes = [
  '/',
  '/catalog',
  '/resources',
  '/repository',
  '/news',
  '/events',
  '/about',
  '/discover',
  '/contacts',
  '/rules',
  '/leadership',
  '/shortlist',
  '/login',
];

const viewports = [
  { name: 'desktop-1920', width: 1920, height: 1080 },
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'desktop-1366', width: 1366, height: 768 },
  { name: 'tablet-1024', width: 1024, height: 768 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'mobile-375', width: 375, height: 812 },
];

function collectRuntimeProblems(page: Page) {
  const consoleErrors: string[] = [];
  const pageErrors: string[] = [];
  const failedRequests: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('requestfailed', (request) => {
    const url = new URL(request.url());
    if (url.origin === new URL(page.url() || 'http://localhost').origin) {
      failedRequests.push(`${request.method()} ${url.pathname}: ${request.failure()?.errorText ?? 'failed'}`);
    }
  });

  return { consoleErrors, pageErrors, failedRequests };
}

test.describe('canonical public application shell', () => {
  for (const route of canonicalRoutes) {
    test(`${route} renders with one public shell and no runtime errors`, async ({ page }) => {
      const runtime = collectRuntimeProblems(page);
      const response = await page.goto(`${route}${route.includes('?') ? '&' : '?'}lang=en`, { waitUntil: 'networkidle' });

      expect(response?.status(), route).toBe(200);
      if (route === '/login') {
        await expect(page.locator('#login-form')).toBeVisible();
        await expect(page.locator('.auth-screen')).toBeVisible();
      } else {
        await expect(page.locator('.site-header')).toBeVisible();
        await expect(page.locator('main#main-content')).toBeVisible();
        await expect(page.locator('footer.university-footer')).toBeVisible();
      }
      await expect(page.locator('h1').first()).toBeVisible();
      await expect(page.locator('html')).toHaveAttribute('lang', 'en');
      expect(await page.locator('h1').count(), `${route} must have exactly one H1`).toBe(1);
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `${route} overflows horizontally`).toBe(true);
      expect(runtime.pageErrors, `page errors on ${route}`).toEqual([]);
      expect(runtime.failedRequests, `failed same-origin requests on ${route}`).toEqual([]);
      expect(runtime.consoleErrors, `console errors on ${route}`).toEqual([]);
    });
  }

  for (const locale of ['kk', 'ru']) {
    test(`all canonical routes render the ${locale.toUpperCase()} locale`, async ({ page }) => {
      for (const route of canonicalRoutes) {
        const response = await page.goto(`${route}?lang=${locale}`, { waitUntil: 'domcontentloaded' });
        expect(response?.status(), `${route} (${locale})`).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('h1').first()).toBeVisible();
        await expect(page.locator('body')).not.toContainText('translation missing');
      }
    });
  }

  for (const viewport of viewports) {
    test(`responsive shell works at ${viewport.name}`, async ({ page }) => {
      await page.setViewportSize(viewport);
      for (const route of ['/contacts', '/rules', '/resources', '/leadership']) {
        const response = await page.goto(`${route}?lang=kk`, { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBe(200);
        await expect(page.locator('.site-header')).toBeVisible();
        await expect(page.locator('h1').first()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `${route} overflows at ${viewport.width}px`).toBe(true);
      }

      if (await page.locator('.hdr-burger > summary').isVisible()) {
        await page.locator('.hdr-burger > summary').click();
        await expect(page.locator('.hdr-panel--menu')).toBeVisible();
        await expect(page.locator('.hdr-panel--menu a[href^="/catalog"]').first()).toBeVisible();
      } else {
        await expect(page.locator('.hdr-nav')).toBeVisible();
        await expect(page.locator('.hdr-nav a[href^="/catalog"]')).toBeVisible();
      }
    });
  }

  test('locale switcher preserves the current route', async ({ page }) => {
    await page.goto('/rules?lang=ru');
    await page.locator('details[data-locale-switcher] > summary').click();
    await page.locator('details[data-locale-switcher] form').filter({ has: page.locator('input[value="en"]') }).locator('button').click();
    await expect(page).toHaveURL(/\/rules$/);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('h1')).toContainText('Library Usage Rules');
  });

  test('guest contact request enters the authenticated message flow', async ({ page }) => {
    await page.goto('/contacts?lang=en');
    await page.locator('[data-test-id="contacts-canonical-inquiry-cta"]').click();
    await expect(page).toHaveURL(/\/login\?.*redirect=/);
    await expect(page.locator('#login-form')).toBeVisible();
  });

  test('legacy public surfaces have explicit permanent redirects', async ({ request }) => {
    for (const [route, target] of [['/services', '/'], ['/for-teachers', '/resources'], ['/app/catalog', '/catalog']]) {
      const response = await request.get(route, { maxRedirects: 0 });
      expect(response.status(), route).toBe(301);
      expect(new URL(response.headers().location!, 'http://localhost').pathname).toBe(target);
    }
  });

  test('all same-origin links exposed by canonical indexes resolve', async ({ page, request }) => {
    const links = new Set<string>();
    for (const route of canonicalRoutes) {
      await page.goto(`${route}?lang=en`, { waitUntil: 'domcontentloaded' });
      for (const href of await page.locator('a[href]').evaluateAll((anchors) => anchors.map((anchor) => (anchor as HTMLAnchorElement).href))) {
        const url = new URL(href);
        if (url.origin === new URL(page.url()).origin && !url.pathname.startsWith('/dashboard') && !url.pathname.startsWith('/admin') && !url.pathname.startsWith('/librarian')) {
          links.add(`${url.pathname}${url.search}`);
        }
      }
    }

    const failures: string[] = [];
    for (const href of links) {
      const response = await request.get(href, { maxRedirects: 5 });
      const protectedResourceOpen = /^\/resources\/[^/]+\/open(?:\?|$)/.test(href);
      if (response.status() >= 400 && !(protectedResourceOpen && response.status() === 403)) {
        failures.push(`${response.status()} ${href}`);
      }
    }
    expect(failures).toEqual([]);
  });
});
