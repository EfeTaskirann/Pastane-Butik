import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Konfigürasyonu — Pastane E2E
 *
 * Environment Variables:
 *   - E2E_BASE_URL          (default: http://localhost:8000)
 *   - E2E_ADMIN_USERNAME    (default: admin)
 *   - E2E_ADMIN_PASSWORD    (default: admin123)
 *   - CI                    (GitHub Actions otomatik set eder)
 *
 * Çalıştırma:
 *   npm run test:e2e                          # tüm tarayıcılar
 *   npm run test:e2e -- --project=chromium    # sadece chromium
 *   npm run test:e2e:ui                       # interaktif UI
 *   npm run test:e2e:report                   # son HTML raporu aç
 *
 * Kurulum (yerel):
 *   npm install --save-dev @playwright/test
 *   npx playwright install --with-deps
 */

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8000';
const IS_CI = !!process.env.CI;

export default defineConfig({
  testDir: './tests/e2e',
  testMatch: /.*\.spec\.ts$/,
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },

  // CI'da tek fail → early exit, lokal'de paralel çalıştır
  fullyParallel: true,
  forbidOnly: IS_CI,
  retries: IS_CI ? 2 : 0,
  workers: IS_CI ? 2 : undefined,

  // Reporter: HTML (kalıcı) + list (konsol) + GitHub summary
  reporter: IS_CI
    ? [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
        ['github'],
        ['junit', { outputFile: 'tests/e2e/results/junit.xml' }],
      ]
    : [
        ['list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'on-failure' }],
      ],

  outputDir: 'tests/e2e/results',

  use: {
    baseURL: BASE_URL,
    actionTimeout: 10_000,
    navigationTimeout: 15_000,
    trace: IS_CI ? 'on-first-retry' : 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: IS_CI ? 'retain-on-failure' : 'off',
    locale: 'tr-TR',
    timezoneId: 'Europe/Istanbul',
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: {
      'Accept-Language': 'tr-TR,tr;q=0.9,en;q=0.8',
    },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    // Mobile (opt-in) — E2E_MOBILE=1 ile aktif olur
    ...(process.env.E2E_MOBILE
      ? [
          {
            name: 'mobile-chrome',
            use: { ...devices['Pixel 5'] },
          },
        ]
      : []),
  ],

  // Yerel dev'de PHP built-in server otomatik başlat — CI'da dışarıdan servis gelir
  webServer: IS_CI
    ? undefined
    : {
        command: 'php -S localhost:8000 -t .',
        url: BASE_URL,
        timeout: 30_000,
        reuseExistingServer: true,
        stdout: 'ignore',
        stderr: 'pipe',
      },
});
