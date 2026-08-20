import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: ['data-quality-runtime.spec.ts', 'login-runtime.spec.ts', 'director-operational-runtime.spec.ts'],
  timeout: 300_000,
  expect: { timeout: 15_000 },
  workers: 1,
  reporter: [['list']],
  outputDir: 'test-results/librarian-runtime',
  use: {
    ...devices['Desktop Chrome'],
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://10.0.1.17',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
