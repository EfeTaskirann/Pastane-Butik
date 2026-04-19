<?php
/**
 * Migration: Siparis Takip Token
 *
 * `siparisler` ve `masa_siparisleri` tablolarina `takip_token VARCHAR(64)` kolonu ekler.
 * Bu kolon public sipariş takip URL'lerinde `?id=<int>` yerine `?token=<hex>` kullanimini
 * destekler; IDOR (Insecure Direct Object Reference) saldirilarina karsi koruma saglar.
 *
 * Token formati: 32 karakter hexadecimal (bin2hex(random_bytes(16))).
 * UNIQUE index ile cakışma onlenir, sorguda prepared statement + hex regex validasyonu yapilir.
 *
 * Idempotent çalişir: Kolon / index zaten varsa atlar.
 *
 * Mevcut kayitlar icin backfill: SHA2(CONCAT(id, 'legacy-salt', RAND()), 256)'nin ilk 32 karakteri.
 * Bu "güvenli rastgele degil" ama mevcut (legacy) kayitlar icin kabul edilebilir — yeni kayitlar
 * `bin2hex(random_bytes(16))` ile üretilir. Eski linkler zaten tahmin edilebilir id ile kullanilmissa
 * üretim ortaminda ilk fırsatta rotate edilmeli.
 *
 * @package Pastane\Database\Migrations
 * @since 2.1.0
 */

return new class {
    /**
     * Token eklenecek tablolar
     *
     * @var array<string>
     */
    private const TARGET_TABLES = ['siparisler', 'masa_siparisleri'];

    /**
     * Kolon adi
     */
    private const COLUMN = 'takip_token';

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
                continue;
            }

            // Kolonu ekle (yoksa)
            if (!$this->columnExists($db, $dbName, $table, self::COLUMN)) {
                $db->exec(
                    "ALTER TABLE `{$table}` ADD COLUMN `" . self::COLUMN . "` "
                    . "VARCHAR(64) NULL DEFAULT NULL COMMENT 'Public sipariş takip URL tokeni (bin2hex(random_bytes(16)))'"
                );
            }

            // Unique index ekle (yoksa)
            $indexName = 'uniq_' . $table . '_takip_token';
            if (!$this->indexExists($db, $dbName, $table, $indexName)) {
                $db->exec(
                    "ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`" . self::COLUMN . "`)"
                );
            }

            // Backfill: NULL olan eski kayıtlar için deterministik olmayan token üret.
            // SHA2 32 karakter kestiği için bin2hex(random_bytes(16)) formatına uyumlu.
            // Legacy kayıtlar için kabul edilebilir (istemci tarafında yeni format zorunlu).
            $db->exec(
                "UPDATE `{$table}` "
                . "SET `" . self::COLUMN . "` = LEFT(SHA2(CONCAT(id, '-legacy-', RAND(), '-', UNIX_TIMESTAMP()), 256), 32) "
                . "WHERE `" . self::COLUMN . "` IS NULL"
            );
        }

        // ---- Yan etki: rate_limits tablosunda `last_attempt_at` eksik olabilir ----
        // Pre-existing schema drift: RateLimiter::hit() INSERT'inde `last_attempt_at`
        // kullaniliyor ama eski kurulumlarda bu kolon yok. Rate limiter header'lari ve
        // takip endpoint'inin enforce çagrisi bu kolona bagimlı — idempotent olarak ekliyoruz.
        if ($this->tableExists($db, $dbName, 'rate_limits')
            && !$this->columnExists($db, $dbName, 'rate_limits', 'last_attempt_at')) {
            $db->exec("ALTER TABLE `rate_limits` ADD COLUMN `last_attempt_at` DATETIME NULL AFTER `first_attempt_at`");
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

            $indexName = 'uniq_' . $table . '_takip_token';
            if ($this->indexExists($db, $dbName, $table, $indexName)) {
                $db->exec("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }

            if ($this->columnExists($db, $dbName, $table, self::COLUMN)) {
                $db->exec("ALTER TABLE `{$table}` DROP COLUMN `" . self::COLUMN . "`");
            }
        }
    }

    /**
     * Aktif veritabani adını dondur
     */
    private function getCurrentDbName(PDO $db): string
    {
        $stmt = $db->query('SELECT DATABASE()');
        $name = (string) $stmt->fetchColumn();
        return $name !== '' ? $name : (defined('DB_NAME') ? DB_NAME : '');
    }

    /**
     * Tablo var mi?
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
     * Kolon var mi?
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
     * Index var mi?
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
