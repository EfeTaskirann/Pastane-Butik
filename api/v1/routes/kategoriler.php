<?php
/**
 * Kategoriler API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;

$router = Router::getInstance();

/**
 * GET /api/v1/kategoriler
 * Tüm kategorileri listele
 */
$router->get('/api/v1/kategoriler', function() {
    $sql = "SELECT k.*,
            (SELECT COUNT(*) FROM urunler u WHERE u.kategori_id = k.id AND u.aktif = 1) as urun_sayisi
            FROM kategoriler k
            WHERE k.aktif = 1
            ORDER BY k.sira ASC";

    $kategoriler = db()->fetchAll($sql);

    json_success([
        'kategoriler' => $kategoriler,
        'toplam' => count($kategoriler),
    ]);
});

/**
 * GET /api/v1/kategoriler/{id}
 * Kategori detayı
 */
$router->get('/api/v1/kategoriler/{id}', function($params) {
    $kategori = db()->fetch(
        "SELECT * FROM kategoriler WHERE id = ? AND aktif = 1",
        [(int)$params['id']]
    );

    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    // Kategoriye ait ürünler
    $urunler = db()->fetchAll(
        "SELECT * FROM urunler WHERE kategori_id = ? AND aktif = 1 ORDER BY sira ASC",
        [(int)$params['id']]
    );

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
$router->get('/api/v1/kategoriler/slug/{slug}', function($params) {
    $kategori = db()->fetch(
        "SELECT * FROM kategoriler WHERE slug = ? AND aktif = 1",
        [$params['slug']]
    );

    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    $urunler = db()->fetchAll(
        "SELECT * FROM urunler WHERE kategori_id = ? AND aktif = 1 ORDER BY sira ASC",
        [$kategori['id']]
    );

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
$router->post('/api/v1/kategoriler', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validation
    if (empty($data['ad'])) {
        json_error('Kategori adı zorunludur.', 422, ['ad' => ['Kategori adı zorunludur.']]);
    }

    // Generate slug
    if (empty($data['slug'])) {
        $data['slug'] = str_slug($data['ad']);
    }

    // Check if slug exists
    $existing = db()->fetch("SELECT id FROM kategoriler WHERE slug = ?", [$data['slug']]);
    if ($existing) {
        $data['slug'] .= '-' . time();
    }

    $id = db()->insert('kategoriler', [
        'ad' => $data['ad'],
        'slug' => $data['slug'],
        'aciklama' => $data['aciklama'] ?? null,
        'resim' => $data['resim'] ?? null,
        'aktif' => $data['aktif'] ?? 1,
        'sira' => $data['sira'] ?? 0,
    ]);

    $kategori = db()->fetch("SELECT * FROM kategoriler WHERE id = ?", [$id]);

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
$router->put('/api/v1/kategoriler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $id = (int)$params['id'];
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $kategori = db()->fetch("SELECT * FROM kategoriler WHERE id = ?", [$id]);
    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    $updateData = [];

    if (isset($data['ad'])) {
        $updateData['ad'] = $data['ad'];
    }

    if (isset($data['slug'])) {
        $updateData['slug'] = $data['slug'];
    }

    if (isset($data['aciklama'])) {
        $updateData['aciklama'] = $data['aciklama'];
    }

    if (isset($data['resim'])) {
        $updateData['resim'] = $data['resim'];
    }

    if (isset($data['aktif'])) {
        $updateData['aktif'] = $data['aktif'] ? 1 : 0;
    }

    if (isset($data['sira'])) {
        $updateData['sira'] = (int)$data['sira'];
    }

    if (!empty($updateData)) {
        db()->update('kategoriler', $updateData, 'id = :id', ['id' => $id]);
    }

    $kategori = db()->fetch("SELECT * FROM kategoriler WHERE id = ?", [$id]);

    json_success(['kategori' => $kategori], 'Kategori başarıyla güncellendi.');
});

/**
 * DELETE /api/v1/kategoriler/{id}
 * Kategori sil (Admin)
 */
$router->delete('/api/v1/kategoriler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $id = (int)$params['id'];

    $kategori = db()->fetch("SELECT * FROM kategoriler WHERE id = ?", [$id]);
    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    // Check for products in category
    $urunSayisi = db()->fetch("SELECT COUNT(*) as count FROM urunler WHERE kategori_id = ?", [$id]);
    if ($urunSayisi['count'] > 0) {
        json_error('Bu kategoride ürünler var. Önce ürünleri silmeniz veya taşımanız gerekiyor.', 400);
    }

    db()->delete('kategoriler', 'id = :id', ['id' => $id]);

    json_success(null, 'Kategori başarıyla silindi.');
});
