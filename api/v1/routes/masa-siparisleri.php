<?php
/**
 * Masa Siparisleri API Routes (Admin Tarafi)
 *
 * QR Menu sistemi siparis yonetimi endpoint'leri.
 * JWT auth zorunlu — admin paneli icin.
 *
 * Endpoint'ler:
 *   GET  /api/v1/masa-siparisleri              — Aktif siparisleri listele
 *   GET  /api/v1/masa-siparisleri/mutfak       — Mutfak ekrani feed'i
 *   GET  /api/v1/masa-siparisleri/ozet         — Istatistik ozeti
 *   GET  /api/v1/masa-siparisleri/{id}         — Siparis detayi
 *   PUT  /api/v1/masa-siparisleri/{id}/durum   — Durum guncelle
 *   POST /api/v1/masa-siparisleri/{id}/iptal   — Siparis iptal et
 *
 * @package Pastane\API\v1
 * @since 1.0.0
 */

use Pastane\Router\Router;
use Pastane\Validators\MasaSiparisValidator;

$router = Router::getInstance();
$siparisService = masa_siparis_service();

// ============================================
// ADMIN ENDPOINTS (JWT Required)
// ============================================

/**
 * GET /api/v1/masa-siparisleri
 * Aktif siparisleri listele (filtre: durum, masa_id)
 *
 * Query params:
 *   durum   — Siparis durumuna gore filtrele (opsiyonel)
 *   masa_id — Masa ID'ye gore filtrele (opsiyonel)
 */
$router->get('/api/v1/masa-siparisleri', function () use ($siparisService) {
    JWT::requireAuth();

    $siparisler = $siparisService->getAktifSiparisler();

    // Filtre: durum
    $durumFiltre = isset($_GET['durum']) ? trim((string)$_GET['durum']) : null;
    if ($durumFiltre !== null && $durumFiltre !== '') {
        $gecerliDurumlar = MasaSiparisValidator::GECERLI_DURUMLAR;
        if (!in_array($durumFiltre, $gecerliDurumlar, true)) {
            json_error(
                'Gecersiz durum filtresi. Gecerli degerler: ' . implode(', ', $gecerliDurumlar),
                422
            );
        }
        $siparisler = array_values(array_filter($siparisler, function ($s) use ($durumFiltre) {
            return ($s['durum'] ?? '') === $durumFiltre;
        }));
    }

    // Filtre: masa_id
    $masaIdFiltre = isset($_GET['masa_id']) ? (int)$_GET['masa_id'] : null;
    if ($masaIdFiltre !== null && $masaIdFiltre > 0) {
        $siparisler = array_values(array_filter($siparisler, function ($s) use ($masaIdFiltre) {
            return (int)($s['masa_id'] ?? 0) === $masaIdFiltre;
        }));
    }

    json_success([
        'siparisler' => $siparisler,
        'toplam'     => count($siparisler),
    ]);
});

/**
 * GET /api/v1/masa-siparisleri/mutfak
 * Mutfak ekrani feed'i — sadece hazirlanacak siparisler
 *
 * Onaylandi ve hazirlaniyor durumundaki siparisler,
 * kalemleriyle birlikte doner.
 */
$router->get('/api/v1/masa-siparisleri/mutfak', function () use ($siparisService) {
    JWT::requireAuth();

    $siparisler = $siparisService->getMutfakSiparisleri();

    json_success([
        'siparisler' => $siparisler,
        'toplam'     => count($siparisler),
    ]);
});

/**
 * GET /api/v1/masa-siparisleri/ozet
 * Istatistik ozeti — aktif, bekleyen, hazirlanan sayilari
 */
$router->get('/api/v1/masa-siparisleri/ozet', function () use ($siparisService) {
    JWT::requireAuth();

    $istatistikler = $siparisService->getBugununIstatistikleri();

    // toplam_aktif hesapla (beklemede + onaylandi + hazirlaniyor + hazir)
    $istatistikler['toplam_aktif'] = ($istatistikler['beklemede'] ?? 0)
        + ($istatistikler['onaylandi'] ?? 0)
        + ($istatistikler['hazirlaniyor'] ?? 0)
        + ($istatistikler['hazir'] ?? 0);

    json_success([
        'ozet' => $istatistikler,
    ]);
});

/**
 * GET /api/v1/masa-siparisleri/{id}
 * Siparis detayi — kalemler dahil
 */
$router->get('/api/v1/masa-siparisleri/{id}', function ($params) use ($siparisService) {
    JWT::requireAuth();

    $id = (int)($params['id'] ?? 0);
    if ($id < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    try {
        $siparis = $siparisService->getSiparisDetay($id);
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error('Siparis bulunamadi.', 404);
    }

    json_success([
        'siparis' => $siparis,
    ]);
});

/**
 * PUT /api/v1/masa-siparisleri/{id}/durum
 * Siparis durumunu guncelle
 *
 * Body: { "yeni_durum": "onaylandi" }
 */
$router->put('/api/v1/masa-siparisleri/{id}/durum', function ($params) use ($siparisService) {
    $payload = JWT::requireAuth();

    $id = (int)($params['id'] ?? 0);
    if ($id < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    // Validator ile dogrula
    $validator = new MasaSiparisValidator('durum_guncelle');
    $cleaned = $validator->validate($data);

    $yeniDurum = $cleaned['yeni_durum'];

    // Service uzerinden durum guncelle (durum gecis kurallari service'de)
    $sonuc = $siparisService->siparisDurumGuncelle($id, $yeniDurum);

    logger('Siparis durumu guncellendi', [
        'siparis_id' => $id,
        'yeni_durum' => $yeniDurum,
        'admin_id'   => $payload['user_id'] ?? null,
    ]);

    json_success([
        'siparis' => $sonuc['siparis'],
    ], $sonuc['mesaj']);
});

/**
 * POST /api/v1/masa-siparisleri/{id}/iptal
 * Siparisi iptal et
 *
 * Sadece beklemede veya onaylandi durumundaki siparisler iptal edilebilir.
 */
$router->post('/api/v1/masa-siparisleri/{id}/iptal', function ($params) use ($siparisService) {
    $payload = JWT::requireAuth();

    $id = (int)($params['id'] ?? 0);
    if ($id < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    $sonuc = $siparisService->siparisIptal($id);

    logger('Siparis iptal edildi', [
        'siparis_id' => $id,
        'admin_id'   => $payload['user_id'] ?? null,
    ]);

    json_success([
        'siparis' => $sonuc['siparis'],
    ], $sonuc['mesaj']);
});
