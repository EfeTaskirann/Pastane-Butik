<?php

declare(strict_types=1);

/**
 * admin/rapor-export.php (P2-17)
 *
 * Rapor verilerini CSV, XLSX (SpreadsheetML fallback), JSON formatında export eder.
 *
 * GET/POST parametreleri:
 *   format   — csv | xlsx | json (default csv)
 *   filtre   — bugun | hafta | ay | ozel (raporlar.php ile aynı)
 *   baslangic, bitis — tarih aralığı (YYYY-MM-DD)
 *   sekme    — genel | urun | saat | ... (opsiyonel)
 *
 * GÜVENLİK:
 *   - `require_login()` ile admin oturumu zorunlu
 *   - CSRF token GET ile değil session sahipliğiyle kontrol (download akışı)
 *   - Dosya adı PHP validation ile sanitize
 */

// Download endpoint — HTML çıktı yapma (header.php INCLUDE ETME!).
// Bootstrap + auth doğrudan yükle, ardından binary/text response.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Services\ExportService;

$format = strtolower($_GET['format'] ?? 'csv');
if (!in_array($format, ['csv', 'xlsx', 'json'], true)) {
    http_response_code(400);
    echo 'Invalid format';
    exit;
}

$filtre = $_GET['filtre'] ?? 'bugun';
$baslangic = $_GET['baslangic'] ?? date('Y-m-01');
$bitis = $_GET['bitis'] ?? date('Y-m-d');

// Tarih formatı validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $baslangic) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) {
    http_response_code(400);
    echo 'Invalid date format';
    exit;
}

// Rapor servisi (var ise)
$rows = [];
$columns = [];

try {
    if (class_exists('\\Pastane\\Services\\RaporService')) {
        $raporService = new \Pastane\Services\RaporService();
        // Method'ları best-effort call et — raporService'de bu isimle varsa çalışır
        if (method_exists($raporService, 'getSiparisRaporu')) {
            $rows = $raporService->getSiparisRaporu($baslangic, $bitis, $filtre);
        } elseif (method_exists($raporService, 'getRapor')) {
            $rows = $raporService->getRapor($baslangic, $bitis);
        }
    }

    // Fallback: doğrudan SiparisRepository ile çek
    if (empty($rows) && class_exists('\\Pastane\\Repositories\\SiparisRepository')) {
        $repo = new \Pastane\Repositories\SiparisRepository();
        if (method_exists($repo, 'getRaporData')) {
            $rows = $repo->getRaporData($baslangic, $bitis);
        } else {
            // Son çare — find-all yerine sorgu
            $db = \Database::getInstance();
            $rows = $db->query(
                "SELECT id, musteri_adi, telefon, adres, tarih, saat, tutar, tamamlandi, created_at
                 FROM siparisler
                 WHERE DATE(created_at) BETWEEN :b AND :s
                 ORDER BY created_at DESC",
                ['b' => $baslangic, 's' => $bitis]
            );
        }
    }
} catch (\Throwable $e) {
    error_log('rapor-export error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Export error';
    exit;
}

// Kolonlar: ilk row'dan türet veya standart
$columns = ['id', 'musteri_adi', 'telefon', 'adres', 'tarih', 'saat', 'tutar', 'tamamlandi', 'created_at'];
if (!empty($rows)) {
    $first = reset($rows);
    if (is_array($first)) {
        // User-friendly kolonları maintain et ama varlığını kontrol et
        $columns = array_values(array_intersect($columns, array_keys($first)));
        // Eğer intersect boşsa (farklı şema) → first row'un key'lerini kullan
        if (empty($columns)) {
            $columns = array_keys($first);
        }
    }
}

$export = new ExportService();
$stamp = date('Ymd_His');

switch ($format) {
    case 'csv':
        $content = $export->toCsv($rows, $columns);
        $filename = "siparisler_{$baslangic}_{$bitis}_{$stamp}.csv";
        $export->sendHeaders('csv', $filename);
        echo $content;
        break;

    case 'xlsx':
        $content = $export->toXlsx($rows, $columns, ['title' => 'Siparisler']);
        // PhpSpreadsheet varsa .xlsx, yoksa .xml uzantısı (SpreadsheetML)
        $ext = class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? 'xlsx' : 'xml';
        $filename = "siparisler_{$baslangic}_{$bitis}_{$stamp}.{$ext}";
        $export->sendHeaders($ext, $filename);
        echo $content;
        break;

    case 'json':
        $content = $export->toJson($rows);
        $filename = "siparisler_{$baslangic}_{$bitis}_{$stamp}.json";
        $export->sendHeaders('json', $filename);
        echo $content;
        break;
}

exit;
