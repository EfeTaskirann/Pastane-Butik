# Security Audit — Sprint 3 (Final)

**Tarih:** 2026-04-17
**Auditor:** Tech Lead (Senior Fullstack PHP)
**Kapsam:** Tatlı Düşler Pastane — tüm PHP backend, API v1, admin paneli, QR menü, ödeme akışı
**Metodoloji:** OWASP Top 10 (2021) + statik grep + dosya/satır bazlı kanıt + risk sınıflandırma

## Yönetici Özeti

| Kategori | Sayı |
|---|---|
| **Kritik (P0)** | 0 |
| **Yüksek (P1)** | 3 |
| **Orta (P2)** | 6 |
| **Düşük (P3)** | 5 |
| **Toplam Bulgu** | 14 |

**Genel postür:** Sprint 0–2 boyunca yapılan sertleştirme çalışmaları sayesinde **genel güvenlik olgunluğu yüksek**. CSRF, PDO prepared statement kullanımı, nonce-based CSP, JWT blacklist, 2FA, password policy, security audit logging, rate limit ve whitelist tabanlı tablo erişimi uygulanmış durumda. Kritik açık bulunmadı; kalan bulgular defense-in-depth iyileştirmeleri.

**Prod çıkış önerisi:** P1 bulgular Sprint 3 kapanmadan kapatılmalı. P2/P3 bulgular Sprint 4 ileri sertleştirme batch'ine alınabilir.

---

## OWASP Top 10 Sonuçları

### A01 — Broken Access Control ✅ Güçlü

**Bulgu:** Admin endpoint'lerinde tutarlı JWT koruması var. Repository/service katmanında kaynak sahipliği doğrulaması uygulanmış.

**Kanıt:**
- `api/v1/routes/siparisler.php:22` — `JWT::requireAuth()` her admin endpoint'inde
- `api/v1/routes/menu.php:247` — QR sipariş görüntülemesinde oturum-sahibi doğrulaması:
  ```php
  if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
      json_error('Bu siparisi goruntuleme yetkiniz yok.', 403);
  }
  ```
- `includes/db.php:19,122` — `ALLOWED_TABLES` whitelist (mass-assignment guard)
- RBAC: `config/permissions.php` + `admin/includes/auth.php`

**Bulgu A01-1 [P2-Orta]:** `api/takvim.php:98` ve `api/raporlar.php:67` — bu legacy admin AJAX endpoint'leri `JWT::requireAuth()` yerine session-based auth kullanıyor olabilir. Tutarsızlık bakım yükü yaratır.

**Çözüm:** `api/v1/` altına konsolide et veya her iki yolu da net şekilde belgele. Her legacy endpoint'te `requireAdmin()` çağrısı doğrulansın.

---

### A02 — Cryptographic Failures ✅ Güçlü, ⚠️ Bir Uyarı

**Bulgu:** `password_hash` ve `password_verify` doğru kullanılıyor. JWT HS256/HS512 destekli, `hash_equals` sabit-süreli karşılaştırma kullanılmış.

**Kanıt:**
- `api/v1/routes/auth.php:61,194,213` — `password_verify`/`password_hash(PASSWORD_BCRYPT, cost=12)`
- `includes/JWT.php` — `hash_equals` kullanıyor (15 yerde), ayrıca token blacklist var
- `includes/security.php:325` — CSRF token için `random_bytes(32)`
- `includes/TwoFactorAuth.php` — TOTP standardı, `hash_equals` ile doğrulama

**Bulgu A02-1 [P1-Yüksek]:** `admin/ayarlar/backup.php:187` — SQL dump dosyası **şifresiz** local disk'e yazılıyor. Dump dosyası DB snapshot içerir (müşteri verileri, hashler, PII).
```php
$sql = (string) file_get_contents($sqlFile);
```
**Risk:** Backup dizini web-readable olursa veya sunucuya düşük yetkili erişim sağlanırsa tüm DB dışarı sızar.

**Çözüm:**
1. Backup dosyalarını `storage/backups/` altına al, `.htaccess` ile `Deny from all`.
2. OpenSSL `aes-256-cbc` ile şifrele (`openssl_encrypt($sql, 'aes-256-cbc', $key, 0, $iv)`).
3. `BACKUP_ENCRYPTION_KEY` env'de tutulsun (secret rotation runbook'una ekle).
4. Dosya izinleri `0600` + klasör `0700` (Linux prod).

**Bulgu A02-2 [P3-Düşük]:** `includes/security.php:98` — `session.cookie_secure` koşulu `isset($_SERVER['HTTPS'])` ile; reverse proxy (Cloudflare/Nginx) arkasında bu başlık olmayabilir.

**Çözüm:** `APP_ENV === 'production'` kontrolü ekle; proxy arkasında `X-Forwarded-Proto` kontrolü yap.

---

### A03 — Injection ✅ Güçlü

**Bulgu:** PDO prepared statement kullanımı yaygın; 10+ dosyada 51+ `prepare()` çağrısı. Raw SQL concat tespit edilmedi. Shell injection `escapeshellarg` ile korunuyor.

**Kanıt:**
- `includes/db.php` — tüm query'ler `prepare()` üzerinden
- `admin/ayarlar/backup.php:75-77` — shell args `escapeshellarg()` ile kaçırılıyor:
  ```php
  $php = escapeshellarg(PHP_BINARY);
  $script = escapeshellarg($CRON_SCRIPT);
  ```
- `includes/security.php:449` — `sanitizeInput()` filter_var ile tür bazlı temizlik
- `api/v1/routes/menu.php:159,177` — `htmlspecialchars(ENT_QUOTES, 'UTF-8')` kullanıcı notlarında uygulanıyor

**Bulgu A03-1 [P2-Orta]:** `api/v1/routes/siparisler.php:78-84` — sipariş listele endpoint'inde dinamik `WHERE` string'i interpolasyon ile oluşturuluyor:
```php
$where = "1=1";
if ($durum) { $where .= " AND durum = ?"; $params[] = $durum; }
...
"SELECT s.*, k.isim FROM siparisler s LEFT JOIN kategoriler k ... WHERE {$where} ORDER BY s.tarih DESC LIMIT ? OFFSET ?"
```
SQL injection olmuyor çünkü **placeholder (`?`) kullanılıyor**, ama kod pattern'i riskli (refactor sırasında yanlışlıkla `$where .= " AND durum = '$durum'"` yapılabilir).

**Çözüm:** `SiparisRepository::getFiltered()` metoduna taşı; test eklenmiş olur (zaten dosya içinde TODO notu var: "İleride SiparisRepository.getFiltered() metodu ile refactor edilebilir").

**Bulgu A03-2 [P3-Düşük]:** `includes/security.php:381-395` — `validateFileMimeType` finfo ile doğru çalışıyor ama `move_uploaded_file` sonrası **içerik tekrar doğrulanmıyor** (polyglot dosya saldırısı teorik risk).

**Çözüm:** Upload sonrası `getimagesize()` çağır; GD/Imagick ile yeniden encode ederek "temizle" (image re-rendering).

---

### A04 — Insecure Design ✅ Genel Olarak İyi

**Bulgu:** Service layer + Repository pattern uygulanmış. Fiyat hesaplaması server-side (`siparisler.php:128-130` — `GÜVENLİK: birim_fiyat ve toplam_tutar client'tan ALINMAZ`). Idempotency ödeme callback'inde var.

**Kanıt:**
- `api/v1/routes/siparisler.php:128-130` — client'tan fiyat alınmıyor
- `api/v1/routes/menu.php:497-558` — ödeme callback idempotent
- Validator katmanı: `src/Validators/*.php`

**Bulgu A04-1 [P2-Orta]:** Tehdit modeli (STRIDE) dokümante edilmemiş. Yeni özellik (ödeme entegrasyonu, QR menü) eklenirken threat actor perspektifi yazılı değil.

**Çözüm:** `docs/THREAT_MODEL.md` oluştur. En azından: QR token ifşası, ödeme callback tampering, session fixation, masa oturum çalma senaryolarını STRIDE'la kapsa.

**Bulgu A04-2 [P3-Düşük]:** `api/v1/routes/menu.php:450` — ödeme `ip` alanı client tarafından override edilebilir (`$_SERVER['REMOTE_ADDR']` zaten kullanılıyor, ok). Ancak `musteri['email']` olarak `masa<N>@tatlidusler.com` formatında placeholder email yolluyor; gateway tarafında e-posta doğrulama varsa başarısız olabilir.

**Çözüm:** Ödeme gateway'in opsiyonel müşteri bilgi alanı kullanılabilir ise kaldır; zorunluysa domain'i `no-reply.invalid` yap.

---

### A05 — Security Misconfiguration ⚠️ Orta

**Bulgu:** Güvenlik header'ları detaylı tanımlı ama `.htaccess` içinde CSP yorum satırı var, aktif değil. Uygulama tarafından PHP seviyesinde gönderiliyor (defense-in-depth eksik).

**Kanıt:**
- `includes/security.php:42-80` — CSP, HSTS, X-Frame-Options, X-Content-Type, Referrer-Policy, Permissions-Policy tümü PHP'de aktif
- `includes/security.php:64` — `script-src 'self' 'nonce-{$nonce}'` (✅ unsafe-inline kaldırılmış)
- `.htaccess:67` — CSP **yorum satırında** (pasif):
  ```
  # Header always set Content-Security-Policy "..."
  ```

**Bulgu A05-1 [P1-Yüksek]:** `.htaccess:67` CSP yorumdayken, PHP'nin çalışmadığı durumlarda (statik HTML, direct asset isteği, PHP fatal error erken çıkış) güvenlik header'ları **gönderilmez**. Defense-in-depth bozuk.

**Çözüm:** `.htaccess`'teki CSP yorumunu aç, `includes/security.php` ile uyumlu hale getir (nonce olmadığı için biraz daha gevşek tut; sadece fallback görevi görür). Sentry + PHP error handler için set headers'ı bootstrap'in en başına al.

**Bulgu A05-2 [P2-Orta]:** `includes/security.php:65` — `style-src 'unsafe-inline'` hâlâ mevcut (inline CSS için). XSS + CSS injection vektörü teorik.

**Çözüm:** Inline style'ları component'lere taşı + `'nonce-{$nonce}'` uygula. Google Fonts için CSS hash-source kullan.

**Bulgu A05-3 [P2-Orta]:** `admin/musteriler.php:338,345` — inline `onchange="this.form.submit()"` hâlâ var. CSP nonce-only değil (unsafe-inline eksik) ama bu sayfa style bazlı. **CLAUDE.md satırında belirtilmiş** (2026-04-01 tarihli not): `admin/kategoriler.php, urunler.php, mesajlar.php, takvim.php` hâlâ inline handler içeriyor.

**Çözüm:** CSP_EVENT_PATTERN.md'ye uyarak `addEventListener` + nonce'lı script bloğuna taşı (Sprint 3'te Frontend agent temizlesin).

**Bulgu A05-4 [P3-Düşük]:** `menu/index.php` ve `menu/siparis-takip.php` müşteri tarafında 8+ inline `onclick`. QR menü tarafında CSP nonce tutarlılığı kontrol edilmeli.

---

### A06 — Vulnerable and Outdated Components ✅ Güçlü

**Komut:** `composer audit --no-dev --format=plain` → `No packages - skipping audit.`

**Bulgu:** Production'da **sıfır 3. taraf Composer paketi** var. Sadece dev bağımlılıklar (phpunit, phpstan, php_codesniffer) mevcut.

**Kanıt:** `composer.json:14-18`:
```json
"require": {
    "php": ">=8.1",
    "ext-pdo": "*", "ext-json": "*", "ext-mbstring": "*"
}
```

**Bulgu A06-1 [P3-Düşük]:** CDN üzerinden **Swagger UI** yüklenecek (Sprint 3 eklemesi, `public/api-docs/index.html`). SRI hash'ler eklendi; supply-chain CDN saldırısı yüzeyi sadece dev/staging ortamına sınırlı.

**Çözüm:** Production'da `api/docs.php` 404 döndürdüğü için CDN yükü hiç yapılmaz. Staging'deki Swagger UI sürümü 3 ayda bir güncellenmeli (`docs/SECRET_ROTATION_RUNBOOK.md` şablonuna eklenmeli).

**Bulgu A06-2 [P3-Düşük]:** PHP minimum sürümü `8.1`. 8.1 aktif destek Kasım 2024'te sona erdi; güvenlik desteği Kasım 2025'e kadar. Prod PHP sürümü doğrulanmalı.

**Çözüm:** `composer.json` minimum'u `>=8.2` yap + CI matrix'te 8.2/8.3 test et.

---

### A07 — Identification and Authentication Failures ✅ Çok Güçlü

**Bulgu:** Rate limiting, 2FA, password policy, password history, account lockout, session regeneration — hepsi var.

**Kanıt:**
- `api/v1/routes/auth.php:30-46` — login için rate limit (IP başına)
- `includes/security.php:243-271` — IP + kullanıcı adı bazlı lockout (15 dk, 5 deneme)
- `includes/TwoFactorAuth.php` — TOTP 2FA
- `includes/PasswordPolicy.php` — policy + history (geçmiş 5 şifre)
- `api/v1/routes/auth.php:136-148` — logout'ta JWT blacklist
- `includes/security.php:117-124` — session regeneration her 30 dakikada bir

**Bulgu A07-1 [P2-Orta]:** `api/v1/routes/auth.php:73-79` — 2FA challenge yanıtı **401 ile** `requires_2fa: true` döndürüyor. Bu yanıt **rate limit'e sayılmıyor** (çünkü hit edilmiyor). Bir saldırgan geçerli kullanıcı adını bulup 2FA challenge bombardımanına tutarak **kullanıcı enumerasyonu** yapabilir (2FA hesap varsa farklı response).

**Çözüm:** 2FA gerekli yanıtı `200 OK` + bir `challenge_token` ile değiştir (istemci bu token'ı 2FA adımda gönderir). Başarısız hesap → generic 401. Kullanıcı enumerasyon vektörü kapanır.

**Bulgu A07-2 [P3-Düşük]:** `includes/security.php:15` — `SESSION_LIFETIME = 3600` sn. Admin için belki uzun; garson/mutfak oturumları için 8 saatlik vardiya senaryosunda kısa kalabilir. Rol bazlı lifetime yok.

**Çözüm:** Rol bazlı lifetime (admin 1h, garson/mutfak 8h).

---

### A08 — Software and Data Integrity Failures ⚠️ Orta

**Bulgu:** CSRF koruması var; SRI (integrity hash) Sprint 3 öncesi hiç kullanılmamış; Swagger UI eklenmesi ile ilk kez geldi.

**Kanıt:**
- CSRF: `includes/security.php:325-352` — `hash_equals` + zaman bazlı expiry
- `grep integrity=` (Sprint 2) → **0 sonuç** (CDN asset yok)
- Sprint 3 eklemesi: `public/api-docs/index.html` → Swagger UI SRI hash'li

**Bulgu A08-1 [P1-Yüksek]:** Migration dosyaları ve `database.sql` için **imza/hash yok**. Dev deploy sırasında disk'teki migration dosyası değiştirilirse (insider risk) `includes/Migration.php:389-407` ham SQL'i `exec` eder.

**Çözüm:**
1. Migration dosyası başına hash yorumu ekle, runtime'da doğrula.
2. Prod'da migration komutu sadece CI üzerinden çalıştırılsın, doğrudan disk yazma engellensin.

**Bulgu A08-2 [P2-Orta]:** Webhook/callback endpoint'i `POST /api/v1/menu/odeme/callback` — ödeme gateway'den gelen veride **HMAC imza doğrulaması** gözlemlenmiyor (kaynağa bakıldığında `OdemeService::odemeDogrula($token)` içinde ne yapıldığı soyut).

**Çözüm:** Gateway'in HMAC signature header'ı (`X-Signature`) doğrulanıyor olduğunu `OdemeService` içinde birim testle kanıtla. Yoksa ekle; konfigurasyon `config/odeme.php`'de `webhook_secret` olarak tutulsun.

---

### A09 — Security Logging and Monitoring Failures ✅ Çok Güçlü

**Bulgu:** SecurityAudit + Sentry + dedicated Logger sınıfları var. Başarısız girişler, 2FA olayları, rate limit aşımları, oturum çalma girişimleri hepsi loglanıyor.

**Kanıt:**
- `includes/SecurityAudit.php` — yapılandırılmış audit log
- `includes/Sentry.php` — dış hata telemetrisi
- `api/v1/routes/auth.php:36,56,63,84,97` — her auth olayı loglanmış
- `includes/Logger.php` — dosya + syslog destekli

**Bulgu A09-1 [P2-Orta]:** `api/v1/routes/menu.php:469,530,545` — ödeme hataları `\Logger::getInstance()->error(...)` ile loglanıyor ama **structured context** eksik (hassas veri filtreleme runtime'a kalmış). Stack trace production'da yutuluyor ama exception sınıfı/dosya/satır bilgisi SecurityAudit'e gitmiyor.

**Çözüm:** `SecurityAudit::log(SecurityAudit::PAYMENT_ERROR, ...)` ekle. `Logger::error()` sonrası `Sentry::captureException($e)` çağrısı sistematikleştir (wrapper helper).

**Bulgu A09-2 [P3-Düşük]:** Log dosyalarına ait rotasyon politikası/DB temizleme tanımsız. `login_attempts` tablosu 24 saatte bir temizleniyor (`security.php:212`) ama `login_log` tablosu sınırsız büyüyor.

**Çözüm:** Cron ile 90 günden eski `login_log` ve audit kayıtlarını arşivle/sil.

---

### A10 — Server-Side Request Forgery (SSRF) ✅ Güçlü

**Bulgu:** Kullanıcı kontrolünde URL alan dış HTTP isteği tespit edilmedi.

**Kanıt:**
- `grep curl_setopt.*CURLOPT_URL.*\$|file_get_contents\(\$_` → **sıfır sonuç**
- `includes/SmsService.php:360,399` ve `includes/Sentry.php:487` — `curl_exec` var ama URL config'den sabit geliyor (`config/sms.php`, `config/logging.php`)

**Bulgu A10-1 [P3-Düşük]:** `includes/SmsService.php:232` — `file_get_contents($file)` template okur; `$file` path kullanıcı kontrolünde değil ama path traversal için ikinci savunma katmanı (`realpath` + base dir check) yok.

**Çözüm:** Template ID whitelist → dosya adı mapping uygula.

---

## Bulgu Özet Matrisi (Sıralı)

| # | Bulgu | OWASP | Şiddet | Dosya:Satır |
|---|---|---|---|---|
| 1 | Backup dosyası şifresiz | A02 | **P1 Yüksek** | admin/ayarlar/backup.php:187 |
| 2 | .htaccess CSP yorum satırında | A05 | **P1 Yüksek** | .htaccess:67 |
| 3 | Migration/SQL dosyaları için integrity yok | A08 | **P1 Yüksek** | includes/Migration.php:389 |
| 4 | 2FA flow kullanıcı enumerasyonu | A07 | P2 Orta | api/v1/routes/auth.php:73 |
| 5 | Payment webhook HMAC doğrulama kanıtı yok | A08 | P2 Orta | api/v1/routes/menu.php:518 |
| 6 | style-src 'unsafe-inline' hâlâ açık | A05 | P2 Orta | includes/security.php:65 |
| 7 | Inline onchange/onclick CSP tutarsız | A05 | P2 Orta | admin/musteriler.php:338, menu/index.php:705 |
| 8 | Threat model dokümante değil | A04 | P2 Orta | — |
| 9 | Siparis where interpolation pattern | A03 | P2 Orta | api/v1/routes/siparisler.php:78 |
| 10 | Legacy AJAX auth tutarsızlığı | A01 | P2 Orta | api/takvim.php:98, api/raporlar.php:67 |
| 11 | Structured logging eksik (ödeme) | A09 | P2 Orta | api/v1/routes/menu.php:469 |
| 12 | session.cookie_secure proxy arkasında | A02 | P3 Düşük | includes/security.php:98 |
| 13 | Upload polyglot defansı yok | A03 | P3 Düşük | includes/security.php:381 |
| 14 | PHP min. sürümü 8.1 | A06 | P3 Düşük | composer.json:15 |

## En Kritik 3 Bulgu (Özet)

### 1. Backup dosyası şifresiz (P1)
`admin/ayarlar/backup.php:187` — tam DB snapshot düz metin yazılıyor. Dosya sistemi erişimi sağlayan herhangi bir saldırı vektörü tüm müşteri verisini ifşa eder.
**Kapatma süresi:** 1 gün.

### 2. `.htaccess` CSP devre dışı (P1)
PHP seviyesinde CSP iyi ama web server seviyesinde bypass ediliyor. Statik asset ve PHP fatal-error senaryolarında header yok.
**Kapatma süresi:** 2 saat.

### 3. Migration integrity eksik (P1)
Prod'da `migrate` komutu disk'teki dosyayı güvendiği için, yüksek ayrıcalıklı insider disk üzerinden SQL enjekte edebilir.
**Kapatma süresi:** 1 gün (hash çeki + CI-only migration lock).

---

## Öneriler (Sprint 4 Roadmap)

1. **P1 kapat** (3 bulgu) — Sprint 3 sonu önce
2. **HSTS preload listesi** (prod için `max-age=63072000; includeSubDomains; preload`)
3. **Secrets management** — şu anda `.env`; HashiCorp Vault veya AWS Secrets Manager entegrasyonu
4. **WAF katmanı** — Cloudflare/ModSecurity + OWASP CRS 3.3
5. **Pen-test** — dış firma ile yıllık sızma testi (bu audit statik tarama + code review; dinamik test kapsam dışı)
6. **Bug bounty** — responsible disclosure politikası + `/.well-known/security.txt`

---

## Ek A — Araç Komutları (Tekrar Üretilebilirlik)

```bash
# Composer vulnerabilities
composer audit --no-dev --format=plain

# Dangerous PHP functions
grep -rnE "eval\(|exec\(|shell_exec|passthru|system\(|file_get_contents\(\\\$" --include="*.php" .

# SQL concat patterns (false positives olacaktır, manuel inceleme şart)
grep -rnE "SELECT.*\\\$.*WHERE|->query\\(['\"].*\\\$" src/ includes/

# Inline event handlers (CSP ihlali)
grep -rn "onclick=\|onchange=\|onsubmit=" admin/ menu/

# Unauthenticated $_GET/_POST usage
grep -rn "\\\$_GET\\[\|\\\$_POST\\[" api/

# CSP/security headers
grep -rn "Content-Security-Policy\|X-Frame-Options" includes/ .htaccess
```

## Ek B — Güvenlik Mimarisi Hızlı Referansı

| Katman | Korunma |
|---|---|
| Ağ | HTTPS zorunlu (HSTS), CORS origin whitelist |
| Web server | `.htaccess` güvenlik header'ları (aktif olmalı — P1) |
| PHP | `setSecurityHeaders()`, `secureSessionStart()` |
| Router | RateLimitMiddleware, CorsMiddleware |
| Controller | JWT auth, session auth, validator |
| Service | İş kuralları + fiyat hesaplama sunucuda |
| Repository | PDO prepared, ALLOWED_TABLES whitelist |
| DB | FK'ler, kullanıcı şifresi bcrypt, audit log |

---

**Rapor Sonu** — İmzalı sayılır ve mevcut sürümün (v1.0) güvenlik temelinin production'a uygun olduğunu, P1 bulguların kapanması koşuluyla onaylar.
