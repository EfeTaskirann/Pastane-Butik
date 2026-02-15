<?php
/**
 * Security Audit Logger
 *
 * Records security-related events for compliance and monitoring.
 *
 * @package Pastane
 * @since 1.0.0
 */

class SecurityAudit
{
    /**
     * Event types
     */
    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
    public const PASSWORD_CHANGE = 'password_change';
    public const PASSWORD_RESET_REQUEST = 'password_reset_request';
    public const PASSWORD_RESET_COMPLETE = 'password_reset_complete';
    public const TWO_FACTOR_ENABLED = '2fa_enabled';
    public const TWO_FACTOR_DISABLED = '2fa_disabled';
    public const TWO_FACTOR_FAILED = '2fa_failed';
    public const ACCOUNT_LOCKED = 'account_locked';
    public const ACCOUNT_UNLOCKED = 'account_unlocked';
    public const API_TOKEN_CREATED = 'api_token_created';
    public const API_TOKEN_REVOKED = 'api_token_revoked';
    public const PERMISSION_DENIED = 'permission_denied';
    public const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    public const DATA_EXPORT = 'data_export';
    public const DATA_DELETE = 'data_delete';
    public const ADMIN_ACTION = 'admin_action';

    /**
     * Log a security event
     *
     * @param string $eventType Event type constant
     * @param int|null $userId Related user ID
     * @param array $details Additional details
     * @return bool
     */
    public static function log(string $eventType, ?int $userId = null, array $details = []): bool
    {
        try {
            // Add common context
            $details['timestamp'] = date('Y-m-d H:i:s');
            $details['request_uri'] = $_SERVER['REQUEST_URI'] ?? null;
            $details['request_method'] = $_SERVER['REQUEST_METHOD'] ?? null;

            db()->insert('security_events', [
                'event_type' => $eventType,
                'user_id' => $userId,
                'ip_address' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);

            // Also log to file for redundancy
            $logger = Logger::getInstance();
            $logger->security("{$eventType}", array_merge(['user_id' => $userId], $details));

            // Send alerts for critical events
            if (in_array($eventType, [
                self::ACCOUNT_LOCKED,
                self::SUSPICIOUS_ACTIVITY,
                self::RATE_LIMIT_EXCEEDED,
            ])) {
                self::sendAlert($eventType, $userId, $details);
            }

            return true;

        } catch (Exception $e) {
            // Log to file if database fails
            error_log("SecurityAudit error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Log successful login
     *
     * @param int $userId
     * @param string|null $username
     * @return void
     */
    public static function logLogin(int $userId, ?string $username = null): void
    {
        self::log(self::LOGIN_SUCCESS, $userId, [
            'username' => $username,
        ]);

        // Update user's last login info
        try {
            db()->update('admin_kullanicilar', [
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => self::getClientIp(),
                'failed_login_count' => 0,
            ], 'id = :id', ['id' => $userId]);
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Log failed login attempt
     *
     * @param string $username
     * @param string $reason
     * @return void
     */
    public static function logFailedLogin(string $username, string $reason = 'invalid_credentials'): void
    {
        self::log(self::LOGIN_FAILED, null, [
            'username' => $username,
            'reason' => $reason,
        ]);

        // Increment failed login count
        try {
            db()->query(
                "UPDATE admin_kullanicilar SET failed_login_count = failed_login_count + 1 WHERE kullanici_adi = ?",
                [$username]
            );
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Log logout
     *
     * @param int $userId
     * @return void
     */
    public static function logLogout(int $userId): void
    {
        self::log(self::LOGOUT, $userId);
    }

    /**
     * Log password change
     *
     * @param int $userId
     * @param bool $forced Whether it was a forced change
     * @return void
     */
    public static function logPasswordChange(int $userId, bool $forced = false): void
    {
        self::log(self::PASSWORD_CHANGE, $userId, [
            'forced' => $forced,
        ]);
    }

    /**
     * Log 2FA status change
     *
     * @param int $userId
     * @param bool $enabled
     * @return void
     */
    public static function log2FAChange(int $userId, bool $enabled): void
    {
        self::log(
            $enabled ? self::TWO_FACTOR_ENABLED : self::TWO_FACTOR_DISABLED,
            $userId
        );
    }

    /**
     * Log account lock
     *
     * @param int $userId
     * @param int $duration Lock duration in seconds
     * @return void
     */
    public static function logAccountLock(int $userId, int $duration): void
    {
        self::log(self::ACCOUNT_LOCKED, $userId, [
            'duration' => $duration,
            'locked_until' => date('Y-m-d H:i:s', time() + $duration),
        ]);
    }

    /**
     * Log suspicious activity
     *
     * @param string $description
     * @param int|null $userId
     * @param array $details
     * @return void
     */
    public static function logSuspicious(string $description, ?int $userId = null, array $details = []): void
    {
        self::log(self::SUSPICIOUS_ACTIVITY, $userId, array_merge($details, [
            'description' => $description,
        ]));
    }

    /**
     * Get recent events for a user
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getUserEvents(int $userId, int $limit = 50): array
    {
        return db()->fetchAll(
            "SELECT * FROM security_events WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get recent events by type
     *
     * @param string $eventType
     * @param int $limit
     * @return array
     */
    public static function getEventsByType(string $eventType, int $limit = 50): array
    {
        return db()->fetchAll(
            "SELECT * FROM security_events WHERE event_type = ? ORDER BY created_at DESC LIMIT ?",
            [$eventType, $limit]
        );
    }

    /**
     * Get recent events by IP
     *
     * @param string $ip
     * @param int $limit
     * @return array
     */
    public static function getEventsByIp(string $ip, int $limit = 50): array
    {
        return db()->fetchAll(
            "SELECT * FROM security_events WHERE ip_address = ? ORDER BY created_at DESC LIMIT ?",
            [$ip, $limit]
        );
    }

    /**
     * Get failed login count in last N minutes
     *
     * @param string $identifier IP or username
     * @param int $minutes
     * @return int
     */
    public static function getFailedLoginCount(string $identifier, int $minutes = 15): int
    {
        $result = db()->fetch(
            "SELECT COUNT(*) as count FROM security_events
             WHERE event_type = ? AND (ip_address = ? OR JSON_EXTRACT(details, '$.username') = ?)
             AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [self::LOGIN_FAILED, $identifier, $identifier, $minutes]
        );

        return (int)($result['count'] ?? 0);
    }

    /**
     * Check for suspicious patterns
     *
     * @param string $ip
     * @return array Suspicious patterns found
     */
    public static function checkSuspiciousPatterns(string $ip): array
    {
        $suspicious = [];

        // Check for multiple failed logins
        $failedCount = self::getFailedLoginCount($ip, 15);
        if ($failedCount >= 5) {
            $suspicious[] = "Multiple failed login attempts ({$failedCount} in 15 min)";
        }

        // Check for login from multiple locations
        $locations = db()->fetchAll(
            "SELECT DISTINCT ip_address FROM security_events
             WHERE event_type = 'login_success' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             AND user_id IN (SELECT user_id FROM security_events WHERE ip_address = ? AND event_type = 'login_success')",
            [$ip]
        );

        if (count($locations) > 3) {
            $suspicious[] = "Logins from multiple IP addresses";
        }

        // Check for rapid requests
        $recentRequests = db()->fetch(
            "SELECT COUNT(*) as count FROM security_events
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$ip]
        );

        if (($recentRequests['count'] ?? 0) > 30) {
            $suspicious[] = "High request rate";
        }

        return $suspicious;
    }

    /**
     * Send alert for critical events (placeholder for email/SMS)
     *
     * @param string $eventType
     * @param int|null $userId
     * @param array $details
     * @return void
     */
    private static function sendAlert(string $eventType, ?int $userId, array $details): void
    {
        $logger = Logger::getInstance();
        $logger->alert("Security Alert: {$eventType}", [
            'user_id' => $userId,
            'ip' => self::getClientIp(),
            'details' => $details,
        ]);

        // TODO: Implement email/SMS alerts
        // Mail::send(config('admin.email'), "Security Alert: {$eventType}", ...);
    }

    /**
     * Get client IP
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Generate security report
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function generateReport(string $startDate, string $endDate): array
    {
        $events = db()->fetchAll(
            "SELECT event_type, COUNT(*) as count
             FROM security_events
             WHERE created_at BETWEEN ? AND ?
             GROUP BY event_type
             ORDER BY count DESC",
            [$startDate, $endDate]
        );

        $topIps = db()->fetchAll(
            "SELECT ip_address, COUNT(*) as count
             FROM security_events
             WHERE event_type = 'login_failed' AND created_at BETWEEN ? AND ?
             GROUP BY ip_address
             ORDER BY count DESC
             LIMIT 10",
            [$startDate, $endDate]
        );

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'events_by_type' => $events,
            'top_failed_login_ips' => $topIps,
            'total_events' => array_sum(array_column($events, 'count')),
        ];
    }
}
