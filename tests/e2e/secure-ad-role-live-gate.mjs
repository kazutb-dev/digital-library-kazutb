import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createInterface } from 'node:readline';

const [mode, expectedRoleLabel, outputRoot = 'test-results/secure-role-live'] = process.argv.slice(2);
if (!['librarian-full', 'librarian-parity', 'senior'].includes(mode ?? '')) {
  throw new Error('mode must be librarian-full, librarian-parity, or senior');
}

const inputReader = createInterface({ input: process.stdin, terminal: false });
const inputIterator = inputReader[Symbol.asyncIterator]();
const inputLine = await inputIterator.next();
inputReader.close();
let input = inputLine.value ?? '';
const credentials = JSON.parse(input.trim());
if (typeof credentials.login !== 'string' || typeof credentials.password !== 'string') {
  throw new Error('A login and password must be supplied through stdin');
}

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://10.0.1.17';
const outputDir = `${outputRoot}/${mode}`;
mkdirSync(outputDir, { recursive: true });

const errors = [];
const failedRequests = [];
const routeResults = [];
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const cleanText = text => !/(?:DATA_QUALITY\.|reservation_lifespan_days|entity_uuid|validator_id|senior_librarian|lead_librarian|admin_full)/i.test(text);

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('pageerror', error => errors.push(error.message));
page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));

try {
  await page.goto(`${baseURL}/login?lang=ru`, { waitUntil: 'domcontentloaded' });
  await page.locator('#login').fill(credentials.login);
  await page.locator('#password').fill(credentials.password);
  await page.locator('#submit-btn').click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(500);
  if (new URL(page.url()).pathname !== '/librarian') {
    const message = (await page.locator('#form-message').innerText()).trim();
    throw new Error(`AD login failed: ${message || 'login page remained active'}`);
  }
  credentials.password = '';
  input = '';

  const roleText = (await page.locator('[data-workspace-role]').innerText()).trim();
  assert(roleText.includes(expectedRoleLabel), `Expected role label ${expectedRoleLabel}; received ${roleText}`);
  assert(!/\b(?:librarian|senior_librarian|lead_librarian|admin|director)\b/i.test(roleText), `Internal role leaked: ${roleText}`);

  const commonRoutes = [
    '/librarian?lang=ru',
    '/librarian/circulation?lang=ru',
    '/librarian/readers?lang=ru',
    '/librarian/visits?lang=ru',
    '/librarian/reservations?lang=ru',
    '/librarian/catalog?search=Современное%20академическое%20письмо&lang=ru',
    '/librarian/copies?search=INV-955001&lang=ru',
    '/librarian/workspace/search?q=KUTB00955001&lang=ru',
    '/librarian/data-quality?lang=ru',
  ];
  const routes = mode === 'librarian-parity'
    ? ['/librarian?lang=ru', '/librarian/circulation?lang=ru', '/librarian/copies?search=INV-955001&lang=ru']
    : commonRoutes;
  if (mode === 'senior') routes.push('/librarian/workspace/tasks?lang=ru', '/librarian/reports?lang=ru');

  for (const route of routes) {
    const response = await page.goto(`${baseURL}${route}`, { waitUntil: 'domcontentloaded' });
    const status = response?.status() ?? 0;
    const geometry = await page.evaluate(() => ({
      client: document.documentElement.clientWidth,
      scroll: document.documentElement.scrollWidth,
    }));
    const text = await page.locator('body').innerText();
    routeResults.push({ route, status, overflow: geometry.scroll - geometry.client });
    assert(status === 200, `${route} returned ${status}`);
    assert(geometry.scroll <= geometry.client + 1, `${route} overflows by ${geometry.scroll - geometry.client}px`);
    assert(cleanText(text), `${route} exposes a technical label`);
  }

  if (mode !== 'librarian-parity') {
    await page.goto(`${baseURL}/librarian/copies/50881?lang=ru`, { waitUntil: 'domcontentloaded' });
    const copyText = await page.locator('body').innerText();
    for (const value of ['INV-955001', 'Современное академическое письмо', 'Научная библиотека', 'Учебный фонд', '3-2']) {
      assert(copyText.includes(value), `Copy detail is missing ${value}`);
    }

    await page.goto(`${baseURL}/librarian/data-quality/issues/1?lang=ru`, { waitUntil: 'domcontentloaded' });
    const issueText = await page.locator('body').innerText();
    assert(issueText.length > 100, 'Data Quality detail is empty');
    assert(cleanText(issueText), 'Data Quality detail exposes a technical label');
    const editLink = page.locator('a[href*="/librarian/catalog/"][href*="/edit"]').first();
    assert(await editLink.count() === 1, 'Data Quality detail has no record edit link');
    await editLink.click();
    await page.waitForLoadState('domcontentloaded');
    assert(page.url().includes('/librarian/catalog/'), 'Record correction flow did not open the catalog editor');
    assert((await page.locator('body').innerText()).includes('Сохранить и проверить'), 'Save and revalidate action is unavailable');
  }

  await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
  const sidebarLinks = await page.locator('aside nav a[href]').evaluateAll(links => [...new Set(links.map(link => link.getAttribute('href')).filter(Boolean))]);
  for (const href of sidebarLinks) {
    const response = await context.request.get(new URL(href, baseURL).toString(), { maxRedirects: 0 });
    assert(![403, 404, 500].includes(response.status()), `Visible sidebar link ${href} returned ${response.status()}`);
  }

  for (const denied of ['/admin', '/admin/users', '/admin/settings', '/librarian/executive/export/csv']) {
    const response = await context.request.get(`${baseURL}${denied}`, { maxRedirects: 0 });
    assert(response.status() === 403, `${denied} must return 403, received ${response.status()}`);
  }

  for (const locale of ['kk', 'ru', 'en']) {
    await page.goto(`${baseURL}/librarian?lang=${locale}`, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('html').getAttribute('lang') === locale, `${locale} did not become active`);
    const text = await page.locator('body').innerText();
    assert(cleanText(text), `${locale} exposes a technical label`);
  }

  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), '1920px dashboard overflow');
  await page.screenshot({ path: `${outputDir}/dashboard-desktop-ru.png`, fullPage: true });

  await page.setViewportSize({ width: 768, height: 1024 });
  await page.goto(`${baseURL}/librarian/data-quality?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Tablet Data Quality overflow');

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileRoute = mode === 'librarian-parity' ? '/librarian' : '/librarian/copies?search=INV-955001';
  await page.goto(`${baseURL}${mobileRoute}${mobileRoute.includes('?') ? '&' : '?'}lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Mobile workspace overflow');
  assert(await page.locator('[data-mobile-navigation]').isVisible(), 'Mobile navigation is unavailable');
  await page.screenshot({ path: `${outputDir}/workspace-mobile-ru.png`, fullPage: true });

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
  await Promise.all([
    page.waitForURL(url => url.pathname === '/login'),
    page.locator('#librarian-logout-btn').click(),
  ]);
  const protectedResponse = await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(page.url().includes('/login'), `Protected route remained accessible after logout (${protectedResponse?.status()})`);

  assert(errors.length === 0, `Console errors: ${JSON.stringify(errors)}`);
  assert(failedRequests.length === 0, `Failed requests: ${JSON.stringify(failedRequests)}`);

  writeFileSync(`${outputDir}/result.json`, JSON.stringify({
    mode,
    roleLabel: expectedRoleLabel,
    routes: routeResults,
    sidebarLinks: sidebarLinks.length,
    negativeRbac: 'pass',
    locales: ['kk', 'ru', 'en'],
    responsive: ['1920x1080', '1440x900', '768x1024', '390x844'],
    consoleErrors: 0,
    failedRequests: 0,
    logout: 'pass',
  }, null, 2));
  process.stdout.write(JSON.stringify({ status: 'PASS', mode, routes: routeResults.length, sidebarLinks: sidebarLinks.length }) + '\n');
} finally {
  credentials.password = '';
  input = '';
  await context.close();
  await browser.close();
}
