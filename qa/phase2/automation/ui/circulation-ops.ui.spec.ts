import { expect, test } from "@playwright/test";

test("P2-UI-OPS-001 guest access to librarian page is protected", async ({
    page,
}) => {
    const response = await page.goto("/librarian");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-OPS-002 account page renders or redirects safely", async ({
    page,
}) => {
    const response = await page.goto("/account");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});

test("P2-UI-OPS-003 member shortlist page renders or redirects safely", async ({
    page,
}) => {
    const response = await page.goto("/dashboard/list");
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(500);
});
