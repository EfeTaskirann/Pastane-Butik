# Frontend Release Notes — v1.0

**Yayın Tarihi:** 2026-04-17
**Sürüm:** 1.0.0 (Production Launch)
**Hedef Kitle:** Son kullanıcılar, restoran personeli, admin kullanıcıları

Bu doküman, v1.0 sürümüyle birlikte kullanıcıya görünen tüm değişiklikleri
(yeni özellikler, iyileştirmeler, erişilebilirlik kazançları ve bilinen
sınırlamalar) özetler. Geliştiriciler için teknik değişiklik kayıtları
`tasks/` dizinindeki sprint raporlarında bulunabilir.

---

## 1. Yeni Özellikler

### 1.1 Koyu Tema (Dark Mode)
- **Nerede:** Ana site sağ-üst köşe toggle butonu, admin panel üst bar.
- Kullanıcı tercihi `localStorage` ile kalıcı olarak saklanır.
- İlk ziyarette sistem tercihi (`prefers-color-scheme`) otomatik algılanır.
- FOUC (flash of unstyled content) engellenmiştir — sayfa render edilmeden
  önce tema uygulanır.
- Tema değişikliği `theme-color` meta etiketini de günceller (iOS Safari
  adres çubuğu rengi).

### 1.2 İki Faktörlü Kimlik Doğrulama (2FA)
- **Nerede:** Admin panel → Ayarlar → İki Faktör (2FA).
- TOTP standardı (Google Authenticator, Authy, 1Password uyumlu).
- QR kod ile kurulum + manuel secret girişi fallback.
- 10 adet tek kullanımlık yedek kod indirilebilir `.txt` dosyası olarak
  sunulur (sadece kurulum anında, sonradan gösterilmez).
- Devre dışı bırakma için mevcut 2FA kodu doğrulaması zorunlu.

### 1.3 Aktivite Logu Gezgini
- **Nerede:** Admin panel → Aktivite Logları.
- Tüm güvenlik olayları ve kritik admin işlemleri kaydedilir.
- Filtreler: tarih aralığı (from/to), kullanıcı, olay tipi, IP adresi.
- Her kayıt için JSON detay modalı ile ham veri incelenebilir.
- 768px altında tablo kart görünümüne dönüşür (mobil okunabilirlik).

### 1.4 Ayarlar Paneli
- **Nerede:** Admin panel → Ayarlar → Genel / Email (SMTP) / SMS /
  Yedekleme / 2FA.
- 5 ayrı grup (site, iletişim, sipariş, ödeme, bildirim) ve tab navigasyonu.
- Tip-bilinçli input'lar: string, integer, boolean (toggle), JSON.

### 1.5 Dijital Menü (QR Kod ile)
- **Nerede:** Masadaki QR kod okutulduğunda `menu/` altındaki sayfa açılır.
- Mobil öncelikli tasarım, 320/375/768px breakpoint'leri.
- Kategori sekmeleri, porsiyon seçim modalı, localStorage tabanlı sepet.
- Gerçek zamanlı sipariş takibi (siparis-takip.php) — 15 saniye polling.
- Sipariş "Hazır" olduğunda confetti + overlay bildirimi.

### 1.6 Yedekleme Yönetimi
- **Nerede:** Admin panel → Ayarlar → Yedekleme.
- Manuel yedek oluşturma, listeleme, indirme.
- Otomatik cron yedeği durumu (25 saat WARN, 48 saat CRIT eşikleri).

---

## 2. UX İyileştirmeleri

### 2.1 Toast Bildirim Sistemi
- **Konum:** Sağ-üst köşe, 4 adet stacked, otomatik 4 saniyede kapanır.
- 4 tip: success, error, info, warning.
- `role="status"` + `aria-live="polite"` (error: `role="alert"` +
  `aria-live="assertive"`) — ekran okuyucular anında duyurur.
- Hover veya fokus sırasında otomatik kapatma duraklatılır.

### 2.2 Yükleme Göstergeleri (Loading States)
- Form submit sırasında otomatik disabled + spinner.
- `<form data-loading>` özniteliği ile opt-in.
- Uzun işlemler için `Loading.wrap(fn)` helper'ı.

### 2.3 Form Doğrulama (Client-side)
- Vanilla JS, `data-validate` özniteliği ile declarative.
- 7 kural: `required`, `email`, `min`, `max`, `numeric`, `phone-tr`, `match`.
- Blur + throttle (200ms) edilmiş input olayında canlı doğrulama.
- İlk hatalı alana otomatik fokus + Toast ile özet hata mesajı.
- `aria-live="polite"` hata mesajları ile ekran okuyucu uyumlu.

### 2.4 Mobil Responsive İyileştirmeleri
- 3 kırılma noktası (320 / 375 / 768px) tam denetlendi.
- `100dvh` dinamik viewport — iOS Safari adres çubuğu taşma sorunu
  çözüldü.
- Touch target'lar minimum 44x44 px (WCAG 2.5.5 Level AAA).
- iOS input zoom sorunu (`font-size: 16px`) çözüldü.
- Admin sidebar Escape tuşuyla kapanır, toggle'a fokus geri döner.
- Menü kategori sekmeleri yatay kaydırılabilir, aktif sekme görünür
  alana otomatik kaydırılır.

### 2.5 Porsiyon Seçim Modalı
- Bottom-sheet tasarım (mobil uyumlu), üstten aşağı kayma animasyonu.
- 4/6/8/10 kişilik seçenekler + "Normal" fiyat.
- Klavye erişimi: portion-option Enter/Space ile seçilebilir.

### 2.6 Sipariş Durum Takibi
- 4 adımlı progress bar (Beklemede → Hazırlanıyor → Hazır → Teslim).
- Canlı polling göstergesi — sayfa görünür olduğunda aktif, arkaplan
  sekmede duraklatılır (`visibilitychange` API).

---

## 3. Erişilebilirlik (A11y) İyileştirmeleri

### 3.1 WCAG 2.1 Level AA Uyumluluğu
- Tüm metin/arkaplan kontrastları 4.5:1 minimum sağlıyor (büyük metin
  3:1). Audit raporu: `docs/WCAG_AA_AUDIT.md`.
- Düşük kontrast renkleri düzeltildi: `--text-secondary` (3.73→5.82),
  `.product-card__desc` (3.21→5.25), `.filter-btn` text-primary'ye
  yükseltildi.

### 3.2 Skip Link
- Her sayfa başında "İçeriğe atla" linki (`Tab` ilk basıldığında
  görünür).
- Ana içerik bölümü programmatically fokus alabilir (`tabindex="-1"`).

### 3.3 Fokus Görünürlüğü
- Tüm interaktif elemanlarda `:focus-visible` ile belirgin altın
  outline (3px, offset 2px).
- Klavye navigasyonu mouse kullanıcılarını rahatsız etmiyor.

### 3.4 ARIA Öznitelikleri
- Tüm dekoratif elemanlar `aria-hidden="true"` ile işaretli (SVG
  ikonları, süslemeler).
- Sidebar navigasyon `aria-expanded`, `aria-controls` ile senkron.
- Toggle butonları `aria-pressed` ile durum bildirir.
- Alternatif metinler tüm ürün görsellerinde mevcut.

### 3.5 Motion-safe Animasyonlar
- `prefers-reduced-motion: reduce` desteği tüm animasyonlarda.
- Vestibular bozukluğu olan kullanıcılar için sakin deneyim.

### 3.6 Semantik HTML
- `<main>`, `<nav>`, `<section>`, `<header>`, `<footer>` doğru
  kullanımı.
- Başlık hiyerarşisi (h1 → h2 → h3) her sayfada tutarlı.

---

## 4. Güvenlik-odaklı Frontend Geliştirmeler

### 4.1 CSP (Content Security Policy) Uyumu
- Admin panelindeki tüm inline `onclick=` handler'ları event delegation
  pattern'e dönüştürüldü (data-action + addEventListener).
- Nonce'lı `<script>` blokları ile güvenli inline script desteği.

### 4.2 CSRF Koruması
- Tüm form gönderimlerinde `csrfTokenField()` helper'ı.
- Admin oturum kapatma işlemi POST + CSRF zorunlu (GET ile tetiklenemez).

### 4.3 XSS Önlemi
- Kullanıcıdan gelen tüm veri `e()` / `htmlspecialchars()` ile escape.
- JS context'te PHP değişkenleri `json_encode(JSON_HEX_*)` ile geçirilir.

---

## 5. Metin ve Kopya İyileştirmeleri (Sprint 4)

- Türkçe diakritik eksikleri düzeltildi ("Tumu" → "Tümü", "Urun" →
  "Ürün", "Siparis" → "Sipariş" vb.).
- Admin sidebar etiketleri tutarlı hale getirildi ("Masa Siparisleri" →
  "Masa Siparişleri", "Mutfak Ekrani" → "Mutfak Ekranı").
- Ayrıntılı değişiklik listesi: `docs/COPY_POLISH_SPRINT4.md`.

---

## 6. Bilinen Sınırlamalar

### 6.1 Internet Explorer Desteği Yok
- v1.0, IE11 ve altını desteklemez. Modern tarayıcılar için optimize
  edildi. Bkz. `docs/BROWSER_COMPAT_MATRIX.md`.

### 6.2 Bazı Admin Sayfalarında Eski `onchange` Kullanımı
- `admin/musteriler.php` içinde iki adet inline `onchange="this.form.submit()"`
  hala mevcut (select otomatik gönderim). v1.1'e taşındı.
- CSP ihlalinden dolayı strict CSP açıldığında bu select'ler tetiklenmeyecek.

### 6.3 QR Menü — Eski onclick (Sprint 4'te giderildi)
- v1.0-rc döneminde `menu/index.php` ve `menu/siparis-takip.php`
  dosyalarında 8 adet inline `onclick` bulunuyordu. Sprint 4'te tamamı
  data-action delegation pattern'e taşındı.
- Artık menu sayfaları da strict CSP ile uyumlu.

### 6.4 QR Kod Üretimi — Google Charts CDN
- Admin 2FA QR kodu `chart.googleapis.com` üzerinden sunulur. Google
  Charts API bir gün kapanırsa pure-PHP QR üretimi eklenmelidir.
- Secret URL'de yer alır — privacy için `referrerpolicy="no-referrer"`.

### 6.5 E2E Test Altyapısı Hazır, Henüz Çalıştırılmadı
- Playwright config'i (`playwright.config.ts`) ve 10 spec dosyası
  oluşturuldu; fakat `npm install` ve `npx playwright install`
  adımları v1.0 release'inde çalıştırılmadı. İlk production
  deploy sonrası ayrıca çalıştırılacak.

### 6.6 Uptime Metrikleri Windows'ta Eksik
- Dev ortamında (XAMPP) `/proc/uptime` yok, sağlık endpoint'i
  `uptime_sec: 0` döner. Linux production'da doğru değerler beklenir.

---

## 7. Tarayıcı Uyumluluğu

Tam matris için bkz. `docs/BROWSER_COMPAT_MATRIX.md`.

| Tarayıcı | Minimum Sürüm | Durum |
|---|---|---|
| Chrome | 120+ | Tam destek |
| Firefox | 120+ | Tam destek |
| Safari (masaüstü) | 17+ | Tam destek |
| Safari (iOS) | 17+ | Tam destek |
| Edge | 120+ | Tam destek |
| Chrome (Android) | Son 2 sürüm | Tam destek |

---

## 8. Performans Notları

- `assets/css/style.css` versiyon parametresi (`?v=4`) ile cache
  busting yapılır. Major CSS güncellemelerinde `v` artırılmalı.
- `assets/js/main.js` ve `assets/js/theme-switcher.js` async/defer
  değil — layout dependency'leri var, bilinçli tercih.
- Ürün görselleri `loading="lazy"` ile tembel yüklenir.
- QR menü sayfasında IntersectionObserver tabanlı lazy-load.

---

## 9. Sonraki Sürüm (v1.1) için Planlananlar

- `admin/musteriler.php` içindeki inline `onchange` → delegation.
- Pure-PHP QR kod üretimi (Google Charts bağımlılığını kaldır).
- E2E testlerin CI'da rutin çalıştırılması.
- Menu sayfasında push notification (sipariş hazır bildirimi).
- Progressive Web App (PWA) manifest + service worker.

---

## 10. Kredi

Sprint 4 Frontend ekibi:
- Copy polish + UI touch + onclick cleanup + release notes.

İlgili raporlar:
- `docs/MOBILE_UX_AUDIT.md`
- `docs/WCAG_AA_AUDIT.md`
- `docs/COPY_POLISH_SPRINT4.md`
- `docs/BROWSER_COMPAT_MATRIX.md`
- `docs/CSP_EVENT_PATTERN.md`
