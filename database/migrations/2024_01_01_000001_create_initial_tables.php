<?php
/**
 * Migration: Create Initial Tables
 *
 * Projenin temel tablolarını oluşturur.
 *
 * @package Pastane\Database\Migrations
 */

return new class {
    /**
     * Run the migration
     *
     * @param PDO $db
     * @return void
     */
    public function up(PDO $db): void
    {
        // Kategoriler tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS kategoriler (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ad VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                aciklama TEXT NULL,
                resim VARCHAR(255) NULL,
                aktif TINYINT(1) DEFAULT 1,
                sira INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_kategoriler_slug (slug),
                INDEX idx_kategoriler_aktif (aktif),
                INDEX idx_kategoriler_sira (sira)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Ürünler tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS urunler (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ad VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                aciklama TEXT NULL,
                fiyat DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                kategori_id INT NULL,
                resim VARCHAR(255) NULL,
                galeri JSON NULL,
                ozellikler JSON NULL,
                aktif TINYINT(1) DEFAULT 1,
                sira INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_urunler_slug (slug),
                INDEX idx_urunler_kategori (kategori_id),
                INDEX idx_urunler_aktif (aktif),
                INDEX idx_urunler_sira (sira),
                INDEX idx_urunler_fiyat (fiyat),
                CONSTRAINT fk_urunler_kategori FOREIGN KEY (kategori_id)
                    REFERENCES kategoriler(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Siparişler tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS siparisler (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ad_soyad VARCHAR(100) NOT NULL,
                telefon VARCHAR(20) NOT NULL,
                email VARCHAR(100) NULL,
                tarih DATE NOT NULL,
                saat TIME NULL,
                kategori VARCHAR(50) NULL,
                kisi_sayisi INT NULL,
                tasarim TEXT NULL,
                mesaj TEXT NULL,
                ozel_istekler TEXT NULL,
                durum ENUM('beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal')
                    DEFAULT 'beklemede',
                puan DECIMAL(3, 2) NULL,
                birim_fiyat DECIMAL(10, 2) DEFAULT 0.00,
                toplam_tutar DECIMAL(10, 2) DEFAULT 0.00,
                odeme_tipi ENUM('online', 'fiziksel') DEFAULT 'online',
                kanal ENUM('site', 'cafe', 'telefon') DEFAULT 'site',
                notlar TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_siparisler_tarih (tarih),
                INDEX idx_siparisler_durum (durum),
                INDEX idx_siparisler_kategori (kategori),
                INDEX idx_siparisler_telefon (telefon),
                INDEX idx_siparisler_tarih_kanal (tarih, kanal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Admin kullanıcıları tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_kullanicilar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
                sifre_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100) NULL,
                ad_soyad VARCHAR(100) NULL,
                rol ENUM('admin', 'editor', 'viewer') DEFAULT 'admin',
                aktif TINYINT(1) DEFAULT 1,
                two_factor_secret VARCHAR(32) NULL,
                password_history JSON NULL,
                password_changed_at TIMESTAMP NULL,
                last_login_at TIMESTAMP NULL,
                last_login_ip VARCHAR(45) NULL,
                failed_login_count INT DEFAULT 0,
                locked_until TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_admin_kullanici_adi (kullanici_adi),
                INDEX idx_admin_email (email),
                INDEX idx_admin_aktif (aktif)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Mesajlar tablosu (iletişim formu)
        $db->exec("
            CREATE TABLE IF NOT EXISTS mesajlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ad VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                telefon VARCHAR(20) NULL,
                konu VARCHAR(200) NULL,
                mesaj TEXT NOT NULL,
                okundu TINYINT(1) DEFAULT 0,
                cevaplandi TINYINT(1) DEFAULT 0,
                ip_adresi VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mesajlar_okundu (okundu),
                INDEX idx_mesajlar_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Site ayarları tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS ayarlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                anahtar VARCHAR(100) NOT NULL UNIQUE,
                deger TEXT NULL,
                tip ENUM('text', 'textarea', 'number', 'boolean', 'json') DEFAULT 'text',
                grup VARCHAR(50) DEFAULT 'genel',
                aciklama VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ayarlar_anahtar (anahtar),
                INDEX idx_ayarlar_grup (grup)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Varsayılan admin kullanıcısı (sadece tablo yeni oluşturulduysa ekle)
        // Mevcut tablolarda zaten admin var, bu yüzden IGNORE kullanıyoruz
        try {
            $defaultPassword = password_hash('admin123', PASSWORD_ARGON2ID);
            $stmt = $db->prepare("
                INSERT IGNORE INTO admin_kullanicilar (kullanici_adi, sifre_hash)
                VALUES ('admin', ?)
            ");
            $stmt->execute([$defaultPassword]);
        } catch (PDOException $e) {
            // Tablo yapısı farklıysa sessizce devam et
        }
    }

    /**
     * Reverse the migration
     *
     * @param PDO $db
     * @return void
     */
    public function down(PDO $db): void
    {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("DROP TABLE IF EXISTS mesajlar");
        $db->exec("DROP TABLE IF EXISTS ayarlar");
        $db->exec("DROP TABLE IF EXISTS siparisler");
        $db->exec("DROP TABLE IF EXISTS urunler");
        $db->exec("DROP TABLE IF EXISTS kategoriler");
        $db->exec("DROP TABLE IF EXISTS admin_kullanicilar");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
};
