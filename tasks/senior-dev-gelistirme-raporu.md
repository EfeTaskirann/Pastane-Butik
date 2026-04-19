# SENIOR FULLSTACK DEVELOPER — GELİŞTİRME RAPORU

**Proje:** Tatlı Düşler Butik Pastane Yönetim Sistemi
**İnceleme Tarihi:** 2026-04-17
**Perspektif:** Production-readiness ve uzun vadeli sürdürülebilirlik

---

## 🎯 YÖNETİCİ ÖZETİ

**Genel Değerlendirme:** `7/10` — Solid temel, ama production'a çıkmadan önce kritik eksiklikler kapatılmalı.

### ✅ Güçlü Yönler
- PSR-4 namespacing, Service/Repository pattern tutarlı
- CSRF, Rate Limiting, JWT, Password Policy altyapıları mevcut
- Middleware mimarisi (CORS, Auth, RateLimit) ayrışmış
- PDO prepared statements her yerde — SQL injection riski düşük
- Session güvenliği doğru konfigüre (HttpOnly, SameSite=Strict)
- Foreign key, soft delete, timestamp disiplini iyi
- PHPUnit altyapısı kurulu

### ❌ En Kritik Eksiklikler
1. **CSP uyumsuzluğu**: Admin sayfalarında hâlâ `onclick=""` var
2. **Admin dosyaları procedural monolit**: MVC değil, 400-600 satırlık tek PHP dosyaları
3. **Test coverage düşük**: Kritik path'lerde (ödeme, sipariş) test yok
4. **CI/CD yok**: `.github/workflows/` boş
5. **Audit log UI yok**: Kimin ne yaptığı izlenemiyor
6. **Email/SMS bildirim sistemi yok**: Sipariş onay bildirimi gönderilmiyor
7. **RBAC enforcement yok**: Admin role kolon var ama kontrol edilmiyor

---

## 📊 ÖNCELİK SEVİYELERİ

| Sembol | Önem | Anlamı |
|---|---|---|
| 🔴 | Kritik | Production-blocker, hemen çözülmeli |
| 🟠 | Yüksek | Launch sonrası ilk sprint'te |
| 🟡 | Orta | 2-3 sprint içinde |
| 🟢 | Düşük | Nice-to-have, backlog |

| Efor | Süre |
|---|---|
| XS | < 2 saat |
| S | 2-8 saat |
| M | 1-2 gün |
| L | 3-5 gün |
| XL | 1+ hafta |

---

## 1️⃣ MİMARİ / KOD KALİTESİ

### 1.1 🟠 Admin sayfaları procedural monolit — Controller katmanı yok
- **Sorun:** [admin/urunler.php](admin/urunler.php) 400+ satır, HTML + SQL + business logic karışık
- **Etki:** Bakım zor, test edilemez, reuse olmaz
- **Çözüm:** `AdminControllers/` + `views/admin/` ayrımı, Service'leri controller'dan çağır
- **Efor:** XL

### 1.2 🟡 `kategori_adi` vs `kategori_ad` tutarsızlığı
- **Sorun:** [src/Repositories/UrunRepository.php:69](src/Repositories/UrunRepository.php#L69) `kategori_adi`, başka yerde `kategori_ad`
- **Etki:** Template'de `$product['kategori_ad']` hatası
- **Çözüm:** Tek isim standardize et, tüm query'leri taraw
- **Efor:** S

### 1.3 🟡 SQL query tekrarı (DRY ihlali)
- **Sorun:** `getActive()`, `getFeatured()`, `search()`, `getCafeMenuUrunleri()` benzer LEFT JOIN yazıyor
- **Çözüm:** `buildProductWithCategoryQuery()` base metodu extract et
- **Efor:** S

### 1.4 🟡 Validator sınıfı basit string parsing
- **Sorun:** [src/Services/BaseService.php:211](src/Services/BaseService.php#L211) `'required|min:5'` parsing; nested/custom rule yok
- **Çözüm:** Dedicated `Validator` sınıfı, Laravel-style API
- **Efor:** M

### 1.5 🟢 Service Locator anti-pattern
- **Sorun:** [src/Repositories/BaseRepository.php:92](src/Repositories/BaseRepository.php#L92) `db()` global function kullanıyor
- **Çözüm:** DI Container (PHP-DI) kur
- **Efor:** M

---

## 2️⃣ GÜVENLİK

### 2.1 🔴 Inline `onclick` handler'ları CSP ile çelişkili
- **Sorun:** [admin/urunler.php:247, 266, 308](admin/urunler.php#L247), [admin/kategoriler.php](admin/kategoriler.php), [admin/masalar.php](admin/masalar.php), [admin/takvim.php](admin/takvim.php)
- **CSP header** [includes/security.php:64](includes/security.php#L64): `script-src 'self' 'nonce-{$nonce}'`
- **Etki:** Production CSP enforce edildiğinde tüm butonlar break olur
- **Çözüm:** `data-id` + `addEventListener` pattern, CLAUDE.md'de de uyarı var
- **Efor:** M

### 2.2 🔴 `.env` dosyası git history'de sızıntı riski
- **Sorun:** JWT_SECRET ve APP_KEY gerçek değerlerle committed olabilir
- **Çözüm:**
  1. `git filter-branch` / BFG ile history temizle
  2. Tüm secret'leri rotate et
  3. `.env` kesinlikle `.gitignore`'da olsun
- **Efor:** M + infra

### 2.3 🟠 XSS: `e()` helper tutarsız kullanım (JS context)
- **Sorun:** JS context'te `<?= $var ?>` direkt gömme riski
- **Kural** (CLAUDE.md satır 20): JS için `json_encode()` kullan
- **Çözüm:** Tüm `<script>` bloklarını denetle
- **Efor:** S

### 2.4 🟠 RBAC enforcement yok
- **Sorun:** `admin_kullanicilar.rol` kolon var ama kontrol edilmiyor
- **Etki:** Her admin her şeyi yapabiliyor
- **Çözüm:** Middleware'de role check: `@permission('product.delete')`
- **Efor:** L

### 2.5 🟡 Rate Limit header'ları response'ta yok
- **Sorun:** `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` eksik
- **Çözüm:** `RateLimitMiddleware` içinde header set et
- **Efor:** S

### 2.6 🟡 `.env` ile `.env.example` senkron değil
- **Sorun:** `.env.example` payment gateway keys var, `.env`'de yok → undefined key warning
- **Çözüm:** Senkronize et + `env()` helper'a default fallback
- **Efor:** XS

### 2.7 🟢 Password hashing algorithm explicit değil
- **Çözüm:** `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`
- **Efor:** XS

### 2.8 🟡 2FA UI yok
- **Sorun:** [includes/TwoFactorAuth.php](includes/TwoFactorAuth.php) class var ama admin panelde enable/disable ekranı yok
- **Çözüm:** Admin profil sayfasına 2FA setup UI ekle
- **Efor:** M

---

## 3️⃣ PERFORMANS

### 3.1 🟠 N+1 query riski (masalar + siparişler)
- **Sorun:** `getMasalarWithSiparisCount()` gibi metotlar her masa için ayrı query yapıyor olabilir
- **Çözüm:** Tek `LEFT JOIN ... GROUP BY` ile birleştir
- **Efor:** M (audit + fix)

### 3.2 🟡 CSS boyutu büyük (~6000 satır)
- **Dosyalar:** style.css 2578, admin.css 2813, animations.css 756
- **Çözüm:** Unused CSS cleaning (PurgeCSS), SCSS compile, critical CSS extract
- **Efor:** M

### 3.3 🟡 WebP görsel fallback yok
- **Sorun:** Ürün görselleri sadece PNG/JPG, `<picture>` tag yok
- **Çözüm:** Upload sırasında WebP variant oluştur
- **Efor:** M

### 3.4 🟡 Cache gerçekten entegre mi denetim
- **Sorun:** [includes/Cache.php](includes/Cache.php) var ama kaç yerde gerçekten kullanılıyor?
- **Çözüm:** Hot path'lere (menu listesi, kategori listesi) cache ekle
- **Efor:** M

### 3.5 🟢 Asset minification / bundling yok
- **Çözüm:** Vite veya Webpack ile build pipeline
- **Efor:** M

---

## 4️⃣ FRONTEND / UX

### 4.1 🟠 Mobile UX (özellikle QR menü) denetimi eksik
- **Sorun:** QR menü mobilde kullanılıyor, responsive breakpoint testleri yapılmamış
- **Çözüm:** 320px/375px/414px/768px breakpoint test + hamburger menu
- **Efor:** M

### 4.2 🟡 Loading state'leri tutarsız
- **Sorun:** AJAX işlemlerinde spinner yok
- **Çözüm:** Global loading component + toast notification
- **Efor:** S

### 4.3 🟡 Erişilebilirlik (a11y) eksikleri
- **Sorun:** ARIA label'lar var ama sistematik değil, contrast testi yapılmamış
- **Çözüm:** WCAG AA audit (Lighthouse/axe)
- **Efor:** M

### 4.4 🟡 Dark mode CSS yok
- **Sorun:** CSS variables var, ama `[data-theme="dark"]` override'ı yazılmamış
- **Çözüm:** Dark theme + system preference listener
- **Efor:** M

### 4.5 🟢 Form validation UX (inline error, real-time)
- **Efor:** S

---

## 5️⃣ TEST KAPSAMI

### 5.1 🔴 Unit test coverage düşük
- **Mevcut:** `tests/Unit/` altında 7 dosya (JWT, Password, Validator, Helpers…)
- **Eksik:** `OdemeService`, `MasaSiparisService`, `SiparisService` gibi kritik servisler
- **Hedef:** %70+ coverage
- **Efor:** XL

### 5.2 🔴 E2E / Feature test yok
- **Sorun:** `tests/Feature/` boş, browser automation yok
- **Çözüm:** Playwright / Cypress ile customer + admin flow testleri
- **Efor:** XL

### 5.3 🔴 CI/CD Pipeline yok
- **Sorun:** `.github/workflows/` boş
- **Çözüm:**
  ```yaml
  # .github/workflows/ci.yml
  - PHPUnit
  - PHPStan (level 5)
  - PHPCS (PSR-12)
  - Security audit (composer audit)
  ```
- **Efor:** M

---

## 6️⃣ API TASARIMI

### 6.1 🟡 OpenAPI/Swagger dokümantasyon yok
- **Çözüm:** `zircote/swagger-php` kur, endpoint annotation'ları ekle, Swagger UI mount et
- **Efor:** L

### 6.2 🟡 Versioning stratejisi belirsiz
- **Sorun:** v1 var, v2 nasıl deploy edilecek? Deprecation policy?
- **Çözüm:** Semantic versioning + `Deprecation`/`Sunset` header'lar
- **Efor:** M

### 6.3 🟢 API response cache header'ları
- **Çözüm:** `Cache-Control`, `ETag` ekle (public endpoints için)
- **Efor:** S

---

## 7️⃣ VERİTABANI

### 7.1 🟡 Migration file yönetimi tutarsız
- **Sorun:** `002_add_security_fields.sql` (raw SQL), `2024_01_01_000001...php` (PHP) karışık
- **Çözüm:** Tüm migrations PHP'ye convert, timestamp-based naming
- **Efor:** M

### 7.2 🟠 Audit trail kolonları eksik
- **Sorun:** `created_by`, `updated_by`, `deleted_by` yok
- **Etki:** Kimin ne değiştiğini bilmiyoruz
- **Çözüm:** Migration + middleware auto-set + UI
- **Efor:** M

### 7.3 🟠 Backup/Restore prosedürü dokümantasyonu yok
- **Çözüm:** `scripts/backup.sh` + admin panelde Restore UI
- **Efor:** M

---

## 8️⃣ KONFİG / ENV YÖNETİMİ

### 8.1 🔴 Secret management infra yok
- **Sorun:** Production'da secret'ler `.env`'de dosya sisteminde duruyor
- **Çözüm:** Vault / AWS Secrets Manager / Doppler entegrasyonu
- **Efor:** L

### 8.2 🟡 `env()` helper default fallback disiplini
- **Çözüm:** `env('KEY', 'safe-default')` pattern zorunlu hale getir
- **Efor:** S

---

## 9️⃣ LOGGİNG / MONİTORİNG

### 9.1 🔴 Error tracking (Sentry) entegrasyonu yok
- **Sorun:** Production'da critical error'lar sadece dosyaya yazılıyor, alerting yok
- **Çözüm:** Sentry SDK kur, unhandled exception hook
- **Efor:** M

### 9.2 🟠 Log rotation yok
- **Sorun:** `storage/logs/` sınırsız büyüyor
- **Çözüm:** Daily rotation + 30 gün retention + cron cleanup
- **Efor:** S

### 9.3 🟠 Health check endpoint zayıf
- **Sorun:** DB/Cache/Disk kontrolleri tam değil
- **Çözüm:** `/api/health` → `{db: ok, cache: ok, disk: 45%, queue: 0}`
- **Efor:** S

### 9.4 🟡 Application metrics yok
- **Çözüm:** Prometheus exporter veya basit metrics endpoint
- **Efor:** M

---

## 🔟 DEVELOPER EXPERIENCE (DX)

### 10.1 🟡 PHP-CS-Fixer yok
- **Sorun:** PHPCS sadece check ediyor, auto-format yok
- **Çözüm:** `.php-cs-fixer.php` + composer script
- **Efor:** XS

### 10.2 🟡 Pre-commit hooks yok
- **Çözüm:** Husky + lint-staged (PHP equivalent: captainhook)
- **Efor:** S

### 10.3 🟡 Developer onboarding dokümantasyonu zayıf
- **Sorun:** `README.md` kısıtlı, test DB seed nasıl?
- **Çözüm:** `CONTRIBUTING.md`, `docs/SETUP.md`, Makefile
- **Efor:** S

### 10.4 🟢 Statik analiz level düşük
- **Mevcut:** `phpstan -l 5`
- **Hedef:** Level 8 (strict)
- **Efor:** L (level yükseltmek refactor gerektirir)

---

## 1️⃣1️⃣ İŞ MANTIĞI / DOMAIN

### 11.1 🟡 Sadakat programı granularity düşük
- **Sorun:** Sabit "her 5 siparişte hediye", tier yok
- **Çözüm:** `loyalty_tiers` (bronze/silver/gold), % indirim, doğum günü indirimi
- **Efor:** L

### 11.2 🟡 Stok yönetimi yetersiz
- **Sorun:** `stok_durumu` ENUM (var/tükendi/sınırlı), sayısal stok yok
- **Çözüm:** `stok_adet` integer + history tablosu + low-stock alerts
- **Efor:** L

### 11.3 🟡 Ödeme iade politikası kodda/dokümanda belirsiz
- **Sorun:** İade süresi, admin approval, kimin başlatabileceği belirsiz
- **Çözüm:** Business rule doc + code enforcement
- **Efor:** S

### 11.4 🟠 Masa oturumu otomatik timeout yok
- **Sorun:** Terk edilen masalar manuel kapatılmalı
- **Çözüm:** Cron job — 60dk inaktif oturumları otomatik kapat
- **Efor:** S

### 11.5 🟡 Sipariş iptal kuralları belirsiz
- **Sorun:** Müşteri kendi iptal edebilir mi? Hangi aşamada?
- **Çözüm:** State machine doc + UI disable
- **Efor:** S

---

## 1️⃣2️⃣ DEPLOYMENT / PRODUCTION

### 12.1 🟠 Docker multi-container setup eksik
- **Sorun:** Dockerfile var ama `docker-compose.yml`'de DB/Redis/Nginx tam değil
- **Çözüm:** Tam production-grade compose
- **Efor:** M

### 12.2 🟠 Environment parity (dev vs prod)
- **Sorun:** XAMPP PHP version vs production farklı olabilir
- **Çözüm:** Dockerfile'da PHP version pin (`php:8.1-fpm`)
- **Efor:** S

### 12.3 🟠 Readiness/Liveness probe yok
- **Çözüm:** K8s/Docker health check endpoint'leri
- **Efor:** S

### 12.4 🟡 Zero-downtime deploy stratejisi yok
- **Çözüm:** Blue-green veya rolling update prosedürü + migration lock
- **Efor:** L

---

## 1️⃣3️⃣ INTERNATIONALIZATION

### 13.1 🟢 Sadece Türkçe + ₺ hardcoded
- **Çözüm:** i18n library + translation files, multi-currency
- **Efor:** XL
- **Not:** Butik pastane domestic market, şimdilik backlog

---

## 1️⃣4️⃣ EKSİK / KAYIP ÖZELLİKLER

| # | Özellik | Öncelik | Efor | Açıklama |
|---|---|---|---|---|
| F1 | Email bildirim sistemi | 🟠 | M | Sipariş confirmation, şifre sıfırlama |
| F2 | SMS bildirim (Netgsm/Twilio) | 🟡 | M | Sipariş durum güncellemesi |
| F3 | Admin Activity Log UI | 🟠 | M | Kim ne yaptı panelden görsün |
| F4 | RBAC — Rol bazlı yetkilendirme | 🟠 | L | Müdür/Kasiyer/Mutfak rolleri |
| F5 | Bulk Import/Export (CSV) | 🟡 | M | Ürün import, sipariş export |
| F6 | Backup & Restore UI | 🟠 | M | Admin panelden DB backup |
| F7 | 2FA Setup UI | 🟡 | M | Class var, UI yok |
| F8 | Settings Management UI | 🟡 | M | Admin > Ayarlar (SITE_NAME vb.) |
| F9 | Müşteri segmentasyon | 🟢 | L | Davranışa göre gruplama |
| F10 | Advanced Forecasting | 🟢 | XL | Satış tahminleri, ML |
| F11 | WhatsApp Business API | 🟡 | L | Şimdi sadece wa.me link, proper API |
| F12 | Kupon/Promo kodu sistemi | 🟡 | M | İndirim kodları |
| F13 | Müşteri üyeliği (login) | 🟡 | L | Müşteri kendi hesabıyla sipariş geçmişi |
| F14 | Puanla ödeme (loyalty points) | 🟢 | L | Sadakat puanı ile indirim |
| F15 | Web Push Notification | 🟢 | M | Yeni kampanya bildirimi |
| F16 | Public API developer portal | 🟢 | L | B2B partner'lar için API docs |

---

## 📅 ÖNERİLEN YOL HARİTASI (Roadmap)

### 🚀 Sprint 0 — Production Blocker (1 hafta)
- [ ] 2.1 CSP uyumsuzluğunu düzelt (onclick → addEventListener)
- [ ] 2.2 `.env` secret rotation + git history temizle
- [ ] 5.3 CI/CD pipeline kur (.github/workflows)
- [ ] 2.6 `.env`/`.env.example` senkronize et
- [ ] 12.2 Dockerfile PHP version pin

### 🎯 Sprint 1 — Temel Sağlamlaştırma (2 hafta)
- [ ] 5.1 OdemeService + MasaSiparisService + SiparisService testleri
- [ ] 2.4 RBAC middleware implementasyon
- [ ] F1 Email bildirim sistemi (sipariş confirmation)
- [ ] F3 Admin Activity Log UI
- [ ] 9.1 Sentry entegrasyonu
- [ ] 9.3 Health check endpoint güçlendirme
- [ ] 11.4 Masa timeout cron job'u
- [ ] 3.1 N+1 query audit

### 🔧 Sprint 2 — Operasyonel İyileştirme (2 hafta)
- [ ] F6 Backup & Restore UI
- [ ] F8 Settings Management UI
- [ ] 1.1 Admin Controller refactoring (pilot: urunler.php)
- [ ] 2.8 2FA UI
- [ ] 7.2 Audit trail kolonları (created_by/updated_by)
- [ ] 9.2 Log rotation + cleanup cron

### 💎 Sprint 3 — UX + DX (2 hafta)
- [ ] 4.1 Mobile UX audit (QR menü)
- [ ] 4.3 WCAG AA a11y audit
- [ ] 4.4 Dark mode
- [ ] 10.1-10.3 DX araçları (PHP-CS-Fixer, hooks, docs)
- [ ] 6.1 OpenAPI/Swagger dokümantasyon

### 🚀 Sprint 4+ — Genişleme
- [ ] F4 RBAC tam implementasyon
- [ ] F12 Kupon sistemi
- [ ] F13 Müşteri üyeliği
- [ ] 11.1 Sadakat tier sistemi
- [ ] 11.2 Gerçek stok yönetimi
- [ ] F5 Bulk Import/Export

---

## 💡 HIZLI KAZANÇLAR (Quick Wins — Bugün başla)

Kullanıcının hızlıca değer göreceği düşük efor, yüksek etkili işler:

1. **🟡 Copyright yılı dinamik** — zaten yapılmış ama tüm footer'larda denetle (XS)
2. **🟡 `.env` default fallback** — `env('KEY', 'default')` (XS)
3. **🟡 Rate limit header'ları** — Response'lara ekle (S)
4. **🟡 Masa timeout cron** — 60dk inaktif masaları kapat (S)
5. **🟡 Log rotation** — Disk dolmasın (S)
6. **🟡 Health check endpoint** — `/api/health` JSON (S)
7. **🟡 PHP-CS-Fixer config** — Formatting otomatik (XS)

---

## 📈 METRİK HEDEFLER

Production'a hazır diyebilmek için ulaşılması gereken minimum metrikler:

| Metrik | Şu An | Hedef |
|---|---|---|
| Unit test coverage | ~%30 (tahmini) | ≥ %70 |
| PHPStan level | 5 | 7 |
| CI pass rate | - | %100 (main branch) |
| Lighthouse Performance | ? | ≥ 85 |
| Lighthouse Accessibility | ? | ≥ 90 |
| Security audit (composer audit) | ? | 0 critical |
| Backup frequency | Yok | Günlük otomatik |
| Sentry error budget | Yok | < %1 session |
| Mean time to recovery (MTTR) | Yok | < 30 dk |

---

## ✅ SONUÇ VE TAVSİYELER

**Genel yargı:** Proje **solid mimarı temel**e sahip — Service/Repository pattern, CSRF/Rate Limit altyapısı, JWT, PSR-4 hepsi var. Ama "**written but not integrated**" sendromu görülüyor: Güvenlik sınıfları yazılmış ama her yerde kullanılmıyor (CSP ihlalleri, RBAC eksikliği, 2FA UI yok).

**Öncelik:**
1. Sprint 0'daki kritik CSP + secret rotation işlerini HEMEN halledin (1 hafta)
2. Sprint 1'de test coverage + email + audit log + Sentry — bu dörtlü olmadan production'a çıkmayın
3. Sprint 2-3 ile operational excellence + UX iyileştirmeleri

**Ekipiniz bu şekilde çalışırsa ~6-8 haftada production-ready v1.0 çıkarabilirsiniz.**

---

**Hazırlayan:** Senior Dev Code Review
**Versiyon:** 1.0
**Son Güncelleme:** 2026-04-17
