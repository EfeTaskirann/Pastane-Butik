<?php
/**
 * Guvenlik Fonksiyonlari
 * Brute-force korumasi, rate limiting, guvenlik headerlari
 */

// Guvenlik sabitleri
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);          // Maksimum giris denemesi
    define('LOGIN_LOCKOUT_TIME', 900);        // Kilitleme suresi (15 dakika)
    define('SESSION_LIFETIME', 3600);         // Session suresi (1 saat)
    define('CSRF_TOKEN_LIFETIME', 3600);      // CSRF token suresi (1 saat)
    define('SESSION_NAME', 'PASTANE_SESSION'); // Session adi
    define('MAX_FILE_SIZE', 5242880);         // Maksimum dosya boyutu (5MB)
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']); // Izin verilen uzantilar
}

/**
 * Guvenlik headerlarini ayarla
 */
function setSecurityHeaders() {
    // Clickjacking korumasi
    header('X-Frame-Options: DENY');

    // XSS korumasi
    header('X-XSS-Protection: 1; mode=block');

    // MIME type sniffing korumasi
    header('X-Content-Type-Options: nosniff');

    // Referrer politikasi
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (güçlendirilmiş)
    // NOT: unsafe-inline şimdilik gerekli (inline script/style kullanımı var)
    // TODO: Inline scriptleri harici dosyalara taşıyınca nonce kullanımına geçilebilir
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
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

    // X-Frame-Options (eski tarayıcılar için yedek)
    header('X-Frame-Options: DENY');

    // HTTPS zorlamasi (production icin)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Guvenli session baslatma
 */
function secureSessionStart() {
    // Session zaten baslamissa cik
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Guvenli session ayarlari
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    session_name(SESSION_NAME);
    session_start();

    // Session timeout kontrolu
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    $_SESSION['last_activity'] = time();

    // Session fixation korumasi
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // 30 dakikada bir session ID yenile
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Giris sonrasi session yenileme
 */
function regenerateSessionOnLogin() {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
    $_SESSION['last_activity'] = time();
}

/**
 * IP adresini al
 *
 * GÜVENLİK: HTTP_X_FORWARDED_FOR ve HTTP_CLIENT_IP başlıkları
 * client tarafından manipüle edilebilir. Sadece güvenilir proxy'ler
 * arkasında bu başlıklara güvenilmeli.
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
 * Basarisiz giris denemesini kaydet
 */
function recordFailedLogin($username) {
    $ip = getClientIP();
    $db = db();

    $db->query(
        "INSERT INTO login_attempts (ip_adresi, kullanici_adi, deneme_zamani) VALUES (?, ?, NOW())",
        [$ip, $username]
    );

    // Eski kayitlari temizle (24 saatten eski)
    $db->query("DELETE FROM login_attempts WHERE deneme_zamani < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

/**
 * Basarisiz giris denemelerini temizle
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
 */
function isAccountLocked($username = null) {
    $ip = getClientIP();
    $db = db();
    $lockoutTime = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);

    // IP bazli kontrol
    $ipAttempts = $db->fetch(
        "SELECT COUNT(*) as count FROM login_attempts WHERE ip_adresi = ? AND deneme_zamani > ?",
        [$ip, $lockoutTime]
    );

    if ($ipAttempts && $ipAttempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }

    // Kullanici adi bazli kontrol
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
 * Kalan kilitleme suresini al (saniye)
 */
function getRemainingLockoutTime($username = null) {
    $ip = getClientIP();
    $db = db();
    $lockoutTime = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_TIME);

    // En son deneme zamanini bul
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
 * Giris logunu kaydet
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
 * Gelismis CSRF token olustur
 */
function generateSecureCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

/**
 * CSRF token dogrula (zaman kontrolu ile)
 */
function validateSecureCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Zaman kontrolu
    if (isset($_SESSION['csrf_token_time'])) {
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF token input fieldi olustur
 * Not: Ayni sayfada birden fazla form varsa token'i yeniden kullanir
 */
function csrfTokenField() {
    // Mevcut ve gecerli bir token varsa onu kullan, yoksa yeni olustur
    if (!empty($_SESSION['csrf_token']) && isset($_SESSION['csrf_token_time'])) {
        // Token suresi dolmamissa mevcut token'i kullan
        if (time() - $_SESSION['csrf_token_time'] <= CSRF_TOKEN_LIFETIME) {
            return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
        }
    }
    // Gecerli token yoksa yeni olustur
    $token = generateSecureCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Dosya MIME type kontrolu
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
 * Guvenli dosya yukleme
 */
function secureUploadImage($file, $folder = 'products') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Dosya secilmedi'];
    }

    // Dosya boyutu kontrolu
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Dosya cok buyuk (Max: 5MB)'];
    }

    // Uzanti kontrolu
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Gecersiz dosya turu'];
    }

    // MIME type kontrolu
    if (!validateFileMimeType($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Gecersiz dosya icerigi'];
    }

    // Guvenli dosya adi olustur
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;

    // Dosyayi tasma
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Yukleme basarisiz'];
}

/**
 * Input temizleme
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
 * Rate limiting kontrolu
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

    // Zaman penceresi gecmisse sifirla
    if (time() - $data['start_time'] > $timeWindow) {
        $data['count'] = 0;
        $data['start_time'] = time();
    }

    $data['count']++;

    return $data['count'] <= $maxRequests;
}
