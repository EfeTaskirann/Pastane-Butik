# Sprint 2 Regression Raporu

**Tarih:** 2026-04-17
**Hazırlayan:** QA+DevOps (ana-agent tarafından tamamlandı, sub-agent rate limit sonrası)

## PHPUnit Sonuçları

| Metrik | Sprint 0 | Sprint 1 | Sprint 2 | Değişim |
|---|---|---|---|---|
| Toplam test | 90 | 147 | **147** | 0 |
| Pass | 88 | 145 | **145** | 0 |
| Fail | 1 | 1 | **1** | 0 |
| Error | 1 | 1 | **1** | 0 |
| Pass oranı | %97.8 | %98.6 | **%98.6** | 0 |
| Assertion | 173 | 292 | **292** | 0 |

**Sıfır regresyon.** Sprint 2'de yeni test eklenmedi (Backend agent rate limit'e takıldı), ancak mevcut testlerde kırılma yok. Sprint 2 eklenen yeni özellikler (Backup, Audit trail, Settings, SMS, Controller refactor, 2FA UI, Settings UI, Prometheus, Staging) mevcut testleri etkilemedi.

**Kalıcı 2 sorun (Sprint 0'dan beri):**
1. `HelpersTest::it_formats_money_correctly` — `₺1.234,56` beklenen, `1.234,56 ₺` dönüyor. Sembol pozisyonu helper/test uyumsuzluğu.
2. `ValidatorTest::kategori_create_accepts_valid_data` — BaseValidator.php:68 ValidationException. `kategoriler.isim` kolon adı uyumsuzluğu veya required rule semantiği sorunu.

## PHPStan (level 5)

- Sprint 0: 2 error
- Sprint 1: 3 error (+1 cosmetic MasaOturumRepository empty-always-true)
- Sprint 2: **3 error** (Sprint 1 ile aynı — sıfır regresyon)

Kalan errors:
1. `SiparisRepository.php:163` — unknown @param `$durum` (docblock mismatch)
2. `MasaSiparisService.php:304` — enum array `??` always exists (cosmetic)
3. `MasaOturumRepository.php:184` — `empty()` always-true (cosmetic, Sprint 1'de eklendi)

## PHPCS (PSR-12)

Baseline: 149 error + 28 warning. Sprint 2'de büyük ölçüde Windows CRLF kaynaklı; yeni dosyalar da CRLF ile yazıldı. `.gitattributes` ile `eol=lf` normalize edilmeli (Sprint 3 hedefi).

## Sprint 2 Eklenen Özellikler (smoke test)

- [x] `src/Controllers/Admin/UrunController.php` — admin/urunler.php 31 satıra indi (öncesi 400+)
- [x] `views/admin/urunler/` — view katmanı ayrıldı
- [x] `bin/cron/db-backup.php` — DB yedekleme cron
- [x] `admin/ayarlar/backup.php`, `sms.php`, `mail.php`, `2fa.php`, `index.php` — tüm ayar UI'ları
- [x] 2 migration: `add_audit_columns` + `create_ayarlar_tablosu`
- [x] `src/Services/AyarService.php`, `includes/SmsService.php`
- [x] `api/metrics.php` + `includes/Metrics.php` — Prometheus endpoint
- [x] `docker-compose.staging.yml` + `docs/STAGING_SETUP.md`
- [x] `bin/cron/backup-monitor.php`
- [x] 0 inline onclick (admin/*.php)

## Sprint 3 Eylem Listesi

1. Backend agent rate limit sonrası yeni unit test yazamadı — Sprint 3 Backend'i SMS/Ayar test coverage ile başlat
2. Helpers format_money test düzeltilebilir (expected string swap)
3. PSR-12 CRLF sorunu için `.gitattributes` ekle
4. Migration'lar yerel DB'de çalıştırılmadı (dev ortamında test edilmeli)
