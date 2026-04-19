<?php
/**
 * Masalar API Routes
 *
 * QR Menu sistemi masa yonetimi endpoint'leri.
 * Service layer uzerinden veri erisimi.
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;

$router = Router::getInstance();
$masaService = masa_service();
$qrKodService = qr_kod_service();

// ============================================
// ADMIN ENDPOINTS (JWT Required)
// ============================================

/**
 * GET /api/v1/masalar
 * Tum masalari listele (Admin)
 */
$router->get('/api/v1/masalar', function() use ($masaService) {
    JWT::requireAuth();

    $masalar = $masaService->getMasalar();

    json_success([
        'masalar' => $masalar,
        'toplam'  => count($masalar),
    ]);
});

/**
 * POST /api/v1/masalar
 * Yeni masa ekle (Admin)
 */
$router->post('/api/v1/masalar', function() use ($masaService) {
    JWT::requireAuth();

    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    // Zorunlu alan kontrolleri
    if (!isset($data['masa_no']) || !is_numeric($data['masa_no'])) {
        json_error('Masa numarasi zorunludur ve sayisal olmalidir.', 422);
    }
    if (!isset($data['kapasite']) || !is_numeric($data['kapasite'])) {
        json_error('Kapasite zorunludur ve sayisal olmalidir.', 422);
    }

    $masaNo   = (int)$data['masa_no'];
    $kapasite = (int)$data['kapasite'];
    $konum    = isset($data['konum']) ? trim((string)$data['konum']) : null;

    // Masa numarasi pozitif olmali
    if ($masaNo < 1) {
        json_error('Masa numarasi pozitif bir sayi olmalidir.', 422);
    }

    // Kapasite aralik kontrolu
    if ($kapasite < 1 || $kapasite > 50) {
        json_error('Kapasite 1 ile 50 arasinda olmalidir.', 422);
    }

    // Konum uzunluk kontrolu (DB: VARCHAR(50))
    if ($konum !== null && mb_strlen($konum) > 50) {
        json_error('Konum alani en fazla 50 karakter olmalidir.', 422);
    }

    $masa = $masaService->masaEkle($masaNo, $kapasite, $konum);

    json_response([
        'success' => true,
        'message' => 'Masa basariyla olusturuldu.',
        'data'    => ['masa' => $masa],
    ], 201);
});

/**
 * PUT /api/v1/masalar/{id}
 * Masa bilgilerini guncelle (Admin)
 */
$router->put('/api/v1/masalar/{id}', function($params) use ($masaService) {
    JWT::requireAuth();

    $id = (int)$params['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    // Guncellenebilir alanlar whitelist
    $izinliAlanlar = ['masa_no', 'kapasite', 'konum'];
    $guncelData = [];

    foreach ($izinliAlanlar as $alan) {
        if (array_key_exists($alan, $data)) {
            $guncelData[$alan] = $data[$alan];
        }
    }

    if (empty($guncelData)) {
        json_error('Guncellenecek alan belirtilmedi.', 422);
    }

    // Tip donusumleri
    if (isset($guncelData['masa_no'])) {
        if (!is_numeric($guncelData['masa_no'])) {
            json_error('Masa numarasi sayisal olmalidir.', 422);
        }
        $guncelData['masa_no'] = (int)$guncelData['masa_no'];
        if ($guncelData['masa_no'] < 1) {
            json_error('Masa numarasi pozitif bir sayi olmalidir.', 422);
        }
    }

    if (isset($guncelData['kapasite'])) {
        if (!is_numeric($guncelData['kapasite'])) {
            json_error('Kapasite sayisal olmalidir.', 422);
        }
        $guncelData['kapasite'] = (int)$guncelData['kapasite'];
        if ($guncelData['kapasite'] < 1 || $guncelData['kapasite'] > 50) {
            json_error('Kapasite 1 ile 50 arasinda olmalidir.', 422);
        }
    }

    if (isset($guncelData['konum'])) {
        $guncelData['konum'] = trim((string)$guncelData['konum']);
        if (mb_strlen($guncelData['konum']) > 50) {
            json_error('Konum alani en fazla 50 karakter olmalidir.', 422);
        }
    }

    $masa = $masaService->masaGuncelle($id, $guncelData);

    json_success(['masa' => $masa], 'Masa basariyla guncellendi.');
});

/**
 * DELETE /api/v1/masalar/{id}
 * Masa sil (Admin)
 */
$router->delete('/api/v1/masalar/{id}', function($params) use ($masaService) {
    $payload = JWT::requireAuth();

    $id = (int)$params['id'];

    $masaService->masaSil($id);

    logger('Masa silindi', [
        'masa_id'  => $id,
        'admin_id' => $payload['user_id'] ?? null,
    ]);

    json_success(null, 'Masa basariyla silindi.');
});

/**
 * POST /api/v1/masalar/{id}/aktif
 * Masayi aktif hale getir ve yeni oturum olustur (Admin)
 */
$router->post('/api/v1/masalar/{id}/aktif', function($params) use ($masaService) {
    $payload = JWT::requireAuth();

    $id = (int)$params['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    $musteriSayisi = 1;
    if ($data !== null && isset($data['musteri_sayisi']) && is_numeric($data['musteri_sayisi'])) {
        $musteriSayisi = (int)$data['musteri_sayisi'];
    }

    $sonuc = $masaService->masaAktifEt($id, $musteriSayisi);

    logger('Masa aktif edildi', [
        'masa_id'        => $id,
        'musteri_sayisi' => $musteriSayisi,
        'admin_id'       => $payload['user_id'] ?? null,
    ]);

    json_success($sonuc, 'Masa basariyla aktif edildi.');
});

/**
 * POST /api/v1/masalar/{id}/kapat
 * Masayi kapat ve oturumu bitir (Admin)
 */
$router->post('/api/v1/masalar/{id}/kapat', function($params) use ($masaService) {
    $payload = JWT::requireAuth();

    $id = (int)$params['id'];

    $sonuc = $masaService->masaKapat($id);

    logger('Masa kapatildi', [
        'masa_id'  => $id,
        'admin_id' => $payload['user_id'] ?? null,
    ]);

    json_success($sonuc, 'Masa basariyla kapatildi.');
});

/**
 * GET /api/v1/masalar/{id}/qr
 * QR kod bilgilerini getir (Admin) — JSON kontratı
 */
$router->get('/api/v1/masalar/{id}/qr', function($params) use ($masaService, $qrKodService) {
    JWT::requireAuth();

    $id = (int)$params['id'];

    // Masa var mi kontrol et — findOrFail 404 firlatir
    $masa = $masaService->find($id);
    if (!$masa) {
        json_error('Masa bulunamadi.', 404);
    }

    $boyut = isset($_GET['boyut']) ? max(100, min(1000, (int)$_GET['boyut'])) : 300;

    $qrBilgi = $qrKodService->qrKodUret($masa['qr_token'], $boyut);

    json_success([
        'masa'    => $masa,
        'qr_kod'  => $qrBilgi,
    ]);
});

/**
 * GET /api/v1/masalar/{id}/qr.png
 * QR kodu ham PNG olarak doner (Admin)
 *
 * Mevcut /qr endpoint'ini bozmaz — sadece binary indirme icin ek kanal.
 * Query: ?boyut=100..1000 (varsayilan 300)
 */
$router->get('/api/v1/masalar/{id}/qr.png', function($params) use ($masaService, $qrKodService) {
    JWT::requireAuth();

    $id = (int)$params['id'];

    $masa = $masaService->find($id);
    if (!$masa) {
        json_error('Masa bulunamadi.', 404);
    }

    $boyut = isset($_GET['boyut']) ? max(100, min(1000, (int)$_GET['boyut'])) : 300;

    try {
        $indir = $qrKodService->qrKodIndir($masa['qr_token'], $boyut);
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error($e->getMessage(), $e->getCode() ?: 500);
    }

    // Disposition: inline (tarayicida goster), attachment (indirmek istenirse ?dl=1)
    $disposition = (isset($_GET['dl']) && $_GET['dl'] === '1') ? 'attachment' : 'inline';

    header('Content-Type: ' . $indir['content_type']);
    header('Content-Length: ' . strlen($indir['data']));
    header('Content-Disposition: ' . $disposition . '; filename="' . $indir['dosya_adi'] . '"');
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');

    echo $indir['data'];
    exit;
});
