<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/security.php';

// Güvenli session başlat (CSRF token için gerekli)
secureSessionStart();

// Kategorileri ve ürünleri veritabanından çek
$categories = getCategories();
$products = getProducts();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="El yapımı pastalar, cupcake'ler ve tatlılar. Özel günleriniz için butik lezzetler.">

    <title><?= e(SITE_NAME) ?> - Butik Pasta & Tatlı</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>
<body>
    <!-- Öğrenci İndirim Banner -->
    <div class="promo-banner" id="promoBanner">
        <div class="promo-content">
            <div class="promo-illustration">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Öğrenci Gövdesi -->
                    <ellipse cx="50" cy="92" rx="18" ry="5" fill="#D4A5B8" opacity="0.3"/>

                    <!-- Bacaklar -->
                    <rect x="42" y="70" width="6" height="20" rx="3" fill="#5C4A42"/>
                    <rect x="52" y="70" width="6" height="20" rx="3" fill="#5C4A42"/>

                    <!-- Ayakkabılar -->
                    <ellipse cx="45" cy="90" rx="5" ry="3" fill="#3D3D3D"/>
                    <ellipse cx="55" cy="90" rx="5" ry="3" fill="#3D3D3D"/>

                    <!-- Gövde (Tişört) -->
                    <path d="M35 45 Q35 70 50 70 Q65 70 65 45 L60 40 L40 40 Z" fill="#E8C4D4"/>

                    <!-- Kollar -->
                    <path d="M35 45 Q28 50 25 60" stroke="#F5E1E9" stroke-width="6" stroke-linecap="round"/>
                    <path d="M65 45 Q72 50 78 55" stroke="#F5E1E9" stroke-width="6" stroke-linecap="round"/>

                    <!-- Eller -->
                    <circle cx="25" cy="62" r="4" fill="#F5D4C1"/>
                    <circle cx="80" cy="57" r="4" fill="#F5D4C1"/>

                    <!-- Baş -->
                    <circle cx="50" cy="28" r="16" fill="#F5D4C1"/>

                    <!-- Saç -->
                    <path d="M34 25 Q34 12 50 12 Q66 12 66 25 Q66 20 50 22 Q34 20 34 25" fill="#5C4A42"/>
                    <ellipse cx="38" cy="18" rx="4" ry="3" fill="#5C4A42"/>
                    <ellipse cx="62" cy="18" rx="4" ry="3" fill="#5C4A42"/>

                    <!-- Yüz -->
                    <circle cx="44" cy="27" r="2" fill="#5C4A42"/>
                    <circle cx="56" cy="27" r="2" fill="#5C4A42"/>
                    <path d="M46 33 Q50 36 54 33" stroke="#D4A5A5" stroke-width="2" stroke-linecap="round" fill="none"/>

                    <!-- Sırt Çantası -->
                    <rect x="58" y="38" width="18" height="25" rx="4" fill="#8B6F5C"/>
                    <rect x="60" y="40" width="14" height="8" rx="2" fill="#A68B7B"/>
                    <rect x="64" y="50" width="6" height="4" rx="1" fill="#6B5344"/>
                    <path d="M62 38 Q62 32 68 32 Q74 32 74 38" stroke="#6B5344" stroke-width="2" fill="none"/>

                    <!-- Elde Pasta -->
                    <g transform="translate(10, 48)">
                        <!-- Pasta tabanı -->
                        <ellipse cx="15" cy="18" rx="12" ry="3" fill="#E8C4D4"/>
                        <rect x="3" y="8" width="24" height="10" rx="2" fill="#F5E1E9"/>
                        <ellipse cx="15" cy="8" rx="12" ry="3" fill="#FDF8F5"/>
                        <!-- Krema -->
                        <path d="M6 6 Q9 2 12 6 Q15 2 18 6 Q21 2 24 6" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" fill="none"/>
                        <!-- Çilek -->
                        <ellipse cx="15" cy="3" rx="3" ry="4" fill="#D4A5A5"/>
                        <path d="M14 0 Q15 -2 16 0" stroke="#7BA87B" stroke-width="1.5" fill="none"/>
                    </g>
                </svg>
            </div>
            <div class="promo-text">
                <span class="promo-badge">%10</span>
                <span class="promo-message">Üniversite Öğrencilerine <strong>İndirim!</strong></span>
            </div>
            <button class="promo-close" onclick="closePromoBanner()" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- Sparkle Container -->
    <div class="sparkle-container" id="sparkleContainer"></div>

    <!-- ========== HERO SECTION ========== -->
    <section class="hero bg-gradient-1" id="hero">
        <!-- Dekoratif Blob -->
        <div class="decoration decoration-blob float-slow" style="top: 10%; right: -100px;"></div>
        <div class="decoration decoration-circle" style="bottom: 20%; left: -150px;"></div>

        <!-- Light Orbs -->
        <div class="light-orb light-orb-1"></div>
        <div class="light-orb light-orb-2"></div>

        <!-- Star Sparkles -->
        <div class="star-sparkle" style="top: 15%; left: 10%; animation-delay: 0s;"></div>
        <div class="star-sparkle" style="top: 25%; right: 15%; animation-delay: 1s;"></div>
        <div class="star-sparkle" style="bottom: 30%; left: 8%; animation-delay: 2s;"></div>
        <div class="star-sparkle" style="bottom: 25%; right: 12%; animation-delay: 1.5s;"></div>

        <div class="hero-content">
            <div class="hero-logo">
                <h1>Tatlı Düşler</h1>
                <span>Butik Pasta & Tatlı</span>
            </div>

            <!-- Paint Tarzı Pasta İllüstrasyonu -->
            <div class="hero-illustration">
                <svg viewBox="0 0 400 350" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Pasta Tabanı -->
                    <ellipse cx="200" cy="310" rx="140" ry="25" fill="#E8C4D4"/>
                    <path d="M60 290 L60 310 Q60 335 200 335 Q340 335 340 310 L340 290 Q340 265 200 265 Q60 265 60 290Z" fill="#F5E1E9"/>

                    <!-- Alt Kat -->
                    <ellipse cx="200" cy="265" rx="130" ry="22" fill="#FDF8F5"/>
                    <rect x="70" y="200" width="260" height="65" rx="10" fill="#F5E1E9"/>
                    <ellipse cx="200" cy="200" rx="130" ry="22" fill="#FDF8F5"/>

                    <!-- Krema Süslemeleri Alt -->
                    <path d="M80 220 Q90 200 100 220 Q110 200 120 220 Q130 200 140 220" stroke="#FFFFFF" stroke-width="8" stroke-linecap="round" fill="none"/>
                    <path d="M260 220 Q270 200 280 220 Q290 200 300 220 Q310 200 320 220" stroke="#FFFFFF" stroke-width="8" stroke-linecap="round" fill="none"/>

                    <!-- Orta Kat -->
                    <ellipse cx="200" cy="200" rx="110" ry="18" fill="#E8C4D4"/>
                    <rect x="90" y="145" width="220" height="55" rx="8" fill="#F5E1E9"/>
                    <ellipse cx="200" cy="145" rx="110" ry="18" fill="#FDF8F5"/>

                    <!-- Krema Süslemeleri Orta -->
                    <path d="M100 165 Q115 145 130 165 Q145 145 160 165" stroke="#FFFFFF" stroke-width="6" stroke-linecap="round" fill="none"/>
                    <path d="M240 165 Q255 145 270 165 Q285 145 300 165" stroke="#FFFFFF" stroke-width="6" stroke-linecap="round" fill="none"/>

                    <!-- Üst Kat -->
                    <ellipse cx="200" cy="145" rx="90" ry="15" fill="#E8C4D4"/>
                    <rect x="110" y="100" width="180" height="45" rx="6" fill="#F5E1E9"/>
                    <ellipse cx="200" cy="100" rx="90" ry="15" fill="#FDF8F5"/>

                    <!-- Üst Krema -->
                    <path d="M130 60 Q150 40 170 60 Q190 40 210 60 Q230 40 250 60 Q270 40 270 60" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" fill="none"/>
                    <ellipse cx="200" cy="55" rx="60" ry="12" fill="#FFFFFF"/>

                    <!-- Çilek -->
                    <ellipse cx="200" cy="45" rx="18" ry="22" fill="#D4A5A5"/>
                    <ellipse cx="200" cy="40" rx="15" ry="18" fill="#C48B8B"/>
                    <path d="M195 25 Q200 15 205 25" stroke="#8B6F5C" stroke-width="3" fill="none"/>
                    <ellipse cx="200" cy="28" rx="8" ry="4" fill="#7BA87B"/>

                    <!-- Çilek Tohumları -->
                    <circle cx="192" cy="38" r="1.5" fill="#FDF8F5"/>
                    <circle cx="208" cy="42" r="1.5" fill="#FDF8F5"/>
                    <circle cx="195" cy="50" r="1.5" fill="#FDF8F5"/>
                    <circle cx="205" cy="35" r="1.5" fill="#FDF8F5"/>
                    <circle cx="198" cy="55" r="1.5" fill="#FDF8F5"/>

                    <!-- Bonbonlar -->
                    <circle cx="130" cy="110" r="8" fill="#D4A5A5"/>
                    <circle cx="270" cy="115" r="7" fill="#A5C4A5"/>
                    <circle cx="150" cy="155" r="6" fill="#E8C4D4"/>
                    <circle cx="250" cy="160" r="7" fill="#D4A5A5"/>
                    <circle cx="100" cy="210" r="8" fill="#A5C4A5"/>
                    <circle cx="300" cy="215" r="6" fill="#E8C4D4"/>

                    <!-- Pudra Şekeri Efekti -->
                    <circle cx="160" cy="85" r="2" fill="#FFFFFF" opacity="0.7"/>
                    <circle cx="240" cy="90" r="2" fill="#FFFFFF" opacity="0.7"/>
                    <circle cx="180" cy="140" r="2" fill="#FFFFFF" opacity="0.7"/>
                    <circle cx="220" cy="135" r="2" fill="#FFFFFF" opacity="0.7"/>
                </svg>
            </div>

            <div class="hero-cta">
                <a href="#products" class="btn btn-primary">Ürünlerimiz</a>
            </div>
        </div>

        <div class="scroll-indicator">
            <span>Keşfet</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
        </div>
    </section>

    <!-- ========== HAKKIMIZDA SECTION ========== -->
    <section class="about bg-gradient-2" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text reveal reveal-left">
                    <h2>Hikayemiz</h2>
                    <p>
                        Pastalardan cheesecake'lere, cupcake'lerden el yapımı kurabiyelere kadar
                        tüm tatlılarımızı sevgiyle ve tutkuyla hazırlıyoruz. Kaliteli malzemeler
                        ve özenle seçilmiş tariflerle sizin için en özel lezzetleri yaratıyoruz.
                    </p>
                    <p>
                        <strong>Sipariş üzerine üretim</strong> yapıyoruz; bu sayede her tatlımızın
                        tazeliğini garanti ediyoruz. Doğum günlerinden düğünlere, kutlamalardan
                        ikramlara, her özel anınızda yanınızdayız.
                    </p>
                    <a href="#contact" class="btn btn-secondary" style="margin-top: 1.5rem;">İletişime Geç</a>
                </div>

                <div class="about-illustration reveal reveal-right">
                    <!-- Cupcake İllüstrasyonu -->
                    <svg viewBox="0 0 300 280" fill="none" xmlns="http://www.w3.org/2000/svg" style="max-width: 350px;">
                        <!-- Cupcake Kabı -->
                        <path d="M70 180 L90 260 L210 260 L230 180 Z" fill="#E8C4D4"/>
                        <path d="M75 185 L80 190 L80 255 L90 260 L90 185 Z" fill="#D4A5B8" opacity="0.5"/>
                        <path d="M225 185 L220 190 L220 255 L210 260 L210 185 Z" fill="#D4A5B8" opacity="0.5"/>

                        <!-- Çizgiler -->
                        <line x1="100" y1="185" x2="95" y2="255" stroke="#D4A5B8" stroke-width="2"/>
                        <line x1="130" y1="185" x2="125" y2="255" stroke="#D4A5B8" stroke-width="2"/>
                        <line x1="160" y1="185" x2="160" y2="255" stroke="#D4A5B8" stroke-width="2"/>
                        <line x1="190" y1="185" x2="195" y2="255" stroke="#D4A5B8" stroke-width="2"/>

                        <!-- Kek Kısmı -->
                        <ellipse cx="150" cy="180" rx="85" ry="20" fill="#F5E1E9"/>
                        <path d="M65 180 Q65 130 150 130 Q235 130 235 180" fill="#8B6F5C"/>
                        <path d="M65 180 Q65 160 150 160 Q235 160 235 180" fill="#A68B7B"/>

                        <!-- Krema -->
                        <path d="M80 130 Q100 80 150 70 Q200 80 220 130" fill="#FDF8F5"/>
                        <path d="M90 120 Q110 90 150 85 Q190 90 210 120" fill="#FFFFFF"/>

                        <!-- Krema Swirl -->
                        <path d="M120 70 Q140 30 150 50 Q160 30 180 70" fill="#F5E1E9"/>
                        <ellipse cx="150" cy="50" rx="25" ry="15" fill="#FDF8F5"/>

                        <!-- Kiraz -->
                        <circle cx="150" cy="35" r="15" fill="#D4A5A5"/>
                        <circle cx="150" cy="32" r="12" fill="#C48B8B"/>
                        <path d="M150 20 Q155 5 165 10" stroke="#7BA87B" stroke-width="3" fill="none"/>
                        <ellipse cx="160" cy="12" rx="6" ry="4" fill="#7BA87B"/>

                        <!-- Işıltı -->
                        <circle cx="145" cy="28" r="3" fill="#FFFFFF" opacity="0.6"/>

                        <!-- Sprinkles -->
                        <rect x="100" y="95" width="8" height="3" rx="1" fill="#D4A5A5" transform="rotate(-20 100 95)"/>
                        <rect x="180" y="100" width="8" height="3" rx="1" fill="#A5C4A5" transform="rotate(15 180 100)"/>
                        <rect x="130" y="110" width="6" height="3" rx="1" fill="#E8C4D4" transform="rotate(-10 130 110)"/>
                        <rect x="160" y="105" width="7" height="3" rx="1" fill="#8B6F5C" transform="rotate(25 160 105)"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== TESLİMAT BİLGİSİ SECTION ========== -->
    <section class="delivery-section bg-gradient-1" id="teslimat">
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
                <h2>Teslimat Bilgisi</h2>
                <div class="delivery-cards">
                    <div class="delivery-card free">
                        <div class="delivery-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h3>Gazimağusa</h3>
                        <p class="delivery-price">Ücretsiz Teslimat</p>
                        <p class="delivery-desc">Gazimağusa içindeki tüm adreslerinize ücretsiz teslimat yapıyoruz.</p>
                    </div>
                    <div class="delivery-card contact">
                        <div class="delivery-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        </div>
                        <h3>Diğer Şehirler</h3>
                        <p class="delivery-price">İletişime Geçin</p>
                        <p class="delivery-desc">Diğer şehirlere teslimat için lütfen bizimle iletişime geçin.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== ÜRÜNLER SECTION ========== -->
    <section class="products bg-gradient-2" id="products">
        <div class="container">
            <div class="section-header reveal reveal-up">
                <h2>Lezzetlerimiz</h2>
                <p>El yapımı, taze ve her biri özenle hazırlanmış ürünlerimiz</p>
            </div>

            <!-- Kategori Filtreleri -->
            <div class="category-filters reveal reveal-up reveal-delay-1">
                <button class="filter-btn active" data-category="all">Tümü</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-category="<?= e($cat['slug']) ?>"><?= e($cat['ad']) ?></button>
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

                    // WhatsApp mesajı için URL encode
                    $waMessage = urlencode("Merhaba, " . $product['ad'] . " hakkında bilgi almak istiyorum.");

                    // Modal için JSON data
                    $productData = [
                        'isim' => $product['ad'],
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
                            <img src="uploads/products/<?= e($product['gorsel']) ?>" alt="<?= e($product['ad']) ?>">
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
                        <h3 class="product-name"><?= e($product['ad']) ?></h3>
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
                                Sipariş
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <p>Henüz ürün bulunmamaktadır.</p>
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
                <h2>Müsaitlik Takvimi</h2>
                <p>Sipariş vermeden önce uygunluk durumumuzu kontrol edin</p>
            </div>

            <div class="calendar-wrapper reveal reveal-up reveal-delay-1">
                <div class="calendar-legend">
                    <div class="legend-item">
                        <span class="legend-dot bos"></span>
                        <span>Boş</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot uygun"></span>
                        <span>Uygun</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot yogun"></span>
                        <span>Yoğun</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot dolu"></span>
                        <span>Dolu</span>
                    </div>
                </div>

                <div class="calendar-grid" id="calendarGrid">
                    <!-- JavaScript ile doldurulacak -->
                    <div class="calendar-loading">
                        <div class="loading-spinner"></div>
                        <span>Takvim yükleniyor...</span>
                    </div>
                </div>

                <div class="calendar-info">
                    <p><strong>Not:</strong> "Dolu" günlerde sipariş kabul edemeyebiliriz. Lütfen önceden iletişime geçin.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== İLETİŞİM SECTION ========== -->
    <section class="contact bg-gradient-3" id="contact">
        <div class="container">
            <div class="contact-content">
                <div class="contact-info reveal reveal-left">
                    <h2>İletişim</h2>
                    <p>
                        Özel günleriniz için sipariş vermek veya sorularınız için
                        bizimle iletişime geçebilirsiniz.
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
                                <strong>%5 Sadakat İndirimi</strong>
                                <span>İlk siparişinizden memnun kalıp tekrar sipariş verirseniz, kayıtlı adreslerinize %5 indirim!</span>
                            </div>
                        </div>
                        <div class="loyalty-item">
                            <div class="loyalty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                                    <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                </svg>
                            </div>
                            <div class="loyalty-text">
                                <strong>5. Siparişe Hediye!</strong>
                                <span>Her 5. siparişinizde 6'lı damla çikolatalı cookie hediye!</span>
                            </div>
                        </div>
                    </div>

                    <div class="contact-details">
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                            <span>0555 123 45 67</span>
                        </div>
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <span>info@tatlidusler.com</span>
                        </div>
                        <div class="contact-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <span>İstanbul, Türkiye</span>
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
                    <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off">
                    <div class="form-group">
                        <label for="name">Adınız</label>
                        <input type="text" id="name" name="name" required placeholder="Adınız Soyadınız">
                    </div>
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" placeholder="ornek@email.com">
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone" placeholder="05XX XXX XX XX">
                    </div>
                    <div class="form-group">
                        <label for="message">Mesajınız</label>
                        <textarea id="message" name="message" required placeholder="Mesajınızı buraya yazın..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Gönder</button>
                </form>
            </div>
        </div>
    </section>

    <!-- ========== SSS (FAQ) SECTION ========== -->
    <section class="faq-section bg-gradient-1" id="sss">
        <div class="container">
            <div class="section-header reveal reveal-up">
                <h2>Sıkça Sorulan Sorular</h2>
                <p>Merak ettiğiniz soruların cevapları</p>
            </div>

            <div class="faq-list reveal reveal-up reveal-delay-1">
                <!-- Porsiyon Seçenekleri -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Pasta boyutları ve kişi sayıları nasıl belirleniyor?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Pastalarımız 4 farklı boyutta sunulmaktadır:</p>
                        <ul>
                            <li><strong>4 Kişilik:</strong> Küçük kutlamalar ve özel günler için ideal</li>
                            <li><strong>6 Kişilik:</strong> Aile içi kutlamalar için uygun</li>
                            <li><strong>8 Kişilik:</strong> Orta ölçekli partiler için</li>
                            <li><strong>10+ Kişilik:</strong> Büyük organizasyonlar ve etkinlikler için</li>
                        </ul>
                        <p>Her boyutun fiyatı ürün kartında ayrı ayrı belirtilmiştir.</p>
                    </div>
                </div>

                <!-- Öğrenci İndirimi -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Üniversite öğrenci indiriminden nasıl yararlanabilirim?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Üniversite öğrencilerine özel <strong>%10 indirim</strong> sunuyoruz!</p>
                        <ul>
                            <li>İndirimden yararlanmak için <strong>geçerli üniversite öğrenci kartınızı</strong> göstermeniz zorunludur.</li>
                            <li>İndirim, teslimat sırasında veya mağazadan teslim alırken uygulanır.</li>
                            <li>Öğrenci kartı ibraz edilmediği takdirde indirim uygulanamamaktadır.</li>
                            <li>Bu indirim diğer kampanyalarla birleştirilemez.</li>
                        </ul>
                        <p>Öğrenci kartınızı yanınızda bulundurmayı unutmayın!</p>
                    </div>
                </div>

                <!-- Sipariş Zamanı -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Sipariş ne zaman verilmeli?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Sipariş zamanlamanız takvimimizdeki müsaitlik durumuna göre belirlenir:</p>
                        <ul>
                            <li>Takvimde <strong>"Boş"</strong> veya <strong>"Uygun"</strong> olan günler için 1 gün öncesinden veya aynı gün sipariş verebilirsiniz.</li>
                            <li><strong>"Yoğun"</strong> günler için en az 2-3 gün önceden sipariş vermenizi öneririz.</li>
                            <li><strong>"Dolu"</strong> günlerde ne yazık ki sipariş alamıyoruz.</li>
                            <li><strong>Acil siparişler</strong> için lütfen WhatsApp veya telefon ile iletişime geçin.</li>
                        </ul>
                    </div>
                </div>

                <!-- Özel Tasarım -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Özel tasarım pasta yaptırabilir miyim?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Evet! Özel tasarım pastalar yapıyoruz. Ancak şu hususları bilmeniz önemli:</p>
                        <ul>
                            <li>Özel tasarım pastaların fiyatı <strong>içeriği, boyutu ve harcanan vakite göre</strong> belirlenir.</li>
                            <li>Bu nedenle özel tasarımlar için <strong>önceden planlama ve görüşme</strong> gerekmektedir.</li>
                            <li>Tasarımınızı konuşmak için lütfen iletişim formu veya WhatsApp üzerinden bize ulaşın.</li>
                            <li>Özel tasarımlar için en az <strong>3-5 gün önceden</strong> sipariş vermenizi öneririz.</li>
                        </ul>
                    </div>
                </div>

                <!-- Teslimat -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Teslimat nasıl yapılıyor?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Teslimat politikamız şehre göre değişmektedir:</p>
                        <ul>
                            <li><strong>Gazimağusa:</strong> Tüm adreslerinize <strong>ücretsiz teslimat</strong> yapıyoruz.</li>
                            <li><strong>Diğer şehirler:</strong> Teslimat imkanı ve ücretlendirme için lütfen iletişime geçin.</li>
                            <li>Teslimat saati siparişi verirken belirlenir.</li>
                            <li>Özel gün ve bayramlarda teslimat yoğunluğu olabilir, erken sipariş vermenizi öneririz.</li>
                        </ul>
                    </div>
                </div>

                <!-- Ödeme -->
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Ödeme nasıl yapılıyor?</span>
                        <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Ödeme seçeneklerimiz:</p>
                        <ul>
                            <li><strong>Nakit:</strong> Teslimat sırasında ödeme yapabilirsiniz.</li>
                            <li><strong>Havale/EFT:</strong> Sipariş onayından sonra banka bilgileri iletilir.</li>
                            <li>Özel tasarım siparişlerde <strong>%50 ön ödeme</strong> alınabilir.</li>
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
                <div class="footer-logo">Tatlı Düşler</div>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">El yapımı lezzetler</p>

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
                <p>&copy; 2024 Tatlı Düşler. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/905551234567" class="whatsapp-float" target="_blank" aria-label="WhatsApp ile iletişime geç">
        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>

    <!-- Ürün Detay Modal -->
    <div class="product-modal" id="productModal">
        <div class="modal-overlay" onclick="closeProductModal()"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeProductModal()" aria-label="Kapat">
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
                        WhatsApp ile Sipariş Ver
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/main.js?v=2"></script>
</body>
</html>
