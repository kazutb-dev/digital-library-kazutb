import { expect, test } from '@playwright/test';

import {
  loginAs,
  requireVerticalSafety,
  verticalMutationsEnabled,
  watchRuntime,
} from './vertical-support';

test.describe('§19 Integration Hub admin vertical', () => {
  test.skip(!verticalMutationsEnabled, 'Integration Hub E2E requires the explicit safe test-database opt-in.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    const runtime = requireVerticalSafety();
    expect(runtime).toMatchObject({ ok: true, environment: 'testing', connection: 'pgsql' });
  });

  test('registry → CRM detail → dry run → mapping → audit-safe UI', async ({ page, baseURL }) => {
    const runtime = watchRuntime(page, baseURL);
    await loginAs(page, 'demo_admin');
    await page.goto('/admin/integrations?lang=kk');

    const hub = page.locator('[data-integration-hub]');
    await expect(hub).toBeVisible();
    await expect(hub.locator('article')).toHaveCount(12);
    await expect(hub).toContainText('CRM');
    await expect(page.locator('body')).not.toContainText(/AD_BIND_PASSWORD|bind_password|access_token|refresh_token/i);

    const crmCard = hub.locator('article', { hasText: 'CRM' });
    await crmCard.getByRole('link').click();
    await expect(page).toHaveURL(/\/admin\/integrations\/[0-9a-f-]{36}/);
    await expect(page.getByRole('heading', { name: 'CRM', exact: true })).toBeVisible();
    await expect(page.locator('#configuration')).toContainText(/Құпия|Secret|Секрет/i);

    await page.getByRole('button', { name: /Сынақ|Dry run|Пробный/i }).click();
    await expect(page.locator('#sync')).toContainText(/Конфигурация|configuration|баптау/i);

    const mapping = page.locator('#mapping form');
    await mapping.locator('input[name="external_field"]').fill(`personId${Date.now()}`);
    await mapping.locator('select[name="local_field"]').selectOption('university_id');
    await mapping.getByRole('button').click();
    await expect(page.locator('#mapping')).toContainText('university_id');
    await expect(page.locator('#outbox')).toBeVisible();
    await expect(page.locator('#conflicts')).toBeVisible();

    runtime.assertClean();
  });
});
