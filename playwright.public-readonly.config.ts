import os from 'node:os';
import path from 'node:path';

import { defineConfig, devices } from '@playwright/test';

const configuredBaseURL = process.env.PLAYWRIGHT_PUBLIC_BASE_URL ?? 'http://127.0.0.1';

function publicBaseURL(value: string): string {
  let parsed: URL;

  try {
    parsed = new URL(value);
  } catch {
    throw new Error('PLAYWRIGHT_PUBLIC_BASE_URL must be an absolute HTTP(S) origin.');
  }

  if (!['http:', 'https:'].includes(parsed.protocol)) {
    throw new Error('PLAYWRIGHT_PUBLIC_BASE_URL must use HTTP or HTTPS.');
  }

  if (parsed.username !== '' || parsed.password !== '') {
    throw new Error('PLAYWRIGHT_PUBLIC_BASE_URL must not contain credentials.');
  }

  if (parsed.pathname !== '/' || parsed.search !== '' || parsed.hash !== '') {
    throw new Error('PLAYWRIGHT_PUBLIC_BASE_URL must be an origin without a path, query, or fragment.');
  }

  const loopbackHosts = new Set(['127.0.0.1', 'localhost', '[::1]']);
  if (parsed.protocol === 'http:' && !loopbackHosts.has(parsed.hostname)) {
    throw new Error('A non-loopback PLAYWRIGHT_PUBLIC_BASE_URL must use HTTPS.');
  }

  return parsed.origin;
}

const baseURL = publicBaseURL(configuredBaseURL);
const outputDir = process.env.PLAYWRIGHT_PUBLIC_ARTIFACT_DIR
  ?? path.join(os.tmpdir(), 'kazutb-public-readonly', String(process.pid));

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: 'public-truth-readonly.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  forbidOnly: true,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  outputDir,
  preserveOutput: 'failures-only',
  use: {
    ...devices['Desktop Chrome'],
    baseURL,
    serviceWorkers: 'block',
    trace: 'off',
    screenshot: 'only-on-failure',
    video: 'off',
    navigationTimeout: 30_000,
    actionTimeout: 15_000,
  },
  // Deliberately no webServer/global setup: this suite never starts, seeds,
  // migrates, resets, or otherwise owns the application it audits.
  projects: [{ name: 'chromium-readonly', use: { ...devices['Desktop Chrome'] } }],
});
