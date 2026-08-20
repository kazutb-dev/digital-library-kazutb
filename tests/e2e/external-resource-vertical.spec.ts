import { expect, test, type Page } from '@playwright/test';

import {
  displayDate,
  idFromEditUrl,
  isoDateFromToday,
  loginAs,
  logout,
  makePdfFixture,
  requireVerticalSafety,
  runHarness,
  runSafeArtisan,
  verticalMutationsEnabled,
  watchRuntime,
} from './vertical-support';

test.describe('§57 live E2E — licensed external resource', () => {
  test.skip(!verticalMutationsEnabled, 'Mutating vertical E2E requires the explicit safe test-database opt-in.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    const runtime = requireVerticalSafety();
    expect(runtime).toMatchObject({ ok: true, environment: 'testing', connection: 'pgsql' });
  });

  test('contract → access → card → role gate → expiry sweep → notification → exact cleanup', async ({ page, baseURL }) => {
    const runtime = watchRuntime(page, baseURL);
    const suffix = `${Date.now()}-${test.info().workerIndex}`;
    const title = `[E2E] Licensed resource ${suffix}`;
    const kkTitle = `[E2E] Лицензиялық ресурс ${suffix}`;
    const ruTitle = `[E2E] Лицензионный ресурс ${suffix}`;
    const enTitle = `[E2E] Licensed resource ${suffix}`;
    const destination = `https://example.com/kazutb-e2e/${suffix}`;
    const contractStart = isoDateFromToday(-1);
    const contractEnd = isoDateFromToday(120);
    let resourceId = 0;
    let editUrl = '';
    let uiDeleted = false;

    try {
      // §57.1–5: an administrator creates a draft with all locales, role rules,
      // instructions, and an unmistakably fake contract stored only in test DB.
      await loginAs(page, 'demo_admin');
      await page.goto('/admin/external-resources/create?lang=kk');
      await page.locator('#resource-title').fill(title);
      await page.locator('#resource-provider').fill('[E2E] KazUTB test provider');
      await page.locator('#resource-type').selectOption('licensed');
      await page.locator('#resource-access-type').selectOption('remote_auth');
      await page.locator('#resource-url').fill(destination);
      await page.locator('#resource-description').fill('E2E licensed-resource description for the isolated test database.');
      await page.locator('#resource-instructions').fill('Sign in with the isolated E2E reader account, search, then open the material.');
      await page.locator('#resource-access-method').selectOption('personal_account');
      await page.locator('input[name="login_required"][type="checkbox"]').setChecked(true);

      for (const audience of ['guest', 'student', 'teacher', 'library_staff']) {
        await page.locator(`input[name="available_roles[]"][value="${audience}"]`).setChecked(
          audience === 'student' || audience === 'teacher',
        );
      }
      await page.locator('input[name="content_types[]"][value="electronic_books"]').setChecked(true);
      await page.locator('input[name="content_types[]"][value="scientific_articles"]').setChecked(true);

      await fillTranslation(page, 'kk', {
        name: kkTitle,
        short: 'Оқырманға арналған E2E сынақ ресурсы.',
        description: 'Оқшауланған сынақ дерекқорындағы лицензиялық электрондық кітаптар мен ғылыми мақалалар.',
        instructions: 'E2E студенттік тіркелгісімен кіріп, материалды іздеп, ашыңыз.',
      });
      await fillTranslation(page, 'ru', {
        name: ruTitle,
        short: 'Тестовый ресурс для читателя.',
        description: 'Лицензионные электронные книги и научные статьи в изолированной тестовой базе.',
        instructions: 'Войдите под тестовой учётной записью студента, найдите и откройте материал.',
      });
      await fillTranslation(page, 'en', {
        name: enTitle,
        short: 'An E2E resource for a reader.',
        description: 'Licensed e-books and scientific articles in the isolated test database.',
        instructions: 'Sign in with the E2E student account, find a material, and open it.',
      });

      await page.locator('#resource-expiry').fill(contractEnd);
      await page.locator('#contract-number').fill(`[E2E-FAKE] ${suffix}`);
      await page.locator('#contract-start').fill(contractStart);
      await page.locator('#contract-end').fill(contractEnd);
      await page.locator('#renewal-at').fill(isoDateFromToday(90));
      await page.locator('#internal-notes').fill('[E2E] Fake contract fixture. No legal or production meaning.');
      await page.locator('#licence-file').setInputFiles({
        name: `e2e-fake-contract-${suffix}.pdf`,
        mimeType: 'application/pdf',
        buffer: makePdfFixture(`E2E fake contract ${suffix}`),
      });

      const createForm = page.locator('form', { has: page.locator('#resource-title') });
      await Promise.all([
        page.waitForURL(/\/admin\/external-resources\/\d+\/edit(?:[?#]|$)/),
        createForm.locator('button[type="submit"]').click(),
      ]);
      editUrl = page.url();
      resourceId = idFromEditUrl(editUrl, 'external-resources');
      await expect(page.locator('#name-kk')).toHaveValue(kkTitle);
      await expect(page.locator('#resource-title')).toHaveValue(title);

      const submitReview = page.locator('form:has(input[name="action"][value="submit_review"])');
      await submitReview.locator('button[type="submit"]').click();
      await expect(page.locator('#resource-publication-status')).not.toContainText(/draft|черновик|жоба/i);

      // §57.6 and §57.9: only the director publishes and sees the verified term.
      await logout(page, '/admin');
      await loginAs(page, 'demo_director');
      await page.goto('/librarian/external-resources/review?lang=kk');
      const reviewCard = page.locator('article', { hasText: title });
      await expect(reviewCard).toBeVisible();
      await expect(reviewCard).toContainText(displayDate(contractEnd));
      await reviewCard.locator('form:has(input[name="action"][value="publish"]) button').click();
      await expect(reviewCard).toContainText(title);

      await logout(page, '/librarian');

      // §57.7: metadata is discoverable, but a guest cannot traverse the role gate.
      await page.goto('/resources?lang=kk');
      await page.locator('[data-resource-search]').fill(suffix);
      const publicCard = page.locator('.external-resource-card', { hasText: kkTitle });
      await expect(publicCard).toBeVisible();
      const detailHref = await publicCard.locator('a.external-resource-card__details').getAttribute('href');
      expect(detailHref).toBeTruthy();
      await page.goto(detailHref!);
      await expect(page.locator('[data-resource-detail]')).toContainText(kkTitle);
      await expect(page.locator('.resource-detail__instructions')).toContainText('E2E студенттік тіркелгісімен');
      const guestOpen = await page.request.get(`/resources/${resourceId}/open`, { maxRedirects: 0 });
      expect(guestOpen.status()).toBe(403);

      // §57.8: a student can pass the same server-side gate; no request is sent
      // to example.com because redirects are deliberately not followed.
      await loginAs(page, 'demo_student');
      await page.goto(detailHref!);
      const studentOpen = await page.request.get(`/resources/${resourceId}/open`, { maxRedirects: 0 });
      expect(studentOpen.status()).toBe(302);
      expect(studentOpen.headers().location).toBe(destination);
      await logout(page, '/dashboard');

      // §57.9–12: director checks the term, then the exact fixture is time-shifted
      // by the guarded harness, swept by the real command, and notified by outbox.
      await loginAs(page, 'demo_director');
      await page.goto('/librarian/external-resources/review?lang=kk');
      await expect(page.locator('article', { hasText: title })).toContainText(displayDate(contractEnd));
      const unreadBefore = await staffUnreadCount(page);

      const expired = runHarness('expire-external', resourceId, title);
      expect(expired).toMatchObject({ id: resourceId, status: 'expired' });
      runSafeArtisan('library:digital-services-sweep');

      const notification = runHarness('external-notification-state', resourceId, title);
      expect(notification.outbox_total).toBeGreaterThanOrEqual(1);
      expect(notification.outbox_delivered).toBeGreaterThanOrEqual(1);
      expect(notification.director_notifications).toBeGreaterThanOrEqual(1);
      await page.reload();
      const unreadAfter = await staffUnreadCount(page);
      expect(unreadAfter).toBeGreaterThan(0);
      if (unreadBefore < 9) expect(unreadAfter).toBeGreaterThanOrEqual(unreadBefore + 1);

      await logout(page, '/librarian');
      const expiredDetail = await page.goto(detailHref!);
      expect(expiredDetail?.status()).toBe(200);
      await expect(page.locator('.resource-detail__badge--expired')).toBeVisible();
      await expect(page.locator('.resource-detail__open--disabled')).toBeVisible();
      expect((await page.request.get(`/resources/${resourceId}/open`, { maxRedirects: 0 })).status()).toBe(403);

      // §57.13: the normal UI deletion path is exercised first.
      await loginAs(page, 'demo_admin');
      await page.goto(editUrl);
      await page.locator('#resource-delete-reason').fill('[E2E] End of isolated vertical test');
      await Promise.all([
        page.waitForURL(/\/admin\/external-resources(?:[/?#]|$)/),
        page.locator('form:has(#resource-delete-reason) button[type="submit"]').click(),
      ]);
      uiDeleted = true;
      runtime.assertClean();
    } finally {
      // Force-removal is narrowly bound to the exact id + [E2E] title and only
      // exists to remove soft-deleted rows/files from an isolated *_test DB.
      if (resourceId > 0) {
        const cleanup = runHarness('cleanup-external', resourceId, title);
        expect(cleanup.removed, `exact external fixture cleanup (UI deleted: ${uiDeleted})`).toBe(true);
      }
    }
  });
});

async function fillTranslation(
  page: Page,
  locale: 'kk' | 'ru' | 'en',
  values: { name: string; short: string; description: string; instructions: string },
): Promise<void> {
  const details = page.locator(`#name-${locale}`).locator('xpath=ancestor::details[1]');
  if ((await details.getAttribute('open')) === null) await details.locator('summary').click();
  await page.locator(`#name-${locale}`).fill(values.name);
  await page.locator(`#short-description-${locale}`).fill(values.short);
  await page.locator(`#description-${locale}`).fill(values.description);
  await page.locator(`#instructions-${locale}`).fill(values.instructions);
}

async function staffUnreadCount(page: Page): Promise<number> {
  const notificationLink = page.locator('header a[href*="/librarian/messages"]').first();
  if (!(await notificationLink.count())) return 0;
  const text = await notificationLink.innerText();
  const values = text.match(/\d+/g);

  return values ? Number(values.at(-1)) : 0;
}
