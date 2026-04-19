<?php
/**
 * QR Menu - Odeme Gateway Callback Handler
 *
 * Odeme gateway'inden (iyzico, test vb.) donen callback'i isler.
 * Basarili veya basarisiz duruma gore sonuc sayfasina yonlendirir.
 *
 * @package Pastane\Menu
 * @since 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// ============================================
// CALLBACK VERILERINI AL
// ============================================

// Gateway'den GET veya POST ile gelen token
$token = $_GET['token'] ?? $_POST['token'] ?? $_GET['islem_id'] ?? $_POST['islem_id'] ?? '';
$siparisId = isset($_GET['siparis']) ? (int)$_GET['siparis'] : (isset($_POST['siparis_id']) ? (int)$_POST['siparis_id'] : 0);

// Token yoksa hata
if (empty($token)) {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata</title></head><body><p role="alert">Gecersiz callback verisi.</p></body></html>';
    exit;
}

// ============================================
// ODEME DOGRULAMA
// ============================================

try {
    $odemeService = odeme_service();

    $sonuc = $odemeService->odemeDogrula($token);

    $referans = $token;
    $sonucSiparisId = $sonuc['siparis_id'] ?? $siparisId;

    if ($sonuc['basarili']) {
        // Basarili — sonuc sayfasina yonlendir
        header('Location: odeme-sonuc.php?ref=' . urlencode($referans) . '&durum=basarili&siparis=' . (int)$sonucSiparisId);
        exit;
    }

    // Basarisiz — sonuc sayfasina yonlendir
    $hataMesaji = $sonuc['mesaj'] ?? 'Odeme basarisiz oldu.';
    header('Location: odeme-sonuc.php?ref=' . urlencode($referans) . '&durum=basarisiz&siparis=' . (int)$sonucSiparisId . '&mesaj=' . urlencode($hataMesaji));
    exit;

} catch (\Pastane\Exceptions\HttpException $e) {
    // Islem bulunamadi vb.
    header('Location: odeme-sonuc.php?durum=basarisiz&mesaj=' . urlencode($e->getMessage()));
    exit;

} catch (\Throwable $e) {
    // Beklenmeyen hata
    error_log('Odeme callback hatasi: ' . $e->getMessage());
    header('Location: odeme-sonuc.php?durum=basarisiz&mesaj=' . urlencode('Beklenmeyen bir hata olustu.'));
    exit;
}
