<?php
/**
 * Security Configuration
 *
 * @package Pastane
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'lifetime' => (int) env('SESSION_LIFETIME', 3600),
        'expire_on_close' => false,
        'encrypt' => false,
        'cookie' => 'pastane_session',
        'path' => '/',
        'domain' => null,
        'secure' => env('APP_ENV') === 'production',
        'http_only' => true,
        'same_site' => 'Strict',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_lifetime' => (int) env('CSRF_TOKEN_LIFETIME', 3600),
        'cookie_name' => 'XSRF-TOKEN',
        'header_name' => 'X-CSRF-TOKEN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Brute Force Protection
    |--------------------------------------------------------------------------
    */
    'login' => [
        'max_attempts' => (int) env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_time' => (int) env('LOGIN_LOCKOUT_TIME', 900),
        'decay_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'max_age_days' => 90,
        'history_count' => 5, // Kaç eski şifre hatırlansın
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled' => true,
        'issuer' => env('APP_NAME', 'Pastane'),
        'algorithm' => 'sha1',
        'digits' => 6,
        'period' => 30,
        'window' => 1, // Tolerance
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => true,
        'requests' => (int) env('RATE_LIMIT_REQUESTS', 60),
        'window' => (int) env('RATE_LIMIT_WINDOW', 60),

        // Endpoint bazlı limitler
        'endpoints' => [
            'login' => ['requests' => 5, 'window' => 60],
            'contact' => ['requests' => 3, 'window' => 60],
            'api' => ['requests' => 100, 'window' => 60],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Security Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        // NOT: CSP header'ları includes/security.php setSecurityHeaders() tarafından
        // nonce-based olarak dinamik uygulanır. Bu config sadece referans amaçlıdır.
        // Gerçek CSP policy → setSecurityHeaders() içindeki getCspNonce() ile üretilir.
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{DYNAMIC}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS Configuration
    |--------------------------------------------------------------------------
    */
    'https' => [
        'force' => env('APP_ENV') === 'production',
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000, // 1 year
            'include_subdomains' => true,
            'preload' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_size' => (int) env('UPLOAD_MAX_SIZE', 5242880), // 5MB
        'allowed_extensions' => explode(',', env('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,webp')),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'path' => '/uploads/products/',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Blocking
    |--------------------------------------------------------------------------
    */
    'ip_blocking' => [
        'enabled' => true,
        'whitelist' => [
            '127.0.0.1',
            '::1',
        ],
        'blacklist' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'log_login' => true,
        'log_logout' => true,
        'log_failed_login' => true,
        'log_password_change' => true,
        'log_data_changes' => true,
        'retention_days' => 90,
    ],
];
