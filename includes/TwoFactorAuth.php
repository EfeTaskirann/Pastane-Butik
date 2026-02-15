<?php
/**
 * Two-Factor Authentication (TOTP)
 *
 * Time-based One-Time Password (RFC 6238) implementasyonu.
 * Google Authenticator, Authy gibi uygulamalarla uyumlu.
 *
 * @package Pastane
 * @since 1.0.0
 */

class TwoFactorAuth
{
    /**
     * @var int Code length (6 digits)
     */
    private const CODE_LENGTH = 6;

    /**
     * @var int Time step in seconds (30 seconds)
     */
    private const TIME_STEP = 30;

    /**
     * @var int Allowed time drift windows (1 = ±30 seconds)
     */
    private const WINDOW = 1;

    /**
     * @var string Algorithm for HMAC
     */
    private const ALGORITHM = 'sha1';

    /**
     * @var string Base32 alphabet
     */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new secret key
     *
     * @param int $length Secret length (default: 16)
     * @return string Base32 encoded secret
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $randomBytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[ord($randomBytes[$i]) % 32];
        }

        return $secret;
    }

    /**
     * Generate TOTP code
     *
     * @param string $secret Base32 encoded secret
     * @param int|null $timestamp Unix timestamp (default: current time)
     * @return string 6-digit code
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter = floor($timestamp / self::TIME_STEP);

        $secretKey = self::base32Decode($secret);
        $counterBytes = pack('N*', 0, $counter);

        $hash = hash_hmac(self::ALGORITHM, $counterBytes, $secretKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $binary % (10 ** self::CODE_LENGTH);

        return str_pad((string) $code, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code
     *
     * @param string $secret Base32 encoded secret
     * @param string $code User-provided code
     * @param int|null $timestamp Unix timestamp (default: current time)
     * @return bool
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp = $timestamp ?? time();

        // Remove spaces and validate
        $code = preg_replace('/\s+/', '', $code);

        if (strlen($code) !== self::CODE_LENGTH || !ctype_digit($code)) {
            return false;
        }

        // Check within time window
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $checkTime = $timestamp + ($i * self::TIME_STEP);
            $expectedCode = self::getCode($secret, $checkTime);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate provisioning URI for authenticator apps
     *
     * @param string $secret Base32 encoded secret
     * @param string $accountName User identifier (email)
     * @param string|null $issuer Application name
     * @return string otpauth:// URI
     */
    public static function getProvisioningUri(
        string $secret,
        string $accountName,
        ?string $issuer = null
    ): string {
        $issuer = $issuer ?? config('app.name', 'Pastane');
        $issuer = rawurlencode($issuer);
        $accountName = rawurlencode($accountName);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            $issuer,
            $accountName,
            $secret,
            $issuer,
            strtoupper(self::ALGORITHM),
            self::CODE_LENGTH,
            self::TIME_STEP
        );
    }

    /**
     * Generate QR code URL (using external service)
     *
     * @param string $provisioningUri
     * @param int $size QR code size
     * @return string QR code image URL
     */
    public static function getQRCodeUrl(string $provisioningUri, int $size = 200): string
    {
        // Using Google Charts API (consider self-hosting for privacy)
        return sprintf(
            'https://chart.googleapis.com/chart?cht=qr&chs=%dx%d&chl=%s&choe=UTF-8',
            $size,
            $size,
            urlencode($provisioningUri)
        );
    }

    /**
     * Generate backup codes
     *
     * @param int $count Number of backup codes
     * @return array Array of backup codes
     */
    public static function generateBackupCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf(
                '%s-%s',
                strtoupper(bin2hex(random_bytes(3))),
                strtoupper(bin2hex(random_bytes(3)))
            );
        }

        return $codes;
    }

    /**
     * Hash backup codes for storage
     *
     * @param array $codes Plain backup codes
     * @return array Hashed backup codes
     */
    public static function hashBackupCodes(array $codes): array
    {
        return array_map(fn($code) => password_hash($code, PASSWORD_DEFAULT), $codes);
    }

    /**
     * Verify a backup code
     *
     * @param string $code User-provided code
     * @param array $hashedCodes Array of hashed backup codes
     * @return int|false Index of matched code or false
     */
    public static function verifyBackupCode(string $code, array $hashedCodes): int|false
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Check if 2FA is enabled for a user
     *
     * @param int $userId
     * @return bool
     */
    public static function isEnabled(int $userId): bool
    {
        $user = db()->fetch(
            "SELECT two_factor_secret FROM admin_kullanicilar WHERE id = ?",
            [$userId]
        );

        return !empty($user['two_factor_secret']);
    }

    /**
     * Enable 2FA for a user
     *
     * @param int $userId
     * @param string $secret
     * @param array $backupCodes
     * @return bool
     */
    public static function enable(int $userId, string $secret, array $backupCodes): bool
    {
        try {
            db()->update('admin_kullanicilar', [
                'two_factor_secret' => $secret,
                'two_factor_backup_codes' => json_encode(self::hashBackupCodes($backupCodes)),
                'two_factor_enabled_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $userId]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Disable 2FA for a user
     *
     * @param int $userId
     * @return bool
     */
    public static function disable(int $userId): bool
    {
        try {
            db()->update('admin_kullanicilar', [
                'two_factor_secret' => null,
                'two_factor_backup_codes' => null,
                'two_factor_enabled_at' => null,
            ], 'id = :id', ['id' => $userId]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Base32 decode
     *
     * @param string $input
     * @return string
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);

        $binary = '';
        foreach (str_split($input) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index !== false) {
                $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $output = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }

        return $output;
    }

    /**
     * Get remaining seconds until code expires
     *
     * @return int
     */
    public static function getRemainingSeconds(): int
    {
        return self::TIME_STEP - (time() % self::TIME_STEP);
    }
}
