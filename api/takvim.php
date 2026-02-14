<?php
/**
 * Takvim API - Yoğunluk verilerini döndürür
 * Tek ay veya 12 aylık görünüm destekler
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

require_once __DIR__ . '/../includes/db.php';

// Yoğunluk kategorileri (Yumuşak Pastel Tonlar)
function getYogunlukKategorisi($puan) {
    if ($puan <= 10) return ['durum' => 'bos', 'label' => 'Boş', 'renk' => '#B8D4B8'];
    if ($puan <= 40) return ['durum' => 'uygun', 'label' => 'Uygun', 'renk' => '#B8D4E8'];
    if ($puan <= 80) return ['durum' => 'yogun', 'label' => 'Yoğun', 'renk' => '#F5D4B0'];
    return ['durum' => 'dolu', 'label' => 'Dolu', 'renk' => '#E8C4C4'];
}

// Türkçe ay isimleri
$ayIsimleri = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

$ayKisaIsimleri = [
    1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis',
    5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu',
    9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'
];

// Tek ay için takvim verisi oluştur
function getAyTakvimi($db, $ay, $yil, $ayIsimleri, $ayKisaIsimleri) {
    $ayinIlkGunu = sprintf('%04d-%02d-01', $yil, $ay);
    $ayinSonGunu = date('Y-m-t', strtotime($ayinIlkGunu));
    $ayinGunSayisi = (int)date('t', strtotime($ayinIlkGunu));

    // Her gün için toplam puanları hesapla (teslim edilmemiş ve iptal olmayan siparişler)
    $sql = "SELECT tarih, SUM(COALESCE(puan, 0) * COALESCE(kisi_sayisi, 1)) as toplam_puan
            FROM siparisler
            WHERE tarih >= ? AND tarih <= ? AND durum NOT IN ('teslim_edildi', 'iptal')
            GROUP BY tarih";

    $stmt = $db->query($sql, [$ayinIlkGunu, $ayinSonGunu]);
    $siparisler = $stmt->fetchAll();

    $puanlar = [];
    foreach ($siparisler as $s) {
        $puanlar[$s['tarih']] = (int)$s['toplam_puan'];
    }

    $takvim = [];
    $bugun = date('Y-m-d');

    for ($gun = 1; $gun <= $ayinGunSayisi; $gun++) {
        $tarih = sprintf('%04d-%02d-%02d', $yil, $ay, $gun);
        $puan = isset($puanlar[$tarih]) ? $puanlar[$tarih] : 0;
        $kategori = getYogunlukKategorisi($puan);

        $takvim[] = [
            'tarih' => $tarih,
            'gun' => $gun,
            'gunAdi' => ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'][(int)date('w', strtotime($tarih))],
            'haftaGunu' => (int)date('N', strtotime($tarih)), // 1=Pazartesi, 7=Pazar
            'ay' => $ayKisaIsimleri[$ay],
            'puan' => $puan,
            'durum' => $kategori['durum'],
            'label' => $kategori['label'],
            'renk' => $kategori['renk'],
            'gecmis' => $tarih < $bugun,
            'bugun' => $tarih === $bugun
        ];
    }

    return [
        'ay' => $ay,
        'yil' => $yil,
        'ayIsmi' => $ayIsimleri[$ay],
        'ilkGunHaftaGunu' => (int)date('N', strtotime($ayinIlkGunu)),
        'gunSayisi' => $ayinGunSayisi,
        'takvim' => $takvim
    ];
}

try {
    $db = db();

    // Görünüm tipi: 'ay' (tek ay) veya 'yil' (12 ay)
    $gorunum = isset($_GET['gorunum']) ? $_GET['gorunum'] : 'ay';

    // Ay ve yıl parametreleri (varsayılan: bu ay)
    $ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n');
    $yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');

    // Ay sınırları kontrolü
    if ($ay < 1 || $ay > 12) {
        $ay = (int)date('n');
    }
    if ($yil < 2020 || $yil > 2100) {
        $yil = (int)date('Y');
    }

    if ($gorunum === 'yil') {
        // 12 aylık görünüm - mevcut aydan itibaren 12 ay
        $aylar = [];
        $tempAy = $ay;
        $tempYil = $yil;

        for ($i = 0; $i < 12; $i++) {
            $aylar[] = getAyTakvimi($db, $tempAy, $tempYil, $ayIsimleri, $ayKisaIsimleri);

            $tempAy++;
            if ($tempAy > 12) {
                $tempAy = 1;
                $tempYil++;
            }
        }

        echo json_encode([
            'success' => true,
            'gorunum' => 'yil',
            'baslangicAy' => $ay,
            'baslangicYil' => $yil,
            'aylar' => $aylar
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Tek ay görünümü
        $ayVerisi = getAyTakvimi($db, $ay, $yil, $ayIsimleri, $ayKisaIsimleri);

        echo json_encode(array_merge(
            ['success' => true],
            $ayVerisi
        ), JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası'
    ], JSON_UNESCAPED_UNICODE);
}
