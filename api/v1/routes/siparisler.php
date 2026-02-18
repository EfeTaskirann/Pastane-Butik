<?php
/**
 * Siparişler API Routes
 *
 * @package Pastane\API\v1
 */

use Pastane\Router\Router;

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

    $durum = $_GET['durum'] ?? null;
    $baslangic = $_GET['baslangic'] ?? null;
    $bitis = $_GET['bitis'] ?? null;

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
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Validation
    $errors = [];

    if (empty($data['ad_soyad'])) {
        $errors['ad_soyad'] = ['Ad soyad zorunludur.'];
    }

    if (empty($data['telefon'])) {
        $errors['telefon'] = ['Telefon numarası zorunludur.'];
    } elseif (!validate_phone($data['telefon'])) {
        $errors['telefon'] = ['Geçerli bir telefon numarası giriniz.'];
    }

    if (empty($data['tarih'])) {
        $errors['tarih'] = ['Teslim tarihi zorunludur.'];
    } else {
        // Geçmiş tarih kontrolü - bugünden önce olamaz
        $teslimTarihi = strtotime($data['tarih']);
        $bugun = strtotime(date('Y-m-d'));
        if ($teslimTarihi !== false && $teslimTarihi < $bugun) {
            $errors['tarih'] = ['Teslim tarihi geçmiş bir tarih olamaz.'];
        }
    }

    if (empty($data['kategori'])) {
        $errors['kategori'] = ['Kategori seçimi zorunludur.'];
    }

    if (!empty($errors)) {
        json_error('Doğrulama hatası.', 422, $errors);
    }

    // GÜVENLİK: birim_fiyat ve toplam_tutar client'tan ALINMAZ
    // Fiyatlar sunucu tarafında hesaplanmalıdır (admin tarafından sonradan belirlenir)
    // Client'ın fiyat göndermesi price manipulation saldırısına açıktır

    // Create order
    $id = db()->insert('siparisler', [
        'ad_soyad' => $data['ad_soyad'],
        'telefon' => $data['telefon'],
        'email' => $data['email'] ?? null,
        'tarih' => $data['tarih'],
        'saat' => $data['saat'] ?? null,
        'kategori' => $data['kategori'],
        'kisi_sayisi' => $data['kisi_sayisi'] ?? null,
        'tasarim' => $data['tasarim'] ?? null,
        'mesaj' => $data['mesaj'] ?? null,
        'ozel_istekler' => $data['ozel_istekler'] ?? null,
        'durum' => 'beklemede',
        'birim_fiyat' => 0,
        'toplam_tutar' => 0,
        'odeme_tipi' => $data['odeme_tipi'] ?? 'online',
        'kanal' => 'site',
    ]);

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);

    // Log the order
    logger('Yeni sipariş oluşturuldu', [
        'siparis_id' => $id,
        'musteri' => $data['ad_soyad'],
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
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['durum'])) {
        json_error('Durum zorunludur.', 422);
    }

    $gecerliDurumlar = ['beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal'];
    if (!in_array($data['durum'], $gecerliDurumlar)) {
        json_error('Geçersiz durum.', 422);
    }

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);
    if (!$siparis) {
        json_error('Sipariş bulunamadı.', 404);
    }

    db()->update('siparisler', [
        'durum' => $data['durum'],
    ], 'id = :id', ['id' => $id]);

    $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$id]);

    // Log status change
    logger('Sipariş durumu güncellendi', [
        'siparis_id' => $id,
        'eski_durum' => $siparis['durum'] ?? 'bilinmiyor',
        'yeni_durum' => $data['durum'],
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

    // Today's stats
    $bugun = date('Y-m-d');
    $bugunStats = db()->fetch(
        "SELECT COUNT(*) as siparis_sayisi, COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
         FROM siparisler WHERE DATE(created_at) = ?",
        [$bugun]
    );

    // This month's stats
    $ayBaslangic = date('Y-m-01');
    $ayStats = db()->fetch(
        "SELECT COUNT(*) as siparis_sayisi, COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
         FROM siparisler WHERE created_at >= ?",
        [$ayBaslangic]
    );

    // Status counts
    $durumlar = db()->fetchAll(
        "SELECT durum, COUNT(*) as sayi FROM siparisler GROUP BY durum"
    );

    // Pending orders
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
