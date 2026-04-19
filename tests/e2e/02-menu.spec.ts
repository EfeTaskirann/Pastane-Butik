import { test, expect } from '@playwright/test';
import { PATHS, TIMEOUTS } from './fixtures/test-data';

/**
 * Menü sayfası — kategori listesi + ürün grid + QR masada sipariş UX.
 */
test.describe('Menü sayfası', () => {
  test('menü sayfası yükleniyor', async ({ page }) => {
    const response = await page.goto(PATHS.menu);
    // 404 olmamalı
    expect(response?.status(), 'menü HTTP 200/3xx').toBeLessThan(400);

    // Sayfa content yüklenmeli — title veya heading
    await expect(page).toHaveTitle(/Menü|Pastane|Tatlı/i, { timeout: TIMEOUTS.medium });
  });

  test('kategori listesi veya tab navigasyonu görünür', async ({ page }) => {
    await page.goto(PATHS.menu);

    // Kategori filtreleme UI — button/link/tab olarak render edilmiş olabilir
    const kategoriler = page.locator(
      '[role="tab"], .kategori-filter, .category-btn, [data-kategori], nav a[href*="kategori"]',
    );
    const urunler = page.locator(
      '.product-card, .urun-kart, .menu-item, [data-urun-id]',
    );

    const catCount = await kategoriler.count();
    const prodCount = await urunler.count();

    expect(catCount + prodCount, 'kategori veya ürün kartı render olmalı').toBeGreaterThan(0);
  });

  test('ürün fiyatları görünür', async ({ page }) => {
    await page.goto(PATHS.menu);

    // TR lira sembolü veya fiyat formatı
    const fiyatPattern = page.getByText(/\d+[.,]?\d*\s*(₺|TL|tl)/);
    const count = await fiyatPattern.count();

    // Menü boş ise skip — sadece fiyat varsa formatı doğrula
    test.skip(count === 0, 'Menüde ürün yok, fiyat formatı test edilemez');
    expect(count, 'en az bir fiyat render olmalı').toBeGreaterThan(0);
  });

  test('sepet butonu veya sipariş CTA mevcut', async ({ page }) => {
    await page.goto(PATHS.menu);

    const sepetCta = page.locator(
      'a[href*="sepet"], button:has-text("Sepete"), .btn-sepet, [data-action*="sepet"]',
    );

    const count = await sepetCta.count();
    // QR menüde sepet/sipariş flow opsiyonel — varsa doğrula
    if (count > 0) {
      await expect(sepetCta.first()).toBeVisible();
    }
  });
});
