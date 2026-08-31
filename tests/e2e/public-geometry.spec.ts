import { mkdirSync } from 'node:fs';
import path from 'node:path';

import { expect, test } from '@playwright/test';

const screenshotDir = path.resolve('docs/runtime/screenshots/public-ui-geometry-20260814');

const pages = [
  { route: '/catalog?lang=ru', sections: ['.catalog-export__hero > .public-v2__inset', '.public-v2__body > .public-v2__inset'] },
  { route: '/resources?lang=ru', sections: ['.external-resources__hero', '.external-resources__toolbar', '.external-resources__layout'] },
  { route: '/repository?lang=ru', sections: ['.public-v2__hero-grid', '.public-v2__body > .public-v2__inset'] },
  { route: '/news?lang=ru', sections: ['.public-v2__hero-grid', '.public-v2__body > .public-v2__inset'] },
  { route: '/events?lang=ru', sections: ['.public-v2__hero-grid', '.public-v2__body > .public-v2__inset'] },
  { route: '/contacts?lang=ru', sections: ['.public-page__intro .public-container', '.public-page__body > .public-container', '.contacts-page__visit-grid'] },
  { route: '/rules?lang=ru', sections: ['.public-page__intro .public-container', '.public-page__body > .public-container'] },
  { route: '/leadership?lang=ru', sections: ['.public-page__intro .public-container', '.public-page__body > .public-container'] },
] as const;

test('standard public surfaces share the header content edges', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  const mismatches: string[] = [];

  for (const surface of pages) {
    const response = await page.goto(surface.route, { waitUntil: 'domcontentloaded' });
    expect(response?.status()).toBe(200);

    const geometry = await page.evaluate(({ sections }) => {
      const contentBox = (element: Element) => {
        const rect = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        const left = rect.left + Number.parseFloat(style.paddingLeft || '0');
        const right = rect.right - Number.parseFloat(style.paddingRight || '0');
        return { left, right, width: right - left };
      };
      const header = document.querySelector('.site-header__inner');
      if (!header) throw new Error('Header inner container is missing');

      return {
        header: contentBox(header),
        sections: sections.map((selector) => {
          const element = document.querySelector(selector);
          if (!element) throw new Error(`Missing geometry target: ${selector}`);
          return { selector, box: contentBox(element) };
        }),
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    }, surface);

    console.log(`${surface.route}: ${JSON.stringify(geometry)}`);
    for (const section of geometry.sections) {
      if (Math.abs(section.box.left - geometry.header.left) > 4) mismatches.push(`${surface.route} ${section.selector} left=${section.box.left}`);
      if (Math.abs(section.box.right - geometry.header.right) > 4) mismatches.push(`${surface.route} ${section.selector} right=${section.box.right}`);
    }
    if (geometry.overflow !== 0) mismatches.push(`${surface.route} overflow=${geometry.overflow}`);
  }

  expect(mismatches).toEqual([]);
});

test('canonical gutters remain aligned at every required breakpoint', async ({ page }) => {
  const viewports = [
    { width: 1920, height: 1080 },
    { width: 1440, height: 900 },
    { width: 1280, height: 800 },
    { width: 1366, height: 768 },
    { width: 1024, height: 768 },
    { width: 768, height: 1024 },
    { width: 390, height: 844 },
    { width: 375, height: 812 },
  ];

  for (const viewport of viewports) {
    await page.setViewportSize(viewport);
    for (const surface of pages) {
      await page.goto(surface.route, { waitUntil: 'domcontentloaded' });
      const geometry = await page.evaluate(({ sections }) => {
        const box = (element: Element) => {
          const rect = element.getBoundingClientRect();
          const style = getComputedStyle(element);
          return {
            left: rect.left + Number.parseFloat(style.paddingLeft || '0'),
            right: rect.right - Number.parseFloat(style.paddingRight || '0'),
          };
        };
        const header = document.querySelector('.site-header__inner');
        const section = document.querySelector(sections[0]);
        if (!header || !section) throw new Error('Missing geometry target');
        return {
          header: box(header),
          section: box(section),
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      }, surface);

      if (Math.abs(geometry.header.left - geometry.section.left) > 4 || Math.abs(geometry.header.right - geometry.section.right) > 4) {
        console.log(`${surface.route} at ${viewport.width}: ${JSON.stringify(geometry)}`);
      }
      expect(Math.abs(geometry.header.left - geometry.section.left), `${surface.route} left at ${viewport.width}`).toBeLessThanOrEqual(4);
      expect(Math.abs(geometry.header.right - geometry.section.right), `${surface.route} right at ${viewport.width}`).toBeLessThanOrEqual(4);
      expect(geometry.overflow, `${surface.route} overflow at ${viewport.width}`).toBe(0);
    }
  }
});

test('capture final geometry screenshots from the live runtime', async ({ page }) => {
  mkdirSync(screenshotDir, { recursive: true });

  for (const route of ['catalog', 'resources', 'contacts', 'leadership']) {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`/${route}?lang=ru`, { waitUntil: 'networkidle' });
    if (route === 'leadership') {
      await page.locator('.leadership-page__portrait img').evaluateAll(async (images) => {
        await Promise.all(images.map((image) => (image as HTMLImageElement).decode()));
      });
    }
    await page.screenshot({ path: path.join(screenshotDir, `${route}-1440x900-ru.png`), fullPage: true });
  }

  for (const route of ['resources', 'contacts', 'leadership']) {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/${route}?lang=ru`, { waitUntil: 'networkidle' });
    if (route === 'leadership') {
      await page.locator('.leadership-page__portrait img').evaluateAll(async (images) => {
        await Promise.all(images.map((image) => (image as HTMLImageElement).decode()));
      });
    }
    await page.screenshot({ path: path.join(screenshotDir, `${route}-390x844-ru.png`), fullPage: true });
  }
});
