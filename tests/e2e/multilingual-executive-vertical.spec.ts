import { expect, test } from '@playwright/test';

import {
  idFromEditUrl,
  loginAs,
  logout,
  requireVerticalSafety,
  runHarness,
  verticalMutationsEnabled,
  watchRuntime,
} from './vertical-support';

test.describe('§21 + §23 multilingual catalogue and executive control centre', () => {
  test.skip(!verticalMutationsEnabled, 'Mutating vertical E2E requires the explicit safe test-database opt-in.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    expect(requireVerticalSafety()).toMatchObject({ ok: true, environment: 'testing', connection: 'pgsql' });
  });

  test('original RU → reviewed KK/EN → public fallback/search → director decision flow', async ({ page, baseURL }) => {
    const runtime = watchRuntime(page, baseURL);
    const suffix = `${Date.now()}-${test.info().workerIndex}`;
    const original = `[E2E] Оригинальное название ${suffix}`;
    const kkTitle = `[E2E] Қазақша атау ${suffix}`;
    const enTitle = `[E2E] English title ${suffix}`;
    const alertTitle = `[E2E] Executive overdue ${suffix}`;
    const taskComment = `[E2E] Director action ${suffix}`;
    let recordId = 0;
    let alertRecordId = 0;

    try {
      await loginAs(page, 'demo_cataloguer');
      await page.goto('/librarian/catalog/create?lang=kk');
      const form = page.locator('form[action$="/librarian/catalog"]');
      await form.locator('[name="title"]').fill(original);
      await form.locator('[name="primary_author"]').fill('[E2E] Test author');
      await form.locator('[name="publisher"]').fill('[E2E] Test publisher');
      await form.locator('[name="publication_year"]').fill('2026');
      await form.locator('[name="udc_code"]').fill('004');
      await form.locator('[name="annotation"]').fill('[E2E] Original Russian annotation');
      await form.locator('[name="keywords"]').fill('исследование\nкаталог');
      await form.locator('[name="language"]').selectOption('ru');

      for (const [locale, title, annotation, keywords] of [
        ['kk', kkTitle, 'Қазақша аңдатпа ӘҒҚҢӨҰҮҺІ', 'кітапхана\nғылым'],
        ['en', enTitle, 'Reviewed English annotation', 'library\nresearch'],
      ] as const) {
        const titleInput = form.locator(`[name="translations[${locale}][title]"]`);
        const details = titleInput.locator('xpath=ancestor::details[1]');
        if ((await details.getAttribute('open')) === null) await details.locator('summary').click();
        await titleInput.fill(title);
        await form.locator(`[name="translations[${locale}][annotation]"]`).fill(annotation);
        await form.locator(`[name="translations[${locale}][keywords]"]`).fill(keywords);
        await form.locator(`[name="translations[${locale}][translation_status]"]`).selectOption('reviewed');
      }

      await Promise.all([
        page.waitForURL(/\/librarian\/catalog\/\d+\/edit(?:[?#]|$)/),
        form.locator('button[type="submit"]').first().click(),
      ]);
      recordId = idFromEditUrl(page.url(), 'catalog');
      await expect(page.locator('[name="translations[kk][title]"]')).toHaveValue(kkTitle);
      await expect(page.locator('[name="translations[en][title]"]')).toHaveValue(enTitle);
      await logout(page, '/librarian');

      for (const [locale, query, expected] of [
        ['kk', kkTitle, kkTitle],
        ['ru', original, original],
        ['en', enTitle, enTitle],
      ] as const) {
        await page.goto(`/catalog?lang=${locale}&q=${encodeURIComponent(query)}`);
        await expect(page.locator('[data-catalog-card]').first()).toContainText(expected);
        if (locale !== 'ru') await expect(page.locator('[data-catalog-card]').first()).toContainText(original);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
      }

      const alert = runHarness('create-executive-alert', alertTitle);
      alertRecordId = Number(alert.record_id);
      await loginAs(page, 'demo_director');
      await page.goto('/librarian?lang=kk&period=month&compare=1');
      const dashboard = page.locator('[data-section="director-executive-dashboard"]');
      await expect(dashboard).toBeVisible();
      await expect(dashboard).toContainText(/Бюджет|budget|бюджет/i);
      const overdueAlert = dashboard.locator('[role="status"] > div > div', { hasText: /Мерзімі өткен берілімдер|Overdue loans|Просроченные выдачи/i }).first();
      await expect(overdueAlert).toBeVisible();
      await overdueAlert.getByRole('link').click();
      await expect(page).toHaveURL(/report=overdue/);
      await page.goBack();

      const refreshedAlert = page.locator('[role="status"] > div > div', { hasText: /Мерзімі өткен берілімдер|Overdue loans|Просроченные выдачи/i }).first();
      await refreshedAlert.locator('summary').click();
      await refreshedAlert.locator('textarea[name="comment"]').fill(taskComment);
      await refreshedAlert.locator('button[type="submit"]').last().click();
      await expect(page.locator('body')).toContainText(/тапсырма|задач|task/i);
      runtime.assertClean();
    } finally {
      if (alertRecordId > 0) {
        expect(runHarness('cleanup-executive-alert', alertRecordId, alertTitle).removed).toBe(true);
      }
      if (recordId > 0) {
        expect(runHarness('cleanup-catalog', recordId, original).removed).toBe(true);
      }
    }
  });
});
