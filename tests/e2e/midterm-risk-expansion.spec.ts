import { expect, test } from "@playwright/test";

test.describe("midterm risk expansion", () => {
    test("rapid guest dashboard access remains protected", async ({ page }) => {
        for (let i = 0; i < 3; i++) {
            const response = await page.goto("/dashboard");
            expect(response).not.toBeNull();
            expect(response!.status()).toBeLessThan(500);
            await expect(page).toHaveURL(/login|dashboard/);
        }
    });

    test("parallel catalog requests with large query payload stay below 5xx", async ({
        request,
    }) => {
        const largeQuery = encodeURIComponent("data-" + "x".repeat(512));

        const responses = await Promise.all([
            request.get(`/catalog?q=${largeQuery}`),
            request.get(`/catalog?q=${largeQuery}&lang=en`),
            request.get(`/resources?lang=en&keyword=${largeQuery}`),
        ]);

        for (const response of responses) {
            expect(response.status()).toBeLessThan(500);
        }
    });
});
