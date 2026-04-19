#!/usr/bin/env php
<?php
/**
 * DB Schema Diff — CLI script
 *
 * `database.sql` (fresh install SQL) ile canlı veritabanının şemasını
 * (`DESCRIBE tablo`) karşılaştırır. Farkları `docs/DB_SCHEMA_AUDIT.md`
 * dosyasına rapor eder. AUTO-FIX YAPMAZ — önerilen migration satırlarını
 * markdown olarak yazar.
 *
 * Kullanım:
 *   php bin/db-schema-diff.php                 # varsayılan (docs/DB_SCHEMA_AUDIT.md yaz)
 *   php bin/db-schema-diff.php --output=- --quiet  # stdout'a yaz (CI için)
 *   php bin/db-schema-diff.php --table=urunler # sadece tek tablo
 *
 * Çıkış kodları:
 *   0 — fark yok
 *   1 — fark var (rapor yazıldı)
 *   2 — CLI / DB hatası
 *
 * @package Pastane\Bin
 * @since 2.1.0-sprint3
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Bu script sadece CLI'da çalışır.\n");
    exit(2);
}

// Bootstrap
define('BASE_PATH', dirname(__DIR__));
$autoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
require_once BASE_PATH . '/includes/bootstrap.php';

// CLI args
$opts = getopt('', ['output::', 'table::', 'quiet', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php bin/db-schema-diff.php [--output=PATH|-] [--table=NAME] [--quiet]\n";
    exit(0);
}
$output = $opts['output'] ?? (BASE_PATH . '/docs/DB_SCHEMA_AUDIT.md');
$tableFilter = $opts['table'] ?? null;
$quiet = isset($opts['quiet']);

$log = function (string $msg) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDOUT, $msg . "\n");
    }
};

/**
 * database.sql dosyasından CREATE TABLE bloklarını parse et.
 * Her tablo için sütun tanımlarını (isim, tip, nullable, default) döndürür.
 *
 * NOT: Bu basit bir regex-parser — gömülü fonksiyonlar, CHECK constraint
 * vb. karmaşık yapılar full parse edilmez. Sprint 3 için "en sık
 * uyumsuzluk yaratan" alanlar (isim+tip+NULL+DEFAULT) yeterli.
 *
 * @param string $sql
 * @return array<string, array<string, array>>  tablo => [kolon => ['type' => ..., 'null' => ...]]
 */
function parseCreateTablesFromSql(string $sql): array
{
    $tables = [];
    // Çoklu CREATE TABLE ... ( ... ) ENGINE= bloğunu yakala
    preg_match_all(
        '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.+?)\)\s*ENGINE\s*=/is',
        $sql,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $m) {
        $tableName = strtolower($m[1]);
        $body = $m[2];
        $columns = [];

        // Her satırı virgülle böl (basit — string literal'lerdeki virgül burada yok)
        $lines = preg_split('/,\s*(?=\n|\r|$)/', $body);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // INDEX, PRIMARY KEY, UNIQUE, CONSTRAINT, FOREIGN KEY satırlarını atla
            if (preg_match('/^\s*(PRIMARY\s+KEY|UNIQUE\s+KEY|UNIQUE\s+INDEX|INDEX|KEY|CONSTRAINT|FOREIGN\s+KEY|FULLTEXT|UNIQUE)\b/i', $line)) {
                continue;
            }
            // `colname` type [NULL|NOT NULL] [DEFAULT ...] [AUTO_INCREMENT] [ON UPDATE ...]
            if (preg_match('/^`?(\w+)`?\s+([A-Z]+(?:\s*\([^)]*\))?(?:\s+UNSIGNED)?)\s*(.*)$/is', $line, $cm)) {
                $col = strtolower($cm[1]);
                $type = preg_replace('/\s+/', ' ', strtoupper(trim($cm[2])));
                $rest = strtoupper($cm[3] ?? '');
                $nullable = !str_contains($rest, 'NOT NULL');
                $default = null;
                if (preg_match('/DEFAULT\s+([^\s,]+(?:\s+ON\s+UPDATE\s+[^,]+)?)/i', $cm[3] ?? '', $dm)) {
                    $default = trim($dm[1]);
                }
                $columns[$col] = [
                    'type'    => $type,
                    'null'    => $nullable ? 'YES' : 'NO',
                    'default' => $default,
                ];
            }
        }

        if ($columns) {
            $tables[$tableName] = $columns;
        }
    }

    return $tables;
}

/**
 * Live DB'den tablonun sütunlarını çek (DESCRIBE tablo).
 *
 * @return array<string, array>
 */
function describeLiveTable(PDO $pdo, string $table): array
{
    $cols = [];
    try {
        $stmt = $pdo->query("DESCRIBE `" . preg_replace('/[^a-z0-9_]/i', '', $table) . "`");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[strtolower($row['Field'])] = [
                'type'    => strtoupper($row['Type']),
                'null'    => $row['Null'],
                'default' => $row['Default'],
            ];
        }
    } catch (\Throwable) {
        return []; // tablo canlı DB'de yok
    }
    return $cols;
}

/**
 * İki kolon tanımını karşılaştır.
 *
 * @return string[] Fark açıklamaları; boş array → aynı
 */
function compareColumn(array $sqlCol, array $liveCol): array
{
    $diffs = [];
    // Type normalize: "INT(11)" ≈ "INT" (MySQL 8 display width kaldırdı)
    // Ama DECIMAL(10,2) gibi anlamlı precision korunur — sadece INT/TINYINT/BIGINT gibi integer display width'i temizle
    $normalize = static function (string $t): string {
        $t = strtoupper($t);
        // Integer display width (INT(11), TINYINT(4)) MySQL 8'de deprecate; eşitle
        $t = preg_replace('/\b(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)\(\d+\)/', '$1', $t);
        return preg_replace('/\s+/', ' ', trim($t));
    };
    if ($normalize($sqlCol['type']) !== $normalize($liveCol['type'])) {
        $diffs[] = sprintf('type: SQL=%s, DB=%s', $sqlCol['type'], $liveCol['type']);
    }
    // Null karşılaştırma: DEFAULT NULL varsa implicit nullable olduğunu hesaba kat
    $sqlNullable = $sqlCol['null'];
    if ($sqlNullable === 'YES' && !empty($sqlCol['default']) && strtoupper($sqlCol['default']) !== 'NULL') {
        // DEFAULT değer var ama "NOT NULL" ifadesi yok → SQL parse'ımız default "YES" dönmüştür;
        // live'da "NO" ve default varsa muhtemelen MySQL default'u uygulamış. False-positive azaltmak için eşitle.
        if ($liveCol['null'] === 'NO' && !empty($liveCol['default'])) {
            $sqlNullable = 'NO';
        }
    }
    if ($sqlNullable !== $liveCol['null']) {
        $diffs[] = sprintf('null: SQL=%s, DB=%s', $sqlNullable, $liveCol['null']);
    }
    // Default karşılaştırma (null equivalence için)
    $sqlDef = strtoupper(trim((string) ($sqlCol['default'] ?? '')));
    $liveDef = strtoupper(trim((string) ($liveCol['default'] ?? '')));

    // NULL eşdeğeri: '' veya 'NULL' hepsi null sayılsın
    $isNullLike = static fn(string $v): bool => $v === '' || $v === 'NULL';
    // CURRENT_TIMESTAMP varyasyonları eşdeğer
    $normalizeTs = static fn(string $v): string => str_replace(['CURRENT_TIMESTAMP()', 'CURRENT_TIMESTAMP'], 'CURRENT_TIMESTAMP', $v);

    if (!($isNullLike($sqlDef) && $isNullLike($liveDef))) {
        if ($normalizeTs($sqlDef) !== $normalizeTs($liveDef)) {
            $diffs[] = sprintf('default: SQL=%s, DB=%s', $sqlDef ?: 'NULL', $liveDef ?: 'NULL');
        }
    }
    return $diffs;
}

// --------------------------------------------------------------------------
// Main
// --------------------------------------------------------------------------
$sqlFile = BASE_PATH . '/database.sql';
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "database.sql bulunamadı: {$sqlFile}\n");
    exit(2);
}

$log('database.sql parse ediliyor...');
$sqlTables = parseCreateTablesFromSql(file_get_contents($sqlFile));
$log(sprintf('  → %d tablo bulundu: %s', count($sqlTables), implode(', ', array_keys($sqlTables))));

try {
    $pdo = db()->getPdo();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB bağlantı hatası: " . $e->getMessage() . "\n");
    exit(2);
}

$log('Canlı DB şeması çekiliyor...');
$report = [];
$totalDiffs = 0;

foreach ($sqlTables as $table => $sqlCols) {
    if ($tableFilter !== null && $table !== strtolower($tableFilter)) {
        continue;
    }

    $liveCols = describeLiveTable($pdo, $table);

    if (!$liveCols) {
        $report[$table] = [
            'status'  => 'table_missing_in_db',
            'message' => 'Tablo canlı DB\'de yok. `database.sql` veya migration çalıştırılmalı.',
            'columns' => [],
        ];
        $totalDiffs++;
        continue;
    }

    $tableReport = ['status' => 'ok', 'columns' => []];

    // SQL'de var, DB'de yok olanlar
    foreach ($sqlCols as $col => $sqlDef) {
        if (!isset($liveCols[$col])) {
            $tableReport['columns'][$col] = [
                'issue'      => 'column_missing_in_db',
                'sql'        => $sqlDef,
                'live'       => null,
                'suggestion' => sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s %s%s;',
                    $table,
                    $col,
                    $sqlDef['type'],
                    $sqlDef['null'] === 'NO' ? 'NOT NULL' : 'NULL',
                    $sqlDef['default'] !== null ? ' DEFAULT ' . $sqlDef['default'] : ''
                ),
            ];
            $totalDiffs++;
            continue;
        }

        $colDiffs = compareColumn($sqlDef, $liveCols[$col]);
        if ($colDiffs) {
            $tableReport['columns'][$col] = [
                'issue'      => 'column_mismatch',
                'sql'        => $sqlDef,
                'live'       => $liveCols[$col],
                'diffs'      => $colDiffs,
                'suggestion' => sprintf(
                    'ALTER TABLE `%s` MODIFY COLUMN `%s` %s %s%s;',
                    $table,
                    $col,
                    $sqlDef['type'],
                    $sqlDef['null'] === 'NO' ? 'NOT NULL' : 'NULL',
                    $sqlDef['default'] !== null ? ' DEFAULT ' . $sqlDef['default'] : ''
                ),
            ];
            $totalDiffs++;
        }
    }

    // DB'de var, SQL'de yok (extra — migration ile gelmiş olabilir, bilgi amaçlı)
    foreach ($liveCols as $col => $liveDef) {
        if (!isset($sqlCols[$col])) {
            $tableReport['columns'][$col] = [
                'issue'      => 'column_extra_in_db',
                'sql'        => null,
                'live'       => $liveDef,
                'suggestion' => sprintf(
                    '// database.sql\'e ekle (ör. migration ile eklenmiş): `%s` %s',
                    $col,
                    $liveDef['type']
                ),
            ];
            $totalDiffs++;
        }
    }

    if ($tableReport['columns']) {
        $tableReport['status'] = 'has_diffs';
        $report[$table] = $tableReport;
    }
}

// --------------------------------------------------------------------------
// Markdown raporu üret
// --------------------------------------------------------------------------
$md = "# DB Schema Audit Raporu\n\n";
$md .= "**Üretim Tarihi:** " . date('Y-m-d H:i:s') . "\n";
$md .= "**Kaynak:** `database.sql` vs. canlı MySQL şeması (`DESCRIBE`)\n";
$md .= "**Üreten:** `bin/db-schema-diff.php`\n\n";

if ($totalDiffs === 0) {
    $md .= "## Durum: TEMIZ\n\nFark bulunamadı. `database.sql` canlı DB şemasıyla tutarlı.\n";
    $exitCode = 0;
} else {
    $md .= "## Durum: {$totalDiffs} fark bulundu\n\n";
    $md .= "> **Not:** Bu raporu körü körüne uygulamayın. `database.sql` dondurulmuş\n";
    $md .= "> bir dosyadır; gerçek kaynak `database/migrations/*.php` migration'larıdır.\n";
    $md .= "> Bu rapor **migration'lar güncel mi?** sorusunu yanıtlamak için bir\n";
    $md .= "> denetim aracıdır.\n\n";

    foreach ($report as $table => $data) {
        $md .= "### `{$table}` tablosu\n\n";
        if ($data['status'] === 'table_missing_in_db') {
            $md .= "**Sorun:** {$data['message']}\n\n";
            continue;
        }

        $md .= "| Kolon | Sorun | SQL tanımı | DB tanımı | Önerilen migration satırı |\n";
        $md .= "|---|---|---|---|---|\n";
        foreach ($data['columns'] as $col => $colData) {
            $sqlStr = $colData['sql']
                ? sprintf('`%s` NULL=%s DEFAULT=%s', $colData['sql']['type'], $colData['sql']['null'], $colData['sql']['default'] ?? 'NULL')
                : '—';
            $liveStr = $colData['live']
                ? sprintf('`%s` NULL=%s DEFAULT=%s', $colData['live']['type'], $colData['live']['null'], $colData['live']['default'] ?? 'NULL')
                : '—';
            $issueLabel = match ($colData['issue']) {
                'column_missing_in_db' => 'DB\'de YOK',
                'column_extra_in_db'   => 'SQL\'de YOK',
                'column_mismatch'      => 'UYUMSUZ',
                default                => '?',
            };
            $md .= sprintf(
                "| `%s` | %s | %s | %s | `%s` |\n",
                $col,
                $issueLabel,
                $sqlStr,
                $liveStr,
                $colData['suggestion']
            );
        }
        $md .= "\n";
    }
    $exitCode = 1;
}

if ($output === '-') {
    echo $md;
} else {
    $dir = dirname($output);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($output, $md);
    $log("Rapor yazıldı: {$output}");
}

$log(sprintf('Toplam fark: %d', $totalDiffs));
exit($exitCode);
