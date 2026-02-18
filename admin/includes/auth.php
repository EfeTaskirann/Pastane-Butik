<?php
/**
 * Admin Yetkilendirme (Güvenlik Güçlendirilmiş)
 */

require_once __DIR__ . '/../../includes/security.php';

// Güvenli session başlat
secureSessionStart();

// Güvenlik header'larını ayarla
setSecurityHeaders();

// Giriş kontrolü
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_user']);
}

// Giriş gerektir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Giriş yap (güvenlik kontrollü)
function login($username, $password) {
    // Hesap kilitli mi kontrol et
    if (isAccountLocked($username)) {
        $remaining = getRemainingLockoutTime($username);
        $minutes = ceil($remaining / 60);
        return [
            'success' => false,
            'error' => "Çok fazla başarısız deneme. Lütfen {$minutes} dakika sonra tekrar deneyin.",
            'locked' => true
        ];
    }

    $user = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE kullanici_adi = :username",
        ['username' => $username]
    );

    if ($user && password_verify($password, $user['sifre_hash'])) {
        // Başarılı giriş
        regenerateSessionOnLogin();

        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_user'] = $user['kullanici_adi'];
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_ip'] = getClientIP();

        // Başarılı giriş logla ve eski denemeleri temizle
        logLoginAttempt($username, true);
        clearFailedLogins($username);

        return ['success' => true];
    }

    // Başarısız giriş
    recordFailedLogin($username);
    logLoginAttempt($username, false);

    return [
        'success' => false,
        'error' => 'Geçersiz kullanıcı adı veya şifre.',
        'locked' => false
    ];
}

// Çıkış yap
function logout() {
    // Session verilerini temizle
    $_SESSION = [];

    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header('Location: index.php');
    exit;
}

// Şifre değiştir
function changePassword($userId, $newPassword) {
    // Şifre gücü kontrolü
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Şifre en az 8 karakter olmalıdır.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
    db()->update('admin_kullanicilar', ['sifre_hash' => $hash], 'id = :id', ['id' => $userId]);

    return ['success' => true];
}

// Session geçerliliği kontrol et
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }

    // IP değişmiş mi kontrol et (opsiyonel - mobil kullanıcılar için devre dışı bırakılabilir)
    // if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== getClientIP()) {
    //     logout();
    //     return false;
    // }

    // Session süresi dolmuş mu
    if (isset($_SESSION['admin_login_time'])) {
        if (time() - $_SESSION['admin_login_time'] > SESSION_LIFETIME) {
            logout();
            return false;
        }
    }

    return true;
}

// CSRF doğrulama wrapper
function verifyCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateSecureCSRFToken($token)) {
        setFlash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        return false;
    }
    return true;
}
