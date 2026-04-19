<?php
/**
 * bin/cron/log-rotate.php — Log rotation cron script
 *
 * Amaç:
 *   `storage/logs/` altındaki *.log dosyalarını kontrol eder.
 *   10 MB'dan büyük olan dosyaları rotate eder (.1, .2, ..., maks 7).
 *   Rotasyondan sonra .gz olarak sıkıştırır.
 *   7 günden eski rotated (.gz) dosyaları siler.
 *
 * Kullanım:
 *   php bin/cron/log-rotate.php             # standart
 *   php bin/cron/log-rotate.php --dry-run   # sadece raporla
 *   php bin/cron/log-rotate.php --threshold=5   # 5 MB eşiği
 *   php bin/cron/log-rotate.php --retention=14  # 14 gün tut
 *
 * Cron örneği (Linux — her saat başı):
 *   0 * * * * cd /var/www/pastane && /usr/bin/php bin/cron/log-rotate.php >> storage/logs/cron.log 2>&1
 *
 * Windows Task Scheduler:
 *   1) Görev oluştur → Günlük/Saatlik tetik
 *   2) Program: C:\xampp\php\php.exe
 *   3) Argüman: C:\xampp\htdocs\pastane\bin\cron\log-rotate.php
 *   4) Başlangıç yeri: C:\xampp\htdocs\pastane
 *
 * Exit codes:
 *   0 = başarı (rotate yapıldı veya gerek yoktu)
 *   1 = I/O hatası (klasör yok, yazılabilir değil)
 *   2 = argüman hatası
 *
 * @package Pastane\Cron
 * @since 1.1.0-sprint1
 */

if (PHP_SAPI !== 'cli') {
    echo "Bu script yalnızca CLI üzerinden çalıştırılmalıdır.\n";
    exit(2);
}

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/includes/bootstrap.php';

// Varsayılanlar
$DEFAULT_THRESHOLD_MB = 10;
$DEFAULT_RETENTION_DAYS = 7;
$MAX_ROTATED_INDEX = 7;

$dryRun = false;
$thresholdMb = $DEFAULT_THRESHOLD_MB;
$retentionDays = $DEFAULT_RETENTION_DAYS;

// Arg parse
foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--threshold=(\d+)$/', $arg, $m)) {
        $thresholdMb = max(1, (int) $m[1]);
    } elseif (preg_match('/^--retention=(\d+)$/', $arg, $m)) {
        $retentionDays = max(1, (int) $m[1]);
    } elseif ($arg !== $argv[0] && !in_array($arg, ['--dry-run'], true) && !str_starts_with($arg, '--')) {
        // Bilinmeyen arg
    }
}

$thresholdBytes = $thresholdMb * 1024 * 1024;
$logDir = $baseDir . DIRECTORY_SEPARATOR . (function_exists('env') ? (string) env('LOG_PATH', 'storage/logs') : 'storage/logs');

$now = date('c');
$prefix = "[log-rotate] [{$now}]";

echo "{$prefix} start — dir={$logDir} threshold={$thresholdMb}MB retention={$retentionDays}d" . ($dryRun ? ' (DRY RUN)' : '') . PHP_EOL;

if (!is_dir($logDir)) {
    echo "{$prefix} HATA: log dizini yok: {$logDir}\n";
    exit(1);
}

if (!is_readable($logDir)) {
    echo "{$prefix} HATA: log dizini okunamıyor: {$logDir}\n";
    exit(1);
}

/**
 * Dosyayı rotate et — .1, .2, ... şeklinde kaydır.
 * Mevcut .1 varsa → .2'ye, .2 → .3 ... maks {$MAX_ROTATED_INDEX}, aşanlar silinir.
 */
function pastane_rotate_file(string $file, int $max, bool $dryRun): bool
{
    // Üstten aşağıya kaydır: N → N+1
    for ($i = $max; $i >= 1; $i--) {
        $from = $file . '.' . $i;
        $to   = $file . '.' . ($i + 1);
        $fromGz = $from . '.gz';
        $toGz   = $to . '.gz';

        if (file_exists($fromGz)) {
            if ($i >= $max) {
                // En eski olan silinir
                if (!$dryRun) {
                    @unlink($fromGz);
                }
                echo "  purge: {$fromGz}\n";
            } else {
                if (!$dryRun) {
                    @rename($fromGz, $toGz);
                }
                echo "  move:  {$fromGz} → {$toGz}\n";
            }
        } elseif (file_exists($from)) {
            if ($i >= $max) {
                if (!$dryRun) {
                    @unlink($from);
                }
                echo "  purge: {$from}\n";
            } else {
                if (!$dryRun) {
                    @rename($from, $to);
                }
                echo "  move:  {$from} → {$to}\n";
            }
        }
    }

    // Canlı dosyayı .1'e al
    $dot1 = $file . '.1';
    if (!$dryRun) {
        if (!@rename($file, $dot1)) {
            echo "  HATA: {$file} rotate edilemedi\n";
            return false;
        }
        // Yeni boş dosya oluştur (son izinlerle)
        @file_put_contents($file, '');
        @chmod($file, 0664);
    }
    echo "  move:  {$file} → {$dot1}\n";

    // .gz'e sıkıştır (sadece .1)
    if (!$dryRun) {
        if (function_exists('gzopen')) {
            $src = @fopen($dot1, 'rb');
            $dst = @gzopen($dot1 . '.gz', 'wb9');
            if ($src && $dst) {
                while (!feof($src)) {
                    $chunk = fread($src, 65536);
                    if ($chunk === false) {
                        break;
                    }
                    gzwrite($dst, $chunk);
                }
                fclose($src);
                gzclose($dst);
                @unlink($dot1);
                echo "  gzip:  {$dot1} → {$dot1}.gz\n";
            } else {
                echo "  UYARI: gzip başarısız — sıkıştırılmadan bırakıldı\n";
            }
        } else {
            echo "  UYARI: gz extension yok — sıkıştırılmadan bırakıldı\n";
        }
    } else {
        echo "  gzip:  (dry) {$dot1} → {$dot1}.gz\n";
    }

    return true;
}

/**
 * Retention — N günden eski rotated dosyaları sil.
 */
function pastane_clean_old(string $dir, int $days, bool $dryRun): int
{
    $count = 0;
    $cutoff = time() - ($days * 86400);
    $patterns = ['*.log.*', '*.log.*.gz'];
    foreach ($patterns as $pat) {
        $files = glob($dir . DIRECTORY_SEPARATOR . $pat) ?: [];
        foreach ($files as $f) {
            if (!is_file($f)) {
                continue;
            }
            if (filemtime($f) < $cutoff) {
                echo "  expire: {$f} (mtime " . date('Y-m-d', filemtime($f)) . ")\n";
                if (!$dryRun) {
                    @unlink($f);
                }
                $count++;
            }
        }
    }
    return $count;
}

// Ana döngü
$rotatedCount = 0;
$skippedCount = 0;
$logs = glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [];

foreach ($logs as $log) {
    if (!is_file($log)) {
        continue;
    }
    $size = filesize($log) ?: 0;
    $sizeMb = round($size / 1024 / 1024, 2);

    if ($size < $thresholdBytes) {
        echo "  skip:  " . basename($log) . " ({$sizeMb}MB < {$thresholdMb}MB)\n";
        $skippedCount++;
        continue;
    }

    echo "  rotate:" . basename($log) . " ({$sizeMb}MB ≥ {$thresholdMb}MB)\n";
    if (pastane_rotate_file($log, $MAX_ROTATED_INDEX, $dryRun)) {
        $rotatedCount++;
    }
}

$expired = pastane_clean_old($logDir, $retentionDays, $dryRun);

echo "{$prefix} done — rotated={$rotatedCount} skipped={$skippedCount} expired={$expired}" . ($dryRun ? ' (DRY RUN)' : '') . PHP_EOL;
exit(0);
