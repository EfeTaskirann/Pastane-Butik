<?php
/**
 * Ürünler API Routes
 *
 * Ürün CRUD ve arama endpoint'leri.
 * Service layer üzerinden veri erişimi sağlanır.
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;

$router = Router::getInstance();
$urunService = urun_service();

/**
 * GET /api/v1/urunler
 * Tüm ürünleri listele (Public)
 */
$router->get('/api/v1/urunler', function() use ($urunService) {
    $kategoriId = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    // Search query uzunluk kontrolü
    if ($search !== null && (mb_strlen($search) < 1 || mb_strlen($search) > 100)) {
        json_error('Arama sorgusu 1-100 karakter arasında olmalıdır.', 422);
    }

    if ($search) {
        $urunler = $urunService->search($search, $kategoriId, $limit ?? 20);
    } else {
        $urunler = $urunService->getActive($kategoriId, $limit);
    }

    json_success([
        'urunler' => $urunler,
        'toplam' => count($urunler),
    ]);
});

/**
 * GET /api/v1/urunler/one-cikan
 * Öne çıkan ürünler (Public)
 */
$router->get('/api/v1/urunler/one-cikan', function() use ($urunService) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;

    json_success([
        'urunler' => $urunService->getFeatured($limit),
    ]);
});

/**
 * GET /api/v1/urunler/{id}
 * Ürün detayı (Public)
 */
$router->get('/api/v1/urunler/{id}', function($params) use ($urunService) {
    $urun = $urunService->findWithCategory((int)$params['id']);

    if (!$urun) {
        json_error('Ürün bulunamadı.', 404);
    }

    json_success(['urun' => $urun]);
});

/**
 * GET /api/v1/urunler/slug/{slug}
 * Ürün detayı - slug ile (Public)
 */
$router->get('/api/v1/urunler/slug/{slug}', function($params) use ($urunService) {
    $urun = $urunService->findBySlug($params['slug']);

    if (!$urun) {
        json_error('Ürün bulunamadı.', 404);
    }

    json_success(['urun' => $urun]);
});

/**
 * GET /api/v1/urunler/fiyat-araligi
 * Fiyat aralığı (Public)
 */
$router->get('/api/v1/urunler/fiyat-araligi', function() use ($urunService) {
    $kategoriId = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;

    json_success($urunService->getPriceRange($kategoriId));
});

// ============================================
// ADMIN ENDPOINTS (JWT Required)
// ============================================

/**
 * POST /api/v1/urunler
 * Yeni ürün ekle (Admin)
 */
$router->post('/api/v1/urunler', function() use ($urunService) {
    JWT::requireAuth();

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $urun = $urunService->create($data);
    json_response([
        'success' => true,
        'message' => 'Ürün başarıyla oluşturuldu.',
        'data' => ['urun' => $urun],
    ], 201);
});

/**
 * PUT /api/v1/urunler/{id}
 * Ürün güncelle (Admin)
 */
$router->put('/api/v1/urunler/{id}', function($params) use ($urunService) {
    JWT::requireAuth();

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $urun = $urunService->update((int)$params['id'], $data);
    json_success(['urun' => $urun], 'Ürün başarıyla güncellendi.');
});

/**
 * DELETE /api/v1/urunler/{id}
 * Ürün sil (Admin)
 */
$router->delete('/api/v1/urunler/{id}', function($params) use ($urunService) {
    JWT::requireAuth();

    $urunService->delete((int)$params['id']);
    json_success(null, 'Ürün başarıyla silindi.');
});

/**
 * PATCH /api/v1/urunler/{id}/toggle
 * Ürün durumunu değiştir (Admin)
 */
$router->patch('/api/v1/urunler/{id}/toggle', function($params) use ($urunService) {
    JWT::requireAuth();

    $newStatus = $urunService->toggleActive((int)$params['id']);

    json_success([
        'aktif' => $newStatus,
    ], $newStatus ? 'Ürün aktifleştirildi.' : 'Ürün pasifleştirildi.');
});
