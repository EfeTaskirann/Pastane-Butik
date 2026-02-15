<?php
/**
 * Admin Yetkilendirme (Guvenlik Guclendirilmis)
 */

require_once __DIR__ . '/../../includes/security.php';

// Guvenli session baslat
secureSessionStart();

// Guvenlik headerlarini ayarla
setSecurityHeaders();

// Giris kontrolu
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_user']);
}

// Giris gerektir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Giris yap (guvenlik kontrollu)
function login($username, $password) {
    // Hesap kilitli mi kontrol et
    if (isAccountLocked($username)) {
        $remaining = getRemainingLockoutTime($username);
        $minutes = ceil($remaining / 60);
        return [
            'success' => false,
            'error' => "Cok fazla basarisiz deneme. Lutfen {$minutes} dakika sonra tekrar deneyin.",
            'locked' => true
        ];
    }

    $user = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE kullanici_adi = :username",
        ['username' => $username]
    );

    if ($user && password_verify($password, $user['sifre_hash'])) {
        // Basarili giris
        regenerateSessionOnLogin();

        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_user'] = $user['kullanici_adi'];
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_ip'] = getClientIP();

        // Basarili giris logla ve eski denemeleri temizle
        logLoginAttempt($username, true);
        clearFailedLogins($username);

        return ['success' => true];
    }

    // Basarisiz giris
    recordFailedLogin($username);
    logLoginAttempt($username, false);

    return [
        'success' => false,
        'error' => 'Gecersiz kullanici adi veya sifre.',
        'locked' => false
    ];
}

// Cikis yap
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

// Sifre degistir
function changePassword($userId, $newPassword) {
    // Sifre gucu kontrolu
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Sifre en az 8 karakter olmalidir.'];
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
    db()->update('admin_kullanicilar', ['sifre_hash' => $hash], 'id = :id', ['id' => $userId]);

    return ['success' => true];
}

// Session gecerliligi kontrol et
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }

    // IP degismis mi kontrol et (opsiyonel - mobil kullanicilar icin devre disi birakilabilir)
    // if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== getClientIP()) {
    //     logout();
    //     return false;
    // }

    // Session suresi dolmus mu
    if (isset($_SESSION['admin_login_time'])) {
        if (time() - $_SESSION['admin_login_time'] > SESSION_LIFETIME) {
            logout();
            return false;
        }
    }

    return true;
}

// CSRF dogrulama wrapper
function verifyCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateSecureCSRFToken($token)) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz. Lutfen tekrar deneyin.');
        return false;
    }
    return true;
}
