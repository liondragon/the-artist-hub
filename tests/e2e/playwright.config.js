const { defineConfig } = require('@playwright/test');

const baseURL = process.env.TAH_E2E_BASE_URL || 'https://wphub.local';
const retries = Number(process.env.TAH_E2E_RETRIES || (process.env.CI ? 2 : 0));

module.exports = defineConfig({
    testDir: __dirname,
    testMatch: ['*.spec.js'],
    fullyParallel: false,
    timeout: 90 * 1000,
    expect: {
        timeout: 10 * 1000,
    },
    retries: Number.isFinite(retries) ? retries : 0,
    workers: 1,
    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
    use: {
        baseURL,
        browserName: 'chromium',
        headless: process.env.TAH_E2E_HEADED === '1' ? false : true,
        ignoreHTTPSErrors: true,
        viewport: { width: 1600, height: 1000 },
        actionTimeout: 15 * 1000,
        navigationTimeout: 30 * 1000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
});
