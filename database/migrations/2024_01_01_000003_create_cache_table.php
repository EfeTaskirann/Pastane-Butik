<?php
/**
 * Migration: Create Cache Table
 *
 * Veritabanı cache tablosu.
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
        $db->exec("
            CREATE TABLE IF NOT EXISTS cache (
                `key` VARCHAR(255) PRIMARY KEY,
                `value` LONGTEXT NOT NULL,
                `expiration` INT NOT NULL,
                INDEX idx_cache_expiration (expiration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Cache locks table
        $db->exec("
            CREATE TABLE IF NOT EXISTS cache_locks (
                `key` VARCHAR(255) PRIMARY KEY,
                `owner` VARCHAR(255) NOT NULL,
                `expiration` INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Reverse the migration
     *
     * @param PDO $db
     * @return void
     */
    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS cache_locks");
        $db->exec("DROP TABLE IF EXISTS cache");
    }
};
