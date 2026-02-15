<?php
/**
 * Raporlar API
 * Satış raporları için JSON endpoint
 *
 * GÜVENLİK: Bu endpoint kimlik doğrulama gerektirir
 */

header('Content-Type: application/json; charset=utf-8');
// CORS - Sadece izin verilen origin'ler
$allowedOrigins = ['http://localhost', 'https://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../includes/functions.php';

// Kimlik doğrulama kontrolü
session_start();

// JWT sınıfını yükle (API erişimi için)
require_once __DIR__ . '/../includes/JWT.php';

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
            // Token geçersiz - log yap
            if (function_exists('error_log')) {
                error_log("API raporlar.php JWT hatası: " . $e->getMessage());
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
$baslangic = $_GET['baslangic'] ?? date('Y-m-01'); // Varsayılan: ayın başı
// İleri tarihli siparişleri de dahil etmek için varsayılan bitiş: gelecek yılın sonu
$bitis = $_GET['bitis'] ?? date('Y-12-31', strtotime('+1 year')); // Varsayılan: gelecek yılın sonu
$tip = $_GET['tip'] ?? 'tum'; // ozet, gunluk, kategori, musteri, tum

// Tarih formatı kontrolü
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $baslangic) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz tarih formatı (YYYY-MM-DD)'], JSON_UNESCAPED_UNICODE);
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
        'bitis' => $bitis
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası'
    ], JSON_UNESCAPED_UNICODE);
}
