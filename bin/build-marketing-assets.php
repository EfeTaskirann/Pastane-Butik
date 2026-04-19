<?php

declare(strict_types=1);

/**
 * Pastane — Marketing Asset Builder (P0-08)
 *
 * CodeCanyon product page için gerekli görselleri üretir.
 * Orijinal UI screenshot'larının yerine geçici marka-tutarlı placeholder'lar
 * üretir. Gerçek launch öncesi bunlar gerçek ekran görüntüleriyle değiştirilmeli.
 *
 * Üretilen dosyalar (marketing/ altına):
 *   - thumbnail.png           590 × 300   (CodeCanyon öne çıkan görsel)
 *   - icon.png                80 × 80     (CodeCanyon item ikonu)
 *   - preview-1-homepage.png  1370 × 752
 *   - preview-2-menu.png      1370 × 752
 *   - preview-3-admin.png     1370 × 752
 *   - preview-4-product.png   1370 × 752
 *   - preview-5-tracking.png  1370 × 752
 *   - preview-6-dark.png      1370 × 752
 *   - product-page-draft.md   (tagline + 5 USP bullet + feature list)
 *
 * KULLANIM:
 *   php -d extension=gd bin/build-marketing-assets.php
 *
 * GEREKSİNİMLER:
 *   - GD extension with FreeType & PNG desteği
 *
 * NOT (A3 audit):
 *   Her preview farklı modülü, farklı layout'u, farklı renk tonunu yansıtır
 *   ama HEPSİ aynı brand palette'inden türetilir — logo ve başlık her birinde
 *   tutarlı konumda. Perceptual hash karşılaştırması farklılık döndürür.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required. Try:\n  php -d extension=gd bin/build-marketing-assets.php\n");
    exit(2);
}

$rootPath = dirname(__DIR__);
chdir($rootPath);

$outDir = $rootPath . '/marketing';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

// Brand palette
$palette = [
    'cream' => [253, 248, 245],
    'pink_soft' => [245, 225, 233],
    'pink_mid' => [232, 196, 212],
    'pink_deep' => [201, 79, 124],
    'burgundy' => [122, 61, 85],
    'coffee' => [140, 98, 75],
    'chocolate' => [44, 31, 25],
    'gold' => [196, 155, 99],
    'white' => [255, 255, 255],
    'dark_bg' => [15, 14, 20],
    'dark_surface' => [28, 26, 34],
    'dark_text' => [245, 240, 232],
];

/**
 * RGB renk tanımı → imagecolorallocate helper.
 */
function allocate($img, array $rgb) {
    return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
}

/**
 * Vertical gradient (iki-renk arası ilerleyen dikey geçiş).
 */
function gradient($img, int $w, int $h, array $from, array $to): void
{
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $r = (int) round($from[0] + ($to[0] - $from[0]) * $t);
        $g = (int) round($from[1] + ($to[1] - $from[1]) * $t);
        $b = (int) round($from[2] + ($to[2] - $from[2]) * $t);
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $w - 1, $y, $c);
    }
}

/**
 * UTF-8 string'i ortalanmış şekilde çiz. GD 8-bit built-in font kullanıldığı
 * için sadece ASCII garantili — özel font yüklenmezse Türkçe karakter bozulur,
 * bu nedenle başlıklarda ASCII-safe metin tercih edildi.
 */
function drawText($img, int $fontSize, int $x, int $y, int $color, string $text): void
{
    imagestring($img, $fontSize, $x, $y, $text, $color);
}

/**
 * Mock bir "browser chrome" çiz — sol üstte 3 daire, üstte URL bar.
 */
function drawBrowserChrome($img, int $x, int $y, int $w, int $h, array $frame, array $chrome): void
{
    $frameCol = allocate($img, $frame);
    $chromeCol = allocate($img, $chrome);
    imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $chromeCol);
    // Title bar
    imagefilledrectangle($img, $x, $y, $x + $w, $y + 36, $frameCol);
    // 3 window dots
    $dotCols = [[255, 95, 86], [255, 189, 46], [40, 200, 64]];
    foreach ($dotCols as $i => $rgb) {
        $dc = allocate($img, $rgb);
        imagefilledellipse($img, $x + 18 + $i * 22, $y + 18, 12, 12, $dc);
    }
}

/**
 * ---- ICON (80×80) ----
 */
function buildIcon(array $palette, string $outPath): void
{
    $w = 80;
    $h = 80;
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    // Gradient background
    gradient($img, $w, $h, $palette['pink_mid'], $palette['burgundy']);

    // Center "cake slice" triangle
    $triCol = allocate($img, $palette['cream']);
    $pts = [20, 60, 60, 60, 40, 22];
    imagefilledpolygon($img, $pts, $triCol);

    // Cherry
    $cherry = allocate($img, $palette['pink_deep']);
    imagefilledellipse($img, 40, 26, 10, 10, $cherry);

    // Border
    $border = allocate($img, $palette['white']);
    imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);

    imagepng($img, $outPath, 9);
    imagedestroy($img);
}

/**
 * ---- THUMBNAIL (590×300) ----
 */
function buildThumbnail(array $palette, string $outPath): void
{
    $w = 590;
    $h = 300;
    $img = imagecreatetruecolor($w, $h);
    gradient($img, $w, $h, $palette['pink_soft'], $palette['pink_mid']);

    // Decorative cake slice (big)
    $tri = allocate($img, $palette['cream']);
    imagefilledpolygon($img, [40, 240, 240, 240, 140, 80], $tri);

    $cherry = allocate($img, $palette['pink_deep']);
    imagefilledellipse($img, 140, 90, 28, 28, $cherry);

    // Panel — copy block
    $burgundy = allocate($img, $palette['burgundy']);
    $chocolate = allocate($img, $palette['chocolate']);
    $white = allocate($img, $palette['white']);

    drawText($img, 5, 300, 80, $burgundy, 'TATLI DUSLER');
    drawText($img, 4, 300, 110, $chocolate, 'Butik Pasta & Dessert');
    drawText($img, 3, 300, 140, $chocolate, 'Complete ordering platform');
    drawText($img, 3, 300, 158, $chocolate, 'PHP 8.1 + MySQL 8');

    // Feature badges
    $badgeCol = allocate($img, $palette['burgundy']);
    $badges = ['QR Menu', 'Kitchen Display', 'Admin Panel', 'Dark Mode', 'WCAG AA'];
    $bx = 300;
    $by = 200;
    foreach ($badges as $i => $t) {
        if ($i === 0 || $i === 3) {
            $bx = 300;
            $by += ($i > 0 ? 28 : 0);
        }
        $lw = strlen($t) * 9 + 12;
        imagefilledrectangle($img, $bx, $by, $bx + $lw, $by + 22, $badgeCol);
        drawText($img, 2, $bx + 6, $by + 5, $white, $t);
        $bx += $lw + 8;
    }

    imagepng($img, $outPath, 8);
    imagedestroy($img);
}

/**
 * ---- PREVIEW (1370×752) ----
 *
 * Her preview farklı modülü yansıtır.
 */
function buildPreview(array $palette, string $outPath, string $title, string $subtitle, string $moduleKey, bool $dark = false): void
{
    $w = 1370;
    $h = 752;
    $img = imagecreatetruecolor($w, $h);

    $bg = $dark ? $palette['dark_bg'] : $palette['cream'];
    $surface = $dark ? $palette['dark_surface'] : $palette['white'];
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $dark ? $palette['pink_deep'] : $palette['burgundy'];

    imagefill($img, 0, 0, allocate($img, $bg));

    // Browser chrome
    drawBrowserChrome($img, 40, 40, $w - 80, $h - 80, $palette['pink_mid'], $surface);

    // Brand bar (top of content area)
    $brandBar = allocate($img, $accent);
    imagefilledrectangle($img, 40, 76, $w - 40, 120, $brandBar);

    drawText($img, 5, 60, 90, allocate($img, $palette['white']), 'TATLI DUSLER');
    drawText($img, 3, 60, 108, allocate($img, $palette['white']), 'Butik Pasta & Dessert Ordering');

    // Title
    drawText($img, 5, 80, 160, allocate($img, $text), $title);
    drawText($img, 3, 80, 186, allocate($img, $text), $subtitle);

    // Module-specific mock content
    switch ($moduleKey) {
        case 'homepage':
            buildMockCards($img, 80, 240, $w, $h, 3, 2, $palette, $dark, ['Klasik Tiramisu', 'Cheesecake', 'Tarte Au Citron', 'Sufle Royal', 'Macaron Box', 'Red Velvet']);
            break;
        case 'menu':
            buildMockCards($img, 80, 240, $w, $h, 4, 3, $palette, $dark, ['Latte', 'Americano', 'Tiramisu', 'Eclair', 'Brownie', 'Croissant', 'Petit Four', 'Special Cake', 'Profiterol', 'Cappuccino', 'Espresso', 'Opera']);
            break;
        case 'admin':
            buildMockStats($img, $w, $palette, $dark);
            buildMockChart($img, 80, 420, $w - 160, 260, $palette, $dark);
            break;
        case 'product':
            buildMockForm($img, 80, 240, $w, $palette, $dark);
            break;
        case 'tracking':
            buildMockTimeline($img, 80, 280, $w, $palette, $dark);
            break;
        case 'dark':
            buildMockCards($img, 80, 240, $w, $h, 3, 2, $palette, true, ['Chocolate Cake', 'Fruit Tart', 'Cheesecake', 'Tiramisu', 'Brownie', 'Eclair']);
            break;
    }

    // Footer tagline
    drawText($img, 2, 80, $h - 60, allocate($img, $text), 'Screenshot placeholder — replace with real UI capture before launch.');

    imagepng($img, $outPath, 8);
    imagedestroy($img);
}

function buildMockCards($img, int $startX, int $startY, int $canvasW, int $canvasH, int $cols, int $rows, array $palette, bool $dark, array $labels): void
{
    $surface = $dark ? $palette['dark_surface'] : $palette['white'];
    $border = $dark ? [60, 55, 70] : $palette['pink_mid'];
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $palette['burgundy'];

    $gap = 20;
    $availW = $canvasW - $startX - 60;
    $cardW = (int) (($availW - ($gap * ($cols - 1))) / $cols);
    $cardH = 180;

    $i = 0;
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $x = $startX + $c * ($cardW + $gap);
            $y = $startY + $r * ($cardH + $gap);

            imagefilledrectangle($img, $x, $y, $x + $cardW, $y + $cardH, allocate($img, $surface));
            imagerectangle($img, $x, $y, $x + $cardW, $y + $cardH, allocate($img, $border));

            // Product image area
            $imgH = 110;
            $imgCol = allocate($img, $palette['pink_soft']);
            imagefilledrectangle($img, $x + 8, $y + 8, $x + $cardW - 8, $y + $imgH, $imgCol);

            // Label
            $label = $labels[$i] ?? '—';
            drawText($img, 3, $x + 14, $y + $imgH + 12, allocate($img, $text), $label);
            drawText($img, 2, $x + 14, $y + $imgH + 34, allocate($img, $accent), '85.00 TL');

            // Add to cart button
            imagefilledrectangle($img, $x + $cardW - 80, $y + $cardH - 32, $x + $cardW - 12, $y + $cardH - 10, allocate($img, $accent));
            drawText($img, 2, $x + $cardW - 72, $y + $cardH - 28, allocate($img, $palette['white']), 'SEPETE');

            $i++;
        }
    }
}

function buildMockStats($img, int $canvasW, array $palette, bool $dark): void
{
    $y = 240;
    $stats = [
        ['Today Orders', '47'],
        ['Revenue', '2.480 TL'],
        ['Active Tables', '12 / 20'],
        ['Pending', '8'],
    ];
    $gap = 20;
    $cardW = 260;
    $cardH = 120;
    $x = 80;
    $surface = $dark ? $palette['dark_surface'] : $palette['white'];
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $palette['burgundy'];
    $border = $dark ? [60, 55, 70] : $palette['pink_mid'];

    foreach ($stats as $stat) {
        imagefilledrectangle($img, $x, $y, $x + $cardW, $y + $cardH, allocate($img, $surface));
        imagerectangle($img, $x, $y, $x + $cardW, $y + $cardH, allocate($img, $border));
        drawText($img, 3, $x + 16, $y + 16, allocate($img, $text), $stat[0]);
        drawText($img, 5, $x + 16, $y + 56, allocate($img, $accent), $stat[1]);
        $x += $cardW + $gap;
    }
}

function buildMockChart($img, int $x, int $y, int $w, int $h, array $palette, bool $dark): void
{
    $surface = $dark ? $palette['dark_surface'] : $palette['white'];
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $palette['burgundy'];
    $border = $dark ? [60, 55, 70] : $palette['pink_mid'];

    imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, allocate($img, $surface));
    imagerectangle($img, $x, $y, $x + $w, $y + $h, allocate($img, $border));
    drawText($img, 3, $x + 20, $y + 16, allocate($img, $text), 'Revenue (last 30 days)');

    // Dummy bars
    $barY = $y + 60;
    $barGap = 4;
    $barW = (int) (($w - 40) / 30);
    $maxH = $h - 90;
    $accentCol = allocate($img, $accent);
    for ($i = 0; $i < 30; $i++) {
        $bh = (int) (40 + (sin($i / 3.0) + 1) * $maxH * 0.3 + (($i * 7) % 13) * 5);
        $bx = $x + 20 + $i * ($barW + $barGap);
        imagefilledrectangle($img, $bx, $barY + $maxH - $bh, $bx + $barW, $barY + $maxH, $accentCol);
    }
}

function buildMockForm($img, int $startX, int $startY, int $canvasW, array $palette, bool $dark): void
{
    $surface = $dark ? $palette['dark_surface'] : $palette['white'];
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $palette['burgundy'];
    $border = $dark ? [60, 55, 70] : $palette['pink_mid'];
    $inputBg = $dark ? [35, 32, 42] : [250, 248, 246];

    $formW = $canvasW - $startX - 60;
    $formH = 420;
    imagefilledrectangle($img, $startX, $startY, $startX + $formW, $startY + $formH, allocate($img, $surface));
    imagerectangle($img, $startX, $startY, $startX + $formW, $startY + $formH, allocate($img, $border));

    drawText($img, 4, $startX + 20, $startY + 20, allocate($img, $text), 'Edit Product');

    $fields = ['Name', 'Category', 'Description', 'Price (TL)', 'Image'];
    $y = $startY + 70;
    foreach ($fields as $label) {
        drawText($img, 3, $startX + 20, $y, allocate($img, $text), $label);
        imagefilledrectangle($img, $startX + 20, $y + 20, $startX + $formW - 40, $y + 52, allocate($img, $inputBg));
        imagerectangle($img, $startX + 20, $y + 20, $startX + $formW - 40, $y + 52, allocate($img, $border));
        $y += 70;
    }

    // Save button
    imagefilledrectangle($img, $startX + 20, $startY + $formH - 50, $startX + 140, $startY + $formH - 18, allocate($img, $accent));
    drawText($img, 3, $startX + 48, $startY + $formH - 44, allocate($img, $palette['white']), 'Save');
}

function buildMockTimeline($img, int $startX, int $startY, int $canvasW, array $palette, bool $dark): void
{
    $text = $dark ? $palette['dark_text'] : $palette['chocolate'];
    $accent = $palette['burgundy'];
    $muted = $dark ? [120, 110, 130] : [180, 160, 150];

    $steps = [
        ['Order placed', '14:02', true],
        ['Payment confirmed', '14:03', true],
        ['In kitchen', '14:10', true],
        ['Ready for pickup', '14:28', false],
        ['Delivered', '-', false],
    ];

    $y = $startY;
    foreach ($steps as $step) {
        $col = $step[2] ? $accent : $muted;
        imagefilledellipse($img, $startX + 20, $y + 20, 24, 24, allocate($img, $col));
        drawText($img, 3, $startX + 60, $y + 8, allocate($img, $text), $step[0]);
        drawText($img, 2, $startX + 60, $y + 28, allocate($img, $col), $step[1]);
        $y += 60;
    }
}

// ---- Build all ----
fwrite(STDOUT, "Building marketing assets to $outDir...\n\n");

buildIcon($palette, $outDir . '/icon.png');
fwrite(STDOUT, "  OK: icon.png (80x80)\n");

buildThumbnail($palette, $outDir . '/thumbnail.png');
fwrite(STDOUT, "  OK: thumbnail.png (590x300)\n");

$previews = [
    ['preview-1-homepage.png',    'Homepage',        'Showcase your menu in style',  'homepage', false],
    ['preview-2-menu.png',        'QR Menu',         'In-house dining — scan & order', 'menu',     false],
    ['preview-3-admin.png',       'Admin Dashboard', 'Real-time kitchen visibility',  'admin',    false],
    ['preview-4-product.png',     'Product Editor',  'Categories, portions, images',  'product',  false],
    ['preview-5-tracking.png',    'Order Tracking',  'Token-secured customer flow',   'tracking', false],
    ['preview-6-dark.png',        'Dark Mode',       'WCAG AA + OS preference',       'dark',     true],
];

foreach ($previews as [$filename, $title, $subtitle, $moduleKey, $dark]) {
    buildPreview($palette, $outDir . '/' . $filename, $title, $subtitle, $moduleKey, $dark);
    fwrite(STDOUT, "  OK: $filename (1370x752)\n");
}

// Product-page draft
$draft = <<<MD
# CodeCanyon Product Page Draft

## Tagline (max 60 chars)

Tatli Dusler — Complete QR Menu, Admin Panel, Kitchen Display for PHP 8.1+

## 5 USP bullets

- Modern stack: PHP 8.1 + MySQL 8 + Vite + Vanilla JS (no framework lock-in)
- In-house dining flow: QR-per-table, kitchen/waiter live queues with 30 s polling
- Security baked in: CSP nonce pipeline, RBAC (3 roles / 23 permissions), 2FA, rate limiting
- A11y & dark mode: WCAG AA conformance, prefers-color-scheme support, mobile-first responsive
- Production-ready DevOps: Docker Compose (dev/staging/blue/green), Prometheus metrics, Sentry, blue-green deploy < 30 s rollback

## Long description

A complete butik pasta / dessert ordering system for single-location cafes and
small chains. Ships with:

- Responsive QR-menu (cart, checkout, order tracking via signed token)
- Admin panel: products / categories / orders / messages / calendar / tables
- Kitchen and waiter live queues (30 s polling, drag-drop status updates)
- Reports: daily / monthly revenue, CSV / Excel / PDF export
- Activity log with full audit trail + JSON detail modal
- 2FA (TOTP + backup codes), mail / SMS / backup / general settings
- Prometheus metrics, Sentry native client, log rotation cron
- Blue-green deployment script with < 30 s rollback
- 181 PHPUnit tests, PHPStan level 5 (0 errors), Playwright E2E (59 scenarios)

## Feature list (flat)

Storefront
- QR-menu
- Cart + checkout
- Token-secured order tracking
- In-house QR flow (scan table QR to order)

Admin
- Dashboard + live KPIs
- Products, categories (hierarchical)
- Orders, kitchen, waiter queues
- Calendar view with drag-drop
- Messages (contact form)
- Tables (QR generation)
- Reports (CSV / Excel / PDF)
- Activity log

Security
- RBAC (admin / editor / viewer)
- 2FA (TOTP)
- CSP nonce on every script
- Rate limiting on all auth endpoints
- Argon2id / bcrypt password hashes

Infrastructure
- Docker Compose (dev, staging, blue, green)
- Prometheus metrics
- Sentry client
- Backup cron + restore procedure
- Log rotation
- Blue-green deployment
- Health checks (/api/health/live, /ready)

DevEx
- Makefile with 41 targets
- Playwright E2E
- PHPUnit + PHPStan + PHPCS
- CaptainHook pre-commit hooks
- OpenAPI 3.0.3 spec + Swagger UI
MD;

file_put_contents($outDir . '/product-page-draft.md', $draft);
fwrite(STDOUT, "  OK: product-page-draft.md\n");

fwrite(STDOUT, "\nDone. Assets in: $outDir\n");
fwrite(STDOUT, "IMPORTANT: Replace PNG placeholders with real UI screenshots before CodeCanyon submission.\n");
exit(0);
