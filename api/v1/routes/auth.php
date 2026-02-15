<?php
/**
 * Authentication API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;

$router = Router::getInstance();

/**
 * POST /api/v1/auth/login
 * Admin girişi
 */
$router->post('/api/v1/auth/login', function() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['kullanici_adi']) || empty($data['sifre'])) {
        json_error('Kullanıcı adı ve şifre zorunludur.', 422);
    }

    // Rate limit check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateLimitResult = RateLimiter::check('login', $ip);

    if (!$rateLimitResult['allowed']) {
        SecurityAudit::log(SecurityAudit::RATE_LIMIT_EXCEEDED, null, [
            'action' => 'login',
            'username' => $data['kullanici_adi'],
        ]);

        json_error('Çok fazla başarısız deneme. Lütfen bekleyin.', 429);
    }

    // Find user
    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE kullanici_adi = ?",
        [$data['kullanici_adi']]
    );

    if (!$kullanici) {
        RateLimiter::hit('login', $ip);
        SecurityAudit::logFailedLogin($data['kullanici_adi'], 'user_not_found');
        json_error('Geçersiz kullanıcı adı veya şifre.', 401);
    }

    // Check password
    if (!password_verify($data['sifre'], $kullanici['sifre'])) {
        RateLimiter::hit('login', $ip);
        SecurityAudit::logFailedLogin($data['kullanici_adi'], 'invalid_password');
        json_error('Geçersiz kullanıcı adı veya şifre.', 401);
    }

    // Check if account is locked
    if (!empty($kullanici['locked_until']) && strtotime($kullanici['locked_until']) > time()) {
        json_error('Hesabınız geçici olarak kilitlendi. Lütfen daha sonra tekrar deneyin.', 403);
    }

    // Check if 2FA is enabled
    if (!empty($kullanici['two_factor_secret']) && empty($data['two_factor_code'])) {
        // HTTP 401 Unauthorized - 2FA kodu eksik
        // Client requires_2fa flag'ını kontrol edip 2FA formunu göstermeli
        json_response([
            'success' => false,
            'requires_2fa' => true,
            'message' => 'İki faktörlü doğrulama kodu gerekli.',
        ], 401);
    }

    // Verify 2FA code if provided
    if (!empty($kullanici['two_factor_secret']) && !empty($data['two_factor_code'])) {
        if (!TwoFactorAuth::verify($kullanici['two_factor_secret'], $data['two_factor_code'])) {
            SecurityAudit::log(SecurityAudit::TWO_FACTOR_FAILED, $kullanici['id']);
            json_error('Geçersiz doğrulama kodu.', 401);
        }
    }

    // Generate JWT token
    $token = JWT::create([
        'user_id' => $kullanici['id'],
        'username' => $kullanici['kullanici_adi'],
        'role' => $kullanici['rol'] ?? 'admin',
    ]);

    // Log successful login
    SecurityAudit::logLogin($kullanici['id'], $kullanici['kullanici_adi']);

    json_success([
        'token' => $token,
        'expires_in' => config('security.jwt.expiration', 3600),
        'user' => [
            'id' => $kullanici['id'],
            'kullanici_adi' => $kullanici['kullanici_adi'],
            'email' => $kullanici['email'] ?? null,
            'rol' => $kullanici['rol'] ?? 'admin',
        ],
    ], 'Giriş başarılı.');
});

/**
 * POST /api/v1/auth/refresh
 * Token yenile
 */
$router->post('/api/v1/auth/refresh', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Geçersiz token.', 401);
    }

    // Generate new token
    $newToken = JWT::create([
        'user_id' => $payload['user_id'],
        'username' => $payload['username'],
        'role' => $payload['role'] ?? 'admin',
    ]);

    json_success([
        'token' => $newToken,
        'expires_in' => config('security.jwt.expiration', 3600),
    ], 'Token yenilendi.');
});

/**
 * POST /api/v1/auth/logout
 * Çıkış yap (token invalidation için)
 */
$router->post('/api/v1/auth/logout', function() {
    $payload = JWT::requireAuth();

    if ($payload) {
        SecurityAudit::logLogout($payload['user_id']);
    }

    // Note: JWT'ler stateless olduğu için server-side invalidation için
    // blacklist sistemi gerekir (Redis/DB). Şimdilik sadece log atıyoruz.

    json_success(null, 'Çıkış yapıldı.');
});

/**
 * GET /api/v1/auth/me
 * Mevcut kullanıcı bilgileri
 */
$router->get('/api/v1/auth/me', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $kullanici = db()->fetch(
        "SELECT id, kullanici_adi, email, rol, created_at, last_login_at
         FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    if (!$kullanici) {
        json_error('Kullanıcı bulunamadı.', 404);
    }

    json_success(['user' => $kullanici]);
});

/**
 * POST /api/v1/auth/change-password
 * Şifre değiştir
 */
$router->post('/api/v1/auth/change-password', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['mevcut_sifre']) || empty($data['yeni_sifre'])) {
        json_error('Mevcut şifre ve yeni şifre zorunludur.', 422);
    }

    // Get current user
    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    // Verify current password
    if (!password_verify($data['mevcut_sifre'], $kullanici['sifre'])) {
        json_error('Mevcut şifreniz hatalı.', 401);
    }

    // Validate new password
    $validation = PasswordPolicy::validate($data['yeni_sifre'], $kullanici['kullanici_adi']);
    if (!$validation['valid']) {
        json_error('Yeni şifre geçersiz.', 422, ['errors' => $validation['errors']]);
    }

    // Check password history
    if (PasswordPolicy::isInHistory($data['yeni_sifre'], $payload['user_id'])) {
        json_error('Bu şifreyi daha önce kullandınız. Lütfen farklı bir şifre seçin.', 422);
    }

    // Hash and update password
    $hashedPassword = password_hash($data['yeni_sifre'], PASSWORD_ARGON2ID);

    db()->update('admin_kullanicilar', [
        'sifre' => $hashedPassword,
    ], 'id = :id', ['id' => $payload['user_id']]);

    // Add to password history
    PasswordPolicy::addToHistory($hashedPassword, $payload['user_id']);

    // Log password change
    SecurityAudit::logPasswordChange($payload['user_id']);

    json_success(null, 'Şifreniz başarıyla değiştirildi.');
});

/**
 * POST /api/v1/auth/2fa/enable
 * 2FA'yı etkinleştir
 */
$router->post('/api/v1/auth/2fa/enable', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    // Check if already enabled
    if (!empty($kullanici['two_factor_secret'])) {
        json_error('İki faktörlü doğrulama zaten etkin.', 400);
    }

    // Generate secret
    $secret = TwoFactorAuth::generateSecret();

    // Generate provisioning URI
    $uri = TwoFactorAuth::getProvisioningUri(
        $secret,
        $kullanici['kullanici_adi'],
        config('app.name', 'Tatlı Düşler')
    );

    // Store secret temporarily (needs verification before permanent save)
    session_put('2fa_temp_secret', $secret);

    json_success([
        'secret' => $secret,
        'qr_uri' => $uri,
        'message' => 'Lütfen uygulamanıza ekleyip doğrulama kodu ile onaylayın.',
    ]);
});

/**
 * POST /api/v1/auth/2fa/verify
 * 2FA kurulumunu doğrula ve etkinleştir
 */
$router->post('/api/v1/auth/2fa/verify', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['code'])) {
        json_error('Doğrulama kodu zorunludur.', 422);
    }

    // Get temp secret
    $tempSecret = session('2fa_temp_secret');
    if (!$tempSecret) {
        json_error('Önce 2FA kurulumunu başlatın.', 400);
    }

    // Verify code
    if (!TwoFactorAuth::verify($tempSecret, $data['code'])) {
        json_error('Geçersiz doğrulama kodu.', 401);
    }

    // Save secret permanently
    db()->update('admin_kullanicilar', [
        'two_factor_secret' => $tempSecret,
    ], 'id = :id', ['id' => $payload['user_id']]);

    // Clear temp secret
    session_forget('2fa_temp_secret');

    // Log 2FA enabled
    SecurityAudit::log2FAChange($payload['user_id'], true);

    json_success(null, 'İki faktörlü doğrulama etkinleştirildi.');
});

/**
 * POST /api/v1/auth/2fa/disable
 * 2FA'yı devre dışı bırak
 */
$router->post('/api/v1/auth/2fa/disable', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['sifre'])) {
        json_error('Şifrenizi onaylayın.', 422);
    }

    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    // Verify password
    if (!password_verify($data['sifre'], $kullanici['sifre'])) {
        json_error('Şifreniz hatalı.', 401);
    }

    // Disable 2FA
    db()->update('admin_kullanicilar', [
        'two_factor_secret' => null,
    ], 'id = :id', ['id' => $payload['user_id']]);

    // Log 2FA disabled
    SecurityAudit::log2FAChange($payload['user_id'], false);

    json_success(null, 'İki faktörlü doğrulama devre dışı bırakıldı.');
});
