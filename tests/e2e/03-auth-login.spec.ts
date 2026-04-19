import { test, expect } from '@playwright/test';
import { assertLoginPage, loginAsAdmin, ADMIN_CREDS } from './fixtures/admin-auth';
import { PATHS } from './fixtures/test-data';

/**
 * Admin Auth — login sayfası, geçersiz/geçerli akış, çıkış (opsiyonel).
 */
test.describe('Admin Auth', () => {
  test('login sayfası render oluyor ve form bileşenleri görünüyor', async ({ page }) => {
    await page.goto(PATHS.adminLogin);
    await assertLoginPage(page);

    await expect(page.locator('form button[type="submit"]')).toBeVisible();
    // CSRF token hidden input — form güvenliği için zorunlu
    await expect(page.locator('input[type="hidden"][name*="csrf"], input[name="_token"]')).toHaveCount(1);
  });

  test('geçersiz kimlik bilgileri ile hata mesajı gösteriliyor', async ({ page }) => {
    await page.goto(PATHS.adminLogin);

    await page.locator('#username').fill('gecersiz_user_' + Date.now());
    await page.locator('#password').fill('wrong_password_!!');
    await page.locator('form button[type="submit"]').click();

    // Hata mesajı DOM'a basılmalı (alert-error veya alert-warning)
    const alert = page.locator('.alert-error, .alert-warning, [role="alert"]');
    await expect(alert.first()).toBeVisible({ timeout: 10_000 });
  });

  test('boş form gönderimi engelleniyor', async ({ page }) => {
    await page.goto(PATHS.adminLogin);

    // HTML5 required attribute ile native validation
    const isRequired = await page.locator('#username').getAttribute('required');
    expect(isRequired !== null).toBeTruthy();
  });

  test('geçerli kimlik ile dashboard yönlendirmesi', async ({ page, browserName }) => {
    // Credential fallback — .env setup yoksa skip
    test.skip(
      !process.env.E2E_ADMIN_USERNAME && !process.env.CI,
      'E2E_ADMIN_USERNAME set edilmemiş, valid login skip',
    );

    await loginAsAdmin(page, ADMIN_CREDS.username, ADMIN_CREDS.password);
    await expect(page).toHaveURL(/\/admin\/dashboard\.php/);
  });

  test('rate limit hata path — çok hızlı invalid deneme', async ({ page }) => {
    // Opsiyonel: 10 istek/60s limit — sadece ilk 2 invalid dene, 429 beklemez
    await page.goto(PATHS.adminLogin);
    for (let i = 0; i < 2; i++) {
      await page.locator('#username').fill('rate_' + i);
      await page.locator('#password').fill('bad');
      await page.locator('form button[type="submit"]').click();
      await page.waitForLoadState('networkidle').catch(() => undefined);
    }
    // Login hala mümkün olmalı (henüz 10'a ulaşılmadı)
    await expect(page.locator('#username')).toBeVisible();
  });
});
