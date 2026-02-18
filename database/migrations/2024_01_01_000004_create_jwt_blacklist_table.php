<?php
/**
 * JWT Blacklist Table Migration
 *
 * Token invalidation (logout) için blacklist tablosu.
 * Expire olan tokenlar periyodik olarak temizlenir.
 *
 * @package Pastane\Database\Migrations
 * @since 1.1.0
 */

class CreateJwtBlacklistTable
{
    /**
     * Run the migration
     *
     * @param PDO $db
     * @return void
     */
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS jwt_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                jti VARCHAR(64) NOT NULL UNIQUE COMMENT 'JWT Token ID',
                expires_at DATETIME NOT NULL COMMENT 'Token expiration time',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_jwt_blacklist_jti (jti),
                INDEX idx_jwt_blacklist_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='JWT token blacklist for logout invalidation'
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
        $db->exec("DROP TABLE IF EXISTS jwt_blacklist");
    }
}
