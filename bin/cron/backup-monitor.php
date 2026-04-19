<?php
/**
 * bin/cron/backup-monitor.php — DB backup monitoring cron
 *
 * Amaç:
 *   `storage/backups/` altındaki son `backup-*.sql.gz` dosyasını kontrol eder:
 *     - Backend'in bin/cron/db-backup.php scripti çalışıyor mu?
 *     - Son backup ne kadar eski?
 *       * 25 saatten eski  → WARNING (backup zincirinde gecikme)
 *       * 48 saatten eski  → CRITICAL (backup ölü, acil eylem)
 *     - Boyut şüpheli mi?
 *       * < 100 KB → WARNING (DB boş veya corrupt dump)
 *   Bulguları `storage/logs/backup-monitor.log` dosyasına yazar ve
 *   Sentry aktifse `Sentry::captureMessage()` tetikler.
 *
 * Sprint 2 bağımlılığı:
 *   Backend agent `bin/cron/db-backup.php`'yi üretiyor. Script henüz yoksa
 *   WARNING yazıp exit 0 dönüyoruz — monitoring her zaman çalışır durumda.
 *
 * Kullanım:
 *   php bin/cron/backup-monitor.php
 *   php bin/cron/backup-monitor.php --dry-run
 *   php bin/cron/backup-monitor.php --warn=25 --crit=48 --min-size-kb=100
 *
 * Cron örneği (Linux — her 6 saatte bir):
 *   0 [slash6] * * * cd /var/www/pastane && /usr/bin/php bin/cron/backup-monitor.php >> storage/logs/cron.log 2>&1
 *   (not: [slash6] yerine gerçek crontab'da '\*​/6' yazın — docblock içinde kapalı yıldız-slash
 *    dizisi yorum bloğunu kapatacağı için placeholder kullandık.)
 *
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Argüman: C:\xampp\htdocs\pastane\bin\cron\backup-monitor.php
 *   Başlangıç yeri: C:\xampp\htdocs\pastane
 *   Trigger: her 6 saatte bir
 *
 * Exit codes:
 *   0 = OK (monitoring tamamlandı, alarmlar loglandı)
 *   1 = I/O hatası (log dizini yazılamıyor)
 *   2 = argüman hatası
 *
 * @package Pastane\Cron
 * @since   1.2.0-sprint2
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "Bu script yalnızca CLI üzerinden çalıştırılmalıdır.\n";
    exit(2);
}

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, '[backup-monitor] FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
});

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/includes/bootstrap.php';

// Varsayılan eşikler
$WARN_HOURS     = 25;
$CRIT_HOURS     = 48;
$MIN_SIZE_KB    = 100;
$dryRun         = false;

foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--warn=(\d+)$/', $arg, $m)) {
        $WARN_HOURS = max(1, (int) $m[1]);
    } elseif (preg_match('/^--crit=(\d+)$/', $arg, $m)) {
        $CRIT_HOURS = max($WARN_HOURS, (int) $m[1]);
    } elseif (preg_match('/^--min-size-kb=(\d+)$/', $arg, $m)) {
        $MIN_SIZE_KB = max(1, (int) $m[1]);
    }
}

$logDir    = $baseDir . DIRECTORY_SEPARATOR . (function_exists('env') ? (string) env('LOG_PATH', 'storage/logs') : 'storage/logs');
$logFile   = $logDir . DIRECTORY_SEPARATOR . 'backup-monitor.log';
$backupDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
$scriptPath = $baseDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'db-backup.php';

if (!is_dir($logDir) && !@mkdir($logDir, 0775, true)) {
    fwrite(STDERR, "[backup-monitor] HATA: log dizini oluşturulamadı: {$logDir}\n");
    exit(1);
}

/**
 * Log yaz — LOCK_EX | FILE_APPEND, Sentry'ye de push et.
 *
 * @param string               $level   INFO|WARNING|CRITICAL
 * @param string               $message İnsan okur mesaj
 * @param array<string, mixed> $context Ek bilgi
 */
function backup_monitor_log(string $level, string $message, array $context, string $logFile, bool $dryRun): void
{
    $entry = sprintf(
        "[%s] %-8s %s %s\n",
        date('c'),
        $level,
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );

    echo $entry;

    if (!$dryRun) {
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    // Sentry entegrasyonu (varsa)
    if (!$dryRun && ($level === 'WARNING' || $level === 'CRITICAL')) {
        try {
            if (class_exists('Sentry', false) && Sentry::isEnabled()) {
                $sentryLevel = $level === 'CRITICAL' ? 'fatal' : 'warning';
                Sentry::captureMessage("[backup-monitor] {$message}", $sentryLevel, $context);
            }
        } catch (Throwable) {
            // Sentry push sessizce fail — monitoring ana görevi engellemesin
        }
    }
}

$now = time();
backup_monitor_log('INFO', 'start', [
    'backup_dir'   => $backupDir,
    'warn_hours'   => $WARN_HOURS,
    'crit_hours'   => $CRIT_HOURS,
    'min_size_kb'  => $MIN_SIZE_KB,
    'dry_run'      => $dryRun,
], $logFile, false);

// 1) db-backup script mevcut mu?
if (!file_exists($scriptPath)) {
    backup_monitor_log(
        'WARNING',
        'db-backup.php bulunamadı — backend cron script henüz eklenmemiş',
        ['expected_path' => $scriptPath],
        $logFile,
        $dryRun
    );
}

// 2) Backup dizini var mı?
if (!is_dir($backupDir)) {
    backup_monitor_log(
        'CRITICAL',
        'Backup dizini yok — hiçbir yedek alınmamış',
        ['backup_dir' => $backupDir],
        $logFile,
        $dryRun
    );
    backup_monitor_log('INFO', 'done', ['result' => 'no_backup_dir'], $logFile, false);
    exit(0);
}

// 3) Son backup dosyasını bul
$pattern = $backupDir . DIRECTORY_SEPARATOR . 'backup-*.sql.gz';
$files   = glob($pattern) ?: [];

// Unsafe fallback: .sql (henüz gzip'lenmemiş)
if (!$files) {
    $files = glob($backupDir . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [];
}

if (!$files) {
    backup_monitor_log(
        'CRITICAL',
        'storage/backups/ içinde hiç backup-*.sql(.gz) bulunamadı',
        ['pattern' => $pattern],
        $logFile,
        $dryRun
    );
    backup_monitor_log('INFO', 'done', ['result' => 'no_backups'], $logFile, false);
    exit(0);
}

// En yeni dosya (mtime'a göre)
usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
$latest      = $files[0];
$latestMtime = (int) filemtime($latest);
$latestSize  = (int) @filesize($latest);
$ageSeconds  = $now - $latestMtime;
$ageHours    = round($ageSeconds / 3600, 2);
$sizeKb      = round($latestSize / 1024, 2);

$context = [
    'latest_file' => basename($latest),
    'age_hours'   => $ageHours,
    'size_kb'     => $sizeKb,
    'mtime'       => date('c', $latestMtime),
];

// 4) Yaş kontrolü
if ($ageHours >= $CRIT_HOURS) {
    backup_monitor_log(
        'CRITICAL',
        "Son backup {$ageHours} saat eski — CRIT eşiği ({$CRIT_HOURS}h) aşıldı",
        $context,
        $logFile,
        $dryRun
    );
} elseif ($ageHours >= $WARN_HOURS) {
    backup_monitor_log(
        'WARNING',
        "Son backup {$ageHours} saat eski — WARN eşiği ({$WARN_HOURS}h) aşıldı",
        $context,
        $logFile,
        $dryRun
    );
} else {
    backup_monitor_log(
        'INFO',
        "Son backup {$ageHours} saat eski — taze",
        $context,
        $logFile,
        $dryRun
    );
}

// 5) Boyut kontrolü
if ($sizeKb < $MIN_SIZE_KB) {
    backup_monitor_log(
        'WARNING',
        "Son backup boyutu {$sizeKb} KB — min eşik ({$MIN_SIZE_KB} KB) altında (DB boş veya dump corrupt olabilir)",
        $context,
        $logFile,
        $dryRun
    );
}

backup_monitor_log('INFO', 'done', ['result' => 'checked', 'files_total' => count($files)], $logFile, false);
exit(0);
