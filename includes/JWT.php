<?php
/**
 * JWT (JSON Web Token) Handler
 *
 * API authentication için JWT token oluşturma ve doğrulama.
 * HS256 algoritması kullanır.
 *
 * @package Pastane
 * @since 1.0.0
 */

class JWT
{
    /**
     * @var string Secret key for signing
     */
    private static string $secretKey = '';

    /**
     * @var int Token expiration time in seconds (default: 1 hour)
     */
    private static int $expiration = 3600;

    /**
     * @var string Token issuer
     */
    private static string $issuer = 'pastane';

    /**
     * @var array In-memory blacklisted token IDs (jti) for current request
     */
    private static array $blacklist = [];

    /**
     * Initialize JWT with secret key
     *
     * GÜVENLİK: JWT_SECRET (veya fallback APP_KEY) .env dosyasında tanımlanmalı!
     * JWT_SECRET ayrı bir key olmalı, APP_KEY ile aynı olmamalıdır.
     *
     * @param string|null $secretKey
     * @return void
     * @throws RuntimeException Secret key tanımlı değilse
     */
    public static function init(?string $secretKey = null): void
    {
        // JWT_SECRET varsa onu kullan, yoksa APP_KEY'e fallback yap
        $key = $secretKey ?? env('JWT_SECRET', '') ?: env('APP_KEY', '');

        // Güvenlik kontrolü: Varsayılan veya boş key kabul edilmez
        if (empty($key) || $key === 'default-secret-key-change-in-production' ||
            str_starts_with($key, 'base64:GENERATE') ||
            $key === 'GENERATE_A_SECURE_JWT_SECRET_HERE') {
            throw new RuntimeException(
                'GÜVENLİK HATASI: JWT_SECRET .env dosyasında tanımlanmalı! ' .
                'Güvenli bir key oluşturmak için: php -r "echo bin2hex(random_bytes(32));"'
            );
        }

        // base64: prefix varsa decode et
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        self::$secretKey = $key;
        self::$issuer = config('app.name', 'Pastane');
    }

    /**
     * Create a new JWT token
     *
     * @param array $payload Custom payload data
     * @param int|null $expiration Token lifetime in seconds
     * @return string JWT token
     */
    public static function create(array $payload, ?int $expiration = null): string
    {
        if (empty(self::$secretKey)) {
            self::init();
        }

        $exp = $expiration ?? self::$expiration;

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $payload = array_merge($payload, [
            'iss' => self::$issuer,
            'iat' => time(),
            'exp' => time() + $exp,
            'jti' => bin2hex(random_bytes(16)) // Unique token ID
        ]);

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            self::$secretKey,
            true
        );
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Verify and decode a JWT token
     *
     * @param string $token JWT token
     * @return array|false Decoded payload or false if invalid
     */
    public static function verify(string $token): array|false
    {
        if (empty(self::$secretKey)) {
            self::init();
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            self::$secretKey,
            true
        );

        $providedSignature = self::base64UrlDecode($signatureEncoded);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return false;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return false;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        // Check token blacklist (jti)
        if (isset($payload['jti']) && self::isBlacklisted($payload['jti'])) {
            return false;
        }

        return $payload;
    }

    /**
     * Decode token without verification (for debugging)
     *
     * @param string $token
     * @return array|null
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        return [
            'header' => json_decode(self::base64UrlDecode($parts[0]), true),
            'payload' => json_decode(self::base64UrlDecode($parts[1]), true),
        ];
    }

    /**
     * Create a refresh token
     *
     * @param int $userId
     * @return string
     */
    public static function createRefreshToken(int $userId): string
    {
        return self::create(
            ['user_id' => $userId, 'type' => 'refresh'],
            86400 * 7 // 7 days
        );
    }

    /**
     * Get remaining time until expiration
     *
     * @param string $token
     * @return int|false Seconds until expiration or false if invalid
     */
    public static function getExpiresIn(string $token): int|false
    {
        $payload = self::verify($token);

        if (!$payload || !isset($payload['exp'])) {
            return false;
        }

        return max(0, $payload['exp'] - time());
    }

    /**
     * Extract token from Authorization header
     *
     * @return string|null
     */
    public static function getTokenFromHeader(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Also check for X-API-KEY header
        return $_SERVER['HTTP_X_API_KEY'] ?? null;
    }

    /**
     * Middleware: Require valid JWT
     *
     * @return array|null User data or null (sends 401 response)
     */
    public static function requireAuth(): ?array
    {
        $token = self::getTokenFromHeader();

        if (!$token) {
            self::sendUnauthorized('No token provided');
            return null;
        }

        $payload = self::verify($token);

        if (!$payload) {
            self::sendUnauthorized('Invalid or expired token');
            return null;
        }

        return $payload;
    }

    /**
     * Send 401 Unauthorized response
     *
     * @param string $message
     * @return void
     */
    private static function sendUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Base64 URL safe encode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL safe decode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Set custom expiration time
     *
     * @param int $seconds
     * @return void
     */
    public static function setExpiration(int $seconds): void
    {
        self::$expiration = $seconds;
    }

    // ========================================
    // TOKEN BLACKLIST (Logout Invalidation)
    // ========================================

    /**
     * Blacklist a token by its JTI (token ID)
     * Stores in database for persistence across requests
     *
     * @param string $jti Token unique identifier
     * @param int $expiresAt Token expiration timestamp
     * @return bool
     */
    public static function blacklist(string $jti, int $expiresAt): bool
    {
        // In-memory cache
        self::$blacklist[$jti] = true;

        // Persist to database
        try {
            db()->query(
                "INSERT IGNORE INTO jwt_blacklist (jti, expires_at, created_at) VALUES (?, ?, NOW())",
                [$jti, date('Y-m-d H:i:s', $expiresAt)]
            );
            return true;
        } catch (\Exception $e) {
            // If table doesn't exist yet, just use in-memory
            return false;
        }
    }

    /**
     * Check if token JTI is blacklisted
     *
     * @param string $jti
     * @return bool
     */
    public static function isBlacklisted(string $jti): bool
    {
        // Check in-memory first
        if (isset(self::$blacklist[$jti])) {
            return true;
        }

        // Check database
        try {
            $result = db()->fetch(
                "SELECT 1 FROM jwt_blacklist WHERE jti = ? AND expires_at > NOW()",
                [$jti]
            );
            if ($result) {
                self::$blacklist[$jti] = true;
                return true;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, not blacklisted
        }

        return false;
    }

    /**
     * Invalidate (blacklist) a token string
     *
     * @param string $token Full JWT token
     * @return bool
     */
    public static function invalidate(string $token): bool
    {
        $payload = self::verify($token);
        if ($payload && isset($payload['jti']) && isset($payload['exp'])) {
            return self::blacklist($payload['jti'], $payload['exp']);
        }
        return false;
    }

    /**
     * Clean expired entries from blacklist
     *
     * @return int Number of deleted entries
     */
    public static function cleanBlacklist(): int
    {
        try {
            $stmt = db()->query("DELETE FROM jwt_blacklist WHERE expires_at < NOW()");
            return $stmt->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
