-- Satış Rapor Sistemi Migration
-- Tarih: 2024
-- Bu dosyayı phpMyAdmin veya MySQL CLI ile çalıştırın

USE pastane_db;

-- 1. Siparişler tablosuna yeni alanlar ekle
ALTER TABLE siparisler
    ADD COLUMN IF NOT EXISTS birim_fiyat DECIMAL(10,2) DEFAULT 0.00 AFTER puan,
    ADD COLUMN IF NOT EXISTS toplam_tutar DECIMAL(10,2) DEFAULT 0.00 AFTER birim_fiyat,
    ADD COLUMN IF NOT EXISTS odeme_tipi ENUM('online', 'fiziksel') DEFAULT 'online' AFTER toplam_tutar,
    ADD COLUMN IF NOT EXISTS kanal ENUM('site', 'cafe', 'telefon') DEFAULT 'site' AFTER odeme_tipi;

-- 2. Performans için indexler
CREATE INDEX IF NOT EXISTS idx_siparisler_tarih ON siparisler(tarih);
CREATE INDEX IF NOT EXISTS idx_siparisler_tarih_kanal ON siparisler(tarih, kanal);
CREATE INDEX IF NOT EXISTS idx_siparisler_kategori ON siparisler(kategori);
CREATE INDEX IF NOT EXISTS idx_siparisler_tamamlandi ON siparisler(tamamlandi);

-- 3. Kategori varsayılan fiyatları tablosu
CREATE TABLE IF NOT EXISTS kategori_fiyatlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(50) NOT NULL UNIQUE,
    varsayilan_fiyat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Varsayılan kategori fiyatlarını ekle
INSERT INTO kategori_fiyatlari (kategori, varsayilan_fiyat) VALUES
    ('pasta', 450.00),
    ('cupcake', 45.00),
    ('cheesecake', 380.00),
    ('kurabiye', 180.00),
    ('ozel', 0.00)
ON DUPLICATE KEY UPDATE varsayilan_fiyat = VALUES(varsayilan_fiyat);

-- 5. Arşivleme alanı ekle (tamamlanmış siparişlerin takvimden gizlenmesi için)
ALTER TABLE siparisler
    ADD COLUMN IF NOT EXISTS arsivlendi TINYINT(1) DEFAULT 0 AFTER musteri_kaydedildi;

CREATE INDEX IF NOT EXISTS idx_siparisler_arsivlendi ON siparisler(arsivlendi);

-- 6. Mevcut siparişlerin fiyatlarını güncelle (opsiyonel)
-- Eğer mevcut siparişlere varsayılan fiyat atamak isterseniz:
-- UPDATE siparisler s
-- JOIN kategori_fiyatlari kf ON s.kategori = kf.kategori
-- SET s.birim_fiyat = kf.varsayilan_fiyat,
--     s.toplam_tutar = kf.varsayilan_fiyat * s.adet
-- WHERE s.birim_fiyat = 0 OR s.birim_fiyat IS NULL;
