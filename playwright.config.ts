import { execSync } from 'node:child_process';
import { accessSync, constants, mkdirSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { defineConfig, devices } from '@playwright/test';

const hasSupportedPhp = (() => {
  try {
    execSync("php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'", { stdio: 'ignore' });

    return true;
  } catch {
    return false;
  }
})();

const useDockerServer = !process.env.CI && !hasSupportedPhp && !process.env.PLAYWRIGHT_DISABLE_DOCKER_FALLBACK;
const port = Number(process.env.PLAYWRIGHT_PORT || (useDockerServer ? 80 : 8000));
const baseURL = process.env.PLAYWRIGHT_BASE_URL || `http://127.0.0.1:${port}`;

function resolveWritableDir(preferred: string, fallbackName: string): string {
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

const htmlReportFolder = process.env.PLAYWRIGHT_REPORT_DIR || resolveWritableDir('playwright-report', 'html-report');
const artifactOutputDir = process.env.PLAYWRIGHT_ARTIFACT_DIR || resolveWritableDir('test-results/playwright-artifacts', 'artifacts');

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: {
    timeout: 10_000,
  },
  reporter: [['list'], ['html', { open: 'never', outputFolder: htmlReportFolder }]],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  outputDir: artifactOutputDir,
  webServer: {
    command: useDockerServer
      ? 'docker compose up -d postgres app'
      : `php artisan serve --host=127.0.0.1 --port=${port}`,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    env: useDockerServer
      ? {
          ...process.env,
        }
      : {
          ...process.env,
          APP_ENV: 'testing',
          APP_KEY: process.env.APP_KEY || 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=',
          APP_DEBUG: 'true',
          DB_CONNECTION: 'sqlite',
          DB_DATABASE: ':memory:',
          CACHE_STORE: 'array',
          SESSION_DRIVER: 'array',
          QUEUE_CONNECTION: 'sync',
        },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
