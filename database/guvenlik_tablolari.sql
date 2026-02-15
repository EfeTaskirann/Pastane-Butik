-- Guvenlik Tablolari
-- Bu SQL'i phpMyAdmin'de calistirin

-- Basarisiz giris denemeleri tablosu (brute-force korumasi)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_adresi VARCHAR(45) NOT NULL,
    kullanici_adi VARCHAR(100) DEFAULT NULL,
    deneme_zamani DATETIME NOT NULL,
    INDEX idx_ip (ip_adresi),
    INDEX idx_kullanici (kullanici_adi),
    INDEX idx_zaman (deneme_zamani)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giris log tablosu (audit trail)
CREATE TABLE IF NOT EXISTS login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(100) NOT NULL,
    ip_adresi VARCHAR(45) NOT NULL,
    user_agent TEXT,
    basarili TINYINT(1) NOT NULL DEFAULT 0,
    tarih DATETIME NOT NULL,
    INDEX idx_kullanici (kullanici_adi),
    INDEX idx_tarih (tarih),
    INDEX idx_basarili (basarili)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eski kayitlari temizlemek icin event (opsiyonel)
-- MySQL Event Scheduler'i etkinlestirmek gerekir
-- SET GLOBAL event_scheduler = ON;

DELIMITER //

CREATE EVENT IF NOT EXISTS temizle_eski_login_kayitlari
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    -- 7 gunluk log tut
    DELETE FROM login_log WHERE tarih < DATE_SUB(NOW(), INTERVAL 7 DAY);
    -- 24 saatlik attempt tut
    DELETE FROM login_attempts WHERE deneme_zamani < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END//

DELIMITER ;

-- iletisim_mesajlari tablosuna ip_adresi sutunu ekle (varsa atla)
ALTER TABLE iletisim_mesajlari ADD COLUMN IF NOT EXISTS ip_adresi VARCHAR(45) DEFAULT NULL AFTER mesaj;
