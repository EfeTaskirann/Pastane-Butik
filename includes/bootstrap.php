<?php
/**
 * Application Bootstrap (Basitleştirilmiş)
 */

// Zaten yüklenmişse tekrar yükleme
if (defined('PASTANE_LOADED')) {
    return;
}

define('PASTANE_LOADED', true);

// Proje kök dizini
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Hata gösterimi (geliştirme için)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Composer autoloader (Service sınıfları için gerekli)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Temel config ve veritabanı
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db.php';

// Güvenlik fonksiyonları
if (file_exists(BASE_PATH . '/includes/security.php')) {
    require_once BASE_PATH . '/includes/security.php';
}

// Helper fonksiyonlar (service fonksiyonları burada)
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/functions.php';

// Session'ı auth.php yönetecek, burada başlatmıyoruz
