<?php

declare(strict_types=1);

/**
 * Asset resolver — prod/dev asset path switching (P1-11)
 *
 * Vite build artefact'lerini production'da, raw kaynakları development'ta servis eder.
 * Vite manifest dosyasını (`dist/manifest.json`) okur ve hash'lenmiş dosya adlarını
 * çözer. Manifest yoksa veya dev mode ise raw dosya döndürür.
 *
 * Çevre değişkenleri:
 *   APP_ENV=production  → dist/*.hash.js + hash.css
 *   APP_ENV=development → assets/*.js + assets/*.css ham
 *
 * Kullanım (template):
 *   <link rel="stylesheet" href="<?= asset('assets/scss/main.scss') ?>">
 *   <script src="<?= asset('assets/js/main.js') ?>"></script>
 *
 * Cache busting:
 *   Manifest yoksa fallback mtime query string (`?v=1234567890`).
 */

/**
 * Verilen raw asset path için çözümlenmiş URL döndür.
 *
 * @param string $path  `assets/js/main.js` gibi raw source path.
 * @return string       Browser-ready URL (relative APP_URL'e).
 */
function asset(string $path): string
{
    static $manifest = null;
    static $loaded = false;

    $rootDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $baseUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    $env = defined('APP_ENV') ? (string) APP_ENV : 'production';

    // Normalize input
    $normalized = ltrim(str_replace('\\', '/', $path), '/');

    // DEV — raw file + mtime
    if ($env !== 'production') {
        return assetFallback($normalized, $rootDir, $baseUrl);
    }

    // PROD — Vite manifest
    if (!$loaded) {
        $loaded = true;
        $manifestPath = $rootDir . '/dist/manifest.json';
        if (is_file($manifestPath)) {
            $raw = file_get_contents($manifestPath);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $manifest = $decoded;
                }
            }
        }
    }

    if ($manifest !== null && isset($manifest[$normalized]['file'])) {
        $built = ltrim((string) $manifest[$normalized]['file'], '/');
        return $baseUrl . '/dist/' . $built;
    }

    // Fallback: manifest yok → raw file + mtime
    return assetFallback($normalized, $rootDir, $baseUrl);
}

/**
 * Raw asset path fallback — mtime query string ile cache bust.
 */
function assetFallback(string $path, string $rootDir, string $baseUrl): string
{
    $absolute = $rootDir . '/' . $path;
    $version = is_file($absolute) ? (string) filemtime($absolute) : '0';

    // SCSS/TS gibi build-only path gelirse raw servis imkansız — yine de yolu
    // göstermeye çalış (dev server Vite middleware catch eder)
    $webPath = $path;
    // `assets/scss/main.scss` → `assets/css/main.css` simetrisi için dev'de
    // raw kaynağa bırak: browser Vite dev-server adresinde işler.
    return $baseUrl . '/' . $webPath . '?v=' . $version;
}

/**
 * Manifest girdisinin "css" bağımlılıklarını (splitted chunks) döndür.
 * Vite bir JS entry için birden çok CSS bundle oluşturabilir.
 *
 * @param string $entry  `assets/js/main.js` gibi raw path.
 * @return array<int,string>  Browser-ready CSS URL'leri.
 */
function assetCss(string $entry): array
{
    $rootDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $baseUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    $env = defined('APP_ENV') ? (string) APP_ENV : 'production';

    if ($env !== 'production') {
        return [];
    }

    $manifestPath = $rootDir . '/dist/manifest.json';
    if (!is_file($manifestPath)) {
        return [];
    }
    $raw = file_get_contents($manifestPath);
    if ($raw === false) {
        return [];
    }
    $manifest = json_decode($raw, true);
    if (!is_array($manifest)) {
        return [];
    }

    $normalized = ltrim(str_replace('\\', '/', $entry), '/');
    $entryData = $manifest[$normalized] ?? null;
    if (!is_array($entryData)) {
        return [];
    }

    $urls = [];
    foreach ((array) ($entryData['css'] ?? []) as $css) {
        $urls[] = $baseUrl . '/dist/' . ltrim((string) $css, '/');
    }
    return $urls;
}
