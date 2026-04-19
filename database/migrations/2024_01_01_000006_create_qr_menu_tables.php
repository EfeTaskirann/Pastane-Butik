<?php
/**
 * Migration: Create QR Menu Tables
 *
 * QR Menu sistemi icin gerekli tablolari olusturur:
 * - masalar: Masa yonetimi ve QR kod eslestirme
 * - masa_oturumlari: Oturum takibi
 * - masa_siparisleri: QR menu uzerinden verilen siparisler
 * - masa_siparis_kalemleri: Siparis detaylari (urun bazinda)
 * - odeme_islemleri: Odeme gateway kayitlari
 *
 * Ayrica urunler tablosuna cafe_menusu, hazirlanma_suresi ve stok_durumu alanlari eklenir.
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
        // ========================================
        // 1. Masalar tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS masalar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                masa_no INT NOT NULL,
                qr_token VARCHAR(64) NOT NULL,
                durum ENUM('bos', 'aktif', 'kapali') DEFAULT 'bos',
                kapasite TINYINT DEFAULT 4,
                konum VARCHAR(50) DEFAULT NULL,
                aktif_oturum_id INT DEFAULT NULL,
                olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
                guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_masalar_masa_no (masa_no),
                UNIQUE KEY uk_masalar_qr_token (qr_token),
                INDEX idx_masalar_durum (durum),
                INDEX idx_masalar_qr_token (qr_token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ========================================
        // 2. Masa oturumlari tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS masa_oturumlari (
                id INT AUTO_INCREMENT PRIMARY KEY,
                masa_id INT NOT NULL,
                oturum_token VARCHAR(64) NOT NULL,
                baslangic_zamani DATETIME DEFAULT CURRENT_TIMESTAMP,
                bitis_zamani DATETIME DEFAULT NULL,
                durum ENUM('aktif', 'tamamlandi', 'iptal') DEFAULT 'aktif',
                musteri_sayisi TINYINT DEFAULT 1,
                toplam_tutar DECIMAL(10,2) DEFAULT 0.00,
                notlar TEXT DEFAULT NULL,
                UNIQUE KEY uk_oturumlar_token (oturum_token),
                INDEX idx_oturumlar_durum (durum),
                INDEX idx_oturumlar_masa_aktif (masa_id, durum),
                INDEX idx_oturum_tarih (baslangic_zamani),
                CONSTRAINT fk_oturumlar_masa FOREIGN KEY (masa_id)
                    REFERENCES masalar(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // masalar.aktif_oturum_id FK (oturumlar tablosu olusturulduktan sonra)
        $this->addForeignKeyIfNotExists(
            $db,
            'masalar',
            'fk_masalar_aktif_oturum',
            'aktif_oturum_id',
            'masa_oturumlari',
            'id',
            'ON DELETE SET NULL'
        );

        // ========================================
        // 3. Masa siparisleri tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS masa_siparisleri (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oturum_id INT NOT NULL,
                masa_id INT NOT NULL,
                durum ENUM('beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal') DEFAULT 'beklemede',
                toplam_tutar DECIMAL(10,2) NOT NULL,
                odeme_durumu ENUM('odenmedi', 'odendi', 'iade') DEFAULT 'odenmedi',
                odeme_yontemi VARCHAR(30) DEFAULT NULL,
                odeme_referans VARCHAR(100) DEFAULT NULL,
                siparis_notu TEXT DEFAULT NULL,
                siparis_zamani DATETIME DEFAULT CURRENT_TIMESTAMP,
                hazirlama_baslangic DATETIME DEFAULT NULL,
                hazir_zamani DATETIME DEFAULT NULL,
                teslim_zamani DATETIME DEFAULT NULL,
                INDEX idx_masa_siparisleri_durum (durum),
                INDEX idx_masa_siparisleri_odeme (odeme_durumu),
                INDEX idx_masa_siparisleri_zaman (siparis_zamani),
                INDEX idx_siparis_tarih_durum (siparis_zamani, durum),
                INDEX idx_siparis_oturum (oturum_id),
                CONSTRAINT fk_masa_siparisleri_oturum FOREIGN KEY (oturum_id)
                    REFERENCES masa_oturumlari(id) ON DELETE CASCADE,
                CONSTRAINT fk_masa_siparisleri_masa FOREIGN KEY (masa_id)
                    REFERENCES masalar(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ========================================
        // 4. Masa siparis kalemleri tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS masa_siparis_kalemleri (
                id INT AUTO_INCREMENT PRIMARY KEY,
                siparis_id INT NOT NULL,
                urun_id INT NOT NULL,
                urun_adi VARCHAR(200) NOT NULL,
                porsiyon VARCHAR(50) DEFAULT NULL,
                adet SMALLINT NOT NULL DEFAULT 1,
                birim_fiyat DECIMAL(10,2) NOT NULL,
                toplam_fiyat DECIMAL(10,2) NOT NULL,
                ozel_not TEXT DEFAULT NULL,
                INDEX idx_siparis_kalemleri_siparis (siparis_id),
                INDEX idx_kalem_urun (urun_id),
                CONSTRAINT fk_siparis_kalemleri_siparis FOREIGN KEY (siparis_id)
                    REFERENCES masa_siparisleri(id) ON DELETE CASCADE,
                CONSTRAINT fk_siparis_kalemleri_urun FOREIGN KEY (urun_id)
                    REFERENCES urunler(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ========================================
        // 5. Odeme islemleri tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS odeme_islemleri (
                id INT AUTO_INCREMENT PRIMARY KEY,
                siparis_id INT NOT NULL,
                gateway VARCHAR(30) NOT NULL,
                islem_id VARCHAR(100) NOT NULL,
                tutar DECIMAL(10,2) NOT NULL,
                para_birimi VARCHAR(3) DEFAULT 'TRY',
                durum ENUM('baslatildi', 'basarili', 'basarisiz', 'iade') DEFAULT 'baslatildi',
                gateway_yaniti JSON DEFAULT NULL,
                islem_zamani DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX uk_islem_id (islem_id),
                INDEX idx_odeme_islemleri_durum (durum),
                INDEX idx_odeme_siparis (siparis_id),
                INDEX idx_odeme_tarih (islem_zamani),
                CONSTRAINT fk_odeme_islemleri_siparis FOREIGN KEY (siparis_id)
                    REFERENCES masa_siparisleri(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ========================================
        // 6. Urunler tablosuna QR menu alanlari ekle
        // ========================================
        $this->addColumnIfNotExists($db, 'urunler', 'cafe_menusu', 'TINYINT(1) DEFAULT 1 AFTER aktif');
        $this->addColumnIfNotExists($db, 'urunler', 'hazirlanma_suresi', 'SMALLINT DEFAULT NULL AFTER cafe_menusu');
        $this->addColumnIfNotExists($db, 'urunler', 'stok_durumu', "ENUM('var', 'tukendi', 'sinirli') DEFAULT 'var' AFTER hazirlanma_suresi");
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

        // Tablolari ters sirada sil
        $db->exec("DROP TABLE IF EXISTS odeme_islemleri");
        $db->exec("DROP TABLE IF EXISTS masa_siparis_kalemleri");
        $db->exec("DROP TABLE IF EXISTS masa_siparisleri");
        $db->exec("DROP TABLE IF EXISTS masa_oturumlari");

        // masalar FK'sini kaldir, sonra tabloyu sil
        try {
            $db->exec("ALTER TABLE masalar DROP FOREIGN KEY fk_masalar_aktif_oturum");
        } catch (\PDOException $e) {
            // FK zaten yoksa devam et
        }
        $db->exec("DROP TABLE IF EXISTS masalar");

        $db->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Urunler tablosundan eklenen kolonlari kaldir
        try {
            $db->exec("ALTER TABLE urunler DROP COLUMN IF EXISTS stok_durumu");
            $db->exec("ALTER TABLE urunler DROP COLUMN IF EXISTS hazirlanma_suresi");
            $db->exec("ALTER TABLE urunler DROP COLUMN IF EXISTS cafe_menusu");
        } catch (\PDOException $e) {
            // Kolon zaten yoksa devam et
        }
    }

    /**
     * Kolon varsa ekleme (idempotent)
     */
    private function addColumnIfNotExists(PDO $db, string $table, string $column, string $definition): void
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$result['cnt'] === 0) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    /**
     * Foreign key varsa ekleme (idempotent)
     */
    private function addForeignKeyIfNotExists(
        PDO $db,
        string $table,
        string $fkName,
        string $column,
        string $refTable,
        string $refColumn,
        string $action = ''
    ): void {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute([$table, $fkName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$result['cnt'] === 0) {
            $db->exec("ALTER TABLE {$table} ADD CONSTRAINT {$fkName} FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) {$action}");
        }
    }
};
