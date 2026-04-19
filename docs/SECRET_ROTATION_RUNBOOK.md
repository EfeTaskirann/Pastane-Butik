# SECRET ROTATION RUNBOOK

**Proje:** Tatlı Düşler Butik Pastane
**Sahip:** Tech Lead
**Son güncelleme:** 2026-04-17
**Kapsam:** Üretim / staging ortamlarında `.env` içindeki hassas değerlerin güvenli rotasyonu.

> Bu runbook, JWT_SECRET / APP_KEY / DB_PASSWORD / IYZICO_* anahtarlarının **planlı veya acil** olarak yenilenmesini adım adım tanımlar. Bu doküman **audit sonucu** yazılmıştır; anahtarların fiilen sızması halinde mutlaka izlenmelidir.

---

## 0. Audit Özeti (2026-04-17)

Sprint 0 kapsamında yapılan secret audit bulguları:

- **`.env` diskte yok** (`c:\xampp\htdocs\pastane\.env`). Yalnızca `.env.example` takipli.
- **`.gitignore` doğru yapılandırılmış**: `.env`, `.env.local`, `.env.*.local`, `.env.production`, `.env.staging` ignore'lu.
- **Git geçmişinde `.env` HİÇ commit edilmemiş** (`git log --all --full-history -- .env` boş).
- **`.env.example` sadece placeholder içeriyor**:
  - `APP_KEY=base64:GENERATE_A_32_CHAR_RANDOM_KEY_HERE` (placeholder)
  - `JWT_SECRET=GENERATE_A_SECURE_JWT_SECRET_HERE` (placeholder)
  - `DB_PASSWORD=` (boş — localhost dev için)
  - `IYZICO_API_KEY=` / `IYZICO_SECRET_KEY=` (boş)
  - `MAIL_USERNAME=` / `MAIL_PASSWORD=` (boş)
- `includes/JWT.php`'de placeholder değerler için **runtime sanity check** var (JWT_SECRET placeholder ise exception fırlatıyor). İyi.

**Sonuç:** Bilinen bir sızıntı yok. Bu runbook önleyici/planlı rotasyon için referanstır.

---

## 1. Tehdit Modeli — Ne Zaman Rotate Edilir?

Aşağıdaki durumların **herhangi biri** gerçekleşirse ACİL rotasyon yapılır:

| # | Tetikleyici | Öncelik |
|---|---|---|
| 1 | `.env` yanlışlıkla git'e commit edildi (history'de geçse bile) | P0 |
| 2 | Geliştirici/ekip üyesi ayrıldı ve prod secret'lara erişimi vardı | P0 |
| 3 | Log/cache içinde secret görüldü (stdout, sentry, cloudwatch vb.) | P0 |
| 4 | JWT ile imzalı token istemci tarafında tampere edildi veya yetkisiz erişim tespit edildi | P0 |
| 5 | Ödeme gateway (iyzico) panelinde anormal işlem görüldü | P0 |
| 6 | DB backup dışarıya sızdı | P0 |
| 7 | Planlı rotasyon (en az **yılda 1 kez** JWT_SECRET, **6 ayda 1 kez** DB_PASSWORD) | P2 |

---

## 2. Rotasyon Ön Gereklilikler

- [ ] Prod DB'nin son 24 saat içinde backup'ı alındığını doğrula.
- [ ] Maintenance window planla (JWT_SECRET rotasyonu tüm aktif oturumları geçersiz kılar).
- [ ] `#incident` kanalına "secret rotation başlıyor" uyarısı at.
- [ ] Erişim: prod sunucuya SSH + DB admin + iyzico panel erişiminin hazır olduğunu doğrula.

---

## 3. JWT_SECRET Rotasyonu

**Etki:** Tüm aktif JWT token'lar geçersiz olur — kullanıcılar yeniden login olmak zorunda kalır.
**Maintenance window:** ~5 dk.

### Adımlar

1. **Yeni secret üret (sunucuda):**
   ```bash
   php -r "echo base64_encode(random_bytes(64)), PHP_EOL;"
   ```
   > `random_bytes(64)` = 512-bit entropy. `openssl rand -base64 64` de kullanılabilir ama PHP ile aynı binary olmalı.

2. **Eski değeri yedekle** (rollback için):
   ```bash
   cp /var/www/pastane/.env /var/www/pastane/.env.bak.$(date +%Y%m%d-%H%M%S)
   ```
   > `.env.bak.*` da `.gitignore` tarafından kapsandığından emin ol (bizim `.gitignore` `.env.*.local` kapsar — farklı pattern için ignore ekle).

3. **`.env` dosyasını güncelle:**
   ```env
   JWT_SECRET=<yeni_üretilen_değer>
   ```

4. **PHP-FPM/OPcache flush** (env cache için):
   ```bash
   sudo systemctl reload php-fpm
   # veya
   sudo service php8.2-fpm reload
   ```

5. **Doğrula:**
   ```bash
   curl -i https://prod.pastane.tr/api/v1/auth/login \
     -X POST -H "Content-Type: application/json" \
     -d '{"kullanici_adi":"test","sifre":"test"}'
   # Yeni token dönüyor olmalı
   ```

6. **JWT blacklist tablosunu temizle** (opsiyonel, tablo büyümesin):
   ```sql
   DELETE FROM jwt_blacklist WHERE expires_at < NOW();
   ```

7. **Rollback planı** (5 dk içinde token hataları başlarsa):
   ```bash
   cp /var/www/pastane/.env.bak.<timestamp> /var/www/pastane/.env
   sudo systemctl reload php-fpm
   ```

8. **İletişim:** Admin panel kullanıcılarına "yeniden giriş yapmanız gerekiyor" duyurusu gönder.

---

## 4. APP_KEY Rotasyonu

**Etki:** APP_KEY ile şifrelenmiş session cookie'leri, encrypted payload'lar geçersizleşir. Şu an projede `APP_KEY` doğrudan encryption için kullanılmıyor (JWT_SECRET ayrıldı — bkz. `includes/JWT.php:47`), ama yine de session hijack riskine karşı rotate edilmelidir.

### Adımlar

1. **Yeni key üret:**
   ```bash
   php -r "echo 'base64:', base64_encode(random_bytes(32)), PHP_EOL;"
   ```

2. **`.env`'de güncelle:**
   ```env
   APP_KEY=base64:<yeni_32_byte_değer>
   ```

3. **Session klasörünü temizle** (eski session'lar geçersizleşsin):
   ```bash
   rm -f /var/www/pastane/storage/sessions/*
   ```

4. **PHP-FPM reload** + smoke test (admin girişi).

---

## 5. DB_PASSWORD Rotasyonu

**Etki:** Yanlış sıralama downtime yaratır. Önce DB user'ına yeni şifre tanımla, sonra `.env` güncelle, sonra eski şifreyi çek.

### Adımlar

1. **Yeni şifre üret:**
   ```bash
   openssl rand -base64 32 | tr -d '=+/' | cut -c1-32
   ```

2. **MySQL'e iki şifre ata** (DUAL PASSWORD — MySQL 8.0.14+):
   ```sql
   ALTER USER 'pastane_user'@'localhost' IDENTIFIED BY '<yeni_şifre>' RETAIN CURRENT PASSWORD;
   FLUSH PRIVILEGES;
   ```
   > Bu aşamada DB hem eski hem yeni şifreyi kabul eder. **Downtime yok.**

3. **`.env`'i güncelle:**
   ```env
   DB_PASSWORD=<yeni_şifre>
   ```

4. **PHP-FPM reload** + smoke test:
   ```bash
   sudo systemctl reload php-fpm
   curl -f https://prod.pastane.tr/admin/dashboard.php || echo "FAIL"
   ```

5. **Eski şifreyi çek** (smoke test geçtikten sonra):
   ```sql
   ALTER USER 'pastane_user'@'localhost' DISCARD OLD PASSWORD;
   FLUSH PRIVILEGES;
   ```

6. **MySQL 5.7 için fallback** (DUAL PASSWORD yok):
   - Kısa downtime kabul et: reload PHP-FPM olmadan önce read-only mode'a al, sonra şifreyi değiştir, `.env` güncelle, reload et.

---

## 6. IYZICO_API_KEY / IYZICO_SECRET_KEY Rotasyonu

**Etki:** Ödeme API'sı geçici olarak (birkaç saniye) başarısız dönebilir — rotation window küçük tutulmalı.

### Adımlar

1. **iyzico merchant panel**'e git → Entegre Ayarları → Yeni API Key üret.
2. Yeni key + secret'i kopyala.
3. **`.env`** güncelle:
   ```env
   IYZICO_API_KEY=<yeni_key>
   IYZICO_SECRET_KEY=<yeni_secret>
   ```
4. **PHP-FPM reload.**
5. **Smoke test** — sandbox'ta test ödeme akışı çalıştır (gerçek kart kullanma).
6. **Eski key'i iyzico panelden DEVRE DIŞI BIRAK** — silme, sadece deactivate et (audit trail için).

---

## 7. Git History'de Sızıntı Senaryosu (P0)

Eğer audit'te `.env` commit'i bulunursa:

1. **Önce tüm secret'ları rotate et** (yukarıdaki 3-6. adımlar). Bu **en önemli** adımdır — history temizliği tek başına yeterli DEĞİL.
2. **Ekibe bildir** — git history rewriting tüm checkout'ları kıracak.
3. **BFG Repo-Cleaner** (filter-branch'ten daha güvenli):
   ```bash
   # Önce backup:
   git clone --mirror git@github.com:org/pastane.git pastane-mirror.bak
   # BFG çalıştır:
   bfg --delete-files .env pastane.git
   cd pastane.git
   git reflog expire --expire=now --all && git gc --prune=now --aggressive
   git push --force
   ```
4. **Ekibe talimat:** Tüm local kopyaları silin, `git clone` ile yeniden çekin.
5. **GitHub/GitLab secret scanning** alarmlarını kontrol et.

> **Bu projede `git log --all --full-history -- .env` boş** (audit 2026-04-17). Bu adıma ihtiyaç YOK.

---

## 8. Rotasyon Sonrası Checklist

- [ ] `.env` dosya izinleri: `chmod 600 .env && chown www-data:www-data .env`
- [ ] Eski backup `.env.bak.*` dosyalarını 24 saat sonra sil.
- [ ] `storage/logs/*.log` içinde yeni secret görünmüyor (grep ile kontrol).
- [ ] Yeni değerler **şifre yöneticisine** (1Password / Bitwarden) kaydedildi.
- [ ] Runbook'a tarih/kim/hangi secret notu düşüldü (aşağıdaki tablo).

---

## 9. Rotasyon Geçmişi

| Tarih | Secret | Sebep | Yapan | Ticket |
|-------|--------|-------|-------|--------|
| 2026-04-17 | — (audit) | Sprint 0 audit — rotation ihtiyacı yok | Tech Lead | SPRINT-0 |

---

## 10. Kaynaklar

- [MySQL Dual Password Docs](https://dev.mysql.com/doc/refman/8.0/en/alter-user.html#alter-user-dual-password)
- [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/)
- [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)
- `includes/JWT.php` — placeholder sanity check implementasyonu
