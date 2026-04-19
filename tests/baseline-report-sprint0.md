# Sprint 0 Baseline Test Raporu

**Rapor Tarihi:** 2026-04-17
**Sorumlu:** QA + DevOps Engineer (Junior-Mid)
**Ortam:** Yerel (Windows 11, XAMPP)
**PHP:** 8.2.12 (cli, ZTS Visual C++ 2019 x64)
**Çalıştırılan araçlar:** PHPUnit 10.5.60, PHPStan 1.x (level 5), PHP_CodeSniffer (PSR-12)

Bu rapor, Sprint 0 başlangıcındaki test ve statik analiz durumunu referans alınması için
dondurur. Sayılar birebir komut çıktısından alınmıştır; uydurma yoktur.

---

## 1. PHPUnit — Unit / Integration / Feature

**Komut:** `vendor/bin/phpunit`

**Konfigürasyon:** `phpunit.xml` (failOnRisky=true, failOnWarning=true)

### Özet

| Metrik              | Değer |
|---------------------|-------|
| Toplam test         | 90    |
| Toplam assertion    | 173   |
| Başarılı (pass)     | 88    |
| Hata (error)        | 1     |
| Başarısız (fail)    | 1     |
| Skip                | 0     |
| PHPUnit warning     | 1     |
| Başarı oranı        | %97.8 |
| Süre                | 00:01.458 |
| Bellek              | 10.00 MB |

### Kritik Bulgular

1. **Failure — `HelpersTest::it_formats_money_correctly`**
   - Dosya: `tests/Unit/HelpersTest.php:131`
   - Beklenen: `'₺1.234,56'` (sembol başta)
   - Gerçek: `'1.234,56 ₺'` (sembol sonda)
   - Kök neden: `includes/helpers.php` içindeki `format_money()` sembolü sonra koyuyor
     ama test symbol-prefix bekliyor. Test veya helper'dan biri güncellenmeli.

2. **Error — `ValidatorTest::kategori_create_accepts_valid_data`**
   - Dosya: `tests/Unit/ValidatorTest.php:203`
   - Fırlatan: `Pastane\Exceptions\ValidationException` (BaseValidator.php:68)
   - Kök neden (olası): `KategoriValidator` kuralları, kolon adı `isim` vs `ad` uyumsuzluğu
     (CLAUDE.md'deki tekrar eden hata kalıbı). Test fixture muhtemelen `ad` veriyor ama
     validator `isim` bekliyor — doğrulanması gerekir.

3. **PHPUnit Warning — Coverage driver yok**
   - `No code coverage driver available` — Xdebug veya PCOV ekli değil, coverage raporu
     üretilemiyor. CI'da xdebug kurulu (PHP matrix) ama yerelde yok. Baseline kapsam
     metriği üretilemedi.

### Çalışan Test Süitleri (Özet)
- HttpException: 11/11
- JWT: 8/8
- PasswordPolicy: 12/12
- Router: 4/4
- ServiceIntegration (Urun/Kategori/Mesaj): 14/14
- TwoFactorAuth: 9/9
- ValidationException: 3/3
- Helpers: 9/10 (1 fail)
- Validator: 14/15 (1 error)

---

## 2. PHPStan — Statik Analiz

**Komut:** `vendor/bin/phpstan analyse --no-progress`
**Konfigürasyon:** `phpstan.neon` (level 5, paths=src, bootstrap=includes/bootstrap.php)

### Özet

| Metrik      | Değer |
|-------------|-------|
| Level       | 5     |
| Error count | 2     |
| Status      | Fail  |

### Tüm Hatalar

1. `src/Repositories/SiparisRepository.php:163`
   - `PHPDoc tag @param references unknown parameter: $durum`
   - Kök neden: PHPDoc'ta `$durum` yazıyor ama metot imzasında böyle bir parametre yok
     (CLAUDE.md'deki "`siparisler` tablosunda `durum` kolonu yok, `tamamlandi` var"
     kalıbıyla tutarlı — muhtemelen eski docblock kalıntısı).

2. `src/Services/MasaSiparisService.php:304`
   - Null-coalescing `??` operatörü zaten var olan bir anahtarın üzerinde kullanılıyor.
   - Uyarı: `Offset 'hazir'|'hazirlaniyor'|... on array{...} on left side of ?? always
     exists and is not nullable`.
   - Kök neden: Durum etiketi haritasına erişim yapılırken gereksiz `??` fallback var.

---

## 3. PHP_CodeSniffer — PSR-12

**Komut:** `vendor/bin/phpcs --standard=PSR12 src/ includes/`

### Özet

| Metrik                    | Değer |
|---------------------------|-------|
| Toplam ERROR              | 149   |
| Toplam WARNING            | 28    |
| Etkilenen dosya           | 48    |
| Otomatik düzeltilebilir   | 135   |

### En Çok İhlal Eden Dosyalar (Top 10)

| Dosya                                    | Errors | Warnings |
|------------------------------------------|-------:|---------:|
| `includes/functions.php`                 | 22     | 1        |
| `includes/security.php`                  | 19     | 1        |
| `includes/db.php`                        | 18     | 3        |
| `includes/PasswordPolicy.php`            | 15     | 1        |
| `includes/SecurityAudit.php`             | 7      | 1        |
| `includes/JWT.php`                       | 5      | 0        |
| `includes/HealthCheck.php`               | 5      | 0        |
| `includes/RateLimiter.php`               | 4      | 0        |
| `includes/Cache.php`                     | 3      | 0        |
| `includes/config.php`                    | 3      | 1        |

### Tekrar Eden Sorun Kalıpları

- **Satır sonu karakteri**: CRLF (`\r\n`) — Windows'tan commit edilmiş dosyalar; PSR-12
  `\n` istiyor.
- **Namespace eksikliği**: `includes/SecurityAudit.php`, `includes/TwoFactorAuth.php` gibi
  global scope'taki sınıflar PSR-12'ye aykırı.
- **Header block ayırma**: Birçok dosyada file header ile kod arasında boş satır yok.
- **120 karakter limiti**: 28 warning'in çoğu uzun satır uyarısı.
- **Multi-line kontrol yapıları**: Girinti ve kapanış parantezi konumu.

**Not:** 135/149 error PHPCBF ile otomatik düzeltilebilir. Bir sonraki sprintte
`phpcbf --standard=PSR12 src/ includes/` çalıştırılması tavsiye edilir.

---

## 4. CI Pipeline Güncellemeleri (Sprint 0)

### `.github/workflows/ci.yml` değişiklikleri

| Değişiklik                                    | Öncesi               | Sonrası              |
|-----------------------------------------------|----------------------|----------------------|
| `actions/cache` (lint job)                    | v3                   | v4                   |
| `actions/cache` (test job)                    | v3                   | v4                   |
| `actions/upload-artifact` (frontend)          | v3                   | v4                   |
| `codecov/codecov-action`                      | v3                   | v4 (+ `token`)       |
| Composer install `--no-suggest`               | var                  | kaldırıldı           |
| PHPStan komutu                                | `analyse src -l 5`   | `analyse` (neon'dan) |
| PHP matrix                                    | 8.1, 8.2, 8.3        | 8.2, 8.3             |
| `fail-fast: false` (test strategy)            | yok                  | eklendi              |

### `Dockerfile` değişiklikleri
- `node:20-alpine` → `node:20.11-alpine` (minor pin)
- `composer:2` → `composer:2.7` (minor pin)
- `php:8.2-apache` → `php:8.2.15-apache` (minor pin)
- HEALTHCHECK zaten mevcut (`/api/health/live` — değiştirilmedi).

### `docker-compose.yml` değişiklikleri
- `mysql:8.0` → `mysql:8.0.36` (patch pin)
- `redis:7-alpine` → `redis:7.2-alpine` (minor pin)

---

## 5. Karşılaşılan Engeller

1. **Coverage driver yok**: Yerelde Xdebug/PCOV kurulu olmadığı için baseline
   coverage yüzdesi çıkarılamadı. CI'da xdebug var, CI çıktısı beklenmeli.
2. **Test fixture DB**: Integration testler için `pastane_test` DB gerekiyor; yerelde
   mevcut görünüyor (integration testleri pass oldu), ancak CI'da service container
   kuruluyor.
3. **PSR-12 CRLF warnings**: Repo Windows'ta geliştirildiği için satır sonu karakteri
   CRLF. `.gitattributes` ile `text=auto eol=lf` önerilir — bu raporun kapsamı dışı.
4. **Test başarısızlıkları bug değil, uyumsuzluk**: 2 test başarısızlığı da kod/test
   uyumsuzluğu — kritik regresyon değil. Sprint 1'de ele alınabilir.

---

## 6. Sprint 1 için Öneriler

1. `HelpersTest` ↔ `format_money()` sembol konum kararını netleştir ve uygula.
2. `KategoriValidator` test fixture'ını gerçek kural seti ile hizala (kolon adı `isim`).
3. `SiparisRepository.php:163` eski `@param $durum` PHPDoc'unu temizle.
4. `MasaSiparisService.php:304` gereksiz `??` fallback'i kaldır.
5. `phpcbf` çalıştırarak 135 otomatik PSR-12 düzeltmesini uygula (Windows CRLF →
   Unix LF dönüşümü için `.gitattributes` güncellemesi gerekir).
6. `includes/SecurityAudit.php`, `includes/TwoFactorAuth.php` gibi namespace'siz
   sınıflar için autoload stratejisi netleştirilmeli (PSR-4 vs classmap).

---

**Dondurulmuş baseline sayıları:** PHPUnit 88/90 pass, PHPStan 2 error @ L5,
PHPCS 149 error + 28 warning. Sprint 1 sonunda aynı komutlarla ölçüp iyileşme
miktarı raporlanacak.
