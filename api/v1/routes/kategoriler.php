<?php
/**
 * Kategoriler API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Validators\KategoriValidator;

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

    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new KategoriValidator('create');
    $validated = $validator->validate($data);

    // Generate slug
    if (empty($validated['slug'])) {
        $validated['slug'] = str_slug($validated['ad']);
    }

    // Check if slug exists
    $existing = db()->fetch("SELECT id FROM kategoriler WHERE slug = ?", [$validated['slug']]);
    if ($existing) {
        $validated['slug'] .= '-' . time();
    }

    $id = db()->insert('kategoriler', [
        'ad' => $validated['ad'],
        'slug' => $validated['slug'],
        'aciklama' => $validated['aciklama'] ?? null,
        'resim' => $validated['resim'] ?? null,
        'aktif' => $validated['aktif'] ?? 1,
        'sira' => (int)($validated['sira'] ?? 0),
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
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    $kategori = db()->fetch("SELECT * FROM kategoriler WHERE id = ?", [$id]);
    if (!$kategori) {
        json_error('Kategori bulunamadı.', 404);
    }

    // Validator ile doğrula (update senaryosu)
    $validator = new KategoriValidator('update');
    $validated = $validator->validate($data);

    // Sadece gönderilen alanları güncelle (whitelist)
    $updateData = [];
    $allowedFields = ['ad', 'slug', 'aciklama', 'resim', 'aktif', 'sira'];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $validated)) {
            if ($field === 'aktif') {
                $updateData[$field] = $validated[$field] ? 1 : 0;
            } elseif ($field === 'sira') {
                $updateData[$field] = (int)$validated[$field];
            } else {
                $updateData[$field] = $validated[$field];
            }
        }
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
