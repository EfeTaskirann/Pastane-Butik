<?php
/**
 * Güvenlik Fonksiyonları
 *
 * Brute-force koruması, rate limiting, güvenlik başlıkları.
 *
 * @package Pastane
 * @since 1.0.0
 */

// Güvenlik sabitleri
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);          // Maksimum giriş denemesi
    define('LOGIN_LOCKOUT_TIME', 900);        // Kilitleme süresi (15 dakika)
    define('SESSION_LIFETIME', 3600);         // Session süresi (1 saat)
    define('CSRF_TOKEN_LIFETIME', 3600);      // CSRF token süresi (1 saat)
    define('SESSION_NAME', 'PASTANE_SESSION'); // Session adı
    define('MAX_FILE_SIZE', 5242880);         // Maksimum dosya boyutu (5MB)
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']); // İzin verilen uzantılar
}

/**
 * CSP nonce değerini al veya oluştur (request başına tek nonce)
 *
 * @return string
 */
function getCspNonce(): string {
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

/**
 * Güvenlik başlıklarını ayarla
 *
 * CSP, clickjacking koruması, XSS koruması vb.
 *
 * @return void
 */
function setSecurityHeaders() {
    // Clickjacking koruması
    header('X-Frame-Options: DENY');

    // XSS koruması
    header('X-XSS-Protection: 1; mode=block');

    // MIME type sniffing koruması
    header('X-Content-Type-Options: nosniff');

    // Referrer politikası
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions Policy
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

    // Content Security Policy (nonce-based)
    // Nonce sistemi sayesinde 'unsafe-inline' kaldırıldı (script-src için)
    // Style için unsafe-inline hala gerekli (Google Fonts + inline styles)
    $nonce = getCspNonce();
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self'",
        "form-action 'self'",               // Form sadece kendi siteye gönderilebilir
        "frame-ancestors 'none'",           // Clickjacking koruması
        "base-uri 'self'",                  // Base tag hijacking koruması
        "object-src 'none'",                // Plugin/embed engelle
        "upgrade-insecure-requests",        // HTTP isteklerini HTTPS'e yükselt
    ];
    header("Content-Security-Policy: " . implode('; ', $csp));

    // HTTPS zorlaması (production için)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Güvenli session başlatma
 *
 * Session fixation ve timeout koruması içerir.
 *
 * @return void
 */
function secureSessionStart() {
    // Session zaten başlamışsa çık
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Güvenli session ayarları
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    session_name(SESSION_NAME);
    session_start();

    // Session timeout kontrolü
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    $_SESSION['last_activity'] = time();

    // Session fixation koruması
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // 30 dakikada bir session ID yenile
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Giriş sonrası session yenileme
 *
 * Session fixation saldırılarına karşı ID yeniler.
 *
 * @return void
 */
function regenerateSessionOnLogin() {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * İstemci IP adresini al
 *
 * GÜVENLİK: HTTP_X_FORWARDED_FOR ve HTTP_CLIENT_IP başlıkları
 * client tarafından manipüle edilebilir. Sadece güvenilir proxy'ler
 * arkasında bu başlıklara güvenilmeli.
 *
 * @return string
 */
function getClientIP() {
    // Güvenilir proxy CIDR aralıkları
    $trustedProxies = [
        '127.0.0.1',
        '::1',
    ];

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Cloudflare kullanılıyorsa CF header'ını kontrol et
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cfIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $cfIp;
        }
    }

    // Güvenilir proxy kontrolü
    $isTrustedProxy = in_array($remoteAddr, $trustedProxies, true);

    // Private IP aralıklarını da güvenilir say (internal load balancer)
    if (!$isTrustedProxy) {
        $isTrustedProxy = filter_var(
            $remoteAddr,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    // Güvenilir proxy'den geliyorsa X-Forwarded-For'u kullan
    if ($isTrustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        // İlk geçerli public IP'yi al
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    // Fallback: REMOTE_ADDR (doğrulanmış)
    if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return $remoteAddr;
    }

    return '0.0.0.0';
}

/**
 * Başarısız giriş denemesini kaydet
 *
 * @param string $username Kullanıcı adı
 * @return void
 */
function recordFailedLogin($username) {
    $ip = getClientIP();
    $db = db();

    $db->query(
        "INSERT INTO login_attempts (ip_adresi, kullanici_adi, deneme_zamani) VALUES (?, ?, NOW())",
        [$ip, $username]
    );

    // Eski kayıtları temizle (24 saatten eski)
    $db->query("DELETE FROM login_attempts WHERE deneme_zamani < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

/**
 * Başarısız giriş denemelerini temizle
 *
 * @param string|null $username Kullanıcı adı (null ise sadece IP bazlı temizle)
 * @return void
 */
function clearFailedLogins($username = null) {
    $ip = getClientIP();
    $db = db();

    if ($username) {
        $db->query(
            "DELETE FROM login_attempts WHERE ip_adresi = ? OR kullanici_adi = ?",
            [$ip, $username]
        );
    } else {
        $db->query("DELETE FROM login_attempts WHERE ip_adresi = ?", [$ip]);
    }
}

/**
 * Hesap kilitli mi kontrol et
 *
 * IP ve kullanıcı adı bazlı kontrol yapar.
 *
 * @param string|null $username Kullanıcı adı
 * @return bool
 */
function isAccountLocked($username = null) {
    $ip = getClientIP();
    $db = db();
    $lockoutTime = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);

    // IP bazlı kontrol
    $ipAttempts = $db->fetch(
        "SELECT COUNT(*) as count FROM login_attempts WHERE ip_adresi = ? AND deneme_zamani > ?",
        [$ip, $lockoutTime]
    );

    if ($ipAttempts && $ipAttempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }

    // Kullanıcı adı bazlı kontrol
    if ($username) {
        $userAttempts = $db->fetch(
            "SELECT COUNT(*) as count FROM login_attempts WHERE kullanici_adi = ? AND deneme_zamani > ?",
            [$username, $lockoutTime]
        );

        if ($userAttempts && $userAttempts['count'] >= MAX_LOGIN_ATTEMPTS) {
            return true;
        }
    }

    return false;
}

/**
 * Kalan kilitleme süresini al
 *
 * @param string|null $username Kullanıcı adı
 * @return int Kalan süre (saniye)
 */
function getRemainingLockoutTime($username = null) {
    $ip = getClientIP();
    $db = db();
    $lockoutTime = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);

    // En son deneme zamanını bul
    $lastAttempt = $db->fetch(
        "SELECT MAX(deneme_zamani) as last_attempt FROM login_attempts
         WHERE (ip_adresi = ? OR kullanici_adi = ?) AND deneme_zamani > ?",
        [$ip, $username, $lockoutTime]
    );

    if ($lastAttempt && $lastAttempt['last_attempt']) {
        $lastTime = strtotime($lastAttempt['last_attempt']);
        $unlockTime = $lastTime + LOGIN_LOCKOUT_TIME;
        $remaining = $unlockTime - time();
        return max(0, $remaining);
    }

    return 0;
}

/**
 * Giriş logunu kaydet
 *
 * @param string $username Kullanıcı adı
 * @param bool $success Başarılı mı
 * @return void
 */
function logLoginAttempt($username, $success) {
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $db = db();

    $db->query(
        "INSERT INTO login_log (kullanici_adi, ip_adresi, user_agent, basarili, tarih)
         VALUES (?, ?, ?, ?, NOW())",
        [$username, $ip, $userAgent, $success ? 1 : 0]
    );
}

/**
 * Gelişmiş CSRF token oluştur
 *
 * @return string Token değeri
 */
function generateSecureCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

/**
 * CSRF token doğrula (zaman kontrolü ile)
 *
 * @param string $token Doğrulanacak token
 * @return bool
 */
function validateSecureCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Zaman kontrolü
    if (isset($_SESSION['csrf_token_time'])) {
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF token input alanı oluştur
 *
 * Aynı sayfada birden fazla form varsa mevcut token'ı yeniden kullanır.
 *
 * @return string HTML hidden input
 */
function csrfTokenField() {
    // Mevcut ve geçerli bir token varsa onu kullan, yoksa yeni oluştur
    if (!empty($_SESSION['csrf_token']) && isset($_SESSION['csrf_token_time'])) {
        // Token süresi dolmamışsa mevcut token'ı kullan
        if (time() - $_SESSION['csrf_token_time'] <= CSRF_TOKEN_LIFETIME) {
            return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
        }
    }
    // Geçerli token yoksa yeni oluştur
    $token = generateSecureCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Dosya MIME type kontrolü
 *
 * @param string $filePath Dosya yolu
 * @param array|null $allowedMimes İzin verilen MIME türleri
 * @return bool
 */
function validateFileMimeType($filePath, $allowedMimes = null) {
    if ($allowedMimes === null) {
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);

    return in_array($mimeType, $allowedMimes);
}

/**
 * Güvenli dosya yükleme
 *
 * Boyut, uzantı ve MIME type kontrolü yapar.
 *
 * @param array $file $_FILES dizisi elemanı
 * @param string $folder Hedef klasör
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function secureUploadImage($file, $folder = 'products') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Dosya seçilmedi'];
    }

    // Dosya boyutu kontrolü
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Dosya çok büyük (Max: 5MB)'];
    }

    // Uzantı kontrolü
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Geçersiz dosya türü'];
    }

    // MIME type kontrolü
    if (!validateFileMimeType($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Geçersiz dosya içeriği'];
    }

    // Güvenli dosya adı oluştur
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;

    // Dosyayı taşı
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Yükleme başarısız'];
}

/**
 * Input temizleme
 *
 * Türe göre uygun sanitize işlemi uygular.
 *
 * @param mixed $input Temizlenecek değer
 * @param string $type Tür (string, email, int, float, url)
 * @return mixed Temizlenmiş değer
 */
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $input);
    }

    $input = trim($input);

    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Rate limiting kontrolü (session-based)
 *
 * @param string $action İşlem adı
 * @param int $maxRequests Maksimum istek sayısı
 * @param int $timeWindow Zaman penceresi (saniye)
 * @return bool İstek izni var mı
 */
function checkRateLimit($action, $maxRequests = 60, $timeWindow = 60) {
    $ip = getClientIP();
    $key = "rate_limit_{$action}_{$ip}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'start_time' => time()
        ];
    }

    $data = &$_SESSION[$key];

    // Zaman penceresi geçmişse sıfırla
    if (time() - $data['start_time'] > $timeWindow) {
        $data['count'] = 0;
        $data['start_time'] = time();
    }

    $data['count']++;

    return $data['count'] <= $maxRequests;
}
