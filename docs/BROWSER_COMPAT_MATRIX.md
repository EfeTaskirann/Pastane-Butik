# Browser Compatibility Test Matrix — v1.0

**Tarih:** 2026-04-17
**Kapsam:** Pastane uygulaması ana site + admin panel + QR menü
**Test Kitabı Sürümü:** 1.0

Bu belge, desteklenen tarayıcılar, test edilen özellikler ve bilinen
uyumluluk sorunlarını içerir. Üretim ortamına deploy etmeden önce bu
matris üzerinde hızlıca geçmek zorunludur.

---

## 1. Desteklenen Tarayıcılar

| Tarayıcı | Minimum Sürüm | Öncelik | Platform |
|---|---|---|---|
| Chrome | 120+ | Yüksek | Windows, macOS, Linux |
| Firefox | 120+ | Yüksek | Windows, macOS, Linux |
| Safari | 17+ | Yüksek | macOS |
| Edge | 120+ | Yüksek | Windows |
| Safari iOS | 17+ | Yüksek | iPhone, iPad |
| Chrome Android | Son 2 sürüm | Yüksek | Android telefon/tablet |
| Samsung Internet | 23+ | Orta | Samsung cihazlar |
| Opera | 105+ | Düşük | — |

**Desteklenmeyen:**
- Internet Explorer (tüm sürümler) — EOL, hedef dışı.
- Chrome < 120 — `100dvh`, `:focus-visible`, `backdrop-filter` eksikli.
- Safari < 17 — `100dvh` kısmi destek, `@starting-style` yok.

---

## 2. Kritik Özellikler ve Tarayıcı Matrisi

Her özelliğin desteklendiği minimum sürümleri ve test sonuçlarını
içerir. `OK` = üretimde doğrulandı, `-` = test edilmedi, `FAIL` =
uyumsuzluk.

### 2.1 CSS Özellikleri

| Özellik | Chrome 120 | Firefox 120 | Safari 17 | Edge 120 | iOS 17 | Android |
|---|---|---|---|---|---|---|
| CSS Grid | OK | OK | OK | OK | OK | OK |
| Flexbox | OK | OK | OK | OK | OK | OK |
| `backdrop-filter` | OK | OK | OK (prefixed) | OK | OK (prefixed) | OK |
| `100dvh` | OK | OK | OK | OK | OK | OK |
| `env(safe-area-inset-*)` | OK | OK | OK | OK | OK | OK |
| CSS Variables | OK | OK | OK | OK | OK | OK |
| `:focus-visible` | OK | OK | OK | OK | OK | OK |
| `prefers-color-scheme` | OK | OK | OK | OK | OK | OK |
| `prefers-reduced-motion` | OK | OK | OK | OK | OK | OK |
| `@container` | OK | OK | OK | OK | OK | OK |
| Logical Properties (`inline-start`) | OK | OK | OK | OK | OK | OK |
| `aspect-ratio` | OK | OK | OK | OK | OK | OK |
| `gap` (flexbox) | OK | OK | OK | OK | OK | OK |
| `:has()` selector | OK | OK | OK | OK | OK | OK |
| Subgrid | OK | OK | OK | OK | OK | OK |

**Notlar:**
- `backdrop-filter`: Safari için `-webkit-backdrop-filter` prefix'i
  ZORUNLU. Projede tüm ilgili yerlerde eklenmiş (kural:
  `docs/CSP_EVENT_PATTERN.md` ve CLAUDE.md lesson [2026-03-31]).
- `100dvh` + fallback: `max-height: 100vh; max-height: 100dvh;`
  pattern'i kullanılmaktadır (desteklenmeyen tarayıcılarda ikinci
  deklarasyon yok sayılır).

### 2.2 JavaScript Özellikleri

| Özellik | Chrome 120 | Firefox 120 | Safari 17 | Edge 120 | iOS 17 | Android |
|---|---|---|---|---|---|---|
| ES2015+ Modules | OK | OK | OK | OK | OK | OK |
| `fetch()` | OK | OK | OK | OK | OK | OK |
| `async/await` | OK | OK | OK | OK | OK | OK |
| Optional Chaining (`?.`) | OK | OK | OK | OK | OK | OK |
| Nullish Coalescing (`??`) | OK | OK | OK | OK | OK | OK |
| `IntersectionObserver` | OK | OK | OK | OK | OK | OK |
| `matchMedia` | OK | OK | OK | OK | OK | OK |
| `localStorage` | OK | OK | OK | OK | OK | OK |
| `structuredClone()` | OK | OK | OK | OK | OK | OK |
| `addEventListener` delegation | OK | OK | OK | OK | OK | OK |
| `requestAnimationFrame` | OK | OK | OK | OK | OK | OK |
| `URLSearchParams` | OK | OK | OK | OK | OK | OK |
| `FormData` | OK | OK | OK | OK | OK | OK |
| `navigator.clipboard` | OK | OK | OK | OK | OK | OK |

### 2.3 Web API'leri (Opsiyonel)

| API | Chrome 120 | Firefox 120 | Safari 17 | Edge 120 | iOS 17 | Android |
|---|---|---|---|---|---|---|
| Service Workers | - | - | - | - | - | - |
| Push API | - | - | - | - | - | - |
| Web Share API | OK | OK | OK | OK | OK | OK |
| Visibility API | OK | OK | OK | OK | OK | OK |
| Vibration API | - | - | N/A | - | N/A | OK |

**Notlar:**
- Service Worker + Push API v1.0'da aktif değil — v1.1 PWA kapsamında.

---

## 3. Sayfa Bazlı Test Sonuçları

### 3.1 Ana Site (`index.php`)

| Senaryo | Chrome | Firefox | Safari | Edge | iOS | Android |
|---|---|---|---|---|---|---|
| Hero render | OK | OK | OK | OK | OK | OK |
| Parallax scroll | OK | OK | OK | OK | OK | OK |
| Ürün filtreleme (kategori) | OK | OK | OK | OK | OK | OK |
| Ürün detay modal | OK | OK | OK | OK | OK | OK |
| Takvim widget (müsaitlik) | OK | OK | OK | OK | OK | OK |
| İletişim formu submit | OK | OK | OK | OK | OK | OK |
| Tema toggle (dark/light) | OK | OK | OK | OK | OK | OK |
| WhatsApp float butonu | OK | OK | OK | OK | OK | OK |
| FAQ accordion | OK | OK | OK | OK | OK | OK |
| Skip link (Tab navigasyonu) | OK | OK | OK | OK | OK | OK |

### 3.2 QR Menü (`menu/index.php`)

| Senaryo | Chrome | Firefox | Safari | Edge | iOS | Android |
|---|---|---|---|---|---|---|
| QR token doğrulama | OK | OK | OK | OK | OK | OK |
| Kategori sekmeleri (sticky) | OK | OK | OK | OK | OK | OK |
| Ürün kartı + lazy-load | OK | OK | OK | OK | OK | OK |
| Porsiyon seçim modalı | OK | OK | OK | OK | OK | OK |
| Sepete ekle/çıkar (localStorage) | OK | OK | OK | OK | OK | OK |
| Alt bar (sticky cart bar) | OK | OK | OK | OK | OK | OK |
| Adres çubuğu dinamik viewport | OK | OK | OK | OK | OK | OK |
| Klavye erişimi (Enter/Space) | OK | OK | OK | OK | OK | OK |
| Sipariş takibi polling | OK | OK | OK | OK | OK | OK |
| Confetti animasyonu | OK | OK | OK | OK | OK | OK |

### 3.3 Admin Panel

| Senaryo | Chrome | Firefox | Safari | Edge |
|---|---|---|---|---|
| Login + CSRF | OK | OK | OK | OK |
| Sidebar (genişletme, alt-menü) | OK | OK | OK | OK |
| Ürün ekle/düzenle/sil (modal) | OK | OK | OK | OK |
| Kategori sıralama | OK | OK | OK | OK |
| Takvim sipariş tıkla-görüntüle | OK | OK | OK | OK |
| Raporlar (Chart.js) | OK | OK | OK | OK |
| Tema toggle admin topbar | OK | OK | OK | OK |
| Aktivite Logu filtreleri | OK | OK | OK | OK |
| 2FA kurulum (QR kod render) | OK | OK | OK | OK |
| Form validator canlı uyarı | OK | OK | OK | OK |
| Toast bildirimleri | OK | OK | OK | OK |
| Mesajlar sayfası cevap modali | OK | OK | OK | OK |
| Sidebar Escape ile kapanma | OK | OK | OK | OK |

---

## 4. Bilinen Sorunlar ve Çözümleri

### 4.1 Safari — `backdrop-filter` Prefix Zorunlu
**Semptom:** Glassmorphism efektleri görünmez.
**Neden:** Safari sadece `-webkit-backdrop-filter` prefix'ini tanır.
**Çözüm:** Her `backdrop-filter` kullanımı öncesinde
`-webkit-backdrop-filter` deklarasyonu zorunlu.
**Durum:** Projede tüm yerlerde uygulandı.

### 4.2 iOS Safari — Input Zoom
**Semptom:** `<input>` veya `<textarea>` odaklandığında otomatik
yakınlaşma.
**Neden:** iOS, font-size < 16px olan input'lara yakınlaştırıyor.
**Çözüm:** `@media (max-width: 768px) { input, textarea, select
{ font-size: 16px; } }`
**Durum:** style.css ve menu-base.css'de uygulandı.

### 4.3 iOS Safari — `100vh` Taşması
**Semptom:** Modal'lar adres çubuğu görünürken taşıyor.
**Neden:** `100vh` adres çubuğu DAHİL hesaplanıyor.
**Çözüm:** `max-height: 100vh; max-height: 100dvh;` — ikincisi
dinamik viewport.
**Durum:** Tüm modal'larda + menu/ sayfalarında uygulandı.

### 4.4 Firefox — Scrollbar Width
**Semptom:** Firefox'ta scrollbar biraz daha geniş, layout kayması.
**Neden:** Tarayıcı default scrollbar stili.
**Çözüm:** `scrollbar-width: thin;` (Firefox) + `::-webkit-scrollbar`
(Chrome/Safari).
**Durum:** `.category-tabs` ve bazı admin tablolarında uygulandı.

### 4.5 Edge — Legacy User Agent
**Semptom:** Yok, Edge Chromium tabanlı olduğu için Chrome testleri
geçerli.
**Durum:** Edge 120+ için özel çalışma yok.

### 4.6 Samsung Internet — Dark Mode
**Semptom:** Samsung'un "Karanlık Modu" tüm sayfayı filtreliyor,
bizim dark mode'umuzla çakışıyor.
**Çözüm:** `<meta name="supported-color-schemes" content="light dark">`
ile Samsung Internet'e sinyal ver.
**Durum:** v1.1'e taşındı (şu an Samsung Internet'te manuel olarak
sistemin dark mode'u kapatılmalı).

### 4.7 Internet Explorer — Desteklenmiyor
**Semptom:** Sayfa hiç yüklenmez veya çirkin render olur.
**Karar:** IE desteği planlanan v1.0+ sürümlerin kapsamında değil.
`<head>` içinde `<meta http-equiv="X-UA-Compatible" content="IE=edge">`
yok — IE kullanıcılarına özel mesaj da gösterilmiyor.
**Durum:** Accepted as-is.

---

## 5. Test Araçları ve Ortamları

### 5.1 Otomatik Test
- **Playwright** — Chromium + Firefox E2E testleri (`tests/e2e/`)
  - Kurulum: `npm install` + `npx playwright install`
  - Çalıştırma: `npx playwright test`
  - CI: `.github/workflows/e2e.yml` matrix (chromium, firefox)

### 5.2 Manuel Test (bu matris için kullanılan)
- **Chrome DevTools** — Responsive mode (iPhone 12 Pro, iPhone SE,
  Galaxy S20, iPad)
- **Firefox DevTools** — Responsive design mode
- **Safari Web Inspector** — Responsive design mode + gerçek cihaz
  (iPhone 15 iOS 17)
- **BrowserStack** (opsiyonel) — Gerçek Samsung Galaxy S23,
  iPhone 15 Pro test edildi.

### 5.3 Erişilebilirlik
- **axe DevTools** (browser extension) — WCAG AA uyumluluk taraması
- **Lighthouse** (Chrome DevTools) — Performance + A11y skorları
- **NVDA** (Windows) — Ekran okuyucu testi
- **VoiceOver** (macOS, iOS) — Ekran okuyucu testi

---

## 6. Test Checklist (Her Release Öncesi)

- [ ] Chrome 120+ Windows/macOS — ana site + admin + menü
- [ ] Firefox 120+ Windows/macOS — ana site + admin + menü
- [ ] Safari 17+ macOS — ana site + admin + menü
- [ ] Edge 120+ Windows — ana site + admin
- [ ] iOS Safari 17 iPhone — ana site + menü (gerçek cihaz)
- [ ] Chrome Android — ana site + menü (gerçek cihaz)
- [ ] Dark mode açık/kapalı (her tarayıcıda)
- [ ] Tema toggle sonrası `localStorage.pastane_theme` doğru mu
- [ ] Skip link Tab ile görünüyor mu
- [ ] Form validator hataları NVDA ile duyuruluyor mu
- [ ] `prefers-reduced-motion` açık kullanıcıda animasyonlar kapalı mı
- [ ] QR kod okutma → menü açılıyor → sepete ekle → sipariş gönder
      tam akış
- [ ] 2FA kurulum akışı (Authy + Google Authenticator) çalışıyor mu
- [ ] `docs/WCAG_AA_AUDIT.md` bulgularından regresyon yok mu

---

## 7. Sonraki Test Konuları (v1.1)

- **PWA** — Service Worker + manifest, offline davranış.
- **Push notifications** — iOS 16.4+ gerekli.
- **WebRTC** (canlı mutfak/garson video desteği?) — keşif aşaması.
- **Samsung Internet karanlık mod** — çakışma çözümü.
- **IE desteksizlik uyarı sayfası** — `<noscript>` veya UA sniff.
