<?php
/**
 * Iletisim Formu Isleme (Guvenlik Guclendirilmis)
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Guvenli session baslat
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contact');
}

// Rate limiting - dakikada 3 mesaj
if (!checkRateLimit('contact_form', 3, 60)) {
    setFlash('error', 'Cok fazla mesaj gonderdiniz. Lutfen biraz bekleyin.');
    redirect('index.php#contact');
}

// CSRF token kontrolu - ZORUNLU (Codex + Antigravity analizi)
$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !validateSecureCSRFToken($token)) {
    setFlash('error', 'Guvenlik dogrulamasi basarisiz. Lutfen sayfayi yenileyip tekrar deneyin.');
    redirect('index.php#contact');
}

// Form verilerini al ve temizle
$name = sanitizeInput(trim($_POST['name'] ?? ''), 'string');
$email = sanitizeInput(trim($_POST['email'] ?? ''), 'email');
$phone = sanitizeInput(trim($_POST['phone'] ?? ''), 'string');
$message = trim($_POST['message'] ?? '');

// Dogrulama
$errors = [];

if (empty($name) || mb_strlen($name) < 2) {
    $errors[] = 'Lutfen gecerli bir ad girin.';
}

if (mb_strlen($name) > 100) {
    $errors[] = 'Ad cok uzun.';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Lutfen gecerli bir e-posta adresi girin.';
}

if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $phone)) {
    $errors[] = 'Lutfen gecerli bir telefon numarasi girin.';
}

if (empty($message) || mb_strlen($message) < 10) {
    $errors[] = 'Mesajiniz en az 10 karakter olmalidir.';
}

if (mb_strlen($message) > 2000) {
    $errors[] = 'Mesaj cok uzun (max 2000 karakter).';
}

// Spam korumasi (honeypot)
if (isset($_POST['website']) && !empty($_POST['website'])) {
    // Bot yakalandi - sessizce yonlendir
    redirect('index.php#contact');
}

// Basit spam kelime kontrolu
$spamWords = ['viagra', 'casino', 'lottery', 'winner', 'click here', 'buy now'];
$lowerMessage = mb_strtolower($message);
foreach ($spamWords as $word) {
    if (strpos($lowerMessage, $word) !== false) {
        setFlash('error', 'Mesajiniz spam olarak algilandi.');
        redirect('index.php#contact');
    }
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect('index.php#contact');
}

// Veritabanina kaydet
try {
    $id = db()->insert('mesajlar', [
        'ad' => $name,
        'email' => $email,
        'telefon' => $phone,
        'mesaj' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), // XSS korumasi
        'ip_adresi' => getClientIP() // IP kaydet
    ]);

    if ($id) {
        setFlash('success', 'Mesajiniz basariyla gonderildi. En kisa surede size donus yapacagiz.');
    } else {
        setFlash('error', 'Bir hata olustu. Lutfen tekrar deneyin.');
    }
} catch (Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        setFlash('error', 'Hata: ' . $e->getMessage());
    } else {
        setFlash('error', 'Bir hata olustu. Lutfen tekrar deneyin.');
    }
}

redirect('index.php#contact');
