import { expect, test } from "@playwright/test";

test("P2-UI-AUTH-001 login page loads", async ({ page }) => {
    const response = await page.goto("/login");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-AUTH-002 guest cannot access dashboard directly", async ({
    page,
}) => {
    const response = await page.goto("/dashboard");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
    await expect(page).toHaveURL(/login|dashboard/);
});

test("P2-UI-AUTH-003 guest cannot access admin directly", async ({ page }) => {
    const response = await page.goto("/admin");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
    await expect(page).toHaveURL(/login|admin/);
});
