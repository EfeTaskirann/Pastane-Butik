<?php
/**
 * QR Menu - Odeme Sayfasi
 *
 * Siparis olusturulduktan sonra odeme islemi icin
 * musteri bu sayfaya yonlendirilir.
 *
 * @package Pastane\Menu
 * @since 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// ============================================
// HTTPS ZORLAMA (production ortaminda)
// ============================================

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$isForwardedHttps = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

if (!$isHttps && !$isForwardedHttps && !$isLocalhost) {
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    header('Location: https://' . $host . $_SERVER['REQUEST_URI']);
    exit;
}

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

if ($oturum['durum'] !== 'aktif') {
    header('Location: index.php');
    exit;
}

// ============================================
// SIPARIS DOGRULAMA
// ============================================

$siparisId = isset($_GET['siparis']) ? (int)$_GET['siparis'] : 0;
if ($siparisId < 1) {
    header('Location: sepet.php');
    exit;
}

$siparisService = masa_siparis_service();

try {
    $siparis = $siparisService->getSiparisDetay($siparisId);
} catch (\Throwable $e) {
    header('Location: sepet.php');
    exit;
}

// Guvenlik: siparis bu oturuma ait mi?
if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Erisim Engellendi</title></head><body><p>Bu siparisi odeme yetkiniz yok.</p></body></html>';
    exit;
}

// Takip URL'i — IDOR korumasi icin daima token bazli
$takipToken = (string)($siparis['takip_token'] ?? '');
$takipUrl = $takipToken !== '' ? 'siparis-takip.php?token=' . $takipToken : 'index.php';

// Zaten odenmis mi?
$odemeDurum = $siparis['odeme_durumu'] ?? 'odenmedi';
if ($odemeDurum === 'odendi') {
    header('Location: ' . $takipUrl);
    exit;
}

// Iptal edilmis siparis
if (($siparis['durum'] ?? '') === 'iptal') {
    header('Location: ' . $takipUrl);
    exit;
}

$masaNo = (int)($masa['masa_no'] ?? 0);
$qrToken = $masa['qr_token'] ?? '';
$basePath = config('app.base_path', '/pastane');
$appName = defined('SITE_NAME') ? SITE_NAME : 'Pastane';

// Odeme konfigurasyonu
$odemeConfig = config('odeme') ?? [];
$aktifGateway = $odemeConfig['gateway'] ?? 'test';
$isTestModu = ($aktifGateway === 'test');
$toplamTutar = (float)($siparis['toplam_tutar'] ?? 0);
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(t('order.payment_title')) ?> - <?= e($appName) ?></title>

    <!-- Ortak CSS -->
    <link rel="stylesheet" href="assets/css/menu-base.css">

    <style>
        body {
            -webkit-tap-highlight-color: transparent;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 16px;
            padding-bottom: 200px;
        }

        /* ========== SIPARIS OZETI KARTI ========== */
        .payment-summary {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 20px;
            margin-bottom: 16px;
        }

        .payment-summary__title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--menu-primary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--menu-border);
        }

        .payment-summary__item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--menu-bg);
        }

        .payment-summary__item:last-of-type {
            border-bottom: none;
        }

        .payment-summary__item-info {
            flex: 1;
        }

        .payment-summary__item-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--menu-text);
        }

        .payment-summary__item-detail {
            font-size: 0.75rem;
            color: var(--menu-text-secondary);
            margin-top: 2px;
        }

        .payment-summary__item-price {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--menu-text);
            white-space: nowrap;
            margin-left: 12px;
        }

        .payment-summary__total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            margin-top: 8px;
            border-top: 2px solid var(--menu-border);
        }

        .payment-summary__total-label {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--menu-text);
        }

        .payment-summary__total-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--menu-primary);
        }

        /* ========== GUVENLI ODEME ALANI ========== */
        .security-info {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 20px;
            margin-bottom: 16px;
            text-align: center;
        }

        .security-info__icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .security-info__title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--menu-text);
            margin-bottom: 6px;
        }

        .security-info__text {
            font-size: 0.8rem;
            color: var(--menu-text-secondary);
            line-height: 1.5;
        }

        .security-badges {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--menu-border);
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            color: var(--menu-text-secondary);
        }

        .security-badge svg {
            width: 16px;
            height: 16px;
            color: var(--menu-success);
        }

        /* ========== ODEME FORMU ALANI ========== */
        .payment-form {
            background: var(--menu-surface);
            border-radius: var(--menu-radius);
            box-shadow: var(--menu-shadow);
            padding: 20px;
            margin-bottom: 16px;
        }

        .payment-form__title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--menu-primary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--menu-border);
        }

        /* Test modu banner */
        .test-banner {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            padding: 12px 16px;
            border-radius: var(--menu-radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            margin-bottom: 16px;
        }

        /* iyzico iframe container */
        .iyzico-container {
            min-height: 400px;
            border: 1px solid var(--menu-border);
            border-radius: var(--menu-radius-sm);
        }

        .iyzico-container iframe {
            width: 100%;
            min-height: 400px;
            border: none;
        }

        /* ========== ODEME YONTEMI SECIM KARTLARI ========== */
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .payment-method-card {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            padding: 16px;
            background: var(--menu-bg);
            border: 2px solid var(--menu-border);
            border-radius: var(--menu-radius);
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            text-align: left;
            font-family: inherit;
        }

        .payment-method-card:active {
            border-color: var(--menu-primary);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.15);
        }

        .payment-method-card__icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--menu-primary);
            color: #fff;
            border-radius: 12px;
        }

        .payment-method-card__icon svg {
            width: 22px;
            height: 22px;
        }

        .payment-method-card--masada .payment-method-card__icon {
            background: #16a34a;
        }

        .payment-method-card__text {
            flex: 1;
        }

        .payment-method-card__text strong {
            display: block;
            font-size: 1rem;
            color: var(--menu-text);
            margin-bottom: 2px;
        }

        .payment-method-card__text span {
            font-size: 0.8rem;
            color: var(--menu-text-secondary);
        }

        .payment-method-card__arrow {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            color: var(--menu-text-secondary);
        }

        /* ========== ODEME BUTONU ========== */
        .payment-btn-wrapper {
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

        .payment-btn {
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

        .payment-btn:active {
            background: #219a52;
        }

        .payment-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .payment-btn svg {
            width: 22px;
            height: 22px;
        }

        /* ========== GERI LINK ========== */
        .back-link {
            display: block;
            text-align: center;
            color: var(--menu-text-secondary);
            font-size: 0.85rem;
            text-decoration: none;
            padding: 12px;
            margin-top: 8px;
        }

        .back-link:active {
            color: var(--menu-primary);
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
    <a href="sepet.php" class="top-bar__back" aria-label="<?= e(t('a11y.cart_back')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <?= e(t('order.payment_back_to_cart')) ?>
    </a>
    <span class="top-bar__title"><?= e(t('order.payment_title')) ?></span>
    <span class="top-bar__masa"><?= e(t('order.your_table', ['no' => (int)$masaNo])) ?></span>
</header>

<div class="container">

    <!-- Siparis Ozeti Karti -->
    <div class="payment-summary">
        <div class="payment-summary__title"><?= e(t('order.payment_summary_title')) ?></div>

        <?php foreach ($siparis['kalemler'] as $kalem): ?>
            <div class="payment-summary__item">
                <div class="payment-summary__item-info">
                    <div class="payment-summary__item-name"><?= e($kalem['urun_adi'] ?? '') ?></div>
                    <div class="payment-summary__item-detail">
                        <?= (int)($kalem['adet'] ?? 1) ?> <?= e(t('order.tracking_units')) ?>
                        <?php if (!empty($kalem['porsiyon'])): ?>
                            &middot; <?= e($kalem['porsiyon']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="payment-summary__item-price">
                    <?= number_format((float)($kalem['toplam_fiyat'] ?? 0), 2, ',', '.') ?> &#8378;
                </div>
            </div>
        <?php endforeach; ?>

        <div class="payment-summary__total">
            <span class="payment-summary__total-label"><?= e(t('common.total')) ?></span>
            <span class="payment-summary__total-value">
                <?= number_format($toplamTutar, 2, ',', '.') ?> &#8378;
            </span>
        </div>
    </div>

    <!-- Odeme Yontemi Secimi -->
    <div class="payment-form" id="paymentMethodSection">
        <div class="payment-form__title"><?= e(t('order.payment_method_title')) ?></div>

        <div class="payment-methods">
            <button type="button" class="payment-method-card" id="btnOnlineOdeme">
                <div class="payment-method-card__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
                <div class="payment-method-card__text">
                    <strong><?= e(t('order.payment_online_title')) ?></strong>
                    <span><?= e(t('order.payment_online_desc')) ?></span>
                </div>
                <svg class="payment-method-card__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <button type="button" class="payment-method-card payment-method-card--masada" id="btnMasadaOdeme">
                <div class="payment-method-card__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path>
                    </svg>
                </div>
                <div class="payment-method-card__text">
                    <strong><?= e(t('order.payment_at_table_title')) ?></strong>
                    <span><?= e(t('order.payment_at_table_desc')) ?></span>
                </div>
                <svg class="payment-method-card__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
    </div>

    <!-- Online Odeme Alani (baslangicta gizli) -->
    <div id="onlinePaymentSection" class="u-hidden">
        <!-- Guvenli Odeme Bilgi Alani -->
        <div class="security-info">
            <div class="security-info__icon">&#128274;</div>
            <div class="security-info__title"><?= e(t('order.payment_secure_title')) ?></div>
            <div class="security-info__text">
                <?= e(t('order.payment_secure_text')) ?>
            </div>
            <div class="security-badges">
                <span class="security-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <?= e(t('order.payment_badge_ssl')) ?>
                </span>
                <span class="security-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <?= e(t('order.payment_badge_encryption')) ?>
                </span>
                <span class="security-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    <?= e(t('order.payment_badge_3ds')) ?>
                </span>
            </div>
        </div>

        <div class="payment-form">
            <div class="payment-form__title"><?= e(t('order.payment_online_section_title')) ?></div>

            <?php if ($isTestModu): ?>
                <div class="test-banner">
                    &#9888; <?= e(t('order.payment_test_mode_warning')) ?>
                </div>
            <?php else: ?>
                <div class="iyzico-container" id="iyzicoContainer"></div>
            <?php endif; ?>
        </div>
    </div>

    <a href="sepet.php" class="back-link" aria-label="<?= e(t('a11y.cart_back')) ?>">
        &#8592; <?= e(t('order.payment_back_to_cart_link')) ?>
    </a>

</div>

<!-- Online Odeme Butonu (gizli, secim sonrasi gorunur) -->
<div class="payment-btn-wrapper u-hidden" id="paymentBtnWrapper">
    <button type="button" class="payment-btn" id="paymentBtn" aria-label="<?= e(t('order.payment_pay_button')) ?> — <?= number_format($toplamTutar, 2, ',', '.') ?> TL">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
        <?php if ($isTestModu): ?><?= e(t('order.payment_test_pay_prefix')) ?> <?php endif; ?><?= e(t('order.payment_pay_button')) ?> — <?= number_format($toplamTutar, 2, ',', '.') ?> &#8378;
    </button>
</div>

<script src="assets/js/menu-utils.js"></script>
<script>
(function() {
    'use strict';

    var SIPARIS_ID = <?= (int)$siparisId ?>;
    var OTURUM_TOKEN = <?= json_encode($oturumToken, JSON_UNESCAPED_UNICODE) ?>;
    var IS_TEST = <?= $isTestModu ? 'true' : 'false' ?>;
    var BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE) ?>;

    var paymentBtn = document.getElementById('paymentBtn');
    var isProcessing = false;

    // ============================================
    // ODEME YONTEMI SECIMI
    // ============================================

    // Online Ode secimi
    document.getElementById('btnOnlineOdeme').addEventListener('click', function() {
        document.getElementById('paymentMethodSection').style.display = 'none';
        document.getElementById('onlinePaymentSection').style.display = 'block';
        document.getElementById('paymentBtnWrapper').style.display = 'block';
    });

    // Masada Ode secimi
    document.getElementById('btnMasadaOdeme').addEventListener('click', function() {
        if (isProcessing) return;
        isProcessing = true;

        var btn = this;
        btn.disabled = true;
        btn.querySelector('strong').textContent = 'Isleniyor...';

        fetch(BASE_PATH + '/api/v1/menu/odeme/masada', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                siparis_id: SIPARIS_ID,
                oturum_token: OTURUM_TOKEN
            })
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, body: data };
            });
        })
        .then(function(result) {
            if (result.body.success) {
                var data = result.body.data || {};
                // Backend token bazli redirect_url doner. Token yoksa guvenli fallback: index.
                // IDOR korumasi icin ?id= formatina ASLA dusme.
                var redirectUrl = data.redirect_url || 'index.php';
                window.location.href = redirectUrl;
            } else {
                var errorMsg = result.body.error || result.body.message || 'Islem baslatilamadi.';
                showError(errorMsg);
                btn.disabled = false;
                btn.querySelector('strong').textContent = 'Masada Ode';
                isProcessing = false;
            }
        })
        .catch(function() {
            showError('Sunucuya ulasilamadi. Lutfen tekrar deneyin.');
            btn.disabled = false;
            btn.querySelector('strong').textContent = 'Masada Ode';
            isProcessing = false;
        });
    });

    // ============================================
    // ONLINE ODEME BUTONU
    // ============================================

    paymentBtn.addEventListener('click', function() {
        if (isProcessing) return;
        isProcessing = true;

        paymentBtn.disabled = true;
        paymentBtn.innerHTML = '<span class="loading-spinner"></span> Odeme isleniyor...';

        fetch(BASE_PATH + '/api/v1/menu/odeme/baslat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                siparis_id: SIPARIS_ID,
                oturum_token: OTURUM_TOKEN
            })
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, body: data };
            });
        })
        .then(function(result) {
            if (result.body.success) {
                var data = result.body.data || {};

                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }

                if (data.odeme_formu) {
                    var iyzicoContainer = document.getElementById('iyzicoContainer');
                    if (iyzicoContainer) {
                        iyzicoContainer.innerHTML = data.odeme_formu;
                    }
                    resetPaymentBtn();
                    return;
                }

                showError('Odeme baslatilamadi. Lutfen tekrar deneyin.');
                resetPaymentBtn();
            } else {
                var errorMsg = result.body.error || result.body.message || 'Odeme baslatilamadi.';
                showError(errorMsg);
                resetPaymentBtn();
            }
        })
        .catch(function() {
            showError('Sunucuya ulasilamadi. Lutfen tekrar deneyin.');
            resetPaymentBtn();
        });
    });

    function resetPaymentBtn() {
        isProcessing = false;
        paymentBtn.disabled = false;
        paymentBtn.innerHTML =
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>' +
                '<line x1="1" y1="10" x2="23" y2="10"></line>' +
            '</svg>' +
            (IS_TEST ? 'Test ' : '') + 'Odeme Yap — <?= number_format($toplamTutar, 2, ',', '.') ?> &#8378;';
    }

    /**
     * Hata mesaji goster
     */
    function showError(message) {
        var container = document.getElementById('paymentMethodSection') || document.querySelector('.payment-form');
        if (!container) return;

        var existing = container.querySelector('.payment-error');
        if (existing) existing.remove();

        var errorDiv = document.createElement('div');
        errorDiv.className = 'payment-error';
        errorDiv.setAttribute('role', 'alert');
        errorDiv.style.cssText = 'background:#fef2f2;color:#dc2626;padding:12px 16px;border-radius:8px;font-size:0.85rem;margin-top:12px;text-align:center;';
        errorDiv.textContent = message;
        container.appendChild(errorDiv);

        setTimeout(function() {
            if (errorDiv.parentNode) errorDiv.remove();
        }, 5000);
    }

})();
</script>

</body>
</html>
