<?php
/**
 * Siparişler API Routes
 *
 * Sipariş CRUD ve istatistik endpoint'leri.
 * Service layer üzerinden veri erişimi tercih edilir.
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Validators\SiparisValidator;

$router = Router::getInstance();
$siparisService = siparis_service();

/**
 * GET /api/v1/siparisler
 * Siparişleri listele (Admin)
 */
$router->get('/api/v1/siparisler', function() use ($siparisService) {
    // JWT::requireAuth() artık HttpException fırlatıyor — null check gereksiz
    JWT::requireAuth();

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;

    // Parametreleri güvenli şekilde al ve doğrula
    $durum = $_GET['durum'] ?? null;
    $baslangic = $_GET['baslangic'] ?? null;
    $bitis = $_GET['bitis'] ?? null;

    // Durum whitelist kontrolü
    $gecerliDurumlar = ['beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal'];
    if ($durum !== null && !in_array($durum, $gecerliDurumlar, true)) {
        json_error('Geçersiz durum filtresi.', 422);
    }

    // Tarih format kontrolü
    $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
    if ($baslangic !== null && !preg_match($datePattern, $baslangic)) {
        json_error('Geçersiz başlangıç tarihi formatı (YYYY-MM-DD).', 422);
    }
    if ($bitis !== null && !preg_match($datePattern, $bitis)) {
        json_error('Geçersiz bitiş tarihi formatı (YYYY-MM-DD).', 422);
    }

    // Filtrelerle sipariş listesi — SiparisService/Repository henüz bu kadar esnek
    // filtreleme desteklemediği için bu geçici olarak doğrudan query kullanır.
    // TODO: SiparisRepository.getFiltered() metodu eklenecek (FAZ 8 optimizasyon)
    $where = "1=1";
    $params = [];

    if ($durum) {
        $where .= " AND durum = ?";
        $params[] = $durum;
    }

    if ($baslangic) {
        $where .= " AND tarih >= ?";
        $params[] = $baslangic;
    }

    if ($bitis) {
        $where .= " AND tarih <= ?";
        $params[] = $bitis;
    }

    // Get total count
    $total = db()->fetch("SELECT COUNT(*) as count FROM siparisler WHERE {$where}", $params);
    $totalCount = (int)($total['count'] ?? 0);

    // Get orders
    $params[] = $limit;
    $params[] = $offset;

    $siparisler = db()->fetchAll(
        "SELECT s.*, k.ad as kategori_adi
         FROM siparisler s
         LEFT JOIN kategoriler k ON s.kategori = k.slug
         WHERE {$where}
         ORDER BY s.tarih DESC
         LIMIT ? OFFSET ?",
        $params
    );

    json_success([
        'siparisler' => $siparisler,
        'meta' => [
            'toplam' => $totalCount,
            'sayfa' => $page,
            'limit' => $limit,
            'toplam_sayfa' => (int)ceil($totalCount / $limit),
        ],
    ]);
});

/**
 * GET /api/v1/siparisler/{id}
 * Sipariş detayı (Admin)
 */
$router->get('/api/v1/siparisler/{id}', function($params) use ($siparisService) {
    JWT::requireAuth();

    $siparis = $siparisService->find((int)$params['id']);
    if (!$siparis) {
        json_error('Sipariş bulunamadı.', 404);
    }

    json_success(['siparis' => $siparis]);
});

/**
 * POST /api/v1/siparisler
 * Yeni sipariş oluştur (Public - Müşteri Siparişi)
 */
$router->post('/api/v1/siparisler', function() use ($siparisService) {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new SiparisValidator('create');
    $validated = $validator->validate($data);

    // GÜVENLİK: birim_fiyat ve toplam_tutar client'tan ALINMAZ
    // Service layer fiyat hesaplamasını yapar
    $siparis = $siparisService->create($validated);

    json_response([
        'success' => true,
        'message' => 'Siparişiniz başarıyla alındı. En kısa sürede sizinle iletişime geçeceğiz.',
        'data' => ['siparis' => $siparis],
    ], 201);
});

/**
 * PATCH /api/v1/siparisler/{id}/durum
 * Sipariş durumunu güncelle (Admin)
 */
$router->patch('/api/v1/siparisler/{id}/durum', function($params) use ($siparisService) {
    $payload = JWT::requireAuth();

    $id = (int)$params['id'];
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile durum doğrula
    $validator = new SiparisValidator('status');
    $validated = $validator->validate($data);

    // Service updateStatus kullan — mevcut kayıt kontrolü + müşteri puan güncelleme dahil
    $siparis = $siparisService->updateStatus($id, $validated['durum']);

    // Log status change
    logger('Sipariş durumu güncellendi', [
        'siparis_id' => $id,
        'yeni_durum' => $validated['durum'],
        'admin_id' => $payload['user_id'] ?? null,
    ]);

    json_success(['siparis' => $siparis], 'Sipariş durumu güncellendi.');
});

/**
 * DELETE /api/v1/siparisler/{id}
 * Sipariş sil veya arşivle (Admin)
 */
$router->delete('/api/v1/siparisler/{id}', function($params) use ($siparisService) {
    $payload = JWT::requireAuth();

    $id = (int)$params['id'];

    // Service deleteOrArchive kullan — arşivleme + müşteri sipariş sayısı yönetimi dahil
    $siparisService->deleteOrArchive($id);

    // Log deletion
    logger('Sipariş silindi/arşivlendi', [
        'siparis_id' => $id,
        'admin_id' => $payload['user_id'] ?? null,
    ]);

    json_success(null, 'Sipariş başarıyla silindi.');
});

/**
 * GET /api/v1/siparisler/istatistikler
 * Sipariş istatistikleri (Admin)
 */
$router->get('/api/v1/siparisler/istatistikler', function() {
    JWT::requireAuth();

    $bugun = date('Y-m-d');
    $bugunStats = db()->fetch(
        "SELECT COUNT(*) as siparis_sayisi, COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
         FROM siparisler WHERE DATE(created_at) = ?",
        [$bugun]
    );

    $ayBaslangic = date('Y-m-01');
    $ayStats = db()->fetch(
        "SELECT COUNT(*) as siparis_sayisi, COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
         FROM siparisler WHERE created_at >= ?",
        [$ayBaslangic]
    );

    $durumlar = db()->fetchAll(
        "SELECT durum, COUNT(*) as sayi FROM siparisler GROUP BY durum"
    );

    $bekleyenler = db()->fetch(
        "SELECT COUNT(*) as sayi FROM siparisler WHERE durum = 'beklemede'"
    );

    json_success([
        'bugun' => [
            'siparis_sayisi' => (int)($bugunStats['siparis_sayisi'] ?? 0),
            'toplam_tutar' => (float)($bugunStats['toplam_tutar'] ?? 0),
        ],
        'bu_ay' => [
            'siparis_sayisi' => (int)($ayStats['siparis_sayisi'] ?? 0),
            'toplam_tutar' => (float)($ayStats['toplam_tutar'] ?? 0),
        ],
        'durum_dagilimi' => $durumlar,
        'bekleyen_siparis' => (int)($bekleyenler['sayi'] ?? 0),
    ]);
});
