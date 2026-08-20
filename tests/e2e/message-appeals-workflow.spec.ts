import { expect, test } from '@playwright/test';

test('reader ticket reaches librarian queue and preserves private workflow', async ({ page, baseURL }, testInfo) => {
  test.setTimeout(90_000);
  const serverErrors: string[] = [];
  const runtimeErrors: string[] = [];
  page.on('response', response => {
    if (response.url().startsWith(baseURL ?? '') && response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
  });
  page.on('console', message => {
    if (message.type() === 'error' && /uncaught|exception|referenceerror|typeerror/i.test(message.text())) runtimeErrors.push(message.text());
  });

  await loginAs(page, 'demo_student');
  await page.goto('/dashboard/messages?lang=ru');
  await expect(page.locator('form[data-message-form]')).toBeVisible();
  await page.locator('select[name="type"]').selectOption('request');
  const requestCategory = page.locator('select[name="category_id"] option').filter({ hasText: /Запрос|Сұрау|Request/ }).first();
  await page.locator('select[name="category_id"]').selectOption(await requestCategory.getAttribute('value') ?? '');
  const marker = `PW-MSG-${Date.now()}`;
  await page.locator('input[name="subject"]').fill(`${marker} catalogue request`);
  await page.locator('textarea[name="body"]').fill('Please help me find literature for the catalogue research task.');
  await page.locator('input[name="contact_confirmed"]').check();
  await Promise.all([
    page.waitForURL(/\/dashboard\/messages\/[0-9a-f-]{36}/),
    page.locator('form[data-message-form] button[type="submit"]').click(),
  ]);
  const ticketUrl = page.url();
  await expect(page.locator('body')).toContainText(marker);
  await expect(page.locator('body')).toContainText(/LIB-\d{4}-\d{6}/);
  await page.screenshot({ path: testInfo.outputPath('reader-ticket.png'), fullPage: true });

  await Promise.all([
    page.waitForURL(/\/login/),
    page.locator('form[action="/logout"] button').first().click(),
  ]);
  await loginAs(page, 'demo_librarian');
  await page.goto('/librarian/messages?search=' + encodeURIComponent(marker));
  await expect(page.locator('body')).toContainText(marker);
  await page.getByText(marker + ' catalogue request').click();
  await expect(page.locator('body')).toContainText(/LIB-\d{4}-\d{6}/);
  await page.screenshot({ path: testInfo.outputPath('librarian-ticket.png'), fullPage: true });
  if (await page.locator('form[action$="/take"] button').count()) {
    await page.locator('form[action$="/take"] button').click();
    await expect(page.locator('body')).toContainText(/На рассмотрении|Қаралуда|In review/);
  }

  expect(ticketUrl).toMatch(/\/dashboard\/messages\/[0-9a-f-]{36}/);
  expect(serverErrors).toEqual([]);
  expect(runtimeErrors).toEqual([]);
});

async function loginAs(page: import('@playwright/test').Page, login: string) {
  await page.goto('/login');
  await page.locator('#login-form input[name="login"]').fill(login);
  await page.locator('#login-form input[name="password"]').fill('DemoAccess2026!');
  await Promise.all([
    page.waitForURL(/\/(?:dashboard|librarian|admin)(?:[/?#]|$)/),
    page.locator('#login-form button[type="submit"]').click(),
  ]);
}

test('message cabinet remains usable on mobile without horizontal overflow', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await loginAs(page, 'demo_student');
  await page.goto('/dashboard/messages');
  await expect(page.locator('form[data-message-form]')).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  expect(overflow).toBe(false);
  await page.screenshot({ path: testInfo.outputPath('member-mobile.png'), fullPage: true });
});
