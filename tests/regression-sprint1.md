# Sprint 1 Regression Test Planı

**Hazırlayan:** QA + DevOps Engineer (Junior-Mid)
**Tarih:** 2026-04-17
**Kapsam:** Sprint 0'da yapılan değişikliklerin Sprint 1 boyunca bozulmadığını
kanıtlamak için smoke test checklist'i. Gerçek sonuçlar
`tests/regression-report-sprint1.md` dosyasındadır.

Sprint 0'da dokunulan alanlar:
- CSP hardening (inline handler yasağı, `nonce` kullanımı)
- Parola hash algoritması (`password_hash`, `PASSWORD_ARGON2ID`)
- `.env` + `Env::load()` refactor
- CI pipeline (`.github/workflows/ci.yml`)
- `Dockerfile` / `docker-compose.yml` pin
- Sentry env keyleri eklendi (`SENTRY_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_TRACES_SAMPLE_RATE`)

Sprint 1'de eklenen:
- `includes/Sentry.php` (native client), bootstrap'te init
- `api/health.php` yeni JSON şema + liveness/readiness ayrı
- `bin/cron/log-rotate.php`
- (paralel agent'lar) RBAC, OdemeService testleri, activity log UI

---

## 1. CSP Regression (kritik)

CSP Sprint 0'da sertleştirildi; Sprint 1'de admin sayfalarına müdahale varsa kırılabilir.

- [ ] `/admin/kategoriler.php` sayfası yüklendiğinde browser console'da CSP violation yok
- [ ] `/admin/urunler.php` sayfasında inline `onclick=` kullanılmamalı (grep ile doğrula)
- [ ] `/admin/takvim.php` içindeki FullCalendar init scriptleri `nonce` ile çalışmalı
- [ ] Admin'de yeni eklenen "mutfak.php" / "garson.php" / "masalar.php" dosyalarında
      inline handler var mı?
- [ ] `Content-Security-Policy` header `.htaccess` veya `includes/security.php` içinde
      hâlâ set ediliyor
- [ ] `unsafe-inline` yok — ya nonce ya da hash var

Grep komutları:
```
rg "onclick=" admin/
rg "Content-Security-Policy" includes/ .htaccess
rg "nonce=" admin/includes/header.php
```

## 2. Password Hashing Regression

- [ ] Admin login akışı çalışıyor (auth.php → `password_verify()` dönüyor)
- [ ] Yeni admin oluşturulurken `password_hash($p, PASSWORD_ARGON2ID)` kullanılıyor
- [ ] `$2y$` (bcrypt) legacy hash'ler hâlâ doğrulanabiliyor (rehash fallback)
- [ ] `docs/` veya `README` plaintext parola örneği içermiyor

Grep:
```
rg "password_hash\(" src/ includes/ admin/
rg "md5\(|sha1\(" src/ includes/ admin/
```

## 3. .env / Env::load() Regression

- [ ] `.env` yoksa bile sistem no-op modda çalışır (CI ortamı)
- [ ] `.env` içindeki `${VAR}` referansları parse ediliyor (`MAIL_FROM_NAME="${APP_NAME}"`)
- [ ] `DB_*` ayarları `.env`'den okunuyor, `config.php` constant'ları eşleşiyor
- [ ] `SENTRY_DSN` boşken `Sentry::init()` no-op
- [ ] `SENTRY_DSN` dolu (fake) iken `Sentry::isEnabled()` === true

## 4. CI Pipeline Regression

- [ ] `.github/workflows/ci.yml` — PHP 8.2 + 8.3 matrix pass
- [ ] `actions/cache@v4` çağrısı hâlâ mevcut
- [ ] `codecov/codecov-action@v4` token ile çağrılıyor
- [ ] PHPStan komutu `analyse` (neon ile) — CLI flag yok
- [ ] `fail-fast: false` test strategy'sinde var
- [ ] PHPUnit CI'da xdebug coverage driver yüklüyor (yerelde yok)

## 5. Docker Regression

- [ ] `Dockerfile` FROM tag'ları patch-pinned
- [ ] `HEALTHCHECK CMD curl -f http://localhost/api/health/live`
      → yeni JSON şemada `"status": "ok"` döner (503 değil)
- [ ] `docker-compose.yml` `mysql:8.0.36`, `redis:7.2-alpine`
- [ ] Container start → DB hazır → web listening (CI'da integration smoke)

## 6. Sentry Entegrasyonu (YENİ — Sprint 1)

- [ ] `bin/test-sentry.php --dry` çalıştığında DSN boş ise "no-op" mesajı döner
- [ ] DSN dolu iken `captureException()` çağrısı PHP hata vermez (best-effort, sessiz başarısız OK)
- [ ] `Sentry::setUser()` sonrası password/credit_card alanları `[Filtered]` olur
- [ ] Global `set_exception_handler` chain edildi, önceki handler ezilmedi
- [ ] `register_shutdown_function` fatal error'da captureMessage çağırır

## 7. Health Check (YENİ — Sprint 1)

- [ ] `GET /api/health` → JSON: `{status, timestamp, checks: {database, disk, php, uptime_sec}, version}`
- [ ] `GET /api/health/live` → sadece `{status: ok, php, version}` — DB'ye bakmaz
- [ ] `GET /api/health/ready` → DB + cache kontrolü — DB down ise 503
- [ ] `?detailed=true` query param memory/writable_paths/php_extensions ekler
- [ ] DB down senaryo (simülasyon) → HTTP 503 döner
- [ ] `/api/health/metrics` — auth yoksa 401

## 8. Log Rotate Cron (YENİ — Sprint 1)

- [ ] `php bin/cron/log-rotate.php --dry-run` hatasız çıkar (exit 0)
- [ ] 10 MB'dan büyük dosya olmadığında `skipped` sayar, `rotated=0`
- [ ] Oluşturulan test dosyası (>10MB) rotate edilir → `.1.gz`
- [ ] 7 günden eski `.gz` dosyalar silinir (`expired` sayacı)
- [ ] Windows Task Scheduler komut örneği README'de veya inline header'da mevcut

## 9. PHPUnit Test Suite Regression

Sprint 0 baseline: **90 test / 88 pass / 1 fail / 1 error** (bkz `baseline-report-sprint0.md`).

- [ ] Sprint 1 sonu `vendor/bin/phpunit` çıktısı baseline'dan geriye gitmedi
- [ ] Yeni testler (OdemeService, MasaSiparisService, RBAC) suite'e eklendi
- [ ] HelpersTest::it_formats_money_correctly — mevcut fail ya düzeltildi ya hâlâ 1 fail
- [ ] ValidatorTest::kategori_create_accepts_valid_data — mevcut error
      (`kategoriler.isim` kolon adı kalıbı) hâlâ baseline'la aynı veya düzeltildi

## 10. Statik Analiz & Lint (bilgilendirme)

- [ ] PHPStan level 5 error sayısı ≤ baseline (2)
- [ ] PHPCS error sayısı ≤ baseline (149) — phpcbf önerildi, uygulandı mı?

---

## Koşum Talimatı

```bash
# 1. Birim + entegrasyon
vendor/bin/phpunit --testdox

# 2. Statik analiz
vendor/bin/phpstan analyse --no-progress

# 3. Sağlık kontrolü
curl -s http://localhost/pastane/api/health | jq .
curl -s http://localhost/pastane/api/health/live | jq .
curl -s -o /dev/null -w "%{http_code}" http://localhost/pastane/api/health/ready

# 4. Sentry smoke
php bin/test-sentry.php --dry

# 5. Log rotate smoke
php bin/cron/log-rotate.php --dry-run
```

Test sonuçları `tests/regression-report-sprint1.md` dosyasında.
