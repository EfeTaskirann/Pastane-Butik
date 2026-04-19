<?php
/**
 * QR Menu - Odeme Sonuc Sayfasi
 *
 * Odeme isleminin basarili veya basarisiz sonucunu gosterir.
 * Kullaniciya net geri bildirim verir.
 *
 * @package Pastane\Menu
 * @since 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// ============================================
// PARAMETRELERI AL
// ============================================

$durum = $_GET['durum'] ?? 'basarisiz';
$referans = $_GET['ref'] ?? '';
$siparisId = isset($_GET['siparis']) ? (int)$_GET['siparis'] : 0;
$hataMesaji = $_GET['mesaj'] ?? '';

$basarili = ($durum === 'basarili');

// ============================================
// OTURUM KONTROLU
// ============================================

$oturumToken = $_COOKIE['oturum_token'] ?? '';
$masaNo = 0;
$qrToken = '';
$toplamTutar = 0;
$takipToken = ''; // IDOR korumali takip URL tokeni

if (!empty($oturumToken)) {
    try {
        $oturumService = masa_oturum_service();
        $bilgi = $oturumService->oturumBilgisi($oturumToken);
        $masa = $bilgi['masa'];
        $masaNo = (int)($masa['masa_no'] ?? 0);
        $qrToken = $masa['qr_token'] ?? '';

        // Siparis tutarini al
        if ($siparisId > 0) {
            $siparisService = masa_siparis_service();
            try {
                $siparis = $siparisService->getSiparisDetay($siparisId);
                $toplamTutar = (float)($siparis['toplam_tutar'] ?? 0);
                // Sipariş bu oturuma ait mi? Aitse takip tokenini kullanabiliriz.
                // Ait degilse token'i gosterme (IDOR korumasi).
                if ((int)($siparis['oturum_id'] ?? 0) === (int)($bilgi['oturum']['id'] ?? 0)) {
                    $takipToken = (string)($siparis['takip_token'] ?? '');
                }
            } catch (\Throwable $e) {
                // Siparis bulunamaz ise devam et
            }
        }
    } catch (\Throwable $e) {
        // Oturum sorunu varsa yine de sayfayi goster
    }
}

$basePath = config('app.base_path', '/pastane');
$appName = defined('SITE_NAME') ? SITE_NAME : 'Pastane';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $basarili ? 'Odeme Basarili' : 'Odeme Basarisiz' ?> - <?= e($appName) ?></title>

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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 56px);
        }

        /* ========== SONUC KARTI ========== */
        .result-card {
            background: var(--menu-surface);
            border-radius: 20px;
            box-shadow: var(--menu-shadow);
            padding: 40px 24px;
            text-align: center;
            width: 100%;
            max-width: 400px;
        }

        /* ========== IKON ANIMASYONU ========== */
        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: icon-bounce 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        .result-icon--success {
            background: var(--menu-success);
        }

        .result-icon--fail {
            background: var(--menu-danger);
        }

        .result-icon svg {
            width: 40px;
            height: 40px;
            color: #fff;
            stroke: #fff;
        }

        @keyframes icon-bounce {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ========== BASLIK & MESAJ ========== */
        .result-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .result-title--success {
            color: var(--menu-success);
        }

        .result-title--fail {
            color: var(--menu-danger);
        }

        .result-message {
            font-size: 0.9rem;
            color: var(--menu-text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* ========== DETAY BILGILER ========== */
        .result-details {
            background: var(--menu-bg);
            border-radius: var(--menu-radius-sm);
            padding: 16px;
            margin-bottom: 24px;
        }

        .result-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
        }

        .result-detail-row + .result-detail-row {
            border-top: 1px solid var(--menu-border);
            padding-top: 8px;
            margin-top: 2px;
        }

        .result-detail-label {
            font-size: 0.8rem;
            color: var(--menu-text-secondary);
        }

        .result-detail-value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--menu-text);
        }

        .result-detail-value--highlight {
            color: var(--menu-primary);
            font-size: 1rem;
        }

        /* ========== BUTONLAR ========== */
        .result-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .result-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            min-height: 48px;
            border: none;
            border-radius: var(--menu-radius);
            font-family: var(--menu-font);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .result-btn--primary {
            background: var(--menu-success);
            color: #fff;
        }

        .result-btn--primary:active {
            background: #219a52;
        }

        .result-btn--warning {
            background: var(--menu-warning);
            color: #fff;
        }

        .result-btn--warning:active {
            background: #d97706;
        }

        .result-btn--outline {
            background: transparent;
            color: var(--menu-primary);
            border: 2px solid var(--menu-primary);
        }

        .result-btn--outline:active {
            background: var(--menu-primary);
            color: #fff;
        }

        .result-btn svg {
            width: 20px;
            height: 20px;
        }

        /* ========== HATA ACIKLAMASI ========== */
        .error-detail {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: var(--menu-radius-sm);
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* ========== CONFETTI ========== */
        .confetti-container {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 1000;
            overflow: hidden;
        }

        .confetti {
            position: absolute;
            top: -10px;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            animation: confetti-fall linear forwards;
        }

        @keyframes confetti-fall {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        /* ========== PULSE EFEKTI ========== */
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); }
        }

        .result-icon--success {
            animation: icon-bounce 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55),
                       pulse 2s ease-in-out 0.6s infinite;
        }

        /* ========== RESPONSIVE ========== */
        @media (min-width: 768px) {
            .container {
                padding: 24px;
            }
        }

        /* ========== PRINT STILI ========== */
        @media print {
            /* Animasyonlar ve dekoratif ogeleri gizle */
            .confetti-container,
            .confetti,
            .top-bar,
            .result-icon,
            .result-actions,
            .error-detail { display: none !important; }

            /* Animasyonlari durdur */
            * { animation: none !important; }

            /* Sayfa duzeni */
            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 12pt;
            }

            .container {
                min-height: auto;
                padding: 0;
            }

            .result-card {
                box-shadow: none;
                border: 1px solid #ccc;
                padding: 20px;
            }

            /* Fis basligi */
            .result-card::before {
                content: "<?= e($appName) ?> — Odeme Fisi";
                display: block;
                font-size: 14pt;
                font-weight: 700;
                text-align: center;
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 2px solid #000;
            }

            .result-title { color: #000 !important; }

            .result-details {
                background: #fff !important;
                border: 1px solid #ccc;
            }

            .result-detail-label,
            .result-detail-value {
                color: #000 !important;
            }

            .result-detail-value--highlight {
                color: #000 !important;
                font-size: 1.1rem;
            }

            /* Alt bilgi */
            .result-card::after {
                content: "Tarih: <?= date('d.m.Y H:i') ?>";
                display: block;
                font-size: 9pt;
                text-align: center;
                margin-top: 16px;
                padding-top: 8px;
                border-top: 1px dashed #999;
                color: #666;
            }
        }
    </style>
</head>
<body>

<!-- Ust Bar -->
<header class="top-bar">
    <?php if (!$basarili && !empty($qrToken)): ?>
        <a href="<?= e($basePath) ?>/menu/?t=<?= e($qrToken) ?>" class="top-bar__back" aria-label="Menuye don">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Menuye Don
        </a>
    <?php endif; ?>
    <span class="top-bar__title"><?= $basarili ? 'Odeme Basarili' : 'Odeme Basarisiz' ?></span>
    <?php if ($masaNo > 0): ?>
        <span class="top-bar__masa">Masa <?= (int)$masaNo ?></span>
    <?php endif; ?>
</header>

<div class="container">
    <div class="result-card">

        <?php if ($basarili): ?>

            <!-- BASARILI DURUM -->
            <div class="result-icon result-icon--success" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>

            <h1 class="result-title result-title--success">Odemeniz Basariyla Tamamlandi!</h1>
            <p class="result-message">Siparissiniz hazirlaniyor. Masaniza servis edilecektir.</p>

            <?php if ($siparisId > 0 || !empty($referans)): ?>
                <div class="result-details">
                    <?php if ($siparisId > 0): ?>
                        <div class="result-detail-row">
                            <span class="result-detail-label">Siparis No</span>
                            <span class="result-detail-value">#<?= (int)$siparisId ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($toplamTutar > 0): ?>
                        <div class="result-detail-row">
                            <span class="result-detail-label">Odenen Tutar</span>
                            <span class="result-detail-value result-detail-value--highlight">
                                <?= number_format($toplamTutar, 2, ',', '.') ?> &#8378;
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($referans)): ?>
                        <div class="result-detail-row">
                            <span class="result-detail-label">Islem Referansi</span>
                            <span class="result-detail-value u-text-tiny"><?= e($referans) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="result-actions">
                <?php if (!empty($takipToken)): ?>
                    <a href="siparis-takip.php?token=<?= e($takipToken) ?>" class="result-btn result-btn--primary" aria-label="Siparisimi takip et">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Siparisimi Takip Et
                    </a>
                <?php endif; ?>

                <?php if (!empty($qrToken)): ?>
                    <a href="<?= e($basePath) ?>/menu/?t=<?= e($qrToken) ?>" class="result-btn result-btn--outline" aria-label="Menuye don">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Menuye Don
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- BASARISIZ DURUM -->
            <div class="result-icon result-icon--fail" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </div>

            <h1 class="result-title result-title--fail">Odeme Basarisiz</h1>
            <p class="result-message">Odeme islemi tamamlanamadi. Lutfen tekrar deneyin veya farkli bir yontem kullanin.</p>

            <?php if (!empty($hataMesaji)): ?>
                <div class="error-detail" role="alert">
                    <?= e($hataMesaji) ?>
                </div>
            <?php endif; ?>

            <div class="result-actions">
                <?php if ($siparisId > 0): ?>
                    <a href="odeme.php?siparis=<?= (int)$siparisId ?>" class="result-btn result-btn--warning" aria-label="Odemeyi tekrar dene">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        Tekrar Dene
                    </a>
                <?php endif; ?>

                <?php if (!empty($qrToken)): ?>
                    <a href="<?= e($basePath) ?>/menu/?t=<?= e($qrToken) ?>" class="result-btn result-btn--outline" aria-label="Menuye don">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Menuye Don
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php if ($basarili): ?>
<!-- Confetti Container -->
<div class="confetti-container" id="confettiContainer"></div>
<?php endif; ?>

<script src="assets/js/menu-utils.js"></script>
<?php if ($basarili): ?>
<script>
(function() {
    'use strict';

    // Confetti animasyonu
    var container = document.getElementById('confettiContainer');
    if (!container) return;

    var renkler = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#22c55e'];

    for (var i = 0; i < 60; i++) {
        (function(index) {
            setTimeout(function() {
                var confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = (Math.random() * 100) + '%';
                confetti.style.background = renkler[Math.floor(Math.random() * renkler.length)];
                confetti.style.width = (Math.random() * 8 + 5) + 'px';
                confetti.style.height = (Math.random() * 8 + 5) + 'px';
                confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                confetti.style.animationDelay = '0s';
                container.appendChild(confetti);

                setTimeout(function() {
                    if (confetti.parentNode) confetti.parentNode.removeChild(confetti);
                }, 4500);
            }, index * 50);
        })(i);
    }
})();
</script>
<?php endif; ?>

</body>
</html>
