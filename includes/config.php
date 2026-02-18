<?php
/**
 * Konfigürasyon Dosyası
 *
 * GÜVENLİK: Hassas bilgiler .env dosyasından okunur
 */

// Env sınıfını yükle
require_once __DIR__ . '/Env.php';

// .env dosyasını yükle (proje kök dizininde)
Env::load(dirname(__DIR__));

// Basit config() fonksiyonu
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        $config = [
            // App
            'app.url' => defined('SITE_URL') ? SITE_URL : env('APP_URL', 'http://localhost/pastane'),
            'app.name' => defined('SITE_NAME') ? SITE_NAME : env('APP_NAME', 'Tatlı Düşler'),
            'app.debug' => defined('DEBUG_MODE') ? DEBUG_MODE : env('APP_DEBUG', false),
            'app.timezone' => env('APP_TIMEZONE', 'Europe/Istanbul'),
            'app.asset_version' => '1.1',

            // Security
            'security.jwt.expiration' => (int) env('SESSION_LIFETIME', 3600),
            'security.cors.allowed_origins' => [env('APP_URL', 'http://localhost/pastane')],

            // Cache
            'cache.driver' => env('CACHE_DRIVER', 'file'),
            'cache.path' => env('CACHE_PATH', 'storage/cache'),
            'cache.ttl' => 3600,
            'cache.prefix' => 'pastane_',
        ];

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

// Veritabanı ayarları - .env dosyasından oku
if (!defined('DB_HOST')) {
    define('DB_HOST', env('DB_HOST', 'localhost'));
    define('DB_USER', env('DB_USERNAME', 'root'));
    define('DB_PASS', env('DB_PASSWORD', ''));
    define('DB_NAME', env('DB_DATABASE', 'pastane_db'));
    define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
}

// Site ayarları - .env dosyasından oku
if (!defined('SITE_NAME')) {
    define('SITE_NAME', env('APP_NAME', 'Tatlı Düşler'));
    define('SITE_URL', env('APP_URL', 'http://localhost/pastane'));
    define('SITE_SLOGAN', 'El yapımı lezzetler');
    define('WHATSAPP_NUMBER', env('WHATSAPP_NUMBER', '905551234567'));
}

// Upload ayarları
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 5242880);
    define('UPLOAD_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
    define('UPLOAD_PATH', '/uploads/products/');
}

// Debug modu - Production'da false olmalı!
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', env('APP_DEBUG', false));
}

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Debug modda hataları göster
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
