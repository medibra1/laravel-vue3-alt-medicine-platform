import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    use: {
        baseURL: 'http://127.0.0.1:8123',
    },
    // `php` isn't on a non-interactive shell's PATH in some local setups
    // (only a login shell sources the profile that puts it there) — see
    // CLAUDE.md "Piège d'environnement rencontré en vérification". Routing
    // through a login shell here means `npm run test:e2e` works the same
    // way regardless of which shell invoked it.
    webServer: {
        command: 'zsh -l -c "php artisan serve --port=8123"',
        url: 'http://127.0.0.1:8123/login',
        reuseExistingServer: true,
        timeout: 30_000,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
