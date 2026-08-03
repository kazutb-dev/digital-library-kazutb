import { expect, test } from '@playwright/test';

const identities = [
  { slug: 'student', landing: '/dashboard' },
  { slug: 'teacher', landing: '/dashboard' },
  { slug: 'librarian', landing: '/librarian' },
  { slug: 'senior_librarian', landing: '/librarian' },
  { slug: 'director', landing: '/librarian' },
  { slug: 'acquisitions', landing: '/librarian' },
  { slug: 'cataloguer', landing: '/librarian' },
  { slug: 'bibliographer', landing: '/librarian' },
  { slug: 'admin', landing: '/admin' },
] as const;

const locales = ['kk', 'ru', 'en'] as const;
const brands = {
  kk: 'Ғылыми кітапхана',
  ru: 'Научная библиотека',
  en: 'Scientific Library',
} as const;

test.describe.configure({ mode: 'serial' });

for (const identity of identities) {
 for (const locale of locales) {
  test(`${identity.slug} / ${locale} opens every visible role navigation link without HTTP 500`, async ({ page, baseURL }) => {
    const runtimeErrors: string[] = [];
    const failed500: string[] = [];

    page.on('console', (message) => {
      if (message.type() === 'error' && /uncaught|exception|referenceerror|typeerror/i.test(message.text())) {
        runtimeErrors.push(message.text());
      }
    });
    page.on('response', (response) => {
      if (response.url().startsWith(baseURL ?? '') && response.status() >= 500) {
        failed500.push(`${response.status()} ${response.url()}`);
      }
    });

    await page.goto('/login');
    await page.locator(`[data-demo-slug="${identity.slug}"]`).click();
    await expect(page).toHaveURL(new RegExp(`${identity.landing.replace('/', '\\/')}(?:[/?#]|$)`));
    await page.goto(identity.landing);

    const localeForm = page.locator(`[data-locale-switcher] form:has(input[name="locale"][value="${locale}"])`).first();
    await expect(localeForm).toBeAttached();
    await localeForm.locator('xpath=ancestor::details[1]/summary').click();
    await localeForm.locator('button').click();
    await expect(page.locator('html')).toHaveAttribute('lang', locale);

    const landingResponse = await page.goto(identity.landing);
    expect(landingResponse?.status()).toBe(200);
    await assertLocalizedPage(page, locale);

    const paths = await page.locator('nav a[href], aside a[href], header a[href]').evaluateAll((links, origin) => {
      const allowedPrefixes = ['/dashboard', '/librarian', '/admin'];
      return [...new Set(links.map((link) => {
        const url = new URL((link as HTMLAnchorElement).href, String(origin));
        return url.origin === origin && allowedPrefixes.some((prefix) => url.pathname.startsWith(prefix))
          ? `${url.pathname}${url.search}`
          : null;
      }).filter((path): path is string => path !== null))];
    }, new URL(baseURL ?? 'http://127.0.0.1').origin);

    for (const path of paths) {
      const response = await page.goto(path);
      expect(response?.status(), `${identity.slug} visible link ${path}`).toBe(200);
      await assertLocalizedPage(page, locale);
    }

    expect(failed500).toEqual([]);
    expect(runtimeErrors).toEqual([]);

    await page.goto(identity.landing);
    if (locale !== 'kk') {
      const defaultLocaleForm = page.locator('[data-locale-switcher] form:has(input[name="locale"][value="kk"])').first();
      await defaultLocaleForm.locator('xpath=ancestor::details[1]/summary').click();
      await defaultLocaleForm.locator('button').click();
      await expect(page.locator('html')).toHaveAttribute('lang', 'kk');
    }
    const librarianLogout = page.locator('#librarian-logout-btn');
    if (await librarianLogout.count()) {
      await librarianLogout.click();
    } else {
      await page.locator('form[action$="/logout"] button').first().click();
    }
    await expect(page).toHaveURL(/\/login(?:[/?#]|$)/);
  });
 }
}

async function assertLocalizedPage(page: import('@playwright/test').Page, locale: typeof locales[number]) {
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('[data-locale-switcher]').first()).toBeVisible();
  await expect(page.locator(`[data-locale-switcher] input[name="locale"][value="${locale}"]`).first()).toBeAttached();
  await expect(page).toHaveTitle(/\S+/);
  await expect(page.locator('body')).toContainText(brands[locale]);
  await expect(page.locator('body')).not.toContainText(/HTTP\s*500|Internal Server Error|SQLSTATE|Stack trace|KazUTB Smart Library/i);

  const visibleText = await page.locator('body').innerText();
  expect(visibleText).not.toMatch(/\b(?:admin|brand|common|errors|librarian|member|reservation|roles|shell)\.[a-z0-9_.]+\b/);
  const mixed = {
    kk: /Reader account|Reader services and activity|Browse Catalog|Sign out|Личный кабинет|Рабочее место/,
    ru: /Reader account|Reader services and activity|Browse Catalog|Sign out|Library workspace/,
    en: /Моя библиотека|Кабинет библиотекаря|Менің кітапханам|Кітапханашы кабинеті/,
  }[locale];
  expect(visibleText).not.toMatch(mixed);
}
