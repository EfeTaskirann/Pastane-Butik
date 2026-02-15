<?php
/**
 * Migration: Create Security Tables
 *
 * Güvenlik ile ilgili tabloları oluşturur.
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
        // API Token'ları tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                abilities JSON NULL,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_tokens_user (user_id),
                INDEX idx_api_tokens_token (token),
                CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id)
                    REFERENCES admin_kullanicilar(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Güvenlik olayları tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS security_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_security_events_type (event_type),
                INDEX idx_security_events_user (user_id),
                INDEX idx_security_events_ip (ip_address),
                INDEX idx_security_events_created (created_at),
                INDEX idx_security_events_type_created (event_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Rate limiting tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(100) NOT NULL,
                action VARCHAR(50) NOT NULL,
                hits INT DEFAULT 1,
                blocked_until TIMESTAMP NULL,
                first_hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_hit_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_rate_limit (identifier, action),
                INDEX idx_rate_limits_blocked (blocked_until),
                INDEX idx_rate_limits_last_hit (last_hit_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Kullanıcı oturumları tablosu
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_sessions (
                id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload TEXT NULL,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_sessions_user (user_id),
                INDEX idx_user_sessions_last_activity (last_activity),
                CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id)
                    REFERENCES admin_kullanicilar(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Password reset tokens
        $db->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                token VARCHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_resets_email (email),
                INDEX idx_password_resets_token (token)
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
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $db->exec("DROP TABLE IF EXISTS password_resets");
        $db->exec("DROP TABLE IF EXISTS user_sessions");
        $db->exec("DROP TABLE IF EXISTS rate_limits");
        $db->exec("DROP TABLE IF EXISTS security_events");
        $db->exec("DROP TABLE IF EXISTS api_tokens");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
};
