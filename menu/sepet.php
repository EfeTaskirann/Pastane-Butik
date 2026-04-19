<?php
/**
 * QR Menu - Sepet Sayfasi
 *
 * Musterinin localStorage'daki sepetini goruntuleyen,
 * siparis olusturma islemi yapan sayfa.
 *
 * @package Pastane\Menu
 * @since 1.0.0
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// ============================================
// OTURUM KONTROLU
// ============================================

$oturumToken = $_COOKIE['oturum_token'] ?? '';

if (empty($oturumToken)) {
    header('Location: index.php');
    exit;
}

try {
    $oturumService = masa_oturum_service();
    $bilgi = $oturumService->oturumBilgisi($oturumToken);
    $masa = $bilgi['masa'];
    $oturum = $bilgi['oturum'];
} catch (\Throwable $e) {
    header('Location: index.php');
    exit;
}

// Oturum aktif mi?
if ($oturum['durum'] !== 'aktif') {
    header('Location: index.php');
    exit;
}

$masaNo = (int)($masa['masa_no'] ?? 0);
$masaId = (int)($masa['id'] ?? 0);
$qrToken = $masa['qr_token'] ?? '';
$basePath = config('app.base_path', '/pastane');
$appName = defined('SITE_NAME') ? SITE_NAME : 'Pastane';
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(t('order.your_cart')) ?> - <?= e($appName) ?></title>

    <!-- Ortak CSS -->
    <link rel="stylesheet" href="assets/css/menu-base.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            -webkit-tap-highlight-color: transparent;
        }

        /* ========== KONTEYNER ========== */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 16px;
            padding-bottom: 200px;
        }

        /* ========== BOS SEPET ========== */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart__icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            color: var(--menu-text-secondary);
            opacity: 0.5;
        }

        .empty-cart__text {
            font-size: 18px;
            font-weight: 500;
            color: var(--menu-text-secondary);
            margin-bottom: 24px;
        }

        .empty-cart__btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--menu-primary);
            color: #fff;
            border: none;
            border-radius: var(--menu-radius);
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            min-height: 48px;
        }

        .empty-cart__btn:active {
            background: var(--menu-primary-dark);
        }

        /* ========== SEPET KALEMLERI ========== */
        .cart-item {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 16px;
            margin-bottom: 12px;
            position: relative;
        }

        .cart-item__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .cart-item__name {
            font-size: 16px;
            font-weight: 600;
            flex: 1;
            padding-right: 8px;
        }

        .cart-item__remove {
            background: none;
            border: none;
            color: var(--menu-danger);
            cursor: pointer;
            padding: 8px;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .cart-item__remove:active {
            background: rgba(231, 76, 60, 0.1);
        }

        .cart-item__remove svg {
            width: 20px;
            height: 20px;
        }

        .cart-item__portion {
            font-size: 13px;
            color: var(--menu-text-secondary);
            margin-bottom: 10px;
        }

        .cart-item__note-input {
            width: 100%;
            border: 1px solid var(--menu-border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            font-family: inherit;
            color: var(--menu-text);
            margin-bottom: 12px;
            outline: none;
            transition: border-color 0.2s;
        }

        .cart-item__note-input:focus {
            border-color: var(--menu-primary);
        }

        .cart-item__note-input::placeholder {
            color: #bdc3c7;
        }

        .cart-item__footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Adet Kontrol */
        .qty-control {
            display: flex;
            align-items: center;
            gap: 0;
            border: 1px solid var(--menu-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .qty-control__btn {
            background: var(--menu-bg);
            border: none;
            color: var(--menu-text);
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            min-width: 44px;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            user-select: none;
        }

        .qty-control__btn:active {
            background: var(--menu-border);
        }

        .qty-control__value {
            min-width: 36px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            padding: 0 4px;
        }

        .cart-item__price {
            font-size: 16px;
            font-weight: 600;
            color: var(--menu-primary-dark);
            white-space: nowrap;
        }

        /* ========== SIPARIS NOTU ========== */
        .order-note {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 16px;
            margin-top: 16px;
            margin-bottom: 16px;
        }

        .order-note__label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .order-note__textarea {
            width: 100%;
            min-height: 80px;
            border: 1px solid var(--menu-border);
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--menu-text);
            resize: vertical;
            outline: none;
            transition: border-color 0.2s;
        }

        .order-note__textarea:focus {
            border-color: var(--menu-primary);
        }

        .order-note__textarea::placeholder {
            color: #bdc3c7;
        }

        .order-note__counter {
            font-size: 12px;
            color: var(--menu-text-secondary);
            text-align: right;
            margin-top: 4px;
        }

        /* ========== SIPARIS OZETI ========== */
        .order-summary {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 16px;
            margin-bottom: 16px;
        }

        .order-summary__row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .order-summary__row--total {
            border-top: 2px solid var(--menu-border);
            margin-top: 4px;
            padding-top: 12px;
        }

        .order-summary__label {
            font-size: 14px;
            color: var(--menu-text-secondary);
        }

        .order-summary__value {
            font-size: 14px;
            font-weight: 500;
        }

        .order-summary__label--total {
            font-size: 18px;
            font-weight: 600;
            color: var(--menu-text);
        }

        .order-summary__value--total {
            font-size: 22px;
            font-weight: 700;
            color: var(--menu-primary-dark);
        }

        /* ========== SIPARIS BUTONU ========== */
        .order-btn-wrapper {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--menu-surface);
            padding: 12px 16px;
            padding-bottom: max(12px, env(safe-area-inset-bottom));
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .order-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 16px 24px;
            min-height: 56px;
            background: var(--menu-success);
            color: #fff;
            border: none;
            border-radius: var(--menu-radius);
            font-size: 18px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, opacity 0.2s;
        }

        .order-btn:active {
            background: #219a52;
        }

        .order-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .order-btn svg {
            width: 22px;
            height: 22px;
        }

        /* ========== LOADING ========== */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== RESPONSIVE ========== */
        @media (min-width: 768px) {
            .container {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Ust Bar -->
<header class="top-bar">
    <a href="<?= e($basePath) ?>/menu/?t=<?= e($qrToken) ?>" class="top-bar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <?= e(t('btn.back_to_menu')) ?>
    </a>
    <span class="top-bar__title"><?= e(t('order.your_cart')) ?></span>
    <span class="top-bar__masa"><?= e(t('order.your_table', ['no' => (int)$masaNo])) ?></span>
</header>

<!-- Icerik Alani -->
<div class="container" id="cartContainer">
    <!-- JavaScript ile doldurulacak -->
</div>

<!-- Siparis Butonu (dolu sepette gorunur) -->
<div class="order-btn-wrapper u-hidden" id="orderBtnWrapper">
    <button type="button" class="order-btn" id="orderBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <?= e(t('btn.order_now')) ?>
    </button>
</div>

<script src="assets/js/menu-utils.js"></script>
<script>
(function() {
    'use strict';

    // ============================================
    // YAPILANDIRMA
    // ============================================
    var MASA_ID = <?= (int)$masaId ?>;
    var STORAGE_KEY = 'pastane_cart_' + MASA_ID;
    var OTURUM_TOKEN = <?= json_encode($oturumToken, JSON_UNESCAPED_UNICODE) ?>;
    var API_URL = '../api/v1/menu/siparis';
    var MENU_URL = <?= json_encode($basePath . '/menu/?t=' . ($qrToken), JSON_UNESCAPED_UNICODE) ?>;

    // ============================================
    // YARDIMCI FONKSIYONLAR
    // ============================================

    /**
     * Sepeti localStorage'dan oku
     */
    function getCart() {
        try {
            var data = localStorage.getItem(STORAGE_KEY);
            if (!data) return [];
            var parsed = JSON.parse(data);
            if (!Array.isArray(parsed)) return [];
            return parsed;
        } catch (e) {
            return [];
        }
    }

    /**
     * Sepeti localStorage'a yaz
     */
    function saveCart(cart) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
        } catch (e) {
            // localStorage dolu veya erisilemez
        }
    }

    // ============================================
    // RENDER
    // ============================================

    var container = document.getElementById('cartContainer');
    var btnWrapper = document.getElementById('orderBtnWrapper');
    var orderBtn = document.getElementById('orderBtn');

    function render() {
        var cart = getCart();

        if (cart.length === 0) {
            btnWrapper.style.display = 'none';
            container.innerHTML =
                '<div class="empty-cart">' +
                    '<svg class="empty-cart__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
                        '<circle cx="9" cy="21" r="1"></circle>' +
                        '<circle cx="20" cy="21" r="1"></circle>' +
                        '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>' +
                    '</svg>' +
                    '<p class="empty-cart__text">Sepetiniz bos</p>' +
                    '<a href="' + escapeHtml(MENU_URL) + '" class="empty-cart__btn">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">' +
                            '<polyline points="15 18 9 12 15 6"></polyline>' +
                        '</svg>' +
                        'Menuye Don' +
                    '</a>' +
                '</div>';
            return;
        }

        // Sepet dolu
        btnWrapper.style.display = 'block';

        var html = '';
        var araToplam = 0;

        for (var i = 0; i < cart.length; i++) {
            var item = cart[i];
            var itemTotal = (item.fiyat || 0) * (item.adet || 1);
            araToplam += itemTotal;

            html +=
                '<div class="cart-item" data-index="' + i + '">' +
                    '<div class="cart-item__header">' +
                        '<div class="cart-item__name">' + escapeHtml(item.urunAdi) + '</div>' +
                        '<button type="button" class="cart-item__remove" data-action="remove" data-index="' + i + '" aria-label="Kalemi kaldir">' +
                            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                                '<polyline points="3 6 5 6 21 6"></polyline>' +
                                '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                    (item.porsiyonLabel
                        ? '<div class="cart-item__portion">' + escapeHtml(item.porsiyonLabel) + '</div>'
                        : '') +
                    '<input type="text" class="cart-item__note-input" data-action="note" data-index="' + i + '"' +
                        ' placeholder="Ozel not ekleyin (opsiyonel)"' +
                        ' maxlength="200"' +
                        ' value="' + escapeHtml(item.ozelNot || '') + '"' +
                        ' aria-label="Urun icin ozel not">' +
                    '<div class="cart-item__footer">' +
                        '<div class="qty-control">' +
                            '<button type="button" class="qty-control__btn" data-action="decrease" data-index="' + i + '" aria-label="Adeti azalt">&minus;</button>' +
                            '<span class="qty-control__value">' + (item.adet || 1) + '</span>' +
                            '<button type="button" class="qty-control__btn" data-action="increase" data-index="' + i + '" aria-label="Adeti artir">&plus;</button>' +
                        '</div>' +
                        '<span class="cart-item__price">' + formatPrice(itemTotal) + '</span>' +
                    '</div>' +
                '</div>';
        }

        // Siparis notu
        html +=
            '<div class="order-note">' +
                '<label class="order-note__label" for="orderNote">Siparis Notu</label>' +
                '<textarea id="orderNote" class="order-note__textarea" placeholder="Siparisizle ilgili ozel isteklerinizi yazin..." maxlength="500"></textarea>' +
                '<div class="order-note__counter"><span id="noteCount">0</span>/500</div>' +
            '</div>';

        // Siparis ozeti
        html +=
            '<div class="order-summary">' +
                '<div class="order-summary__row">' +
                    '<span class="order-summary__label">Ara Toplam</span>' +
                    '<span class="order-summary__value">' + formatPrice(araToplam) + '</span>' +
                '</div>' +
                '<div class="order-summary__row order-summary__row--total">' +
                    '<span class="order-summary__label order-summary__label--total">Toplam</span>' +
                    '<span class="order-summary__value order-summary__value--total">' + formatPrice(araToplam) + '</span>' +
                '</div>' +
            '</div>';

        container.innerHTML = html;

        // Siparis notu karakter sayaci
        var noteEl = document.getElementById('orderNote');
        var noteCountEl = document.getElementById('noteCount');
        if (noteEl && noteCountEl) {
            noteEl.addEventListener('input', function() {
                noteCountEl.textContent = this.value.length;
            });
        }
    }

    // ============================================
    // OLAY YONETIMI (Event Delegation)
    // ============================================

    container.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var action = btn.getAttribute('data-action');
        var index = parseInt(btn.getAttribute('data-index'), 10);
        var cart = getCart();

        if (isNaN(index) || index < 0 || index >= cart.length) return;

        switch (action) {
            case 'increase':
                if (cart[index].adet < 99) {
                    cart[index].adet++;
                    saveCart(cart);
                    render();
                }
                break;

            case 'decrease':
                if (cart[index].adet > 1) {
                    cart[index].adet--;
                    saveCart(cart);
                    render();
                } else {
                    // Adet 1'den asagiya dustugunde kaldirma onay
                    confirmRemove(index);
                }
                break;

            case 'remove':
                confirmRemove(index);
                break;
        }
    });

    // Ozel not degisikligi (blur ile kaydet)
    container.addEventListener('change', function(e) {
        if (e.target.getAttribute('data-action') !== 'note') return;
        var index = parseInt(e.target.getAttribute('data-index'), 10);
        var cart = getCart();
        if (isNaN(index) || index < 0 || index >= cart.length) return;
        cart[index].ozelNot = e.target.value.substring(0, 200);
        saveCart(cart);
    });

    /**
     * Kalemi kaldirma onay
     */
    function confirmRemove(index) {
        var cart = getCart();
        if (index < 0 || index >= cart.length) return;

        Swal.fire({
            title: 'Kaldir',
            text: '"' + (cart[index].urunAdi || 'Urun') + '" sepetten kaldirilsin mi?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Kaldir',
            cancelButtonText: 'Vazgec'
        }).then(function(result) {
            if (result.isConfirmed) {
                var freshCart = getCart();
                freshCart.splice(index, 1);
                saveCart(freshCart);
                render();
            }
        });
    }

    // ============================================
    // SIPARIS VER
    // ============================================

    orderBtn.addEventListener('click', function() {
        var cart = getCart();
        if (cart.length === 0) {
            Swal.fire({
                title: 'Sepet Bos',
                text: 'Siparis verebilmek icin once sepete urun ekleyin.',
                icon: 'info',
                confirmButtonColor: '#8B4513',
                confirmButtonText: 'Menuye Don'
            }).then(function() {
                window.location.href = MENU_URL;
            });
            return;
        }

        var noteEl = document.getElementById('orderNote');
        var siparisNotu = noteEl ? noteEl.value.substring(0, 500) : '';

        // Notlari son kez kaydet
        var noteInputs = container.querySelectorAll('[data-action="note"]');
        for (var i = 0; i < noteInputs.length; i++) {
            var idx = parseInt(noteInputs[i].getAttribute('data-index'), 10);
            if (!isNaN(idx) && idx < cart.length) {
                cart[idx].ozelNot = noteInputs[i].value.substring(0, 200);
            }
        }
        saveCart(cart);

        // API payload
        var kalemler = cart.map(function(item) {
            return {
                urun_id: item.urunId,
                adet: item.adet || 1,
                porsiyon: item.porsiyon || null,
                ozel_not: item.ozelNot || null
            };
        });

        var payload = {
            oturum_token: OTURUM_TOKEN,
            kalemler: kalemler,
            siparis_notu: siparisNotu || null
        };

        // Buton durumu
        orderBtn.disabled = true;
        orderBtn.innerHTML = '<span class="loading-spinner"></span> Gonderiliyor...';

        fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, body: data };
            });
        })
        .then(function(result) {
            if (result.body.success) {
                // Sepeti temizle
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {}

                var siparisId = '';
                var takipToken = '';
                if (result.body.data) {
                    if (result.body.data.siparis) {
                        siparisId = result.body.data.siparis.id || result.body.data.siparis_id || '';
                        takipToken = result.body.data.siparis.takip_token || result.body.data.takip_token || '';
                    } else {
                        siparisId = result.body.data.siparis_id || '';
                        takipToken = result.body.data.takip_token || '';
                    }
                }

                // Odeme gerekli mi kontrol et
                var odemeGerekli = result.body.data && result.body.data.odeme_gerekli;

                if (odemeGerekli && siparisId) {
                    // Odeme sayfasina yonlendir
                    window.location.href = 'odeme.php?siparis=' + encodeURIComponent(siparisId);
                } else {
                    Swal.fire({
                        title: 'Siparis Alindi!',
                        text: 'Siparisiz hazirlaniyor.',
                        icon: 'success',
                        confirmButtonColor: '#22c55e',
                        confirmButtonText: 'Tamam',
                        allowOutsideClick: false
                    }).then(function() {
                        // IDOR korumasi: ?id= yerine ?token= kullan.
                        // Token sadece ilgili musteriye doner; 32 hex karakter (bin2hex).
                        if (takipToken && /^[a-f0-9]{32}$/.test(takipToken)) {
                            window.location.href = 'siparis-takip.php?token=' + encodeURIComponent(takipToken);
                        } else {
                            // Token eksikse (eski backend), guvenli fallback: anasayfa
                            window.location.href = 'index.php';
                        }
                    });
                }
            } else {
                var errorMsg = result.body.error || result.body.message || 'Siparis olusturulamadi.';
                Swal.fire({
                    title: 'Hata',
                    text: errorMsg,
                    icon: 'error',
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Tamam'
                });
                resetOrderBtn();
            }
        })
        .catch(function(err) {
            Swal.fire({
                title: 'Baglanti Hatasi',
                text: 'Sunucuya ulasilamadi. Lutfen tekrar deneyin.',
                icon: 'error',
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Tamam'
            });
            resetOrderBtn();
        });
    });

    function resetOrderBtn() {
        orderBtn.disabled = false;
        orderBtn.innerHTML =
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                '<polyline points="20 6 9 17 4 12"></polyline>' +
            '</svg>' +
            'Siparis Ver';
    }

    // ============================================
    // ILK RENDER
    // ============================================

    render();

})();
</script>

</body>
</html>
