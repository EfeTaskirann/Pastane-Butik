<?php
/**
 * Kategoriler API Routes
 *
 * Service layer üzerinden veri erişimi — doğrudan db() çağrısı yok.
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Validators\KategoriValidator;

$router = Router::getInstance();
$kategoriService = kategori_service();
$urunService = urun_service();

// ============================================
// PUBLIC ENDPOINTS
// ============================================

/**
 * GET /api/v1/kategoriler
 * Tüm kategorileri listele (ürün sayısıyla birlikte)
 */
$router->get('/api/v1/kategoriler', function() use ($kategoriService) {
    $kategoriler = $kategoriService->getAllWithProductCount();

    json_success([
        'kategoriler' => $kategoriler,
        'toplam' => count($kategoriler),
    ]);
});

/**
 * GET /api/v1/kategoriler/{id}
 * Kategori detayı
 */
$router->get('/api/v1/kategoriler/{id}', function($params) use ($kategoriService, $urunService) {
    $kategori = $kategoriService->find((int)$params['id']);

    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    // Kategoriye ait aktif ürünler
    $urunler = $urunService->getByCategory((int)$params['id'], true);

    json_success([
        'kategori' => $kategori,
        'urunler' => $urunler,
        'urun_sayisi' => count($urunler),
    ]);
});

/**
 * GET /api/v1/kategoriler/slug/{slug}
 * Kategori detayı (slug ile)
 */
$router->get('/api/v1/kategoriler/slug/{slug}', function($params) use ($kategoriService, $urunService) {
    $kategori = $kategoriService->findBySlug($params['slug']);

    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    $urunler = $urunService->getByCategory((int)$kategori['id'], true);

    json_success([
        'kategori' => $kategori,
        'urunler' => $urunler,
        'urun_sayisi' => count($urunler),
    ]);
});

// ============================================
// ADMIN ENDPOINTS (JWT Required)
// ============================================

/**
 * POST /api/v1/kategoriler
 * Yeni kategori ekle (Admin)
 */
$router->post('/api/v1/kategoriler', function() use ($kategoriService) {
    // JWT::requireAuth() artık HttpException fırlatıyor — null check gereksiz
    JWT::requireAuth();

    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new KategoriValidator('create');
    $validated = $validator->validate($data);

    // Service layer slug oluşturma ve unique kontrolünü otomatik yapar
    $kategori = $kategoriService->create($validated);

    json_response([
        'success' => true,
        'message' => 'Kategori başarıyla oluşturuldu.',
        'data' => ['kategori' => $kategori],
    ], 201);
});

/**
 * PUT /api/v1/kategoriler/{id}
 * Kategori güncelle (Admin)
 */
$router->put('/api/v1/kategoriler/{id}', function($params) use ($kategoriService) {
    JWT::requireAuth();

    $id = (int)$params['id'];
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Mevcut kayıt kontrolü — service findOrFail() ile 404 fırlatır
    $kategoriService->findOrFail($id);

    // Validator ile doğrula (update senaryosu)
    $validator = new KategoriValidator('update');
    $validated = $validator->validate($data);

    // Service update — slug yeniden oluşturma otomatik
    $kategori = $kategoriService->update($id, $validated);

    json_success(['kategori' => $kategori], 'Kategori başarıyla güncellendi.');
});

/**
 * DELETE /api/v1/kategoriler/{id}
 * Kategori sil (Admin)
 */
$router->delete('/api/v1/kategoriler/{id}', function($params) use ($kategoriService) {
    JWT::requireAuth();

    $id = (int)$params['id'];

    // findOrFail → 404 fırlatır
    $kategoriService->findOrFail($id);

    // delete() → ürün varsa HttpException::badRequest fırlatır
    $kategoriService->delete($id);

    json_success(null, 'Kategori başarıyla silindi.');
});
