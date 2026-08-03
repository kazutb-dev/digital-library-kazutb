import { expect, test } from "@playwright/test";

test.describe("public smoke coverage", () => {
    test("homepage renders the KazTBU library search-first shell", async ({
        page,
    }) => {
        await page.goto("/?lang=en");

        await expect(
            page.locator('[data-section="homepage-canonical-hero"]'),
        ).toBeVisible();
        await expect(page.locator("#heroSearch")).toBeVisible();
        await expect(page.getByText(/KazUTB/i).first()).toBeVisible();
    });

    test("catalog keeps the critical discovery controls visible", async ({
        page,
    }) => {
        await page.goto("/catalog");

        await expect(page.locator("#language-chips")).toBeVisible();
        await expect(page.locator("#sort-select")).toBeVisible();
        await expect(page.locator("#filter-available-only")).toBeVisible();
    });

    test("guest account access redirects to login while resources stay public", async ({
        page,
    }) => {
        await page.goto("/account");
        await expect(page).toHaveURL(/\/login\?redirect=%2Faccount/);
        // The page also carries one POST form per demo identity, so target the
        // credential form itself rather than "any form".
        await expect(page.locator("#login-form")).toBeVisible();

        await page.goto("/resources?lang=en");
        await expect(
            page.locator('[data-section="resources-canonical-hero"]'),
        ).toBeVisible();
    });
});
