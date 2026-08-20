import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

import { expect, type Page } from '@playwright/test';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const harnessPath = path.join(projectRoot, 'tests/e2e/support/e2e-db-harness.php');
const demoPassword = 'DemoAccess2026!';

export const verticalMutationsEnabled = process.env.PLAYWRIGHT_E2E_MUTATIONS === '1';
export const kazakhLetters = /[ӘәҒғҚқҢңӨөҰұҮүҺһІі]/u;

export type RuntimeWatch = {
  assertClean: () => void;
};

export type ParsedReport = {
  magic: string;
  text: string;
  rows?: unknown[][];
  entries?: string[];
};

export function verticalEnvironment(): NodeJS.ProcessEnv {
  const database = process.env.PLAYWRIGHT_E2E_DATABASE ?? '';
  const port = String(Number(process.env.PLAYWRIGHT_VERTICAL_PORT ?? 8017));
  const databaseHost = process.env.PLAYWRIGHT_E2E_DB_HOST ?? '127.0.0.1';
  const databasePort = String(Number(process.env.PLAYWRIGHT_E2E_DB_PORT ?? process.env.DB_PORT ?? 5432));
  const databaseUsername = process.env.PLAYWRIGHT_E2E_DB_USERNAME ?? process.env.DB_USERNAME ?? '';
  const databasePassword = process.env.PLAYWRIGHT_E2E_DB_PASSWORD ?? process.env.DB_PASSWORD ?? '';
  const cacheStem = database.replace(/[^A-Za-z0-9_-]/g, '_') || 'invalid';
  const runId = process.env.PLAYWRIGHT_VERTICAL_RUN_ID ?? 'missing-run-id';
  const storagePath = path.join('/tmp/kazutb-library-playwright', `${cacheStem}-${port}-${runId}`);

  return {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_URL: `http://127.0.0.1:${port}`,
    APP_KEY: process.env.APP_KEY ?? 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=',
    APP_LOCALE: 'kk',
    APP_FALLBACK_LOCALE: 'kk',
    APP_DEMO_LOGIN_ENABLED: 'true',
    APP_DEMO_LOGIN_PASSWORD: demoPassword,
    DB_CONNECTION: 'pgsql',
    DB_HOST: databaseHost,
    DB_PORT: databasePort,
    DB_DATABASE: database,
    DB_USERNAME: databaseUsername,
    DB_PASSWORD: databasePassword,
    FILESYSTEM_DISK: 'local',
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    SESSION_COOKIE: `kazutb-e2e-${cacheStem}-${port}-${runId}`,
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    APP_CONFIG_CACHE: process.env.APP_CONFIG_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-config.php`,
    APP_ROUTES_CACHE: process.env.APP_ROUTES_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-routes.php`,
    APP_EVENTS_CACHE: process.env.APP_EVENTS_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-events.php`,
    LARAVEL_STORAGE_PATH: storagePath,
    LOGIN_RATE_LIMIT: '100',
    EXTERNAL_RESOURCE_PUBLIC_RATE_LIMIT: '600',
  };
}

export function requireVerticalSafety(): Record<string, unknown> {
  if (!verticalMutationsEnabled) {
    throw new Error('Set PLAYWRIGHT_E2E_MUTATIONS=1 to opt in to mutating vertical E2E tests.');
  }

  const database = process.env.PLAYWRIGHT_E2E_DATABASE ?? '';
  if (!/^[A-Za-z_][A-Za-z0-9_]*_test$/i.test(database)) {
    throw new Error('PLAYWRIGHT_E2E_DATABASE must be a simple PostgreSQL identifier ending in _test.');
  }

  const port = Number(process.env.PLAYWRIGHT_VERTICAL_PORT ?? 8017);
  if (!Number.isInteger(port) || port < 1024 || port > 65535) {
    throw new Error('PLAYWRIGHT_VERTICAL_PORT must be an integer between 1024 and 65535.');
  }

  const configuredBase = process.env.PLAYWRIGHT_BASE_URL;
  if (configuredBase && configuredBase !== `http://127.0.0.1:${port}`) {
    throw new Error('Vertical E2E only accepts its dedicated loopback base URL.');
  }

  if (!/^[A-Za-z0-9_-]+$/.test(process.env.PLAYWRIGHT_VERTICAL_RUN_ID ?? '')) {
    throw new Error('Vertical E2E requires its config-generated safe run id.');
  }

  const databaseHost = process.env.PLAYWRIGHT_E2E_DB_HOST ?? '127.0.0.1';
  const databasePort = Number(process.env.PLAYWRIGHT_E2E_DB_PORT ?? process.env.DB_PORT ?? 5432);
  const databaseUsername = process.env.PLAYWRIGHT_E2E_DB_USERNAME ?? process.env.DB_USERNAME ?? '';
  const databasePassword = process.env.PLAYWRIGHT_E2E_DB_PASSWORD ?? process.env.DB_PASSWORD ?? '';
  if (!['127.0.0.1', '::1', 'localhost'].includes(databaseHost)
    || !Number.isInteger(databasePort)
    || databasePort < 1024
    || databasePort > 65535
    || databaseUsername.trim() === ''
    || databasePassword === '') {
    throw new Error('Vertical E2E requires explicit loopback PostgreSQL credentials.');
  }

  return runHarness('assert-runtime');
}

export function runHarness(action: string, ...args: Array<string | number>): Record<string, any> {
  const stdout = execFileSync(
    'php',
    [harnessPath, action, ...args.map(String)],
    {
      cwd: projectRoot,
      env: verticalEnvironment(),
      encoding: 'utf8',
      maxBuffer: 10 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'pipe'],
    },
  );

  return JSON.parse(stdout.trim()) as Record<string, any>;
}

export function runSafeArtisan(command: 'library:digital-services-sweep' | 'library:external-resources:notifications'): string {
  requireVerticalSafety();

  return execFileSync('php', ['artisan', command, '--no-interaction'], {
    cwd: projectRoot,
    env: verticalEnvironment(),
    encoding: 'utf8',
    maxBuffer: 10 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

export async function loginAs(page: Page, login: string, locale?: 'kk' | 'ru' | 'en'): Promise<void> {
  await page.goto('/login');
  await expect(page.locator('#login-form')).toBeVisible();
  await page.locator('#login-form input[name="login"]').fill(login);
  await page.locator('#login-form input[name="password"]').fill(demoPassword);
  await Promise.all([
    page.waitForURL(/\/(?:dashboard|librarian|admin)(?:[/?#]|$)/),
    page.locator('#login-form button[type="submit"]').click(),
  ]);

  if (locale) {
    const localeForm = page.locator(`[data-locale-switcher] form:has(input[name="locale"][value="${locale}"])`).first();
    await expect(localeForm).toBeAttached();
    await localeForm.locator('xpath=ancestor::details[1]/summary').click();
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      localeForm.locator('button[type="submit"]').click(),
    ]);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
  }
}

export async function logout(page: Page, shell: '/admin' | '/librarian' | '/dashboard'): Promise<void> {
  await page.goto(shell);

  const librarianLogout = page.locator('#librarian-logout-btn');
  if (await librarianLogout.count()) {
    const [logoutResponse] = await Promise.all([
      page.waitForResponse(response => response.url().endsWith('/api/v1/logout') && response.request().method() === 'POST'),
      librarianLogout.click(),
    ]);
    expect(logoutResponse.ok(), 'The librarian shell must complete its server-side logout request.').toBe(true);
    await page.waitForURL(/\/login(?:[/?#]|$)/);
    return;
  }

  const formLogout = page.locator('form[action$="/logout"] button[type="submit"]').first();
  await expect(formLogout).toBeVisible();
  await Promise.all([
    page.waitForURL(/\/login(?:[/?#]|$)/),
    formLogout.click(),
  ]);
}

export function watchRuntime(page: Page, baseURL?: string): RuntimeWatch {
  const serverErrors: string[] = [];
  const runtimeErrors: string[] = [];
  const origin = new URL(baseURL ?? 'http://127.0.0.1').origin;

  page.on('response', response => {
    if (response.url().startsWith(origin) && response.status() >= 500) {
      serverErrors.push(`${response.status()} ${response.url()}`);
    }
  });
  page.on('console', message => {
    if (message.type() === 'error' && /uncaught|exception|referenceerror|typeerror/i.test(message.text())) {
      runtimeErrors.push(message.text());
    }
  });
  page.on('pageerror', error => runtimeErrors.push(error.message));

  return {
    assertClean: () => {
      expect(serverErrors, 'No application response may be HTTP 5xx').toEqual([]);
      expect(runtimeErrors, 'No uncaught browser runtime error may occur').toEqual([]);
    },
  };
}

export function isoDateFromToday(dayOffset: number): string {
  const date = new Date();
  date.setUTCHours(12, 0, 0, 0);
  date.setUTCDate(date.getUTCDate() + dayOffset);

  return date.toISOString().slice(0, 10);
}

export function displayDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-');

  return `${day}.${month}.${year}`;
}

export function idFromEditUrl(url: string, segment: string): number {
  const match = new URL(url).pathname.match(new RegExp(`/${segment}/(\\d+)/edit$`));
  if (!match) throw new Error(`Cannot extract numeric id from ${url}`);

  return Number(match[1]);
}

/** A self-contained, structurally valid PDF fixture with a distinct checksum per label. */
export function makePdfFixture(label: string): Buffer {
  const escaped = label.replace(/[()\\]/g, match => `\\${match}`).replace(/[^\x20-\x7e]/g, '?');
  const stream = `BT /F1 12 Tf 72 720 Td (${escaped}) Tj ET\n`;
  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    `<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}endstream`,
  ];
  const chunks: Buffer[] = [Buffer.from('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n', 'binary')];
  const offsets = [0];
  let offset = chunks[0].length;

  objects.forEach((object, index) => {
    offsets.push(offset);
    const chunk = Buffer.from(`${index + 1} 0 obj\n${object}\nendobj\n`, 'binary');
    chunks.push(chunk);
    offset += chunk.length;
  });

  const xrefOffset = offset;
  const xref = [
    'xref',
    `0 ${objects.length + 1}`,
    '0000000000 65535 f ',
    ...offsets.slice(1).map(value => `${String(value).padStart(10, '0')} 00000 n `),
    'trailer',
    `<< /Size ${objects.length + 1} /Root 1 0 R >>`,
    'startxref',
    String(xrefOffset),
    '%%EOF',
    '',
  ].join('\n');
  chunks.push(Buffer.from(xref, 'binary'));

  return Buffer.concat(chunks);
}

export function sha256(buffer: Buffer | Uint8Array): string {
  return createHash('sha256').update(buffer).digest('hex');
}

export async function parseReportFile(format: 'pdf' | 'xlsx' | 'csv' | 'docx', filePath: string): Promise<ParsedReport> {
  const bytes = readFileSync(filePath);
  const magic = bytes.subarray(0, 8).toString('binary');

  if (format === 'pdf') {
    const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const document = await pdfjs.getDocument({ data: new Uint8Array(bytes) }).promise;
    const pageTexts: string[] = [];
    for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber += 1) {
      const pdfPage = await document.getPage(pageNumber);
      const content = await pdfPage.getTextContent();
      pageTexts.push(content.items.map(item => ('str' in item ? item.str : '')).join(' '));
    }

    return { magic, text: pageTexts.join('\n') };
  }

  if (format === 'csv') {
    const parsed = parseWithPhp(filePath, 'csv');
    return { magic, text: parsed.text, rows: parsed.rows };
  }

  const parsed = parseWithPhp(filePath, format);
  return { magic, text: parsed.text, entries: parsed.entries };
}

function parseWithPhp(filePath: string, format: 'csv' | 'xlsx' | 'docx'): Record<string, any> {
  const script = format === 'csv'
    ? String.raw`
$handle = fopen($argv[1], 'rb');
if ($handle === false) { fwrite(STDERR, "open failed\n"); exit(2); }
$rows = [];
while (($row = fgetcsv($handle)) !== false) { $rows[] = $row; }
fclose($handle);
$flat = [];
foreach ($rows as $row) { foreach ($row as $cell) { $flat[] = (string) $cell; } }
echo json_encode(['rows' => $rows, 'text' => implode("\n", $flat)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
`
    : String.raw`
$format = $argv[2];
$zip = new ZipArchive();
if ($zip->open($argv[1]) !== true) { fwrite(STDERR, "zip open failed\n"); exit(2); }
$entries = [];
for ($i = 0; $i < $zip->numFiles; $i++) { $entries[] = $zip->getNameIndex($i); }
$required = $format === 'xlsx'
    ? ['[Content_Types].xml', 'xl/workbook.xml', 'xl/worksheets/sheet1.xml']
    : ['[Content_Types].xml', 'word/document.xml'];
foreach ($required as $name) {
    if ($zip->locateName($name) === false) { fwrite(STDERR, "missing {$name}\n"); exit(3); }
}
$prefix = $format === 'xlsx' ? 'xl/' : 'word/';
$text = [];
foreach ($entries as $name) {
    if (!str_starts_with($name, $prefix) || !str_ends_with($name, '.xml')) { continue; }
    $xml = $zip->getFromName($name);
    if (!is_string($xml)) { continue; }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    if ($document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        $text[] = preg_replace('/\s+/u', ' ', $document->textContent) ?? '';
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
}
$zip->close();
echo json_encode(['entries' => $entries, 'text' => implode("\n", $text)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
`;

  const stdout = execFileSync(
    'php',
    format === 'csv' ? ['-r', script, filePath] : ['-r', script, filePath, format],
    { cwd: projectRoot, encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 },
  );

  return JSON.parse(stdout) as Record<string, any>;
}
