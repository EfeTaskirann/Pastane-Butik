<?php
/**
 * Authentication API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Validators\AuthValidator;

$router = Router::getInstance();

/**
 * POST /api/v1/auth/login
 * Admin girişi
 */
$router->post('/api/v1/auth/login', function() {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new AuthValidator('login');
    $validated = $validator->validate($data);

    // Rate limit check
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateLimitResult = RateLimiter::check('login', $ip);

    if (!$rateLimitResult['allowed']) {
        SecurityAudit::log(SecurityAudit::RATE_LIMIT_EXCEEDED, null, [
            'action' => 'login',
            'username' => $validated['kullanici_adi'],
        ]);

        json_error('Çok fazla başarısız deneme. Lütfen bekleyin.', 429);
    }

    // Find user
    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE kullanici_adi = ?",
        [$validated['kullanici_adi']]
    );

    if (!$kullanici) {
        RateLimiter::hit('login', $ip);
        SecurityAudit::logFailedLogin($validated['kullanici_adi'], 'user_not_found');
        json_error('Geçersiz kullanıcı adı veya şifre.', 401);
    }

    // Check password (kolon adı: sifre_hash)
    if (!password_verify($validated['sifre'], $kullanici['sifre_hash'])) {
        RateLimiter::hit('login', $ip);
        SecurityAudit::logFailedLogin($validated['kullanici_adi'], 'invalid_password');
        json_error('Geçersiz kullanıcı adı veya şifre.', 401);
    }

    // Check if account is locked
    if (!empty($kullanici['locked_until']) && strtotime($kullanici['locked_until']) > time()) {
        json_error('Hesabınız geçici olarak kilitlendi. Lütfen daha sonra tekrar deneyin.', 403);
    }

    // Check if 2FA is enabled
    if (!empty($kullanici['two_factor_secret']) && empty($validated['two_factor_code'])) {
        json_response([
            'success' => false,
            'requires_2fa' => true,
            'message' => 'İki faktörlü doğrulama kodu gerekli.',
        ], 401);
    }

    // Verify 2FA code if provided
    if (!empty($kullanici['two_factor_secret']) && !empty($validated['two_factor_code'])) {
        if (!TwoFactorAuth::verify($kullanici['two_factor_secret'], $validated['two_factor_code'])) {
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
    // requireAuth() artık HttpException fırlatıyor — null check gereksiz
    $payload = JWT::requireAuth();

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
    $token = JWT::getTokenFromHeader();
    $payload = JWT::requireAuth();

    // Token'ı blacklist'e ekle (gerçek server-side invalidation)
    if ($token) {
        JWT::invalidate($token);
    }

    SecurityAudit::logLogout($payload['user_id']);

    json_success(null, 'Çıkış yapıldı.');
});

/**
 * GET /api/v1/auth/me
 * Mevcut kullanıcı bilgileri
 */
$router->get('/api/v1/auth/me', function() {
    $payload = JWT::requireAuth();

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

    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new AuthValidator('change_password');
    $validated = $validator->validate($data);

    // Get current user
    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    // Verify current password (kolon adı: sifre_hash)
    if (!password_verify($validated['mevcut_sifre'], $kullanici['sifre_hash'])) {
        json_error('Mevcut şifreniz hatalı.', 401);
    }

    // Validate new password with PasswordPolicy
    $policyValidation = PasswordPolicy::validate($validated['yeni_sifre'], $kullanici['kullanici_adi']);
    if (!$policyValidation['valid']) {
        json_error('Yeni şifre geçersiz.', 422, ['errors' => $policyValidation['errors']]);
    }

    // Check password history
    if (PasswordPolicy::isInHistory($validated['yeni_sifre'], $payload['user_id'])) {
        json_error('Bu şifreyi daha önce kullandınız. Lütfen farklı bir şifre seçin.', 422);
    }

    // Hash and update password
    $hashedPassword = password_hash($validated['yeni_sifre'], PASSWORD_ARGON2ID);

    db()->update('admin_kullanicilar', [
        'sifre_hash' => $hashedPassword,
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

    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new AuthValidator('2fa_verify');
    $validated = $validator->validate($data);

    // Get temp secret
    $tempSecret = session('2fa_temp_secret');
    if (!$tempSecret) {
        json_error('Önce 2FA kurulumunu başlatın.', 400);
    }

    // Verify code
    if (!TwoFactorAuth::verify($tempSecret, $validated['code'])) {
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

    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new AuthValidator('2fa_disable');
    $validated = $validator->validate($data);

    $kullanici = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE id = ?",
        [$payload['user_id']]
    );

    // Verify password (kolon adı: sifre_hash)
    if (!password_verify($validated['sifre'], $kullanici['sifre_hash'])) {
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
