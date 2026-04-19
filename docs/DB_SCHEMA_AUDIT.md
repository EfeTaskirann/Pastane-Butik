# DB Schema Audit Raporu

**Üretim Tarihi:** 2026-04-17 13:01:09
**Kaynak:** `database.sql` vs. canlı MySQL şeması (`DESCRIBE`)
**Üreten:** `bin/db-schema-diff.php`

## Durum: 51 fark bulundu

> **Not:** Bu raporu körü körüne uygulamayın. `database.sql` dondurulmuş
> bir dosyadır; gerçek kaynak `database/migrations/*.php` migration'larıdır.
> Bu rapor **migration'lar güncel mi?** sorusunu yanıtlamak için bir
> denetim aracıdır.

### `kategoriler` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `kategoriler` MODIFY COLUMN `id` INT NULL;` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |

### `urunler` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `urunler` MODIFY COLUMN `id` INT NULL;` |
| `updated_at` | UYUMSUZ | `TIMESTAMP` NULL=YES DEFAULT=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | `TIMESTAMP` NULL=NO DEFAULT=current_timestamp() | `ALTER TABLE `urunler` MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |
| `cafe_menusu` | SQL'de YOK | — | `TINYINT(1)` NULL=YES DEFAULT=1 | `// database.sql'e ekle (ör. migration ile eklenmiş): `cafe_menusu` TINYINT(1)` |
| `hazirlanma_suresi` | SQL'de YOK | — | `SMALLINT(6)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `hazirlanma_suresi` SMALLINT(6)` |
| `stok_durumu` | SQL'de YOK | — | `ENUM('VAR','TUKENDI','SINIRLI')` NULL=YES DEFAULT=var | `// database.sql'e ekle (ör. migration ile eklenmiş): `stok_durumu` ENUM('VAR','TUKENDI','SINIRLI')` |
| `slug` | SQL'de YOK | — | `VARCHAR(150)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `slug` VARCHAR(150)` |

### `admin_kullanicilar` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `admin_kullanicilar` MODIFY COLUMN `id` INT NULL;` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |

### `iletisim_mesajlari` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `iletisim_mesajlari` MODIFY COLUMN `id` INT NULL;` |

### `siparisler` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `siparisler` MODIFY COLUMN `id` INT NULL;` |
| `kategori` | UYUMSUZ | `ENUM('PASTA', 'CUPCAKE', 'CHEESECAKE', 'KURABIYE', 'OZEL')` NULL=NO DEFAULT=NULL | `ENUM('PASTA','CUPCAKE','CHEESECAKE','KURABIYE','OZEL')` NULL=NO DEFAULT=NULL | `ALTER TABLE `siparisler` MODIFY COLUMN `kategori` ENUM('PASTA', 'CUPCAKE', 'CHEESECAKE', 'KURABIYE', 'OZEL') NOT NULL;` |
| `odeme_tipi` | UYUMSUZ | `ENUM('ONLINE', 'FIZIKSEL')` NULL=YES DEFAULT='online' | `ENUM('ONLINE','FIZIKSEL')` NULL=YES DEFAULT=online | `ALTER TABLE `siparisler` MODIFY COLUMN `odeme_tipi` ENUM('ONLINE', 'FIZIKSEL') NULL DEFAULT 'online';` |
| `kanal` | UYUMSUZ | `ENUM('SITE', 'CAFE', 'TELEFON')` NULL=YES DEFAULT='site' | `ENUM('SITE','CAFE','TELEFON')` NULL=YES DEFAULT=site | `ALTER TABLE `siparisler` MODIFY COLUMN `kanal` ENUM('SITE', 'CAFE', 'TELEFON') NULL DEFAULT 'site';` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |
| `updated_at` | SQL'de YOK | — | `TIMESTAMP` NULL=NO DEFAULT=current_timestamp() | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_at` TIMESTAMP` |

### `siparis_puan_ayarlari` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `siparis_puan_ayarlari` MODIFY COLUMN `id` INT NULL;` |

### `kategori_fiyatlari` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `kategori_fiyatlari` MODIFY COLUMN `id` INT NULL;` |
| `updated_at` | UYUMSUZ | `TIMESTAMP` NULL=YES DEFAULT=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | `TIMESTAMP` NULL=NO DEFAULT=current_timestamp() | `ALTER TABLE `kategori_fiyatlari` MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;` |

### `musteriler` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `musteriler` MODIFY COLUMN `id` INT NULL;` |
| `updated_at` | UYUMSUZ | `TIMESTAMP` NULL=YES DEFAULT=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | `TIMESTAMP` NULL=NO DEFAULT=current_timestamp() | `ALTER TABLE `musteriler` MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;` |

### `login_attempts` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `login_attempts` MODIFY COLUMN `id` INT NULL;` |

### `login_log` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `login_log` MODIFY COLUMN `id` INT NULL;` |

### `mesajlar` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `mesajlar` MODIFY COLUMN `id` INT NULL;` |

### `site_temalari` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `site_temalari` MODIFY COLUMN `id` INT NULL;` |
| `updated_at` | UYUMSUZ | `TIMESTAMP` NULL=YES DEFAULT=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | `TIMESTAMP` NULL=NO DEFAULT=current_timestamp() | `ALTER TABLE `site_temalari` MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;` |

### `masalar` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `masalar` MODIFY COLUMN `id` INT NULL;` |
| `durum` | UYUMSUZ | `ENUM('BOS', 'AKTIF', 'KAPALI')` NULL=YES DEFAULT='bos' | `ENUM('BOS','AKTIF','KAPALI')` NULL=YES DEFAULT=bos | `ALTER TABLE `masalar` MODIFY COLUMN `durum` ENUM('BOS', 'AKTIF', 'KAPALI') NULL DEFAULT 'bos';` |
| `guncelleme_tarihi` | UYUMSUZ | `DATETIME` NULL=YES DEFAULT=CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | `DATETIME` NULL=YES DEFAULT=current_timestamp() | `ALTER TABLE `masalar` MODIFY COLUMN `guncelleme_tarihi` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |

### `masa_oturumlari` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `masa_oturumlari` MODIFY COLUMN `id` INT NULL;` |
| `durum` | UYUMSUZ | `ENUM('AKTIF', 'TAMAMLANDI', 'IPTAL')` NULL=YES DEFAULT='aktif' | `ENUM('AKTIF','TAMAMLANDI','IPTAL')` NULL=YES DEFAULT=aktif | `ALTER TABLE `masa_oturumlari` MODIFY COLUMN `durum` ENUM('AKTIF', 'TAMAMLANDI', 'IPTAL') NULL DEFAULT 'aktif';` |

### `masa_siparisleri` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `masa_siparisleri` MODIFY COLUMN `id` INT NULL;` |
| `durum` | UYUMSUZ | `ENUM('BEKLEMEDE', 'ONAYLANDI', 'HAZIRLANIYOR', 'HAZIR', 'TESLIM_EDILDI', 'IPTAL')` NULL=YES DEFAULT='beklemede' | `ENUM('BEKLEMEDE','ONAYLANDI','HAZIRLANIYOR','HAZIR','TESLIM_EDILDI','IPTAL')` NULL=YES DEFAULT=beklemede | `ALTER TABLE `masa_siparisleri` MODIFY COLUMN `durum` ENUM('BEKLEMEDE', 'ONAYLANDI', 'HAZIRLANIYOR', 'HAZIR', 'TESLIM_EDILDI', 'IPTAL') NULL DEFAULT 'beklemede';` |
| `odeme_durumu` | UYUMSUZ | `ENUM('ODENMEDI', 'ODENDI', 'IADE')` NULL=YES DEFAULT='odenmedi' | `ENUM('ODENMEDI','ODENDI','IADE')` NULL=YES DEFAULT=odenmedi | `ALTER TABLE `masa_siparisleri` MODIFY COLUMN `odeme_durumu` ENUM('ODENMEDI', 'ODENDI', 'IADE') NULL DEFAULT 'odenmedi';` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |

### `masa_siparis_kalemleri` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `masa_siparis_kalemleri` MODIFY COLUMN `id` INT NULL;` |

### `odeme_islemleri` tablosu

| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |
|---|---|---|---|---|
| `id` | UYUMSUZ | `INT` NULL=YES DEFAULT=NULL | `INT(11)` NULL=NO DEFAULT=NULL | `ALTER TABLE `odeme_islemleri` MODIFY COLUMN `id` INT NULL;` |
| `para_birimi` | UYUMSUZ | `VARCHAR(3)` NULL=YES DEFAULT='TRY' | `VARCHAR(3)` NULL=YES DEFAULT=TRY | `ALTER TABLE `odeme_islemleri` MODIFY COLUMN `para_birimi` VARCHAR(3) NULL DEFAULT 'TRY';` |
| `durum` | UYUMSUZ | `ENUM('BASLATILDI', 'BASARILI', 'BASARISIZ', 'IADE')` NULL=YES DEFAULT='baslatildi' | `ENUM('BASLATILDI','BASARILI','BASARISIZ','IADE')` NULL=YES DEFAULT=baslatildi | `ALTER TABLE `odeme_islemleri` MODIFY COLUMN `durum` ENUM('BASLATILDI', 'BASARILI', 'BASARISIZ', 'IADE') NULL DEFAULT 'baslatildi';` |
| `gateway_yaniti` | UYUMSUZ | `JSON` NULL=YES DEFAULT=NULL | `LONGTEXT` NULL=YES DEFAULT=NULL | `ALTER TABLE `odeme_islemleri` MODIFY COLUMN `gateway_yaniti` JSON NULL DEFAULT NULL;` |
| `created_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `created_by` INT(11)` |
| `updated_by` | SQL'de YOK | — | `INT(11)` NULL=YES DEFAULT=NULL | `// database.sql'e ekle (ör. migration ile eklenmiş): `updated_by` INT(11)` |

