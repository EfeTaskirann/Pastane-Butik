<?php

declare(strict_types=1);

/**
 * Masa Timeout Cron Job
 *
 * Belirli bir süre aktivitesiz kalan QR menü masa oturumlarını
 * otomatik olarak kapatır ve masaları boşa çıkarır.
 *
 * Çalıştırma (CLI):
 *   php bin/cron/masa-timeout.php [timeout_dakika]
 *
 * Parametre:
 *   timeout_dakika: Varsayılan 180 (3 saat). 1-1440 arası.
 *
 * Zamanlama önerileri:
 *   Linux (crontab -e):
 *     10 * * * * /usr/bin/php /path/to/pastane/bin/cron/masa-timeout.php >> /path/to/pastane/logs/cron-masa-timeout.log 2>&1
 *
 *   Windows (Task Scheduler):
 *     Program:    C:\xampp\php\php.exe
 *     Arguments:  C:\xampp\htdocs\pastane\bin\cron\masa-timeout.php
 *     Trigger:    Her saat başı 10'u (recurring)
 *
 * Exit code:
 *   0 = başarılı (kapatılan olmasa bile)
 *   1 = hata
 *
 * Log:
 *   storage/logs/cron-masa-timeout.log (FileLogger)
 *
 * @package Pastane\Cron
 * @since 1.1.0
 */

// Sadece CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Bu script sadece komut satırından çalıştırılabilir (CLI-only).\n";
    exit(1);
}

// Error handling — uncaught exception'larda exit 1
set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, '[masa-timeout] FATAL: ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
});

// Bootstrap
require_once __DIR__ . '/../../includes/bootstrap.php';

use Pastane\Repositories\MasaOturumRepository;

// Log yazıcı — basit file logger, storage/logs/ altına yazar
$logDir  = dirname(__DIR__, 2) . '/storage/logs';
$logFile = $logDir . '/cron-masa-timeout.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$logLine = function (string $level, string $msg) use ($logFile): void {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    // CLI stdout
    $stream = $level === 'ERROR' ? STDERR : STDOUT;
    fwrite($stream, $line);
};

// Parametre parse
$timeoutMinutes = 180;
if (isset($argv[1])) {
    $parsed = (int)$argv[1];
    if ($parsed >= 1 && $parsed <= 1440) {
        $timeoutMinutes = $parsed;
    } else {
        $logLine('WARN', "Geçersiz timeout parametresi '{$argv[1]}', varsayılan 180 kullanılıyor.");
    }
}

$startedAt = microtime(true);
$logLine('INFO', "Başladı — timeout={$timeoutMinutes} dk");

try {
    $repo   = new MasaOturumRepository();
    $result = $repo->closeInactiveSessions($timeoutMinutes);

    $elapsed = round((microtime(true) - $startedAt) * 1000, 2);

    $logLine('INFO', sprintf(
        'Tamamlandı — kapatılan_oturum=%d bosalan_masa=%d süre=%sms ids=[%s]',
        $result['closed_sessions'],
        $result['freed_tables'],
        $elapsed,
        implode(',', $result['session_ids'])
    ));

    exit(0);
} catch (\Throwable $e) {
    $logLine('ERROR', 'Cron başarısız: ' . $e->getMessage());
    if (function_exists('sentry_capture') && class_exists('Sentry')) {
        try {
            \Sentry::captureException($e);
        } catch (\Throwable) {
            // Sentry başarısız olsa bile cron hata kodu dönsün
        }
    }
    exit(1);
}
