import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createInterface } from 'node:readline';

const [mode, expectedRoleLabel, outputRoot = 'test-results/staff-profile-live'] = process.argv.slice(2);
if (!['cataloguer', 'librarian', 'senior', 'director'].includes(mode ?? '')) throw new Error('Unsupported staff mode');

const reader = createInterface({ input: process.stdin, terminal: false });
const iterator = reader[Symbol.asyncIterator]();
const line = await iterator.next();
reader.close();
const credentials = JSON.parse((line.value ?? '').trim());
if (typeof credentials.login !== 'string' || typeof credentials.password !== 'string') throw new Error('Credentials must be provided through stdin');

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://elibrary.kaztbu.edu.kz';
const outputDir = `${outputRoot}/${mode}`;
mkdirSync(outputDir, { recursive: true });
const consoleErrors = [];
const failedRequests = [];
const results = [];
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const hasNoTechnicalCopy = text => !/(?:objectGUID|\bLDAP\b|senior_librarian|lead_librarian|librarian\.staff_profile\.|brand\.workspace\.)/i.test(text);

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => consoleErrors.push(error.message));
page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));

try {
  await page.goto(`${baseURL}/login?lang=ru`, { waitUntil: 'domcontentloaded' });
  await page.locator('#login').fill(credentials.login);
  await page.locator('#password').fill(credentials.password);
  await page.locator('#submit-btn').click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(400);
  credentials.password = '';
  if (new URL(page.url()).pathname !== '/librarian') {
    const message = (await page.locator('#form-message').innerText()).trim();
    throw new Error(`AD login did not reach the staff workspace: ${message || 'login page remained active'}`);
  }
  assert((await page.locator('[data-workspace-role]').innerText()).includes(expectedRoleLabel), 'Wrong localized role label');

  const sidebarHrefs = await page.locator('aside nav a[href]').evaluateAll(nodes => nodes.map(node => new URL(node.href).pathname));
  assert(sidebarHrefs.includes('/librarian/profile') === false, 'Profile belongs to the account area, not the primary navigation');

  const routes = mode === 'cataloguer'
    ? ['/librarian', '/librarian/catalog', '/librarian/copies', '/librarian/workspace/search?q=INV-955001', '/librarian/data-quality', '/librarian/profile']
    : mode === 'director'
      ? ['/librarian', '/librarian/data-quality', '/librarian/reports', '/librarian/messages', '/librarian/repository', '/librarian/profile']
      : ['/librarian', '/librarian/profile'];
  for (const route of routes) {
    const response = await page.goto(`${baseURL}${route}${route.includes('?') ? '&' : '?'}lang=ru`, { waitUntil: 'domcontentloaded' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    const body = await page.locator('body').innerText();
    assert(response?.status() === 200, `${route} returned ${response?.status()}`);
    assert(overflow <= 1, `${route} overflows by ${overflow}px`);
    assert(hasNoTechnicalCopy(body), `${route} exposes technical copy`);
    results.push({ route, status: response.status(), overflow });
  }

  await page.goto(`${baseURL}/librarian/profile?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.locator('[data-staff-profile]').count() === 1, 'Staff profile is missing');
  assert((await page.locator('[data-profile-role]').innerText()).includes(expectedRoleLabel), 'Profile role label is wrong');
  assert(await page.locator('input[type="password"]').count() === 0, 'Local password control is exposed');
  assert(await page.locator('input[name="name"], input[name="email"], input[name="ad_login"]').count() === 0, 'Corporate identity is locally editable');
  await page.screenshot({ path: `${outputDir}/profile-desktop-ru.png`, fullPage: true });

  const accountProfileLink = page.locator(`aside a[href="${baseURL}/librarian/profile"], aside a[href="/librarian/profile"]`).first();
  assert(await accountProfileLink.count() === 1, 'Sidebar account area has no profile link');
  const avatarLink = page.locator(`header a[href="${baseURL}/librarian/profile"], header a[href="/librarian/profile"]`).first();
  assert(await avatarLink.count() === 1, 'Header avatar is not linked to the profile');

  if (mode === 'cataloguer') {
    await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
    await page.screenshot({ path: `${outputDir}/dashboard-desktop-ru.png`, fullPage: true });
    const visibleHrefs = await page.locator('aside a[href]').evaluateAll(nodes => nodes.map(node => new URL(node.href).pathname));
    for (const allowed of ['/librarian/catalog', '/librarian/copies', '/librarian/workspace/search', '/librarian/data-quality', '/librarian/profile']) {
      assert(visibleHrefs.includes(allowed), `Missing cataloguer navigation ${allowed}`);
    }
    for (const forbidden of ['/librarian/circulation', '/librarian/readers', '/librarian/visits', '/librarian/reservations']) {
      assert(!visibleHrefs.includes(forbidden), `Forbidden cataloguer navigation is visible: ${forbidden}`);
    }
    for (const denied of ['/admin', '/admin/users', '/librarian/circulation', '/librarian/readers', '/librarian/executive/export/csv']) {
      const response = await context.request.get(`${baseURL}${denied}`, { maxRedirects: 0 });
      assert(response.status() === 403, `${denied} must be 403, received ${response.status()}`);
    }
    const readerDashboard = await context.request.get(`${baseURL}/dashboard`, { maxRedirects: 0 });
    assert(readerDashboard.status() === 302 && new URL(readerDashboard.headers().location, baseURL).pathname === '/librarian', 'Staff reader-dashboard redirect is wrong');
  }

  if (mode === 'director') {
    await page.goto(`${baseURL}/librarian?lang=ru`, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('[data-section="director-executive-dashboard"]').count() === 1, 'Director executive dashboard is missing');
    assert(await page.locator('a[href^="/admin"], a[href^="' + baseURL + '/admin"]').count() === 0, 'Technical admin navigation is exposed to the director');
    await page.screenshot({ path: `${outputDir}/dashboard-desktop-ru.png`, fullPage: true });

    for (const denied of ['/librarian/catalog', '/librarian/copies', '/librarian/workspace/search', '/librarian/circulation', '/librarian/readers', '/librarian/visits', '/librarian/reservations', '/admin', '/admin/users', '/admin/system']) {
      const response = await context.request.get(`${baseURL}${denied}`, { maxRedirects: 0 });
      assert(response.status() === 403, `${denied} must be 403 for the director, received ${response.status()}`);
    }
  }

  for (const locale of ['ru', 'kk', 'en']) {
    await page.goto(`${baseURL}/librarian/profile?lang=${locale}`, { waitUntil: 'domcontentloaded' });
    assert(await page.locator('html').getAttribute('lang') === locale, `${locale} locale is not active`);
    assert(hasNoTechnicalCopy(await page.locator('body').innerText()), `${locale} profile exposes technical copy`);
  }

  await page.setViewportSize({ width: 768, height: 1024 });
  await page.goto(`${baseURL}/librarian/profile?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Tablet profile overflow');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${baseURL}/librarian/profile?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Mobile profile overflow');
  assert(await page.locator('[data-mobile-navigation]').isVisible(), 'Mobile navigation is unavailable');
  await page.screenshot({ path: `${outputDir}/profile-mobile-ru.png`, fullPage: true });

  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`${baseURL}/librarian/profile?lang=ru`, { waitUntil: 'domcontentloaded' });
  await Promise.all([page.waitForURL(url => url.pathname === '/login'), page.locator('#librarian-logout-btn').click()]);

  assert(consoleErrors.length === 0, `Console errors: ${JSON.stringify(consoleErrors)}`);
  assert(failedRequests.length === 0, `Failed requests: ${JSON.stringify(failedRequests)}`);
  writeFileSync(`${outputDir}/result.json`, JSON.stringify({ mode, role: expectedRoleLabel, routes: results, locales: ['ru', 'kk', 'en'], responsive: ['1440x900', '768x1024', '390x844'], consoleErrors: 0, failedRequests: 0, logout: 'pass' }, null, 2));
  process.stdout.write(JSON.stringify({ status: 'PASS', mode, routes: results.length }) + '\n');
} finally {
  credentials.password = '';
  await context.close();
  await browser.close();
}
