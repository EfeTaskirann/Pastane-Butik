/**
 * Ortak test sabitleri — selector'lar, URL path'leri, sample data.
 *
 * Not: data-testid attribute'u mevcut olmayan admin sayfaları için
 * role/label/text-based selector'lar kullanıyoruz (a11y friendly).
 */

export const PATHS = {
  home: '/',
  menu: '/menu/',
  adminLogin: '/admin/index.php',
  adminDashboard: '/admin/dashboard.php',
  adminUrunEkle: '/admin/urun-ekle.php',
  adminUrunler: '/admin/urunler.php',
  adminMasalar: '/admin/masalar.php',
  adminActivityLog: '/admin/activity-log.php',
  adminAyarlar: '/admin/ayarlar/index.php',
  apiHealth: '/api/health.php',
  apiHealthLive: '/api/health.php/live',
  apiHealthReady: '/api/health.php/ready',
} as const;

export const SAMPLE_URUN = {
  isim: `E2E Test Ürün ${Date.now()}`,
  fiyat: '45.50',
  aciklama: 'Playwright E2E otomasyonu tarafından oluşturuldu',
  stok: '10',
};

export const SAMPLE_MASA = {
  numara: `99${Math.floor(Math.random() * 100)}`,
  kapasite: '4',
};

/**
 * Yaygın timeout'lar.
 */
export const TIMEOUTS = {
  short: 3_000,
  medium: 10_000,
  long: 20_000,
} as const;
