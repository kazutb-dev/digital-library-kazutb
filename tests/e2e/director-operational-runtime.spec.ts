import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

type NetscapeCookie = {
  name: string;
  value: string;
  domain: string;
  path: string;
  expires: number;
  httpOnly: boolean;
  secure: boolean;
  sameSite: 'Lax';
};

function authenticatedCookies(): NetscapeCookie[] {
  const cookieFile = process.env.PLAYWRIGHT_AUTH_COOKIE_FILE;
  if (!cookieFile) throw new Error('PLAYWRIGHT_AUTH_COOKIE_FILE is required');

  return readFileSync(cookieFile, 'utf8').split(/\r?\n/).flatMap(line => {
    if (!line || (line.startsWith('#') && !line.startsWith('#HttpOnly_'))) return [];
    const httpOnly = line.startsWith('#HttpOnly_');
    const fields = (httpOnly ? line.slice('#HttpOnly_'.length) : line).split('\t');
    if (fields.length !== 7) return [];
    const [domain, , path, secure, expires, name, value] = fields;
    return [{ domain, path, secure: secure === 'TRUE', expires: Number(expires), name, value, httpOnly, sameSite: 'Lax' as const }];
  });
}

test('AD-linked director receives the role-aware operational workspace', async ({ browser }, testInfo) => {
  const errors: string[] = [];
  const failedRequests: string[] = [];
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await context.addCookies(authenticatedCookies());
  const page = await context.newPage();
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('pageerror', error => errors.push(error.message));
  page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()}`));

  const allowed = [
    '/librarian?lang=ru',
    '/librarian/data-quality?lang=ru',
    '/librarian/reports?lang=ru',
    '/librarian/messages?lang=ru',
    '/librarian/repository?lang=ru',
  ];
  for (const route of allowed) {
    const response = await page.goto(route, { waitUntil: 'networkidle' });
    expect(response?.status(), route).toBe(200);
    const geometry = await page.evaluate(() => ({
      clientWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      offenders: [...document.querySelectorAll<HTMLElement>('body *')]
        .map(element => ({ tag: element.tagName, className: element.className, right: element.getBoundingClientRect().right }))
        .filter(element => element.right > document.documentElement.clientWidth + 1)
        .slice(0, 8),
    }));
    expect(geometry.scrollWidth, `${route}: ${JSON.stringify(geometry)}`).toBeLessThanOrEqual(geometry.clientWidth);
    const visibleText = await page.locator('body').innerText();
    expect(visibleText, route).not.toContain('reservation_lifespan_days');
    expect(visibleText, route).not.toMatch(/DATA_QUALITY\.|Active Directory (не настроен|not configured)/i);
  }

  await page.goto('/librarian?lang=ru', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-workspace-role]')).toContainText('Директор');
  await expect(page.locator('[data-section="director-executive-dashboard"]')).toBeVisible();
  await expect(page.locator('body')).not.toContainText('Демо-');
  await expect(page.locator('a[href^="/admin"]')).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('director-dashboard-desktop-ru.png'), fullPage: true });

  for (const locale of ['kk', 'ru', 'en']) {
    await page.goto(`/librarian?lang=${locale}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('[data-section="director-executive-dashboard"]')).toBeVisible();
  }

  const denied = [
    '/librarian/catalog', '/librarian/copies', '/librarian/workspace/search',
    '/librarian/circulation', '/librarian/readers', '/librarian/visits',
    '/librarian/reservations', '/admin', '/admin/users', '/admin/system',
  ];
  for (const route of denied) {
    expect((await context.request.get(route)).status(), route).toBe(403);
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/librarian?lang=ru', { waitUntil: 'domcontentloaded' });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
  await expect(page.locator('[data-mobile-navigation]')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('director-dashboard-mobile-ru.png'), fullPage: true });

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-section="director-executive-dashboard"]')).toBeVisible();
  await page.locator('#librarian-logout-btn').click();
  await page.waitForURL(/\/login/);
  await expect(page.locator('#login-form')).toBeVisible();
  await page.goto('/librarian?lang=ru');
  await expect(page).toHaveURL(/\/login/);

  expect(errors).toEqual([]);
  expect(failedRequests).toEqual([]);
  await context.close();
});
