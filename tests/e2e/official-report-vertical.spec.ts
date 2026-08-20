import { readFileSync } from 'node:fs';

import { expect, test, type Download, type Page, type TestInfo } from '@playwright/test';

import {
  kazakhLetters,
  loginAs,
  logout,
  parseReportFile,
  requireVerticalSafety,
  sha256,
  verticalMutationsEnabled,
  watchRuntime,
} from './vertical-support';

type Format = 'pdf' | 'xlsx' | 'csv' | 'docx';

const mimes: Record<Format, RegExp> = {
  pdf: /^application\/pdf(?:;|$)/i,
  xlsx: /^application\/vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet(?:;|$)/i,
  csv: /^(?:text\/csv|application\/csv)(?:;|$)/i,
  docx: /^application\/vnd\.openxmlformats-officedocument\.wordprocessingml\.document(?:;|$)/i,
};

test.describe('§59 live E2E — official acquisition report', () => {
  test.skip(!verticalMutationsEnabled, 'Mutating vertical E2E requires the explicit safe test-database opt-in.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(() => {
    const runtime = requireVerticalSafety();
    expect(runtime).toMatchObject({ ok: true, environment: 'testing', connection: 'pgsql' });
  });

  test('live data → filtered snapshot → approval → four real formats → immutable revision → audit', async ({ page, baseURL }, testInfo) => {
    const runtime = watchRuntime(page, baseURL);
    const marker = `[E2E] Official acquisition ${Date.now()}-${testInfo.workerIndex}`;

    // §59.1–4: use the real live-report screen for current-month preview and
    // select an actual seeded branch before freezing anything.
    await loginAs(page, 'demo_librarian', 'kk');
    await page.goto('/librarian/reports?lang=kk&report=acquisitions&preset=month');
    await expect(page.locator('[data-report-code="acquisitions"]')).toHaveAttribute('aria-current', 'page');
    const liveBranch = await firstNonEmptyOption(page.locator('form[data-report-filters] select[name="branch_id"]'));
    await page.locator('form[data-report-filters] select[name="branch_id"]').selectOption(liveBranch.value);
    await page.locator('form[data-report-filters] button[type="submit"]').click();
    await expect(page.locator('#active-report-title')).toBeVisible();
    await expect(page.locator('form[data-report-filters] select[name="branch_id"]')).toHaveValue(liveBranch.value);
    await expect(page.locator('table.admin-table').last()).toBeVisible();

    // §59.5: capture the exact live dataset as an immutable official snapshot.
    await page.goto('/librarian/reports/official?lang=kk');
    const captureForm = page.locator('form', { has: page.locator('select[name="report"]') }).first();
    await captureForm.locator('select[name="report"]').selectOption('acquisitions');
    await captureForm.locator('select[name="preset"]').selectOption('month');
    await captureForm.locator('select[name="branch_id"]').selectOption(liveBranch.value);
    await captureForm.locator('input[name="revision_note"]').fill(marker);
    await Promise.all([
      page.waitForURL(/\/librarian\/reports\/official\/[0-9a-f-]{36}(?:[?#]|$)/),
      captureForm.locator('button[type="submit"]').click(),
    ]);

    const originalUrl = page.url();
    const originalPublicId = publicIdFromUrl(originalUrl);
    const sourceHash = await displayedSourceHash(page);
    const sourceBeforePath = testInfo.outputPath('acquisitions-source-before-approval.json');
    const sourceBefore = await downloadSource(page, sourceBeforePath);
    expect(sha256(sourceBefore)).toBe(sourceHash);
    const payload = JSON.parse(sourceBefore.toString('utf8')) as Record<string, any>;
    expect(payload).toMatchObject({ report_type: 'acquisitions', revision: 1 });
    expect(String(payload.filters.branch_id)).toBe(liveBranch.value);
    expect(String(payload.report_title)).toMatch(kazakhLetters);
    await expect(page.locator('body')).toContainText(marker);

    // Official exports deliberately materialize only after independent
    // approval. This secure order supersedes the shorthand ordering in §59.
    await expect(page.locator('form:has(input[name="format"])')).toHaveCount(0);
    await page.getByRole('button', { name: /Директорға жіберу|Отправить директору|Submit/i }).click();
    await expect(page.locator('body')).toContainText(/Қаралуда|Қарау|согласован|pending/i);

    await logout(page, '/librarian');
    await loginAs(page, 'demo_director', 'kk');
    await page.goto(originalUrl);
    const approveForm = page.locator('form[action$="/approve"]');
    await expect(approveForm).toBeVisible();
    await approveForm.locator('input[name="decision_note"]').fill('[E2E] Independently approved by director');
    await approveForm.locator('button[type="submit"]').click();
    await expect(page.locator('body')).toContainText(/Бекітіл|Утвержд|Approved/i);

    // §59.6–11: download each artefact through the secured UI and open it with
    // an actual parser: PDF.js, PHP fgetcsv, and ZipArchive + DOM for OOXML.
    const parsedTexts: string[] = [];
    for (const format of ['pdf', 'xlsx', 'csv', 'docx'] as const) {
      const filePath = testInfo.outputPath(`official-acquisitions.${format}`);
      const { contentType, download } = await exportAndDownload(page, format, filePath);
      expect(contentType).toMatch(mimes[format]);
      expect(download.suggestedFilename().toLowerCase()).toMatch(new RegExp(`\\.${format}$`));

      const parsed = await parseReportFile(format, filePath);
      if (format === 'pdf') {
        expect(parsed.magic.startsWith('%PDF-')).toBe(true);
      } else if (format === 'csv') {
        expect(parsed.rows?.length).toBeGreaterThan(0);
      } else {
        expect(parsed.magic.startsWith('PK')).toBe(true);
        expect(parsed.entries).toContain('[Content_Types].xml');
        expect(parsed.entries).toContain(format === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml');
      }
      expect(parsed.text, `${format} must preserve Kazakh symbols`).toMatch(kazakhLetters);
      parsedTexts.push(parsed.text);
    }
    expect(parsedTexts).toHaveLength(4);

    // §59.12–14: approval is visible and the exact source bytes/hash cannot be
    // changed by export generation or by the director UI.
    await expect(page.locator('form[action$="/submit"]')).toHaveCount(0);
    await expect(page.locator('form[action*="/revisions"]')).toHaveCount(0);
    await expect(page.locator('form[action$="/reports/official/' + originalPublicId + '"]')).toHaveCount(0);
    expect(await displayedSourceHash(page)).toBe(sourceHash);
    const sourceAfterPath = testInfo.outputPath('acquisitions-source-after-approval.json');
    const sourceAfter = await downloadSource(page, sourceAfterPath);
    expect(sourceAfter.equals(sourceBefore)).toBe(true);
    expect(sha256(sourceAfter)).toBe(sourceHash);

    // §59.15: a responsible librarian creates R2; the approved R1 remains a
    // distinct immutable record in the lineage.
    await logout(page, '/librarian');
    await loginAs(page, 'demo_librarian', 'kk');
    await page.goto(originalUrl);
    const revisionForm = page.locator('form[action*="/revisions"]');
    await revisionForm.locator('input[name="revision_note"]').fill(`${marker} revision 2`);
    await Promise.all([
      page.waitForURL(url => /\/librarian\/reports\/official\/[0-9a-f-]{36}$/.test(url.pathname) && url.href !== originalUrl),
      revisionForm.locator('button[type="submit"]').click(),
    ]);
    const revisionUrl = page.url();
    const revisionPublicId = publicIdFromUrl(revisionUrl);
    expect(revisionPublicId).not.toBe(originalPublicId);
    await expect(page.locator('body')).toContainText('R2');
    await expect(page.locator('body')).toContainText(`${marker} revision 2`);
    await expect(page.locator('section', { hasText: /R1/ })).toContainText('R2');

    // §59.16: the common protected audit UI contains both the independent
    // approval event and the new revision event with their immutable public ids.
    await logout(page, '/librarian');
    await loginAs(page, 'demo_admin', 'kk');
    await page.goto('/admin/logs?entity_type=official_report_snapshot&action_type=official_report.approved');
    await expect(page.locator('table.admin-table tbody')).toContainText(originalPublicId);
    await page.goto('/admin/logs?entity_type=official_report_snapshot&action_type=official_report.revision_created');
    await expect(page.locator('table.admin-table tbody')).toContainText(revisionPublicId);

    runtime.assertClean();
  });
});

async function firstNonEmptyOption(select: ReturnType<Page['locator']>): Promise<{ value: string; label: string }> {
  await expect(select).toBeVisible();
  const option = await select.locator('option').evaluateAll(options => {
    const found = options.find(node => (node as HTMLOptionElement).value !== '') as HTMLOptionElement | undefined;
    return found ? { value: found.value, label: found.textContent?.trim() ?? '' } : null;
  });
  if (!option) throw new Error('The isolated E2E database must contain at least one branch.');

  return option;
}

function publicIdFromUrl(url: string): string {
  const match = new URL(url).pathname.match(/\/official\/([0-9a-f-]{36})$/);
  if (!match) throw new Error(`Official snapshot URL has no UUID: ${url}`);

  return match[1];
}

async function displayedSourceHash(page: Page): Promise<string> {
  const texts = await page.locator('span.font-mono').allInnerTexts();
  const match = texts.join('\n').match(/[a-f0-9]{64}/i);
  if (!match) throw new Error('Official report page does not expose its source hash.');

  return match[0].toLowerCase();
}

async function downloadSource(page: Page, outputPath: string): Promise<Buffer> {
  const sourceLink = page.locator('a[href$="/source"]');
  await expect(sourceLink).toBeVisible();
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    sourceLink.click(),
  ]);
  await download.saveAs(outputPath);

  return readFileSync(outputPath);
}

async function exportAndDownload(
  page: Page,
  format: Format,
  outputPath: string,
): Promise<{ contentType: string; download: Download }> {
  const exportForm = page.locator(`form:has(input[name="format"][value="${format}"])`).first();
  await expect(exportForm).toBeVisible();
  await exportForm.locator('button[type="submit"]').click();

  const card = page.locator('[data-export-status]', {
    has: page.locator('strong', { hasText: new RegExp(`^${format}$`, 'i') }),
  }).first();
  await expect(card).toHaveAttribute('data-export-status', 'ready');
  const downloadLink = card.locator('a[href*="/download"]');
  const href = await downloadLink.getAttribute('href');
  expect(href).toBeTruthy();

  const [response, download] = await Promise.all([
    page.waitForResponse(candidate => candidate.url() === new URL(href!, page.url()).href),
    page.waitForEvent('download'),
    downloadLink.click(),
  ]);
  expect(response.status()).toBe(200);
  await download.saveAs(outputPath);

  return { contentType: response.headers()['content-type'] ?? '', download };
}
