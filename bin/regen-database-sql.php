<?php

declare(strict_types=1);

/**
 * Regenerate `database.sql` from migration state (P1-14)
 *
 * Temp DB yaratır, tüm migration'ları çalıştırır, şemayı dump eder,
 * final `database.sql` dosyasına yazar, temp DB'yi bırakır.
 *
 * KULLANIM:
 *   php bin/regen-database-sql.php --confirm
 *
 * GEREKSİNİMLER:
 *   - .env'de DB_USER CREATE DATABASE izinli
 *   - mysqldump erişilebilir (PATH veya DB_DUMP_BIN env)
 *   - Aynı DB_HOST'ta test DB yaratma yetkisi
 *
 * EXIT CODES:
 *   0 = başarı
 *   1 = --confirm eksik
 *   2 = DB bağlantısı / migration hatası
 *   3 = mysqldump hatası
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

$args = array_slice($argv, 1);
if (!in_array('--confirm', $args, true)) {
    fwrite(STDOUT, <<<TXT
Regenerates `database.sql` from migrations by creating a temp DB.

Usage: php bin/regen-database-sql.php --confirm
Options:
  --keep-temp    Temp DB'yi bırakma (debug için)
  --dump-bin=PATH  mysqldump binary yolu (env DB_DUMP_BIN)
  --verbose      Detaylı çıktı

Uyarı: --confirm olmadan çalıştırıldığında hiçbir değişiklik yapılmaz.

TXT);
    exit(1);
}

$keepTemp = in_array('--keep-temp', $args, true);
$verbose = in_array('--verbose', $args, true);
$dumpBin = 'mysqldump';
foreach ($args as $a) {
    if (strpos($a, '--dump-bin=') === 0) {
        $dumpBin = substr($a, strlen('--dump-bin='));
    }
}

require_once $rootPath . '/includes/bootstrap.php';

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : '';
$charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

$tempDb = 'pastane_regen_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

fwrite(STDOUT, "Temp DB: $tempDb\n");

try {
    $adminPdo = new PDO(
        "mysql:host=$host;charset=$charset",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create temp DB
    $adminPdo->exec("CREATE DATABASE `$tempDb` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
    if ($verbose) fwrite(STDOUT, "  -> CREATE DATABASE OK\n");

    // Open temp DB
    $tempPdo = new PDO(
        "mysql:host=$host;dbname=$tempDb;charset=$charset",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Override global DB for Migration class — reflect into Database singleton
    // Bu basitçe migrations klasöründeki *.sql / *.php dosyalarını elle çalıştıracağız
    $migrationDir = $rootPath . '/database/migrations';
    $migrations = glob($migrationDir . '/*.php');
    sort($migrations);

    fwrite(STDOUT, "Migration count: " . count($migrations) . "\n");

    // Ham .sql dosyalarını da dahil et (002_add_security_fields.sql)
    $sqlFiles = glob($migrationDir . '/*.sql');
    sort($sqlFiles);

    // Run .sql files first
    foreach ($sqlFiles as $sqlFile) {
        $sql = file_get_contents($sqlFile);
        if ($sql !== false && trim($sql) !== '') {
            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    try {
                        $tempPdo->exec($stmt);
                    } catch (PDOException $e) {
                        if ($verbose) fwrite(STDERR, "  SQL stmt warning: " . $e->getMessage() . "\n");
                    }
                }
            }
            fwrite(STDOUT, "  Applied: " . basename($sqlFile) . "\n");
        }
    }

    // PHP migration'ları çalıştır: her dosya bir sınıf döndürmeli veya doğrudan anonim class'lı olmalı
    // Fallback: her migration $pdo parametreli return statement'la çalıştırmayı dene
    foreach ($migrations as $phpFile) {
        try {
            $migration = include $phpFile;
            if (is_object($migration) && method_exists($migration, 'up')) {
                // Migration sınıfının DB bağımlılığı ya $db property ya $pdo alanında
                $refl = new ReflectionObject($migration);
                foreach (['pdo', 'db', 'connection'] as $propName) {
                    if ($refl->hasProperty($propName)) {
                        $p = $refl->getProperty($propName);
                        $p->setAccessible(true);
                        $p->setValue($migration, $tempPdo);
                        break;
                    }
                }
                $migration->up();
                fwrite(STDOUT, "  Applied: " . basename($phpFile) . "\n");
            } else {
                if ($verbose) fwrite(STDOUT, "  Skipped (no class): " . basename($phpFile) . "\n");
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "  Migration failed: " . basename($phpFile) . " — " . $e->getMessage() . "\n");
        }
    }

    // mysqldump --no-data (schema only)
    $dumpPath = $rootPath . '/database.sql.new';
    $cmd = sprintf(
        '%s --no-data --no-tablespaces --skip-add-drop-table --skip-comments --default-character-set=%s --host=%s --user=%s %s %s > %s 2>&1',
        escapeshellarg($dumpBin),
        escapeshellarg($charset),
        escapeshellarg($host),
        escapeshellarg($user),
        $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
        escapeshellarg($tempDb),
        escapeshellarg($dumpPath)
    );

    if ($verbose) fwrite(STDOUT, "  Dump cmd: $cmd\n");
    exec($cmd, $dumpOut, $dumpExit);

    if ($dumpExit !== 0) {
        fwrite(STDERR, "mysqldump failed (exit=$dumpExit):\n");
        fwrite(STDERR, file_get_contents($dumpPath) . "\n");
        if (!$keepTemp) $adminPdo->exec("DROP DATABASE `$tempDb`");
        exit(3);
    }

    // Add header
    $dump = file_get_contents($dumpPath);
    $header = <<<SQL
-- =============================================================================
-- Pastane — Canonical Schema (auto-generated from migrations)
-- =============================================================================
-- Generated at: @{date}
-- Source of truth: database/migrations/*.{php,sql}
-- Regenerate: php bin/regen-database-sql.php --confirm
--
-- Fresh install:
--     mysql -u user -p < database.sql
--   OR
--     php bin/migrate run
-- =============================================================================

CREATE DATABASE IF NOT EXISTS pastane_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pastane_db;


SQL;
    $header = str_replace('@{date}', date('c'), $header);
    file_put_contents($rootPath . '/database.sql', $header . $dump);

    @unlink($dumpPath);

    $size = filesize($rootPath . '/database.sql');
    fwrite(STDOUT, "database.sql regenerated: {$size} bytes\n");

    if (!$keepTemp) {
        $adminPdo->exec("DROP DATABASE `$tempDb`");
        fwrite(STDOUT, "Temp DB dropped.\n");
    } else {
        fwrite(STDOUT, "Temp DB kept: $tempDb\n");
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    try {
        if (isset($adminPdo) && !$keepTemp) {
            $adminPdo->exec("DROP DATABASE IF EXISTS `$tempDb`");
        }
    } catch (Throwable $_) {
        // ignore
    }
    exit(2);
}
