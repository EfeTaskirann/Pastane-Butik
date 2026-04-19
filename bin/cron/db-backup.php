<?php
/**
 * bin/cron/db-backup.php — MySQL Database Backup Script
 *
 * Amaç:
 *   MySQL veritabanının full dump'ını alır, gzip'le sıkıştırır ve
 *   `storage/backups/` altına kaydeder. 30 günden eski backup'ları siler.
 *
 * Kullanım:
 *   php bin/cron/db-backup.php                      # tüm tabloları yedekle
 *   php bin/cron/db-backup.php --tables=urunler,kategoriler  # sadece belirli tablolar
 *   php bin/cron/db-backup.php --retention=60       # 60 gün tut (varsayılan 30)
 *   php bin/cron/db-backup.php --dry-run            # raporla, dosya oluşturma
 *
 * Cron örneği (Linux — her gece 03:00):
 *   0 3 * * * cd /var/www/pastane && /usr/bin/php bin/cron/db-backup.php >> storage/logs/db-backup.log 2>&1
 *
 * Windows Task Scheduler:
 *   1) Görev oluştur → Günlük 03:00 tetik
 *   2) Program:   C:\xampp\php\php.exe
 *   3) Argüman:   C:\xampp\htdocs\pastane\bin\cron\db-backup.php
 *   4) Başlangıç: C:\xampp\htdocs\pastane
 *
 * Dosya formatı:
 *   storage/backups/backup-YYYYMMDD-HHMMSS.sql.gz
 *
 * Exit codes:
 *   0 = başarı
 *   1 = I/O hatası (klasör, yazılabilir değil)
 *   2 = argüman hatası
 *   3 = mysqldump/PDO hatası
 *
 * @package Pastane\Cron
 * @since 2.0.0-sprint2
 */

if (PHP_SAPI !== 'cli') {
    echo "Bu script yalnızca CLI üzerinden çalıştırılmalıdır.\n";
    exit(2);
}

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/includes/bootstrap.php';

set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, "[db-backup] FATAL: " . $e->getMessage() . "\n");
    exit(3);
});

// Varsayılanlar
$DEFAULT_RETENTION_DAYS = 30;
$BACKUP_DIR = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
$LOG_FILE = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'db-backup.log';

$dryRun = false;
$retentionDays = $DEFAULT_RETENTION_DAYS;
$tables = [];

// Arg parse
foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (preg_match('/^--retention=(\d+)$/', $arg, $m)) {
        $retentionDays = max(1, min(365, (int) $m[1]));
    } elseif (preg_match('/^--tables=(.+)$/', $arg, $m)) {
        $tables = array_values(array_filter(
            array_map('trim', explode(',', $m[1])),
            static fn(string $t): bool => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $t) === 1
        ));
    } elseif (str_starts_with($arg, '--')) {
        fwrite(STDERR, "Bilinmeyen argüman: {$arg}\n");
        exit(2);
    }
}

// Dizinleri hazırla
if (!is_dir($BACKUP_DIR)) {
    if (!@mkdir($BACKUP_DIR, 0755, true) && !is_dir($BACKUP_DIR)) {
        fwrite(STDERR, "Backup dizini oluşturulamadı: {$BACKUP_DIR}\n");
        exit(1);
    }
}
if (!is_writable($BACKUP_DIR)) {
    fwrite(STDERR, "Backup dizini yazılabilir değil: {$BACKUP_DIR}\n");
    exit(1);
}
$logDir = dirname($LOG_FILE);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

/**
 * Log yaz — hem stdout'a hem dosyaya
 */
$log = static function (string $level, string $msg) use ($LOG_FILE): void {
    $line = sprintf("[%s] [%s] %s\n", date('c'), $level, $msg);
    echo $line;
    @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
};

$startTime = microtime(true);
$log('INFO', 'Backup başladı' . ($dryRun ? ' (DRY RUN)' : ''));

// Dosya adı — backup-YYYYMMDD-HHMMSS.sql.gz
$timestamp = date('Ymd-His');
$filename = "backup-{$timestamp}.sql.gz";
$fullPath = $BACKUP_DIR . DIRECTORY_SEPARATOR . $filename;

$log('INFO', "Hedef: {$fullPath}");
if (!empty($tables)) {
    $log('INFO', 'Belirli tablolar: ' . implode(', ', $tables));
}

/**
 * mysqldump binary'sini bul (Windows + Linux)
 */
function pastane_find_mysqldump(): ?string
{
    // PATH'te varsa
    $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
    $out = @shell_exec("{$which} mysqldump 2>&1");
    if ($out !== null && trim($out) !== '') {
        $first = trim(explode("\n", trim($out))[0]);
        if ($first !== '' && !str_contains(strtolower($first), 'not found') && !str_contains(strtolower($first), 'could not')) {
            return $first;
        }
    }

    // XAMPP default path
    $xamppPaths = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        '/xampp/mysql/bin/mysqldump',
        '/opt/lampp/bin/mysqldump',
        '/usr/bin/mysqldump',
    ];
    foreach ($xamppPaths as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    return null;
}

$mysqldump = pastane_find_mysqldump();
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? DB_NAME : '';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';

if ($dbName === '') {
    $log('ERROR', 'DB_NAME tanımlı değil — .env veya config.php kontrol edin.');
    exit(3);
}

if ($dryRun) {
    $log('INFO', 'Dry run — dosya oluşturulmayacak.');
    $log('INFO', "Would use: " . ($mysqldump ?: 'PHP-native PDO fallback'));
} elseif ($mysqldump !== null) {
    // mysqldump ile
    $log('INFO', "mysqldump bulundu: {$mysqldump}");

    $escapedHost = escapeshellarg($dbHost);
    $escapedUser = escapeshellarg($dbUser);
    $escapedDb = escapeshellarg($dbName);
    $escapedPass = $dbPass !== '' ? '-p' . escapeshellarg($dbPass) : '';
    $tableArgs = '';
    if (!empty($tables)) {
        $tableArgs = ' ' . implode(' ', array_map('escapeshellarg', $tables));
    }

    // mysqldump → gzip pipe
    $tempSql = $BACKUP_DIR . DIRECTORY_SEPARATOR . 'backup-' . $timestamp . '.sql.tmp';
    $cmd = sprintf(
        '%s -h %s -u %s %s --single-transaction --quick --lock-tables=false --routines --triggers %s%s > %s 2>&1',
        escapeshellarg($mysqldump),
        $escapedHost,
        $escapedUser,
        $escapedPass,
        $escapedDb,
        $tableArgs,
        escapeshellarg($tempSql)
    );

    exec($cmd, $cmdOutput, $cmdExit);
    if ($cmdExit !== 0) {
        $log('ERROR', 'mysqldump başarısız (exit ' . $cmdExit . '): ' . implode(' | ', $cmdOutput));
        @unlink($tempSql);
        exit(3);
    }

    // gzip ile sıkıştır
    if (!function_exists('gzopen')) {
        $log('WARN', 'zlib yok — .sql olarak bırakılıyor.');
        rename($tempSql, $BACKUP_DIR . DIRECTORY_SEPARATOR . "backup-{$timestamp}.sql");
    } else {
        $src = fopen($tempSql, 'rb');
        $dst = gzopen($fullPath, 'wb9');
        if ($src === false || $dst === false) {
            $log('ERROR', 'Gzip çıktı açılamadı');
            @unlink($tempSql);
            exit(3);
        }
        while (!feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false) {
                break;
            }
            gzwrite($dst, $chunk);
        }
        fclose($src);
        gzclose($dst);
        @unlink($tempSql);
    }
} else {
    // PHP-native fallback: PDO ile SELECT * FROM her tablo
    $log('INFO', 'mysqldump bulunamadı — PHP-native PDO fallback');

    $pdo = db()->getPdo();
    $tablesToExport = $tables;
    if (empty($tablesToExport)) {
        $stmt = $pdo->query('SHOW TABLES');
        $tablesToExport = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $tmpFile = $BACKUP_DIR . DIRECTORY_SEPARATOR . "backup-{$timestamp}.sql.tmp";
    $out = fopen($tmpFile, 'wb');
    if ($out === false) {
        $log('ERROR', "Temp dosya yazılamadı: {$tmpFile}");
        exit(3);
    }

    fwrite($out, "-- Pastane DB Backup\n");
    fwrite($out, "-- Timestamp: {$timestamp}\n");
    fwrite($out, "-- Mode: PHP-native PDO (mysqldump yok)\n");
    fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach ($tablesToExport as $t) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $t)) {
            continue; // güvenlik
        }
        // CREATE TABLE
        $createRow = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
        fwrite($out, "\n-- Table: {$t}\nDROP TABLE IF EXISTS `{$t}`;\n");
        fwrite($out, ($createRow['Create Table'] ?? '') . ";\n\n");

        // INSERT
        $rows = $pdo->query("SELECT * FROM `{$t}`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(static fn($c) => "`{$c}`", array_keys($row));
            $vals = array_map(static function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, array_values($row));
            fwrite($out, "INSERT INTO `{$t}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
        }
    }

    fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($out);

    // gzip
    if (function_exists('gzopen')) {
        $src = fopen($tmpFile, 'rb');
        $dst = gzopen($fullPath, 'wb9');
        while (!feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false) {
                break;
            }
            gzwrite($dst, $chunk);
        }
        fclose($src);
        gzclose($dst);
        @unlink($tmpFile);
    } else {
        rename($tmpFile, $BACKUP_DIR . DIRECTORY_SEPARATOR . "backup-{$timestamp}.sql");
    }
}

// Sonuç
if (!$dryRun && file_exists($fullPath)) {
    $size = filesize($fullPath);
    $sizeMb = round($size / 1024 / 1024, 2);
    $elapsed = round(microtime(true) - $startTime, 2);
    $log('INFO', "Backup başarılı: {$filename} ({$sizeMb} MB, {$elapsed}s)");
}

// Retention — N günden eski *.sql.gz dosyalarını sil
$cutoff = time() - ($retentionDays * 86400);
$cleaned = 0;
foreach (glob($BACKUP_DIR . DIRECTORY_SEPARATOR . 'backup-*.sql.gz') ?: [] as $f) {
    if (!is_file($f)) {
        continue;
    }
    if (filemtime($f) < $cutoff) {
        $log('INFO', 'Retention: siliniyor ' . basename($f));
        if (!$dryRun) {
            @unlink($f);
        }
        $cleaned++;
    }
}
foreach (glob($BACKUP_DIR . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [] as $f) {
    // gzip edilmemiş eski dosyalar (fallback için)
    if (!is_file($f)) {
        continue;
    }
    if (filemtime($f) < $cutoff) {
        $log('INFO', 'Retention: siliniyor ' . basename($f));
        if (!$dryRun) {
            @unlink($f);
        }
        $cleaned++;
    }
}

$log('INFO', "Bitti — cleaned={$cleaned}");
exit(0);
