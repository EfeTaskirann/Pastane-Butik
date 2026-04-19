#!/usr/bin/env php
<?php
/**
 * Cache Warming Script — Deploy sonrasi kritik path'leri pre-load
 *
 * Sprint 4 (Production Launch). Ilk kullanici isteklerinin cache miss'e denk
 * gelmemesi icin deploy pipeline'inda calistirilir:
 *   UrunService::getActive()               -> anasayfa + menu listesi
 *   UrunService::getFeatured()              -> anasayfa one cikan urunler
 *   KategoriService::getAllWithProductCount -> kategoriler menusu + admin
 *   AyarService key'leri                    -> site genel ayarlari
 *
 * Kullanim:
 *   php bin/cache-warm.php                  # tum key'leri warm et
 *   php bin/cache-warm.php --quiet          # sadece exit code
 *
 * Exit codes:
 *   0 = tum key'ler basariyla warm edildi
 *   1 = en az bir key basarisiz (detay stderr'de)
 *
 * @package Pastane
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use Pastane\Services\AyarService;
use Pastane\Services\KategoriService;
use Pastane\Services\UrunService;

$quiet = in_array('--quiet', array_slice($argv, 1), true);

function warm_log(string $msg, string $type = 'info', bool $quiet = false): void
{
    if ($quiet) {
        return;
    }
    $colors = [
        'info'    => "\033[0;36m",
        'success' => "\033[0;32m",
        'error'   => "\033[0;31m",
        'warning' => "\033[0;33m",
        'reset'   => "\033[0m",
    ];
    $c = $colors[$type] ?? $colors['info'];
    echo $c . $msg . $colors['reset'] . PHP_EOL;
}

$startTime = microtime(true);
$warmed = 0;
$errors = 0;
$failedKeys = [];

warm_log('==> Cache warming baslıyor...', 'info', $quiet);

/**
 * Tek bir warming adimini calistir — hata yakalar, sayac arttirir.
 */
$warm = function (string $label, callable $task) use (&$warmed, &$errors, &$failedKeys, $quiet): void {
    $t0 = microtime(true);
    try {
        $result = $task();
        $elapsed = round((microtime(true) - $t0) * 1000, 2);
        $size = is_array($result) ? count($result) : 1;
        warm_log(sprintf('  OK  %-45s (%d kayit, %s ms)', $label, $size, $elapsed), 'success', $quiet);
        $warmed++;
    } catch (\Throwable $e) {
        warm_log(sprintf('  FAIL %-45s : %s', $label, $e->getMessage()), 'error', $quiet);
        $errors++;
        $failedKeys[] = $label;
    }
};

// =============================================================================
// 1. UrunService — active + featured
// =============================================================================
/** @var UrunService $urunService */
$urunService = resolve(UrunService::class);

$warm('UrunService::getActive()',    fn() => $urunService->getActive());
$warm('UrunService::getFeatured(6)', fn() => $urunService->getFeatured(6));

// =============================================================================
// 2. KategoriService — product count
// =============================================================================
/** @var KategoriService $kategoriService */
$kategoriService = resolve(KategoriService::class);

$warm('KategoriService::getAllWithProductCount()', fn() => $kategoriService->getAllWithProductCount());

// =============================================================================
// 3. AyarService — tum key'ler (loadAll)
// =============================================================================
try {
    /** @var AyarService $ayarService */
    $ayarService = resolve(AyarService::class);

    $warm('AyarService::getAllRaw()', fn() => $ayarService->getAllRaw());

    // Yaygin tekil key'leri de warm et — loadAll cache'te zaten tutuyor ama
    // kritik tekil erisimler icin ekstra guvenlik:
    foreach (['site_adi', 'site_email', 'site_telefon', 'para_birimi', 'kdv_orani'] as $k) {
        $warm("AyarService::get('{$k}')", fn() => $ayarService->get($k));
    }
} catch (\Throwable $e) {
    warm_log('  UYARI AyarService cozulemedi: ' . $e->getMessage(), 'warning', $quiet);
    warm_log('         (Ayarlar tablosu henuz migrate edilmemis olabilir.)', 'warning', $quiet);
    $errors++;
    $failedKeys[] = 'AyarService';
}

// =============================================================================
// Ozet
// =============================================================================
$elapsed = round((microtime(true) - $startTime) * 1000, 2);

warm_log('', 'info', $quiet);
warm_log(
    sprintf('==> %d keys cached in %s ms (errors: %d)', $warmed, $elapsed, $errors),
    $errors === 0 ? 'success' : 'warning',
    $quiet
);

if ($errors > 0) {
    warm_log('Basarisiz key\'ler: ' . implode(', ', $failedKeys), 'warning', $quiet);
    exit(1);
}

exit(0);
