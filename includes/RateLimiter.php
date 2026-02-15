<?php
/**
 * Rate Limiter
 *
 * Request rate limiting for brute-force and abuse protection.
 * Database-backed for distributed environments.
 *
 * @package Pastane
 * @since 1.0.0
 */

class RateLimiter
{
    /**
     * @var array Default limits
     */
    private static array $limits = [
        'default' => ['requests' => 60, 'window' => 60],
        'login' => ['requests' => 5, 'window' => 60],
        'api' => ['requests' => 100, 'window' => 60],
        'contact' => ['requests' => 3, 'window' => 60],
    ];

    /**
     * Initialize from config
     *
     * @return void
     */
    public static function init(): void
    {
        $configLimits = config('security.rate_limit.endpoints', []);
        self::$limits = array_merge(self::$limits, $configLimits);
    }

    /**
     * Check if request is allowed
     *
     * @param string $action Action type (login, api, contact, etc.)
     * @param string|null $identifier Custom identifier (default: IP)
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     */
    public static function check(string $action = 'default', ?string $identifier = null): array
    {
        $identifier = $identifier ?? self::getIdentifier();
        $limits = self::$limits[$action] ?? self::$limits['default'];

        try {
            $record = db()->fetch(
                "SELECT * FROM rate_limits WHERE identifier = ? AND action = ?",
                [$identifier, $action]
            );

            // Check if blocked
            if ($record && !empty($record['blocked_until'])) {
                $blockedUntil = strtotime($record['blocked_until']);
                if ($blockedUntil > time()) {
                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'retry_after' => $blockedUntil - time(),
                        'message' => 'Çok fazla istek. Lütfen daha sonra tekrar deneyin.',
                    ];
                }
            }

            // Check window
            if ($record) {
                $firstAttempt = strtotime($record['first_attempt_at']);
                $windowEnd = $firstAttempt + $limits['window'];

                if (time() > $windowEnd) {
                    // Window expired, reset
                    self::reset($action, $identifier);
                    $record = null;
                }
            }

            $attempts = $record ? (int)$record['attempts'] : 0;
            $remaining = max(0, $limits['requests'] - $attempts);

            return [
                'allowed' => $remaining > 0,
                'remaining' => $remaining,
                'retry_after' => $remaining <= 0 ? $limits['window'] : null,
                'message' => $remaining <= 0 ? 'Rate limit aşıldı.' : null,
            ];

        } catch (Exception $e) {
            // GÜVENLİK: Fail-closed - database hatası durumunda isteği reddet
            // Bu, saldırganların DB hatası oluşturarak rate limiting'i bypass etmesini önler
            if (function_exists('error_log')) {
                error_log("RateLimiter DB hatası [{$action}]: " . $e->getMessage());
            }
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => 60,
                'message' => 'Sistem geçici olarak kullanılamıyor. Lütfen daha sonra tekrar deneyin.'
            ];
        }
    }

    /**
     * Record an attempt
     *
     * @param string $action
     * @param string|null $identifier
     * @return void
     */
    public static function hit(string $action = 'default', ?string $identifier = null): void
    {
        $identifier = $identifier ?? self::getIdentifier();
        $now = date('Y-m-d H:i:s');

        try {
            $existing = db()->fetch(
                "SELECT id, attempts FROM rate_limits WHERE identifier = ? AND action = ?",
                [$identifier, $action]
            );

            if ($existing) {
                db()->query(
                    "UPDATE rate_limits SET attempts = attempts + 1, last_attempt_at = ? WHERE id = ?",
                    [$now, $existing['id']]
                );
            } else {
                db()->insert('rate_limits', [
                    'identifier' => $identifier,
                    'action' => $action,
                    'attempts' => 1,
                    'first_attempt_at' => $now,
                    'last_attempt_at' => $now,
                ]);
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Block an identifier
     *
     * @param string $action
     * @param int $seconds Block duration
     * @param string|null $identifier
     * @return void
     */
    public static function block(string $action, int $seconds, ?string $identifier = null): void
    {
        $identifier = $identifier ?? self::getIdentifier();
        $blockedUntil = date('Y-m-d H:i:s', time() + $seconds);

        try {
            db()->query(
                "UPDATE rate_limits SET blocked_until = ? WHERE identifier = ? AND action = ?",
                [$blockedUntil, $identifier, $action]
            );
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $action
     * @param string|null $identifier
     * @return void
     */
    public static function reset(string $action, ?string $identifier = null): void
    {
        $identifier = $identifier ?? self::getIdentifier();

        try {
            db()->delete('rate_limits', 'identifier = :id AND action = :action', [
                'id' => $identifier,
                'action' => $action,
            ]);
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Clear all limits for an identifier
     *
     * @param string|null $identifier
     * @return void
     */
    public static function clearAll(?string $identifier = null): void
    {
        $identifier = $identifier ?? self::getIdentifier();

        try {
            db()->delete('rate_limits', 'identifier = :id', ['id' => $identifier]);
        } catch (Exception $e) {
            // Silently fail
        }
    }

    /**
     * Get rate limit headers
     *
     * @param string $action
     * @return array
     */
    public static function getHeaders(string $action = 'default'): array
    {
        $status = self::check($action);
        $limits = self::$limits[$action] ?? self::$limits['default'];

        return [
            'X-RateLimit-Limit' => $limits['requests'],
            'X-RateLimit-Remaining' => $status['remaining'],
            'X-RateLimit-Reset' => time() + ($limits['window'] ?? 60),
        ];
    }

    /**
     * Send rate limit headers
     *
     * @param string $action
     * @return void
     */
    public static function sendHeaders(string $action = 'default'): void
    {
        foreach (self::getHeaders($action) as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Middleware: Enforce rate limit
     *
     * @param string $action
     * @param string|null $identifier
     * @return bool True if allowed, exits with 429 if not
     */
    public static function enforce(string $action = 'default', ?string $identifier = null): bool
    {
        $status = self::check($action, $identifier);

        if (!$status['allowed']) {
            self::sendHeaders($action);
            self::respondTooManyRequests($status['retry_after'] ?? 60, $status['message'] ?? 'Rate limit exceeded');
            return false;
        }

        self::hit($action, $identifier);
        self::sendHeaders($action);

        return true;
    }

    /**
     * Send 429 Too Many Requests response
     *
     * @param int $retryAfter
     * @param string $message
     * @return void
     */
    private static function respondTooManyRequests(int $retryAfter, string $message): void
    {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => $message,
            'retry_after' => $retryAfter,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Get client identifier
     *
     * @return string
     */
    private static function getIdentifier(): string
    {
        return self::getClientIp();
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

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
     * Cleanup old entries
     *
     * @return int Number of deleted rows
     */
    public static function cleanup(): int
    {
        try {
            $stmt = db()->query(
                "DELETE FROM rate_limits WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}
