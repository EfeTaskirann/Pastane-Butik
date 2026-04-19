import { test, expect } from './fixtures/admin-auth';
import { PATHS, SAMPLE_URUN } from './fixtures/test-data';

/**
 * Ürün Ekleme Flow — urun-ekle.php form submit + redirect + listede görünme.
 */
test.describe('Ürün Ekleme Flow', () => {
  test('ürün ekle sayfası form alanları render ediliyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminUrunEkle);

    // Admin form validator ile uyumlu — data-validate="required|..." vs.
    await expect(adminPage.locator('#isim, [name="isim"]')).toBeVisible();
    await expect(adminPage.locator('#fiyat, [name="fiyat"]')).toBeVisible();
    await expect(adminPage.locator('form button[type="submit"]')).toBeVisible();
  });

  test('geçerli form verisi ile ürün oluşturma', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminUrunEkle);

    const urun = {
      ...SAMPLE_URUN,
      isim: `E2E Test Ürün ${Date.now()}`,
    };

    await adminPage.locator('[name="isim"]').fill(urun.isim);
    await adminPage.locator('[name="fiyat"]').fill(urun.fiyat);

    // Opsiyonel alanlar
    const aciklama = adminPage.locator('[name="aciklama"]');
    if (await aciklama.count() > 0) {
      await aciklama.fill(urun.aciklama);
    }

    const stok = adminPage.locator('[name="stok"]');
    if (await stok.count() > 0) {
      await stok.fill(urun.stok);
    }

    // Kategori select — ilk seçeneği seç (boş bırakırsa validation hatası verebilir)
    const kategori = adminPage.locator('select[name="kategori_id"]');
    if (await kategori.count() > 0) {
      const options = await kategori.locator('option').count();
      if (options > 1) {
        await kategori.selectOption({ index: 1 });
      }
    }

    // Submit — redirect veya success Toast bekle
    await Promise.all([
      adminPage.waitForURL(/\/admin\/(urunler|urun-ekle|urun-duzenle)\.php/, { timeout: 15_000 }).catch(() => undefined),
      adminPage.locator('form button[type="submit"]').first().click(),
    ]);

    // Listede görünüyor mu?
    await adminPage.goto(PATHS.adminUrunler);
    const urunSatiri = adminPage.getByText(urun.isim, { exact: false });
    await expect(urunSatiri.first()).toBeVisible({ timeout: 10_000 });
  });

  test('geçersiz form — zorunlu alan boş bırakıldığında submit engelleniyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminUrunEkle);

    // isim boş — fiyat dolu
    await adminPage.locator('[name="fiyat"]').fill('99');
    await adminPage.locator('form button[type="submit"]').first().click();

    // HTML5 required veya form-validator error gösteriyor
    await adminPage.waitForTimeout(500);
    const urlAfter = adminPage.url();
    // Redirect OLMAMALI — hala ekle sayfasında
    expect(urlAfter).toContain('urun-ekle');
  });
});
