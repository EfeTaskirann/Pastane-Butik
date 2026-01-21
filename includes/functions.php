<?php
/**
 * Yardımcı Fonksiyonlar
 */

require_once __DIR__ . '/db.php';

// XSS Koruması
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Slug oluşturma
function slugify($text) {
    $turkce = ['ş', 'Ş', 'ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'];
    $latin = ['s', 's', 'i', 'i', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'];
    $text = str_replace($turkce, $latin, $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

// Fiyat formatlama
function formatPrice($price) {
    return number_format($price, 2, ',', '.') . ' ₺';
}

// Kategorileri getir
function getCategories() {
    return db()->fetchAll("SELECT * FROM kategoriler ORDER BY sira ASC, isim ASC");
}

// Aktif ürünleri getir
function getProducts($kategori_id = null) {
    $sql = "SELECT u.*, k.isim as kategori_isim, k.slug as kategori_slug
            FROM urunler u
            LEFT JOIN kategoriler k ON u.kategori_id = k.id
            WHERE u.aktif = 1";
    $params = [];

    if ($kategori_id) {
        $sql .= " AND u.kategori_id = :kategori_id";
        $params['kategori_id'] = $kategori_id;
    }

    $sql .= " ORDER BY u.sira ASC, u.created_at DESC";
    return db()->fetchAll($sql, $params);
}

// Tek ürün getir
function getProduct($id) {
    return db()->fetch(
        "SELECT u.*, k.isim as kategori_isim
         FROM urunler u
         LEFT JOIN kategoriler k ON u.kategori_id = k.id
         WHERE u.id = :id",
        ['id' => $id]
    );
}

// WhatsApp linki oluştur
function whatsappLink($message = '') {
    $number = WHATSAPP_NUMBER;
    $encodedMessage = urlencode($message);
    return "https://wa.me/{$number}?text={$encodedMessage}";
}

// Ürün WhatsApp sipariş linki
function productWhatsappLink($product) {
    $message = "Merhaba, '{$product['isim']}' ürünü hakkında bilgi almak istiyorum.";
    return whatsappLink($message);
}

// Görsel yükleme
function uploadImage($file, $folder = 'products') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Dosya seçilmedi'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Geçersiz dosya türü'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Dosya çok büyük (Max: 5MB)'];
    }

    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . $filename;

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Yükleme başarısız'];
}

// Eski görsel silme
function deleteImage($filename) {
    $path = UPLOAD_PATH . $filename;
    if (file_exists($path)) {
        unlink($path);
    }
}

// Okunmamış mesaj sayısı
function getUnreadMessageCount() {
    $result = db()->fetch("SELECT COUNT(*) as count FROM iletisim_mesajlari WHERE okundu = 0");
    return $result['count'];
}

// CSRF Token oluştur
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token doğrula
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash mesaj
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Redirect
function redirect($url) {
    header("Location: " . $url);
    exit;
}
