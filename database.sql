-- Pasta Butik Veritabanı Kurulum Scripti
-- MySQL 5.7+ veya MariaDB 10.2+

CREATE DATABASE IF NOT EXISTS pastane_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pastane_db;

-- Kategoriler Tablosu
CREATE TABLE IF NOT EXISTS kategoriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isim VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    sira INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ürünler Tablosu
CREATE TABLE IF NOT EXISTS urunler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT,
    isim VARCHAR(200) NOT NULL,
    aciklama TEXT,
    fiyat DECIMAL(10,2) DEFAULT 0.00,
    fiyat_4kisi DECIMAL(10,2) DEFAULT NULL,
    fiyat_6kisi DECIMAL(10,2) DEFAULT NULL,
    fiyat_8kisi DECIMAL(10,2) DEFAULT NULL,
    fiyat_10kisi DECIMAL(10,2) DEFAULT NULL,
    gorsel VARCHAR(255),
    aktif TINYINT(1) DEFAULT 1,
    sira INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut tabloya porsiyon fiyat sütunları ekle (eğer yoksa)
-- ALTER TABLE urunler ADD COLUMN fiyat_4kisi DECIMAL(10,2) DEFAULT NULL AFTER fiyat;
-- ALTER TABLE urunler ADD COLUMN fiyat_6kisi DECIMAL(10,2) DEFAULT NULL AFTER fiyat_4kisi;
-- ALTER TABLE urunler ADD COLUMN fiyat_8kisi DECIMAL(10,2) DEFAULT NULL AFTER fiyat_6kisi;
-- ALTER TABLE urunler ADD COLUMN fiyat_10kisi DECIMAL(10,2) DEFAULT NULL AFTER fiyat_8kisi;

-- Admin Kullanıcılar Tablosu
CREATE TABLE IF NOT EXISTS admin_kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İletişim Mesajları Tablosu
CREATE TABLE IF NOT EXISTS iletisim_mesajlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isim VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefon VARCHAR(20),
    mesaj TEXT NOT NULL,
    okundu TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek Kategoriler
INSERT IGNORE INTO kategoriler (isim, slug, sira) VALUES
('Pastalar', 'pastalar', 1),
('Cupcake', 'cupcake', 2),
('Kurabiyeler', 'kurabiyeler', 3),
('Cheesecake', 'cheesecake', 4);

-- Örnek Ürünler
INSERT IGNORE INTO urunler (kategori_id, isim, aciklama, fiyat, aktif, sira) VALUES
(1, 'Çilekli Yaş Pasta', 'Taze çilekler ve hafif krema ile hazırlanan özel yaş pastamız.', 450.00, 1, 1),
(1, 'Çikolatalı Doğum Günü Pastası', 'Yoğun çikolata aroması ile unutulmaz bir kutlama.', 500.00, 1, 2),
(2, 'Vanilya Cupcake', 'Klasik vanilya tadı, renkli süslemelerle.', 45.00, 1, 1),
(2, 'Red Velvet Cupcake', 'Kırmızı kadife dokusu, cream cheese kreması.', 50.00, 1, 2),
(3, 'Butik Kurabiye Seti', '12 adet el yapımı dekoratif kurabiye.', 180.00, 1, 1),
(4, 'Frambuazlı Cheesecake', 'New York usulü, taze frambuaz sosu ile.', 380.00, 1, 1);

-- Varsayılan Admin Kullanıcı
-- Kullanıcı: admin, Şifre: admin123 (Lütfen değiştirin!)
INSERT IGNORE INTO admin_kullanicilar (kullanici_adi, sifre_hash) VALUES
('admin', '$2y$10$8K1p/a5bGx8FUQ0v5uTh0OeJwNsQ7LJKaGdO4X5mRNHqLZQHWvCPG');

-- Siparişler Tablosu (Takvim Yoğunluk Sistemi)
CREATE TABLE IF NOT EXISTS siparisler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarih DATE NOT NULL,
    kategori ENUM('pasta', 'cupcake', 'cheesecake', 'kurabiye', 'ozel') NOT NULL,
    adet INT DEFAULT 1,
    puan INT NOT NULL,
    musteri_adi VARCHAR(100),
    telefon VARCHAR(20),
    adres TEXT,
    notlar TEXT,
    tamamlandi TINYINT(1) DEFAULT 0,
    musteri_kaydedildi TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut tabloya sütunlar ekle (eğer yoksa)
-- ALTER TABLE siparisler ADD COLUMN adres TEXT AFTER musteri_adi;
-- ALTER TABLE siparisler ADD COLUMN tamamlandi TINYINT(1) DEFAULT 0 AFTER notlar;

-- Sipariş Puan Ayarları
CREATE TABLE IF NOT EXISTS siparis_puan_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori VARCHAR(50) NOT NULL UNIQUE,
    puan INT NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Puan Ayarları
INSERT IGNORE INTO siparis_puan_ayarlari (kategori, puan) VALUES
('pasta', 15),
('cupcake', 8),
('cheesecake', 12),
('kurabiye', 6),
('ozel', 20);

-- Kayıtlı Müşteriler Tablosu (Sadakat Programı)
CREATE TABLE IF NOT EXISTS musteriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telefon VARCHAR(20) NOT NULL UNIQUE,
    isim VARCHAR(100),
    adres TEXT,
    siparis_sayisi INT DEFAULT 0,
    toplam_harcama DECIMAL(10,2) DEFAULT 0.00,
    son_siparis_tarihi DATE,
    hediye_hak_edildi INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Siparişler tablosuna telefon ve müşteri kaydedildi alanları ekle (eğer yoksa)
-- ALTER TABLE siparisler ADD COLUMN telefon VARCHAR(20) AFTER musteri_adi;
-- ALTER TABLE siparisler ADD COLUMN musteri_kaydedildi TINYINT(1) DEFAULT 0 AFTER tamamlandi;
