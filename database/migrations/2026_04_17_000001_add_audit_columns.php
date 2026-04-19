<?php
/**
 * Migration: Audit Trail Kolonları (created_by / updated_by)
 *
 * Hedef tablolara `created_by` ve `updated_by` INT NULL kolonları ekler.
 * Foreign key → `admin_kullanicilar(id)` ON DELETE SET NULL.
 *
 * Idempotent çalışır: Kolon/indeks/FK zaten varsa atlar.
 *
 * Gerçek DB şeması üzerinde (2026-04-17):
 *   Hedef tablolar: urunler, kategoriler, siparisler, masalar,
 *                   masa_siparisleri, odeme_islemleri, admin_kullanicilar
 *   (Opsiyonel: ayarlar — ikinci migration tarafından audit kolonlarıyla oluşturulur)
 *
 * NOT: `admin_kullanicilar` tablosuna audit kolonu eklerken self-reference
 * yapıyoruz (admin kullanıcıyı hangi admin oluşturdu).
 *
 * @package Pastane\Database\Migrations
 * @since 2.0.0-sprint2
 */

return new class {
    /**
     * Audit eklenecek tablolar
     */
    private const TARGET_TABLES = [
        'urunler',
        'kategoriler',
        'siparisler',
        'masalar',
        'masa_siparisleri',
        'masa_siparis_kalemleri',
        'masa_oturumlari',
        'odeme_islemleri',
        'admin_kullanicilar',
        'site_temalari',
        'iletisim_mesajlari',
        'mesajlar',
        'musteriler',
        'kategori_fiyatlari',
        'siparis_puan_ayarlari',
    ];

    /**
     * Run the migration
     *
     * @param PDO $db
     * @return void
     */
    public function up(PDO $db): void
    {
        $dbName = $this->getCurrentDbName($db);

        foreach (self::TARGET_TABLES as $table) {
            if (!$this->tableExists($db, $dbName, $table)) {
                // Tablo yok → sessizce atla (farklı ortamlarda tüm tablolar bulunmayabilir)
                continue;
            }

            // created_by kolonu
            if (!$this->columnExists($db, $dbName, $table, 'created_by')) {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `created_by` INT NULL AFTER `id`");
                $db->exec("ALTER TABLE `{$table}` ADD INDEX `idx_{$table}_created_by` (`created_by`)");
            }

            // updated_by kolonu
            if (!$this->columnExists($db, $dbName, $table, 'updated_by')) {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `updated_by` INT NULL AFTER `created_by`");
                $db->exec("ALTER TABLE `{$table}` ADD INDEX `idx_{$table}_updated_by` (`updated_by`)");
            }

            // Foreign keys — admin_kullanicilar(id) referansı
            // NOT: admin_kullanicilar tablosunun kendisine FK eklerken self-reference
            if ($this->tableExists($db, $dbName, 'admin_kullanicilar')) {
                $fkCreated = "fk_{$table}_created_by";
                $fkUpdated = "fk_{$table}_updated_by";

                if (!$this->foreignKeyExists($db, $dbName, $table, $fkCreated)) {
                    $db->exec(
                        "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkCreated}` "
                        . "FOREIGN KEY (`created_by`) REFERENCES `admin_kullanicilar`(`id`) ON DELETE SET NULL"
                    );
                }

                if (!$this->foreignKeyExists($db, $dbName, $table, $fkUpdated)) {
                    $db->exec(
                        "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkUpdated}` "
                        . "FOREIGN KEY (`updated_by`) REFERENCES `admin_kullanicilar`(`id`) ON DELETE SET NULL"
                    );
                }
            }
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
        $dbName = $this->getCurrentDbName($db);

        foreach (array_reverse(self::TARGET_TABLES) as $table) {
            if (!$this->tableExists($db, $dbName, $table)) {
                continue;
            }

            // FK'leri önce sil
            $fkCreated = "fk_{$table}_created_by";
            $fkUpdated = "fk_{$table}_updated_by";

            if ($this->foreignKeyExists($db, $dbName, $table, $fkCreated)) {
                $db->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkCreated}`");
            }
            if ($this->foreignKeyExists($db, $dbName, $table, $fkUpdated)) {
                $db->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkUpdated}`");
            }

            // İndekslleri sil
            if ($this->indexExists($db, $dbName, $table, "idx_{$table}_created_by")) {
                $db->exec("ALTER TABLE `{$table}` DROP INDEX `idx_{$table}_created_by`");
            }
            if ($this->indexExists($db, $dbName, $table, "idx_{$table}_updated_by")) {
                $db->exec("ALTER TABLE `{$table}` DROP INDEX `idx_{$table}_updated_by`");
            }

            // Kolonları sil
            if ($this->columnExists($db, $dbName, $table, 'updated_by')) {
                $db->exec("ALTER TABLE `{$table}` DROP COLUMN `updated_by`");
            }
            if ($this->columnExists($db, $dbName, $table, 'created_by')) {
                $db->exec("ALTER TABLE `{$table}` DROP COLUMN `created_by`");
            }
        }
    }

    /**
     * Aktif veritabanı adını döndür
     */
    private function getCurrentDbName(PDO $db): string
    {
        $stmt = $db->query('SELECT DATABASE()');
        $name = (string) $stmt->fetchColumn();
        return $name !== '' ? $name : (defined('DB_NAME') ? DB_NAME : '');
    }

    /**
     * Tablo var mı?
     */
    private function tableExists(PDO $db, string $dbName, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([$dbName, $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Kolon var mı?
     */
    private function columnExists(PDO $db, string $dbName, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = ? AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$dbName, $table, $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Foreign key constraint var mı?
     */
    private function foreignKeyExists(PDO $db, string $dbName, string $table, string $constraint): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.table_constraints '
            . "WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'"
        );
        $stmt->execute([$dbName, $table, $constraint]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Index var mı?
     */
    private function indexExists(PDO $db, string $dbName, string $table, string $index): bool
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics '
            . 'WHERE table_schema = ? AND table_name = ? AND index_name = ?'
        );
        $stmt->execute([$dbName, $table, $index]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
};
