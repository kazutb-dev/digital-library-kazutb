import { expect, test } from '@playwright/test';

test('librarian draft is approved by director and reaches public surfaces', async ({ page, baseURL }) => {
  test.setTimeout(90_000);
  const runtimeErrors: string[] = [];
  const serverErrors: string[] = [];
  page.on('console', message => {
    if (message.type() === 'error' && /uncaught|exception|referenceerror|typeerror/i.test(message.text())) {
      runtimeErrors.push(message.text());
    }
  });
  page.on('response', response => {
    if (response.url().startsWith(baseURL ?? '') && response.status() >= 500) {
      serverErrors.push(`${response.status()} ${response.url()}`);
    }
  });

  await loginAs(page, 'demo_librarian');
  await expect(page).toHaveURL(/\/librarian(?:[/?#]|$)/);
  await page.goto('/librarian/news/create');

  const marker = `PW-E2E-${Date.now()}`;
  const title = `${marker} кітапхана іс-шарасы`;
  await page.locator('input[name="title_kk"]').fill(title);
  await page.locator('textarea[name="excerpt_kk"]').fill('Playwright арқылы тексерілетін қысқаша сипаттама.');
  await page.locator('textarea[name="content_kk"]').fill('<p>Қауіпсіз редакциялық workflow толық тексеріледі.</p>');
  await page.locator('input[name="image_alt_kk"]').fill('Кітапхана іс-шарасының суреті');
  await page.locator('input[name="audience"]').fill('Барлық оқырмандар');
  const category = page.locator('select[name="category_id"] option').filter({ hasText: 'Іс-шаралар' });
  await page.locator('select[name="category_id"]').selectOption(await category.getAttribute('value') ?? '');

  const start = new Date(Date.now() + 5 * 86_400_000);
  const end = new Date(start.getTime() + 3_600_000);
  const localDateTime = (date: Date) => date.toISOString().slice(0, 16);
  await page.locator('input[name="starts_at"]').fill(localDateTime(start));
  await page.locator('input[name="ends_at"]').fill(localDateTime(end));
  await page.locator('input[name="venue"]').fill('Оқу залы');
  await page.locator('input[name="organizer"]').fill('Ғылыми кітапхана');
  await page.locator('input[name="contact_name"]').fill('Ақпарат бөлімі');
  await page.locator('input[name="cover_image"]').setInputFiles('public/logo.png');
  await page.locator('#news-editor button[type="submit"]').click();
  await expect(page).toHaveURL(/\/librarian\/news\/\d+\/edit/);

  const editUrl = page.url();
  const slug = await page.locator('input[name="slug_kk"]').inputValue();
  await page.locator('form:has(input[name="status"][value="pending_review"]) button').click();
  await expect(page.locator('body')).toContainText(/Келіс|согласован|review/i);

  await Promise.all([
    page.waitForURL(/\/login(?:[/?#]|$)/),
    page.locator('#librarian-logout-btn').click(),
  ]);
  await loginAs(page, 'demo_director');
  await page.goto(editUrl);
  await page.locator('input[name="show_on_homepage"]').check();
  await page.locator('#news-editor button[type="submit"]').click();
  await page.locator('form:has(input[name="status"][value="approved"]) button').click();
  await page.locator('form:has(input[name="status"][value="published"]) button').click();

  const detailResponse = await page.goto(`/news/${slug}?lang=kk`);
  expect(detailResponse?.status()).toBe(200);
  await expect(page.locator('body')).toContainText(title);
  const homepageResponse = await page.goto('/?lang=kk');
  expect(homepageResponse?.status()).toBe(200);
  await expect(page.locator('body')).toContainText(title);

  await page.goto(editUrl);
  await page.locator('form:has(input[name="status"][value="archived"]) button').click();
  const archivedResponse = await page.goto(`/news/${slug}?lang=kk`);
  expect(archivedResponse?.status()).toBe(404);
  expect(serverErrors).toEqual([]);
  expect(runtimeErrors).toEqual([]);
});

async function loginAs(page: import('@playwright/test').Page, login: string) {
  if (!/\/login(?:[/?#]|$)/.test(page.url())) await page.goto('/login');
  await page.locator('#login-form input[name="login"]').fill(login);
  await page.locator('#login-form input[name="password"]').fill('DemoAccess2026!');
  await Promise.all([
    page.waitForURL(/\/(?:dashboard|librarian|admin)(?:[/?#]|$)/),
    page.locator('#login-form button[type="submit"]').click(),
  ]);
}
