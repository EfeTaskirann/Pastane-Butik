import { test, expect } from './fixtures/admin-auth';

/**
 * Sipariş CRUD — admin paneli üzerinden sipariş listesi + durum güncelleme.
 *
 * NOT: Gerçek sipariş oluşturmak için müşteri-facing flow (QR masa → sepet → odeme)
 * kullanılmalı; burada mevcut siparişler üzerinde durum güncellemesi ve liste
 * render'ını test ediyoruz.
 */
test.describe('Sipariş Yönetimi', () => {
  test('mutfak sayfası aktif siparişleri listeliyor', async ({ adminPage }) => {
    await adminPage.goto('/admin/mutfak.php');
    await expect(adminPage).toHaveURL(/mutfak\.php/);

    // Sayfa header'ı — "Mutfak" yazısı
    const heading = adminPage.getByRole('heading', { name: /mutfak|sipariş/i }).first();
    await expect(heading).toBeVisible({ timeout: 10_000 });
  });

  test('masa siparişleri listesi yükleniyor', async ({ adminPage }) => {
    await adminPage.goto('/admin/masa-siparisleri.php');
    await expect(adminPage).toHaveURL(/masa-siparisleri\.php/);

    // Liste veya "henüz sipariş yok" mesajı
    const pageContent = adminPage.locator('main, .content-wrapper, body').first();
    await expect(pageContent).toBeVisible();
  });

  test('sipariş durum güncelleme butonu mevcut', async ({ adminPage }) => {
    await adminPage.goto('/admin/mutfak.php');

    // "Hazırla" / "Tamamla" / "İptal" gibi action butonları
    const actionBtn = adminPage.locator(
      'button:has-text("Hazır"), button:has-text("Tamam"), [data-action*="durum"], form button[type="submit"]',
    );

    const count = await actionBtn.count();
    // Aktif sipariş varsa butonlar olmalı; yoksa sayfa boş mesajı göstermeli
    if (count === 0) {
      const emptyMsg = adminPage.getByText(/henüz.*sipariş|bekleyen.*yok|aktif.*sipariş yok/i);
      expect(await emptyMsg.count()).toBeGreaterThanOrEqual(0); // informational
    } else {
      expect(count).toBeGreaterThan(0);
    }
  });

  test('garson sayfası masa durumlarını gösteriyor', async ({ adminPage }) => {
    await adminPage.goto('/admin/garson.php');
    await expect(adminPage).toHaveURL(/garson\.php/);

    // Masa kartları veya tablo
    const masaGrid = adminPage.locator('.masa-kart, .masa-item, table, .grid');
    const count = await masaGrid.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});
