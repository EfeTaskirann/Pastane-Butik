<?php
/**
 * Siparişler API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;
use Pastane\Validators\SiparisValidator;

$router = Router::getInstance();

/**
 * GET /api/v1/siparisler
 * Siparişleri listele (Admin)
 */
$router->get('/api/v1/siparisler', function() {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

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
$router->get('/api/v1/siparisler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $siparis = db()->fetch(
        "SELECT s.*, k.ad as kategori_adi
         FROM siparisler s
         LEFT JOIN kategoriler k ON s.kategori = k.slug
         WHERE s.id = ?",
        [(int)$params['id']]
    );

    if (!$siparis) {
        json_error('Sipariş bulunamadı.', 404);
    }

    json_success(['siparis' => $siparis]);
});

/**
 * POST /api/v1/siparisler
 * Yeni sipariş oluştur (Public - Müşteri Siparişi)
 */
$router->post('/api/v1/siparisler', function() {
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile doğrula
    $validator = new SiparisValidator('create');
    $validated = $validator->validate($data);

    // GÜVENLİK: birim_fiyat ve toplam_tutar client'tan ALINMAZ
    // Fiyatlar sunucu tarafında hesaplanmalıdır

    // Create order
    $id = db()->insert('siparisler', [
        'ad_soyad' => $validated['ad_soyad'],
        'telefon' => $validated['telefon'],
        'email' => $validated['email'] ?? null,
        'tarih' => $validated['tarih'],
        'saat' => $validated['saat'] ?? null,
        'kategori' => $validated['kategori'],
        'kisi_sayisi' => $validated['kisi_sayisi'] ?? null,
        'tasarim' => $validated['tasarim'] ?? null,
        'mesaj' => $validated['mesaj'] ?? null,
        'ozel_istekler' => $validated['ozel_istekler'] ?? null,
        'durum' => 'beklemede',
        'birim_fiyat' => 0,
        'toplam_tutar' => 0,
        'odeme_tipi' => $validated['odeme_tipi'] ?? 'online',
        'kanal' => 'site',
    ]);

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);

    // Log the order
    logger('Yeni sipariş oluşturuldu', [
        'siparis_id' => $id,
        'musteri' => $validated['ad_soyad'],
    ]);

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
$router->patch('/api/v1/siparisler/{id}/durum', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $id = (int)$params['id'];
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        json_error('Geçersiz JSON verisi.', 400);
    }

    // Validator ile durum doğrula
    $validator = new SiparisValidator('status');
    $validated = $validator->validate($data);

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);
    if (!$siparis) {
        json_error('Sipariş bulunamadı.', 404);
    }

    $eskiDurum = $siparis['durum'];

    db()->update('siparisler', [
        'durum' => $validated['durum'],
    ], 'id = :id', ['id' => $id]);

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);

    // Log status change
    logger('Sipariş durumu güncellendi', [
        'siparis_id' => $id,
        'eski_durum' => $eskiDurum,
        'yeni_durum' => $validated['durum'],
        'admin_id' => $payload['user_id'] ?? null,
    ]);

    json_success(['siparis' => $siparis], 'Sipariş durumu güncellendi.');
});

/**
 * DELETE /api/v1/siparisler/{id}
 * Sipariş sil (Admin)
 */
$router->delete('/api/v1/siparisler/{id}', function($params) {
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

    $id = (int)$params['id'];

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);
    if (!$siparis) {
        json_error('Sipariş bulunamadı.', 404);
    }

    db()->delete('siparisler', 'id = :id', ['id' => $id]);

    // Log deletion
    logger('Sipariş silindi', [
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
    $payload = JWT::requireAuth();
    if (!$payload) {
        json_error('Yetkilendirme gerekli.', 401);
    }

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
