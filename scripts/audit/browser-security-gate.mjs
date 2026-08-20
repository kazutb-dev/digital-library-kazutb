import { chromium } from '@playwright/test';

const baseURL = process.env.SECURITY_GATE_BASE_URL ?? 'https://elibrary.kaztbu.edu.kz';
const parsedBaseURL = new URL(baseURL);

if (parsedBaseURL.protocol !== 'https:' || parsedBaseURL.username || parsedBaseURL.password) {
  throw new Error('SECURITY_GATE_BASE_URL must be a credential-free HTTPS origin.');
}

const routes = ['/', '/catalog', '/login'];
const viewports = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

const browser = await chromium.launch({ headless: true });
const results = [];

try {
  for (const viewport of viewports) {
    const context = await browser.newContext({
      viewport,
      serviceWorkers: 'block',
    });

    for (const route of routes) {
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      const failedRequests = [];
      const mixedContent = [];
      const serverErrors = [];

      page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
      });
      page.on('pageerror', (error) => pageErrors.push(error.message));
      page.on('requestfailed', (request) => {
        failedRequests.push({
          method: request.method(),
          url: request.url(),
          error: request.failure()?.errorText ?? 'failed',
        });
      });
      page.on('request', (request) => {
        if (request.url().startsWith('http://')) mixedContent.push(request.url());
      });
      page.on('response', (response) => {
        if (response.status() >= 500) {
          serverErrors.push({ status: response.status(), url: response.url() });
        }
      });

      const response = await page.goto(new URL(route, parsedBaseURL).toString(), {
        waitUntil: 'networkidle',
      });

      results.push({
        viewport: viewport.name,
        route,
        status: response?.status() ?? 0,
        finalPath: new URL(page.url()).pathname,
        consoleErrors,
        pageErrors,
        failedRequests,
        mixedContent,
        serverErrors,
      });

      await page.close();
    }

    const cookies = await context.cookies(parsedBaseURL.origin);
    const sessionCookie = cookies.find((cookie) => cookie.name === 'digital-library-session');
    results.push({
      viewport: viewport.name,
      sessionCookie: sessionCookie
        ? {
            present: true,
            secure: sessionCookie.secure,
            httpOnly: sessionCookie.httpOnly,
            sameSite: sessionCookie.sameSite,
          }
        : { present: false },
    });

    await context.close();
  }
} finally {
  await browser.close();
}

const routeResults = results.filter((result) => 'route' in result);
const cookieResults = results.filter((result) => 'sessionCookie' in result);
const passed = routeResults.every((result) => (
  result.status === 200
  && result.consoleErrors.length === 0
  && result.pageErrors.length === 0
  && result.failedRequests.length === 0
  && result.mixedContent.length === 0
  && result.serverErrors.length === 0
)) && cookieResults.every((result) => (
  result.sessionCookie.present
  && result.sessionCookie.secure === true
  && result.sessionCookie.httpOnly === true
  && result.sessionCookie.sameSite === 'Lax'
));

process.stdout.write(`${JSON.stringify({ passed, results }, null, 2)}\n`);
if (!passed) process.exitCode = 1;
