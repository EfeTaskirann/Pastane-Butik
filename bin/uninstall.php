<?php
declare(strict_types=1);

/**
 * Pastane — Uninstall Script
 *
 * KULLANIM (CLI):
 *   php bin/uninstall.php --confirm
 *   php bin/uninstall.php --confirm --keep-uploads --keep-env
 *
 * YAPTIĞI İŞ:
 *   1. Tüm proje tablolarını DROP eder (FK check disabled → drop → FK enabled)
 *   2. uploads/ altındaki kullanıcı dosyalarını siler (.gitkeep / .htaccess korunur)
 *   3. storage/logs/*.log temizler (kendi log'u hariç)
 *   4. .env silinir
 *   5. storage/cache/ içeriği temizlenir
 *
 * GÜVENLİK:
 *   - Sadece CLI ortamında çalışır (PHP_SAPI guard)
 *   - --confirm flag zorunlu, exit kodu ≠ 0 aksi halde
 *   - Log dosyası uninstall sonrası KORUNUR: storage/logs/uninstall-<timestamp>.log
 *
 * EXIT CODES:
 *   0 = başarılı
 *   1 = --confirm eksik / CLI değil
 *   2 = bağlantı hatası
 *   3 = kısmî hata (bazı adımlar başarısız, log'a bakın)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Bu script sadece CLI üzerinden çalışır.\n";
    exit(1);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

// ---- Argüman parse ----
$args = array_slice($argv, 1);
$confirmed = in_array('--confirm', $args, true);
$keepUploads = in_array('--keep-uploads', $args, true);
$keepEnv = in_array('--keep-env', $args, true);
$keepLogs = in_array('--keep-logs', $args, true);
$verbose = in_array('--verbose', $args, true) || in_array('-v', $args, true);

if (!$confirmed) {
    fwrite(STDERR, <<<TXT
UYARI: Bu işlem GERİ DÖNDÜRÜLEMEZ.
  - Tüm veritabanı tabloları silinir
  - uploads/, storage/logs/, storage/cache/ temizlenir
  - .env silinir

Çalıştırmak için:
  php bin/uninstall.php --confirm

Opsiyonel bayraklar:
  --keep-uploads   uploads/ içeriğini silme
  --keep-env       .env dosyasını silme
  --keep-logs      storage/logs/ temizleme
  --verbose        ayrıntılı çıktı

TXT);
    exit(1);
}

// ---- Log kurulumu ----
$logDir = $rootPath . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/uninstall-' . date('Ymd-His') . '.log';
$logStart = microtime(true);

$log = function (string $level, string $msg) use ($logFile, $verbose) {
    $line = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), strtoupper($level), $msg);
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    if ($verbose || $level === 'ERROR' || $level === 'WARN' || $level === 'INFO') {
        fwrite($level === 'ERROR' ? STDERR : STDOUT, $line . PHP_EOL);
    }
};

$log('INFO', 'Uninstall started');
$log('INFO', 'Root: ' . $rootPath);
$log('INFO', sprintf(
    'Flags: keep-uploads=%d keep-env=%d keep-logs=%d',
    (int) $keepUploads, (int) $keepEnv, (int) $keepLogs
));

$exitCode = 0;

// ---- 1. Veritabanı: tabloları drop et ----
try {
    require_once $rootPath . '/includes/bootstrap.php';

    $db = \Database::getInstance();
    $pdo = $db->getPdo();

    // ALLOWED_TABLES'ı reflection ile al (private const, PHP 8.1+ getConstant private erişimi verir)
    $refl = new ReflectionClass('Database');
    $allowedTables = [];
    foreach ($refl->getReflectionConstants() as $const) {
        if ($const->getName() === 'ALLOWED_TABLES') {
            $allowedTables = $const->getValue();
            break;
        }
    }

    if (!is_array($allowedTables) || count($allowedTables) === 0) {
        throw new RuntimeException('ALLOWED_TABLES boş — Database sınıfı güncel mi?');
    }

    $log('INFO', 'Drop table sayısı: ' . count($allowedTables));

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $dropped = 0;
    $failed = 0;

    foreach ($allowedTables as $table) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            $dropped++;
            $log('INFO', 'DROP TABLE ' . $table . ' OK');
        } catch (Throwable $e) {
            $failed++;
            $log('ERROR', 'DROP TABLE ' . $table . ' FAIL: ' . $e->getMessage());
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $log('INFO', "Tables dropped: $dropped, failed: $failed");

    if ($failed > 0) {
        $exitCode = 3;
    }
} catch (Throwable $e) {
    $log('ERROR', 'DB bağlantısı / drop hatası: ' . $e->getMessage());
    $exitCode = 2;
}

// ---- 2. uploads/ temizle ----
if (!$keepUploads) {
    $uploadsPath = $rootPath . '/uploads';
    if (is_dir($uploadsPath)) {
        $cleared = clearDirectory($uploadsPath, ['.gitkeep', '.htaccess'], $log);
        $log('INFO', "uploads/ temizlendi: $cleared dosya/klasör silindi");
    }
}

// ---- 3. storage/cache/ temizle ----
$cachePath = $rootPath . '/storage/cache';
if (is_dir($cachePath)) {
    $cleared = clearDirectory($cachePath, ['.gitkeep'], $log);
    $log('INFO', "storage/cache/ temizlendi: $cleared dosya silindi");
}

// ---- 4. storage/logs/ temizle (uninstall log hariç) ----
if (!$keepLogs) {
    $protectedLogs = [basename($logFile), '.gitkeep'];
    $cleared = clearDirectory($logDir, $protectedLogs, $log);
    $log('INFO', "storage/logs/ temizlendi: $cleared dosya silindi");
}

// ---- 5. .env sil ----
if (!$keepEnv) {
    $envFile = $rootPath . '/.env';
    if (file_exists($envFile)) {
        if (@unlink($envFile)) {
            $log('INFO', '.env silindi');
        } else {
            $log('ERROR', '.env silinemedi (dosya kilitli mi?)');
            $exitCode = 3;
        }
    } else {
        $log('INFO', '.env zaten yok');
    }
}

// ---- Sonuç ----
$duration = round(microtime(true) - $logStart, 3);
$log('INFO', "Uninstall completed in {$duration}s. Exit code: $exitCode");
fwrite(STDOUT, PHP_EOL);
fwrite(STDOUT, "Uninstall tamamlandı. Log: $logFile\n");
fwrite(STDOUT, "Exit code: $exitCode\n");

exit($exitCode);

/**
 * Bir dizinin içeriğini siler, istisnalar parametresiyle belirtilen dosya/klasörler korunur.
 * Alt klasörleri recursive olarak siler.
 */
function clearDirectory(string $dir, array $preserve, callable $log): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        $name = $file->getBasename();
        if (in_array($name, $preserve, true)) {
            continue;
        }

        $path = $file->getPathname();

        try {
            if ($file->isDir()) {
                // Boş hale gelmediyse atla
                if (count(scandir($path)) > 2) {
                    continue;
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
            $count++;
        } catch (Throwable $e) {
            $log('WARN', "Silinemedi: $path — " . $e->getMessage());
        }
    }

    return $count;
}
