import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { createInterface } from 'node:readline';

const [readerId, outputDir = 'test-results/barcode-marking-live'] = process.argv.slice(2);
if (!/^\d+$/.test(readerId ?? '')) throw new Error('A numeric reader id is required');
const input = createInterface({ input: process.stdin, terminal: false });
const iterator = input[Symbol.asyncIterator]();
const line = await iterator.next(); input.close();
const credentials = JSON.parse((line.value ?? '').trim());
if (typeof credentials.login !== 'string' || typeof credentials.password !== 'string') throw new Error('Credentials must be supplied through stdin');

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://10.0.1.17';
mkdirSync(outputDir, { recursive: true });
const consoleErrors = []; const failedRequests = [];
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => consoleErrors.push(error.message));
page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`));

try {
  await page.goto(`${baseURL}/login?lang=ru`, { waitUntil: 'domcontentloaded' });
  await page.locator('#login').fill(credentials.login); await page.locator('#password').fill(credentials.password);
  await page.locator('#submit-btn').click(); await page.waitForLoadState('domcontentloaded'); await page.waitForTimeout(400);
  assert(new URL(page.url()).pathname === '/librarian', `AD login did not reach staff workspace: ${page.url()}`);
  credentials.password = '';

  const copiesResponse = await page.goto(`${baseURL}/librarian/copies?barcode_status=without&lang=ru`, { waitUntil: 'domcontentloaded' });
  const indexText = await page.locator('body').innerText();
  assert(copiesResponse?.status() === 200 && new URL(page.url()).pathname === '/librarian/copies', `Copies route failed: ${copiesResponse?.status()} ${page.url()}`);
  assert(indexText.toLowerCase().includes('маркировка фонда'), `Marking progress is absent: ${indexText.slice(0, 240)}`);
  assert(indexText.toLowerCase().includes('без штрихкода'), 'Barcode filter is absent');
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Copies page overflows at desktop');
  const selector = page.locator('.barcode-copy-selector:not([disabled])').first();
  assert(await selector.count() === 1, 'No eligible copy is available for read-only batch preview');
  const copyId = await selector.getAttribute('value');
  await selector.check();
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#barcode-batch-submit').click()]);
  assert((await page.locator('body').innerText()).includes('Проверка партии'), 'Batch preview did not open');
  assert((await page.locator('body').innerText()).includes('Ни один новый экземпляр не создаётся'), 'Safety explanation is absent');
  await page.screenshot({ path: `${outputDir}/batch-preview-desktop-ru.png`, fullPage: true });

  await page.goto(`${baseURL}/librarian/copies/${copyId}?lang=ru`, { waitUntil: 'domcontentloaded' });
  const unmarkedText = await page.locator('body').innerText();
  assert(unmarkedText.toLowerCase().includes('маркировка экземпляра') && unmarkedText.toLowerCase().includes('не маркирован'), 'Unmarked copy workflow is absent');
  assert(await page.locator(`form[action$="/copies/${copyId}/barcode"]`).count() === 1, 'Controlled assignment form is absent');

  await page.goto(`${baseURL}/librarian/copies/50881?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert((await page.locator('body').innerText()).includes('KUTB00955001'), 'Existing barcode is absent from copy detail');
  const labelLink = page.locator('a[href$="/librarian/copies/50881/label"]').first();
  assert(await labelLink.count() === 1, 'Print label link is absent');
  await page.goto(`${baseURL}/librarian/copies/50881/label?lang=ru`, { waitUntil: 'domcontentloaded' });
  const labelText = await page.locator('body').innerText();
  assert(labelText.includes('KUTB00955001') && labelText.includes('INV-955001'), 'Label lacks barcode or inventory number');
  assert(await page.locator('svg').count() >= 1, 'Code 128 SVG is absent');
  assert(!labelText.includes('Современное академическое письмо'), 'Full title leaked onto the compact label');

  await page.goto(`${baseURL}/librarian/circulation/issue?reader=${readerId}&lang=ru`, { waitUntil: 'domcontentloaded' });
  const code = page.locator('#copy-code');
  await code.fill('9965-17-469-5'); await code.press('Enter');
  await page.locator('#copy-preview').getByText('Издание найдено по ISBN').waitFor();
  assert(await page.locator('#confirm-issue').isDisabled(), 'ISBN enabled arbitrary issuance');
  await code.fill('INV-955001'); await code.press('Enter');
  await page.locator('#copy-preview').getByText('INV-955001').waitFor();
  assert(!await page.locator('#confirm-issue').isDisabled(), 'Inventory number did not resolve a physical copy');
  await code.fill('KUTB00955001'); await code.press('Enter');
  await page.locator('#copy-preview').getByText('KUTB00955001').waitFor();
  assert(!await page.locator('#confirm-issue').isDisabled(), 'Barcode did not resolve a physical copy');
  await page.screenshot({ path: `${outputDir}/circulation-barcode-desktop-ru.png`, fullPage: true });

  for (const locale of ['ru', 'kk', 'en']) {
    const response = await page.goto(`${baseURL}/librarian/copies/${copyId}?lang=${locale}`, { waitUntil: 'domcontentloaded' });
    assert(response?.status() === 200 && await page.locator('html').getAttribute('lang') === locale, `${locale} copy detail failed`);
    assert(!(await page.locator('body').innerText()).includes('librarian.copies.marking'), `${locale} exposes a raw translation key`);
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${baseURL}/librarian/copies?barcode_status=without&lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Mobile copies page overflows');
  await page.screenshot({ path: `${outputDir}/copies-mobile-ru.png`, fullPage: true });
  await page.goto(`${baseURL}/librarian/copies/${copyId}?lang=ru`, { waitUntil: 'domcontentloaded' });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'Mobile copy detail overflows');
  await page.screenshot({ path: `${outputDir}/copy-marking-mobile-ru.png`, fullPage: true });

  assert(consoleErrors.length === 0, `Console errors: ${JSON.stringify(consoleErrors)}`);
  assert(failedRequests.length === 0, `Failed requests: ${JSON.stringify(failedRequests)}`);
  writeFileSync(`${outputDir}/result.json`, JSON.stringify({ status: 'PASS', copyId, checks: ['single-ui','batch-preview','print','isbn','inventory','barcode','ru','kk','en','mobile'], consoleErrors: 0, failedRequests: 0 }, null, 2));
  process.stdout.write('BARCODE MARKING LIVE GATE: PASS\n');
} finally { credentials.password = ''; await context.close(); await browser.close(); }
