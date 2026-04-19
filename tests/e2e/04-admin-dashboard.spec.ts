import { test, expect } from './fixtures/admin-auth';
import { PATHS } from './fixtures/test-data';

/**
 * Admin Dashboard — login sonrası ana sayfa + sidebar + kritik widget'lar.
 */
test.describe('Admin Dashboard', () => {
  test('dashboard sayfası yükleniyor', async ({ adminPage }) => {
    await expect(adminPage).toHaveURL(/\/admin\/(dashboard|index)\.php/);
    await expect(adminPage).toHaveTitle(/Dashboard|Admin|Yönetim/i);
  });

  test('sidebar navigasyonu görünür', async ({ adminPage }) => {
    // Sidebar link'leri (ürünler/kategoriler/siparişler vb.)
    const sidebar = adminPage.locator('aside, .sidebar, nav.admin-nav').first();
    await expect(sidebar).toBeVisible();

    const criticalLinks = [
      /ürün|urun/i,
      /kategori/i,
      /sipariş|siparis/i,
    ];

    for (const linkText of criticalLinks) {
      const link = adminPage.getByRole('link', { name: linkText }).first();
      const count = await link.count();
      expect(count, `"${linkText}" linki sidebar'da olmalı`).toBeGreaterThan(0);
    }
  });

  test('CSRF meta tag veya token footer\'da basılı', async ({ adminPage }) => {
    // Admin sayfalarında CSRF token görünür olmalı (JS POST'ları için)
    const csrfMeta = adminPage.locator('meta[name="csrf-token"]');
    const csrfInput = adminPage.locator('input[name="csrf_token"], input[name="_token"]');

    const count = (await csrfMeta.count()) + (await csrfInput.count());
    expect(count, 'en az bir CSRF token reference bulunmalı').toBeGreaterThan(0);
  });

  test('dashboard kritik özet kartları render', async ({ adminPage }) => {
    // Stat card'lar — numbers/counters/summary
    const statCards = adminPage.locator('.stat-card, .dashboard-card, .widget, .card');
    const count = await statCards.count();
    expect(count, 'en az bir özet kartı görünmeli').toBeGreaterThan(0);
  });

  test('logout linki mevcut', async ({ adminPage }) => {
    const logout = adminPage.locator(
      'a[href*="logout"], button[name="logout"], form:has(input[name="logout"])',
    );
    expect(await logout.count()).toBeGreaterThan(0);
  });

  test('session devam ediyor — ikinci sayfaya geçişte tekrar giriş istemiyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminUrunler);
    // Login'e redirect OLMAMALI
    await expect(adminPage).not.toHaveURL(/admin\/index\.php/);
  });
});
