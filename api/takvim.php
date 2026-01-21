<?php
/**
 * Takvim API - Yoğunluk verilerini döndürür
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../includes/db.php';

// Yoğunluk kategorileri (Yumuşak Pastel Tonlar)
function getYogunlukKategorisi($puan) {
    if ($puan <= 10) return ['durum' => 'bos', 'label' => 'Boş', 'renk' => '#B8D4B8'];
    if ($puan <= 40) return ['durum' => 'uygun', 'label' => 'Uygun', 'renk' => '#B8D4E8'];
    if ($puan <= 80) return ['durum' => 'yogun', 'label' => 'Yoğun', 'renk' => '#F5D4B0'];
    return ['durum' => 'dolu', 'label' => 'Dolu', 'renk' => '#E8C4C4'];
}

try {
    $db = db();

    // 30 günlük veri al
    $bugun = date('Y-m-d');
    $bitisTarihi = date('Y-m-d', strtotime('+30 days'));

    // Her gün için toplam puanları hesapla
    $sql = "SELECT tarih, SUM(puan * adet) as toplam_puan
            FROM siparisler
            WHERE tarih >= ? AND tarih <= ?
            GROUP BY tarih";

    $stmt = $db->query($sql, [$bugun, $bitisTarihi]);
    $siparisler = $stmt->fetchAll();

    // Tarihleri indexle
    $puanlar = [];
    foreach ($siparisler as $s) {
        $puanlar[$s['tarih']] = (int)$s['toplam_puan'];
    }

    // 30 günlük takvim oluştur
    $takvim = [];
    for ($i = 0; $i < 30; $i++) {
        $tarih = date('Y-m-d', strtotime("+$i days"));
        $puan = isset($puanlar[$tarih]) ? $puanlar[$tarih] : 0;
        $kategori = getYogunlukKategorisi($puan);

        $takvim[] = [
            'tarih' => $tarih,
            'gun' => date('d', strtotime($tarih)),
            'gunAdi' => ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'][date('w', strtotime($tarih))],
            'ay' => ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'][date('n', strtotime($tarih))],
            'puan' => $puan,
            'durum' => $kategori['durum'],
            'label' => $kategori['label'],
            'renk' => $kategori['renk']
        ];
    }

    echo json_encode([
        'success' => true,
        'takvim' => $takvim
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası'
    ], JSON_UNESCAPED_UNICODE);
}
