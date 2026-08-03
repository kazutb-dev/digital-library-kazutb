import { expect, test } from "@playwright/test";

test("P2-UI-CAT-001 homepage loads without server error", async ({ page }) => {
    const response = await page.goto("/");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-CAT-002 catalog page loads and contains catalog marker", async ({
    page,
}) => {
    const response = await page.goto("/catalog");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
    await expect(page.locator("body")).toContainText(/catalog|каталог/i);
});

test("P2-UI-CAT-003 resources page loads", async ({ page }) => {
    const response = await page.goto("/resources");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-CAT-004 news page loads", async ({ page }) => {
    const response = await page.goto("/news");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-CAT-005 events page loads", async ({ page }) => {
    const response = await page.goto("/events");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});
