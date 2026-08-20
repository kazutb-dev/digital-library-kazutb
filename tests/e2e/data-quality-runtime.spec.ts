import { expect, test } from '@playwright/test';

const login = process.env.PLAYWRIGHT_RUNTIME_LOGIN;
const password = process.env.PLAYWRIGHT_RUNTIME_PASSWORD;
const authBootstrapUrl = process.env.PLAYWRIGHT_AUTH_BOOTSTRAP_URL;
const sessionCookieName = process.env.PLAYWRIGHT_SESSION_COOKIE_NAME;
const sessionCookieValue = process.env.PLAYWRIGHT_SESSION_COOKIE_VALUE;
const demoSlug = process.env.PLAYWRIGHT_DEMO_SLUG;

test.describe('librarian data-quality runtime', () => {
  test.skip((!login || !password) && (!sessionCookieName || !sessionCookieValue), 'Runtime librarian credentials are required.');

  test('supports the librarian daily workflow on the live runtime', async ({ page }, testInfo) => {
    const errors: string[] = [];
    const failedRequests: string[] = [];
    page.on('console', message => {
      if (message.type() === 'error') errors.push(message.text());
    });
    page.on('pageerror', error => errors.push(error.message));
    page.on('requestfailed', request => {
      if (new URL(request.url()).origin === new URL(process.env.PLAYWRIGHT_BASE_URL!).origin) {
        failedRequests.push(`${request.method()} ${request.url()} — ${request.failure()?.errorText ?? 'failed'}`);
      }
    });

    if (sessionCookieName && sessionCookieValue) {
      await page.context().addCookies([{ name: sessionCookieName, value: sessionCookieValue, url: process.env.PLAYWRIGHT_BASE_URL! }]);
    } else {
      await page.goto(authBootstrapUrl ? `${authBootstrapUrl}/login?lang=ru` : '/login?lang=ru');
      if (demoSlug) {
        await Promise.all([
          page.waitForURL(/\/(?:dashboard|librarian|admin)(?:[/?#]|$)/),
          page.locator(`[data-demo-slug="${demoSlug}"]`).click(),
        ]);
      } else {
        await page.locator('#login-form input[name="login"]').fill(login!);
        await page.locator('#login-form input[name="password"]').fill(password!);
        await Promise.all([
          page.waitForURL(/\/(?:dashboard|librarian|admin)(?:[/?#]|$)/),
          page.locator('#login-form button[type="submit"]').click(),
        ]);
      }
    }

    if (!sessionCookieName && authBootstrapUrl) {
      const runtimeOrigin = new URL(process.env.PLAYWRIGHT_BASE_URL!);
      const cookies = await page.context().cookies();
      await page.context().addCookies(cookies.map(cookie => ({
        ...cookie,
        domain: runtimeOrigin.hostname,
        path: '/',
      })));
      if (process.env.PLAYWRIGHT_AUTH_PAUSE_MS) {
        await page.waitForTimeout(Number(process.env.PLAYWRIGHT_AUTH_PAUSE_MS));
      }
    }

    const assertPage = async (path: string) => {
      const response = await page.goto(path);
      expect(response?.status(), path).toBeLessThan(400);
      await expect(page.locator('h1').first(), path).toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), path).toBeTruthy();
      const visibleText = await page.locator('body').innerText();
      expect(visibleText, path).not.toMatch(/DATA_QUALITY\.|reservation_lifespan_days|\b(?:entity_uuid|validator_id)\b/);
      if (!path.includes('lang=en')) expect(visibleText, path).not.toMatch(/\bShowing \d/i);
    };

    await page.setViewportSize({ width: 1440, height: 900 });
    await assertPage('/librarian/data-quality?lang=ru');
    await expect(page.getByRole('heading', { name: 'Центр качества данных' })).toBeVisible();
    await expect(page.getByText('Проверено записей')).toBeVisible();
    await expect(page.getByText('9 562 / 9 562')).toBeVisible();
    await expect(page.getByText('50 907 / 50 907')).toBeVisible();
    await expect(page.getByText('Записей требуют внимания')).toBeVisible();
    await expect(page.getByText('Экземпляров требуют внимания')).toBeVisible();
    await expect(page.getByText('Одна строка — одна книга или экземпляр.')).toBeVisible();
    await expect(page.getByText('DATA_QUALITY.FIELDS.CATEGORY')).toHaveCount(0);
    await expect(page.locator('select[name="category"]')).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Быстрый поиск' })).toBeVisible();
    const inbox = page.getByTestId('quality-object-inbox');
    await expect(inbox.locator('tbody tr').first()).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('data-quality-desktop.png'), fullPage: true });

    await inbox.locator('tbody tr a').first().click();
    await expect(page.getByText('Технические сведения').first()).toBeVisible();
    await expect(page.getByRole('link', { name: /Открыть (редактирование|экземпляр)/ })).toBeVisible();
    await expect(page.getByText('Замечания по объекту')).toBeVisible();

    await assertPage('/librarian/catalog/1/edit?lang=ru');
    await expect(page.getByRole('heading', { name: 'Центр качества данных' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Открыть связанные проблемы' })).toBeVisible();

    await assertPage('/librarian/copies/1?lang=ru');
    await expect(page.getByRole('heading', { name: 'Центр качества данных' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Открыть связанные проблемы' })).toBeVisible();
    await expect(page.getByText('MARC-3', { exact: true }).first()).toBeVisible();

    await assertPage('/librarian/copies?lang=ru');
    await expect(page.getByRole('heading', { name: 'Экземпляры' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    await page.screenshot({ path: testInfo.outputPath('copies-desktop.png'), fullPage: true });

    await assertPage('/librarian/workspace/search?q=MARC-3&lang=ru');
    await expect(page.getByRole('heading', { name: /поиск/i })).toBeVisible();
    await expect(page.getByText('MARC-3', { exact: true }).first()).toBeVisible();

    const desktopScreens: Array<[string, string]> = [
      ['/librarian?lang=ru', 'dashboard-desktop.png'],
      ['/librarian/catalog?lang=ru', 'catalog-desktop.png'],
      ['/librarian/copies?lang=ru', 'copies-desktop.png'],
      ['/librarian/workspace/search?q=MARC-3&lang=ru', 'search-desktop.png'],
    ];
    for (const [path, screenshot] of desktopScreens) {
      await assertPage(path);
      await page.screenshot({ path: testInfo.outputPath(screenshot), fullPage: true });
    }

    await assertPage('/librarian?lang=ru');
    await expect(page.getByText(/настроена\. Часть специализированного функционала/)).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Центр качества данных' })).toBeVisible();

    for (const path of ['/librarian/circulation?lang=ru', '/librarian/readers?lang=ru', '/librarian/visits?lang=ru', '/librarian/reservations?lang=ru']) {
      await assertPage(path);
    }
    await expect(page.getByText('reservation_lifespan_days')).toHaveCount(0);

    await page.setViewportSize({ width: 1920, height: 1080 });
    for (const path of ['/librarian?lang=ru', '/librarian/data-quality?lang=ru', '/librarian/catalog?lang=ru', '/librarian/copies?lang=ru', '/librarian/workspace/search?q=MARC-3&lang=ru', '/librarian/circulation?lang=ru', '/librarian/readers?lang=ru', '/librarian/visits?lang=ru', '/librarian/reservations?lang=ru']) {
      await assertPage(path);
    }

    for (const viewport of [{ width: 768, height: 1024 }, { width: 1024, height: 768 }]) {
      await page.setViewportSize(viewport);
      for (const path of ['/librarian?lang=ru', '/librarian/data-quality?lang=ru', '/librarian/catalog?lang=ru', '/librarian/copies?lang=ru', '/librarian/workspace/search?q=MARC-3&lang=ru']) {
        await assertPage(path);
      }
    }

    await page.setViewportSize({ width: 390, height: 844 });
    for (const [path, screenshot] of [
      ['/librarian/data-quality?lang=ru', 'data-quality-mobile.png'],
      ['/librarian/catalog?lang=ru', 'catalog-mobile.png'],
      ['/librarian/copies?lang=ru', 'copies-mobile.png'],
      ['/librarian/workspace/search?q=MARC-3&lang=ru', 'search-mobile.png'],
    ] as Array<[string, string]>) {
      await assertPage(path);
      if (path.startsWith('/librarian/data-quality')) {
        const card = page.getByTestId('quality-object-card').first();
        await expect(card).toBeVisible();
        await expect(card.getByRole('link', { name: 'Открыть' })).toBeVisible();
      }
      if (path.startsWith('/librarian/catalog')) {
        const card = page.getByTestId('catalog-record-card').first();
        await expect(card).toBeVisible();
        await expect(card.getByRole('link', { name: 'Редактировать' })).toBeVisible();
      }
      await page.screenshot({ path: testInfo.outputPath(screenshot), fullPage: true });
    }
    await expect(page.locator('[data-mobile-navigation] summary')).toBeVisible();
    await page.locator('[data-mobile-navigation] summary').click();
    await expect(page.locator('[data-mobile-navigation] nav')).toBeVisible();

    await page.setViewportSize({ width: 375, height: 812 });
    for (const path of ['/librarian/data-quality?lang=ru', '/librarian/catalog?lang=ru', '/librarian/copies?lang=ru', '/librarian/workspace/search?q=MARC-3&lang=ru']) {
      await assertPage(path);
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    const localizedRoutes = ['/librarian', '/librarian/data-quality', '/librarian/catalog', '/librarian/copies', '/librarian/workspace/search?q=MARC-3', '/librarian/readers', '/librarian/reservations'];
    for (const locale of ['kk', 'en']) {
      for (const route of localizedRoutes) {
        await assertPage(`${route}${route.includes('?') ? '&' : '?'}lang=${locale}`);
      }
      await assertPage(`/librarian/data-quality?lang=${locale}`);
      await page.screenshot({ path: testInfo.outputPath(`data-quality-${locale}.png`), fullPage: true });
    }

    expect(errors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });
});
