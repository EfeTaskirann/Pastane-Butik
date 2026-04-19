<?php
/**
 * Menu API Routes (Musteri Tarafi)
 *
 * QR Menu sistemi musteri endpoint'leri.
 * Admin auth GEREKMEZ — oturum token ile calisir.
 *
 * Endpoint'ler:
 *   GET  /api/v1/menu/{qr_token}           — Menu verisini JSON olarak getir
 *   POST /api/v1/menu/siparis              — Siparis olustur
 *   GET  /api/v1/menu/siparis/{id}/durum   — Siparis durumu sorgula
 *   POST /api/v1/menu/odeme/baslat         — Odeme baslat
 *   POST /api/v1/menu/odeme/callback       — Gateway callback handler
 *   GET  /api/v1/menu/odeme/durum/{siparis_id} — Odeme durumu sorgula
 *
 * @package Pastane\API\v1
 * @since 1.0.0
 */

use Pastane\Router\Router;

$router = Router::getInstance();

// Service instances
$oturumService  = masa_oturum_service();
$siparisService = masa_siparis_service();

// ============================================
// MUSTERI ENDPOINTS (Oturum Token ile)
// ============================================

/**
 * GET /api/v1/menu/{qr_token}
 * QR token ile menu verisini getir
 *
 * Masa aktif degilse 403 doner.
 * Aktifse kategoriler + cafe menusu urunleri JSON olarak doner.
 */
$router->get('/api/v1/menu/{qr_token}', function ($params) use ($oturumService) {
    $qrToken = trim((string)($params['qr_token'] ?? ''));

    if (empty($qrToken) || strlen($qrToken) > 128) {
        json_error('Gecersiz QR token.', 400);
    }

    // QR token ile oturum dogrula
    $sonuc = $oturumService->oturumDogrula($qrToken);

    if (!$sonuc['gecerli']) {
        json_error($sonuc['mesaj'], 403);
    }

    // Kategorileri getir
    $kategoriler = db()->fetchAll(
        "SELECT id, isim, slug, sira FROM kategoriler ORDER BY sira ASC, isim ASC"
    );

    // Cafe menusundeki aktif urunleri getir
    $urunler = db()->fetchAll(
        "SELECT u.id, u.isim, u.aciklama, u.fiyat, u.gorsel, u.kategori_id,
                u.fiyat_4kisi, u.fiyat_6kisi, u.fiyat_8kisi, u.fiyat_10kisi,
                u.stok_durumu,
                k.isim as kategori_ad, k.slug as kategori_slug
         FROM urunler u
         LEFT JOIN kategoriler k ON u.kategori_id = k.id
         WHERE u.aktif = 1 AND u.cafe_menusu = 1
         ORDER BY u.sira ASC, u.isim ASC"
    );

    json_success([
        'masa'        => [
            'id'      => (int)$sonuc['masa']['id'],
            'masa_no' => (int)$sonuc['masa']['masa_no'],
            'konum'   => $sonuc['masa']['konum'] ?? null,
        ],
        'oturum_token' => $sonuc['oturum_token'],
        'kategoriler'  => $kategoriler,
        'urunler'      => $urunler,
    ], $sonuc['mesaj']);
});

/**
 * POST /api/v1/menu/siparis
 * Yeni siparis olustur
 *
 * Body: { oturum_token, kalemler: [{urun_id, porsiyon, adet, ozel_not}], siparis_notu }
 * Rate limit: IP basina dakikada 5 siparis
 */
$router->post('/api/v1/menu/siparis', function () use ($siparisService) {
    // Rate limit: siparis icin ozel sinir (IP basina dakikada 5)
    \RateLimiter::enforce('menu_siparis');

    // JSON body oku
    $body = json_decode(file_get_contents('php://input'), true);
    if ($body === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    // Oturum token dogrula
    $oturumToken = trim((string)($body['oturum_token'] ?? ''));
    if (empty($oturumToken)) {
        json_error('Oturum token zorunludur.', 400);
    }

    if (strlen($oturumToken) > 128) {
        json_error('Gecersiz oturum token.', 400);
    }

    // Kalemler validasyonu
    $kalemler = $body['kalemler'] ?? [];
    if (!is_array($kalemler) || empty($kalemler)) {
        json_error('En az bir urun secmelisiniz.', 422);
    }

    if (count($kalemler) > 50) {
        json_error('Tek sipariste en fazla 50 kalem olabilir.', 422);
    }

    // Kalemleri temizle ve dogrula
    $temizKalemler = [];
    foreach ($kalemler as $index => $kalem) {
        if (!is_array($kalem)) {
            json_error('Gecersiz kalem formati (index: ' . $index . ').', 422);
        }

        $urunId = isset($kalem['urun_id']) ? (int)$kalem['urun_id'] : 0;
        if ($urunId < 1) {
            json_error('Gecersiz urun ID (index: ' . $index . ').', 422);
        }

        $adet = isset($kalem['adet']) ? (int)$kalem['adet'] : 1;
        if ($adet < 1 || $adet > 99) {
            json_error('Adet 1 ile 99 arasinda olmalidir (index: ' . $index . ').', 422);
        }

        // Porsiyon (opsiyonel)
        $porsiyon = isset($kalem['porsiyon']) ? trim((string)$kalem['porsiyon']) : null;
        // 'Normal' porsiyon = default fiyat, null olarak isle
        if ($porsiyon === 'Normal' || $porsiyon === 'normal') {
            $porsiyon = null;
        }
        if ($porsiyon !== null && $porsiyon !== '') {
            $gecerliPorsiyonlar = ['4kisi', '6kisi', '8kisi', '10kisi'];
            if (!in_array($porsiyon, $gecerliPorsiyonlar, true)) {
                json_error('Gecersiz porsiyon degeri (index: ' . $index . '). Gecerli: 4kisi, 6kisi, 8kisi, 10kisi', 422);
            }
        } else {
            $porsiyon = null;
        }

        // Ozel not (opsiyonel, max 200 karakter)
        $ozelNot = isset($kalem['ozel_not']) ? trim((string)$kalem['ozel_not']) : null;
        if ($ozelNot !== null && mb_strlen($ozelNot) > 200) {
            json_error('Ozel not en fazla 200 karakter olabilir (index: ' . $index . ').', 422);
        }

        // XSS temizligi
        if ($ozelNot !== null) {
            $ozelNot = htmlspecialchars($ozelNot, ENT_QUOTES, 'UTF-8');
        }

        $temizKalemler[] = [
            'urun_id'  => $urunId,
            'adet'     => $adet,
            'porsiyon' => $porsiyon,
            'ozel_not' => $ozelNot,
        ];
    }

    // Siparis notu (opsiyonel, max 500 karakter)
    $siparisNotu = isset($body['siparis_notu']) ? trim((string)$body['siparis_notu']) : null;
    if ($siparisNotu !== null && $siparisNotu !== '') {
        if (mb_strlen($siparisNotu) > 500) {
            json_error('Siparis notu en fazla 500 karakter olabilir.', 422);
        }
        // XSS temizligi
        $siparisNotu = htmlspecialchars($siparisNotu, ENT_QUOTES, 'UTF-8');
    } else {
        $siparisNotu = null;
    }

    // Service uzerinden siparis olustur
    $siparis = $siparisService->siparisOlustur($oturumToken, $temizKalemler, $siparisNotu);

    // Odeme gerekli mi kontrol et (tutar > 0 ve odenmemis)
    $odemeDurumu = $siparis['odeme_durumu'] ?? 'odenmedi';
    $toplamTutar = (float)$siparis['toplam_tutar'];
    $odemeGerekli = $toplamTutar > 0 && $odemeDurumu !== 'odendi';

    // Takip tokeni — IDOR korumali public takip URL'i icin
    $takipToken = (string)($siparis['takip_token'] ?? '');

    json_response([
        'success' => true,
        'message' => 'Siparis basariyla olusturuldu.',
        'data'    => [
            'siparis_id'    => (int)$siparis['id'],
            'durum'         => $siparis['durum'],
            'toplam_tutar'  => $toplamTutar,
            'kalemler'      => $siparis['kalemler'] ?? [],
            'odeme_gerekli' => $odemeGerekli,
            'odeme_url'     => $odemeGerekli
                ? '/menu/odeme.php?siparis=' . (int)$siparis['id']
                : null,
            // Takip URL'i tokenla doner; musteri bu URL'i kullanmali.
            'takip_token'   => $takipToken,
            'takip_url'     => $takipToken !== ''
                ? '/menu/siparis-takip.php?token=' . $takipToken
                : null,
        ],
    ], 201);
});

/**
 * GET /api/v1/menu/siparis/{id}/durum
 * Siparis durumunu sorgula
 *
 * Oturum token cookie veya X-Oturum-Token header'indan alinir.
 * Siparisin bu oturuma ait oldugu dogrulanir.
 */
$router->get('/api/v1/menu/siparis/{id}/durum', function ($params) use ($siparisService) {
    $siparisId = (int)($params['id'] ?? 0);
    if ($siparisId < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    // Oturum token: once header, sonra cookie
    $oturumToken = '';
    if (!empty($_SERVER['HTTP_X_OTURUM_TOKEN'])) {
        $oturumToken = trim((string)$_SERVER['HTTP_X_OTURUM_TOKEN']);
    } elseif (!empty($_COOKIE['oturum_token'])) {
        $oturumToken = trim((string)$_COOKIE['oturum_token']);
    }

    if (empty($oturumToken) || strlen($oturumToken) > 128) {
        json_error('Oturum token gerekli. Cookie veya X-Oturum-Token header gonderin.', 401);
    }

    // Oturum dogrula
    $oturumRepo = new \Pastane\Repositories\MasaOturumRepository();
    $oturum = $oturumRepo->findByOturumToken($oturumToken);

    if ($oturum === null) {
        json_error('Gecersiz oturum token.', 401);
    }

    // Siparis detay
    try {
        $siparis = $siparisService->getSiparisDetay($siparisId);
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error('Siparis bulunamadi.', 404);
    }

    // Guvenlik: siparis bu oturuma ait mi?
    if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
        json_error('Bu siparisi goruntuleme yetkiniz yok.', 403);
    }

    // Durum etiketleri
    $durumEtiketleri = [
        'beklemede'     => 'Beklemede',
        'onaylandi'     => 'Onaylandi',
        'hazirlaniyor'  => 'Hazirlaniyor',
        'hazir'         => 'Hazir',
        'teslim_edildi' => 'Teslim Edildi',
        'iptal'         => 'Iptal',
    ];

    json_success([
        'siparis_id'    => (int)$siparis['id'],
        'durum'         => $siparis['durum'],
        'durum_etiket'  => $durumEtiketleri[$siparis['durum']] ?? $siparis['durum'],
        'toplam_tutar'  => (float)$siparis['toplam_tutar'],
        'odeme_durumu'  => $siparis['odeme_durumu'] ?? 'odenmedi',
        'siparis_zamani' => $siparis['siparis_zamani'] ?? null,
    ]);
});

// ============================================
// ODEME ENDPOINTS (Oturum Token ile)
// ============================================

/**
 * Oturum token'i header veya cookie'den cikaran yardimci fonksiyon.
 *
 * @return string Oturum token
 */
$oturumTokenCikar = function (): string {
    $oturumToken = '';
    if (!empty($_SERVER['HTTP_X_OTURUM_TOKEN'])) {
        $oturumToken = trim((string)$_SERVER['HTTP_X_OTURUM_TOKEN']);
    } elseif (!empty($_COOKIE['oturum_token'])) {
        $oturumToken = trim((string)$_COOKIE['oturum_token']);
    }

    if (empty($oturumToken) || strlen($oturumToken) > 128) {
        json_error('Oturum token gerekli. Cookie veya X-Oturum-Token header gonderin.', 401);
    }

    return $oturumToken;
};

/**
 * Oturum token ile oturum kaydini dogrulayan yardimci fonksiyon.
 *
 * @param string $oturumToken
 * @return array Oturum kaydi
 */
$oturumDogrula = function (string $oturumToken): array {
    $oturumRepo = new \Pastane\Repositories\MasaOturumRepository();
    $oturum = $oturumRepo->findByOturumToken($oturumToken);

    if ($oturum === null) {
        json_error('Gecersiz oturum token.', 401);
    }

    if (($oturum['durum'] ?? '') !== 'aktif') {
        json_error('Bu oturum artik aktif degil.', 403);
    }

    return $oturum;
};

/**
 * POST /api/v1/menu/odeme/masada
 * Masada odeme secimi (nakit/POS)
 *
 * Body: { siparis_id, oturum_token }
 * Musteri online odeme yerine masada odemeyi secer.
 * Siparis onaylandi durumuna gecer, garson teslimde odemeyi alir.
 */
$router->post('/api/v1/menu/odeme/masada', function () use ($siparisService, $oturumTokenCikar) {
    \RateLimiter::enforce('menu_odeme');

    $body = json_decode(file_get_contents('php://input'), true);
    if ($body === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    $siparisId = isset($body['siparis_id']) ? (int)$body['siparis_id'] : 0;
    if ($siparisId < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    $oturumToken = trim((string)($body['oturum_token'] ?? ''));
    if (empty($oturumToken)) {
        $oturumToken = $oturumTokenCikar();
    }
    if (strlen($oturumToken) > 128) {
        json_error('Gecersiz oturum token.', 400);
    }

    try {
        $sonuc = $siparisService->masadaOdemeSecimi($siparisId, $oturumToken);
    } catch (\Pastane\Exceptions\ValidationException $e) {
        json_error($e->getMessage(), 422, $e->getErrors());
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error($e->getMessage(), $e->getCode() ?: 400);
    }

    // Takip URL'i token bazli — IDOR korumasi icin zorunlu
    $takipToken = (string)($sonuc['siparis']['takip_token'] ?? '');
    $redirectUrl = $takipToken !== ''
        ? 'siparis-takip.php?token=' . $takipToken
        : 'index.php';

    json_response([
        'success' => true,
        'message' => $sonuc['mesaj'],
        'data'    => [
            'siparis_id'    => (int)$sonuc['siparis']['id'],
            'durum'         => $sonuc['siparis']['durum'],
            'odeme_yontemi' => 'masada',
            'takip_token'   => $takipToken,
            'redirect_url'  => $redirectUrl,
        ],
    ]);
});

/**
 * POST /api/v1/menu/odeme/baslat
 * Odeme baslatma
 *
 * Body: { siparis_id, oturum_token }
 * Rate limit: IP basina dakikada 3 odeme baslatma
 *
 * - Oturum dogrulama
 * - Siparisin bu oturuma ait oldugu kontrol edilir
 * - Siparis zaten odenmis mi kontrol edilir
 * - OdemeService.odemeBaslat() cagrilir
 */
$router->post('/api/v1/menu/odeme/baslat', function () use ($siparisService, $oturumTokenCikar, $oturumDogrula) {
    // Rate limit: odeme icin siki sinir (IP basina dakikada 3)
    \RateLimiter::enforce('menu_odeme');

    // JSON body oku
    $body = json_decode(file_get_contents('php://input'), true);
    if ($body === null) {
        json_error('Gecersiz JSON verisi.', 400);
    }

    // Siparis ID dogrula
    $siparisId = isset($body['siparis_id']) ? (int)$body['siparis_id'] : 0;
    if ($siparisId < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    // Oturum token dogrula (body'den veya header/cookie'den)
    $oturumToken = trim((string)($body['oturum_token'] ?? ''));
    if (empty($oturumToken)) {
        $oturumToken = $oturumTokenCikar();
    }
    if (strlen($oturumToken) > 128) {
        json_error('Gecersiz oturum token.', 400);
    }

    // Oturum dogrula
    $oturum = $oturumDogrula($oturumToken);

    // Siparis detayini getir
    try {
        $siparis = $siparisService->getSiparisDetay($siparisId);
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error('Siparis bulunamadi.', 404);
    }

    // Guvenlik: siparis bu oturuma ait mi?
    if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
        json_error('Bu siparis uzerinde islem yetkiniz yok.', 403);
    }

    // Siparis zaten odenmis mi?
    $odemeDurumu = $siparis['odeme_durumu'] ?? 'odenmedi';
    if ($odemeDurumu === 'odendi') {
        json_error('Bu siparis zaten odenmistir.', 409);
    }

    // Siparis iptal mi?
    if (($siparis['durum'] ?? '') === 'iptal') {
        json_error('Iptal edilmis siparis icin odeme baslatilamaz.', 422);
    }

    // Tutar kontrolu (0 veya negatif tutar icin odeme baslatilamaz)
    $toplamTutar = (float)($siparis['toplam_tutar'] ?? 0);
    if ($toplamTutar <= 0) {
        json_error('Gecersiz siparis tutari.', 422);
    }

    // OdemeService uzerinden odeme baslat
    // OdemeService henuz mevcut olmayabilir (DEV1 tarafindan olusturuluyor)
    if (!class_exists(\Pastane\Services\OdemeService::class)) {
        json_error('Odeme servisi henuz aktif degil.', 503);
    }

    try {
        $odemeService = new \Pastane\Services\OdemeService();
        // Musteri bilgileri (QR Menu'de minimal bilgi yeterli)
        // Masa numarasini siparis bilgisinden al
        $masaNo = (int) ($siparis['masa_id'] ?? 0);
        $musteri = [
            'ad'      => 'Masa',
            'soyad'   => (string) $masaNo,
            'email'   => 'masa' . $masaNo . '@tatlidusler.com',
            'telefon' => '',
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'adres'   => 'Masa ' . $masaNo,
        ];

        // Callback URL — config'den APP_URL al, HTTP_HOST güvenlik riski taşır
        $odemeConfig = config('odeme') ?? [];
        $appUrl = config('app.url') ?? 'http://localhost/pastane';
        $callbackUrl = $odemeConfig['callback_url']
            ?? (rtrim($appUrl, '/') . '/menu/odeme-callback.php');

        $sonuc = $odemeService->odemeBaslat($siparisId, $musteri, $callbackUrl);
    } catch (\Pastane\Exceptions\ValidationException $e) {
        json_error($e->getMessage(), 422, $e->getErrors());
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error($e->getMessage(), $e->getStatusCode());
    } catch (\Throwable $e) {
        // Hassas bilgi loglamadan genel hata
        try {
            if (class_exists('Logger', false)) {
                \Logger::getInstance()->error('Odeme baslatma hatasi', [
                    'siparis_id' => $siparisId,
                    'hata' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable) {
            // Logger hatasi — sessizce devam et
        }
        json_error('Odeme baslatilirken bir hata olustu. Lutfen tekrar deneyin.', 500);
    }

    json_success([
        'odeme_id'      => $sonuc['odeme_id'] ?? null,
        'islem_id'      => $sonuc['token'] ?? null,
        'odeme_formu'   => $sonuc['odeme_formu'] ?? null,
        'redirect_url'  => $sonuc['redirect_url'] ?? null,
        'tutar'         => $toplamTutar,
    ], 'Odeme baslatildi.');
});

/**
 * POST /api/v1/menu/odeme/callback
 * Gateway callback handler
 *
 * Gateway'den gelen odeme sonucu verisini isler.
 * Idempotency kontrolu: ayni odeme birden fazla kez islenmez.
 * Not: Bu endpoint disaridan (gateway) cagirilir, oturum dogrulama YOKTUR.
 */
$router->post('/api/v1/menu/odeme/callback', function () {
    // Rate limit: callback icin (IP basina dakikada 10)
    \RateLimiter::enforce('odeme_callback');

    // JSON body oku
    $body = json_decode(file_get_contents('php://input'), true);
    if ($body === null) {
        // Bazi gateway'ler form-urlencoded gonderebilir
        $body = $_POST;
    }

    if (empty($body)) {
        json_error('Gecersiz callback verisi.', 400);
    }

    // OdemeService uzerinden odeme dogrula
    if (!class_exists(\Pastane\Services\OdemeService::class)) {
        json_error('Odeme servisi henuz aktif degil.', 503);
    }

    try {
        $odemeService = new \Pastane\Services\OdemeService();

        // Token'i body'den cikar
        $token = $body['token'] ?? $body['islem_id'] ?? '';
        if (empty($token)) {
            json_error('Odeme token bilgisi (token veya islem_id) zorunludur.', 400);
        }

        $sonuc = $odemeService->odemeDogrula($token);
    } catch (\Pastane\Exceptions\HttpException $e) {
        // Loglama (hassas veri haric)
        try {
            if (class_exists('Logger', false)) {
                \Logger::getInstance()->warning('Odeme callback hatasi', [
                    'status_code' => $e->getStatusCode(),
                    'mesaj' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable) {
            // Logger hatasi — sessizce devam et
        }
        json_error($e->getMessage(), $e->getStatusCode());
    } catch (\Throwable $e) {
        try {
            if (class_exists('Logger', false)) {
                \Logger::getInstance()->error('Odeme callback beklenmeyen hata', [
                    'hata' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable) {
            // Logger hatasi — sessizce devam et
        }
        json_error('Odeme dogrulama sirasinda bir hata olustu.', 500);
    }

    json_success([
        'basarili'       => $sonuc['basarili'] ?? false,
        'siparis_id'     => $sonuc['siparis_id'] ?? null,
        'referans'       => $sonuc['referans'] ?? null,
    ], $sonuc['mesaj'] ?? 'Callback islendi.');
});

/**
 * GET /api/v1/menu/odeme/durum/{siparis_id}
 * Odeme durumu sorgulama
 *
 * Oturum dogrulama yapilir.
 * Siparisin bu oturuma ait oldugu kontrol edilir.
 */
$router->get('/api/v1/menu/odeme/durum/{siparis_id}', function ($params) use ($siparisService, $oturumTokenCikar, $oturumDogrula) {
    $siparisId = (int)($params['siparis_id'] ?? 0);
    if ($siparisId < 1) {
        json_error('Gecersiz siparis ID.', 400);
    }

    // Oturum token dogrula
    $oturumToken = $oturumTokenCikar();
    $oturum = $oturumDogrula($oturumToken);

    // Siparis detay
    try {
        $siparis = $siparisService->getSiparisDetay($siparisId);
    } catch (\Pastane\Exceptions\HttpException $e) {
        json_error('Siparis bulunamadi.', 404);
    }

    // Guvenlik: siparis bu oturuma ait mi?
    if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
        json_error('Bu siparisi goruntuleme yetkiniz yok.', 403);
    }

    json_success([
        'odeme_durumu'  => $siparis['odeme_durumu'] ?? 'odenmedi',
        'siparis_durumu' => $siparis['durum'] ?? 'beklemede',
        'tutar'          => (float)($siparis['toplam_tutar'] ?? 0),
    ]);
});
