<?php
/**
 * Dijital Menu Sayfasi
 *
 * QR kod ile erisilen musteri menusu.
 * Tamamen mobil oncelikli, standalone sayfa.
 *
 * @package Pastane
 * @since 1.0.0
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// ============================================
// QR TOKEN DOGRULAMA
// ============================================

$qrToken = $_GET['t'] ?? '';
$appName = config('app.name', 'Tatli Dusler');

// Token yoksa hata sayfasi (Bad Request)
if (empty($qrToken)) {
    renderHataSayfasi($appName, t('error.qr_required'), t('error.qr_required_desc'), 'qr', 400);
    exit;
}

// Oturum dogrulama
$oturumService = masa_oturum_service();
$sonuc = $oturumService->oturumDogrula($qrToken);

if (!$sonuc['gecerli']) {
    $mesaj = $sonuc['mesaj'] ?? t('error.table_inactive_desc');
    renderHataSayfasi($appName, t('error.table_inactive'), $mesaj, 'masa', 403);
    exit;
}

// ============================================
// OTURUM COOKIE AYARLA
// ============================================

$oturumToken = $sonuc['oturum_token'];
$masa = $sonuc['masa'];
$masaNo = $masa['masa_no'];
$masaId = $masa['id'];

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
setcookie('oturum_token', $oturumToken, [
    'expires'  => time() + 86400,
    'path'     => '/',
    'httponly'  => true,
    'samesite' => 'Strict',
    'secure'   => $isSecure,
]);

// ============================================
// VERILERI GETIR
// ============================================

$kategoriler = kategori_service()->all(['*'], 'sira', 'ASC');
$urunler = urun_service()->getActive();

// cafe_menusu = 0 olanlari filtrele (tukenmisleri goster ama siparis edilemez yap)
$menuUrunler = array_filter($urunler, function ($u) {
    return ($u['cafe_menusu'] ?? 1) == 1;
});

// Kategorileri ID'ye gore indexle
$kategoriMap = [];
foreach ($kategoriler as $k) {
    $kategoriMap[$k['id']] = $k;
}

// Menude kullanilan kategorileri belirle
$menuKategoriIds = [];
foreach ($menuUrunler as $u) {
    if (!empty($u['kategori_id'])) {
        $menuKategoriIds[$u['kategori_id']] = true;
    }
}

// Urunleri JSON'a hazirla (sepet icin)
$urunJsonData = [];
foreach ($menuUrunler as $u) {
    $porsiyonlar = [];
    if (!empty($u['fiyat'])) {
        $porsiyonlar[] = ['label' => 'Normal', 'fiyat' => (float)$u['fiyat']];
    }
    if (!empty($u['fiyat_4kisi'])) {
        $porsiyonlar[] = ['label' => '4 Kişilik', 'fiyat' => (float)$u['fiyat_4kisi']];
    }
    if (!empty($u['fiyat_6kisi'])) {
        $porsiyonlar[] = ['label' => '6 Kişilik', 'fiyat' => (float)$u['fiyat_6kisi']];
    }
    if (!empty($u['fiyat_8kisi'])) {
        $porsiyonlar[] = ['label' => '8 Kişilik', 'fiyat' => (float)$u['fiyat_8kisi']];
    }
    if (!empty($u['fiyat_10kisi'])) {
        $porsiyonlar[] = ['label' => '10 Kişilik', 'fiyat' => (float)$u['fiyat_10kisi']];
    }

    $urunJsonData[$u['id']] = [
        'id'          => (int)$u['id'],
        'ad'          => $u['isim'],
        'fiyat'       => (float)$u['fiyat'],
        'stok'        => $u['stok_durumu'] ?? 'var',
        'porsiyonlar' => $porsiyonlar,
    ];
}

$siteUrl = rtrim(config('app.url', ''), '/');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#8B4513">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($appName) ?> - <?= e(t('order.your_table', ['no' => (int)$masaNo])) ?></title>

    <link rel="stylesheet" href="assets/css/menu-base.css">

    <style>
    body {
        overflow-x: hidden;
        padding-bottom: 80px; /* alt bar icin */
    }

    /* ============================================
       KATEGORI TAB'LARI
       ============================================ */
    .category-tabs {
        position: sticky;
        top: 60px;
        z-index: 90;
        background: #FAF7F4;
        border-bottom: 1px solid #E8DDD4;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
        white-space: nowrap;
        padding: 0 12px;
    }

    .category-tabs::-webkit-scrollbar { display: none; }

    .category-tabs__inner {
        display: inline-flex;
        gap: 6px;
        padding: 10px 0;
    }

    .category-tab {
        display: inline-block;
        padding: 8px 18px;
        border-radius: 24px;
        font-size: 0.875rem;
        font-weight: 500;
        color: #7A6855;
        background: #F0E8DF;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        -webkit-user-select: none;
        user-select: none;
        min-height: 44px;
        line-height: 1.4;
    }

    .category-tab:active { transform: scale(0.95); }

    .category-tab--active {
        background: #8B4513;
        color: #fff;
        box-shadow: 0 2px 8px rgba(139, 69, 19, 0.3);
    }

    /* ============================================
       URUN GRID
       ============================================ */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 16px 12px;
        max-width: 600px;
        margin: 0 auto;
    }

    @media (min-width: 480px) {
        .products-grid { gap: 16px; padding: 16px; }
    }

    /* Cok dar viewport icin 1-column */
    @media (max-width: 360px) {
        .products-grid { grid-template-columns: 1fr; gap: 10px; }
    }

    /* WCAG Focus Visible */
    .category-tab:focus-visible,
    .btn-add:focus-visible,
    .qty-btn:focus-visible,
    .cart-bar__btn:focus-visible,
    .modal-sheet__add-btn:focus-visible,
    .portion-option:focus-visible {
        outline: 3px solid #C5A572;
        outline-offset: 2px;
    }

    /* Skip link */
    .skip-link {
        position: absolute;
        top: -48px;
        left: 0;
        background: #8B4513;
        color: #fff;
        padding: 12px 16px;
        z-index: 10000;
        font-weight: 600;
        border-radius: 0 0 8px 0;
        text-decoration: none;
        transition: top 0.2s ease;
    }

    .skip-link:focus,
    .skip-link:focus-visible {
        top: 0;
        outline: 3px solid #FFD700;
        outline-offset: 2px;
    }

    /* ============================================
       URUN KARTI
       ============================================ */
    .product-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(61, 43, 31, 0.08);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
    }

    .product-card:active { transform: scale(0.98); }

    .product-card--out-of-stock { opacity: 0.65; }

    .product-card__img-wrap {
        position: relative;
        width: 100%;
        padding-top: 75%; /* 4:3 orani */
        background: #F5EDE6;
        overflow: hidden;
    }

    .product-card__img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: opacity 0.3s ease;
    }

    .product-card__img[data-src] { opacity: 0; }
    .product-card__img.loaded { opacity: 1; }

    .product-card__img-placeholder {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #C4B5A6;
        font-size: 2.5rem;
    }

    .product-card__badge {
        position: absolute;
        top: 8px;
        left: 8px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge--out { background: #DC3545; color: #fff; }
    .badge--limited { background: #FFC107; color: #3D2B1F; }

    .product-card__body {
        padding: 10px 12px 12px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .product-card__name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #3D2B1F;
        margin-bottom: 4px;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-card__desc {
        font-size: 0.75rem;
        color: #7A6855; /* WCAG AA contrast: onceki #9B8B7A 3.21:1 FAIL -> #7A6855 5.25:1 PASS */
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .product-card__footer {
        margin-top: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }

    .product-card__price {
        font-size: 1rem;
        font-weight: 700;
        color: #8B4513;
    }

    .product-card__price small {
        font-size: 0.7rem;
        font-weight: 400;
        color: #9B8B7A;
        display: block;
    }

    /* ============================================
       SEPETE EKLE BUTONU
       ============================================ */
    .btn-add {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: #8B4513;
        color: #fff;
        font-size: 1.3rem;
        font-weight: 300;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
        -webkit-user-select: none;
        user-select: none;
    }

    .btn-add:active { transform: scale(0.9); background: #6B3410; }
    .btn-add:disabled { background: #C4B5A6; cursor: not-allowed; }

    /* Adet secici (eklenmis urun icin) */
    .qty-control {
        display: flex;
        align-items: center;
        gap: 0;
        background: #F5EDE6;
        border-radius: 24px;
        overflow: hidden;
    }

    .qty-btn {
        width: 44px;
        height: 44px;
        border: none;
        background: transparent;
        color: #8B4513;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-user-select: none;
        user-select: none;
    }

    .qty-btn:active { background: rgba(139, 69, 19, 0.1); }

    .qty-value {
        min-width: 24px;
        text-align: center;
        font-size: 0.9rem;
        font-weight: 700;
        color: #3D2B1F;
    }

    /* ============================================
       PORSIYON SECIM MODAL
       ============================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 200;
        align-items: flex-end;
        justify-content: center;
    }

    .modal-overlay.active { display: flex; }

    .modal-sheet {
        background: #fff;
        border-radius: 24px 24px 0 0;
        width: 100%;
        max-width: 480px;
        max-height: 70vh;
        max-height: 70dvh; /* iOS Safari adres cubugu fix (dinamik viewport) */
        overflow-y: auto;
        padding: 20px 20px 32px;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }

    .modal-sheet__handle {
        width: 40px;
        height: 4px;
        background: #D4C9BE;
        border-radius: 2px;
        margin: 0 auto 16px;
    }

    .modal-sheet__title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #3D2B1F;
        margin-bottom: 4px;
    }

    .modal-sheet__subtitle {
        font-size: 0.85rem;
        color: #9B8B7A;
        margin-bottom: 16px;
    }

    .portion-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border: 2px solid #F0E8DF;
        border-radius: 12px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        -webkit-user-select: none;
        user-select: none;
    }

    .portion-option:active,
    .portion-option.selected {
        border-color: #8B4513;
        background: #FDF8F4;
    }

    .portion-option__label {
        font-size: 0.95rem;
        font-weight: 500;
        color: #3D2B1F;
    }

    .portion-option__price {
        font-size: 1rem;
        font-weight: 700;
        color: #8B4513;
    }

    .modal-sheet__add-btn {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 14px;
        background: #8B4513;
        color: #fff;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 16px;
        transition: background 0.2s ease;
    }

    .modal-sheet__add-btn:active { background: #6B3410; }

    /* ============================================
       ALT BAR - SEPET
       ============================================ */
    .cart-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 100;
        background: #fff;
        border-top: 1px solid #E8DDD4;
        padding: 10px 16px;
        padding-bottom: calc(10px + env(safe-area-inset-bottom, 0px));
        box-shadow: 0 -2px 12px rgba(61, 43, 31, 0.1);
        transform: translateY(100%);
        transition: transform 0.3s ease;
    }

    .cart-bar--visible { transform: translateY(0); }

    .cart-bar__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 480px;
        margin: 0 auto;
    }

    .cart-bar__info {
        display: flex;
        flex-direction: column;
    }

    .cart-bar__count {
        font-size: 0.85rem;
        color: #7A6855;
    }

    .cart-bar__total {
        font-size: 1.15rem;
        font-weight: 700;
        color: #3D2B1F;
    }

    .cart-bar__btn {
        padding: 12px 28px;
        border: none;
        border-radius: 14px;
        background: #8B4513;
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s ease;
        -webkit-user-select: none;
        user-select: none;
    }

    .cart-bar__btn:active { background: #6B3410; }

    .cart-bar__badge {
        background: rgba(255,255,255,0.25);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
    }

    /* ============================================
       BOS DURUM
       ============================================ */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
        color: #7A6855; /* WCAG AA contrast fix (onceki #9B8B7A) */
    }

    .empty-state__icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-state__text { font-size: 0.95rem; }

    /* ============================================
       TOAST BILDIRIM
       ============================================ */
    .toast {
        position: fixed;
        bottom: 90px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: #3D2B1F;
        color: #fff;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
        z-index: 300;
        opacity: 0;
        transition: all 0.3s ease;
        pointer-events: none;
        white-space: nowrap;
        max-width: 90vw;
        text-align: center;
    }

    .toast--show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }

    /* ============================================
       YÜKLENIYOR ANIMASYONU
       ============================================ */
    .skeleton {
        background: linear-gradient(90deg, #F0E8DF 25%, #F5EDE6 50%, #F0E8DF 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 8px;
    }

    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    </style>
</head>
<body>
    <a href="#productsGrid" class="skip-link">Menüye atla</a>

    <!-- UST BAR -->
    <header class="top-bar">
        <div class="top-bar__brand">
            <div class="top-bar__logo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 010 8h-1"/>
                    <path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/>
                    <line x1="6" y1="1" x2="6" y2="4"/>
                    <line x1="10" y1="1" x2="10" y2="4"/>
                    <line x1="14" y1="1" x2="14" y2="4"/>
                </svg>
            </div>
            <span class="top-bar__name"><?= e($appName) ?></span>
        </div>
        <span class="top-bar__masa"><?= e(t('order.your_table', ['no' => (int)$masaNo])) ?></span>
    </header>

    <!-- KATEGORI TAB'LARI -->
    <nav class="category-tabs" id="categoryTabs">
        <div class="category-tabs__inner">
            <button class="category-tab category-tab--active" data-category="all" type="button"><?= e(t('common.all')) ?></button>
            <?php foreach ($kategoriler as $kat): ?>
                <?php if (isset($menuKategoriIds[$kat['id']])): ?>
                    <button class="category-tab" data-category="<?= (int)$kat['id'] ?>" type="button">
                        <?= e($kat['isim']) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- URUN GRID -->
    <main class="products-grid" id="productsGrid" tabindex="-1" aria-label="<?= e(t('nav.products')) ?>">
        <?php foreach ($menuUrunler as $urun): ?>
            <?php
                $stokDurumu = $urun['stok_durumu'] ?? 'var';
                $tukendi = ($stokDurumu === 'tukendi');
                $sinirli = ($stokDurumu === 'sinirli');
                $gorselYol = !empty($urun['gorsel']) ? $siteUrl . '/' . $urun['gorsel'] : '';

                // Coklu porsiyon var mi?
                $cokluPorsiyon = !empty($urun['fiyat_4kisi']) || !empty($urun['fiyat_6kisi'])
                                || !empty($urun['fiyat_8kisi']) || !empty($urun['fiyat_10kisi']);
            ?>
            <div class="product-card<?= $tukendi ? ' product-card--out-of-stock' : '' ?>"
                 data-category="<?= (int)$urun['kategori_id'] ?>"
                 data-product-id="<?= (int)$urun['id'] ?>">

                <!-- Gorsel -->
                <div class="product-card__img-wrap">
                    <?php if ($gorselYol): ?>
                        <img class="product-card__img"
                             src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                             data-src="<?= e($gorselYol) ?>"
                             alt="<?= e($urun['isim']) ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="product-card__img-placeholder">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </div>
                    <?php endif; ?>

                    <?php if ($tukendi): ?>
                        <span class="product-card__badge badge--out"><?= e(t('order.out_of_stock')) ?></span>
                    <?php elseif ($sinirli): ?>
                        <span class="product-card__badge badge--limited"><?= e(t('order.limited_stock')) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Icerik -->
                <div class="product-card__body">
                    <h3 class="product-card__name"><?= e($urun['isim']) ?></h3>

                    <?php if (!empty($urun['aciklama'])): ?>
                        <p class="product-card__desc"><?= e(str_limit($urun['aciklama'], 60)) ?></p>
                    <?php endif; ?>

                    <div class="product-card__footer">
                        <div class="product-card__price">
                            <?= money((float)$urun['fiyat']) ?>
                            <?php if ($cokluPorsiyon): ?>
                                <small><?= e(t('common.and_more')) ?></small>
                            <?php endif; ?>
                        </div>

                        <?php if (!$tukendi): ?>
                            <div class="product-card__actions" id="actions-<?= (int)$urun['id'] ?>">
                                <button class="btn-add"
                                        type="button"
                                        data-action="add-to-cart"
                                        data-urun-id="<?= (int)$urun['id'] ?>"
                                        aria-label="<?= e($urun['isim']) ?> — <?= e(t('btn.add_to_cart')) ?>">+</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($menuUrunler)): ?>
            <div class="empty-state u-grid-col-full">
                <div class="empty-state__icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4">
                        <path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/>
                    </svg>
                </div>
                <p class="empty-state__text"><?= e(t('order.menu_empty')) ?></p>
            </div>
        <?php endif; ?>
    </main>

    <!-- PORSIYON SECIM MODALI -->
    <div class="modal-overlay" id="portionModal">
        <div class="modal-sheet">
            <div class="modal-sheet__handle"></div>
            <h3 class="modal-sheet__title" id="modalTitle"></h3>
            <p class="modal-sheet__subtitle"><?= e(t('order.select_portion')) ?></p>
            <div id="portionOptions"></div>
            <button class="modal-sheet__add-btn" id="modalAddBtn" type="button"><?= e(t('btn.add_to_cart')) ?></button>
        </div>
    </div>

    <!-- ALT BAR - SEPET -->
    <div class="cart-bar" id="cartBar">
        <div class="cart-bar__inner">
            <div class="cart-bar__info">
                <span class="cart-bar__count" id="cartCount"><?= e(t('order.cart_count', ['count' => 0])) ?></span>
                <span class="cart-bar__total" id="cartTotal"><?= money(0) ?></span>
            </div>
            <button class="cart-bar__btn" type="button" data-action="go-to-cart">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>
                </svg>
                <?= e(t('btn.view_cart')) ?>
                <span class="cart-bar__badge" id="cartBadge">0</span>
            </button>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast"></div>

    <script src="assets/js/menu-utils.js"></script>
    <script>
    (function () {
        'use strict';

        // ============================================
        // VERI
        // ============================================

        var PRODUCTS = <?= json_encode($urunJsonData, JSON_UNESCAPED_UNICODE) ?>;
        var MASA_ID = <?= (int)$masaId ?>;
        var CART_KEY = 'pastane_cart_' + MASA_ID;

        // ============================================
        // SEPET YONETIMI (localStorage)
        // ============================================

        function getCart() {
            try {
                var data = localStorage.getItem(CART_KEY);
                return data ? JSON.parse(data) : [];
            } catch (e) {
                return [];
            }
        }

        function saveCart(cart) {
            try {
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
            } catch (e) {}
        }

        function addToCart(urunId, urunAdi, fiyat, porsiyon, porsiyonLabel, adet) {
            var cart = getCart();
            var key = urunId + '_' + porsiyon;

            var existing = null;
            for (var i = 0; i < cart.length; i++) {
                if (cart[i].key === key) {
                    existing = cart[i];
                    break;
                }
            }

            if (existing) {
                existing.adet += adet;
            } else {
                cart.push({
                    key: key,
                    urunId: urunId,
                    urunAdi: urunAdi,
                    fiyat: fiyat,
                    porsiyon: porsiyon,
                    porsiyonLabel: porsiyonLabel,
                    adet: adet,
                    ozelNot: ''
                });
            }

            saveCart(cart);
            updateCartUI();
            updateProductActions(urunId);
            showToast(urunAdi + ' sepete eklendi');
        }

        function removeFromCart(urunId, porsiyon) {
            var cart = getCart();
            var key = urunId + '_' + porsiyon;
            cart = cart.filter(function (item) { return item.key !== key; });
            saveCart(cart);
            updateCartUI();
            updateProductActions(urunId);
        }

        function updateCartItemQty(urunId, porsiyon, delta) {
            var cart = getCart();
            var key = urunId + '_' + porsiyon;

            for (var i = 0; i < cart.length; i++) {
                if (cart[i].key === key) {
                    cart[i].adet += delta;
                    if (cart[i].adet <= 0) {
                        cart.splice(i, 1);
                    }
                    break;
                }
            }

            saveCart(cart);
            updateCartUI();
            updateProductActions(urunId);
        }

        function getCartItemCount(urunId) {
            var cart = getCart();
            var total = 0;
            for (var i = 0; i < cart.length; i++) {
                if (cart[i].urunId === urunId) {
                    total += cart[i].adet;
                }
            }
            return total;
        }

        function getCartTotals() {
            var cart = getCart();
            var count = 0;
            var total = 0;
            for (var i = 0; i < cart.length; i++) {
                count += cart[i].adet;
                total += cart[i].fiyat * cart[i].adet;
            }
            return { count: count, total: total };
        }

        // ============================================
        // UI GUNCELLEME
        // ============================================

        function updateCartUI() {
            var totals = getCartTotals();
            var cartBar = document.getElementById('cartBar');
            var cartCount = document.getElementById('cartCount');
            var cartTotal = document.getElementById('cartTotal');
            var cartBadge = document.getElementById('cartBadge');

            cartCount.textContent = totals.count + ' ürün';
            cartTotal.textContent = formatPrice(totals.total);
            cartBadge.textContent = totals.count;

            if (totals.count > 0) {
                cartBar.classList.add('cart-bar--visible');
            } else {
                cartBar.classList.remove('cart-bar--visible');
            }
        }

        function updateProductActions(urunId) {
            var actionsEl = document.getElementById('actions-' + urunId);
            if (!actionsEl) return;

            var product = PRODUCTS[urunId];
            if (!product) return;

            var count = getCartItemCount(urunId);

            if (count === 0) {
                actionsEl.innerHTML =
                    '<button class="btn-add" type="button" data-action="add-to-cart" data-urun-id="' + urunId + '" ' +
                    'aria-label="Sepete ekle">+</button>';
            } else {
                // Coklu porsiyon varsa sadece toplam adet goster + ekle butonu
                var hasPortion = product.porsiyonlar && product.porsiyonlar.length > 1;
                if (hasPortion) {
                    actionsEl.innerHTML =
                        '<div class="qty-control">' +
                        '<span class="qty-value u-qty-badge">' + count + '</span>' +
                        '<button class="qty-btn" type="button" data-action="add-to-cart" data-urun-id="' + urunId + '" aria-label="Ekle">+</button>' +
                        '</div>';
                } else {
                    var porsiyon = product.porsiyonlar[0] ? product.porsiyonlar[0].label : 'Normal';
                    var porsiyonAttr = escapeHtml(porsiyon);
                    actionsEl.innerHTML =
                        '<div class="qty-control">' +
                        '<button class="qty-btn" type="button" data-action="qty-change" data-urun-id="' + urunId + '" data-porsiyon="' + porsiyonAttr + '" data-delta="-1" aria-label="Azalt">&minus;</button>' +
                        '<span class="qty-value">' + count + '</span>' +
                        '<button class="qty-btn" type="button" data-action="qty-change" data-urun-id="' + urunId + '" data-porsiyon="' + porsiyonAttr + '" data-delta="1" aria-label="Artir">+</button>' +
                        '</div>';
                }
            }
        }

        function updateAllProductActions() {
            var cards = document.querySelectorAll('.product-card[data-product-id]');
            for (var i = 0; i < cards.length; i++) {
                var id = parseInt(cards[i].getAttribute('data-product-id'), 10);
                updateProductActions(id);
            }
        }

        // ============================================
        // PORSIYON MODALI
        // ============================================

        var selectedPortion = null;
        var modalProductId = null;

        function openPortionModal(urunId) {
            var product = PRODUCTS[urunId];
            if (!product) return;

            modalProductId = urunId;
            selectedPortion = null;

            document.getElementById('modalTitle').textContent = product.ad;

            var optionsHtml = '';
            for (var i = 0; i < product.porsiyonlar.length; i++) {
                var p = product.porsiyonlar[i];
                optionsHtml +=
                    '<div class="portion-option' + (i === 0 ? ' selected' : '') + '" ' +
                    'role="button" tabindex="0" ' +
                    'data-action="select-portion" data-index="' + i + '">' +
                    '<span class="portion-option__label">' + escapeHtml(p.label) + '</span>' +
                    '<span class="portion-option__price">' + formatPrice(p.fiyat) + '</span>' +
                    '</div>';
            }

            if (product.porsiyonlar.length > 0) {
                selectedPortion = product.porsiyonlar[0];
            }

            document.getElementById('portionOptions').innerHTML = optionsHtml;
            document.getElementById('portionModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePortionModal() {
            document.getElementById('portionModal').classList.remove('active');
            document.body.style.overflow = '';
            modalProductId = null;
            selectedPortion = null;
        }

        function selectPortion(el, index) {
            var product = PRODUCTS[modalProductId];
            if (!product) return;
            selectedPortion = product.porsiyonlar[index];

            var options = document.querySelectorAll('.portion-option');
            for (var i = 0; i < options.length; i++) {
                options[i].classList.remove('selected');
            }
            el.classList.add('selected');
        }

        // Modal overlay'e tiklayinca kapat
        document.getElementById('portionModal').addEventListener('click', function (e) {
            if (e.target === this) closePortionModal();
        });

        // Modal ekle butonu
        document.getElementById('modalAddBtn').addEventListener('click', function () {
            if (!modalProductId || !selectedPortion) return;
            var product = PRODUCTS[modalProductId];
            addToCart(modalProductId, product.ad, selectedPortion.fiyat, selectedPortion.label, selectedPortion.label, 1);
            closePortionModal();
        });

        // ============================================
        // EKLE BUTONU HANDLER
        // ============================================

        function handleAddClick(urunId) {
            var product = PRODUCTS[urunId];
            if (!product || product.stok === 'tukendi') return;

            if (product.porsiyonlar && product.porsiyonlar.length > 1) {
                openPortionModal(urunId);
            } else {
                var porsiyon = product.porsiyonlar[0] ? product.porsiyonlar[0].label : 'Normal';
                var porsiyonLabel = porsiyon;
                var fiyat = product.porsiyonlar[0] ? product.porsiyonlar[0].fiyat : product.fiyat;
                addToCart(urunId, product.ad, fiyat, porsiyon, porsiyonLabel, 1);
            }
        }

        function goToCart() {
            window.location.href = 'sepet.php';
        }

        // ============================================
        // EVENT DELEGATION (CSP uyumlu — inline onclick YOK)
        // ============================================

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-action]');
            if (!trigger) return;

            var action = trigger.getAttribute('data-action');

            if (action === 'add-to-cart') {
                var urunId = parseInt(trigger.getAttribute('data-urun-id'), 10);
                if (!isNaN(urunId)) handleAddClick(urunId);
                return;
            }

            if (action === 'qty-change') {
                var qId = parseInt(trigger.getAttribute('data-urun-id'), 10);
                var qPorsiyon = trigger.getAttribute('data-porsiyon') || 'Normal';
                var qDelta = parseInt(trigger.getAttribute('data-delta'), 10);
                if (!isNaN(qId) && !isNaN(qDelta)) updateCartItemQty(qId, qPorsiyon, qDelta);
                return;
            }

            if (action === 'select-portion') {
                var idx = parseInt(trigger.getAttribute('data-index'), 10);
                if (!isNaN(idx)) selectPortion(trigger, idx);
                return;
            }

            if (action === 'go-to-cart') {
                goToCart();
                return;
            }
        });

        // Klavye erisilebilirlik: portion-option role=button icin Enter/Space
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var trigger = e.target.closest('[data-action="select-portion"]');
            if (!trigger) return;
            e.preventDefault();
            var idx = parseInt(trigger.getAttribute('data-index'), 10);
            if (!isNaN(idx)) selectPortion(trigger, idx);
        });

        // ============================================
        // KATEGORI FILTRELEME
        // ============================================

        var categoryTabs = document.querySelectorAll('.category-tab');
        var productCards = document.querySelectorAll('.product-card[data-category]');

        for (var i = 0; i < categoryTabs.length; i++) {
            categoryTabs[i].addEventListener('click', function () {
                var category = this.getAttribute('data-category');

                // Aktif tab'i guncelle
                for (var j = 0; j < categoryTabs.length; j++) {
                    categoryTabs[j].classList.remove('category-tab--active');
                }
                this.classList.add('category-tab--active');

                // Tab'i gorunur yap (scroll)
                this.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

                // Urunleri filtrele
                var anyVisible = false;
                for (var k = 0; k < productCards.length; k++) {
                    if (category === 'all' || productCards[k].getAttribute('data-category') === category) {
                        productCards[k].style.display = '';
                        anyVisible = true;
                    } else {
                        productCards[k].style.display = 'none';
                    }
                }

                // Bos durum
                var emptyState = document.querySelector('.empty-state');
                if (emptyState) {
                    emptyState.style.display = anyVisible ? 'none' : '';
                }
            });
        }

        // ============================================
        // LAZY LOAD
        // ============================================

        function lazyLoadImages() {
            var images = document.querySelectorAll('img[data-src]');

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    for (var i = 0; i < entries.length; i++) {
                        if (entries[i].isIntersecting) {
                            var img = entries[i].target;
                            img.src = img.getAttribute('data-src');
                            img.removeAttribute('data-src');
                            img.addEventListener('load', function () {
                                this.classList.add('loaded');
                            });
                            img.addEventListener('error', function () {
                                this.style.display = 'none';
                            });
                            observer.unobserve(img);
                        }
                    }
                }, { rootMargin: '100px' });

                for (var i = 0; i < images.length; i++) {
                    observer.observe(images[i]);
                }
            } else {
                // Fallback: hepsini hemen yukle
                for (var i = 0; i < images.length; i++) {
                    images[i].src = images[i].getAttribute('data-src');
                    images[i].removeAttribute('data-src');
                    images[i].classList.add('loaded');
                }
            }
        }

        // ============================================
        // YARDIMCI FONKSIYONLAR
        // ============================================

        function escapeStr(str) {
            return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        function showToast(message) {
            var toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('toast--show');

            clearTimeout(toast._timeout);
            toast._timeout = setTimeout(function () {
                toast.classList.remove('toast--show');
            }, 2000);
        }

        // ============================================
        // INIT
        // ============================================

        lazyLoadImages();
        updateCartUI();
        updateAllProductActions();

    })();
    </script>

</body>
</html>
<?php

// ============================================
// HATA/BILGI SAYFA FONKSIYONLARI
// ============================================

/**
 * Hata/bilgi sayfasi render et
 *
 * @param string $appName  Uygulama adi
 * @param string $baslik   Sayfa basligi
 * @param string $mesaj    Aciklama mesaji
 * @param string $tip      Hata tipi: 'qr' veya 'masa'
 * @param int    $httpCode HTTP yanit kodu (default 200; 400/403 gibi hata kodlari destekli)
 */
function renderHataSayfasi(string $appName, string $baslik, string $mesaj, string $tip = 'qr', int $httpCode = 200): void
{
    if ($httpCode >= 400 && $httpCode < 600 && !headers_sent()) {
        http_response_code($httpCode);
    }
    $ikonQr = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>';
    $ikonMasa = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>';
    $ikon = ($tip === 'qr') ? $ikonQr : $ikonMasa;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($appName) ?> - <?= e($baslik) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #FAF7F4;
            color: #3D2B1F;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .error-page {
            text-align: center;
            max-width: 360px;
        }
        .error-page__icon {
            color: #C4B5A6;
            margin-bottom: 24px;
        }
        .error-page__title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #3D2B1F;
        }
        .error-page__message {
            font-size: 0.95rem;
            color: #7A6855;
            line-height: 1.6;
        }
        .error-page__brand {
            margin-top: 48px;
            font-size: 0.85rem;
            color: #C4B5A6;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-page__icon"><?= $ikon ?></div>
        <h1 class="error-page__title"><?= e($baslik) ?></h1>
        <p class="error-page__message"><?= e($mesaj) ?></p>
        <p class="error-page__brand"><?= e($appName) ?></p>
    </div>
</body>
</html>
<?php
}
?>
