<?php
/**
 * Password Policy Manager
 *
 * Güçlü şifre politikası uygulama ve doğrulama.
 * NIST ve OWASP önerilerine uygun.
 *
 * @package Pastane
 * @since 1.0.0
 */

class PasswordPolicy
{
    /**
     * @var array Policy configuration
     */
    private static array $policy = [
        'min_length' => 12,
        'max_length' => 128,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'min_unique_chars' => 6,
        'max_consecutive_chars' => 3,
        'disallow_common' => true,
        'disallow_username' => true,
        'history_count' => 5,
    ];

    /**
     * @var array Common/weak passwords list
     */
    private static array $commonPasswords = [
        'password', '123456', '12345678', 'qwerty', 'abc123', 'monkey', '1234567',
        'letmein', 'trustno1', 'dragon', 'baseball', 'iloveyou', 'master', 'sunshine',
        'ashley', 'bailey', 'passw0rd', 'shadow', '123123', '654321', 'superman',
        'qazwsx', 'michael', 'football', 'password1', 'password123', 'welcome',
        'jesus', 'ninja', 'mustang', 'admin', 'admin123', 'root', 'toor',
    ];

    /**
     * Initialize policy from config
     *
     * @return void
     */
    public static function init(): void
    {
        $configPolicy = config('security.password', []);
        self::$policy = array_merge(self::$policy, $configPolicy);
    }

    /**
     * Validate a password against the policy
     *
     * @param string $password Password to validate
     * @param string|null $username Username (to check against)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate(string $password, ?string $username = null): array
    {
        $errors = [];

        // Length checks
        if (strlen($password) < self::$policy['min_length']) {
            $errors[] = sprintf('Şifre en az %d karakter olmalıdır.', self::$policy['min_length']);
        }

        if (strlen($password) > self::$policy['max_length']) {
            $errors[] = sprintf('Şifre en fazla %d karakter olabilir.', self::$policy['max_length']);
        }

        // Character type checks
        if (self::$policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Şifre en az bir büyük harf içermelidir.';
        }

        if (self::$policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Şifre en az bir küçük harf içermelidir.';
        }

        if (self::$policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Şifre en az bir rakam içermelidir.';
        }

        if (self::$policy['require_symbols'] && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
            $errors[] = 'Şifre en az bir özel karakter içermelidir (!@#$%^&* vb.).';
        }

        // Unique characters
        $uniqueChars = count(array_unique(str_split($password)));
        if ($uniqueChars < self::$policy['min_unique_chars']) {
            $errors[] = sprintf('Şifre en az %d farklı karakter içermelidir.', self::$policy['min_unique_chars']);
        }

        // Consecutive characters check
        if (self::hasConsecutiveChars($password, self::$policy['max_consecutive_chars'])) {
            $errors[] = sprintf('Şifre %d\'den fazla ardışık aynı karakter içeremez.', self::$policy['max_consecutive_chars']);
        }

        // Sequential characters check (abc, 123)
        if (self::hasSequentialChars($password)) {
            $errors[] = 'Şifre ardışık harf veya rakam dizileri içeremez (abc, 123 vb.).';
        }

        // Common password check
        if (self::$policy['disallow_common'] && self::isCommonPassword($password)) {
            $errors[] = 'Bu şifre çok yaygın ve güvenli değil. Lütfen farklı bir şifre seçin.';
        }

        // Username check
        if (self::$policy['disallow_username'] && $username) {
            if (stripos($password, $username) !== false) {
                $errors[] = 'Şifre kullanıcı adını içeremez.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculateStrength($password),
        ];
    }

    /**
     * Calculate password strength score
     *
     * @param string $password
     * @return array ['score' => int, 'label' => string, 'color' => string]
     */
    public static function calculateStrength(string $password): array
    {
        $score = 0;

        // Length score
        $length = strlen($password);
        if ($length >= 8) $score += 1;
        if ($length >= 12) $score += 1;
        if ($length >= 16) $score += 1;
        if ($length >= 20) $score += 1;

        // Character variety
        if (preg_match('/[a-z]/', $password)) $score += 1;
        if (preg_match('/[A-Z]/', $password)) $score += 1;
        if (preg_match('/[0-9]/', $password)) $score += 1;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) $score += 2;

        // Unique characters bonus
        $uniqueRatio = count(array_unique(str_split($password))) / $length;
        if ($uniqueRatio > 0.8) $score += 1;

        // Penalty for patterns
        if (self::hasConsecutiveChars($password, 2)) $score -= 1;
        if (self::hasSequentialChars($password)) $score -= 1;
        if (self::isCommonPassword($password)) $score -= 3;

        $score = max(0, min(10, $score));

        $labels = [
            [0, 2, 'Çok Zayıf', '#dc3545'],
            [3, 4, 'Zayıf', '#fd7e14'],
            [5, 6, 'Orta', '#ffc107'],
            [7, 8, 'Güçlü', '#20c997'],
            [9, 10, 'Çok Güçlü', '#28a745'],
        ];

        foreach ($labels as [$min, $max, $label, $color]) {
            if ($score >= $min && $score <= $max) {
                return [
                    'score' => $score,
                    'label' => $label,
                    'color' => $color,
                    'percentage' => ($score / 10) * 100,
                ];
            }
        }

        return ['score' => 0, 'label' => 'Bilinmiyor', 'color' => '#6c757d', 'percentage' => 0];
    }

    /**
     * Check password against history
     *
     * @param string $password New password
     * @param int $userId User ID
     * @return bool True if password was used before
     */
    public static function isInHistory(string $password, int $userId): bool
    {
        $user = db()->fetch(
            "SELECT password_history FROM admin_kullanicilar WHERE id = ?",
            [$userId]
        );

        if (!$user || empty($user['password_history'])) {
            return false;
        }

        $history = json_decode($user['password_history'], true) ?? [];

        foreach ($history as $oldHash) {
            if (password_verify($password, $oldHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add password to history
     *
     * @param string $passwordHash Hashed password
     * @param int $userId User ID
     * @return void
     */
    public static function addToHistory(string $passwordHash, int $userId): void
    {
        $user = db()->fetch(
            "SELECT password_history FROM admin_kullanicilar WHERE id = ?",
            [$userId]
        );

        $history = [];
        if ($user && !empty($user['password_history'])) {
            $history = json_decode($user['password_history'], true) ?? [];
        }

        // Add new hash at the beginning
        array_unshift($history, $passwordHash);

        // Keep only the configured number of passwords
        $history = array_slice($history, 0, self::$policy['history_count']);

        db()->update('admin_kullanicilar', [
            'password_history' => json_encode($history),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $userId]);
    }

    /**
     * Check if password needs to be changed (expired)
     *
     * @param int $userId
     * @return bool
     */
    public static function isExpired(int $userId): bool
    {
        $maxAgeDays = config('security.password.max_age_days', 90);

        if ($maxAgeDays === 0) {
            return false; // Password expiry disabled
        }

        $user = db()->fetch(
            "SELECT password_changed_at FROM admin_kullanicilar WHERE id = ?",
            [$userId]
        );

        if (!$user || empty($user['password_changed_at'])) {
            return true; // Force change if no record
        }

        $changedAt = strtotime($user['password_changed_at']);
        $expiresAt = $changedAt + ($maxAgeDays * 86400);

        return time() > $expiresAt;
    }

    /**
     * Generate a random strong password
     *
     * @param int $length Password length
     * @return string
     */
    public static function generate(int $length = 16): string
    {
        $lowercase = 'abcdefghijkmnopqrstuvwxyz'; // Excluding l for clarity
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding I, O for clarity
        $numbers = '23456789'; // Excluding 0, 1 for clarity
        $symbols = '!@#$%^&*-_=+?';

        $password = '';

        // Ensure at least one of each required type
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill the rest
        $allChars = $lowercase . $uppercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Check for consecutive repeated characters
     *
     * @param string $password
     * @param int $max Maximum allowed consecutive chars
     * @return bool
     */
    private static function hasConsecutiveChars(string $password, int $max): bool
    {
        $pattern = '/(.)\1{' . $max . ',}/';
        return preg_match($pattern, $password) === 1;
    }

    /**
     * Check for sequential characters (abc, 123, etc.)
     *
     * @param string $password
     * @return bool
     */
    private static function hasSequentialChars(string $password): bool
    {
        $sequences = [
            'abcdefghijklmnopqrstuvwxyz',
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            '0123456789',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
        ];

        $password = strtolower($password);

        foreach ($sequences as $seq) {
            for ($i = 0; $i <= strlen($seq) - 4; $i++) {
                $substring = substr($seq, $i, 4);
                if (strpos($password, $substring) !== false) {
                    return true;
                }
                // Check reverse
                if (strpos($password, strrev($substring)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if password is in common passwords list
     *
     * @param string $password
     * @return bool
     */
    private static function isCommonPassword(string $password): bool
    {
        $normalized = strtolower($password);

        // Check exact match
        if (in_array($normalized, self::$commonPasswords)) {
            return true;
        }

        // Check with common substitutions (l33t speak)
        $substitutions = [
            'a' => ['4', '@'],
            'e' => ['3'],
            'i' => ['1', '!'],
            'o' => ['0'],
            's' => ['$', '5'],
            't' => ['7'],
        ];

        $variants = [$normalized];

        foreach ($substitutions as $char => $replacements) {
            $newVariants = [];
            foreach ($variants as $variant) {
                foreach ($replacements as $replacement) {
                    $newVariants[] = str_replace($replacement, $char, $variant);
                }
            }
            $variants = array_merge($variants, $newVariants);
        }

        foreach (array_unique($variants) as $variant) {
            if (in_array($variant, self::$commonPasswords)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get policy requirements as human-readable text
     *
     * @return array
     */
    public static function getRequirements(): array
    {
        $requirements = [];

        $requirements[] = sprintf('En az %d karakter uzunluğunda olmalı', self::$policy['min_length']);

        if (self::$policy['require_uppercase']) {
            $requirements[] = 'En az bir büyük harf içermeli (A-Z)';
        }

        if (self::$policy['require_lowercase']) {
            $requirements[] = 'En az bir küçük harf içermeli (a-z)';
        }

        if (self::$policy['require_numbers']) {
            $requirements[] = 'En az bir rakam içermeli (0-9)';
        }

        if (self::$policy['require_symbols']) {
            $requirements[] = 'En az bir özel karakter içermeli (!@#$%^&* vb.)';
        }

        if (self::$policy['min_unique_chars'] > 1) {
            $requirements[] = sprintf('En az %d farklı karakter kullanılmalı', self::$policy['min_unique_chars']);
        }

        $requirements[] = 'Ardışık karakter dizileri içermemeli (abc, 123)';
        $requirements[] = 'Yaygın şifreler kullanılmamalı';

        return $requirements;
    }
}
