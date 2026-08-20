import { expect, test, type Page } from '@playwright/test';

import {
  idFromEditUrl,
  loginAs,
  logout,
  makePdfFixture,
  requireVerticalSafety,
  runHarness,
  sha256,
  verticalMutationsEnabled,
  watchRuntime,
} from './vertical-support';

test.describe('§58 live E2E — scholarly repository publication', () => {
  test.skip(!verticalMutationsEnabled, 'Mutating vertical E2E requires the explicit safe test-database opt-in.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    const runtime = requireVerticalSafety();
    expect(runtime).toMatchObject({ ok: true, environment: 'testing', connection: 'pgsql' });
  });

  test('master thesis → rights review → approval → guest PDF → v2 → tombstone → exact cleanup', async ({ page, baseURL }) => {
    const runtime = watchRuntime(page, baseURL);
    const suffix = `${Date.now()}-${test.info().workerIndex}`;
    const title = `[E2E] Master thesis ${suffix}`;
    const author = `Aruzhan E2E ${suffix}`;
    const udc = `004.8:${suffix.slice(-6)}`;
    const abstract = 'ӘОЖ сынақ аңдатпасы: ғылыми зерттеу, қауіпсіз оқшауланған дерекқор.';
    const withdrawalReason = `[E2E] Withdraw exact test thesis ${suffix}`;
    const versionOne = makePdfFixture(`KazUTB E2E repository v1 ${suffix}`);
    const versionTwo = makePdfFixture(`KazUTB E2E repository v2 ${suffix}`);
    const versionOneName = `e2e-master-${suffix}-v1.pdf`;
    const versionTwoName = `e2e-master-${suffix}-v2.pdf`;
    let itemId = 0;
    let editUrl = '';

    try {
      // §58.1–6: the responsible librarian uploads a safe PDF and complete
      // master-thesis metadata, then performs the explicit rights-review stage.
      await loginAs(page, 'demo_librarian');
      await page.goto('/librarian/repository/create?lang=kk');
      await page.locator('#repository-title').fill(title);
      await page.locator('#repository-source').fill('[E2E] KazUTB isolated repository intake');
      await page.locator('#repository-rights-holder').fill(author);
      await page.locator('#repository-copyright').selectOption('university_owned');
      await page.locator('#repository-access').selectOption('full_public');
      await page.locator('#repository-authors').fill(author);
      await page.locator('#repository-work-type').selectOption('master_thesis');
      await page.locator('#repository-year').fill(String(new Date().getUTCFullYear()));
      await page.locator('#repository-department').fill('[E2E] Faculty of Digital Technologies');
      await page.locator('#repository-udc').fill(udc);
      await page.locator('#repository-language').selectOption('kk');
      await page.locator('#repository-abstract').fill(abstract);
      await page.locator('#repository-keywords').fill('E2E\nғылым\nжасанды интеллект');
      await page.locator('#repository-file').setInputFiles({
        name: versionOneName,
        mimeType: 'application/pdf',
        buffer: versionOne,
      });

      const createForm = page.locator('form', { has: page.locator('#repository-title') });
      await Promise.all([
        page.waitForURL(/\/librarian\/repository\/\d+\/edit(?:[?#]|$)/),
        createForm.locator('button[type="submit"]').click(),
      ]);
      editUrl = page.url();
      itemId = idFromEditUrl(editUrl, 'repository');
      await expect(page.locator('body')).toContainText(title);
      await expect(page.locator('[data-section="repository-version-history"]')).toContainText(`v1 · ${versionOneName}`);

      await transition(page, 'metadata_review');
      await transition(page, 'author_verification');
      await transition(page, 'rights_review');
      await expect(page.locator('[data-section="repository-workflow-audit"]')).toContainText(/rights|құқық|прав/i);
      await transition(page, 'quality_review');
      await transition(page, 'pending_approval');

      // §58.7–8: a separate director account approves and publishes the
      // exact active PDF; the uploader cannot self-approve it.
      await logout(page, '/librarian');
      await loginAs(page, 'demo_director');
      await page.goto(editUrl);
      await transition(page, 'approved');
      await transition(page, 'published');
      // The director has no manage-versions capability: only the responsible
      // library employee can start a fresh immutable revision cycle.
      await expect(page.locator('[data-section="repository-new-version"]')).toHaveCount(0);
      await logout(page, '/librarian');

      // §58.9–12: a guest discovers the public metadata, uses the inline PDF
      // endpoint, searches by title, and sees a stable bibliographic citation.
      await page.goto(`/repository?lang=kk&q=${encodeURIComponent(suffix)}&work_type=master_thesis`);
      const result = page.locator(`article[data-work-id="${itemId}"]`);
      await expect(result).toBeVisible();
      await expect(result).toContainText(title);
      await result.locator(`[data-test-id="repository-canonical-details-${itemId}"]`).click();
      await expect(page).toHaveURL(new RegExp(`/repository/${itemId}(?:[?#]|$)`));
      await assertPublicMetadata(page, { title, author, udc, abstract, itemId, expectFullText: true });
      await expect(page.locator('[data-section="repository-inline-pdf"] iframe')).toBeVisible();
      await assertGuestPdf(page, itemId, versionOne);

      const citation = page.locator('[data-section="repository-detail-citation"]');
      await expect(citation).toContainText(author);
      await expect(citation).toContainText(title);
      await expect(citation).toContainText(String(new Date().getUTCFullYear()));
      await expect(citation).toContainText(`/repository/${itemId}`);

      // §58.13–14: published metadata remains locked. A dedicated revision
      // action preserves v1, installs distinct v2, and starts a fresh review.
      await loginAs(page, 'demo_librarian');
      await page.goto(editUrl);
      const revisionForm = page.locator('[data-section="repository-new-version"]');
      await expect(revisionForm).toBeVisible();
      await revisionForm.locator('#repository-new-version-file').setInputFiles({
        name: versionTwoName,
        mimeType: 'application/pdf',
        buffer: versionTwo,
      });
      await revisionForm.locator('#repository-new-version-reason').fill('[E2E] Corrected full text; preserve immutable v1 history.');
      await revisionForm.locator('button[type="submit"]').click();

      const history = page.locator('[data-section="repository-version-history"]');
      await expect(history).toContainText(`v1 · ${versionOneName}`);
      await expect(history).toContainText(`v2 · ${versionTwoName}`);
      await expect(history.locator('li', { hasText: `v2 · ${versionTwoName}` })).toContainText(/active|актив|текущ|белсенді|ағымдағы/i);

      await transition(page, 'author_verification');
      await transition(page, 'rights_review');
      await transition(page, 'quality_review');
      await transition(page, 'pending_approval');
      await logout(page, '/librarian');

      await loginAs(page, 'demo_director');
      await page.goto(editUrl);
      await transition(page, 'approved');
      await transition(page, 'published');
      await logout(page, '/librarian');

      await page.goto(`/repository/${itemId}?lang=kk`);
      await assertPublicMetadata(page, { title, author, udc, abstract, itemId, expectFullText: true });
      await assertGuestPdf(page, itemId, versionTwo);

      // §58.15–16: withdrawal is an audited director workflow transition;
      // the public metadata/citation remains, while every full-text route closes.
      await loginAs(page, 'demo_director');
      await page.goto(editUrl);
      await transition(page, 'withdrawn', withdrawalReason);
      await logout(page, '/librarian');

      const tombstoneResponse = await page.goto(`/repository/${itemId}?lang=kk`);
      expect(tombstoneResponse?.status()).toBe(200);
      await expect(page.locator('[data-section="repository-withdrawal-tombstone"]')).toContainText(withdrawalReason);
      await expect(page.locator('[data-test-id="repository-detail-view"]')).toHaveCount(0);
      await expect(page.locator('[data-test-id="repository-detail-download"]')).toHaveCount(0);
      await assertPublicMetadata(page, { title, author, udc, abstract, itemId, expectFullText: false });
      await expect(page.locator('[data-section="repository-detail-citation"]')).toContainText(title);
      expect((await page.request.get(`/repository/${itemId}/view?lang=kk`)).status()).toBe(404);
      expect((await page.request.get(`/repository/${itemId}/download?lang=kk`)).status()).toBe(404);

      runtime.assertClean();
    } finally {
      // §58.17: production intentionally has no destructive repository UI.
      // Exact id + hash-equal [E2E] title cleanup is therefore restricted to
      // the guarded harness and an isolated PostgreSQL database ending _test.
      if (itemId > 0) {
        const cleanup = runHarness('cleanup-repository', itemId, title);
        expect(cleanup.removed, 'exact repository fixture cleanup').toBe(true);
      }
    }
  });
});

async function transition(page: Page, action: string, comment?: string): Promise<void> {
  const form = page.locator(`form:has(input[name="action"][value="${action}"])`);
  await expect(form, `workflow action ${action} must be available to this role`).toBeVisible();
  if (comment !== undefined) await form.locator('textarea[name="comment"]').fill(comment);
  await form.locator('button[type="submit"]').click();
  await expect(page.locator('[data-section="repository-workflow-audit"]')).toContainText(
    action === 'withdrawn' ? comment! : new RegExp(workflowText(action), 'i'),
  );
}

function workflowText(action: string): string {
  const words: Record<string, string> = {
    metadata_review: 'metadata|метадер|метадан',
    author_verification: 'author|автор',
    rights_review: 'rights|құқық|прав',
    quality_review: 'quality|сапа|качеств',
    pending_approval: 'approval|бекіту|утвержден',
    approved: 'approved|мақұлдан|бекітіл|утвержд',
    published: 'published|жариялан|опублик',
  };

  return words[action] ?? action;
}

async function assertPublicMetadata(
  page: Page,
  values: {
    title: string;
    author: string;
    udc: string;
    abstract: string;
    itemId: number;
    expectFullText: boolean;
  },
): Promise<void> {
  const detail = page.locator('[data-section="repository-detail-page"]');
  await expect(detail).toHaveAttribute('data-work-id', String(values.itemId));
  await expect(detail).toContainText(values.title);
  await expect(detail).toContainText(values.author);
  await expect(detail).toContainText(values.udc);
  await expect(page.locator('[data-section="repository-detail-abstract"]')).toContainText(values.abstract);
  if (values.expectFullText) {
    await expect(page.locator('[data-test-id="repository-detail-view"]')).toBeVisible();
  }
}

async function assertGuestPdf(page: Page, itemId: number, expected: Buffer): Promise<void> {
  const response = await page.request.get(`/repository/${itemId}/view?lang=kk`, {
    headers: { Range: 'bytes=0-' },
  });
  expect([200, 206]).toContain(response.status());
  expect(response.headers()['content-type']).toMatch(/^application\/pdf(?:;|$)/i);
  const body = await response.body();
  expect(body.subarray(0, 5).toString('ascii')).toBe('%PDF-');
  expect(sha256(body)).toBe(sha256(expected));
}
