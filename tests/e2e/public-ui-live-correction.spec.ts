import { mkdirSync } from 'node:fs';
import path from 'node:path';

import { expect, test, type BrowserContext, type Page } from '@playwright/test';

const screenshotDir = path.resolve('docs/runtime/screenshots/public-ui-correction-20260814');

const surfaces = [
  {
    name: 'contacts',
    route: '/contacts?lang=ru',
    container: '.public-container',
    title: '.public-page__title',
    card: '.public-card',
    grid: '.contacts-page__channels',
    section: '.public-page__body',
  },
  {
    name: 'leadership',
    route: '/leadership?lang=ru',
    container: '.public-container',
    title: '.public-page__title',
    card: '.leadership-page__profile',
    grid: '.leadership-page__profile',
    section: '.public-page__body',
  },
  {
    name: 'resources',
    route: '/resources?lang=ru',
    container: '.external-resources__directory',
    title: '.external-resources__hero h1',
    card: '.external-resource-card',
    grid: '.external-resources__cards',
    section: '.external-resources__hero',
  },
  {
    name: 'rules',
    route: '/rules?lang=ru',
    container: '.rules-page__workspace',
    title: '.rules-page .public-page__title',
    card: '.rules-canonical__panel',
    grid: '.rules-page__workspace',
    section: '.rules-page .public-page__intro',
  },
] as const;

type Surface = (typeof surfaces)[number];

async function captureComputed(page: Page, surface: Surface) {
  await page.evaluate(() => document.fonts.ready);

  return page.evaluate((selectors) => {
    const read = (selector: string) => {
      const element = document.querySelector<HTMLElement>(selector);
      if (!element) throw new Error(`Missing element: ${selector}`);
      const style = getComputedStyle(element);
      const rect = element.getBoundingClientRect();

      return {
        display: style.display,
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        left: Math.round(rect.left),
        maxWidth: style.maxWidth,
        paddingTop: style.paddingTop,
        paddingLeft: style.paddingLeft,
        fontSize: style.fontSize,
        fontFamily: style.fontFamily,
        borderTopStyle: style.borderTopStyle,
        gridTemplateColumns: style.gridTemplateColumns,
        backgroundColor: style.backgroundColor,
        backgroundImage: style.backgroundImage,
        color: style.color,
      };
    };

    return {
      cssHref: document.querySelector<HTMLLinkElement>('link[href*="public-pages-v2.css"]')?.getAttribute('href') ?? '',
      container: read(selectors.container),
      title: read(selectors.title),
      card: read(selectors.card),
      grid: read(selectors.grid),
      section: read(selectors.section),
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    };
  }, surface);
}

async function openSurface(context: BrowserContext, surface: Surface, viewport: { width: number; height: number }) {
  const page = await context.newPage();
  await page.setViewportSize(viewport);
  const response = await page.goto(surface.route, { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  return page;
}

test.beforeAll(() => mkdirSync(screenshotDir, { recursive: true }));

for (const surface of surfaces) {
  test(`${surface.name} has live component styles after reload and in a fresh context`, async ({ browser, request }) => {
    const firstContext = await browser.newContext();
    const desktop = await openSurface(firstContext, surface, { width: 1440, height: 900 });
    const initial = await captureComputed(desktop, surface);
    console.log(`${surface.name}: ${JSON.stringify(initial)}`);

    expect(initial.cssHref).toMatch(/^\/css\/public-pages-v2\.css\?v=[a-f0-9]{16}$/);
    const cssResponse = await request.get(initial.cssHref);
    expect(cssResponse.status()).toBe(200);
    expect(cssResponse.headers()['content-type']).toContain('text/css');
    expect(await cssResponse.text()).toContain('.contacts-page__channels');

    expect(initial.container.width).toBeGreaterThan(300);
    expect(initial.container.width).toBeLessThanOrEqual(1440);
    expect(Number.parseFloat(initial.title.fontSize)).toBeGreaterThanOrEqual(32);
    expect(initial.title.fontFamily).toContain('Literata');
    expect(Number.parseFloat(initial.card.paddingTop)).toBeGreaterThanOrEqual(20);
    expect(initial.card.borderTopStyle).toBe('solid');
    expect(initial.grid.display).toBe('grid');
    expect(initial.grid.gridTemplateColumns).not.toBe('none');
    expect(initial.documentWidth).toBeLessThanOrEqual(initial.viewportWidth + 1);

    if (surface.name === 'resources') {
      expect(initial.section.height).toBeLessThan(360);
      expect(initial.section.color).not.toBe('rgb(255, 255, 255)');
    }
    if (surface.name === 'rules') {
      expect(initial.section.height).toBeLessThan(380);
      expect(Number.parseFloat(initial.title.fontSize)).toBeLessThanOrEqual(46);
    }

    await desktop.screenshot({ path: path.join(screenshotDir, `${surface.name}-1440x900-ru.png`), fullPage: true });
    await desktop.reload({ waitUntil: 'networkidle' });
    expect(await captureComputed(desktop, surface)).toEqual(initial);
    await firstContext.close();

    const freshContext = await browser.newContext();
    const freshPage = await openSurface(freshContext, surface, { width: 1440, height: 900 });
    expect(await captureComputed(freshPage, surface)).toEqual(initial);
    await freshContext.close();

    const mobileContext = await browser.newContext();
    const mobile = await openSurface(mobileContext, surface, { width: 390, height: 844 });
    const mobileComputed = await captureComputed(mobile, surface);
    expect(mobileComputed.documentWidth).toBeLessThanOrEqual(mobileComputed.viewportWidth + 1);
    expect(Number.parseFloat(mobileComputed.title.fontSize)).toBeGreaterThanOrEqual(32);
    expect(mobileComputed.cssHref).toBe(initial.cssHref);
    await mobile.screenshot({ path: path.join(screenshotDir, `${surface.name}-390x844-ru.png`), fullPage: true });
    await mobileContext.close();

  });
}

test('corrected surfaces keep the live styled shell in KK and EN', async ({ page }) => {
  for (const locale of ['kk', 'en']) {
    for (const surface of surfaces) {
      const route = surface.route.replace('lang=ru', `lang=${locale}`);
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), route).toBe(200);
      await expect(page.locator('html')).toHaveAttribute('lang', locale);
      await expect(page.locator('link[href*="public-pages-v2.css?v="]')).toHaveCount(1);
      expect(Number.parseFloat((await page.locator(surface.title).evaluate((element) => getComputedStyle(element).fontSize)))).toBeGreaterThanOrEqual(32);
    }
  }
});
