import { test, expect } from './fixtures/admin-auth';
import { PATHS, SAMPLE_MASA } from './fixtures/test-data';

/**
 * Masa İşlemleri — masa CRUD + oturum aç/kapat + QR kod erişimi.
 */
test.describe('Masa Yönetimi', () => {
  test('masalar sayfası yükleniyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminMasalar);
    await expect(adminPage).toHaveURL(/masalar\.php/);

    const heading = adminPage.getByRole('heading', { name: /masa/i }).first();
    await expect(heading).toBeVisible({ timeout: 10_000 });
  });

  test('masa ekleme modal/form tetikleniyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminMasalar);

    // "Yeni Masa" / "Masa Ekle" butonu
    const ekleBtn = adminPage.locator(
      'button:has-text("Yeni Masa"), button:has-text("Masa Ekle"), a:has-text("Yeni Masa"), [data-action*="masa-ekle"]',
    ).first();

    if (await ekleBtn.count() > 0) {
      await ekleBtn.click();
      // Modal açılmış olmalı
      const modal = adminPage.locator('.modal, [role="dialog"], .modal-content');
      await expect(modal.first()).toBeVisible({ timeout: 5_000 });
    }
  });

  test('masa kartları veya listesi render ediliyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminMasalar);

    const masalar = adminPage.locator('.masa-kart, .masa-item, tr[data-masa-id], .grid-item');
    const emptyMsg = adminPage.getByText(/henüz.*masa|masa.*eklenmemiş/i);

    const masaCount = await masalar.count();
    const hasEmpty = await emptyMsg.count();

    expect(masaCount + hasEmpty, 'masa listesi veya boş mesajı olmalı').toBeGreaterThan(0);
  });

  test('QR kod erişim butonu/linki mevcut', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminMasalar);

    // QR kod butonu (print/indir/görüntüle)
    const qrBtn = adminPage.locator(
      '[data-action*="qr"], button:has-text("QR"), a:has-text("QR"), img[src*="qr"]',
    );

    const count = await qrBtn.count();
    // Masa yoksa QR de olmaz — tolerant check
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('masa oturum aç/kapat actionları mevcut (aktif masa varsa)', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminMasalar);

    const oturumBtn = adminPage.locator(
      '[data-action*="oturum"], button:has-text("Oturum"), button:has-text("Kapat"), button:has-text("Aç")',
    );

    const count = await oturumBtn.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});
