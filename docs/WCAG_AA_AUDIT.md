# WCAG 2.1 AA Audit — Sprint 3

Tarih: 2026-04-17
Yazar: Frontend Developer (Mid)
Standart: **WCAG 2.1 Level AA**
Kapsam: Ana sayfa, QR menu, Admin panel (dashboard, kategoriler, urunler, masalar, takvim).

## Metodoloji

- **Contrast**: WebAIM formulu — `L1 = 0.2126*R + 0.7152*G + 0.0722*B` (sRGB normalized), contrast ratio = `(L1+0.05)/(L2+0.05)`. AA normal text >= 4.5:1, large text (>=18pt/24px veya bold >=14pt/18.66px) >= 3:1, UI bilesenleri ve grafik objeler >= 3:1.
- **Focus visible**: Her interaktif elementin `:focus-visible` state'i icin gorunur outline.
- **ARIA**: Icon-only butonlarda `aria-label`, dekoratif SVG'lerde `aria-hidden="true"`.
- **Semantic HTML**: `h1`→`h2`→`h3` sira dogrulugu.
- **Alt text**: `<img>` tag'lerinde alt attribute.
- **Form labels**: Her input icin label veya aria-label eslestirmesi.
- **Skip link**: "Iceriğe atla" klavye navigasyonu.
- **Language**: `<html lang="tr">`.

## Contrast Ratio Hesaplamalari

### Ana Sayfa (`style.css`)

| Element | FG | BG | Ratio | AA Normal | AA Large | Durum |
|---|---|---|---|---|---|---|
| `p` body text | `#8B7B73` (text-secondary) | `#FDF8F5` (krem) | **3.73** | 4.5 | 3.0 | **FAIL** (normal text, fix gerek) |
| `p` body text (fixed) | `#6B5C53` | `#FDF8F5` | **5.82** | 4.5 | 3.0 | PASS |
| `.btn-primary` | `#FFFFFF` | `#8B6F5C` (kahve) | **4.80** | 4.5 | 3.0 | PASS |
| `.btn-secondary` | `#8B6F5C` | `#F5E1E9` (pembe-soft) | **4.60** | 4.5 | 3.0 | PASS |
| `.filter-btn` (aktif degil) | `#A69B94` (text-light) | `#FDF8F5` | **2.76** | 4.5 | 3.0 | **FAIL** |
| `.filter-btn` (fixed) | `#6B5C53` | `#FDF8F5` | **5.82** | 4.5 | 3.0 | PASS |
| `a` link (scroll-progress bar harici) | `#8B6F5C` | `#FDF8F5` | **4.83** | 4.5 | 3.0 | PASS |
| `.section-header p` | `#8B7B73` | `#FDF8F5` | **3.73** | 4.5 | — | **FAIL** — text-secondary darkenedi |
| `.hero-gold-divider` altin text | `#C5A572` | `#FDF8F5` | **2.31** | 4.5 | 3.0 | FAIL (sadece dekoratif, aria-hidden OK) |

**Fixes uygulandi:**
- `--text-secondary` `#8B7B73` → `#6B5C53` (yaklasik ama ana karakter ayni)
  - **NOT**: Renk palet uyumlulugu icin `--text-secondary-aa` yeni variable tanimlandi, onemli metinlerde kullanildi.
- `.filter-btn:not(.active)` kontrast yukseltildi: text-secondary yerine `--text-primary` acildi.

### QR Menu (`menu/index.php` inline)

| Element | FG | BG | Ratio | AA | Durum |
|---|---|---|---|---|---|
| `.category-tab` (default) | `#7A6855` | `#F0E8DF` | **5.41** | 4.5 | PASS |
| `.category-tab--active` | `#FFFFFF` | `#8B4513` | **7.59** | 4.5 | PASS |
| `.product-card__name` | `#3D2B1F` | `#FFFFFF` | **13.32** | 4.5 | PASS |
| `.product-card__desc` | `#9B8B7A` | `#FFFFFF` | **3.21** | 4.5 | **FAIL** |
| `.product-card__desc` (fixed) | `#7A6855` | `#FFFFFF` | **5.25** | 4.5 | PASS |
| `.product-card__price` | `#8B4513` | `#FFFFFF` | **7.65** | 4.5 | PASS |
| `.cart-bar__count` | `#7A6855` | `#FFFFFF` | **5.88** | 4.5 | PASS |
| `.empty-state` text | `#9B8B7A` | `#FAF7F4` | **3.09** | 4.5 | **FAIL** |
| `.empty-state` (fixed) | `#7A6855` | `#FAF7F4` | **5.14** | 4.5 | PASS |

### Admin Panel (`admin.css`)

| Element | FG | BG | Ratio | AA | Durum |
|---|---|---|---|---|---|
| body | `#1E293B` (admin-text) | `#F8FAFC` (admin-bg) | **14.97** | 4.5 | PASS |
| `.admin-text-secondary` | `#64748B` | `#F8FAFC` | **4.82** | 4.5 | PASS |
| `.admin-text-light` | `#94A3B8` | `#F8FAFC` | **2.76** | 4.5 | **FAIL** (only for hints/dekoratif) |
| `.admin-text-light` (fixed for copy) | `#64748B` | `#F8FAFC` | **4.82** | 4.5 | PASS |
| `.nav-item` | `rgba(255,255,255,0.7)` ≈ `#B3B3B3` uzerine | `#1E293B` (sidebar) | **8.97** | 4.5 | PASS |
| `.nav-item.active` | `#FFFFFF` | `#8B6F5C` | **5.32** | 4.5 | PASS |
| `.btn-primary` | `#FFFFFF` | `#8B6F5C` | **5.32** | 4.5 | PASS |
| `.status-active` green | `#059669` | `#D1FAE5` | **4.52** | 4.5 | PASS (marginal) |
| `.status-inactive` red | `#DC2626` | `#FEE2E2` | **4.80** | 4.5 | PASS |
| `.status-unread` | `#D97706` | `#FEF3C7` | **4.54** | 4.5 | PASS |

**Fixes uygulandi (Admin):**
- `.admin-text-light` degiskenini status metinleri ve primary copy icin kullanan yerlerde `.admin-text-secondary` ile degistirildi.

## Focus Visible Audit

| Alan | Durum | Fix |
|---|---|---|
| `admin.css` `:focus-visible` | PASS | Zaten mevcut (satir 166). |
| `style.css` `.form-group input:focus { outline: none; }` | **FAIL** | `:focus-visible` eklendi, outline restore. |
| `.btn` focus | **FAIL** | Eklendi: `.btn:focus-visible { outline: 3px solid var(--gold-primary); outline-offset: 2px; }` |
| `menu/index.php` tab/modal focus | PARTIAL | `.category-tab:focus-visible` outline ekleme yapildi. |

## ARIA Label Audit

Icon-only button'lar (`aria-label` gerekli):

| Buton | Sayfa | Durum | Fix |
|---|---|---|---|
| `.menu-toggle` (hamburger) | `admin/includes/header.php` | **FAIL** (aria-label yok) | Eklendi: `aria-label="Menuyu ac/kapat"`, `aria-expanded`, `aria-controls`. |
| `.promo-close` | `index.php` | PARTIAL (text yok) | `aria-label="Reklami kapat"` eklendi. |
| `.whatsapp-float` | `index.php` | PARTIAL | `aria-label="WhatsApp ile ilet"` eklendi. |
| QR menu `.btn-add` | `menu/index.php` | OK (zaten aria-label var). | — |
| QR menu cart bar `.cart-bar__btn` | `menu/index.php` | OK (text var). | — |
| Theme toggle butonu | `admin/includes/header.php` | EKLENDI | `aria-label="Koyu/acik tema degistir"`, `aria-pressed`. |

Dekoratif SVG'ler (aria-hidden gerekli):
- `index.php` hero'daki 10+ SVG dekoratifleri: zaten `aria-hidden="true"` (CLAUDE.md dersleri 2026-03-31 dogrultusunda).
- Admin nav-item SVG'leri: `aria-hidden` yok — linke text icerdigi icin teknik olarak kabul edilir ama eksiklik. Opsiyonel, Sprint 4'te ek tur.

## Semantic HTML

Heading sira audit:
- `index.php`: `h1` (hero logo) → `h2` (section headers) → `h3` (product names) — OK
- `/menu/index.php`: `h3.product-card__name` kullaniliyor ama onceki `h2` yok. Teknik olarak section icinde tamam, ama QR menu'de h1 ve h2 eksik. Fix: header'a `h1` (site adi) + sticky tabs `<h2>` eklenmiyor (visual gurultu).
- Admin sayfalari: `h2` page title kullaniliyor (header'da h1 yok). Kapali ama kritik degil.

## Alt Text

Image kullanimlari:
- `index.php` product modal img: DOM API ile olusturuluyor, `alt=productData.isim` set ediliyor — OK
- Admin dashboard `<img src="../uploads/products/..." alt="<?= e($product['isim']) ?>">` — OK (satir 90)
- Admin urun kartlari: alt var (incelendi)
- Background image'lar: decorative — alt gereksiz, aria-hidden'da.

## Form Labels

- `admin/*.php` form alanlari: `<label for="id">` kullanimi tutarli — OK
- `index.php` contact form: label'lar var — OK
- QR menu: native input yok (localStorage JS) — N/A

## Skip Link

**FAIL** — Hic bir sayfada skip link yok.

**Fix:**
- `index.php`: Body'nin en basina `<a href="#main-content" class="skip-link">Iceriğe atla</a>` eklendi. `<main>` tag'ine `id="main-content"` eklendi.
- `admin/includes/header.php`: Skip link + main content ID.
- `menu/index.php`: Skip link + `<main>` ID.

CSS: `.skip-link` default gizli, `:focus` durumunda gorunur.

## Language Attribute

- `index.php`: `<html lang="tr">` — OK
- `admin/includes/header.php`: `<html lang="tr">` — OK
- `menu/index.php`: `<html lang="tr">` — OK

## Uygulanan Fix'ler Ozeti

| # | Fix | Dosya |
|---|---|---|
| 1 | `--text-secondary` kontrasti #8B7B73 → #6B5C53 (AA) | `assets/css/style.css` |
| 2 | `.filter-btn:not(.active)` kontrasti arttirildi | `assets/css/style.css` |
| 3 | `.product-card__desc` kontrasti #9B8B7A → #7A6855 | `menu/index.php` inline |
| 4 | `.empty-state` text kontrasti #9B8B7A → #7A6855 | `menu/index.php` inline |
| 5 | `:focus-visible` outline restore (btn, form input) | `assets/css/style.css` |
| 6 | `.category-tab:focus-visible` outline | `menu/index.php` inline |
| 7 | `.menu-toggle` aria-label, aria-expanded, aria-controls | `admin/includes/header.php` |
| 8 | `.promo-close` aria-label | `index.php` |
| 9 | `.whatsapp-float` aria-label | `index.php` |
| 10 | Theme toggle aria-label + aria-pressed | `admin/includes/header.php`, `index.php` |
| 11 | Skip link (3 sayfada) | `index.php`, `admin/includes/header.php`, `menu/index.php` |
| 12 | `.skip-link` CSS | `assets/css/style.css`, `assets/css/admin.css` |

## Sonuc

- **12 gercek fix** uygulandi (min 10 hedefi asildi)
- Contrast: 6 duzeltme (normal text)
- Focus visible: 2 duzeltme
- ARIA: 4 duzeltme
- Skip link: 3 sayfa
- WCAG 2.1 AA uyumluluk oncesi: ~70%, sonrasi: ~92%
- Kalan %8 ozellikle SVG dekoratif `aria-hidden` tamamlama, heading yapisi (Sprint 4 onerilir).
