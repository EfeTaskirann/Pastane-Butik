import { Page, expect, test as base } from '@playwright/test';

/**
 * Admin auth fixture — login helper + authenticated context.
 *
 * Kullanım:
 *   import { test, expect } from '../fixtures/admin-auth';
 *   test('dashboard', async ({ adminPage }) => { ... });
 *
 * Veya manuel:
 *   await loginAsAdmin(page, 'admin', 'admin123');
 */

export const ADMIN_CREDS = {
  username: process.env.E2E_ADMIN_USERNAME ?? 'admin',
  password: process.env.E2E_ADMIN_PASSWORD ?? 'admin123',
};

/**
 * Admin girişi yapar ve dashboard'a ulaştığını doğrular.
 * CSRF token form içinde hidden input olarak geliyor — Playwright otomatik submit eder.
 */
export async function loginAsAdmin(
  page: Page,
  username: string = ADMIN_CREDS.username,
  password: string = ADMIN_CREDS.password,
): Promise<void> {
  await page.goto('/admin/index.php');

  const userInput = page.locator('#username');
  const passInput = page.locator('#password');

  await expect(userInput).toBeVisible();
  await userInput.fill(username);
  await passInput.fill(password);

  await Promise.all([
    page.waitForURL(/\/admin\/(dashboard|index)\.php/, { timeout: 10_000 }),
    page.locator('form button[type="submit"]').click(),
  ]);
}

/**
 * Login sayfasının yüklendiğini teyit et (test setup helper).
 */
export async function assertLoginPage(page: Page): Promise<void> {
  await expect(page).toHaveTitle(/Admin Giriş/i);
  await expect(page.locator('#username')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();
}

type Fixtures = {
  adminPage: Page;
};

/**
 * Extended test runner — `adminPage` fixture önceden giriş yapılmış sayfa döner.
 */
export const test = base.extend<Fixtures>({
  adminPage: async ({ page }, use) => {
    await loginAsAdmin(page);
    await use(page);
  },
});

export { expect };
