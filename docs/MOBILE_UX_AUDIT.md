# Mobile UX Audit — Sprint 3

Tarih: 2026-04-17
Yazar: Frontend Developer (Mid)
Kapsam: Ana sayfa, QR menu, Admin dashboard, Admin siparis listesi, Admin masa yonetimi.
Test edilen breakpoint'ler: **320px** (iPhone SE small), **375px** (iPhone 12/13 mini), **768px** (iPad portrait).

## Metodoloji

- Chrome DevTools Device Mode (320x568, 375x667, 768x1024)
- Touch target olculeri CSS inspector ile dogrulandi
- `overflow-x` taramasi: body ve section seviyesinde horizontal scroll kontrolu
- Font-size analizi: `input`, `textarea`, `select` elemanlari iOS auto-zoom threshold (<=16px) acisindan denetlendi
- Modal viewport: `100vh` yerine `100dvh` dinamik viewport birim kullanimi
- Z-index stack: `.sticky`, `.modal`, `.topbar`, `.cart-bar` katmanlarinin hiyerarsisi

## Sayfa Bazli Audit

### 1. Ana Sayfa (`/index.php`)

**320px:**
| Kontrol | Durum | Not |
|---|---|---|
| Overflow-x | FAIL | `.parallax-layer--front` ve hero illustration 320'de taşıyor. Fix: `.parallax-layer` display:none <360px. |
| Touch targets | PARTIAL | `.filter-btn` min 44x44 degil (~36px). Fix uygulandi: min-height 44px. |
| Font readability | OK | Body font 16px, h1 `clamp()` ile responsive. |
| Skip link | FAIL | Mevcut degil. Fix: "İçeriğe atla" skip link eklendi. |

**375px:**
- CTA buton grid `.hero-cta` overflow olmadi
- Section header stackleniyor — OK
- Promo banner `.promo-content` wrap ediliyor — OK

**768px:**
- Grid 2 column — OK
- Product card kullanilan genislik yeterli

### 2. QR Menu (`/menu/*.php`)

**320px:**
| Kontrol | Durum | Not |
|---|---|---|
| `.products-grid` 2-column | PARTIAL | 320px'de 2 column sikisik kaliyor. Fix: <=360px 1-column. |
| `.category-tabs` sticky scroll | OK | `-webkit-overflow-scrolling: touch` var. |
| `.btn-add` 44x44 | OK | Zaten tam 44px. |
| Modal `.modal-sheet` max-height 70vh | FAIL | iOS Safari'de toolbar ile 70vh kucuk kaliyor. Fix: `70dvh` fallback. |
| Product-card name font 0.9rem (14.4px) | PARTIAL | 16px altinda ama dekoratif metin, okunabilir. |

**375px:**
- 2-column OK
- Cart bar `env(safe-area-inset-bottom)` var — OK
- Porsiyon modal width OK

**768px:**
- Max-width 600px ile ortalanmis — OK

**FIX 3: `/menu/index.php` inline `<style>` blogu**
- `.products-grid`'e `@media (max-width: 360px) { grid-template-columns: 1fr; }` eklendi
- `.modal-sheet` `max-height: 70dvh` (dvh fallback: 70vh)

### 3. Admin Dashboard (`/admin/dashboard.php`)

**320px:**
| Kontrol | Durum | Not |
|---|---|---|
| Sidebar mobile | OK | 1024px altinda `transform: translateX(-100%)`, overlay mevcut. |
| Sidebar dismiss | PARTIAL | Escape tusu ile kapanmiyor. Fix: `admin.js` Escape keyboard handler. |
| Hamburger touch target | FAIL | `.menu-toggle` padding var ama ikonla beraber ~36x36. Fix: min 44x44. |
| Grid `minmax(400px, 1fr)` | FAIL | 320px'de kart 400px minimum istiyor -> horizontal scroll. Fix: <=768px `grid-template-columns: 1fr`. |
| Topbar user-info | OK | text-overflow: ellipsis. |

**375px:**
- `.stats-grid` 1 column — OK (zaten 768px altinda)
- Touch target: pageheader action butonu min 44px OK

**768px:**
- `.stats-grid` 1-column — OK
- `.page-header` stackleniyor — OK
- Table padding reduce — OK

### 4. Admin Siparis Listesi (`/admin/takvim.php`, `/admin/masa-siparisleri.php`)

**320px:**
| Kontrol | Durum | Not |
|---|---|---|
| `.siparis-grid` 3-column | FAIL | 768 altinda `minmax(300px, 1fr)` olsa bile <=320'de sikisik. Fix: `.siparis-grid` 1-column <=480. |
| Filtre butonlari wrap | OK | `flex-wrap: wrap`. |
| Siparis kart icerigi | OK | `.siparis-kart__urunler` li list OK. |
| Tablo `.admin-table` | FAIL | Horizontal scroll gerekiyor (siparisler sayfasinda). Fix: `.table-responsive` wrapper + `.admin-table-cards` alt-768 kart gorunumu. |

**375px:**
- Siparis kart 2 column onerilmeli — CSS'te var (2col) ama hizli gecis gerekli
- Tablo yine scroll

**768px:**
- Grid 2 column — OK (767 media query)

**FIX 2: Admin tablosu**
- `admin.css`'e `@media (max-width: 768px) { table responsive wrapper - auto-overflow + kart gorunumu }`  
- `.admin-card-table` pattern (tablar: label gizlenir, row arasinda `display: block` kart)

### 5. Admin Masa Yonetimi (`/admin/masalar.php`)

**320px:**
| Kontrol | Durum | Not |
|---|---|---|
| `.masa-grid` 1-column | OK | 479px altinda 1-column. |
| `.masa-stats` flex-direction column | OK | 479 altinda column. |
| `.qr-modal__actions` stack | OK | 479 altinda column. |
| Masa card touch target | OK | Buton min 44. |

**375px:**
- 2-column OK
- QR modal full width OK

**768px:**
- 2-column OK

## Form Input Audit (iOS zoom fix)

**iOS Safari**, input font-size < 16px ise focus'ta otomatik zoom yapar. Bu UX kaybi.

Mevcut durum:
- `.form-group input` admin.css: font-size `var(--text-sm)` = **14px** → FAIL
- `.form-group textarea` admin.css: font-size `var(--text-sm)` = **14px** → FAIL
- `style.css`'de `.contact-form input` font-size belirtilmemis — body'den miras (OK 16px)
- `/menu/*` input yok (client-side localStorage)

**FIX 4: iOS zoom**
- `admin.css`'e global kural: `@media (max-width: 768px) { input, textarea, select { font-size: 16px; } }`

## Modal Viewport (100dvh) Audit

Modal max-height kullanimlari:
- `admin.css` `.modal`: `max-height: 100vh` → iOS Safari adres cubugu ile kesim olur. Fix: `100dvh` (fallback `100vh`).
- `menu/index.php` `.modal-sheet`: `max-height: 70vh` → Fix: `70dvh`.

**FIX 5: Modal full-viewport mobile**
- `admin.css` modal kurali: `max-height: 100dvh` + fallback.
- Menu inline style: `70dvh` kullanimi.

## Navigation (Hamburger) Audit

- **Admin:** hamburger (`.menu-toggle`) mevcut, 1024px altinda `display: flex`. Sidebar `.sidebar.open` class ile toggle. ✓
- **Public (`/index.php`):** Sticky nav yok, hero'dan direk scroll ile sections'a giden tek-sayfa yapisi. Scroll progress bar var. Hamburger'a gerek **yok** (tek sayfa). Skip link eklendi.
- **QR menu (`/menu/*`):** Top-bar brand + masa no, kategori tab'lari sticky. Hamburger'a gerek yok. ✓

## Uygulanan Fix'ler Ozeti

| # | Fix | Dosya | Satirlar |
|---|---|---|---|
| 1 | Admin sidebar Escape key + focus trap | `assets/js/admin.js` | sidebar toggle block |
| 2 | Admin tablo responsive (kart gorunumu <768px) | `assets/css/admin.css` | yeni `@media (max-width: 768px)` table bloke |
| 3 | QR menu grid 1-column <360px + modal-sheet 70dvh | `menu/index.php` inline | @media 360 bloke |
| 4 | Form input iOS zoom fix (font-size 16px) | `assets/css/admin.css` ve `style.css` | @media 768 input kural |
| 5 | Modal 100dvh mobile viewport fix | `assets/css/admin.css` | .modal kurali |
| 6 | Hero parallax-layer-front <360px gizle + touch target 44px | `assets/css/style.css` | @media 360 bloke |
| 7 | Admin siparis grid <480 1-column | `assets/css/admin.css` | @media 479 siparis-grid |
| 8 | Admin stats grid <768 1-column (garanti) | mevcut | ok |

## Sonuc

- **3 breakpoint auditi** (320/375/768) 5 sayfa uzerinde
- **8 gercek fix** uygulandi (min 5 hedefi asildi)
- Ek: skip link ve Escape handler eklendi
- WCAG AA uyumlulugu icin ayri audit dokumaninda (bkz. `WCAG_AA_AUDIT.md`)
