<?php

declare(strict_types=1);

/**
 * Pastane — i18n Extract & Sync Tool
 *
 * Tum PHP/JS dosyalarini tarar, `t('key')` cagrilarini bulur, `lang/tr.php`
 * ve `lang/en.php` ile karsilastirir. Eksik key'leri raporlar; --apply ile
 * her iki dosyaya da TR icin "[TODO TR]" ve EN icin TR placeholder ekler.
 *
 * KULLANIM:
 *   php bin/i18n-extract.php                # rapor (dry-run)
 *   php bin/i18n-extract.php --apply        # eksik key'leri auto-stub'la doldur
 *   php bin/i18n-extract.php --json         # JSON cikti (CI gate)
 *   php bin/i18n-extract.php --target=en    # sadece EN eksiklerini gorster
 *
 * EXIT CODES:
 *   0 = eksik key yok (CI gate green)
 *   1 = parametre hatasi
 *   2 = eksik key var (apply yapilmadi)
 *   3 = apply sonrasi yazma hatasi
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$jsonOut = in_array('--json', $args, true);
$verbose = in_array('--verbose', $args, true) || in_array('-v', $args, true);

$targetLocale = null;
foreach ($args as $a) {
    if (preg_match('/^--target=([a-z]{2})$/', $a, $m)) {
        $targetLocale = $m[1];
    }
}

// 1) Tum kaynakta t('key') cagrilarini bul
$scanDirs = ['admin', 'menu', 'includes', 'src', 'api', 'index.php'];
$extensions = ['php', 'js', 'html'];

$keys = [];

foreach ($scanDirs as $dir) {
    $abs = $rootPath . '/' . $dir;
    if (!file_exists($abs)) continue;

    if (is_file($abs)) {
        scanFile($abs, $keys);
        continue;
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) continue;
        // Skip vendor/dist/test artefacts
        $path = str_replace('\\', '/', $file->getPathname());
        if (preg_match('#/(vendor|node_modules|dist|tests/e2e|tests/Unit/I18n)/#', $path)) continue;
        scanFile($file->getPathname(), $keys);
    }
}

ksort($keys);
$totalUsed = count($keys);

// 2) Lang dosyalarini yukle
$tr = loadLang($rootPath . '/lang/tr.php');
$en = loadLang($rootPath . '/lang/en.php');
$trFlat = flatten($tr);
$enFlat = flatten($en);

// 3) Eksikleri hesapla
$missingTr = [];
$missingEn = [];
foreach ($keys as $key => $usages) {
    if (!isset($trFlat[$key])) {
        $missingTr[$key] = $usages;
    }
    if (!isset($enFlat[$key])) {
        $missingEn[$key] = $usages;
    }
}

// 4) Runtime missing (storage/i18n-missing.json) - opsiyonel ek bilgi
$runtimeMissingPath = $rootPath . '/storage/i18n-missing.json';
$runtimeMissing = [];
if (is_file($runtimeMissingPath)) {
    $raw = @file_get_contents($runtimeMissingPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $runtimeMissing = $decoded;
    }
}

// 5) Cikti
if ($jsonOut) {
    $report = [
        'generated_at' => date('c'),
        'total_used_keys' => $totalUsed,
        'tr_total' => count($trFlat),
        'en_total' => count($enFlat),
        'missing_tr' => array_keys($missingTr),
        'missing_en' => array_keys($missingEn),
        'runtime_missing' => $runtimeMissing,
    ];
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo "i18n Extract Report\n";
    echo str_repeat('=', 60) . "\n";
    echo "Scan directories: " . implode(', ', $scanDirs) . "\n";
    echo "Used keys (in code): {$totalUsed}\n";
    echo "lang/tr.php keys:    " . count($trFlat) . "\n";
    echo "lang/en.php keys:    " . count($enFlat) . "\n";
    echo "Missing in TR:       " . count($missingTr) . "\n";
    echo "Missing in EN:       " . count($missingEn) . "\n";
    echo "Runtime hits:        " . array_sum(array_map('count', $runtimeMissing)) . " key(s)\n\n";

    if (!empty($missingTr) && (!$targetLocale || $targetLocale === 'tr')) {
        echo "--- MISSING IN TR ---\n";
        foreach ($missingTr as $key => $usages) {
            echo "  $key\n";
            if ($verbose) {
                foreach (array_slice($usages, 0, 3) as $u) {
                    echo "    - $u\n";
                }
            }
        }
        echo "\n";
    }

    if (!empty($missingEn) && (!$targetLocale || $targetLocale === 'en')) {
        echo "--- MISSING IN EN ---\n";
        foreach ($missingEn as $key => $usages) {
            $trText = $trFlat[$key] ?? '(also missing in TR)';
            echo "  $key  →  TR: \"$trText\"\n";
            if ($verbose) {
                foreach (array_slice($usages, 0, 3) as $u) {
                    echo "    - $u\n";
                }
            }
        }
        echo "\n";
    }
}

if (!$apply) {
    if (!empty($missingTr) || !empty($missingEn)) {
        if (!$jsonOut) {
            echo "Run with --apply to auto-stub missing keys.\n";
        }
        exit(2);
    }
    if (!$jsonOut) echo "All keys translated. Lang files in sync.\n";
    exit(0);
}

// 6) APPLY: Eksik key'leri auto-stub'la doldur
$trOut = $tr;
$enOut = $en;
$addedTr = 0;
$addedEn = 0;

foreach ($missingTr as $key => $_) {
    setNested($trOut, $key, '[TODO TR] ' . $key);
    $addedTr++;
}

foreach ($missingEn as $key => $_) {
    // EN icin TR'den placeholder al; yoksa key'i kullan
    $trVal = $trFlat[$key] ?? ($trOut[$key] ?? null);
    if ($trVal === null) {
        // TR'ye az once eklediysek oradan al
        $tmp = flatten($trOut);
        $trVal = $tmp[$key] ?? $key;
    }
    setNested($enOut, $key, '[TR] ' . $trVal);
    $addedEn++;
}

if ($addedTr > 0) {
    if (!writeLang($rootPath . '/lang/tr.php', $trOut, 'tr')) {
        fwrite(STDERR, "Failed to write lang/tr.php\n");
        exit(3);
    }
    echo "Added {$addedTr} stubs to lang/tr.php\n";
}

if ($addedEn > 0) {
    if (!writeLang($rootPath . '/lang/en.php', $enOut, 'en')) {
        fwrite(STDERR, "Failed to write lang/en.php\n");
        exit(3);
    }
    echo "Added {$addedEn} stubs to lang/en.php\n";
}

if ($addedTr === 0 && $addedEn === 0) {
    echo "Nothing to add.\n";
}

exit(0);


// ----- Helpers -----

function scanFile(string $path, array &$keys): void
{
    $content = @file_get_contents($path);
    if ($content === false) return;

    // PHP: t('key') | t("key") | I18n::translate('key')
    // JS: t('key') (varsa)
    $patterns = [
        "/\\bt\\s*\\(\\s*'([a-zA-Z0-9._-]+)'/",
        '/\\bt\\s*\\(\\s*"([a-zA-Z0-9._-]+)"/',
        "/I18n::translate\\s*\\(\\s*'([a-zA-Z0-9._-]+)'/",
        '/I18n::translate\\s*\\(\\s*"([a-zA-Z0-9._-]+)"/',
    ];

    $relPath = str_replace('\\', '/', $path);
    $relPath = preg_replace('#^.*?/pastane/#', '', $relPath) ?? $relPath;

    foreach ($patterns as $p) {
        if (preg_match_all($p, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $key = $match[0];
                $offset = $match[1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;
                $keys[$key] = $keys[$key] ?? [];
                $keys[$key][] = $relPath . ':' . $line;
            }
        }
    }
}

function loadLang(string $path): array
{
    if (!is_file($path)) return [];
    $data = include $path;
    return is_array($data) ? $data : [];
}

function flatten(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $newKey = $prefix === '' ? (string)$k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out = array_merge($out, flatten($v, $newKey));
        } else {
            $out[$newKey] = $v;
        }
    }
    return $out;
}

function setNested(array &$arr, string $key, string $value): void
{
    $segments = explode('.', $key);
    $current = &$arr;
    foreach ($segments as $i => $seg) {
        if ($i === count($segments) - 1) {
            $current[$seg] = $value;
            return;
        }
        if (!isset($current[$seg]) || !is_array($current[$seg])) {
            $current[$seg] = [];
        }
        $current = &$current[$seg];
    }
}

function writeLang(string $path, array $data, string $locale): bool
{
    ksort($data);
    foreach ($data as &$group) {
        if (is_array($group)) ksort($group);
    }
    unset($group);

    $header = "<?php\n\ndeclare(strict_types=1);\n\n";
    $header .= "/**\n * Pastane — Translations ({$locale})\n";
    $header .= " * Auto-generated by bin/i18n-extract.php — manual edits ok, save with locked sort.\n";
    $header .= " */\n\nreturn ";
    $body = var_export($data, true) . ";\n";

    // var_export quirk: array() yerine [] tercih
    $body = preg_replace('/array \(/', '[', $body);
    $body = preg_replace('/^(\s*)\)/m', '$1]', $body);

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $header . $body, LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, $path);
}
