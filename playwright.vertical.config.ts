import { execFileSync } from 'node:child_process';
import { accessSync, constants, mkdirSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { defineConfig, devices } from '@playwright/test';

if (process.env.PLAYWRIGHT_E2E_MUTATIONS !== '1') {
  throw new Error('Vertical E2E is mutating: explicitly set PLAYWRIGHT_E2E_MUTATIONS=1.');
}

const database = process.env.PLAYWRIGHT_E2E_DATABASE ?? '';
if (!/^[A-Za-z_][A-Za-z0-9_]*_test$/i.test(database)) {
  throw new Error('PLAYWRIGHT_E2E_DATABASE must be a PostgreSQL identifier ending in _test.');
}

const databaseHost = process.env.PLAYWRIGHT_E2E_DB_HOST ?? '127.0.0.1';
if (!['127.0.0.1', '::1', 'localhost'].includes(databaseHost)) {
  throw new Error('PLAYWRIGHT_E2E_DB_HOST must be a loopback host.');
}
const databasePort = Number(process.env.PLAYWRIGHT_E2E_DB_PORT ?? process.env.DB_PORT ?? 5432);
if (!Number.isInteger(databasePort) || databasePort < 1024 || databasePort > 65535) {
  throw new Error('PLAYWRIGHT_E2E_DB_PORT must be between 1024 and 65535.');
}
const databaseUsername = process.env.PLAYWRIGHT_E2E_DB_USERNAME ?? process.env.DB_USERNAME ?? '';
const databasePassword = process.env.PLAYWRIGHT_E2E_DB_PASSWORD ?? process.env.DB_PASSWORD ?? '';
if (databaseUsername.trim() === '' || databasePassword === '') {
  throw new Error('Vertical E2E requires PLAYWRIGHT_E2E_DB_USERNAME and PLAYWRIGHT_E2E_DB_PASSWORD.');
}

const port = Number(process.env.PLAYWRIGHT_VERTICAL_PORT ?? 8017);
if (!Number.isInteger(port) || port < 1024 || port > 65535) {
  throw new Error('PLAYWRIGHT_VERTICAL_PORT must be between 1024 and 65535.');
}

const baseURL = `http://127.0.0.1:${port}`;
if (process.env.PLAYWRIGHT_BASE_URL && process.env.PLAYWRIGHT_BASE_URL !== baseURL) {
  throw new Error(`Vertical E2E accepts only ${baseURL}.`);
}

const browserWsEndpoint = process.env.PLAYWRIGHT_BROWSER_WS_ENDPOINT;
if (browserWsEndpoint !== undefined && !/^ws:\/\/(?:127\.0\.0\.1|localhost):[1-9][0-9]{3,4}\/$/.test(browserWsEndpoint)) {
  throw new Error('PLAYWRIGHT_BROWSER_WS_ENDPOINT must be a loopback WebSocket endpoint.');
}

const cacheStem = database.replace(/[^A-Za-z0-9_-]/g, '_');
const inheritedRunId = process.env.PLAYWRIGHT_VERTICAL_RUN_ID;
if (inheritedRunId !== undefined && !/^[A-Za-z0-9_-]+$/.test(inheritedRunId)) {
  throw new Error('PLAYWRIGHT_VERTICAL_RUN_ID contains unsafe characters.');
}
const runId = inheritedRunId ?? `${Date.now()}-${process.pid}`;
process.env.PLAYWRIGHT_VERTICAL_RUN_ID = runId;
const storagePath = path.join('/tmp', 'kazutb-library-playwright', `${cacheStem}-${port}-${runId}`);
for (const directory of [
  'app/private',
  'app/public',
  'framework/cache/data',
  'framework/sessions',
  'framework/views',
  'logs',
]) {
  mkdirSync(path.join(storagePath, directory), { recursive: true });
}
const serverEnv = {
  ...process.env,
  APP_ENV: 'testing',
  APP_DEBUG: 'true',
  APP_URL: baseURL,
  APP_KEY: process.env.APP_KEY ?? 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=',
  APP_LOCALE: 'kk',
  APP_FALLBACK_LOCALE: 'kk',
  APP_DEMO_LOGIN_ENABLED: 'true',
  APP_DEMO_LOGIN_PASSWORD: 'DemoAccess2026!',
  DB_CONNECTION: 'pgsql',
  DB_HOST: databaseHost,
  DB_PORT: String(databasePort),
  DB_DATABASE: database,
  DB_USERNAME: databaseUsername,
  DB_PASSWORD: databasePassword,
  FILESYSTEM_DISK: 'local',
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'file',
  SESSION_COOKIE: `kazutb-e2e-${cacheStem}-${port}-${runId}`,
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'log',
  LOGIN_RATE_LIMIT: '100',
  EXTERNAL_RESOURCE_PUBLIC_RATE_LIMIT: '600',
  APP_CONFIG_CACHE: process.env.APP_CONFIG_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-config.php`,
  APP_ROUTES_CACHE: process.env.APP_ROUTES_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-routes.php`,
  APP_EVENTS_CACHE: process.env.APP_EVENTS_CACHE ?? `/tmp/kazutb-e2e-${cacheStem}-${port}-${runId}-events.php`,
  LARAVEL_STORAGE_PATH: storagePath,
  PLAYWRIGHT_VERTICAL_RUN_ID: runId,
};

execFileSync('php', ['tests/e2e/support/e2e-db-harness.php', 'assert-runtime'], {
  env: serverEnv,
  stdio: 'ignore',
});

function writableDir(preferred: string, fallbackName: string): string {
  try {
    mkdirSync(preferred, { recursive: true });
    accessSync(preferred, constants.W_OK);
    return preferred;
  } catch {
    const fallback = path.join(os.tmpdir(), 'digital-library-playwright', fallbackName);
    mkdirSync(fallback, { recursive: true });
    return fallback;
  }
}

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: [
    'external-resource-vertical.spec.ts',
    'repository-vertical.spec.ts',
    'official-report-vertical.spec.ts',
    'role-runtime-smoke.spec.ts',
    'integration-hub-vertical.spec.ts',
    'multilingual-executive-vertical.spec.ts',
  ],
  fullyParallel: false,
  workers: 1,
  timeout: 180_000,
  expect: { timeout: 15_000 },
  globalTeardown: './tests/e2e/vertical-global-teardown.ts',
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: writableDir('playwright-report/vertical', 'vertical-html') }],
  ],
  outputDir: writableDir('test-results/playwright-vertical', 'vertical-artifacts'),
  use: {
    ...devices['Desktop Chrome'],
    baseURL,
    connectOptions: browserWsEndpoint ? { wsEndpoint: browserWsEndpoint } : undefined,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: {
    command: `php artisan serve --host=127.0.0.1 --port=${port}`,
    url: `${baseURL}/up`,
    reuseExistingServer: false,
    timeout: 120_000,
    env: serverEnv,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
