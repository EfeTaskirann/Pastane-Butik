-- Migration: Add Security Fields for 2FA and Password Policy
-- Version: 002
-- Date: 2024
-- Run this after the initial database setup

USE pastane_db;

-- 1. Add Two-Factor Authentication fields to admin_kullanicilar
ALTER TABLE admin_kullanicilar
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) NULL AFTER sifre_hash,
    ADD COLUMN IF NOT EXISTS two_factor_backup_codes TEXT NULL AFTER two_factor_secret,
    ADD COLUMN IF NOT EXISTS two_factor_enabled_at DATETIME NULL AFTER two_factor_backup_codes;

-- 2. Add Password Policy fields
ALTER TABLE admin_kullanicilar
    ADD COLUMN IF NOT EXISTS password_history TEXT NULL AFTER two_factor_enabled_at,
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password_history,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 0 AFTER password_changed_at;

-- 3. Add account security fields
ALTER TABLE admin_kullanicilar
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER kullanici_adi,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS failed_login_count INT DEFAULT 0 AFTER last_login_ip,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_count,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER locked_until;

-- 4. Create API tokens table
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL COMMENT 'JSON array of abilities/scopes',
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),

    FOREIGN KEY (user_id) REFERENCES admin_kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create security events table (audit log)
CREATE TABLE IF NOT EXISTS security_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL COMMENT 'login_success, login_failed, password_change, 2fa_enabled, etc.',
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create rate_limits table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL COMMENT 'IP address or user:id',
    action VARCHAR(50) NOT NULL COMMENT 'login, api_request, contact_form, etc.',
    attempts INT DEFAULT 1,
    first_attempt_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,

    UNIQUE KEY unique_identifier_action (identifier, action),
    INDEX idx_blocked_until (blocked_until),
    INDEX idx_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create sessions table (for better session management)
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),

    FOREIGN KEY (user_id) REFERENCES admin_kullanicilar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Update existing admin password to require change
UPDATE admin_kullanicilar
SET must_change_password = 1,
    password_changed_at = NOW()
WHERE must_change_password IS NULL OR password_changed_at IS NULL;

-- 9. Opsiyonel: Cleanup event için phpMyAdmin'de manuel çalıştırın
