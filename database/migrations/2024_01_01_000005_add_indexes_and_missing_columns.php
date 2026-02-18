<?php
/**
 * Migration: Add Performance Indexes & Missing Columns/Tables
 *
 * - Siparişler tablosuna eksik kolonlar eklenir (arsivlendi, musteri_kaydedildi)
 * - Müşteriler tablosu oluşturulur
 * - Performans index'leri eklenir
 * - Composite index'ler eklenir (sık kullanılan WHERE+ORDER BY kombinasyonları)
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
        // 1. Siparişler - Eksik kolonlar
        // ========================================

        // arsivlendi kolonu (SiparisRepository'de kullanılıyor)
        $this->addColumnIfNotExists($db, 'siparisler', 'arsivlendi', 'TINYINT(1) DEFAULT 0 AFTER notlar');

        // musteri_kaydedildi kolonu (SiparisRepository'de kullanılıyor)
        $this->addColumnIfNotExists($db, 'siparisler', 'musteri_kaydedildi', 'TINYINT(1) DEFAULT 0 AFTER arsivlendi');

        // ========================================
        // 2. Müşteriler tablosu
        // ========================================
        $db->exec("
            CREATE TABLE IF NOT EXISTS musteriler (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telefon VARCHAR(20) NOT NULL,
                isim VARCHAR(100) NULL,
                adres TEXT NULL,
                siparis_sayisi INT DEFAULT 0,
                toplam_harcama DECIMAL(10, 2) DEFAULT 0.00,
                son_siparis_tarihi DATE NULL,
                hediye_hak_edildi INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_musteriler_telefon (telefon),
                INDEX idx_musteriler_isim (isim),
                INDEX idx_musteriler_siparis_sayisi (siparis_sayisi),
                INDEX idx_musteriler_toplam_harcama (toplam_harcama),
                INDEX idx_musteriler_son_siparis (son_siparis_tarihi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ========================================
        // 3. Performans Index'leri - Siparişler
        // ========================================

        // Takvim sorguları: WHERE tarih BETWEEN ? AND ? AND durum NOT IN (...)
        $this->addIndexIfNotExists($db, 'siparisler', 'idx_siparisler_tarih_durum', '(tarih, durum)');

        // Arşiv filtresi: WHERE arsivlendi = 0 AND durum != 'iptal'
        $this->addIndexIfNotExists($db, 'siparisler', 'idx_siparisler_arsiv_durum', '(arsivlendi, durum)');

        // Telefon + tarih (müşteri sipariş geçmişi): WHERE telefon = ? ORDER BY tarih DESC
        $this->addIndexIfNotExists($db, 'siparisler', 'idx_siparisler_telefon_tarih', '(telefon, tarih)');

        // toplam_tutar sıralama
        $this->addIndexIfNotExists($db, 'siparisler', 'idx_siparisler_toplam_tutar', '(toplam_tutar)');

        // ========================================
        // 4. Performans Index'leri - Ürünler
        // ========================================

        // Aktif + sıra (en sık kullanılan sorgu): WHERE aktif = 1 ORDER BY sira ASC
        $this->addIndexIfNotExists($db, 'urunler', 'idx_urunler_aktif_sira', '(aktif, sira)');

        // Aktif + kategori (kategori sayfası): WHERE aktif = 1 AND kategori_id = ?
        $this->addIndexIfNotExists($db, 'urunler', 'idx_urunler_aktif_kategori', '(aktif, kategori_id)');

        // ========================================
        // 5. Performans Index'leri - Mesajlar
        // ========================================

        // Okunmamış + tarih (admin panel): WHERE okundu = 0 ORDER BY created_at DESC
        $this->addIndexIfNotExists($db, 'mesajlar', 'idx_mesajlar_okundu_created', '(okundu, created_at)');

        // ========================================
        // 6. JWT Blacklist - TTL Index
        // ========================================
        $this->addIndexIfNotExists($db, 'jwt_blacklist', 'idx_jwt_blacklist_expires', '(expires_at)');
    }

    /**
     * Reverse the migration
     *
     * @param PDO $db
     * @return void
     */
    public function down(PDO $db): void
    {
        // Index'leri kaldır
        $indexes = [
            'siparisler' => ['idx_siparisler_tarih_durum', 'idx_siparisler_arsiv_durum', 'idx_siparisler_telefon_tarih', 'idx_siparisler_toplam_tutar'],
            'urunler' => ['idx_urunler_aktif_sira', 'idx_urunler_aktif_kategori'],
            'mesajlar' => ['idx_mesajlar_okundu_created'],
            'jwt_blacklist' => ['idx_jwt_blacklist_expires'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                try {
                    $db->exec("DROP INDEX {$index} ON {$table}");
                } catch (\PDOException $e) {
                    // Index zaten yoksa devam et
                }
            }
        }

        // Kolonları kaldır
        try {
            $db->exec("ALTER TABLE siparisler DROP COLUMN IF EXISTS musteri_kaydedildi");
            $db->exec("ALTER TABLE siparisler DROP COLUMN IF EXISTS arsivlendi");
        } catch (\PDOException $e) {
            // Kolon zaten yoksa devam et
        }

        // Müşteriler tablosunu kaldır
        $db->exec("DROP TABLE IF EXISTS musteriler");
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
     * Index varsa ekleme (idempotent)
     */
    private function addIndexIfNotExists(PDO $db, string $table, string $indexName, string $columns): void
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $indexName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$result['cnt'] === 0) {
            $db->exec("CREATE INDEX {$indexName} ON {$table} {$columns}");
        }
    }
};
