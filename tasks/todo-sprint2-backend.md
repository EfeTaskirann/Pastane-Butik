# Sprint 2 — Backend Developer TODO

**Rol:** Backend Developer (Mid-Senior PHP)
**Başlangıç:** 2026-04-17
**Kapsam:** Backup UI + cron, Audit trail, Settings backend, SMS notification

## Sorumluluk alanı dosyaları
- [ ] bin/cron/db-backup.php
- [ ] admin/ayarlar/backup.php
- [ ] admin/ayarlar/sms.php
- [ ] database/migrations/2026_04_17_000001_add_audit_columns.php
- [ ] database/migrations/2026_04_17_000002_create_ayarlar_tablosu.php
- [ ] src/Repositories/AyarRepository.php
- [ ] src/Services/AyarService.php
- [ ] includes/SmsService.php
- [ ] includes/functions.php (auth_user_id + ayar helpers)
- [ ] src/Repositories/BaseRepository.php (audit auto-inject)
- [ ] storage/views/sms/*.txt
- [ ] config/sms.php
- [ ] src/Services/SiparisService.php (SMS hook)
- [ ] tests/Unit/AyarServiceTest.php
- [ ] tests/Unit/SmsServiceTest.php
- [ ] CLAUDE.md (dersler)
- [ ] .env.example (SMS vars)

## Öncelik sırası
1. Migration'lar (DB first)
2. Audit trail — BaseRepository injection
3. AyarRepository + AyarService + helper
4. SmsService + template'ler + config
5. SiparisService SMS hook
6. Admin UI: backup + sms
7. Cron script (db-backup)
8. Tests (min 6 yeni)
9. PHPUnit çalıştır — %85+ kalsın
10. CLAUDE.md + .env.example güncelle

## DB gerçekliği (mysql -e SHOW TABLES ile doğrulandı 2026-04-17)
- `ayarlar` tablosu DB'de YOK → migration oluşturmalı
- `admin_kullanicilar` tablosunda `rol`, `aktif`, `email`, `ad_soyad` kolonları YOK → audit FK ekleme çalışsın diye admin_kullanicilar(id) ref yeterli
- `urunler.isim`, `kategoriler.isim` (not `ad`!)
- `siparisler.tamamlandi` (not `durum`!)

## Çıktı
- tasks/review-sprint2-backend.md (rapor)
