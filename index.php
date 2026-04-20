<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Güvenli session başlat (CSRF token için gerekli)
secureSessionStart();

// Kategorileri ve ürünleri veritabanından çek
$categories = getCategories();
$products = getProducts();
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(t('home.meta_description')) ?>">

    <title><?= e(SITE_NAME) ?> - <?= e(t('home.hero_subtitle')) ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=4">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/utilities.css?v=1.0">
    <link rel="stylesheet" href="assets/css/themes/dark.css?v=1.0">
    <?= render_theme_assets() ?>
    <meta name="theme-color" content="#FDF8F5">
    <?php
    // FOUC prevention: theme'i script erken uygula
    $cspNonce = function_exists('getCspNonce') ? getCspNonce() : '';
    ?>
    <script nonce="<?= e($cspNonce) ?>">
    (function () {
        try {
            var stored = localStorage.getItem('pastane_theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>
</head>
<body<?= get_theme_body_attr() ?>>
    <a href="#hero" class="skip-link"><?= e(t('a11y.skip_to_content')) ?></a>

    <?php
    // Dil secici (TR/EN) — sag-ust kose
    $currentLocale = locale();
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    $sep = (str_contains($currentUri, '?')) ? '&' : '?';
    ?>
    <div class="lang-switcher lang-switcher--floating" role="group" aria-label="<?= e(t('a11y.language_menu')) ?>">
        <a href="<?= e($currentUri . $sep) ?>lang=tr"
           class="lang-link <?= $currentLocale === 'tr' ? 'is-active' : '' ?>"
           aria-current="<?= $currentLocale === 'tr' ? 'true' : 'false' ?>"
           title="<?= e(t('language.turkish')) ?>"><?= e(t('language.tr_short')) ?></a>
        <span aria-hidden="true">|</span>
        <a href="<?= e($currentUri . $sep) ?>lang=en"
           class="lang-link <?= $currentLocale === 'en' ? 'is-active' : '' ?>"
           aria-current="<?= $currentLocale === 'en' ? 'true' : 'false' ?>"
           title="<?= e(t('language.english')) ?>"><?= e(t('language.en_short')) ?></a>
    </div>

    <!-- Tema Toggle -->
    <button class="theme-toggle"
            type="button"
            data-action="toggle-theme"
            aria-label="<?= e(t('a11y.switch_to_dark')) ?>"
            aria-pressed="false"
            title="<?= e(t('a11y.toggle_theme')) ?>">
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
        </svg>
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </svg>
    </button>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>

    <!-- Sparkle Container -->
    <div class="sparkle-container" id="sparkleContainer" aria-hidden="true"></div>

    <!-- ========== HERO SECTION ========== -->
    <section class="hero bg-gradient-1" id="hero" tabindex="-1">
        <!-- Parallax Background Layers -->
        <div class="parallax-layer parallax-layer--back" aria-hidden="true"></div>
        <div class="parallax-layer parallax-layer--mid" aria-hidden="true"></div>
        <div class="parallax-layer parallax-layer--front" aria-hidden="true"></div>

        <!-- Dekoratif Blob -->
        <div class="decoration decoration-blob float-slow u-decor-blob-right" aria-hidden="true"></div>
        <div class="decoration decoration-circle u-decor-circle-left" aria-hidden="true"></div>

        <!-- Light Orbs -->
        <div class="light-orb light-orb-1" aria-hidden="true"></div>
        <div class="light-orb light-orb-2" aria-hidden="true"></div>

        <!-- Star Sparkles -->
        <div class="star-sparkle u-sparkle-1" aria-hidden="true"></div>
        <div class="star-sparkle u-sparkle-2" aria-hidden="true"></div>
        <div class="star-sparkle u-sparkle-3" aria-hidden="true"></div>
        <div class="star-sparkle u-sparkle-4" aria-hidden="true"></div>

        <div class="hero-content">
            <div class="hero-logo">
                <h1><?= e(t('home.hero_title')) ?></h1>
                <span><?= e(t('home.hero_subtitle')) ?></span>
            </div>

            <!-- Altın Divider -->
            <div class="hero-gold-divider" aria-hidden="true"></div>

            <!-- 4 Katlı Pasta İllüstrasyonu (Claude Design bundle — Pasta.html'den entegre edildi).
                 Renkler --tier-*, --cream-*, --berry-*, --dot-*, --contact değişkenlerine
                 bağlı; tema (light/dark/yaz/kış + dark kombinasyonları) otomatik uygular. -->
            <div class="hero-illustration" data-cake-tiers="4">
                <svg viewBox="0 0 644 500" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Dört katlı butik pasta" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <radialGradient id="cakeContactShadow" cx="50%" cy="50%" r="50%">
                            <stop offset="0%" stop-color="var(--contact)"/>
                            <stop offset="65%" stop-color="var(--contact)" stop-opacity=".35"/>
                            <stop offset="100%" stop-color="var(--contact)" stop-opacity="0"/>
                        </radialGradient>
                        <linearGradient id="cakeCreamShade" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--cream)"/>
                            <stop offset="100%" stop-color="var(--cream-shade)"/>
                        </linearGradient>
                    </defs>

                    <!-- KAİDE -->
                    <ellipse cx="322" cy="462" rx="120" ry="12" fill="var(--contact)" opacity=".5"/>
                    <path d="M 218 418 Q 218 446 322 446 Q 426 446 426 418 L 426 452 Q 426 462 322 462 Q 218 462 218 452 Z" fill="var(--tier-base)"/>
                    <ellipse cx="322" cy="418" rx="104" ry="10" fill="color-mix(in oklab, var(--tier-base), #fff 18%)"/>

                    <!-- 1. KAT (en alt, büyük) -->
                    <ellipse cx="322" cy="416" rx="140" ry="6" fill="var(--contact)" opacity=".6"/>
                    <path d="M 182 362 L 182 410 Q 182 424 322 424 Q 462 424 462 410 L 462 362 Z" fill="var(--tier-light)"/>
                    <path d="M 408 362 L 462 362 L 462 410 Q 462 420 438 423 L 438 362 Z" fill="var(--tier-dark)" opacity="0.35"/>
                    <path d="M 182 362 L 206 362 L 206 422 Q 194 420 182 416 Z" fill="var(--cream)" opacity="0.10"/>
                    <ellipse cx="322" cy="362" rx="140" ry="18" fill="var(--tier-mid)"/>
                    <ellipse cx="322" cy="360" rx="140" ry="16" fill="color-mix(in oklab, var(--tier-mid), #fff 12%)"/>
                    <path d="M 182 362 Q 198 392 214 368 Q 230 398 246 368 Q 262 396 278 368 Q 294 398 310 368 Q 326 398 342 368 Q 358 396 374 368 Q 390 398 406 368 Q 422 396 438 368 Q 454 394 462 362 L 462 362 Q 322 380 182 362 Z" fill="url(#cakeCreamShade)"/>
                    <circle cx="218" cy="390" r="5" fill="var(--dot-green)"/>
                    <circle cx="418" cy="392" r="5" fill="var(--dot-pink)"/>
                    <circle cx="322" cy="400" r="3.5" fill="var(--dot-pink)" opacity=".75"/>

                    <!-- 2. KAT (4-modda görünür, 3-modda gizli) -->
                    <g class="cake-tier-4">
                        <ellipse cx="322" cy="358" rx="108" ry="4" fill="var(--contact)" opacity=".55"/>
                        <path d="M 216 310 L 216 352 Q 216 364 322 364 Q 428 364 428 352 L 428 310 Z" fill="var(--tier-mid)"/>
                        <path d="M 388 310 L 428 310 L 428 352 Q 428 362 406 364 L 406 310 Z" fill="var(--tier-dark)" opacity="0.4"/>
                        <ellipse cx="322" cy="310" rx="106" ry="15" fill="var(--tier-light)"/>
                        <ellipse cx="322" cy="308" rx="106" ry="13" fill="color-mix(in oklab, var(--tier-light), #fff 10%)"/>
                        <path d="M 216 310 Q 230 336 244 312 Q 258 340 272 312 Q 286 338 300 312 Q 314 340 322 312 Q 330 340 344 312 Q 358 338 372 312 Q 386 340 400 312 Q 414 336 428 310 Q 322 326 216 310 Z" fill="url(#cakeCreamShade)"/>
                        <circle cx="250" cy="334" r="4.5" fill="var(--dot-pink)"/>
                        <circle cx="394" cy="334" r="4.5" fill="var(--dot-green)"/>
                        <circle cx="322" cy="342" r="3" fill="var(--cream)" opacity=".9"/>
                    </g>

                    <!-- 3. KAT (her iki modda görünür) -->
                    <ellipse cx="322" cy="306" rx="82" ry="4" fill="var(--contact)" opacity=".5"/>
                    <path d="M 242 262 L 242 302 Q 242 312 322 312 Q 402 312 402 302 L 402 262 Z" fill="var(--tier-mid)"/>
                    <path d="M 366 262 L 402 262 L 402 302 Q 402 310 384 312 L 384 262 Z" fill="var(--tier-dark)" opacity="0.42"/>
                    <ellipse cx="322" cy="262" rx="80" ry="12" fill="var(--tier-light)"/>
                    <ellipse cx="322" cy="260" rx="80" ry="10" fill="color-mix(in oklab, var(--tier-light), #fff 10%)"/>
                    <path d="M 242 262 Q 254 286 266 264 Q 278 288 290 264 Q 302 288 312 264 Q 322 288 332 264 Q 342 288 354 264 Q 366 288 378 264 Q 390 286 402 262 Q 322 278 242 262 Z" fill="url(#cakeCreamShade)"/>
                    <circle cx="270" cy="286" r="4" fill="var(--dot-pink)"/>
                    <circle cx="374" cy="286" r="4" fill="var(--dot-pink)" opacity=".85"/>
                    <circle cx="322" cy="294" r="2.5" fill="var(--cream)" opacity=".9"/>

                    <!-- EN ÜST KAT -->
                    <ellipse cx="322" cy="258" rx="58" ry="3.5" fill="var(--contact)" opacity=".5"/>
                    <path d="M 266 214 L 266 254 Q 266 262 322 262 Q 378 262 378 254 L 378 214 Z" fill="var(--tier-light)"/>
                    <path d="M 352 214 L 378 214 L 378 254 Q 378 261 364 262 L 364 214 Z" fill="var(--tier-dark)" opacity="0.3"/>
                    <ellipse cx="322" cy="214" rx="56" ry="10" fill="var(--tier-mid)"/>
                    <ellipse cx="322" cy="212" rx="56" ry="8" fill="color-mix(in oklab, var(--tier-mid), #fff 10%)"/>
                    <path d="M 266 214 Q 278 234 290 216 Q 302 236 314 216 Q 322 236 330 216 Q 342 236 354 216 Q 366 234 378 214 Q 322 228 266 214 Z" fill="url(#cakeCreamShade)"/>
                    <circle cx="286" cy="236" r="3.5" fill="var(--dot-pink)"/>
                    <circle cx="358" cy="236" r="3.5" fill="var(--dot-green)"/>

                    <!-- ÇİLEK -->
                    <ellipse cx="322" cy="204" rx="24" ry="4" fill="var(--cream)"/>
                    <path d="M 302 202 Q 310 210 316 204 Q 322 212 328 204 Q 334 210 342 202 Q 338 208 334 208 L 310 208 Q 306 208 302 202 Z" fill="var(--cream)"/>
                    <path d="M 322 174 Q 334 174 336 186 Q 338 198 322 204 Q 306 198 308 186 Q 310 174 322 174 Z" fill="var(--berry)"/>
                    <g fill="var(--berry-dark)" opacity="0.85">
                        <circle cx="316" cy="184" r="1"/>
                        <circle cx="326" cy="182" r="1"/>
                        <circle cx="330" cy="190" r="1"/>
                        <circle cx="318" cy="194" r="1"/>
                        <circle cx="326" cy="196" r="1"/>
                        <circle cx="312" cy="190" r="1"/>
                    </g>
                    <ellipse cx="317" cy="180" rx="1.5" ry="2.5" fill="var(--sparkle)" opacity=".6"/>
                    <path d="M 312 176 Q 322 166 332 176 Q 327 180 322 178 Q 317 180 312 176 Z" fill="var(--leaf)"/>
                    <path d="M 322 170 L 322 176" stroke="var(--leaf)" stroke-width="1.3" stroke-linecap="round"/>

                    <!-- parıltılar -->
                    <g fill="var(--sparkle)" opacity=".7">
                        <circle cx="160" cy="250" r="1.5"/>
                        <circle cx="500" cy="300" r="1.5"/>
                        <circle cx="130" cy="370" r="1.2"/>
                        <circle cx="520" cy="370" r="1.2"/>
                    </g>
                </svg>
            </div>

            <div class="hero-cta">
                <a href="#products" class="btn btn-primary"><?= e(t('nav.products')) ?></a>
            </div>

            <!-- Öğrenci İndirim Banner (Hero altında) -->
            <div class="promo-banner" id="promoBanner">
                <div class="promo-content">
                    <div class="promo-illustration">
                        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="50" cy="92" rx="18" ry="5" fill="#D4A5B8" opacity="0.3"/>
                            <rect x="42" y="70" width="6" height="20" rx="3" fill="#5C4A42"/>
                            <rect x="52" y="70" width="6" height="20" rx="3" fill="#5C4A42"/>
                            <ellipse cx="45" cy="90" rx="5" ry="3" fill="#3D3D3D"/>
                            <ellipse cx="55" cy="90" rx="5" ry="3" fill="#3D3D3D"/>
                            <path d="M35 45 Q35 70 50 70 Q65 70 65 45 L60 40 L40 40 Z" fill="#E8C4D4"/>
                            <path d="M35 45 Q28 50 25 60" stroke="#F5E1E9" stroke-width="6" stroke-linecap="round"/>
                            <path d="M65 45 Q72 50 78 55" stroke="#F5E1E9" stroke-width="6" stroke-linecap="round"/>
                            <circle cx="25" cy="62" r="4" fill="#F5D4C1"/>
                            <circle cx="80" cy="57" r="4" fill="#F5D4C1"/>
                            <circle cx="50" cy="28" r="16" fill="#F5D4C1"/>
                            <path d="M34 25 Q34 12 50 12 Q66 12 66 25 Q66 20 50 22 Q34 20 34 25" fill="#5C4A42"/>
                            <ellipse cx="38" cy="18" rx="4" ry="3" fill="#5C4A42"/>
                            <ellipse cx="62" cy="18" rx="4" ry="3" fill="#5C4A42"/>
                            <circle cx="44" cy="27" r="2" fill="#5C4A42"/>
                            <circle cx="56" cy="27" r="2" fill="#5C4A42"/>
                            <path d="M46 33 Q50 36 54 33" stroke="#D4A5A5" stroke-width="2" stroke-linecap="round" fill="none"/>
                            <rect x="58" y="38" width="18" height="25" rx="4" fill="#8B6F5C"/>
                            <rect x="60" y="40" width="14" height="8" rx="2" fill="#A68B7B"/>
                            <rect x="64" y="50" width="6" height="4" rx="1" fill="#6B5344"/>
                            <path d="M62 38 Q62 32 68 32 Q74 32 74 38" stroke="#6B5344" stroke-width="2" fill="none"/>
                            <g transform="translate(10, 48)">
                                <ellipse cx="15" cy="18" rx="12" ry="3" fill="#E8C4D4"/>
                                <rect x="3" y="8" width="24" height="10" rx="2" fill="#F5E1E9"/>
                                <ellipse cx="15" cy="8" rx="12" ry="3" fill="#FDF8F5"/>
                                <path d="M6 6 Q9 2 12 6 Q15 2 18 6 Q21 2 24 6" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" fill="none"/>
                                <ellipse cx="15" cy="3" rx="3" ry="4" fill="#D4A5A5"/>
                                <path d="M14 0 Q15 -2 16 0" stroke="#7BA87B" stroke-width="1.5" fill="none"/>
                            </g>
                        </svg>
                    </div>
                    <div class="promo-text">
                        <span class="promo-badge">%10</span>
                        <span class="promo-message"><?= e(t('home.promo_student')) ?></span>
                    </div>
                    <button class="promo-close" aria-label="<?= e(t('common.close')) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="scroll-indicator">
            <span><?= e(t('btn.discover')) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
        </div>
    </section>

    <!-- ========== HAKKIMIZDA SECTION ========== -->
    <section class="about bg-gradient-2" id="about">
        <div class="section-texture" aria-hidden="true"></div>
        <div class="parallax-layer parallax-layer--about" aria-hidden="true"></div>
        <div class="container">
            <div class="about-content">
                <div class="about-text reveal reveal-left">
                    <h2><?= e(t('home.about_title')) ?></h2>
                    <p>
                        <?= e(t('home.about_paragraph_1')) ?>
                    </p>
                    <p>
                        <?= e(t('home.about_paragraph_2')) ?>
                    </p>
                    <a href="#contact" class="btn btn-secondary u-mt-5"><?= e(t('btn.contact_us')) ?></a>
                </div>

                <div class="about-illustration reveal reveal-right">
                    <!-- Kawaii Cupcake Karakter (Claude Design — Cupcake.html'den entegre).
                         Renkler --cup-* degiskenlerine bagli; tema (light/dark/yaz/kis +
                         dark kombinasyonlari) otomatik uygular. Bob, arm-wave, goz-kirp
                         animasyonlari SVG icinde scope'lanmis (prefers-reduced-motion korumali). -->
                    <svg class="cupcake-svg" viewBox="0 0 320 380" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Tatli Dusler cupcake karakteri" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <style>
                                .cup-ln { fill: none; stroke: var(--cup-stroke); stroke-width: 3.4; stroke-linecap: round; stroke-linejoin: round; }
                                .cup-ln-thin { fill: none; stroke: var(--cup-stroke); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
                                .cup-ln-xthin { fill: none; stroke: var(--cup-stroke); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
                                .cup-limb { fill: none; stroke: var(--cup-stroke); stroke-width: 4.5; stroke-linecap: round; stroke-linejoin: round; }
                            </style>
                        </defs>

                        <!-- zemin golgesi -->
                        <ellipse cx="160" cy="360" rx="80" ry="6" fill="var(--cup-shadow)"/>

                        <!-- BACAKLAR -->
                        <path class="cup-limb" d="M 138 326 L 138 352"/>
                        <path class="cup-limb" d="M 182 326 L 182 352"/>
                        <circle cx="138" cy="354" r="3.6" fill="var(--cup-stroke)"/>
                        <circle cx="182" cy="354" r="3.6" fill="var(--cup-stroke)"/>

                        <!-- KOLLAR (sol kol sallanir) -->
                        <g class="cup-arm-l">
                            <path class="cup-limb" d="M 102 266 C 84 268, 74 252, 76 238"/>
                            <circle cx="76" cy="236" r="3.8" fill="var(--cup-stroke)"/>
                        </g>
                        <g>
                            <path class="cup-limb" d="M 218 266 C 238 274, 248 288, 242 302"/>
                            <circle cx="242" cy="304" r="3.8" fill="var(--cup-stroke)"/>
                        </g>

                        <!-- KAGIT KALIP -->
                        <path d="M 94 222 Q 102 220 160 218 Q 218 220 226 222 L 232 320 Q 228 330 218 332 Q 200 338 160 338 Q 120 338 102 332 Q 92 330 88 320 Z" fill="var(--cup-wrap)"/>
                        <g fill="var(--cup-wrap-dark)" opacity=".9">
                            <path d="M 114 224 L 116 332 L 126 332 L 126 224 Z"/>
                            <path d="M 144 224 L 146 334 L 156 334 L 156 224 Z"/>
                            <path d="M 174 224 L 172 334 L 182 334 L 182 224 Z"/>
                            <path d="M 202 224 L 200 332 L 210 332 L 212 224 Z"/>
                        </g>
                        <path d="M 210 222 Q 224 222 226 222 L 232 320 Q 228 330 218 332 Q 214 334 208 336 L 210 224 Z" fill="var(--cup-wrap-shadow)" opacity=".35"/>

                        <!-- ust bant -->
                        <path d="M 86 222 Q 160 206 234 222 Q 226 232 160 234 Q 94 232 86 222 Z" fill="var(--cup-wrap-edge)"/>
                        <ellipse cx="150" cy="221" rx="22" ry="2.2" fill="#fff" opacity=".55"/>

                        <!-- kalip outline + pili cizgileri -->
                        <path class="cup-ln" d="M 86 222 Q 160 206 234 222 Q 226 232 160 234 Q 94 232 86 222 Z"/>
                        <path class="cup-ln" d="M 88 226 L 88 320 Q 92 330 102 332 Q 120 338 160 338 Q 200 338 218 332 Q 228 330 232 320 L 232 226"/>
                        <g class="cup-ln-thin">
                            <path d="M 112 226 L 114 330"/>
                            <path d="M 130 226 L 132 332"/>
                            <path d="M 146 226 L 148 334"/>
                            <path d="M 160 226 L 160 338"/>
                            <path d="M 174 226 L 172 334"/>
                            <path d="M 190 226 L 188 332"/>
                            <path d="M 208 226 L 206 330"/>
                        </g>

                        <!-- YUZ -->
                        <ellipse cx="126" cy="270" rx="10" ry="5" fill="var(--cup-cheek)" opacity=".85"/>
                        <ellipse cx="194" cy="270" rx="10" ry="5" fill="var(--cup-cheek)" opacity=".85"/>
                        <g class="cup-eyes">
                            <circle cx="140" cy="260" r="5" fill="var(--cup-stroke)"/>
                            <circle cx="180" cy="260" r="5" fill="var(--cup-stroke)"/>
                            <circle cx="141.6" cy="258.4" r="1.5" fill="#fff"/>
                            <circle cx="181.6" cy="258.4" r="1.5" fill="#fff"/>
                        </g>
                        <path d="M 148 278 Q 160 294 172 278 Q 166 292 160 292 Q 154 292 148 278 Z" fill="var(--cup-cherry-shade)" stroke="var(--cup-stroke)" stroke-width="2.2" stroke-linejoin="round"/>
                        <path d="M 154 286 Q 160 292 166 286 Q 162 290 160 290 Q 158 290 154 286 Z" fill="var(--cup-cherry-light)"/>

                        <!-- KEK TABANI (mafin) -->
                        <path d="M 66 202 C 86 172, 124 160, 160 160 C 196 160, 234 172, 254 202 C 254 224, 214 230, 160 230 C 106 230, 66 224, 66 202 Z" fill="var(--cup-cake)"/>
                        <path d="M 70 214 C 100 228, 140 232, 160 232 C 180 232, 220 228, 250 214 C 240 226, 206 232, 160 232 C 114 232, 80 226, 70 214 Z" fill="var(--cup-cake-shade)" opacity=".6"/>
                        <path class="cup-ln" d="M 66 202 C 86 172, 124 160, 160 160 C 196 160, 234 172, 254 202"/>
                        <path class="cup-ln" d="M 66 202 C 66 224, 106 230, 160 230 C 214 230, 254 224, 254 202"/>

                        <!-- FROSTING (3 dalga swirl) -->
                        <path d="M 72 162 C 82 118, 120 100, 160 100 C 200 100, 238 118, 248 162 C 248 176, 220 182, 160 182 C 100 182, 72 176, 72 162 Z" fill="var(--cup-frost)"/>
                        <path d="M 76 164 C 110 178, 210 178, 244 164 C 236 178, 200 182, 160 182 C 120 182, 84 178, 76 164 Z" fill="var(--cup-frost-mid)"/>
                        <path d="M 90 128 C 100 96, 130 80, 160 80 C 190 80, 220 96, 230 128 C 226 140, 200 146, 160 146 C 120 146, 94 140, 90 128 Z" fill="var(--cup-frost)"/>
                        <path d="M 94 130 C 122 142, 198 142, 226 130 C 220 142, 192 146, 160 146 C 128 146, 100 142, 94 130 Z" fill="var(--cup-frost-mid)"/>
                        <path d="M 120 92 C 128 66, 146 56, 160 56 C 174 56, 192 66, 200 92 C 196 102, 180 106, 160 106 C 140 106, 124 102, 120 92 Z" fill="var(--cup-frost)"/>
                        <path d="M 124 94 C 140 104, 180 104, 196 94 C 190 104, 178 106, 160 106 C 142 106, 130 104, 124 94 Z" fill="var(--cup-frost-mid)"/>

                        <!-- beyaz swirl highlights -->
                        <g stroke="#fff" stroke-linecap="round" fill="none" opacity=".9">
                            <path d="M 98 140 Q 130 128 160 128" stroke-width="3"/>
                            <path d="M 130 104 Q 150 92 174 94" stroke-width="2.5"/>
                            <path d="M 140 72 Q 152 64 166 66" stroke-width="2"/>
                        </g>

                        <!-- serpme sekerler -->
                        <g fill="var(--cup-sprinkle)">
                            <ellipse cx="110" cy="150" rx="2.2" ry="1.2" transform="rotate(20 110 150)"/>
                            <ellipse cx="138" cy="158" rx="2.2" ry="1.2" transform="rotate(-14 138 158)"/>
                            <ellipse cx="168" cy="156" rx="2.2" ry="1.2" transform="rotate(8 168 156)"/>
                            <ellipse cx="196" cy="152" rx="2.2" ry="1.2" transform="rotate(-20 196 152)"/>
                            <ellipse cx="222" cy="148" rx="2.2" ry="1.2" transform="rotate(22 222 148)"/>
                            <ellipse cx="122" cy="124" rx="2" ry="1.1" transform="rotate(-18 122 124)"/>
                            <ellipse cx="152" cy="120" rx="2" ry="1.1" transform="rotate(10 152 120)"/>
                            <ellipse cx="184" cy="118" rx="2" ry="1.1" transform="rotate(-8 184 118)"/>
                            <ellipse cx="212" cy="124" rx="2" ry="1.1" transform="rotate(24 212 124)"/>
                            <ellipse cx="146" cy="88" rx="1.8" ry="1" transform="rotate(-18 146 88)"/>
                            <ellipse cx="178" cy="86" rx="1.8" ry="1" transform="rotate(12 178 86)"/>
                        </g>

                        <!-- frosting outlines -->
                        <path class="cup-ln" d="M 120 92 C 128 66, 146 56, 160 56 C 174 56, 192 66, 200 92"/>
                        <path class="cup-ln-thin" d="M 124 94 C 140 104, 180 104, 196 94"/>
                        <path class="cup-ln" d="M 90 128 C 100 96, 130 80, 160 80 C 190 80, 220 96, 230 128"/>
                        <path class="cup-ln-thin" d="M 94 130 C 122 142, 198 142, 226 130"/>
                        <path class="cup-ln" d="M 72 162 C 82 118, 120 100, 160 100 C 200 100, 238 118, 248 162 C 248 176, 220 182, 160 182 C 100 182, 72 176, 72 162 Z"/>

                        <!-- KIRAZ -->
                        <path class="cup-ln" d="M 168 40 C 176 22, 192 18, 202 22"/>
                        <path d="M 196 14 C 212 12, 216 24, 206 30 C 198 30, 192 22, 196 14 Z" fill="var(--cup-leaf)"/>
                        <path class="cup-ln-thin" d="M 196 14 C 212 12, 216 24, 206 30 C 198 30, 192 22, 196 14 Z"/>
                        <path class="cup-ln-xthin" d="M 198 18 C 202 22, 205 26, 206 28"/>
                        <circle cx="160" cy="44" r="16" fill="var(--cup-cherry)"/>
                        <path d="M 146 46 C 152 60, 168 60, 174 46 C 172 58, 148 58, 146 46 Z" fill="var(--cup-cherry-shade)" opacity=".85"/>
                        <ellipse cx="152" cy="38" rx="3.2" ry="4.6" fill="#fff" opacity=".95"/>
                        <circle cx="157" cy="42" r="1.4" fill="#fff" opacity=".85"/>
                        <circle class="cup-ln" cx="160" cy="44" r="16" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== TESLİMAT BİLGİSİ SECTION ========== -->
    <section class="delivery-section bg-gradient-1" id="teslimat">
        <div class="section-texture" aria-hidden="true"></div>
        <div class="container">
            <div class="delivery-content reveal reveal-up">
                <!-- Animasyonlu Teslimat Kamyoneti -->
                <div class="delivery-animation-container">
                    <div class="delivery-truck-scene">
                        <div class="delivery-truck">
                            <!-- Kamyonet Gövdesi -->
                            <svg class="truck-body" viewBox="0 0 120 70" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <!-- Kasa -->
                                <rect x="5" y="15" width="55" height="35" rx="3" fill="#F5E1E9" stroke="#D4A5A5" stroke-width="2"/>
                                <!-- Kabin -->
                                <path d="M60 25 L60 50 L90 50 L90 35 L80 25 Z" fill="#FDF8F5" stroke="#D4A5A5" stroke-width="2"/>
                                <!-- Cam -->
                                <path d="M65 28 L65 40 L82 40 L82 33 L75 28 Z" fill="#B8D4E8" stroke="#8B9DC3" stroke-width="1"/>
                                <!-- Arka Kapı -->
                                <rect class="truck-door" x="5" y="15" width="8" height="35" rx="1" fill="#E8C4D4" stroke="#D4A5A5" stroke-width="2"/>
                                <!-- Tekerlekler -->
                                <circle cx="25" cy="55" r="10" fill="#5C4A42" stroke="#3D3028" stroke-width="2"/>
                                <circle cx="25" cy="55" r="4" fill="#8B6F5C"/>
                                <circle cx="75" cy="55" r="10" fill="#5C4A42" stroke="#3D3028" stroke-width="2"/>
                                <circle cx="75" cy="55" r="4" fill="#8B6F5C"/>
                                <!-- Far -->
                                <rect x="88" y="40" width="4" height="6" rx="1" fill="#F5D4B0"/>
                            </svg>
                            <!-- Pasta (Arka kapıdan yükleniyor) -->
                            <div class="truck-cake">
                                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <ellipse cx="20" cy="35" rx="16" ry="4" fill="#E8C4D4"/>
                                    <rect x="4" y="25" width="32" height="10" rx="2" fill="#F5E1E9"/>
                                    <ellipse cx="20" cy="25" rx="16" ry="4" fill="#FDF8F5"/>
                                    <rect x="8" y="18" width="24" height="7" rx="2" fill="#F5E1E9"/>
                                    <ellipse cx="20" cy="18" rx="12" ry="3" fill="#FDF8F5"/>
                                    <circle cx="20" cy="12" r="6" fill="#D4A5A5"/>
                                    <path d="M20 6 L20 2" stroke="#F5D4B0" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="20" cy="1" r="2" fill="#FFD700"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <h2><?= e(t('home.delivery_title')) ?></h2>
                <div class="gold-divider u-mb-6" aria-hidden="true"></div>
                <div class="delivery-cards">
                    <div class="delivery-card free">
                        <div class="delivery-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h3><?= e(t('home.delivery_city_main')) ?></h3>
                        <p class="delivery-price"><?= e(t('home.delivery_free')) ?></p>
                        <p class="delivery-desc"><?= e(t('home.delivery_city_main_desc')) ?></p>
                    </div>
                    <div class="delivery-card contact">
                        <div class="delivery-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        </div>
                        <h3><?= e(t('home.delivery_city_other')) ?></h3>
                        <p class="delivery-price"><?= e(t('home.delivery_city_other_price')) ?></p>
                        <p class="delivery-desc"><?= e(t('home.delivery_city_other_desc')) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== ÜRÜNLER SECTION ========== -->
    <section class="products bg-gradient-2" id="products">
        <div class="section-texture" aria-hidden="true"></div>
        <div class="container">
            <div class="section-header reveal reveal-up">
                <h2><?= e(t('home.products_title')) ?></h2>
                <div class="gold-divider" aria-hidden="true"></div>
                <p><?= e(t('home.products_subtitle')) ?></p>
            </div>

            <!-- Kategori Filtreleri -->
            <div class="category-filters reveal reveal-up reveal-delay-1">
                <button class="filter-btn active" data-category="all"><?= e(t('common.all')) ?></button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-category="<?= e($cat['slug']) ?>"><?= e($cat['isim']) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Ürün Grid -->
            <div class="products-grid" id="productsGrid">
                <?php
                // Sadece aktif ürünleri göster
                // NOT: getProducts() zaten JOIN ile kategori bilgilerini (kategori_ad, kategori_slug) döndürüyor
                foreach ($products as $product):
                    if (!$product['aktif']) continue;

                    // Kategori bilgisini doğrudan JOIN'den al (N+1 optimizasyonu)
                    $categorySlug = $product['kategori_slug'] ?? '';
                    $categoryName = $product['kategori_ad'] ?? '';

                    // WhatsApp mesajı için URL encode (i18n)
                    $waMessage = urlencode(t('home.whatsapp_msg_template', ['product' => $product['isim']]));

                    // Modal için JSON data
                    $productData = [
                        'isim' => $product['isim'],
                        'aciklama' => $product['aciklama'] ?? '',
                        'gorsel' => $product['gorsel'] ?? '',
                        'fiyat' => $product['fiyat'],
                        'fiyat_4kisi' => $product['fiyat_4kisi'] ?? null,
                        'fiyat_6kisi' => $product['fiyat_6kisi'] ?? null,
                        'fiyat_8kisi' => $product['fiyat_8kisi'] ?? null,
                        'fiyat_10kisi' => $product['fiyat_10kisi'] ?? null,
                        'kategori' => $categoryName,
                        'waMessage' => $waMessage
                    ];
                ?>
                <div class="product-card" data-category="<?= e($categorySlug) ?>" data-product='<?= htmlspecialchars(json_encode($productData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
                    <div class="product-image">
                        <?php if ($product['gorsel']): ?>
                            <img src="uploads/products/<?= e($product['gorsel']) ?>" alt="<?= e($product['isim']) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <!-- Varsayılan SVG İllüstrasyon -->
                            <svg viewBox="0 0 200 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <ellipse cx="100" cy="160" rx="70" ry="12" fill="#E8C4D4"/>
                                <rect x="30" y="120" width="140" height="40" rx="5" fill="#F5E1E9"/>
                                <ellipse cx="100" cy="120" rx="70" ry="12" fill="#FDF8F5"/>
                                <rect x="45" y="85" width="110" height="35" rx="4" fill="#F5E1E9"/>
                                <ellipse cx="100" cy="85" rx="55" ry="10" fill="#FDF8F5"/>
                                <path d="M60 60 Q80 40 100 50 Q120 40 140 60" fill="#FFFFFF"/>
                                <circle cx="100" cy="45" r="12" fill="#D4A5A5"/>
                                <circle cx="70" cy="95" r="5" fill="#A5C4A5"/>
                                <circle cx="130" cy="100" r="4" fill="#D4A5A5"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <?php if ($categoryName): ?>
                            <span class="product-category"><?= e($categoryName) ?></span>
                        <?php endif; ?>
                        <h3 class="product-name"><?= e($product['isim']) ?></h3>
                        <?php if ($product['aciklama']): ?>
                            <p class="product-description"><?= e($product['aciklama']) ?></p>
                        <?php endif; ?>
                        <div class="product-footer">
                            <?php
                            // Porsiyon fiyatları var mı kontrol et
                            $hasPorsiyonFiyat = !empty($product['fiyat_4kisi']) || !empty($product['fiyat_6kisi']) || !empty($product['fiyat_8kisi']) || !empty($product['fiyat_10kisi']);

                            if ($hasPorsiyonFiyat): ?>
                                <div class="product-portions">
                                    <?php if (!empty($product['fiyat_4kisi'])): ?>
                                        <span class="portion-price">4 kişi: <?= number_format($product['fiyat_4kisi'], 0, ',', '.') ?> ₺</span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['fiyat_6kisi'])): ?>
                                        <span class="portion-price">6 kişi: <?= number_format($product['fiyat_6kisi'], 0, ',', '.') ?> ₺</span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['fiyat_8kisi'])): ?>
                                        <span class="portion-price">8 kişi: <?= number_format($product['fiyat_8kisi'], 0, ',', '.') ?> ₺</span>
                                    <?php endif; ?>
                                    <?php if (!empty($product['fiyat_10kisi'])): ?>
                                        <span class="portion-price">10+ kişi: <?= number_format($product['fiyat_10kisi'], 0, ',', '.') ?> ₺</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="product-price"><?= number_format($product['fiyat'], 0, ',', '.') ?> ₺</span>
                            <?php endif; ?>
                            <a href="https://wa.me/905551234567?text=<?= $waMessage ?>" class="product-order-btn" target="_blank">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 00.917.918l4.458-1.495A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.487 0-4.807-.798-6.694-2.151l-.48-.353-3.127 1.048 1.048-3.127-.353-.48A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                                <?= e(t('home.product_order_short')) ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <div class="no-products u-grid-empty-center">
                        <p><?= e(t('home.empty_products')) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sayfalama -->
            <div class="pagination" id="productPagination">
                <!-- JavaScript ile doldurulacak -->
            </div>
        </div>
    </section>

    <!-- ========== TAKVİM SECTION ========== -->
    <section class="calendar-section bg-gradient-2" id="takvim">
        <div class="container">
            <div class="section-header reveal reveal-up">
                <h2><?= e(t('home.calendar_title')) ?></h2>
                <div class="gold-divider" aria-hidden="true"></div>
                <p><?= e(t('home.calendar_subtitle')) ?></p>
            </div>

            <div class="calendar-wrapper reveal reveal-up reveal-delay-1">
                <div class="calendar-legend">
                    <div class="legend-item">
                        <span class="legend-dot bos"></span>
                        <span><?= e(t('home.calendar_free')) ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot uygun"></span>
                        <span><?= e(t('home.calendar_suitable')) ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot yogun"></span>
                        <span><?= e(t('home.calendar_busy')) ?></span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot dolu"></span>
                        <span><?= e(t('home.calendar_full')) ?></span>
                    </div>
                </div>

                <div class="calendar-grid" id="calendarGrid">
                    <!-- JavaScript ile doldurulacak -->
                    <div class="calendar-loading">
                        <div class="loading-spinner"></div>
                        <span><?= e(t('home.calendar_loading')) ?></span>
                    </div>
                </div>

                <div class="calendar-info">
                    <p><strong><?= e(t('common.note')) ?>:</strong> <?= e(t('home.calendar_full_warning')) ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== İLETİŞİM SECTION ========== -->
    <section class="contact bg-gradient-3" id="contact">
        <div class="section-texture" aria-hidden="true"></div>
        <div class="container">
            <div class="contact-content">
                <div class="contact-info reveal reveal-left">
                    <h2><?= e(t('home.contact_title')) ?></h2>
                    <p>
                        <?= e(t('home.contact_intro')) ?>
                    </p>

                    <!-- Sadakat Programı Bilgisi -->
                    <div class="loyalty-info">
                        <div class="loyalty-item">
                            <div class="loyalty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </div>
                            <div class="loyalty-text">
                                <strong><?= e(t('home.gift_loyalty_title')) ?></strong>
                                <span><?= e(t('home.gift_loyalty_desc')) ?></span>
                            </div>
                        </div>
                        <div class="loyalty-item">
                            <div class="loyalty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                </svg>
                            </div>
                            <div class="loyalty-text">
                                <strong><?= e(t('home.gift_fifth_title')) ?></strong>
                                <span><?= e(t('home.gift_fifth_desc')) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="contact-details">
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                            <span><?= e(t('home.contact_phone')) ?></span>
                        </div>
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span><?= e(t('home.contact_email')) ?></span>
                        </div>
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span><?= e(t('home.contact_address')) ?></span>
                        </div>
                    </div>

                    <!-- Hediye Kutusu Animasyonu -->
                    <div class="gift-box-container" id="giftBoxContainer">
                        <div class="gift-box">
                            <!-- Pasta (kutudan çıkacak) -->
                            <div class="cake-wrapper">
                                <svg class="cake-svg" viewBox="0 0 120 100" fill="none">
                                    <!-- Mumlar -->
                                    <g class="candles">
                                        <rect x="35" y="15" width="4" height="20" fill="#F5D4B0" rx="2"/>
                                        <rect x="58" y="10" width="4" height="25" fill="#B8D4E8" rx="2"/>
                                        <rect x="81" y="15" width="4" height="20" fill="#E8C4C4" rx="2"/>
                                        <!-- Alevler -->
                                        <ellipse class="flame flame-1" cx="37" cy="10" rx="4" ry="6" fill="#FFD700"/>
                                        <ellipse class="flame flame-2" cx="60" cy="5" rx="4" ry="6" fill="#FFA500"/>
                                        <ellipse class="flame flame-3" cx="83" cy="10" rx="4" ry="6" fill="#FFD700"/>
                                        <ellipse class="flame-inner flame-1" cx="37" cy="11" rx="2" ry="3" fill="#FFF"/>
                                        <ellipse class="flame-inner flame-2" cx="60" cy="6" rx="2" ry="3" fill="#FFF"/>
                                        <ellipse class="flame-inner flame-3" cx="83" cy="11" rx="2" ry="3" fill="#FFF"/>
                                    </g>
                                    <!-- Pasta katmanları -->
                                    <ellipse cx="60" cy="40" rx="40" ry="8" fill="#F5E1E9"/>
                                    <rect x="20" y="40" width="80" height="25" fill="#FDF8F5" rx="3"/>
                                    <ellipse cx="60" cy="65" rx="40" ry="8" fill="#F5E1E9"/>
                                    <rect x="25" y="65" width="70" height="20" fill="#FDF8F5" rx="3"/>
                                    <ellipse cx="60" cy="85" rx="35" ry="6" fill="#E8C4D4"/>
                                    <!-- Süslemeler -->
                                    <circle cx="30" cy="52" r="3" fill="#E8C4D4"/>
                                    <circle cx="45" cy="50" r="3" fill="#B8D4B8"/>
                                    <circle cx="60" cy="52" r="3" fill="#E8C4D4"/>
                                    <circle cx="75" cy="50" r="3" fill="#B8D4E8"/>
                                    <circle cx="90" cy="52" r="3" fill="#E8C4D4"/>
                                    <circle cx="38" cy="75" r="2" fill="#F5D4B0"/>
                                    <circle cx="52" cy="73" r="2" fill="#E8C4D4"/>
                                    <circle cx="68" cy="75" r="2" fill="#F5D4B0"/>
                                    <circle cx="82" cy="73" r="2" fill="#B8D4E8"/>
                                </svg>
                            </div>
                            <!-- Kutu alt kısmı -->
                            <div class="box-bottom">
                                <svg viewBox="0 0 140 80" fill="none">
                                    <rect x="10" y="0" width="120" height="70" rx="5" fill="#E8C4D4"/>
                                    <rect x="10" y="0" width="120" height="70" rx="5" stroke="#D4A5B8" stroke-width="2"/>
                                    <rect x="60" y="0" width="20" height="70" fill="#D4A5B8"/>
                                </svg>
                            </div>
                            <!-- Kutu kapağı -->
                            <div class="box-lid">
                                <svg viewBox="0 0 150 40" fill="none">
                                    <rect x="5" y="15" width="140" height="25" rx="5" fill="#F5E1E9"/>
                                    <rect x="5" y="15" width="140" height="25" rx="5" stroke="#D4A5B8" stroke-width="2"/>
                                    <rect x="65" y="15" width="20" height="25" fill="#E8C4D4"/>
                                </svg>
                            </div>
                            <!-- Kurdele/Fiyonk -->
                            <div class="ribbon">
                                <svg viewBox="0 0 80 50" fill="none">
                                    <path class="ribbon-left" d="M40 25 Q20 10 5 20 Q15 30 40 25" fill="#D4A5B8"/>
                                    <path class="ribbon-right" d="M40 25 Q60 10 75 20 Q65 30 40 25" fill="#D4A5B8"/>
                                    <circle cx="40" cy="25" r="10" fill="#E8C4D4"/>
                                    <circle cx="40" cy="25" r="6" fill="#D4A5B8"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="contact-form reveal reveal-right" action="iletisim.php" method="POST">
                    <?= csrfTokenField() ?>
                    <!-- Honeypot - spam koruması -->
                    <input type="text" name="website" class="u-hidden" tabindex="-1" autocomplete="off">
                    <div class="form-group">
                        <label for="name"><?= e(t('form.name_label')) ?></label>
                        <input type="text" id="name" name="name" required placeholder="<?= e(t('form.placeholder_name')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="email"><?= e(t('form.email')) ?></label>
                        <input type="email" id="email" name="email" placeholder="<?= e(t('form.placeholder_email')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone"><?= e(t('form.phone')) ?></label>
                        <input type="tel" id="phone" name="phone" placeholder="<?= e(t('form.phone_placeholder_tr')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="message"><?= e(t('form.your_message')) ?></label>
                        <textarea id="message" name="message" required placeholder="<?= e(t('form.message_placeholder')) ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary u-w-100"><?= e(t('form.send')) ?></button>
                </form>
            </div>
        </div>
    </section>

    <!-- ========== SSS (FAQ) SECTION ========== -->
    <section class="faq-section bg-gradient-1" id="sss">
        <div class="container">
            <div class="section-header reveal reveal-up">
                <h2><?= e(t('home.faq_title')) ?></h2>
                <div class="gold-divider" aria-hidden="true"></div>
                <p><?= e(t('home.faq_subtitle')) ?></p>
            </div>

            <div class="faq-list reveal reveal-up reveal-delay-1">
                <!-- Porsiyon Seçenekleri -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q1_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= e(t('home.faq_q1_intro')) ?></p>
                        <ul>
                            <li><strong><?= e(t('home.faq_q1_size_4_label')) ?></strong> <?= e(t('home.faq_q1_size_4_desc')) ?></li>
                            <li><strong><?= e(t('home.faq_q1_size_6_label')) ?></strong> <?= e(t('home.faq_q1_size_6_desc')) ?></li>
                            <li><strong><?= e(t('home.faq_q1_size_8_label')) ?></strong> <?= e(t('home.faq_q1_size_8_desc')) ?></li>
                            <li><strong><?= e(t('home.faq_q1_size_10_label')) ?></strong> <?= e(t('home.faq_q1_size_10_desc')) ?></li>
                        </ul>
                        <p><?= e(t('home.faq_q1_outro')) ?></p>
                    </div>
                </div>

                <!-- Öğrenci İndirimi -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q2_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <?php // FAQ paragraf/madde içeriğinde <strong> markup'ı saklı; lang dosyası developer-controlled, XSS güvenli. ?>
                        <p><?= t('home.faq_q2_intro') ?></p>
                        <ul>
                            <li><?= t('home.faq_q2_li_1') ?></li>
                            <li><?= t('home.faq_q2_li_2') ?></li>
                            <li><?= t('home.faq_q2_li_3') ?></li>
                            <li><?= t('home.faq_q2_li_4') ?></li>
                        </ul>
                        <p><?= e(t('home.faq_q2_outro')) ?></p>
                    </div>
                </div>

                <!-- Sipariş Zamanı -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q3_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= e(t('home.faq_q3_intro')) ?></p>
                        <ul>
                            <li><?= t('home.faq_q3_li_1') ?></li>
                            <li><?= t('home.faq_q3_li_2') ?></li>
                            <li><?= t('home.faq_q3_li_3') ?></li>
                            <li><?= t('home.faq_q3_li_4') ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Özel Tasarım -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q4_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= e(t('home.faq_q4_intro')) ?></p>
                        <ul>
                            <li><?= t('home.faq_q4_li_1') ?></li>
                            <li><?= t('home.faq_q4_li_2') ?></li>
                            <li><?= e(t('home.faq_q4_li_3')) ?></li>
                            <li><?= t('home.faq_q4_li_4') ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Teslimat -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q5_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= e(t('home.faq_q5_intro')) ?></p>
                        <ul>
                            <li><?= t('home.faq_q5_li_1') ?></li>
                            <li><?= t('home.faq_q5_li_2') ?></li>
                            <li><?= e(t('home.faq_q5_li_3')) ?></li>
                            <li><?= e(t('home.faq_q5_li_4')) ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Ödeme -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span><?= e(t('home.faq_q6_question')) ?></span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p><?= e(t('home.faq_q6_intro')) ?></p>
                        <ul>
                            <li><?= t('home.faq_q6_li_1') ?></li>
                            <li><?= t('home.faq_q6_li_2') ?></li>
                            <li><?= t('home.faq_q6_li_3') ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo"><?= e(t('home.hero_title')) ?></div>
                <p class="u-note-box"><?= e(t('home.footer_tagline')) ?></p>

                <div class="footer-social">
                    <a href="#" aria-label="Instagram">
                        <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" aria-label="Facebook">
                        <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= e(t('home.footer_copyright', ['year' => date('Y')])) ?></p>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/905551234567" class="whatsapp-float" target="_blank" aria-label="<?= e(t('a11y.whatsapp_contact')) ?>">
        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>

    <!-- Ürün Detay Modal -->
    <div class="product-modal" id="productModal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <button class="modal-close" aria-label="<?= e(t('a11y.close')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-body">
                <div class="modal-image" id="modalImage">
                    <!-- Ürün görseli veya SVG buraya gelecek -->
                </div>
                <div class="modal-info">
                    <span class="modal-category" id="modalCategory"></span>
                    <h2 class="modal-title" id="modalTitle"></h2>
                    <p class="modal-description" id="modalDescription"></p>
                    <div class="modal-pricing" id="modalPricing">
                        <!-- Fiyatlar buraya gelecek -->
                    </div>
                    <a href="#" class="modal-order-btn" id="modalOrderBtn" target="_blank">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 00.917.918l4.458-1.495A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.487 0-4.807-.798-6.694-2.151l-.48-.353-3.127 1.048 1.048-3.127-.353-.48A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/>
                        </svg>
                        <?= e(t('home.whatsapp_order_btn')) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/main.js?v=3"></script>
    <script src="assets/js/theme-switcher.js?v=1.0"></script>
</body>
</html>
