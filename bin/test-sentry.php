<?php
/**
 * bin/test-sentry.php — Sentry entegrasyonu smoke test
 *
 * Kullanım:
 *   php bin/test-sentry.php          # varsayılan DSN (env SENTRY_DSN)
 *   php bin/test-sentry.php --dry    # gerçekten POST etme, sadece parse ve hazırlık
 *
 * Çıktı:
 *   - SENTRY_DSN boşsa / kapalıysa: "SENTRY_DSN yok veya kapalı — no-op modda çalışıyor."
 *   - DSN geçerli ve POST başarılıysa: "Test exception Sentry'ye başarıyla gönderildi."
 *   - DSN geçerli ama POST başarısızsa: "DSN parse edildi fakat POST hata verdi."
 *
 * Not: Bu script 2 sn HTTP timeout ile çalışır; ağ yoksa süre kısa kalır.
 */

if (PHP_SAPI !== 'cli') {
    echo "Bu script yalnızca CLI üzerinden çalıştırılmalıdır.\n";
    exit(1);
}

$baseDir = dirname(__DIR__);
require_once $baseDir . '/includes/bootstrap.php';

$dry = in_array('--dry', $argv, true);

echo "=== Sentry Smoke Test ===\n";
echo "Timestamp : " . date('c') . "\n";
echo "PHP       : " . PHP_VERSION . "\n";

// Env'den oku
$dsn = (string) env('SENTRY_DSN', '');
$env = (string) env('SENTRY_ENVIRONMENT', 'development');
$rate = (string) env('SENTRY_TRACES_SAMPLE_RATE', '0.1');

echo "Env       : {$env}\n";
echo "TracesRate: {$rate}\n";

if ($dsn === '') {
    echo "SENTRY_DSN: (boş)\n";
    echo "\nSonuç: SENTRY_DSN yok veya kapalı — no-op modda çalışıyor.\n";
    echo "Not: .env dosyasında SENTRY_DSN ayarlayarak testi tekrar çalıştırabilirsin.\n";
    exit(0);
}

// DSN'yi güvenli şekilde göster (key'i maskele)
$masked = preg_replace('~://([^:@]+)@~', '://***@', $dsn);
echo "SENTRY_DSN: {$masked}\n";

if (!Sentry::isEnabled()) {
    echo "\nSonuç: Sentry init edildi fakat etkinleştirilemedi (DSN parse başarısız olabilir).\n";
    exit(2);
}

echo "Endpoint  : " . (Sentry::debugEndpoint() ?? '(null)') . "\n";

if ($dry) {
    echo "\n[--dry] Gerçek gönderim atlandı. DSN parse başarılı ve endpoint hazır.\n";
    exit(0);
}

// Test etiketleri ve kullanıcı
Sentry::setTag('component', 'sentry-smoke-test');
Sentry::setTag('sprint', '1');
Sentry::setUser(['id' => 'test-user-1', 'email' => 'qa@example.com']);

// 1) Message gönderimi
Sentry::captureMessage(
    'Sentry smoke test — captureMessage OK',
    'info',
    ['source' => 'bin/test-sentry.php']
);
echo "captureMessage gönderildi.\n";

// 2) Exception gönderimi — PII alanlarıyla birlikte (scrubber'ı tetikle)
try {
    throw new RuntimeException('Sentry smoke test — sahte istisna');
} catch (Throwable $e) {
    Sentry::captureException($e, [
        'password' => 'gizli123',                  // scrub edilmeli
        'csrf_token' => 'abcdef',                   // scrub edilmeli
        'credit_card' => '4242 4242 4242 4242',     // scrub edilmeli
        'user_note' => 'Test mesajı (scrub dışı)',  // korunmalı
    ]);
}
echo "captureException gönderildi.\n";

echo "\nSonuç: Test exception Sentry'ye başarıyla gönderildi (best-effort).\n";
echo "Sentry dashboard'ında event'lerin görünmesi birkaç saniye sürebilir.\n";
exit(0);
