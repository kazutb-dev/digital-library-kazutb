import { chromium } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8127';
const browser = await chromium.launch({ headless: true });
const errors = [];

try {
  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ baseURL, viewport });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error') errors.push(message.text());
    });
    page.on('response', response => {
      if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
    });

    await page.goto('/login');
    await page.locator('#login-form input[name="login"]').fill('demo-senior-librarian@kazutb.local');
    await page.locator('#login-form input[name="password"]').fill('DemoAccess2026!');
    await Promise.all([
      page.waitForURL(/\/librarian(?:[/?#]|$)/),
      page.locator('#login-form button[type="submit"]').click(),
    ]);

    await page.goto('/librarian/inventory?lang=ru');
    await page.getByText('Проблемы размещения фонда').waitFor();
    await page.getByText('Размер pilot').waitFor();

    await page.goto('/librarian/copies?lang=ru');
    const firstCopy = page.locator('a[href*="/librarian/copies/"]').first();
    if (await firstCopy.count()) {
      await firstCopy.click();
      await page.getByText('Маркировка экземпляра').waitFor();
      await page.getByText('Качество данных').first().waitFor();
    }

    await page.goto('/librarian/circulation?lang=ru');
    await page.waitForLoadState('domcontentloaded');
    await context.close();
  }
} finally {
  await browser.close();
}

if (errors.length) {
  throw new Error(`Browser gate errors:\n${errors.join('\n')}`);
}

console.log('PHYSICAL INVENTORY UI GATE: PASS (desktop + mobile)');
