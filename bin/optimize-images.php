<?php

declare(strict_types=1);

/**
 * Pastane — Upload Image Optimizer (P1-10)
 *
 * `uploads/products/*.{jpg,jpeg,png}` dosyalarını WebP'e dönüştürür ve
 * orijinal dosyaları ya bırakır (fallback için) ya da quality/size
 * limitlerine göre yeniden sıkıştırır.
 *
 * KULLANIM:
 *   php bin/optimize-images.php                   # dry-run (rapor)
 *   php bin/optimize-images.php --apply           # dönüşümü uygula
 *   php bin/optimize-images.php --apply --quality=80
 *   php bin/optimize-images.php --apply --max-width=1200
 *   php bin/optimize-images.php --apply --dir=uploads/products
 *
 * OPSIYONLAR:
 *   --apply        Dönüşümü uygula (yoksa sadece rapor)
 *   --quality=N    WebP kalite 1..100 (default 82)
 *   --max-width=N  Maks genişlik (>=): default 1600 (daha genişi resize)
 *   --dir=PATH     Kök dizin (default uploads/products)
 *   --keep-orig    Orijinal JPG/PNG'yi koru (default: korur — fallback için)
 *   --strip-orig   Orijinal JPG/PNG'yi sil (EKSTRA risk, önce test et)
 *   --verbose      Detay çıktı
 *
 * GEREKSİNİMLER:
 *   - GD extension: imagewebp(), imagecreatefromjpeg(), imagecreatefrompng()
 *
 * EXIT CODES:
 *   0 = başarı (veya dry-run OK)
 *   1 = parametre hatası
 *   2 = GD yok
 *   3 = kısmi hata (bazı dosyalar işlenemedi)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$keepOrig = !in_array('--strip-orig', $args, true);
$verbose = in_array('--verbose', $args, true);

$quality = 82;
$maxWidth = 1600;
$dir = 'uploads/products';
foreach ($args as $a) {
    if (preg_match('/^--quality=(\d+)$/', $a, $m)) $quality = (int) $m[1];
    if (preg_match('/^--max-width=(\d+)$/', $a, $m)) $maxWidth = (int) $m[1];
    if (preg_match('/^--dir=(.+)$/', $a, $m)) $dir = trim($m[1]);
}

if ($quality < 1 || $quality > 100) {
    fwrite(STDERR, "Quality must be in 1..100\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required.\n");
    exit(2);
}

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD built without WebP support (imagewebp()).\n");
    exit(2);
}

$absDir = $rootPath . '/' . ltrim($dir, '/');
if (!is_dir($absDir)) {
    fwrite(STDERR, "Directory not found: $absDir\n");
    exit(1);
}

$files = array_merge(
    glob($absDir . '/*.jpg') ?: [],
    glob($absDir . '/*.jpeg') ?: [],
    glob($absDir . '/*.JPG') ?: [],
    glob($absDir . '/*.png') ?: [],
    glob($absDir . '/*.PNG') ?: []
);

fwrite(STDOUT, ($apply ? 'APPLY' : 'DRY-RUN') . " mode. Scanning " . $dir . "\n");
fwrite(STDOUT, "Found " . count($files) . " candidate(s).\n");
fwrite(STDOUT, "Settings: quality=$quality maxWidth=$maxWidth keepOrig=" . ($keepOrig ? 'yes' : 'NO') . "\n\n");

$stats = [
    'total' => count($files),
    'converted' => 0,
    'skipped_existing' => 0,
    'skipped_error' => 0,
    'bytes_before' => 0,
    'bytes_after' => 0,
    'errors' => [],
];

foreach ($files as $src) {
    $basename = basename($src);
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $webpPath = substr($src, 0, -(strlen($ext) + 1)) . '.webp';

    $origSize = filesize($src) ?: 0;
    $stats['bytes_before'] += $origSize;

    if (file_exists($webpPath)) {
        if ($verbose) fwrite(STDOUT, "  SKIP (exists): $basename → .webp\n");
        $stats['skipped_existing']++;
        $stats['bytes_after'] += filesize($webpPath);
        continue;
    }

    // Image load
    $img = null;
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = @imagecreatefromjpeg($src);
    } elseif ($ext === 'png') {
        $img = @imagecreatefrompng($src);
    }

    if ($img === false || $img === null) {
        fwrite(STDERR, "  ERROR: cannot read $basename\n");
        $stats['errors'][] = $src;
        $stats['skipped_error']++;
        continue;
    }

    // Resize if exceeds max-width
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > $maxWidth) {
        $newH = (int) round($h * ($maxWidth / $w));
        $resized = imagecreatetruecolor($maxWidth, $newH);
        if ($ext === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    // Transparency for PNG → WebP
    if ($ext === 'png') {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    }

    $newSize = null;
    if ($apply) {
        $ok = @imagewebp($img, $webpPath, $quality);
        imagedestroy($img);
        if ($ok && file_exists($webpPath)) {
            $newSize = filesize($webpPath) ?: 0;
            $stats['bytes_after'] += $newSize;
            $stats['converted']++;

            if (!$keepOrig) {
                @unlink($src);
            }
            $savedPct = $origSize > 0 ? round(100 - (($newSize / $origSize) * 100), 1) : 0;
            fwrite(STDOUT, sprintf(
                "  OK: %-40s  %7d → %7d bytes  (-%s%%)\n",
                $basename,
                $origSize,
                $newSize,
                $savedPct
            ));
        } else {
            fwrite(STDERR, "  ERROR: webp encode failed for $basename\n");
            $stats['errors'][] = $src;
            $stats['skipped_error']++;
        }
    } else {
        imagedestroy($img);
        fwrite(STDOUT, sprintf("  CANDIDATE: %-40s  %7d bytes  (→ webp)\n", $basename, $origSize));
        $stats['bytes_after'] += (int) round($origSize * 0.35); // tahmini
    }
}

fwrite(STDOUT, "\n--- SUMMARY ---\n");
fwrite(STDOUT, "Total files:         " . $stats['total'] . "\n");
fwrite(STDOUT, "Converted:           " . $stats['converted'] . "\n");
fwrite(STDOUT, "Skipped (existing):  " . $stats['skipped_existing'] . "\n");
fwrite(STDOUT, "Skipped (errors):    " . $stats['skipped_error'] . "\n");
fwrite(STDOUT, sprintf("Bytes before:        %s\n", number_format($stats['bytes_before'])));
fwrite(STDOUT, sprintf("Bytes after:         %s\n", number_format($stats['bytes_after'])));
if ($stats['bytes_before'] > 0) {
    $saved = $stats['bytes_before'] - $stats['bytes_after'];
    $pct = round($saved / $stats['bytes_before'] * 100, 1);
    fwrite(STDOUT, sprintf("Savings:             %s bytes (%s%%)\n", number_format($saved), $pct));
}

exit(count($stats['errors']) > 0 ? 3 : 0);
