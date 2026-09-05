import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:4173',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    // Reuse the installed Edge channel locally; CI can install Chromium explicitly.
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], channel: 'msedge' } }],
    webServer: {
        command: 'php artisan serve --env=testing --host=127.0.0.1 --port=4173',
        url: 'http://127.0.0.1:4173/login',
        // An existing local `artisan serve` can point to a non-testing database.
        // Reuse only when the caller explicitly owns and configured that server.
        reuseExistingServer: process.env.PLAYWRIGHT_REUSE_SERVER === '1',
        timeout: 120_000,
        // HTTP browser tests need a persistent session across requests. Laravel's
        // array driver is intentionally request-local for the PHP test suite.
        env: { ...process.env, APP_ENV: 'testing', SESSION_DRIVER: 'file' },
    },
    globalSetup: './tests/e2e/global-setup.js',
});
