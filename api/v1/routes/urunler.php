<?php
/**
 * Ürünler API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Services\UrunService;

$router = Router::getInstance();

/**
 * GET /api/v1/urunler
 * Tüm ürünleri listele
 */
$router->get('/api/v1/urunler', function() {
    $service = new UrunService();

    $kategoriId = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    // Search query uzunluk kontrolü
    if ($search !== null && (mb_strlen($search) < 1 || mb_strlen($search) > 100)) {
        json_error('Arama sorgusu 1-100 karakter arasında olmalıdır.', 422);
    }

    if ($search) {
        $urunler = $service->search($search, $kategoriId, $limit ?? 20);
    } else {
        $urunler = $service->getActive($kategoriId, $limit);
    }

    json_success([
        'urunler' => $urunler,
        'toplam' => count($urunler),
    ]);
});

/**
 * GET /api/v1/urunler/one-cikan
 * Öne çıkan ürünler
 */
$router->get('/api/v1/urunler/one-cikan', function() {
    $service = new UrunService();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;

    json_success([
        'urunler' => $service->getFeatured($limit),
    ]);
});

/**
 * GET /api/v1/urunler/{id}
 * Ürün detayı
 */
$router->get('/api/v1/urunler/{id}', function($params) {
    $service = new UrunService();
    $urun = $service->findWithCategory((int)$params['id']);

    if (!$urun) {
        json_error('Ürün bulunamadı.', 404);
    }

    json_success(['urun' => $urun]);
});

/**
 * GET /api/v1/urunler/slug/{slug}
 * Ürün detayı (slug ile)
 */
$router->get('/api/v1/urunler/slug/{slug}', function($params) {
    $service = new UrunService();
    $urun = $service->findBySlug($params['slug']);

    if (!$urun) {
        json_error('Ürün bulunamadı.', 404);
    }

    json_success(['urun' => $urun]);
});

/**
 * GET /api/v1/urunler/fiyat-araligi
 * Fiyat aralığı
 */
$router->get('/api/v1/urunler/fiyat-araligi', function() {
    $service = new UrunService();
    $kategoriId = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : null;

    json_success($service->getPriceRange($kategoriId));
});

// ============================================
// ADMIN ENDPOINTS (JWT Required)
// ============================================

/**
 * POST /api/v1/urunler
 * Yeni ürün ekle (Admin)
 */
$router->post('/api/v1/urunler', function() {
    // Require authentication
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $service = new UrunService();

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $urun = $service->create($data);
        json_response([
            'success' => true,
            'message' => 'Ürün başarıyla oluşturuldu.',
            'data' => ['urun' => $urun],
        ], 201);
    } catch (\Pastane\Exceptions\ValidationException $e) {
        json_error($e->getMessage(), 422, $e->getErrors());
    }
});

/**
 * PUT /api/v1/urunler/{id}
 * Ürün güncelle (Admin)
 */
$router->put('/api/v1/urunler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $service = new UrunService();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        $urun = $service->update((int)$params['id'], $data);
        json_success(['urun' => $urun], 'Ürün başarıyla güncellendi.');
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error($e->getMessage(), $e->getStatusCode());
    } catch (\Pastane\Exceptions\ValidationException $e) {
        json_error($e->getMessage(), 422, $e->getErrors());
    }
});

/**
 * DELETE /api/v1/urunler/{id}
 * Ürün sil (Admin)
 */
$router->delete('/api/v1/urunler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $service = new UrunService();

    try {
        $service->delete((int)$params['id']);
        json_success(null, 'Ürün başarıyla silindi.');
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error($e->getMessage(), $e->getStatusCode());
    }
});

/**
 * PATCH /api/v1/urunler/{id}/toggle
 * Ürün durumunu değiştir (Admin)
 */
$router->patch('/api/v1/urunler/{id}/toggle', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $service = new UrunService();
    $newStatus = $service->toggleActive((int)$params['id']);

    json_success([
        'aktif' => $newStatus,
    ], $newStatus ? 'Ürün aktifleştirildi.' : 'Ürün pasifleştirildi.');
});
