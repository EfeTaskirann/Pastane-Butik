<?php
/**
 * Siparis Durum Takip Sayfasi
 *
 * Musteri tarafindan verilen siparislerin durumunu canli olarak takip eder.
 *
 * Erisim: `?token=<32-hex>` parametresi zorunludur. Token sipariş olusturuldugunda
 * DB'ye yazilir ve yalnizca ilgili musteriye gösterilen yanit/URL'de doner; bu sayede
 * IDOR (Insecure Direct Object Reference) engellenir — sayisal `id` ile enumeration
 * calismaz.
 *
 * Geriye donuk uyumluluk: eski `?id=` formatindaki URL'ler **403 Forbidden** doner
 * ve IP basina rate limit uygulanir (brute-force korumasi, mesaj sizintisi yok).
 *
 * @package Pastane\Menu
 * @since 2.1.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// --- 1. Eski ?id= kullanimini reddet (IDOR saldırisi koruması) ---
if (isset($_GET['id'])) {
    // Eski URL'lerden gelen her deneme icin rate limit (5/dk/IP).
    // Deneme sayisi da artirilir; saldirgan enumeration'i hizlandiramaz.
    try {
        \RateLimiter::enforce('siparis-takip-id-legacy');
    } catch (\Pastane\Exceptions\HttpException $e) {
        http_response_code($e->getStatusCode());
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Cok Fazla Istek</title></head><body>'
            . '<p>' . e($e->getMessage()) . '</p></body></html>';
        exit;
    }

    http_response_code(403);
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Erisim Engellendi</title></head><body>'
        . '<h1>Erisim Engellendi</h1>'
        . '<p>Guvenlik guncellemesi nedeniyle sipariş takip bağlantı yapısı değişti. '
        . 'Lütfen yeni takip bağlantınızı email/SMS üzerinden kontrol edin veya QR kodu tekrar tarayarak siparişinize ulaşın.</p>'
        . '</body></html>';
    exit;
}

// --- 2. Token zorunlu (yeni URL formati) ---
$takipToken = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($takipToken === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata</title></head><body>'
        . '<p>Takip bağlantısı eksik. Lütfen size gönderilen bağlantıyı kullanın.</p></body></html>';
    exit;
}

// Format validasyonu: 32 karakter hex. Hatali format = 404 (mesaj sizintisi yok).
if (!preg_match(\Pastane\Repositories\MasaSiparisRepository::TAKIP_TOKEN_REGEX, $takipToken)) {
    // Format denemelerinde de rate limit
    try {
        \RateLimiter::enforce('siparis-takip-token');
    } catch (\Pastane\Exceptions\HttpException $e) {
        http_response_code($e->getStatusCode());
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Cok Fazla Istek</title></head><body>'
            . '<p>' . e($e->getMessage()) . '</p></body></html>';
        exit;
    }
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Bulunamadı</title></head><body>'
        . '<p>Sipariş bulunamadı.</p></body></html>';
    exit;
}

// --- 3. Brute-force korumasi (gecerli token deneme hizini sinirla) ---
try {
    \RateLimiter::enforce('siparis-takip-token');
} catch (\Pastane\Exceptions\HttpException $e) {
    http_response_code($e->getStatusCode());
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Cok Fazla Istek</title></head><body>'
        . '<p>' . e($e->getMessage()) . '</p></body></html>';
    exit;
}

// --- 4. Siparis + masa bilgisi ---
$siparisRepo = new \Pastane\Repositories\MasaSiparisRepository();
$siparisService = masa_siparis_service();

try {
    $siparisRow = $siparisRepo->findByToken($takipToken);
    if ($siparisRow === null) {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Bulunamadı</title></head><body>'
            . '<p>Sipariş bulunamadı.</p></body></html>';
        exit;
    }

    $siparisId = (int)$siparisRow['id'];
    $siparis = $siparisService->getSiparisDetay($siparisId);

    // Masa bilgisini oturum uzerinden al (public endpoint — cookie opsiyonel)
    $masaNo = '?';
    $qrToken = '';
    if (!empty($siparis['oturum_id'])) {
        $oturumRepo = new \Pastane\Repositories\MasaOturumRepository();
        $oturumRow = $oturumRepo->find((int)$siparis['oturum_id']);
        if ($oturumRow !== null && !empty($oturumRow['masa_id'])) {
            $masaRepo = new \Pastane\Repositories\MasaRepository();
            $masaRow = $masaRepo->find((int)$oturumRow['masa_id']);
            if ($masaRow !== null) {
                $masaNo  = $masaRow['masa_no'] ?? '?';
                $qrToken = $masaRow['qr_token'] ?? '';
            }
        }
    }
} catch (\Pastane\Exceptions\HttpException $e) {
    http_response_code($e->getStatusCode());
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata</title></head><body><p>' . e($e->getMessage()) . '</p></body></html>';
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata</title></head><body><p>Beklenmeyen bir hata olustu.</p></body></html>';
    exit;
}

// Durum bilgileri
$durumlar = [
    'beklemede'     => ['etiket' => 'Siparis Alindi',  'ikon' => '&#10003;', 'sira' => 1],
    'onaylandi'     => ['etiket' => 'Onaylandi',       'ikon' => '&#10003;', 'sira' => 1],
    'hazirlaniyor'  => ['etiket' => 'Hazirlaniyor',    'ikon' => '&#9203;',  'sira' => 2],
    'hazir'         => ['etiket' => 'Hazir',           'ikon' => '&#127869;','sira' => 3],
    'teslim_edildi' => ['etiket' => 'Servis Edildi',   'ikon' => '&#9989;',  'sira' => 4],
    'iptal'         => ['etiket' => 'Iptal Edildi',    'ikon' => '&#10060;', 'sira' => 0],
];

$mevcutDurum = $siparis['durum'] ?? 'beklemede';
$mevcutSira = $durumlar[$mevcutDurum]['sira'] ?? 0;
$iptalMi = ($mevcutDurum === 'iptal');

// Odeme durumu badge
$odemeBadge = [
    'odenmedi' => ['etiket' => 'Odenmedi',  'sinif' => 'odeme-badge--odenmedi'],
    'odendi'   => ['etiket' => 'Odendi',    'sinif' => 'odeme-badge--odendi'],
    'iade'     => ['etiket' => 'Iade',      'sinif' => 'odeme-badge--iade'],
];
$odemeDurum = $siparis['odeme_durumu'] ?? 'odenmedi';

$basePath = config('app.base_path', '/pastane');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparis Durumu - Masa <?= e((string)$masaNo) ?></title>
    <link rel="stylesheet" href="assets/css/menu-base.css">
    <style>
        /* Sayfa ozel container ayari */
        .container {
            padding: 20px 16px 40px;
        }

        /* Siparis No */
        .siparis-no {
            text-align: center;
            margin-bottom: 8px;
            color: var(--menu-primary);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .siparis-zaman {
            text-align: center;
            margin-bottom: 24px;
            color: var(--menu-text-secondary);
            font-size: 0.8rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--menu-surface);
            border-radius: 16px;
            padding: 28px 20px;
            margin-bottom: 20px;
            box-shadow: var(--menu-shadow);
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 0 10px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            height: 3px;
            background: var(--menu-border);
            z-index: 1;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 20px;
            height: 3px;
            background: linear-gradient(90deg, var(--menu-success), #2ecc71);
            z-index: 2;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 3;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--menu-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--menu-text-secondary);
            border: 3px solid transparent;
        }

        .step.completed .step-circle {
            background: var(--menu-success);
            color: var(--menu-surface);
            border-color: var(--menu-success);
            transform: scale(1);
        }

        .step.active .step-circle {
            background: var(--menu-surface);
            color: var(--menu-primary);
            border-color: var(--menu-primary);
            transform: scale(1.15);
            box-shadow: 0 0 0 6px rgba(139, 69, 19, 0.15);
            animation: pulse-ring 2s ease-in-out infinite;
        }

        .step.cancelled .step-circle {
            background: var(--menu-danger);
            color: var(--menu-surface);
            border-color: var(--menu-danger);
        }

        @keyframes pulse-ring {
            0%, 100% { box-shadow: 0 0 0 4px rgba(139, 69, 19, 0.15); }
            50% { box-shadow: 0 0 0 10px rgba(139, 69, 19, 0.05); }
        }

        .step-label {
            margin-top: 10px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--menu-text-secondary);
            text-align: center;
            transition: color 0.4s ease;
            max-width: 70px;
        }

        .step.completed .step-label,
        .step.active .step-label {
            color: var(--menu-text);
            font-weight: 600;
        }

        /* Iptal durumu */
        .iptal-banner {
            background: linear-gradient(135deg, var(--menu-danger), #c0392b);
            color: var(--menu-surface);
            text-align: center;
            padding: 16px;
            border-radius: var(--menu-radius);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Siparis Detaylari */
        .detay-card {
            background: var(--menu-surface);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: var(--menu-shadow);
        }

        .detay-card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--menu-primary);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--menu-border);
        }

        .urun-satir {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--menu-bg);
        }

        .urun-satir:last-child { border-bottom: none; }

        .urun-bilgi {
            flex: 1;
        }

        .urun-ad {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--menu-text);
        }

        .urun-detay {
            font-size: 0.75rem;
            color: var(--menu-text-secondary);
            margin-top: 2px;
        }

        .urun-not {
            font-size: 0.72rem;
            color: var(--menu-primary);
            font-style: italic;
            margin-top: 2px;
        }

        .urun-fiyat {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--menu-text);
            white-space: nowrap;
            margin-left: 12px;
        }

        .toplam-satir {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            margin-top: 8px;
            border-top: 2px solid var(--menu-border);
        }

        .toplam-etiket {
            font-size: 1rem;
            font-weight: 600;
            color: var(--menu-text);
        }

        .toplam-tutar {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--menu-primary);
        }

        /* Odeme Badge — menu-base .badge kullanilabilir ama odeme-badge ozel kaliyor */
        .odeme-satir {
            margin-top: 14px;
            text-align: right;
        }

        .odeme-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--menu-surface);
        }

        .masada-odeme-bilgi {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 14px;
            padding: 12px 14px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            font-size: 0.82rem;
            color: #166534;
            line-height: 1.5;
        }

        .masada-odeme-bilgi svg {
            color: #16a34a;
            margin-top: 2px;
        }

        /* Butonlar — .btn, .btn-primary, .btn-outline menu-base.css'ten gelir */
        .btn-grup {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-grup .btn {
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(139, 69, 19, 0.35);
        }

        .btn-outline:hover {
            background: var(--menu-primary);
            color: var(--menu-surface);
        }

        /* Canli guncelleme gostergesi */
        .canli-gosterge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
            font-size: 0.75rem;
            color: var(--menu-text-secondary);
        }

        .canli-nokta {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--menu-success);
            animation: blink-dot 1.5s ease-in-out infinite;
        }

        @keyframes blink-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Hazir animasyonu */
        .hazir-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(34, 197, 94, 0.92);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s ease;
        }

        .hazir-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .hazir-overlay .ikon {
            font-size: 5rem;
            animation: bounce-in 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        .hazir-overlay .mesaj {
            color: var(--menu-surface);
            font-size: 1.4rem;
            font-weight: 700;
            margin-top: 16px;
        }

        .hazir-overlay .alt-mesaj {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            margin-top: 8px;
        }

        .hazir-overlay .kapat-btn {
            margin-top: 32px;
            padding: 12px 32px;
            background: var(--menu-surface);
            color: var(--menu-success);
            border: none;
            border-radius: 30px;
            font-family: var(--menu-font);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        @keyframes bounce-in {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Confetti */
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

        /* Odeme durumu siniflar */
        .odeme-badge--odenmedi { background: var(--menu-danger); }
        .odeme-badge--odendi { background: var(--menu-success); }
        .odeme-badge--iade { background: var(--menu-warning); }

        /* Siparis notu metin */
        .siparis-notu-metin {
            font-size: 0.85rem;
            color: var(--menu-text-secondary);
        }

        /* Tamamlandi metni */
        .tamamlandi-metin {
            color: var(--menu-text-secondary);
        }

        /* Responsive */
        @media (max-width: 380px) {
            .step-label { font-size: 0.6rem; max-width: 55px; }
            .step-circle { width: 34px; height: 34px; font-size: 0.85rem; }
        }
    </style>
</head>
<body>

<!-- Ust Bar -->
<header class="top-bar">
    <span class="top-bar__title">Siparis Durumu</span>
    <span class="top-bar__masa">Masa <?= e((string)$masaNo) ?></span>
</header>

<div class="container">
    <!-- Siparis No ve Zaman -->
    <div class="siparis-no">Siparis #<?= e((string)$siparisId) ?></div>
    <div class="siparis-zaman"><?= e(date('d.m.Y H:i', strtotime($siparis['siparis_zamani'] ?? $siparis['created_at'] ?? 'now'))) ?></div>

    <?php if ($iptalMi): ?>
        <div class="iptal-banner">Bu siparis iptal edilmistir.</div>
    <?php endif; ?>

    <!-- Progress Bar -->
    <div class="progress-container" id="progressContainer">
        <div class="progress-steps">
            <?php
            $adimlar = [
                ['durum' => 'beklemede',     'etiket' => 'Siparis Alindi',  'ikon' => '&#10003;'],
                ['durum' => 'hazirlaniyor',  'etiket' => 'Hazirlaniyor',   'ikon' => '&#9203;'],
                ['durum' => 'hazir',         'etiket' => 'Hazir',          'ikon' => '&#127869;'],
                ['durum' => 'teslim_edildi', 'etiket' => 'Servis Edildi',  'ikon' => '&#9989;'],
            ];

            // Her adimin sira numarasi
            $adimSiralari = [
                'beklemede'     => 1,
                'onaylandi'     => 1,
                'hazirlaniyor'  => 2,
                'hazir'         => 3,
                'teslim_edildi' => 4,
            ];

            // Progress line genislik yuzdesi
            $linePercent = 0;
            if (!$iptalMi && $mevcutSira > 0) {
                $linePercent = (($mevcutSira - 1) / 3) * 100;
            }
            ?>

            <?php /* Dinamik progress genisligi — PHP uretimi, CSS custom property ile */ ?>
            <div class="progress-line u-progress-fill" id="progressLine" style="--u-progress: <?= (float)$linePercent ?>%;"></div>

            <?php foreach ($adimlar as $index => $adim):
                $adimSira = $adimSiralari[$adim['durum']] ?? 0;
                $sinif = '';
                if ($iptalMi) {
                    $sinif = 'cancelled';
                } elseif ($mevcutSira > $adimSira) {
                    $sinif = 'completed';
                } elseif ($mevcutSira === $adimSira) {
                    $sinif = 'active';
                }
            ?>
                <div class="step <?= $sinif ?>" data-durum="<?= e($adim['durum']) ?>">
                    <div class="step-circle"><?= $adim['ikon'] ?></div>
                    <span class="step-label"><?= e($adim['etiket']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Siparis Detaylari -->
    <div class="detay-card">
        <h3>Sipariş Detayları</h3>

        <?php foreach ($siparis['kalemler'] as $kalem): ?>
            <div class="urun-satir">
                <div class="urun-bilgi">
                    <div class="urun-ad"><?= e($kalem['urun_adi'] ?? 'Ürün') ?></div>
                    <div class="urun-detay">
                        <?= (int)($kalem['adet'] ?? 1) ?> adet
                        <?php if (!empty($kalem['porsiyon'])): ?>
                            &middot; <?= e($kalem['porsiyon']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($kalem['ozel_not'])): ?>
                        <div class="urun-not"><?= e($kalem['ozel_not']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="urun-fiyat"><?= number_format((float)($kalem['toplam_fiyat'] ?? 0), 2, ',', '.') ?> &#8378;</div>
            </div>
        <?php endforeach; ?>

        <div class="toplam-satir">
            <span class="toplam-etiket">Toplam</span>
            <span class="toplam-tutar" id="toplamTutar"><?= number_format((float)($siparis['toplam_tutar'] ?? 0), 2, ',', '.') ?> &#8378;</span>
        </div>

        <!-- Odeme Durumu -->
        <div class="odeme-satir">
            <span class="odeme-badge <?= e($odemeBadge[$odemeDurum]['sinif'] ?? '') ?>">
                <?= e($odemeBadge[$odemeDurum]['etiket'] ?? 'Bilinmiyor') ?>
            </span>
        </div>

        <?php if (($siparis['odeme_yontemi'] ?? '') === 'masada'): ?>
        <div class="masada-odeme-bilgi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="u-icon-20">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path>
            </svg>
            <span>Ödemenizi garson geldiğinde <strong>nakit</strong> veya <strong>POS cihazı</strong> ile yapabilirsiniz.</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($siparis['siparis_notu'])): ?>
        <div class="detay-card">
            <h3>Sipariş Notu</h3>
            <p class="siparis-notu-metin"><?= e($siparis['siparis_notu']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Butonlar -->
    <div class="btn-grup">
        <a href="<?= e($basePath) ?>/menu/?t=<?= e($qrToken) ?>" class="btn btn-primary">
            Tekrar Sipariş Ver
        </a>
    </div>

    <!-- Canli guncelleme gostergesi -->
    <div class="canli-gosterge" id="canliGosterge">
        <span class="canli-nokta"></span>
        <span>Canlı takip aktif</span>
    </div>
</div>

<!-- Hazir Bildirimi Overlay -->
<div class="hazir-overlay" id="hazirOverlay">
    <div class="ikon">&#127881;</div>
    <div class="mesaj">Siparişiniz Hazır!</div>
    <div class="alt-mesaj">Garson siparişinizi getiriyor</div>
    <button class="kapat-btn" type="button" data-action="close-hazir-overlay">Tamam</button>
</div>

<!-- Confetti Container -->
<div class="confetti-container" id="confettiContainer"></div>

<script src="assets/js/menu-utils.js"></script>
<script>
(function() {
    'use strict';

    var SIPARIS_ID = <?= (int)$siparisId ?>;
    var TAKIP_TOKEN = <?= json_encode($takipToken, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE) ?>;
    var POLLING_INTERVAL = 15000; // 15 saniye
    var oncekiDurum = <?= json_encode($mevcutDurum, JSON_UNESCAPED_UNICODE) ?>;
    var pollingTimer = null;

    // Durum sira haritasi
    var durumSiralari = {
        'beklemede': 1,
        'onaylandi': 1,
        'hazirlaniyor': 2,
        'hazir': 3,
        'teslim_edildi': 4,
        'iptal': 0
    };

    // Adim durumlari (progress bar'daki 4 adim)
    var adimDurumlari = ['beklemede', 'hazirlaniyor', 'hazir', 'teslim_edildi'];

    /**
     * Siparis durumunu API'den sorgula
     */
    function durumSorgula() {
        var oturumToken = getCookie('oturum_token');
        if (!oturumToken) return;

        fetch(BASE_PATH + '/api/v1/menu/siparis/' + SIPARIS_ID + '/durum', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Oturum-Token': oturumToken
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function(json) {
            if (json.success && json.data && json.data.durum) {
                var yeniDurum = json.data.durum;
                if (yeniDurum !== oncekiDurum) {
                    durumGuncelle(yeniDurum);
                    oncekiDurum = yeniDurum;
                }
            }
        })
        .catch(function(err) {
            console.warn('Durum sorgusu hatasi:', err.message);
        });
    }

    /**
     * Progress bar'i yeni duruma gore guncelle
     */
    function durumGuncelle(yeniDurum) {
        var sira = durumSiralari[yeniDurum] || 0;
        var iptalMi = (yeniDurum === 'iptal');

        // Progress line genisligi
        var progressLine = document.getElementById('progressLine');
        if (progressLine) {
            if (iptalMi) {
                progressLine.style.width = '0%';
                progressLine.style.background = 'var(--menu-danger)';
            } else {
                var yuzde = ((sira - 1) / 3) * 100;
                progressLine.style.width = Math.max(0, yuzde) + '%';
            }
        }

        // Adimlari guncelle
        var adimlar = document.querySelectorAll('.step');
        adimlar.forEach(function(adimEl, index) {
            var adimDurum = adimDurumlari[index];
            var adimSira = durumSiralari[adimDurum] || 0;

            adimEl.classList.remove('completed', 'active', 'cancelled');

            if (iptalMi) {
                adimEl.classList.add('cancelled');
            } else if (sira > adimSira) {
                adimEl.classList.add('completed');
            } else if (sira === adimSira) {
                adimEl.classList.add('active');
            }
        });

        // Iptal banner goster/gizle
        if (iptalMi) {
            var container = document.querySelector('.container');
            if (container && !document.querySelector('.iptal-banner')) {
                var banner = document.createElement('div');
                banner.className = 'iptal-banner';
                banner.textContent = 'Bu siparis iptal edilmistir.';
                var progressContainer = document.getElementById('progressContainer');
                if (progressContainer) {
                    container.insertBefore(banner, progressContainer);
                }
            }
        }

        // Hazir bildirimi
        if (yeniDurum === 'hazir') {
            hazirBildirimi();
        }

        // Teslim edildi veya iptal ise polling durdur
        if (yeniDurum === 'teslim_edildi' || yeniDurum === 'iptal') {
            pollingDurdur();
            var gosterge = document.getElementById('canliGosterge');
            if (gosterge) {
                gosterge.innerHTML = '<span class="tamamlandi-metin">Siparis tamamlandi</span>';
            }
        }
    }

    /**
     * Siparis hazir animasyonu
     */
    function hazirBildirimi() {
        // Overlay goster
        var overlay = document.getElementById('hazirOverlay');
        if (overlay) {
            overlay.classList.add('active');
        }

        // Confetti efekti
        confettiBaslat();

        // 5 saniye sonra otomatik kapat
        setTimeout(function() {
            if (overlay) overlay.classList.remove('active');
        }, 5000);
    }

    /**
     * Confetti animasyonu
     */
    function confettiBaslat() {
        var container = document.getElementById('confettiContainer');
        if (!container) return;

        var renkler = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22'];

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
    }

    /**
     * Polling baslat
     */
    function pollingBaslat() {
        if (oncekiDurum === 'teslim_edildi' || oncekiDurum === 'iptal') return;
        pollingTimer = setInterval(durumSorgula, POLLING_INTERVAL);
    }

    /**
     * Polling durdur
     */
    function pollingDurdur() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    // Event delegation — data-action handler'lari (CSP uyumlu, inline onclick yerine)
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-action]');
        if (!trigger) return;
        var action = trigger.getAttribute('data-action');
        if (action === 'close-hazir-overlay') {
            var overlay = document.getElementById('hazirOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    });

    // Sayfa yuklendikten sonra polling baslat
    pollingBaslat();

    // Sayfa gizlendiginde polling durdur, gosterildiginde tekrar baslat
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            pollingDurdur();
        } else {
            durumSorgula(); // Hemen kontrol et
            pollingBaslat();
        }
    });
})();
</script>

</body>
</html>
