<?php
/**
 * Mutfak Ekrani - Tam Ekran Siparis Takip
 *
 * Garsonlar ve mutfak personeli icin tasarlanmis,
 * tablet/TV uyumlu, koyu temali, canli guncellenen siparis ekrani.
 *
 * @package Pastane\Admin
 * @since 1.0.0
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

// Service instance
$siparisService = masa_siparis_service();

/**
 * Siparisleri oncelik sirasina gore siralar (FIFO + durum onceligi).
 *
 * @param array &$siparisler Siralanacak siparis dizisi (referans)
 * @return void
 */
function siparisleriSirala(array &$siparisler): void
{
    usort($siparisler, function ($a, $b) {
        $durumOncelik = [
            'onaylandi'    => 1,
            'beklemede'    => 1,
            'hazirlaniyor' => 2,
            'hazir'        => 3,
        ];
        $oa = $durumOncelik[$a['durum'] ?? ''] ?? 9;
        $ob = $durumOncelik[$b['durum'] ?? ''] ?? 9;
        if ($oa !== $ob) {
            return $oa - $ob;
        }
        return strtotime($a['siparis_zamani'] ?? 'now') - strtotime($b['siparis_zamani'] ?? 'now');
    });
}

// POST: Durum guncelleme (AJAX ve form destegi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX istegi mi?
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        // AJAX icin JSON response
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            $data = $_POST;
        }

        $csrfToken = $data['csrf_token'] ?? '';
        if (!validateSecureCSRFToken($csrfToken)) {
            json_response(['success' => false, 'error' => 'CSRF dogrulama basarisiz.'], 403);
        }

        $action = $data['action'] ?? '';
        $siparisId = (int)($data['siparis_id'] ?? 0);
        $yeniDurum = trim($data['yeni_durum'] ?? '');

        if ($action === 'durum_guncelle' && $siparisId > 0 && $yeniDurum !== '') {
            try {
                $result = $siparisService->siparisDurumGuncelle($siparisId, $yeniDurum);
                json_response(['success' => true, 'mesaj' => $result['mesaj'] ?? 'Durum guncellendi.']);
            } catch (ValidationException $e) {
                json_response(['success' => false, 'error' => $e->getMessage()], 422);
            } catch (HttpException $e) {
                json_response(['success' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
            } catch (\Exception $e) {
                json_response(['success' => false, 'error' => 'Bir hata olustu.'], 500);
            }
        }

        json_response(['success' => false, 'error' => 'Gecersiz istek.'], 400);
    }

    // Normal form POST
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: mutfak.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $siparisId = (int)($_POST['siparis_id'] ?? 0);
    $yeniDurum = trim($_POST['yeni_durum'] ?? '');

    if ($action === 'durum_guncelle' && $siparisId > 0 && $yeniDurum !== '') {
        try {
            $result = $siparisService->siparisDurumGuncelle($siparisId, $yeniDurum);
            setFlash('success', $result['mesaj'] ?? 'Siparis durumu guncellendi.');
        } catch (ValidationException $e) {
            setFlash('error', $e->getMessage());
        } catch (HttpException $e) {
            setFlash('error', $e->getMessage());
        } catch (\Exception $e) {
            setFlash('error', 'Bir hata olustu: ' . $e->getMessage());
        }
    }

    header('Location: mutfak.php');
    exit;
}

// AJAX yenileme istegi (GET + X-Requested-With)
$isAjaxGet = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $_SERVER['REQUEST_METHOD'] === 'GET';

if ($isAjaxGet) {
    // Aktif siparisleri getir (beklemede, onaylandi, hazirlaniyor, hazir)
    $tumSiparisler = $siparisService->getAktifSiparisler();
    $siparisRepo = new \Pastane\Repositories\MasaSiparisRepository();

    // N+1 fix (Sprint 1): Her siparis icin ayri sorgu yerine tek toplu sorgu
    $siparisIds = array_map(static fn ($s) => (int)$s['id'], $tumSiparisler);
    $kalemMap   = $siparisRepo->getKalemlerBySiparisIds($siparisIds);
    foreach ($tumSiparisler as &$ts) {
        $ts['kalemler'] = $kalemMap[(int)$ts['id']] ?? [];
    }
    unset($ts);

    // FIFO: en eski siparis once
    siparisleriSirala($tumSiparisler);

    // CSRF token'i yenile (AJAX icin)
    $csrfToken = $_SESSION['csrf_token'] ?? generateSecureCSRFToken();

    // Sadece frontend'in ihtiyac duydugu alanlari dondur (bant genisligi optimizasyonu)
    $filtrelenmis = array_map(function ($s) {
        return [
            'id'             => (int)$s['id'],
            'masa_no'        => (int)($s['masa_no'] ?? 0),
            'durum'          => $s['durum'] ?? 'beklemede',
            'siparis_zamani'  => $s['siparis_zamani'] ?? '',
            'siparis_notu'   => $s['siparis_notu'] ?? '',
            'kalemler'       => array_map(function ($k) {
                return [
                    'urun_adi'    => $k['urun_adi'] ?? '',
                    'adet'        => (int)($k['adet'] ?? 1),
                    'porsiyon'    => $k['porsiyon'] ?? null,
                    'ozel_not'    => $k['ozel_not'] ?? '',
                    'kategori_id' => isset($k['kategori_id']) ? (int)$k['kategori_id'] : null,
                ];
            }, $s['kalemler'] ?? []),
        ];
    }, $tumSiparisler);

    json_response([
        'success'    => true,
        'siparisler' => array_values($filtrelenmis),
        'toplam'     => count($filtrelenmis),
        'csrf_token' => $csrfToken,
        'sunucu_zamani' => date('Y-m-d H:i:s'),
    ]);
}

// Sayfa ilk yukleme: aktif siparisleri getir (beklemede, onaylandi, hazirlaniyor, hazir)
$tumSiparisler = $siparisService->getAktifSiparisler();
$siparisRepo = new \Pastane\Repositories\MasaSiparisRepository();

// N+1 fix (Sprint 1): Tek sorgu ile tum siparislerin kalemleri
$siparisIds = array_map(static fn ($s) => (int)$s['id'], $tumSiparisler);
$kalemMap   = $siparisRepo->getKalemlerBySiparisIds($siparisIds);
foreach ($tumSiparisler as &$ts) {
    $ts['kalemler'] = $kalemMap[(int)$ts['id']] ?? [];
}
unset($ts);

// FIFO: en eski siparis once, durum onceligi ile
siparisleriSirala($tumSiparisler);

$durumEtiketleri = $siparisService->getDurumEtiketleri();
$nonce = getCspNonce();

// Kategori listesi (filtre butonlari icin)
try {
    $kategoriler = db()->fetchAll("SELECT id, isim FROM kategoriler ORDER BY sira ASC, isim ASC");
} catch (\Exception $e) {
    $kategoriler = [];
}

// Alt kirilim sayaclari
$sayacAltKirilim = ['beklemede' => 0, 'onaylandi' => 0, 'hazirlaniyor' => 0, 'hazir' => 0];
foreach ($tumSiparisler as $s) {
    $d = $s['durum'] ?? '';
    if (isset($sayacAltKirilim[$d])) {
        $sayacAltKirilim[$d]++;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mutfak Ekrani - <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style nonce="<?= $nonce ?>">
        /* ============================================
         * MUTFAK EKRANI - KOYU TEMA
         * Tablet/TV uyumlu, buyuk fontlar
         * ============================================ */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0f0f1a;
            color: #e8e8f0;
            font-size: 16px;
            line-height: 1.4;
        }

        /* ============================================
         * UST BAR
         * ============================================ */
        .mutfak-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background: #1a1a2e;
            border-bottom: 2px solid #2a2a4a;
            height: 60px;
            flex-shrink: 0;
        }

        .mutfak-topbar__sol {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .mutfak-topbar__baslik {
            font-size: 1.3rem;
            font-weight: 700;
            color: #ff6b35;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mutfak-topbar__baslik svg {
            width: 28px;
            height: 28px;
        }

        .mutfak-topbar__saat {
            font-size: 1.5rem;
            font-weight: 600;
            color: #a0a0c0;
            font-variant-numeric: tabular-nums;
        }

        .mutfak-topbar__sag {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mutfak-topbar__sayac {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #2a2a4a;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #a0a0c0;
        }

        .mutfak-topbar__sayac strong {
            color: #ff6b35;
            font-size: 1.2rem;
        }

        .mutfak-topbar__sayac--alt {
            gap: 6px;
            padding: 4px 8px;
            background: transparent;
        }

        .mutfak-sayac-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #2a2a4a;
        }

        .mutfak-sayac-pill strong {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .mutfak-sayac-pill--sari {
            background: rgba(245, 158, 11, 0.18);
            color: #fbbf24;
        }

        .mutfak-sayac-pill--sari strong { color: #fbbf24; }

        .mutfak-sayac-pill--turuncu {
            background: rgba(249, 115, 22, 0.2);
            color: #fb923c;
        }

        .mutfak-sayac-pill--turuncu strong { color: #fb923c; }

        .mutfak-sayac-pill--yesil {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .mutfak-sayac-pill--yesil strong { color: #4ade80; }

        /* ============================================
         * KATEGORI FILTRE BAR
         * ============================================ */
        .mutfak-filtre-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #14142a;
            border-bottom: 1px solid #2a2a4a;
            overflow-x: auto;
            flex-shrink: 0;
            scrollbar-width: thin;
        }

        .mutfak-filtre-bar::-webkit-scrollbar {
            height: 4px;
        }

        .mutfak-filtre-bar::-webkit-scrollbar-thumb {
            background: #3a3a5a;
            border-radius: 2px;
        }

        .mutfak-filtre-etiket {
            font-size: 0.78rem;
            color: #888;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .mutfak-filtre-btn {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border: 1px solid #3a3a5a;
            border-radius: 20px;
            background: transparent;
            color: #a0a0c0;
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .mutfak-filtre-btn:hover {
            background: #2a2a4a;
            color: #e8e8f0;
        }

        .mutfak-filtre-btn.aktif {
            background: #ff6b35;
            border-color: #ff6b35;
            color: #fff;
        }

        .mutfak-kart.mutfak-kart--filtrelenmis {
            display: none;
        }

        .mutfak-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            text-decoration: none;
            color: #e8e8f0;
            background: #2a2a4a;
        }

        .mutfak-btn:hover {
            background: #3a3a5a;
        }

        .mutfak-btn:active {
            transform: scale(0.97);
        }

        .mutfak-btn svg {
            width: 18px;
            height: 18px;
        }

        .mutfak-btn--ses-acik {
            background: #1a5c2e;
            color: #4ade80;
        }

        .mutfak-btn--ses-kapali {
            background: #5c1a1a;
            color: #f87171;
        }

        /* ============================================
         * ANA ICERIK ALANI
         * ============================================ */
        .mutfak-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .mutfak-icerik {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 16px;
            scroll-behavior: smooth;
        }

        .mutfak-icerik::-webkit-scrollbar {
            width: 8px;
        }

        .mutfak-icerik::-webkit-scrollbar-track {
            background: #1a1a2e;
        }

        .mutfak-icerik::-webkit-scrollbar-thumb {
            background: #3a3a5a;
            border-radius: 4px;
        }

        /* ============================================
         * SIPARIS GRID
         * ============================================ */
        .mutfak-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        /* TV: 4-5 sutun */
        @media (min-width: 1600px) {
            .mutfak-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (min-width: 2200px) {
            .mutfak-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        /* Tablet: 2 sutun */
        @media (max-width: 1024px) {
            .mutfak-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Mobil: 1 sutun */
        @media (max-width: 640px) {
            .mutfak-grid {
                grid-template-columns: 1fr;
            }
            .mutfak-topbar {
                padding: 8px 12px;
                height: auto;
                flex-wrap: wrap;
                gap: 8px;
            }
            .mutfak-topbar__baslik {
                font-size: 1rem;
            }
            .mutfak-topbar__saat {
                font-size: 1.1rem;
            }
        }

        /* ============================================
         * SIPARIS KARTI
         * ============================================ */
        .mutfak-kart {
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: kartGiris 0.4s ease-out;
        }

        @keyframes kartGiris {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Durum renkleri */
        .mutfak-kart--onaylandi,
        .mutfak-kart--beklemede {
            background: #2a2518;
            border: 2px solid #f59e0b;
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.15);
        }

        .mutfak-kart--hazirlaniyor {
            background: #2a1f18;
            border: 2px solid #f97316;
            box-shadow: 0 0 16px rgba(249, 115, 22, 0.15);
        }

        .mutfak-kart--hazir {
            background: #182a1c;
            border: 2px solid #22c55e;
            box-shadow: 0 0 16px rgba(34, 197, 94, 0.2);
        }

        /* ============================================
         * KART UST - MASA NO + ZAMAN
         * ============================================ */
        .mutfak-kart__ust {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px 8px;
        }

        .mutfak-kart__masa {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .mutfak-kart--onaylandi .mutfak-kart__masa,
        .mutfak-kart--beklemede .mutfak-kart__masa {
            color: #fbbf24;
        }

        .mutfak-kart--hazirlaniyor .mutfak-kart__masa {
            color: #fb923c;
        }

        .mutfak-kart--hazir .mutfak-kart__masa {
            color: #4ade80;
        }

        .mutfak-kart__siparis-no {
            font-size: 0.8rem;
            font-weight: 500;
            color: #888;
        }

        .mutfak-kart__zaman-blok {
            text-align: right;
        }

        .mutfak-kart__saat {
            font-size: 0.8rem;
            color: #888;
        }

        .mutfak-kart__sayac {
            font-size: 1.3rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }

        .mutfak-kart__sayac--yesil {
            color: #4ade80;
            background: rgba(34, 197, 94, 0.15);
        }

        .mutfak-kart__sayac--sari {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.15);
        }

        .mutfak-kart__sayac--kirmizi {
            color: #f87171;
            background: rgba(248, 113, 113, 0.2);
            animation: sayacYanipSon 1s ease-in-out infinite;
        }

        @keyframes sayacYanipSon {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ============================================
         * KART ICERIK - URUN LISTESI
         * ============================================ */
        .mutfak-kart__icerik {
            padding: 4px 16px 8px;
            flex: 1;
        }

        .mutfak-kart__durum-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 10px;
            border-radius: 4px;
            margin-bottom: 8px;
        }

        .mutfak-kart--onaylandi .mutfak-kart__durum-badge,
        .mutfak-kart--beklemede .mutfak-kart__durum-badge {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .mutfak-kart--hazirlaniyor .mutfak-kart__durum-badge {
            background: rgba(249, 115, 22, 0.2);
            color: #fb923c;
        }

        .mutfak-kart--hazir .mutfak-kart__durum-badge {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .mutfak-kart__urunler {
            list-style: none;
        }

        .mutfak-kart__urun {
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .mutfak-kart__urun:last-child {
            border-bottom: none;
        }

        .mutfak-kart__urun-satir {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-size: 1.05rem;
        }

        .mutfak-kart__urun-adet {
            font-weight: 700;
            color: #ff6b35;
            min-width: 28px;
        }

        .mutfak-kart__urun-adi {
            font-weight: 500;
        }

        .mutfak-kart__urun-porsiyon {
            font-size: 0.8rem;
            color: #888;
        }

        .mutfak-kart__urun-not {
            margin-left: 34px;
            font-size: 0.82rem;
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            border-left: 3px solid #fbbf24;
            margin-top: 2px;
        }

        .mutfak-kart__siparis-notu {
            margin-top: 8px;
            padding: 6px 10px;
            background: rgba(99, 102, 241, 0.12);
            border-left: 3px solid #818cf8;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #a5b4fc;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .mutfak-kart__siparis-notu svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ============================================
         * KART ALT - AKSIYON BUTONU
         * ============================================ */
        .mutfak-kart__alt {
            padding: 8px 16px 12px;
        }

        .mutfak-kart__aksiyon-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
        }

        .mutfak-kart__aksiyon-btn:active {
            transform: scale(0.97);
        }

        .mutfak-kart__aksiyon-btn svg {
            width: 22px;
            height: 22px;
        }

        .mutfak-kart__aksiyon-btn--hazirla {
            background: #f97316;
            color: #fff;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .mutfak-kart__aksiyon-btn--hazirla:hover {
            background: #ea580c;
            box-shadow: 0 4px 16px rgba(249, 115, 22, 0.5);
        }

        .mutfak-kart__aksiyon-btn--hazir {
            background: #22c55e;
            color: #fff;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .mutfak-kart__aksiyon-btn--hazir:hover {
            background: #16a34a;
            box-shadow: 0 4px 16px rgba(34, 197, 94, 0.5);
        }

        .mutfak-kart__aksiyon-btn--teslim {
            background: #3b82f6;
            color: #fff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .mutfak-kart__aksiyon-btn--teslim:hover {
            background: #2563eb;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.5);
        }

        .mutfak-kart__aksiyon-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ============================================
         * BOS DURUM
         * ============================================ */
        .mutfak-bos {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #666;
        }

        .mutfak-bos svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .mutfak-bos h2 {
            font-size: 1.6rem;
            color: #888;
            margin-bottom: 8px;
        }

        .mutfak-bos p {
            font-size: 1rem;
            color: #555;
        }

        /* ============================================
         * FLASH MESAJLAR
         * ============================================ */
        .mutfak-flash {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: flashGiris 0.3s ease-out;
            max-width: 400px;
        }

        .mutfak-flash--success {
            background: #166534;
            color: #4ade80;
            border: 1px solid #22c55e;
        }

        .mutfak-flash--error {
            background: #7f1d1d;
            color: #fca5a5;
            border: 1px solid #ef4444;
        }

        @keyframes flashGiris {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* ============================================
         * TOAST BILDIRIMI
         * ============================================ */
        .mutfak-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            z-index: 9999;
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            pointer-events: none;
            max-width: calc(100vw - 32px);
            text-align: center;
        }

        .mutfak-toast--error {
            background: #991b1b;
            border: 1px solid #ef4444;
        }

        .mutfak-toast--success {
            background: #166534;
            border: 1px solid #22c55e;
        }

        .mutfak-toast.active {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        /* ============================================
         * YENILEME GOSTERGESI
         * ============================================ */
        .mutfak-yenileme {
            position: fixed;
            bottom: 16px;
            right: 16px;
            background: rgba(26, 26, 46, 0.9);
            border: 1px solid #3a3a5a;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 0.8rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 100;
        }

        .mutfak-yenileme.active {
            opacity: 1;
        }

        .mutfak-yenileme__spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #3a3a5a;
            border-top-color: #ff6b35;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Fullscreen durumunda body scroll */
        body:fullscreen,
        body:-webkit-full-screen,
        body:-moz-full-screen {
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="mutfak-wrapper">
    <!-- UST BAR -->
    <div class="mutfak-topbar">
        <div class="mutfak-topbar__sol">
            <div class="mutfak-topbar__baslik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 006 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/>
                    <path d="M9 18h6"/>
                    <path d="M10 22h4"/>
                </svg>
                Mutfak Ekrani
            </div>
            <div class="mutfak-topbar__saat" id="saatGosterge">--:--:--</div>
        </div>
        <div class="mutfak-topbar__sag">
            <div class="mutfak-topbar__sayac" role="status" aria-live="polite">
                Aktif: <strong id="aktifSayac"><?= count($tumSiparisler) ?></strong>
            </div>
            <div class="mutfak-topbar__sayac mutfak-topbar__sayac--alt" role="status" aria-live="polite"
                 title="Beklemede / Hazirlaniyor / Hazir">
                <span class="mutfak-sayac-pill mutfak-sayac-pill--sari">
                    B:<strong id="sayacBeklemede"><?= (int)($sayacAltKirilim['beklemede'] + $sayacAltKirilim['onaylandi']) ?></strong>
                </span>
                <span class="mutfak-sayac-pill mutfak-sayac-pill--turuncu">
                    H:<strong id="sayacHazirlaniyor"><?= (int)$sayacAltKirilim['hazirlaniyor'] ?></strong>
                </span>
                <span class="mutfak-sayac-pill mutfak-sayac-pill--yesil">
                    ✓:<strong id="sayacHazir"><?= (int)$sayacAltKirilim['hazir'] ?></strong>
                </span>
            </div>
            <button type="button" class="mutfak-btn mutfak-btn--ses-acik" id="sesToggle"
                    aria-label="Ses bildirimlerini kapat" aria-pressed="true" title="Ses: Acik">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="sesIkon">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                    <path d="M19.07 4.93a10 10 0 010 14.14" id="sesYol1"/>
                    <path d="M15.54 8.46a5 5 0 010 7.07" id="sesYol2"/>
                </svg>
                <span id="sesLabel">Ses</span>
            </button>
            <button type="button" class="mutfak-btn" id="tamEkranBtn"
                    aria-label="Tam ekran modu" title="Tam Ekran">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 3 21 3 21 9"/>
                    <polyline points="9 21 3 21 3 15"/>
                    <line x1="21" y1="3" x2="14" y2="10"/>
                    <line x1="3" y1="21" x2="10" y2="14"/>
                </svg>
            </button>
            <a href="masa-siparisleri.php" class="mutfak-btn" title="Admin Panele Don">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Admin
            </a>
        </div>
    </div>

    <!-- KATEGORI FILTRE BAR -->
    <?php if (!empty($kategoriler)): ?>
    <div class="mutfak-filtre-bar" id="mutfakFiltreBar" role="tablist" aria-label="Kategori filtresi">
        <span class="mutfak-filtre-etiket">Kategori:</span>
        <button type="button" class="mutfak-filtre-btn aktif" data-kategori-id="tumu" role="tab" aria-selected="true">
            Tümü
        </button>
        <?php foreach ($kategoriler as $kat): ?>
        <button type="button" class="mutfak-filtre-btn" data-kategori-id="<?= (int)$kat['id'] ?>" role="tab" aria-selected="false">
            <?= e($kat['isim']) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FLASH MESAJ -->
    <?php
    $flash = getFlash();
    if ($flash):
    ?>
    <div class="mutfak-flash mutfak-flash--<?= e($flash['type']) ?>" id="flashMesaj">
        <?= e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- SIPARIS ICERIK ALANI -->
    <div class="mutfak-icerik" id="mutfakIcerik">
        <?php if (empty($tumSiparisler)): ?>
            <div class="mutfak-bos" id="bosEkran">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <h2>Bekleyen siparis yok</h2>
                <p>Yeni siparisler otomatik olarak burada gorunecek.</p>
            </div>
        <?php else: ?>
            <div class="mutfak-grid" id="mutfakGrid">
                <?php foreach ($tumSiparisler as $siparis):
                    $durum = $siparis['durum'] ?? 'beklemede';
                    $masaNo = (int)($siparis['masa_no'] ?? 0);
                    $siparisId = (int)$siparis['id'];
                    $kalemler = $siparis['kalemler'] ?? [];
                    $siparisNotu = $siparis['siparis_notu'] ?? '';
                    $siparisZamani = $siparis['siparis_zamani'] ?? date('Y-m-d H:i:s');
                    $saatStr = date('H:i', strtotime($siparisZamani));

                    // Durum etiketleri
                    $durumLabel = $durumEtiketleri[$durum] ?? $durum;

                    // Sonraki durum (domain kurallarina uygun gecisler)
                    $sonrakiDurum = '';
                    $butonLabel = '';
                    $butonClass = '';
                    if ($durum === 'beklemede') {
                        $sonrakiDurum = 'onaylandi';
                        $butonLabel = 'ONAYLA';
                        $butonClass = 'mutfak-kart__aksiyon-btn--hazirla';
                    } elseif ($durum === 'onaylandi') {
                        $sonrakiDurum = 'hazirlaniyor';
                        $butonLabel = 'HAZIRLA';
                        $butonClass = 'mutfak-kart__aksiyon-btn--hazirla';
                    } elseif ($durum === 'hazirlaniyor') {
                        $sonrakiDurum = 'hazir';
                        $butonLabel = 'HAZIR';
                        $butonClass = 'mutfak-kart__aksiyon-btn--hazir';
                    } elseif ($durum === 'hazir') {
                        $sonrakiDurum = 'teslim_edildi';
                        $butonLabel = 'TESLIM EDILDI';
                        $butonClass = 'mutfak-kart__aksiyon-btn--teslim';
                    }
                ?>
                <?php
                $kategoriIdler = array_values(array_unique(array_filter(array_map(function ($k) {
                    return isset($k['kategori_id']) && $k['kategori_id'] !== null ? (int)$k['kategori_id'] : null;
                }, $kalemler), function ($v) { return $v !== null; })));
                ?>
                <div class="mutfak-kart mutfak-kart--<?= e($durum) ?>"
                     data-siparis-id="<?= $siparisId ?>"
                     data-durum="<?= e($durum) ?>"
                     data-zaman="<?= e($siparisZamani) ?>"
                     data-kategori-ids="<?= e(implode(',', $kategoriIdler)) ?>">

                    <!-- KART UST -->
                    <div class="mutfak-kart__ust">
                        <div class="mutfak-kart__masa">
                            Masa <?= $masaNo ?>
                            <span class="mutfak-kart__siparis-no">#<?= $siparisId ?></span>
                        </div>
                        <div class="mutfak-kart__zaman-blok">
                            <div class="mutfak-kart__saat"><?= e($saatStr) ?></div>
                            <div class="mutfak-kart__sayac" data-siparis-zamani="<?= e($siparisZamani) ?>">
                                --:--
                            </div>
                        </div>
                    </div>

                    <!-- KART ICERIK -->
                    <div class="mutfak-kart__icerik">
                        <span class="mutfak-kart__durum-badge"><?= e($durumLabel) ?></span>

                        <ul class="mutfak-kart__urunler">
                            <?php foreach ($kalemler as $kalem):
                                $urunAdi = $kalem['urun_adi'] ?? 'Bilinmeyen Urun';
                                $adet = (int)($kalem['adet'] ?? 1);
                                $porsiyon = $kalem['porsiyon'] ?? null;
                                $ozelNot = $kalem['ozel_not'] ?? '';
                            ?>
                            <li class="mutfak-kart__urun">
                                <div class="mutfak-kart__urun-satir">
                                    <span class="mutfak-kart__urun-adet"><?= $adet ?>x</span>
                                    <span class="mutfak-kart__urun-adi"><?= e($urunAdi) ?></span>
                                    <?php if ($porsiyon): ?>
                                        <span class="mutfak-kart__urun-porsiyon">(<?= e($porsiyon) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ozelNot)): ?>
                                <div class="mutfak-kart__urun-not"><?= e($ozelNot) ?></div>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (!empty($siparisNotu)): ?>
                        <div class="mutfak-kart__siparis-notu">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <?= e($siparisNotu) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- AKSIYON BUTONU -->
                    <?php if ($sonrakiDurum !== ''): ?>
                    <div class="mutfak-kart__alt">
                        <button type="button"
                                class="mutfak-kart__aksiyon-btn <?= $butonClass ?>"
                                data-siparis-id="<?= $siparisId ?>"
                                data-yeni-durum="<?= e($sonrakiDurum) ?>"
                                aria-label="Siparis #<?= $siparisId ?> - <?= e($butonLabel) ?>">
                            <?php if ($durum === 'beklemede'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <?php elseif ($durum === 'onaylandi'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 006 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/>
                                <path d="M9 18h6"/>
                            </svg>
                            <?php elseif ($durum === 'hazirlaniyor'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            <?php elseif ($durum === 'hazir'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <?php endif; ?>
                            <?= e($butonLabel) ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast Bildirimi -->
<div class="mutfak-toast" id="mutfakToast" role="alert" aria-live="polite"></div>

<!-- Yenileme Gostergesi -->
<div class="mutfak-yenileme" id="yenilemeGosterge">
    <div class="mutfak-yenileme__spinner"></div>
    Guncelleniyor...
</div>

<script nonce="<?= $nonce ?>">
(function() {
    'use strict';

    // ============================================
    // YAPILANDIRMA
    // ============================================
    var YENILEME_ARALIK = 10000; // 10 saniye
    var UYARI_ESIK_MS = 5 * 60 * 1000; // 5 dakika
    var csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? generateSecureCSRFToken()) ?>;
    var sesAcik = true;
    var aktifKategoriFiltresi = 'tumu'; // 'tumu' veya kategori_id (string)
    var oncekiSiparisSayisi = <?= count($tumSiparisler) ?>;
    var oncekiSiparisIdleri = <?= json_encode(array_map(function($s) { return (int)$s['id']; }, $tumSiparisler)) ?>;
    var yenilemeInterval = null;
    var sayacInterval = null;
    var audioContext = null;

    // ============================================
    // WEB AUDIO API - SES URETIMI
    // ============================================
    function getAudioContext() {
        if (!audioContext) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            } catch (err) {
                // Audio desteklenmiyor
            }
        }
        return audioContext;
    }

    /**
     * Kisa "ding" sesi - yeni siparis bildirimi
     */
    function dingSesi() {
        var ctx = getAudioContext();
        if (!ctx || !sesAcik) return;

        // Resume if suspended (Chrome autoplay policy)
        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);        // A5
        osc.frequency.setValueAtTime(1108.73, ctx.currentTime + 0.1); // C#6
        osc.frequency.setValueAtTime(1318.51, ctx.currentTime + 0.2); // E6

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.5);
    }

    /**
     * Uyari sesi - 5 dakikayi gecmis siparis
     */
    function uyariSesi() {
        var ctx = getAudioContext();
        if (!ctx || !sesAcik) return;

        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        // Cift bip sesi
        for (var i = 0; i < 2; i++) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.type = 'square';
            osc.frequency.setValueAtTime(600, ctx.currentTime + i * 0.3);

            gain.gain.setValueAtTime(0.2, ctx.currentTime + i * 0.3);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + i * 0.3 + 0.15);

            osc.start(ctx.currentTime + i * 0.3);
            osc.stop(ctx.currentTime + i * 0.3 + 0.15);
        }
    }

    // ============================================
    // SAAT GOSTERIMI
    // ============================================
    function saatGuncelle() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var el = document.getElementById('saatGosterge');
        if (el) el.textContent = h + ':' + m + ':' + s;
    }

    // ============================================
    // CANLI SAYAC
    // ============================================
    function sayaclariGuncelle() {
        var sayaclar = document.querySelectorAll('[data-siparis-zamani]');
        var simdi = Date.now();
        var uyariVar = false;

        for (var i = 0; i < sayaclar.length; i++) {
            var el = sayaclar[i];
            var zamaniStr = el.getAttribute('data-siparis-zamani');
            var zamani = new Date(zamaniStr.replace(' ', 'T')).getTime();
            var fark = simdi - zamani;

            if (isNaN(fark) || fark < 0) fark = 0;

            var toplamSaniye = Math.floor(fark / 1000);
            var dk = Math.floor(toplamSaniye / 60);
            var sn = toplamSaniye % 60;
            var gosterim = String(dk).padStart(2, '0') + ':' + String(sn).padStart(2, '0');
            el.textContent = gosterim;

            // Renk sinifi guncelle
            el.classList.remove('mutfak-kart__sayac--yesil', 'mutfak-kart__sayac--sari', 'mutfak-kart__sayac--kirmizi');

            if (dk >= 10) {
                el.classList.add('mutfak-kart__sayac--kirmizi');
            } else if (dk >= 5) {
                el.classList.add('mutfak-kart__sayac--sari');
            } else {
                el.classList.add('mutfak-kart__sayac--yesil');
            }

            // Uyari: 5 dk ustu hazirlaniyor-olmayan siparis
            var kart = el.closest('.mutfak-kart');
            if (kart) {
                var kartDurum = kart.getAttribute('data-durum');
                if (fark > UYARI_ESIK_MS && (kartDurum === 'beklemede' || kartDurum === 'onaylandi')) {
                    uyariVar = true;
                }
            }
        }

        return uyariVar;
    }

    // ============================================
    // SES TOGGLE
    // ============================================
    var sesToggleBtn = document.getElementById('sesToggle');
    if (sesToggleBtn) {
        sesToggleBtn.addEventListener('click', function() {
            sesAcik = !sesAcik;
            var label = document.getElementById('sesLabel');
            var yol1 = document.getElementById('sesYol1');
            var yol2 = document.getElementById('sesYol2');

            if (sesAcik) {
                this.classList.remove('mutfak-btn--ses-kapali');
                this.classList.add('mutfak-btn--ses-acik');
                this.title = 'Ses: Acik';
                this.setAttribute('aria-pressed', 'true');
                this.setAttribute('aria-label', 'Ses bildirimlerini kapat');
                if (label) label.textContent = 'Ses';
                if (yol1) yol1.style.display = '';
                if (yol2) yol2.style.display = '';
            } else {
                this.classList.remove('mutfak-btn--ses-acik');
                this.classList.add('mutfak-btn--ses-kapali');
                this.title = 'Ses: Kapali';
                this.setAttribute('aria-pressed', 'false');
                this.setAttribute('aria-label', 'Ses bildirimlerini ac');
                if (label) label.textContent = 'Ses';
                if (yol1) yol1.style.display = 'none';
                if (yol2) yol2.style.display = 'none';
            }

            // Audio context baslatmak icin ilk tiklama gerekli
            getAudioContext();
        });
    }

    // ============================================
    // TOAST BILDIRIMI
    // ============================================
    var mutfakToastEl = document.getElementById('mutfakToast');
    var mutfakToastTimer = null;

    function toastGoster(mesaj, tip) {
        if (!mutfakToastEl) return;
        mutfakToastEl.textContent = mesaj;
        mutfakToastEl.className = 'mutfak-toast active';
        if (tip === 'error') {
            mutfakToastEl.classList.add('mutfak-toast--error');
        } else if (tip === 'success') {
            mutfakToastEl.classList.add('mutfak-toast--success');
        }
        clearTimeout(mutfakToastTimer);
        mutfakToastTimer = setTimeout(function() {
            mutfakToastEl.classList.remove('active');
        }, 3500);
    }

    // ============================================
    // TAM EKRAN
    // ============================================
    var tamEkranBtn = document.getElementById('tamEkranBtn');
    if (tamEkranBtn) {
        tamEkranBtn.addEventListener('click', function() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                var el = document.documentElement;
                if (el.requestFullscreen) {
                    el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        });
    }

    // ============================================
    // DURUM GUNCELLEME (AJAX)
    // ============================================
    function durumGuncelle(siparisId, yeniDurum, btnEl) {
        if (btnEl) btnEl.disabled = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'mutfak.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // Karti animasyonlu kaldir veya guncelle
                        var kart = document.querySelector('[data-siparis-id="' + siparisId + '"]');
                        if (kart) {
                            if (yeniDurum === 'teslim_edildi') {
                                kart.style.transition = 'opacity 0.4s, transform 0.4s';
                                kart.style.opacity = '0';
                                kart.style.transform = 'scale(0.9)';
                                setTimeout(function() { kart.remove(); guncelleSayac(); }, 400);
                            } else {
                                // Hemen yenile
                                siparisleriYenile();
                            }
                        }
                    } else {
                        if (btnEl) btnEl.disabled = false;
                        toastGoster(res.error || 'Bir hata olustu.', 'error');
                    }
                } catch (e) {
                    if (btnEl) btnEl.disabled = false;
                    siparisleriYenile();
                }
            } else {
                if (btnEl) btnEl.disabled = false;
                toastGoster('Sunucu hatasi (' + xhr.status + ')', 'error');
            }
        };

        xhr.onerror = function() {
            if (btnEl) btnEl.disabled = false;
            toastGoster('Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
        };

        xhr.send(JSON.stringify({
            action: 'durum_guncelle',
            siparis_id: siparisId,
            yeni_durum: yeniDurum,
            csrf_token: csrfToken,
        }));
    }

    function guncelleSayac() {
        var kartlar = document.querySelectorAll('.mutfak-kart');
        var sayac = document.getElementById('aktifSayac');
        if (sayac) sayac.textContent = kartlar.length;

        // Alt kirilim sayaclari (beklemede+onaylandi, hazirlaniyor, hazir)
        var b = 0, h = 0, r = 0;
        for (var i = 0; i < kartlar.length; i++) {
            var d = kartlar[i].getAttribute('data-durum');
            if (d === 'beklemede' || d === 'onaylandi') b++;
            else if (d === 'hazirlaniyor') h++;
            else if (d === 'hazir') r++;
        }
        var sB = document.getElementById('sayacBeklemede');
        var sH = document.getElementById('sayacHazirlaniyor');
        var sR = document.getElementById('sayacHazir');
        if (sB) sB.textContent = b;
        if (sH) sH.textContent = h;
        if (sR) sR.textContent = r;

        if (kartlar.length === 0) {
            bosEkranGoster();
        }
    }

    // Kategori filtresi uygula
    function filtreUygula() {
        var kartlar = document.querySelectorAll('.mutfak-kart');
        for (var i = 0; i < kartlar.length; i++) {
            var kart = kartlar[i];
            if (aktifKategoriFiltresi === 'tumu') {
                kart.classList.remove('mutfak-kart--filtrelenmis');
                continue;
            }
            var idAttr = kart.getAttribute('data-kategori-ids') || '';
            var ids = idAttr.split(',').filter(function (x) { return x.length > 0; });
            if (ids.indexOf(aktifKategoriFiltresi) === -1) {
                kart.classList.add('mutfak-kart--filtrelenmis');
            } else {
                kart.classList.remove('mutfak-kart--filtrelenmis');
            }
        }
    }

    // Filtre bar event delegation
    var filtreBar = document.getElementById('mutfakFiltreBar');
    if (filtreBar) {
        filtreBar.addEventListener('click', function (e) {
            var btn = e.target.closest('.mutfak-filtre-btn');
            if (!btn) return;
            var kategoriId = btn.getAttribute('data-kategori-id');
            aktifKategoriFiltresi = kategoriId || 'tumu';

            var tumButonlar = filtreBar.querySelectorAll('.mutfak-filtre-btn');
            for (var i = 0; i < tumButonlar.length; i++) {
                var isActive = tumButonlar[i] === btn;
                tumButonlar[i].classList.toggle('aktif', isActive);
                tumButonlar[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
            }

            filtreUygula();
        });
    }

    function bosEkranGoster() {
        var icerik = document.getElementById('mutfakIcerik');
        if (!icerik) return;
        icerik.innerHTML = '<div class="mutfak-bos" id="bosEkran">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>' +
            '<polyline points="22 4 12 14.01 9 11.01"/>' +
            '</svg>' +
            '<h2>Bekleyen siparis yok</h2>' +
            '<p>Yeni siparisler otomatik olarak burada gorunecek.</p>' +
            '</div>';
    }

    // ============================================
    // SIPARIS KARTLARINI OLUSTUR (JS ILE)
    // ============================================
    function kartHTML(s) {
        var durum = s.durum || 'beklemede';
        var masaNo = parseInt(s.masa_no, 10) || 0;
        var siparisId = parseInt(s.id, 10);
        var kalemler = s.kalemler || [];
        var siparisNotu = s.siparis_notu || '';
        var siparisZamani = s.siparis_zamani || '';
        var saatStr = '';

        if (siparisZamani) {
            var d = new Date(siparisZamani.replace(' ', 'T'));
            if (!isNaN(d.getTime())) {
                saatStr = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
        }

        // Durum etiketleri
        var durumEtiketleri = {
            'beklemede': 'Beklemede',
            'onaylandi': 'Onaylandi',
            'hazirlaniyor': 'Hazirlaniyor',
            'hazir': 'Hazir',
            'teslim_edildi': 'Teslim Edildi',
            'iptal': 'Iptal',
        };
        var durumLabel = durumEtiketleri[durum] || durum;

        // Sonraki durum
        var sonrakiDurum = '';
        var butonLabel = '';
        var butonClass = '';
        var butonSvg = '';

        if (durum === 'beklemede') {
            sonrakiDurum = 'onaylandi';
            butonLabel = 'ONAYLA';
            butonClass = 'mutfak-kart__aksiyon-btn--hazirla';
            butonSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
        } else if (durum === 'onaylandi') {
            sonrakiDurum = 'hazirlaniyor';
            butonLabel = 'HAZIRLA';
            butonClass = 'mutfak-kart__aksiyon-btn--hazirla';
            butonSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 006 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/></svg>';
        } else if (durum === 'hazirlaniyor') {
            sonrakiDurum = 'hazir';
            butonLabel = 'HAZIR';
            butonClass = 'mutfak-kart__aksiyon-btn--hazir';
            butonSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
        } else if (durum === 'hazir') {
            sonrakiDurum = 'teslim_edildi';
            butonLabel = 'TESLIM EDILDI';
            butonClass = 'mutfak-kart__aksiyon-btn--teslim';
            butonSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
        }

        // Kategori ID'leri topla (filtre icin)
        var katIdler = [];
        for (var ki = 0; ki < kalemler.length; ki++) {
            var kid = kalemler[ki].kategori_id;
            if (kid !== undefined && kid !== null && katIdler.indexOf(kid) === -1) {
                katIdler.push(kid);
            }
        }

        var html = '<div class="mutfak-kart mutfak-kart--' + esc(durum) + '"'
            + ' data-siparis-id="' + siparisId + '"'
            + ' data-durum="' + esc(durum) + '"'
            + ' data-zaman="' + esc(siparisZamani) + '"'
            + ' data-kategori-ids="' + esc(katIdler.join(',')) + '">';

        // Kart ust
        html += '<div class="mutfak-kart__ust">';
        html += '<div class="mutfak-kart__masa">Masa ' + masaNo
            + ' <span class="mutfak-kart__siparis-no">#' + siparisId + '</span></div>';
        html += '<div class="mutfak-kart__zaman-blok">';
        html += '<div class="mutfak-kart__saat">' + esc(saatStr) + '</div>';
        html += '<div class="mutfak-kart__sayac" data-siparis-zamani="' + esc(siparisZamani) + '">--:--</div>';
        html += '</div></div>';

        // Kart icerik
        html += '<div class="mutfak-kart__icerik">';
        html += '<span class="mutfak-kart__durum-badge">' + esc(durumLabel) + '</span>';
        html += '<ul class="mutfak-kart__urunler">';

        for (var i = 0; i < kalemler.length; i++) {
            var k = kalemler[i];
            var urunAdi = k.urun_adi || 'Bilinmeyen Urun';
            var adet = parseInt(k.adet, 10) || 1;
            var porsiyon = k.porsiyon || '';
            var ozelNot = k.ozel_not || '';

            html += '<li class="mutfak-kart__urun">';
            html += '<div class="mutfak-kart__urun-satir">';
            html += '<span class="mutfak-kart__urun-adet">' + adet + 'x</span>';
            html += '<span class="mutfak-kart__urun-adi">' + esc(urunAdi) + '</span>';
            if (porsiyon) {
                html += '<span class="mutfak-kart__urun-porsiyon">(' + esc(porsiyon) + ')</span>';
            }
            html += '</div>';
            if (ozelNot) {
                html += '<div class="mutfak-kart__urun-not">' + esc(ozelNot) + '</div>';
            }
            html += '</li>';
        }

        html += '</ul>';

        if (siparisNotu) {
            html += '<div class="mutfak-kart__siparis-notu">';
            html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            html += '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>';
            html += '<line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            html += esc(siparisNotu);
            html += '</div>';
        }

        html += '</div>';

        // Aksiyon butonu
        if (sonrakiDurum) {
            html += '<div class="mutfak-kart__alt">';
            html += '<button type="button" class="mutfak-kart__aksiyon-btn ' + butonClass + '"'
                + ' data-siparis-id="' + siparisId + '"'
                + ' data-yeni-durum="' + esc(sonrakiDurum) + '"'
                + ' aria-label="Siparis #' + siparisId + ' - ' + esc(butonLabel) + '">';
            html += butonSvg + ' ' + esc(butonLabel);
            html += '</button></div>';
        }

        html += '</div>';
        return html;
    }

    /**
     * HTML entity escape
     */
    function esc(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // ============================================
    // SIPARISLERI YENILE (AJAX)
    // ============================================
    function siparisleriYenile() {
        var toast = document.getElementById('yenilemeGosterge');
        if (toast) toast.classList.add('active');

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'mutfak.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function() {
            if (toast) toast.classList.remove('active');

            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        // CSRF token guncelle
                        if (res.csrf_token) {
                            csrfToken = res.csrf_token;
                        }

                        var siparisler = res.siparisler || [];
                        var yeniIdler = siparisler.map(function(s) { return parseInt(s.id, 10); });

                        // Yeni siparis kontrolu
                        var yeniSiparis = false;
                        for (var i = 0; i < yeniIdler.length; i++) {
                            if (oncekiSiparisIdleri.indexOf(yeniIdler[i]) === -1) {
                                yeniSiparis = true;
                                break;
                            }
                        }

                        if (yeniSiparis) {
                            dingSesi();
                        }

                        oncekiSiparisIdleri = yeniIdler;
                        oncekiSiparisSayisi = siparisler.length;

                        // Sayac guncelle
                        var sayac = document.getElementById('aktifSayac');
                        if (sayac) sayac.textContent = siparisler.length;

                        // Grid guncelle
                        var icerik = document.getElementById('mutfakIcerik');
                        if (!icerik) return;

                        if (siparisler.length === 0) {
                            bosEkranGoster();
                        } else {
                            var gridHtml = '<div class="mutfak-grid" id="mutfakGrid">';
                            for (var j = 0; j < siparisler.length; j++) {
                                gridHtml += kartHTML(siparisler[j]);
                            }
                            gridHtml += '</div>';
                            icerik.innerHTML = gridHtml;
                        }

                        // Sayaclari hemen guncelle
                        sayaclariGuncelle();
                        guncelleSayac();
                        filtreUygula();
                    }
                } catch (e) {
                    // JSON parse hatasi — sessiz gec
                }
            }
        };

        xhr.onerror = function() {
            if (toast) toast.classList.remove('active');
        };

        xhr.send();
    }

    // Event delegation: tum tiklamalari tek yerden yakala
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.mutfak-kart__aksiyon-btn');
        if (!btn) return;

        e.preventDefault();
        var siparisId = parseInt(btn.getAttribute('data-siparis-id'), 10);
        var yeniDurum = btn.getAttribute('data-yeni-durum');

        if (siparisId && yeniDurum) {
            durumGuncelle(siparisId, yeniDurum, btn);
        }
    });

    // ============================================
    // UYARI KONTROLU (her 30 saniyede)
    // ============================================
    var sonUyariZamani = 0;

    function uyariKontrol() {
        var uyariVar = sayaclariGuncelle();
        var simdi = Date.now();

        // 60 saniyede bir uyari sesi cal (cok sik olmasin)
        if (uyariVar && (simdi - sonUyariZamani > 60000)) {
            uyariSesi();
            sonUyariZamani = simdi;
        }
    }

    // ============================================
    // VISIBILITY API - Tab gizliyken polling durdur
    // ============================================
    function pollingBaslat() {
        if (yenilemeInterval) clearInterval(yenilemeInterval);
        yenilemeInterval = setInterval(siparisleriYenile, YENILEME_ARALIK);
    }

    function pollingDurdur() {
        if (yenilemeInterval) {
            clearInterval(yenilemeInterval);
            yenilemeInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            pollingDurdur();
        } else {
            // Sayfa tekrar gorunur oldugunda hemen yenile
            siparisleriYenile();
            pollingBaslat();
        }
    });

    // ============================================
    // FLASH MESAJ OTOMATIK KAPATMA
    // ============================================
    var flashEl = document.getElementById('flashMesaj');
    if (flashEl) {
        setTimeout(function() {
            flashEl.style.transition = 'opacity 0.5s';
            flashEl.style.opacity = '0';
            setTimeout(function() { flashEl.remove(); }, 500);
        }, 4000);
    }

    // ============================================
    // BASLAT
    // ============================================
    // Saat
    saatGuncelle();
    setInterval(saatGuncelle, 1000);

    // Sayaclar (her saniye)
    sayaclariGuncelle();
    guncelleSayac();
    sayacInterval = setInterval(uyariKontrol, 1000);

    // Otomatik yenileme
    pollingBaslat();

})();
</script>
</body>
</html>
