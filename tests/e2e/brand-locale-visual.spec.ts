import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const phase = process.env.UI_SNAPSHOT_PHASE || 'after';
const screenshotDir = path.resolve(`docs/runtime/screenshots/brand-locale-${phase}`);

test.beforeAll(() => mkdirSync(screenshotDir, { recursive: true }));

test('shared locale control is accessible and stable on public desktop and mobile', async ({ browser, baseURL }) => {
  for (const surface of [
    { name: 'public-desktop', path: '/', viewport: { width: 1440, height: 1000 } },
    { name: 'public-mobile', path: '/', viewport: { width: 390, height: 844 } },
    { name: 'login-desktop', path: '/login', viewport: { width: 1440, height: 1000 } },
  ]) {
    const context = await browser.newContext({ baseURL, viewport: surface.viewport });
    const page = await context.newPage();
    const runtimeErrors: string[] = [];
    const failed500: string[] = [];
    page.on('pageerror', error => runtimeErrors.push(error.message));
    page.on('response', response => {
      if (response.url().startsWith(baseURL ?? '') && response.status() >= 500) failed500.push(`${response.status()} ${response.url()}`);
    });

    const response = await page.goto(surface.path, { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await assertBrandAndSwitcher(page);

    const switcher = page.locator('[data-locale-switcher]');
    const trigger = switcher.locator('summary');
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    const menu = switcher.locator('[role="menu"]');
    await expect(menu).toBeVisible();
    const menuBox = await menu.boundingBox();
    expect(menuBox).not.toBeNull();
    expect(menuBox!.x).toBeGreaterThanOrEqual(0);
    expect(menuBox!.x + menuBox!.width).toBeLessThanOrEqual(surface.viewport.width);
    await page.keyboard.press('Escape');
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await trigger.click();
    await page.mouse.click(surface.viewport.width / 2, surface.viewport.height - 20);
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    expect(runtimeErrors).toEqual([]);
    expect(failed500).toEqual([]);
    await page.screenshot({ path: path.join(screenshotDir, `${surface.name}.png`), fullPage: true });
    await context.close();
  }
});

test('official brand and shared switcher are consistent in role workspaces', async ({ browser, baseURL }) => {
  for (const surface of [
    { name: 'member-desktop', role: 'student', path: '/dashboard' },
    { name: 'librarian-desktop', role: 'librarian', path: '/librarian' },
    { name: 'senior-librarian-desktop', role: 'senior_librarian', path: '/librarian' },
    { name: 'director-desktop', role: 'director', path: '/librarian' },
    { name: 'acquisitions-desktop', role: 'acquisitions', path: '/librarian' },
    { name: 'cataloguer-desktop', role: 'cataloguer', path: '/librarian' },
    { name: 'bibliographer-desktop', role: 'bibliographer', path: '/librarian' },
    { name: 'admin-desktop', role: 'admin', path: '/admin' },
  ]) {
    const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    const runtimeErrors: string[] = [];
    const failed500: string[] = [];
    page.on('pageerror', error => runtimeErrors.push(error.message));
    page.on('response', response => {
      if (response.url().startsWith(baseURL ?? '') && response.status() >= 500) failed500.push(`${response.status()} ${response.url()}`);
    });

    await page.goto('/login', { waitUntil: 'networkidle' });
    await page.locator(`[data-demo-slug="${surface.role}"]`).click();
    await page.waitForLoadState('networkidle');
    const response = await page.goto(surface.path, { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await assertBrandAndSwitcher(page);
    await expect(page.locator('body')).not.toContainText(/KazUTB кітапханасы|KazUTB Smart Library|My Library|Operations|Librarian Console/);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    expect(runtimeErrors).toEqual([]);
    expect(failed500).toEqual([]);
    await page.screenshot({ path: path.join(screenshotDir, `${surface.name}.png`), fullPage: true });
    await context.close();
  }
});

test('member and admin mobile navigation retain brand and locale access', async ({ browser, baseURL }) => {
  for (const surface of [
    { name: 'member-mobile', role: 'student', path: '/dashboard', openDrawer: false },
    { name: 'admin-mobile', role: 'admin', path: '/admin', openDrawer: true },
  ]) {
    const context = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    await page.goto('/login', { waitUntil: 'networkidle' });
    await page.locator(`[data-demo-slug="${surface.role}"]`).click();
    await page.waitForLoadState('networkidle');
    await page.goto(surface.path, { waitUntil: 'networkidle' });
    await assertBrandAndSwitcher(page);
    if (surface.openDrawer) await page.locator('header details > summary').first().click();
    await expect(page.locator('[data-library-brand]:visible').first()).toBeVisible();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await page.screenshot({ path: path.join(screenshotDir, `${surface.name}.png`), fullPage: true });
    await context.close();
  }
});

async function assertBrandAndSwitcher(page: import('@playwright/test').Page) {
  await expect(page.locator('[data-locale-switcher]')).toHaveCount(1);
  await expect(page.locator('[data-locale-globe]')).toHaveCount(1);
  await expect(page.locator('[data-locale-switcher]')).toBeVisible();
  await expect(page.locator('[data-library-brand]:visible').first()).toBeVisible();
  await expect(page.locator('[data-library-brand]:visible img').first()).toHaveAttribute('src', /\/logo\.png$/);
  await expect(page.locator('body')).toContainText('Ғылыми кітапхана');
  await expect(page.locator('body')).toContainText('Қ. Құлажанов атындағы Қазақ технология және бизнес университеті');
  await expect(page.locator('body')).not.toContainText('🌐');
}
