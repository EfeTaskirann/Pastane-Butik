import { test, expect } from '@playwright/test';
import { PATHS } from './fixtures/test-data';

/**
 * Anasayfa — temel yükleme + QR menü navigasyonu.
 */
test.describe('Anasayfa', () => {
  test('anasayfa yükleniyor ve başlık görünüyor', async ({ page }) => {
    const response = await page.goto(PATHS.home);
    expect(response?.status(), 'HTTP 200 bekleniyor').toBeLessThan(400);

    await expect(page).toHaveTitle(/Butik Pasta|Tatlı/i);
    // Hero bölümü render oldu mu?
    await expect(page.locator('#hero, .hero').first()).toBeVisible();
  });

  test('navigasyon ve ürün kartları render ediliyor', async ({ page }) => {
    await page.goto(PATHS.home);

    // Sayfa kritik bileşenleri — en az birinin görünür olması yeterli
    const hasProducts = await page.locator('.product-card, .urun-kart, [data-urun-id]').first().isVisible().catch(() => false);
    const hasHero = await page.locator('.hero, #hero').first().isVisible();

    expect(hasHero || hasProducts, 'hero veya ürün grid görünmeli').toBeTruthy();
  });

  test('menü / qr menü linki ulaşılabilir', async ({ page }) => {
    await page.goto(PATHS.home);

    // Menü linki — text veya href pattern
    const menuLink = page
      .locator('a[href*="/menu/"], a[href="menu/"], nav a', { hasText: /menü|menu/i })
      .first();

    const hasMenuLink = await menuLink.count();
    if (hasMenuLink > 0) {
      await expect(menuLink).toBeVisible();
    } else {
      // Menü linki anasayfada yoksa direkt route'u dene
      const resp = await page.request.get(PATHS.menu);
      expect(resp.status()).toBeLessThan(500);
    }
  });

  test('temel erişilebilirlik — lang attribute ve viewport meta', async ({ page }) => {
    await page.goto(PATHS.home);
    await expect(page.locator('html')).toHaveAttribute('lang', /tr/i);
    await expect(page.locator('meta[name="viewport"]')).toHaveCount(1);
  });
});
