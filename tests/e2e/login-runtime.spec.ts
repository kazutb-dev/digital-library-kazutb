import { expect, test } from '@playwright/test';

test.describe('login page live runtime', () => {
  test('login page uses the canonical form and recovers from an expired CSRF session', async ({ browser }, testInfo) => {
    const errors: string[] = [];
    const failedRequests: string[] = [];
    const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const page = await context.newPage();
    page.on('console', message => {
      if (message.type() === 'error') errors.push(message.text());
    });
    page.on('pageerror', error => errors.push(error.message));
    page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()}`));

    for (const locale of ['kk', 'ru', 'en']) {
      const response = await page.goto(`/login?lang=${locale}`);
      expect(response?.status()).toBe(200);
      expect(response?.headers()['cache-control']).toContain('no-store');
      await expect(page.locator('#login-form')).toHaveAttribute('method', 'POST');
      await expect(page.locator('#login-form')).toHaveAttribute('action', /\/login$/);
      await expect(page.locator('body')).not.toContainText('Active Directory');
      await expect(page.locator('[data-demo-slug], #demo-login-block')).toHaveCount(0);
      await expect(page.locator('body')).not.toContainText('CSRF token mismatch');
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    }

    await page.goto('/login?lang=ru');
    const pane = await page.locator('.auth-pane').boundingBox();
    const form = await page.locator('.auth-pane__inner').boundingBox();
    expect(pane).not.toBeNull();
    expect(form).not.toBeNull();
    expect(Math.abs((pane!.x + pane!.width / 2) - (form!.x + form!.width / 2))).toBeLessThanOrEqual(3);
    await page.screenshot({ path: testInfo.outputPath('login-desktop-ru.png'), fullPage: true });

    await page.locator('#login-form input[name="_token"]').evaluate((element: HTMLInputElement) => { element.value = 'expired-token'; });
    await page.locator('#login').fill('csrf-regression-check');
    await page.locator('#password').fill('not-submitted-to-directory');
    await Promise.all([
      page.waitForURL(/\/login\?lang=ru$/),
      page.locator('#submit-btn').click(),
    ]);
    await expect(page.locator('#form-message')).toContainText('Сеанс завершён');
    await expect(page.locator('body')).not.toContainText('CSRF token mismatch');

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login?lang=ru');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    await expect(page.locator('#login')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#submit-btn')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('login-mobile-ru.png'), fullPage: true });

    expect((await context.request.get('/api/demo-auth/identities')).status()).toBe(404);
    expect((await context.request.post('/api/demo-auth/login', { data: { role: 'director' } })).status()).toBe(405);
    expect((await context.request.post('/login/demo/director')).status()).toBe(405);

    expect(errors).toEqual([]);
    expect(failedRequests).toEqual([]);
    await context.close();
  });
});
