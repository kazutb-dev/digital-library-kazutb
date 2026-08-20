import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createInterface } from 'node:readline';

const [readerId, outputDir = 'test-results/circulation-isbn-live'] = process.argv.slice(2);
if (!/^\d+$/.test(readerId ?? '')) throw new Error('A numeric reader id is required');

const input = createInterface({ input: process.stdin, terminal: false });
const iterator = input[Symbol.asyncIterator]();
const line = await iterator.next();
input.close();
const credentials = JSON.parse((line.value ?? '').trim());
if (typeof credentials.login !== 'string' || typeof credentials.password !== 'string') {
  throw new Error('Credentials must be supplied through stdin');
}

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://elibrary.kaztbu.edu.kz';
mkdirSync(outputDir, { recursive: true });
const consoleErrors = [];
const failedRequests = [];
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => consoleErrors.push(error.message));
page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));
const assert = (condition, message) => { if (!condition) throw new Error(message); };

try {
  await page.goto(`${baseURL}/login?lang=ru`, { waitUntil: 'domcontentloaded' });
  await page.locator('#login').fill(credentials.login);
  await page.locator('#password').fill(credentials.password);
  await page.locator('#submit-btn').click();
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(500);
  if (new URL(page.url()).pathname !== '/librarian') {
    const message = await page.locator('#form-message').innerText().catch(() => '');
    throw new Error(`AD login did not reach the workspace: ${message || page.url()}`);
  }
  credentials.password = '';

  await page.goto(`${baseURL}/librarian/circulation/issue?reader=${readerId}&lang=ru`, { waitUntil: 'domcontentloaded' });
  await page.locator('#copy-code').fill('9965-17-469-5');
  await page.locator('#copy-preview').getByText('Издание найдено по ISBN').waitFor();
  const preview = await page.locator('#copy-preview').innerText();
  assert(preview.includes('Русско-казахский словарь'), 'The ISBN edition title is missing');
  assert(preview.includes('Доступно экземпляров: 701'), 'The live available-copy count is missing');
  assert(!preview.includes('Экземпляр с таким кодом не найден'), 'The old misleading error is still visible');
  assert(await page.locator('#copy-preview a[href*="/librarian/copies"]').count() === 1, 'Edition copies link is missing');
  assert(await page.locator('#confirm-issue').isDisabled(), 'Ambiguous ISBN must not enable checkout');
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Desktop overflow');
  await page.screenshot({ path: `${outputDir}/isbn-found-desktop-ru.png`, fullPage: true });

  await page.setViewportSize({ width: 390, height: 844 });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Mobile overflow');
  await page.screenshot({ path: `${outputDir}/isbn-found-mobile-ru.png`, fullPage: true });

  assert(consoleErrors.length === 0, `Console errors: ${JSON.stringify(consoleErrors)}`);
  assert(failedRequests.length === 0, `Failed requests: ${JSON.stringify(failedRequests)}`);
  writeFileSync(`${outputDir}/result.json`, JSON.stringify({
    route: '/librarian/circulation/issue',
    isbn: '9965-17-469-5',
    title: 'Русско-казахский словарь',
    availableCopies: 701,
    desktop: 'pass',
    mobile: 'pass',
    consoleErrors: 0,
    failedRequests: 0,
  }, null, 2));
  process.stdout.write('CIRCULATION ISBN LIVE GATE: PASS\n');
} finally {
  credentials.password = '';
  await context.close();
  await browser.close();
}
