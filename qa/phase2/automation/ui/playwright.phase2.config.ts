import { defineConfig } from "@playwright/test";

export default defineConfig({
    testDir: ".",
    timeout: 45_000,
    retries: 0,
    reporter: [["list"]],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:80",
        trace: "on-first-retry",
        screenshot: "only-on-failure",
    },
    projects: [
        {
            name: "chromium",
            use: { browserName: "chromium" },
        },
    ],
});
