<?php
/**
 * Global Helper Functions
 *
 * Proje genelinde kullanılan yardımcı fonksiyonlar.
 * Composer autoload tarafından otomatik yüklenir.
 *
 * @package Pastane
 * @since 1.0.0
 */

use Pastane\Router\Router;
use Pastane\Services\UrunService;

// ============================================
// APPLICATION HELPERS
// ============================================

if (!function_exists('app_path')) {
    /**
     * Get application base path
     *
     * @param string $path
     * @return string
     */
    function app_path(string $path = ''): string
    {
        $basePath = dirname(__DIR__);
        return $basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return app_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

// ============================================
// URL HELPERS
// ============================================

if (!function_exists('url')) {
    /**
     * Generate URL
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    function url(string $path = '', array $params = []): string
    {
        $baseUrl = config('app.url', '');
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $status
     * @return never
     */
    function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }
}

// ============================================
// STRING HELPERS
// ============================================

if (!function_exists('e')) {
    /**
     * Escape HTML entities
     *
     * @param string|null $value
     * @return string
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8', false);
    }
}

if (!function_exists('str_slug')) {
    /**
     * Generate URL-friendly slug
     *
     * @param string $text
     * @param string $separator
     * @return string
     */
    function str_slug(string $text, string $separator = '-'): string
    {
        // Turkish character map
        $turkishMap = [
            'ç' => 'c', 'Ç' => 'C',
            'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I',
            'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S',
            'ü' => 'u', 'Ü' => 'U',
        ];

        $text = strtr($text, $turkishMap);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', $separator, $text);
        $text = trim($text, $separator);

        return $text;
    }
}

if (!function_exists('str_limit')) {
    /**
     * Limit string length
     *
     * @param string $value
     * @param int $limit
     * @param string $end
     * @return string
     */
    function str_limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('str_random')) {
    /**
     * Generate random string
     *
     * @param int $length
     * @return string
     */
    function str_random(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}

// ============================================
// ARRAY HELPERS
// ============================================

if (!function_exists('array_get')) {
    /**
     * Get array value using dot notation
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('array_set')) {
    /**
     * Set array value using dot notation
     *
     * @param array &$array
     * @param string $key
     * @param mixed $value
     * @return array
     */
    function array_set(array &$array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}

if (!function_exists('array_only')) {
    /**
     * Get subset of array
     *
     * @param array $array
     * @param array $keys
     * @return array
     */
    function array_only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }
}

if (!function_exists('array_except')) {
    /**
     * Get array except specified keys
     *
     * @param array $array
     * @param array $keys
     * @return array
     */
    function array_except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}

// ============================================
// DATE HELPERS
// ============================================

if (!function_exists('now')) {
    /**
     * Get current datetime
     *
     * @param string $format
     * @return string
     */
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date in Turkish
     *
     * @param string|int $date
     * @param string $format
     * @return string
     */
    function format_date(string|int $date, string $format = 'd F Y'): string
    {
        $timestamp = is_string($date) ? strtotime($date) : $date;

        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
        ];

        $result = date($format, $timestamp);

        // Replace month names
        $monthNum = (int)date('n', $timestamp);
        $result = str_replace(date('F', $timestamp), $months[$monthNum], $result);

        return $result;
    }
}

// ============================================
// MONEY HELPERS
// ============================================

if (!function_exists('money')) {
    /**
     * Format money
     *
     * @param float|int|string $amount
     * @param string $currency
     * @return string
     */
    function money(float|int|string $amount, string $currency = '₺'): string
    {
        return $currency . number_format((float)$amount, 2, ',', '.');
    }
}

if (!function_exists('format_price')) {
    /**
     * Format price
     *
     * @param float|int|string $price
     * @return string
     */
    function format_price(float|int|string $price): string
    {
        return number_format((float)$price, 2, ',', '.') . ' ₺';
    }
}

// ============================================
// RESPONSE HELPERS
// ============================================

if (!function_exists('json_response')) {
    /**
     * Send JSON response
     *
     * @param mixed $data
     * @param int $status
     * @return never
     */
    function json_response(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('json_success')) {
    /**
     * Send success JSON response
     *
     * @param mixed $data
     * @param string|null $message
     * @return never
     */
    function json_success(mixed $data = null, ?string $message = null): never
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        json_response($response);
    }
}

if (!function_exists('json_error')) {
    /**
     * Send error JSON response
     *
     * @param string $message
     * @param int $status
     * @param array|null $errors
     * @return never
     */
    function json_error(string $message, int $status = 400, ?array $errors = null): never
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        json_response($response, $status);
    }
}

// ============================================
// SESSION HELPERS
// ============================================

if (!function_exists('session')) {
    /**
     * Get or set session value
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function session(?string $key = null, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($key === null) {
            return $_SESSION;
        }

        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('session_put')) {
    /**
     * Put value in session
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    function session_put(string $key, mixed $value): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[$key] = $value;
    }
}

if (!function_exists('session_forget')) {
    /**
     * Remove value from session
     *
     * @param string $key
     * @return void
     */
    function session_forget(string $key): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[$key]);
    }
}

// ============================================
// VALIDATION HELPERS
// ============================================

if (!function_exists('validate_email')) {
    /**
     * Validate email
     *
     * @param string $email
     * @return bool
     */
    function validate_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validate_phone')) {
    /**
     * Validate Turkish phone number (sıkılaştırılmış)
     * Desteklenen formatlar:
     * - 05XX XXX XX XX (11 haneli, 05 ile başlar)
     * - 5XX XXX XX XX (10 haneli, 5 ile başlar)
     * - 905XXXXXXXXX (12 haneli, 905 ile başlar)
     *
     * @param string $phone
     * @return bool
     */
    function validate_phone(string $phone): bool
    {
        // Sadece rakamları al
        $phone = preg_replace('/\D/', '', $phone);

        // 10 haneli: 5XX ile başlamalı (GSM operatörleri: 50-59)
        if (strlen($phone) === 10) {
            return preg_match('/^5[0-9]{9}$/', $phone) === 1;
        }

        // 11 haneli: 05XX ile başlamalı
        if (strlen($phone) === 11) {
            return preg_match('/^05[0-9]{9}$/', $phone) === 1;
        }

        // 12 haneli: 905XX ile başlamalı (ülke kodu ile)
        if (strlen($phone) === 12) {
            return preg_match('/^905[0-9]{9}$/', $phone) === 1;
        }

        return false;
    }
}

if (!function_exists('logger')) {
    /**
     * Log message
     *
     * @param string $message
     * @param array $context
     * @param string $level
     * @return void
     */
    function logger(string $message, array $context = [], string $level = 'info'): void
    {
        $log = Logger::getInstance();
        $log->$level($message, $context);
    }
}

// ============================================
// SERVICE CONTAINER HELPERS
// ============================================

if (!function_exists('resolve')) {
    /**
     * Resolve class from container (simple factory)
     *
     * @param string $class
     * @return object
     */
    function resolve(string $class): object
    {
        static $instances = [];

        if (!isset($instances[$class])) {
            $instances[$class] = new $class();
        }

        return $instances[$class];
    }
}

if (!function_exists('urun_service')) {
    /**
     * Get UrunService instance
     *
     * @return UrunService
     */
    function urun_service(): UrunService
    {
        return resolve(UrunService::class);
    }
}

if (!function_exists('mesaj_service')) {
    /**
     * Get MesajService instance
     *
     * @return \Pastane\Services\MesajService
     */
    function mesaj_service(): \Pastane\Services\MesajService
    {
        return resolve(\Pastane\Services\MesajService::class);
    }
}

if (!function_exists('kategori_service')) {
    /**
     * Get KategoriService instance
     *
     * @return \Pastane\Services\KategoriService
     */
    function kategori_service(): \Pastane\Services\KategoriService
    {
        return resolve(\Pastane\Services\KategoriService::class);
    }
}

if (!function_exists('rapor_service')) {
    /**
     * Get RaporService instance
     *
     * @return \Pastane\Services\RaporService
     */
    function rapor_service(): \Pastane\Services\RaporService
    {
        return resolve(\Pastane\Services\RaporService::class);
    }
}

if (!function_exists('musteri_service')) {
    /**
     * Get MusteriService instance
     *
     * @return \Pastane\Services\MusteriService
     */
    function musteri_service(): \Pastane\Services\MusteriService
    {
        return resolve(\Pastane\Services\MusteriService::class);
    }
}

if (!function_exists('siparis_service')) {
    /**
     * Get SiparisService instance
     *
     * @return \Pastane\Services\SiparisService
     */
    function siparis_service(): \Pastane\Services\SiparisService
    {
        return resolve(\Pastane\Services\SiparisService::class);
    }
}
