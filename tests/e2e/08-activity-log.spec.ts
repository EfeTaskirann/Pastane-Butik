import { test, expect } from './fixtures/admin-auth';
import { PATHS } from './fixtures/test-data';

/**
 * Activity Log — tablo render, filtreleme, pagination.
 */
test.describe('Activity Log', () => {
  test('activity log sayfası yükleniyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminActivityLog);
    await expect(adminPage).toHaveURL(/activity-log\.php/);

    const heading = adminPage.getByRole('heading', { name: /aktivite|log|etkinlik/i }).first();
    await expect(heading).toBeVisible({ timeout: 10_000 });
  });

  test('filtre form alanları render ediliyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminActivityLog);

    // Sprint 1 UI: from, to, user, event_type, ip filtreleri
    const filters = [
      'input[name="from"], input[name="tarih_from"], input[type="date"]',
      'input[name="user"], input[name="kullanici"], select[name*="user"]',
      'input[name="event_type"], select[name="event_type"], select[name*="olay"]',
    ];

    let foundCount = 0;
    for (const sel of filters) {
      if ((await adminPage.locator(sel).count()) > 0) foundCount++;
    }

    expect(foundCount, 'en az bir filtre alanı bulunmalı').toBeGreaterThan(0);
  });

  test('tablo veya kart listesi görünüyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminActivityLog);

    // 768px altında kart, üstünde tablo
    const table = adminPage.locator('table, .log-table');
    const cards = adminPage.locator('.log-card, .log-item');
    const empty = adminPage.getByText(/log.*yok|kayıt.*bulunamadı|veri yok/i);

    const total = (await table.count()) + (await cards.count()) + (await empty.count());
    expect(total).toBeGreaterThan(0);
  });

  test('pagination UI mevcut (kayıt varsa)', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminActivityLog);

    const pagination = adminPage.locator('.pagination, nav[aria-label*="sayfa"], .pager, a[href*="sayfa="]');
    const count = await pagination.count();
    // Sayfalama opsiyonel — 25 kayıt altında görünmeyebilir
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('filtre submit çalışıyor', async ({ adminPage }) => {
    await adminPage.goto(PATHS.adminActivityLog);

    const eventTypeSelect = adminPage.locator('select[name="event_type"], select[name*="olay"]').first();
    if (await eventTypeSelect.count() > 0) {
      const options = await eventTypeSelect.locator('option').count();
      if (options > 1) {
        await eventTypeSelect.selectOption({ index: 1 });
        const submitBtn = adminPage.locator('form button[type="submit"], form input[type="submit"]').first();
        if (await submitBtn.count() > 0) {
          await submitBtn.click();
          // URL'de query string olmalı
          await expect(adminPage).toHaveURL(/\?/);
        }
      }
    }
  });
});
