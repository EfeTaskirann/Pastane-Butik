<?php
/**
 * Raporlar API
 * Satış raporları için JSON endpoint
 *
 * GÜVENLİK: Bu endpoint kimlik doğrulama gerektirir
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';

// CORS — CorsMiddleware kullanılamadığı için basit CORS
$allowedOrigin = env('APP_URL', 'http://localhost/pastane');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Credentials: true');

// Kimlik doğrulama kontrolü
secureSessionStart();

// Admin session kontrolü VEYA JWT token kontrolü
$authenticated = false;

// 1. Admin session kontrolü - admin_id ve admin_user kontrol et (auth.php ile tutarlı)
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_user'])) {
    $authenticated = true;
}

// 2. JWT token kontrolü (API erişimi için)
if (!$authenticated) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        try {
            $payload = JWT::verify($token);
            if ($payload && isset($payload['role']) && $payload['role'] === 'admin') {
                $authenticated = true;
            }
        } catch (Exception $e) {
            try {
                if (class_exists('Logger', false)) {
                    Logger::getInstance()->warning('Raporlar API JWT hatası', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            } catch (Throwable) {
                // Logger da fail ederse sessiz kal
            }
        }
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Bu API\'ye erişim için kimlik doğrulama gereklidir.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tarih parametreleri
$baslangic = $_GET['baslangic'] ?? date('Y-m-01');
$bitis = $_GET['bitis'] ?? date('Y-12-31', strtotime('+1 year'));
$tip = $_GET['tip'] ?? 'tum';

// Tip whitelist validation
$allowedTips = ['tum', 'ozet', 'gunluk', 'kategori', 'encok', 'musteri', 'odeme', 'kanal', 'haftalik'];
if (!in_array($tip, $allowedTips, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz rapor tipi. İzin verilen: ' . implode(', ', $allowedTips),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tarih formatı kontrolü
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $baslangic) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz tarih formatı (YYYY-MM-DD)',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tarih geçerlilik kontrolü
if (strtotime($baslangic) === false || strtotime($bitis) === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz tarih değeri',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($baslangic > $bitis) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Başlangıç tarihi bitiş tarihinden sonra olamaz',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $response = ['success' => true];

    // Özet veriler
    if ($tip === 'tum' || $tip === 'ozet') {
        $ozet = getSatisOzeti($baslangic, $bitis);
        $karsilastirma = getGecenAyKarsilastirma($baslangic, $bitis);

        $response['ozet'] = [
            'toplam_satis' => (float)$ozet['toplam_satis'],
            'siparis_sayisi' => (int)$ozet['siparis_sayisi'],
            'ortalama_sepet' => (float)$ozet['ortalama_sepet'],
            'toplam_kisi' => (int)$ozet['toplam_kisi'],
            'gecen_ay_karsilastirma' => $karsilastirma
        ];
    }

    // Günlük veriler
    if ($tip === 'tum' || $tip === 'gunluk') {
        $response['gunluk'] = getGunlukSatislar($baslangic, $bitis);
    }

    // Kategori dağılımı
    if ($tip === 'tum' || $tip === 'kategori') {
        $response['kategori_dagilimi'] = getKategoriDagilimi($baslangic, $bitis);
    }

    // En çok satanlar
    if ($tip === 'tum' || $tip === 'encok') {
        $response['en_cok_satanlar'] = getEnCokSatanlar($baslangic, $bitis, 5);
    }

    // Müşteri analizi
    if ($tip === 'tum' || $tip === 'musteri') {
        $response['musteri_analizi'] = getMusteriAnalizi($baslangic, $bitis);
    }

    // Ödeme tipi dağılımı
    if ($tip === 'tum' || $tip === 'odeme') {
        $response['odeme_tipi_dagilimi'] = getOdemeTipiDagilimi($baslangic, $bitis);
    }

    // Kanal dağılımı
    if ($tip === 'tum' || $tip === 'kanal') {
        $response['kanal_dagilimi'] = getKanalDagilimi($baslangic, $bitis);
    }

    // Haftalık karşılaştırma
    if ($tip === 'tum' || $tip === 'haftalik') {
        $response['haftalik_karsilastirma'] = getHaftalikKarsilastirma();
    }

    // Dönem bilgisi
    $response['donem'] = [
        'baslangic' => $baslangic,
        'bitis' => $bitis,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log the actual error
    try {
        if (class_exists('Logger', false)) {
            Logger::getInstance()->error('Raporlar API hatası', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'tip' => $tip,
                'baslangic' => $baslangic,
                'bitis' => $bitis,
            ]);
        } else {
            error_log("Raporlar API hatası: " . $e->getMessage());
        }
    } catch (Throwable) {
        error_log("Raporlar API hatası (logger unavailable): " . $e->getMessage());
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Rapor oluşturulurken bir hata oluştu',
    ], JSON_UNESCAPED_UNICODE);
}
