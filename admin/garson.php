<?php
/**
 * Garson Mobil Servis Arayuzu
 *
 * Garsonlarin telefonundan eristigi minimal arayuz.
 * Sadece "hazir" siparisleri gosterir — garson siparisi masaya
 * goturdukten sonra "Teslim Ettim" butonuna basar.
 *
 * Standalone sayfa: admin sidebar kullanmaz, kendi minimal header'i vardir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

$siparisService = masa_siparis_service();

// ============================================
// AJAX Odeme Alma (POST) — Nakit / POS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['odeme_al'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCSRF()) {
        http_response_code(403);
        echo json_encode(['basarili' => false, 'mesaj' => 'Guvenlik dogrulamasi basarisiz.']);
        exit;
    }

    $siparisId = (int)($_POST['siparis_id'] ?? 0);
    $yontem = trim((string)($_POST['yontem'] ?? ''));

    if ($siparisId <= 0) {
        http_response_code(400);
        echo json_encode(['basarili' => false, 'mesaj' => 'Gecersiz siparis ID.']);
        exit;
    }

    try {
        $result = $siparisService->garsonOdemeAl($siparisId, $yontem);
        echo json_encode(['basarili' => true, 'mesaj' => $result['mesaj'] ?? 'Odeme alindi.']);
    } catch (ValidationException $e) {
        http_response_code(422);
        echo json_encode(['basarili' => false, 'mesaj' => $e->getMessage()]);
    } catch (HttpException $e) {
        http_response_code($e->getCode() ?: 400);
        echo json_encode(['basarili' => false, 'mesaj' => $e->getMessage()]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['basarili' => false, 'mesaj' => 'Bir hata olustu.']);
    }
    exit;
}

// ============================================
// AJAX Teslim Islemi (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teslim_et'])) {
    // AJAX isteklerinde JSON yanit don
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    header('Content-Type: application/json; charset=utf-8');

    if (!verifyCSRF()) {
        http_response_code(403);
        echo json_encode(['basarili' => false, 'mesaj' => 'Guvenlik dogrulamasi basarisiz.']);
        exit;
    }

    $siparisId = (int)($_POST['siparis_id'] ?? 0);
    if ($siparisId <= 0) {
        http_response_code(400);
        echo json_encode(['basarili' => false, 'mesaj' => 'Gecersiz siparis ID.']);
        exit;
    }

    try {
        $result = $siparisService->siparisDurumGuncelle($siparisId, 'teslim_edildi');
        echo json_encode(['basarili' => true, 'mesaj' => $result['mesaj'] ?? 'Siparis teslim edildi.']);
    } catch (ValidationException $e) {
        http_response_code(422);
        echo json_encode(['basarili' => false, 'mesaj' => $e->getMessage()]);
    } catch (HttpException $e) {
        http_response_code($e->getCode() ?: 400);
        echo json_encode(['basarili' => false, 'mesaj' => $e->getMessage()]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['basarili' => false, 'mesaj' => 'Bir hata olustu.']);
    }
    exit;
}

// ============================================
// AJAX Yenileme (GET + X-Requested-With)
// ============================================
$isAjaxGet = ($_SERVER['REQUEST_METHOD'] === 'GET')
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjaxGet) {
    header('Content-Type: application/json; charset=utf-8');
    $hazirSiparisler = $siparisService->getHazirSiparisler();

    // Kalemleri toplu yukle (N+1 onleme — Sprint 4 audit)
    // Eskiden: her siparis icin findOrFail + getSiparisKalemleri = 1 + 2N query
    // Simdi: 1 liste + 1 IN query
    if (!empty($hazirSiparisler)) {
        $siparisRepo = new \Pastane\Repositories\MasaSiparisRepository();
        $siparisIds  = array_map(static fn ($s) => (int)$s['id'], $hazirSiparisler);
        $kalemMap    = $siparisRepo->getKalemlerBySiparisIds($siparisIds);
        foreach ($hazirSiparisler as &$siparis) {
            $siparis['kalemler'] = $kalemMap[(int)$siparis['id']] ?? [];
        }
        unset($siparis);
    }

    echo json_encode([
        'basarili' => true,
        'siparisler' => $hazirSiparisler,
        'adet' => count($hazirSiparisler),
    ]);
    exit;
}

// ============================================
// Normal Sayfa Yuklemesi
// ============================================
$hazirSiparisler = $siparisService->getHazirSiparisler();

// Kalemleri toplu yukle (N+1 onleme — Sprint 4 audit)
if (!empty($hazirSiparisler)) {
    $siparisRepo = new \Pastane\Repositories\MasaSiparisRepository();
    $siparisIds  = array_map(static fn ($s) => (int)$s['id'], $hazirSiparisler);
    $kalemMap    = $siparisRepo->getKalemlerBySiparisIds($siparisIds);
    foreach ($hazirSiparisler as &$siparis) {
        $siparis['kalemler'] = $kalemMap[(int)$siparis['id']] ?? [];
    }
    unset($siparis);
}

$csrfField = csrfTokenField();
$nonce = getCspNonce();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Garson Paneli - <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style nonce="<?= $nonce ?>">
        /* =============================================
           GARSON PANELI - MOBIL ONCELIKLI TASARIM
           ============================================= */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --garson-primary: #8B6F5C;
            --garson-primary-light: #A68B7B;
            --garson-primary-dark: #6B5344;
            --garson-success: #10B981;
            --garson-success-dark: #059669;
            --garson-success-light: #D1FAE5;
            --garson-bg: #F8FAFC;
            --garson-card: #FFFFFF;
            --garson-text: #1E293B;
            --garson-text-secondary: #64748B;
            --garson-text-muted: #94A3B8;
            --garson-border: #E2E8F0;
            --garson-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --garson-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --garson-radius: 16px;
            --garson-radius-sm: 10px;
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: var(--font-family);
            background: var(--garson-bg);
            color: var(--garson-text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ---- Ust Bar ---- */
        .garson-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--garson-primary);
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .garson-header__baslik {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .garson-header__baslik svg {
            width: 22px;
            height: 22px;
        }

        .garson-header__aksiyonlar {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .garson-header__link {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .garson-header__link:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }

        .garson-ses-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            -webkit-tap-highlight-color: transparent;
        }

        .garson-ses-btn:hover,
        .garson-ses-btn:active {
            background: rgba(255,255,255,0.25);
        }

        .garson-ses-btn svg {
            width: 20px;
            height: 20px;
        }

        .garson-ses-btn--kapali {
            opacity: 0.5;
        }

        /* ---- Sayac Bar ---- */
        .garson-sayac {
            background: var(--garson-card);
            border-bottom: 1px solid var(--garson-border);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--garson-text-secondary);
        }

        .garson-sayac__adet {
            font-weight: 600;
            color: var(--garson-text);
        }

        .garson-sayac__yenileme {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--garson-text-muted);
        }

        .garson-sayac__yenileme svg {
            width: 14px;
            height: 14px;
            animation: garson-spin 2s linear infinite;
        }

        @keyframes garson-spin {
            to { transform: rotate(360deg); }
        }

        /* ---- Siparis Listesi ---- */
        .garson-liste {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ---- Siparis Karti ---- */
        .garson-kart {
            background: var(--garson-card);
            border-radius: var(--garson-radius);
            box-shadow: var(--garson-shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.4s, background-color 0.3s;
        }

        .garson-kart__ust {
            padding: 16px 16px 12px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .garson-kart__masa {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            color: var(--garson-primary);
        }

        .garson-kart__masa-etiket {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--garson-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 2px;
        }

        .garson-kart__zaman {
            text-align: right;
            flex-shrink: 0;
        }

        .garson-kart__saat {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--garson-text);
        }

        .garson-kart__gecen {
            font-size: 0.75rem;
            color: var(--garson-text-muted);
        }

        /* ---- Urun Listesi ---- */
        .garson-kart__urunler {
            padding: 0 16px 12px;
        }

        .garson-kart__urunler ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .garson-kart__urunler li {
            padding: 4px 0;
            font-size: 0.9rem;
            color: var(--garson-text);
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .garson-kart__urunler li + li {
            border-top: 1px solid var(--garson-border);
            padding-top: 6px;
        }

        .garson-urun-adet {
            font-weight: 600;
            color: var(--garson-primary);
            min-width: 28px;
        }

        .garson-urun-adi {
            flex: 1;
        }

        .garson-urun-porsiyon {
            font-size: 0.78rem;
            color: var(--garson-text-muted);
        }

        .garson-kart__masada-odeme {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 16px 10px;
            padding: 8px 12px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #92400e;
            letter-spacing: 0.02em;
        }

        .garson-kart__not {
            padding: 0 16px 12px;
            font-size: 0.8rem;
            color: var(--garson-text-secondary);
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .garson-kart__not svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 2px;
            color: var(--garson-text-muted);
        }

        /* ---- Teslim Butonu ---- */
        .garson-kart__aksiyon {
            padding: 0 16px 16px;
        }

        .garson-teslim-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            min-height: 56px;
            padding: 14px 20px;
            border: none;
            border-radius: var(--garson-radius-sm);
            background: var(--garson-success);
            color: #fff;
            font-family: var(--font-family);
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
            -webkit-tap-highlight-color: transparent;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }

        .garson-teslim-btn:hover {
            background: var(--garson-success-dark);
        }

        .garson-teslim-btn:active {
            transform: scale(0.97);
        }

        .garson-teslim-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .garson-teslim-btn svg {
            width: 22px;
            height: 22px;
        }

        /* ---- Nakit / POS Odeme Butonlari ---- */
        .garson-odeme-grubu {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }

        .garson-odeme-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 48px;
            padding: 10px 12px;
            border: none;
            border-radius: var(--garson-radius-sm);
            font-family: var(--font-family);
            font-size: 0.92rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            transition: background 0.2s, transform 0.1s;
            -webkit-tap-highlight-color: transparent;
        }

        .garson-odeme-btn:active {
            transform: scale(0.97);
        }

        .garson-odeme-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .garson-odeme-btn svg {
            width: 18px;
            height: 18px;
        }

        .garson-odeme-btn--nakit {
            background: #2563eb;
        }

        .garson-odeme-btn--nakit:hover {
            background: #1d4ed8;
        }

        .garson-odeme-btn--pos {
            background: #7c3aed;
        }

        .garson-odeme-btn--pos:hover {
            background: #6d28d9;
        }

        .garson-odeme-alindi {
            margin: 0 16px 10px;
            padding: 8px 12px;
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #065f46;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ---- Teslim Animasyonu ---- */
        .garson-kart--teslim-ediliyor {
            background-color: var(--garson-success-light) !important;
            pointer-events: none;
        }

        .garson-kart--teslim-ediliyor .garson-teslim-btn {
            background: var(--garson-success-dark);
        }

        .garson-kart--kaybolma {
            opacity: 0;
            transform: translateX(80px) scale(0.95);
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        /* Teslim tik animasyonu */
        .garson-teslim-tik {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--garson-success-dark);
            font-weight: 600;
            font-size: 1rem;
        }

        .garson-kart--teslim-ediliyor .garson-teslim-tik {
            display: flex;
        }

        .garson-kart--teslim-ediliyor .garson-teslim-btn {
            display: none;
        }

        .garson-teslim-tik svg {
            width: 28px;
            height: 28px;
            animation: garson-tik-bounce 0.5s ease;
        }

        @keyframes garson-tik-bounce {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ---- Bos Durum ---- */
        .garson-bos {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 24px;
            min-height: calc(100vh - 120px);
        }

        .garson-bos__ikon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--garson-success-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .garson-bos__ikon svg {
            width: 40px;
            height: 40px;
            color: var(--garson-success);
        }

        .garson-bos__baslik {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--garson-text);
            margin-bottom: 8px;
        }

        .garson-bos__aciklama {
            font-size: 0.88rem;
            color: var(--garson-text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .garson-bos__aciklama svg {
            width: 14px;
            height: 14px;
            animation: garson-spin 2s linear infinite;
        }

        /* ---- Hata Toast ---- */
        .garson-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1E293B;
            color: #fff;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 9999;
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
            max-width: calc(100vw - 32px);
            text-align: center;
        }

        .garson-toast--hata {
            background: #DC2626;
        }

        .garson-toast--basarili {
            background: var(--garson-success-dark);
        }

        .garson-toast.aktif {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        /* ---- Responsive ---- */
        @media (min-width: 500px) {
            .garson-liste {
                padding: 16px;
                gap: 16px;
            }

            .garson-kart__masa {
                font-size: 3.5rem;
            }
        }

        @media (min-width: 768px) {
            .garson-liste {
                max-width: 540px;
            }
        }
    </style>
</head>
<body>
    <!-- Ust Bar -->
    <header class="garson-header">
        <div class="garson-header__baslik">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                <path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
            Garson Paneli
        </div>
        <div class="garson-header__aksiyonlar">
            <a href="masa-siparisleri.php" class="garson-header__link">Admin'e Don</a>
            <button type="button"
                    class="garson-ses-btn"
                    id="sesBtn"
                    data-ses-acik="true"
                    aria-label="Bildirim sesini kapat"
                    aria-pressed="true"
                    title="Bildirim sesi">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="sesIkon" aria-hidden="true">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 010 14.14" id="sesDalga1"/>
                    <path d="M15.54 8.46a5 5 0 010 7.07" id="sesDalga2"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Sayac -->
    <div class="garson-sayac" id="garsonSayac" role="status" aria-live="polite">
        <div>
            <span class="garson-sayac__adet" id="siparisAdet"><?= count($hazirSiparisler) ?></span>
            teslim bekleyen siparis
        </div>
        <div class="garson-sayac__yenileme">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="23 4 23 10 17 10"/>
                <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
            </svg>
            Otomatik yenileniyor
        </div>
    </div>

    <!-- Siparis Listesi / Bos Durum -->
    <div id="garsonIcerik">
        <?php if (empty($hazirSiparisler)): ?>
            <div class="garson-bos" id="garsonBos" role="status" aria-live="polite">
                <div class="garson-bos__ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="garson-bos__baslik">Su an teslim bekleyen siparis yok</div>
                <div class="garson-bos__aciklama">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Otomatik yenileniyor...
                </div>
            </div>
        <?php else: ?>
            <div class="garson-liste" id="garsonListe">
                <?php foreach ($hazirSiparisler as $siparis):
                    $siparisId = (int)$siparis['id'];
                    $masaNo = (int)($siparis['masa_no'] ?? 0);
                    $kalemler = $siparis['kalemler'] ?? [];
                    $siparisNotu = $siparis['siparis_notu'] ?? '';

                    $siparisZamani = strtotime($siparis['siparis_zamani'] ?? 'now');
                    $saatStr = date('H:i', $siparisZamani);
                    $gecenDakika = max(0, (int)((time() - $siparisZamani) / 60));
                    if ($gecenDakika < 60) {
                        $gecenStr = $gecenDakika . ' dk once';
                    } else {
                        $gecenStr = (int)($gecenDakika / 60) . ' saat once';
                    }
                ?>
                    <div class="garson-kart"
                         data-siparis-id="<?= $siparisId ?>"
                         data-masa-no="<?= $masaNo ?>">
                        <!-- Masa ve Zaman -->
                        <div class="garson-kart__ust">
                            <div>
                                <span class="garson-kart__masa-etiket">Masa</span>
                                <div class="garson-kart__masa"><?= $masaNo ?></div>
                            </div>
                            <div class="garson-kart__zaman">
                                <div class="garson-kart__saat"><?= e($saatStr) ?></div>
                                <div class="garson-kart__gecen"><?= e($gecenStr) ?></div>
                            </div>
                        </div>

                        <?php
                        $odemeYontemi = $siparis['odeme_yontemi'] ?? '';
                        $odemeDurumu = $siparis['odeme_durumu'] ?? 'odenmedi';
                        $odemeBekliyor = ($odemeYontemi === 'masada' && $odemeDurumu !== 'odendi');
                        $odemeAlindi = ($odemeDurumu === 'odendi');
                        ?>
                        <?php if ($odemeBekliyor): ?>
                        <div class="garson-kart__masada-odeme">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="u-icon-16">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path>
                            </svg>
                            MASADA ODEME — Nakit/POS
                        </div>
                        <?php elseif ($odemeAlindi): ?>
                        <div class="garson-odeme-alindi">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" class="u-icon-14">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            ODEME ALINDI (<?= e(strtoupper($odemeYontemi)) ?>)
                        </div>
                        <?php endif; ?>

                        <!-- Urunler -->
                        <div class="garson-kart__urunler">
                            <ul>
                                <?php foreach ($kalemler as $kalem):
                                    $urunAdi = $kalem['urun_adi'] ?? 'Bilinmeyen Urun';
                                    $adet = (int)($kalem['adet'] ?? 1);
                                    $porsiyon = $kalem['porsiyon'] ?? null;
                                ?>
                                    <li>
                                        <span class="garson-urun-adet"><?= $adet ?>x</span>
                                        <span class="garson-urun-adi"><?= e($urunAdi) ?></span>
                                        <?php if ($porsiyon): ?>
                                            <span class="garson-urun-porsiyon">(<?= e($porsiyon) ?>)</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Siparis Notu -->
                        <?php if (!empty($siparisNotu)): ?>
                            <div class="garson-kart__not">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                <?= e($siparisNotu) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Teslim Butonu & Tik Animasyonu -->
                        <div class="garson-kart__aksiyon">
                            <button type="button"
                                    class="garson-teslim-btn"
                                    data-action="teslim"
                                    data-siparis-id="<?= $siparisId ?>"
                                    aria-label="Masa <?= $masaNo ?> siparisini teslim et">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                TESLIM ETTIM
                            </button>
                            <?php if ($odemeBekliyor): ?>
                            <div class="garson-odeme-grubu">
                                <button type="button"
                                        class="garson-odeme-btn garson-odeme-btn--nakit"
                                        data-action="odeme"
                                        data-yontem="nakit"
                                        data-siparis-id="<?= $siparisId ?>"
                                        aria-label="Masa <?= $masaNo ?> icin nakit odeme al">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    NAKIT
                                </button>
                                <button type="button"
                                        class="garson-odeme-btn garson-odeme-btn--pos"
                                        data-action="odeme"
                                        data-yontem="pos"
                                        data-siparis-id="<?= $siparisId ?>"
                                        aria-label="Masa <?= $masaNo ?> icin POS odeme al">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    POS
                                </button>
                            </div>
                            <?php endif; ?>
                            <div class="garson-teslim-tik" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Teslim Edildi
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Toast -->
    <div class="garson-toast" id="garsonToast" role="alert" aria-live="polite"></div>

    <!-- CSRF Token (JS icin) -->
    <input type="hidden" id="csrfToken" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? generateSecureCSRFToken()) ?>">

    <script nonce="<?= $nonce ?>">
    (function() {
        'use strict';

        // ============================================
        // Ayarlar
        // ============================================
        var YENILEME_SURESI = 15000; // 15 saniye
        var yenilemeTimer = null;
        var oncekiSiparisSayisi = <?= count($hazirSiparisler) ?>;
        var sesAcik = true;

        // ============================================
        // Ses Yonetimi
        // ============================================
        var audioContext = null;

        function bildirimSesiCal() {
            if (!sesAcik) return;
            try {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                // Cift bip ses
                [0, 300].forEach(function(delay) {
                    var osc = audioContext.createOscillator();
                    var gain = audioContext.createGain();
                    osc.connect(gain);
                    gain.connect(audioContext.destination);
                    osc.type = 'sine';
                    osc.frequency.value = 880;
                    gain.gain.value = 0.3;
                    var baslangic = audioContext.currentTime + delay / 1000;
                    osc.start(baslangic);
                    gain.gain.exponentialRampToValueAtTime(0.01, baslangic + 0.2);
                    osc.stop(baslangic + 0.25);
                });
            } catch (err) {
                // Ses API desteklenmiyorsa sessiz devam et
            }
        }

        // Ses butonu
        var sesBtn = document.getElementById('sesBtn');
        var sesDalga1 = document.getElementById('sesDalga1');
        var sesDalga2 = document.getElementById('sesDalga2');

        sesBtn.addEventListener('click', function() {
            sesAcik = !sesAcik;
            sesBtn.dataset.sesAcik = sesAcik ? 'true' : 'false';
            if (sesAcik) {
                sesBtn.classList.remove('garson-ses-btn--kapali');
                sesDalga1.style.display = '';
                sesDalga2.style.display = '';
                sesBtn.setAttribute('aria-label', 'Bildirim sesini kapat');
                sesBtn.setAttribute('aria-pressed', 'true');
                // AudioContext'i kullanici etkilesimi ile baslat
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
            } else {
                sesBtn.classList.add('garson-ses-btn--kapali');
                sesDalga1.style.display = 'none';
                sesDalga2.style.display = 'none';
                sesBtn.setAttribute('aria-label', 'Bildirim sesini ac');
                sesBtn.setAttribute('aria-pressed', 'false');
            }
        });

        // ============================================
        // Toast
        // ============================================
        var toastEl = document.getElementById('garsonToast');
        var toastTimer = null;

        function toastGoster(mesaj, tip) {
            toastEl.textContent = mesaj;
            toastEl.className = 'garson-toast aktif';
            if (tip === 'hata') {
                toastEl.classList.add('garson-toast--hata');
            } else if (tip === 'basarili') {
                toastEl.classList.add('garson-toast--basarili');
            }
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function() {
                toastEl.classList.remove('aktif');
            }, 3000);
        }

        // ============================================
        // Teslim Islemi (AJAX)
        // ============================================
        function teslimEt(siparisId, kartEl) {
            var btn = kartEl.querySelector('.garson-teslim-btn');
            if (btn.disabled) return;
            btn.disabled = true;

            var csrfToken = document.getElementById('csrfToken').value;

            var formData = new FormData();
            formData.append('teslim_et', '1');
            formData.append('siparis_id', siparisId);
            formData.append('csrf_token', csrfToken);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'garson.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                try {
                    var yanit = JSON.parse(xhr.responseText);
                } catch (e) {
                    btn.disabled = false;
                    toastGoster('Sunucu hatasi. Tekrar deneyin.', 'hata');
                    return;
                }

                if (yanit.basarili) {
                    // Basari animasyonu
                    kartEl.classList.add('garson-kart--teslim-ediliyor');

                    setTimeout(function() {
                        kartEl.classList.add('garson-kart--kaybolma');

                        setTimeout(function() {
                            kartEl.remove();
                            siparisleriGuncelle();
                        }, 400);
                    }, 800);
                } else {
                    btn.disabled = false;
                    toastGoster(yanit.mesaj || 'Islem basarisiz.', 'hata');
                }
            };

            xhr.onerror = function() {
                btn.disabled = false;
                toastGoster('Baglanti hatasi. Tekrar deneyin.', 'hata');
            };

            xhr.send(formData);
        }

        // ============================================
        // Odeme Alma (Nakit / POS) - AJAX
        // ============================================
        function odemeAl(siparisId, yontem, btn) {
            if (btn.disabled) return;
            btn.disabled = true;

            // Ayni kart icindeki diger odeme butonunu da pasiflestir
            var kart = btn.closest('.garson-kart');
            if (kart) {
                kart.querySelectorAll('[data-action="odeme"]').forEach(function (b) {
                    b.disabled = true;
                });
            }

            var csrfToken = document.getElementById('csrfToken').value;

            var formData = new FormData();
            formData.append('odeme_al', '1');
            formData.append('siparis_id', siparisId);
            formData.append('yontem', yontem);
            formData.append('csrf_token', csrfToken);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'garson.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function () {
                var yanit;
                try {
                    yanit = JSON.parse(xhr.responseText);
                } catch (e) {
                    if (kart) {
                        kart.querySelectorAll('[data-action="odeme"]').forEach(function (b) { b.disabled = false; });
                    }
                    toastGoster('Sunucu hatasi. Tekrar deneyin.', 'hata');
                    return;
                }

                if (yanit.basarili) {
                    toastGoster(yanit.mesaj || 'Odeme alindi.', 'basarili');
                    // Odeme alindi gostergesini ekle, odeme butonlarini ve badge'i kaldir
                    if (kart) {
                        var grup = kart.querySelector('.garson-odeme-grubu');
                        if (grup) grup.remove();
                        var badge = kart.querySelector('.garson-kart__masada-odeme');
                        if (badge) badge.remove();

                        var alindi = document.createElement('div');
                        alindi.className = 'garson-odeme-alindi';
                        alindi.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" class="u-icon-14">'
                            + '<polyline points="20 6 9 17 4 12"/></svg>'
                            + 'ODEME ALINDI (' + escapeHtml(yontem.toUpperCase()) + ')';
                        var urunlerEl = kart.querySelector('.garson-kart__urunler');
                        if (urunlerEl) {
                            urunlerEl.parentNode.insertBefore(alindi, urunlerEl);
                        } else {
                            kart.appendChild(alindi);
                        }
                    }
                } else {
                    if (kart) {
                        kart.querySelectorAll('[data-action="odeme"]').forEach(function (b) { b.disabled = false; });
                    }
                    toastGoster(yanit.mesaj || 'Odeme alinamadi.', 'hata');
                }
            };

            xhr.onerror = function () {
                if (kart) {
                    kart.querySelectorAll('[data-action="odeme"]').forEach(function (b) { b.disabled = false; });
                }
                toastGoster('Baglanti hatasi. Tekrar deneyin.', 'hata');
            };

            xhr.send(formData);
        }

        // Event delegation
        document.getElementById('garsonIcerik').addEventListener('click', function(e) {
            var teslimBtn = e.target.closest('[data-action="teslim"]');
            if (teslimBtn) {
                var kart = teslimBtn.closest('.garson-kart');
                if (kart) {
                    teslimEt(teslimBtn.dataset.siparisId, kart);
                }
                return;
            }

            var odemeBtn = e.target.closest('[data-action="odeme"]');
            if (odemeBtn) {
                odemeAl(odemeBtn.dataset.siparisId, odemeBtn.dataset.yontem, odemeBtn);
            }
        });

        // ============================================
        // Siparis Sayaci Guncelleme
        // ============================================
        function siparisleriGuncelle() {
            var kartlar = document.querySelectorAll('.garson-kart:not(.garson-kart--kaybolma)');
            var adet = kartlar.length;
            var adetEl = document.getElementById('siparisAdet');
            if (adetEl) {
                adetEl.textContent = adet;
            }

            // Bos duruma gec
            if (adet === 0) {
                bosGorunum();
            }
        }

        function bosGorunum() {
            var icerik = document.getElementById('garsonIcerik');
            icerik.innerHTML =
                '<div class="garson-bos" id="garsonBos" role="status" aria-live="polite">' +
                '  <div class="garson-bos__ikon">' +
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '      <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>' +
                '      <polyline points="22 4 12 14.01 9 11.01"/>' +
                '    </svg>' +
                '  </div>' +
                '  <div class="garson-bos__baslik">Su an teslim bekleyen siparis yok</div>' +
                '  <div class="garson-bos__aciklama">' +
                '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                '      <polyline points="23 4 23 10 17 10"/>' +
                '      <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>' +
                '    </svg>' +
                '    Otomatik yenileniyor...' +
                '  </div>' +
                '</div>';
        }

        // ============================================
        // Otomatik Yenileme (AJAX)
        // ============================================
        function siparisleriYenile() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'garson.php', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function() {
                if (xhr.status !== 200) return;

                try {
                    var yanit = JSON.parse(xhr.responseText);
                } catch (e) {
                    return;
                }

                if (!yanit.basarili) return;

                var siparisler = yanit.siparisler || [];
                var yeniAdet = siparisler.length;

                // Yeni siparis geldiyse ses cal
                if (yeniAdet > oncekiSiparisSayisi) {
                    bildirimSesiCal();
                }
                oncekiSiparisSayisi = yeniAdet;

                // Sayac guncelle
                var adetEl = document.getElementById('siparisAdet');
                if (adetEl) {
                    adetEl.textContent = yeniAdet;
                }

                var icerik = document.getElementById('garsonIcerik');

                if (yeniAdet === 0) {
                    if (!document.getElementById('garsonBos')) {
                        bosGorunum();
                    }
                    return;
                }

                // Mevcut kartlarin ID'lerini al
                var mevcutKartlar = {};
                document.querySelectorAll('.garson-kart').forEach(function(kart) {
                    mevcutKartlar[kart.dataset.siparisId] = kart;
                });

                // Yeni siparis ID'leri
                var yeniIdler = {};
                siparisler.forEach(function(s) {
                    yeniIdler[s.id] = true;
                });

                // Kaldirilan siparisleri kaldir (teslim edilen animasyonlu kartlari atla)
                Object.keys(mevcutKartlar).forEach(function(id) {
                    if (!yeniIdler[id] && !mevcutKartlar[id].classList.contains('garson-kart--teslim-ediliyor')) {
                        mevcutKartlar[id].remove();
                    }
                });

                // Yeni siparisleri ekle veya guncelle
                var liste = document.getElementById('garsonListe');
                if (!liste) {
                    icerik.innerHTML = '<div class="garson-liste" id="garsonListe"></div>';
                    liste = document.getElementById('garsonListe');
                }

                siparisler.forEach(function(siparis) {
                    if (mevcutKartlar[siparis.id]) return; // Zaten var

                    var kartHtml = kartOlustur(siparis);
                    liste.insertAdjacentHTML('beforeend', kartHtml);
                });
            };

            xhr.onerror = function() {
                // Sessiz hata — sonraki yenilemede tekrar denenir
            };

            xhr.send();
        }

        // ============================================
        // Kart HTML Olustur (JS tarafli)
        // ============================================
        function kartOlustur(siparis) {
            var masaNo = parseInt(siparis.masa_no) || 0;
            var siparisId = parseInt(siparis.id);
            var kalemler = siparis.kalemler || [];
            var siparisNotu = siparis.siparis_notu || '';

            var siparisZamani = siparis.siparis_zamani || '';
            var tarih = siparisZamani ? new Date(siparisZamani.replace(' ', 'T')) : new Date();
            var saatStr = ('0' + tarih.getHours()).slice(-2) + ':' + ('0' + tarih.getMinutes()).slice(-2);
            var gecenMs = Date.now() - tarih.getTime();
            var gecenDk = Math.max(0, Math.floor(gecenMs / 60000));
            var gecenStr = gecenDk < 60
                ? gecenDk + ' dk once'
                : Math.floor(gecenDk / 60) + ' saat once';

            var html = '<div class="garson-kart" data-siparis-id="' + siparisId + '" data-masa-no="' + masaNo + '">';
            html += '<div class="garson-kart__ust">';
            html += '<div><span class="garson-kart__masa-etiket">Masa</span>';
            html += '<div class="garson-kart__masa">' + escapeHtml(String(masaNo)) + '</div></div>';
            html += '<div class="garson-kart__zaman">';
            html += '<div class="garson-kart__saat">' + escapeHtml(saatStr) + '</div>';
            html += '<div class="garson-kart__gecen">' + escapeHtml(gecenStr) + '</div>';
            html += '</div></div>';

            var yontem = siparis.odeme_yontemi || '';
            var odemeDurumu = siparis.odeme_durumu || 'odenmedi';
            var odemeBekliyor = (yontem === 'masada' && odemeDurumu !== 'odendi');
            var odemeAlindi = (odemeDurumu === 'odendi');

            if (odemeBekliyor) {
                html += '<div class="garson-kart__masada-odeme">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="u-icon-16">';
                html += '<line x1="12" y1="1" x2="12" y2="23"></line>';
                html += '<path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"></path></svg>';
                html += 'MASADA ODEME — Nakit/POS</div>';
            } else if (odemeAlindi) {
                html += '<div class="garson-odeme-alindi">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" class="u-icon-14">';
                html += '<polyline points="20 6 9 17 4 12"/></svg>';
                html += 'ODEME ALINDI (' + escapeHtml(yontem.toUpperCase()) + ')</div>';
            }

            html += '<div class="garson-kart__urunler"><ul>';
            kalemler.forEach(function(kalem) {
                var urunAdi = kalem.urun_adi || 'Bilinmeyen Urun';
                var adet = parseInt(kalem.adet) || 1;
                var porsiyon = kalem.porsiyon || null;
                html += '<li>';
                html += '<span class="garson-urun-adet">' + adet + 'x</span>';
                html += '<span class="garson-urun-adi">' + escapeHtml(urunAdi) + '</span>';
                if (porsiyon) {
                    html += '<span class="garson-urun-porsiyon">(' + escapeHtml(porsiyon) + ')</span>';
                }
                html += '</li>';
            });
            html += '</ul></div>';

            if (siparisNotu) {
                html += '<div class="garson-kart__not">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
                html += '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>';
                html += '<line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                html += escapeHtml(siparisNotu);
                html += '</div>';
            }

            html += '<div class="garson-kart__aksiyon">';
            html += '<button type="button" class="garson-teslim-btn" data-action="teslim" data-siparis-id="' + siparisId + '"';
            html += ' aria-label="Masa ' + masaNo + ' siparisini teslim et">';
            html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
            html += '<polyline points="20 6 9 17 4 12"/></svg>';
            html += 'TESLIM ETTIM</button>';

            if (odemeBekliyor) {
                html += '<div class="garson-odeme-grubu">';
                html += '<button type="button" class="garson-odeme-btn garson-odeme-btn--nakit"'
                    + ' data-action="odeme" data-yontem="nakit" data-siparis-id="' + siparisId + '"'
                    + ' aria-label="Masa ' + masaNo + ' icin nakit odeme al">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
                html += '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>';
                html += 'NAKIT</button>';
                html += '<button type="button" class="garson-odeme-btn garson-odeme-btn--pos"'
                    + ' data-action="odeme" data-yontem="pos" data-siparis-id="' + siparisId + '"'
                    + ' aria-label="Masa ' + masaNo + ' icin POS odeme al">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">';
                html += '<rect x="3" y="5" width="18" height="14" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
                html += 'POS</button>';
                html += '</div>';
            }

            html += '<div class="garson-teslim-tik" aria-hidden="true">';
            html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">';
            html += '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>';
            html += '<polyline points="22 4 12 14.01 9 11.01"/></svg>';
            html += 'Teslim Edildi</div>';
            html += '</div>';

            html += '</div>';
            return html;
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // ============================================
        // Zamanlayici ve Visibility API
        // ============================================
        function zamanlayiciBaslat() {
            zamanlayiciDurdur();
            yenilemeTimer = setInterval(siparisleriYenile, YENILEME_SURESI);
        }

        function zamanlayiciDurdur() {
            if (yenilemeTimer) {
                clearInterval(yenilemeTimer);
                yenilemeTimer = null;
            }
        }

        // Sayfa gorunur/gorunmez oldugunda
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                zamanlayiciDurdur();
            } else {
                // Hemen bir kez yenile, sonra timer'i baslat
                siparisleriYenile();
                zamanlayiciBaslat();
            }
        });

        // Sayfa yuklendiginde timer baslat
        zamanlayiciBaslat();

    })();
    </script>
</body>
</html>
