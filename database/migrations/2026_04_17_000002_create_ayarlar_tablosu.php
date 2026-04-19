<?php
/**
 * Migration: Ayarlar Tablosu (Settings — key-value store)
 *
 * Site genelinde kullanılan ayarları tip-aware şekilde saklar.
 *
 * Şema:
 *   - anahtar:  VARCHAR(100) UNIQUE — benzersiz anahtar (ör. "site_baslik")
 *   - deger:    TEXT          — raw string değer
 *   - tip:      ENUM          — değerin PHP tipi: string | int | bool | json
 *   - grup:     VARCHAR(50)   — gruplama (ör. "genel", "iletisim", "odeme")
 *   - aciklama: VARCHAR(255)  — admin UI için yardım metni
 *   - audit:    created_by / updated_by (nullable FK → admin_kullanicilar.id)
 *
 * Seed kayıtlar:
 *   site_baslik, site_aciklama, iletisim_email, iletisim_telefon, adres,
 *   siparis_minimum_tutar, kapida_odeme_aktif
 *
 * @package Pastane\Database\Migrations
 * @since 2.0.0-sprint2
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
        // Mevcut `ayarlar` tablosu varsa (eski şema) şemayı güncelle, yoksa yeni oluştur
        $dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
        $exists = (int) $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        )->execute([$dbName, 'ayarlar']);

        // Basit kontrol: tabloyu oluştur (idempotent)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `ayarlar` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `created_by` INT NULL,
                `updated_by` INT NULL,
                `anahtar` VARCHAR(100) NOT NULL UNIQUE,
                `deger` TEXT NULL,
                `tip` ENUM('string', 'int', 'bool', 'json') NOT NULL DEFAULT 'string',
                `grup` VARCHAR(50) NOT NULL DEFAULT 'genel',
                `aciklama` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_ayarlar_anahtar` (`anahtar`),
                INDEX `idx_ayarlar_grup` (`grup`),
                INDEX `idx_ayarlar_created_by` (`created_by`),
                INDEX `idx_ayarlar_updated_by` (`updated_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Eski şema varsa tip ENUM'unu yeni değerlere genişlet
        // (önceki migration 'text','textarea','number','boolean','json' kullanıyor)
        $typeEnumCheck = $db->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.columns "
            . "WHERE table_schema = ? AND table_name = 'ayarlar' AND column_name = 'tip'"
        );
        $typeEnumCheck->execute([$dbName]);
        $currentType = (string) $typeEnumCheck->fetchColumn();

        if ($currentType !== '' && !str_contains($currentType, "'string'")) {
            // Eski şema → yeni ENUM'a migrate (data'yı da dönüştür)
            $db->exec("UPDATE `ayarlar` SET `tip` = 'string' WHERE `tip` IN ('text', 'textarea')");
            $db->exec("UPDATE `ayarlar` SET `tip` = 'int' WHERE `tip` = 'number'");
            $db->exec("UPDATE `ayarlar` SET `tip` = 'bool' WHERE `tip` = 'boolean'");
            $db->exec(
                "ALTER TABLE `ayarlar` MODIFY COLUMN `tip` "
                . "ENUM('string', 'int', 'bool', 'json') NOT NULL DEFAULT 'string'"
            );
        }

        // Audit kolonları eski şemada yok olabilir — ekle
        foreach (['created_by', 'updated_by'] as $col) {
            $colCheck = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema = ? AND table_name = ? AND column_name = ?'
            );
            $colCheck->execute([$dbName, 'ayarlar', $col]);
            if ((int) $colCheck->fetchColumn() === 0) {
                $db->exec("ALTER TABLE `ayarlar` ADD COLUMN `{$col}` INT NULL AFTER `id`");
                $db->exec("ALTER TABLE `ayarlar` ADD INDEX `idx_ayarlar_{$col}` (`{$col}`)");
            }
        }

        // Foreign keys — admin_kullanicilar(id)
        $fkCheck = function (string $constraint) use ($db, $dbName): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.table_constraints '
                . "WHERE table_schema = ? AND table_name = 'ayarlar' AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'"
            );
            $stmt->execute([$dbName, $constraint]);
            return ((int) $stmt->fetchColumn()) > 0;
        };

        // admin_kullanicilar tablosu varsa FK ekle
        $adminExists = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );
        $adminExists->execute([$dbName, 'admin_kullanicilar']);
        if ((int) $adminExists->fetchColumn() > 0) {
            if (!$fkCheck('fk_ayarlar_created_by')) {
                $db->exec(
                    'ALTER TABLE `ayarlar` ADD CONSTRAINT `fk_ayarlar_created_by` '
                    . 'FOREIGN KEY (`created_by`) REFERENCES `admin_kullanicilar`(`id`) ON DELETE SET NULL'
                );
            }
            if (!$fkCheck('fk_ayarlar_updated_by')) {
                $db->exec(
                    'ALTER TABLE `ayarlar` ADD CONSTRAINT `fk_ayarlar_updated_by` '
                    . 'FOREIGN KEY (`updated_by`) REFERENCES `admin_kullanicilar`(`id`) ON DELETE SET NULL'
                );
            }
        }

        // Seed verileri (INSERT IGNORE — idempotent)
        $seeds = [
            ['site_baslik',             'Tatlı Düşler Butik Pastane', 'string', 'genel',    'Site başlığı (browser title)'],
            ['site_aciklama',           'El yapımı özel tasarım pastalar ve butik kurabiyeler.', 'string', 'genel', 'Meta description'],
            ['iletisim_email',          'info@tatlidusler.com', 'string', 'iletisim', 'İletişim email adresi'],
            ['iletisim_telefon',        '+90 555 123 45 67',    'string', 'iletisim', 'İletişim telefonu'],
            ['adres',                   'İstanbul, Kadıköy',    'string', 'iletisim', 'Fiziksel adres'],
            ['siparis_minimum_tutar',   '200',                  'int',    'siparis',  'Minimum sipariş tutarı (TL)'],
            ['kapida_odeme_aktif',      '1',                    'bool',   'odeme',    'Kapıda ödeme aktif mi?'],
        ];

        $stmt = $db->prepare(
            'INSERT IGNORE INTO `ayarlar` (`anahtar`, `deger`, `tip`, `grup`, `aciklama`) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($seeds as $row) {
            $stmt->execute($row);
        }
    }

    /**
     * Reverse the migration
     *
     * Sadece bu sprint'te eklenen yapıyı geri al. Tabloyu tamamen kaldırmak
     * yerine sadece audit kolonlarını ve yeni seed'leri geri al — başka
     * migration'ın oluşturduğu veriye dokunma.
     *
     * @param PDO $db
     * @return void
     */
    public function down(PDO $db): void
    {
        $dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();

        // Sadece Sprint 2'de eklenen seed'leri sil
        $keys = [
            'site_baslik', 'site_aciklama', 'iletisim_email', 'iletisim_telefon',
            'adres', 'siparis_minimum_tutar', 'kapida_odeme_aktif',
        ];
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $db->prepare("DELETE FROM `ayarlar` WHERE `anahtar` IN ({$placeholders})")->execute($keys);

        // FK'leri sil
        foreach (['fk_ayarlar_created_by', 'fk_ayarlar_updated_by'] as $fk) {
            $check = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.table_constraints '
                . "WHERE table_schema = ? AND table_name = 'ayarlar' AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'"
            );
            $check->execute([$dbName, $fk]);
            if ((int) $check->fetchColumn() > 0) {
                $db->exec("ALTER TABLE `ayarlar` DROP FOREIGN KEY `{$fk}`");
            }
        }

        // Audit kolonlarını sil
        foreach (['updated_by', 'created_by'] as $col) {
            $check = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns '
                . 'WHERE table_schema = ? AND table_name = ? AND column_name = ?'
            );
            $check->execute([$dbName, 'ayarlar', $col]);
            if ((int) $check->fetchColumn() > 0) {
                $db->exec("ALTER TABLE `ayarlar` DROP COLUMN `{$col}`");
            }
        }
    }
};
