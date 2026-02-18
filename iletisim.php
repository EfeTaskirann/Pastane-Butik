<?php
/**
 * İletişim Formu İşleme (Güvenlik Güçlendirilmiş)
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Güvenli session başlat
secureSessionStart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contact');
}

// Rate limiting — dakikada 3 mesaj
if (!checkRateLimit('contact_form', 3, 60)) {
    setFlash('error', 'Çok fazla mesaj gönderdiniz. Lütfen biraz bekleyin.');
    redirect('index.php#contact');
}

// CSRF token kontrolü — ZORUNLU
$token = $_POST['csrf_token'] ?? '';
if (empty($token) || !validateSecureCSRFToken($token)) {
    setFlash('error', 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.');
    redirect('index.php#contact');
}

// Form verilerini al ve temizle
$name = sanitizeInput(trim($_POST['name'] ?? ''), 'string');
$email = sanitizeInput(trim($_POST['email'] ?? ''), 'email');
$phone = sanitizeInput(trim($_POST['phone'] ?? ''), 'string');
$message = trim($_POST['message'] ?? '');

// Doğrulama
$errors = [];

if (empty($name) || mb_strlen($name) < 2) {
    $errors[] = 'Lütfen geçerli bir ad girin.';
}

if (mb_strlen($name) > 100) {
    $errors[] = 'Ad çok uzun.';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Lütfen geçerli bir e-posta adresi girin.';
}

if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{7,20}$/', $phone)) {
    $errors[] = 'Lütfen geçerli bir telefon numarası girin.';
}

if (empty($message) || mb_strlen($message) < 10) {
    $errors[] = 'Mesajınız en az 10 karakter olmalıdır.';
}

if (mb_strlen($message) > 2000) {
    $errors[] = 'Mesaj çok uzun (maks. 2000 karakter).';
}

// Spam koruması (honeypot)
if (!empty($_POST['website'])) {
    // Bot yakalandı — sessizce yönlendir
    redirect('index.php#contact');
}

// Basit spam kelime kontrolü
$spamWords = ['viagra', 'casino', 'lottery', 'winner', 'click here', 'buy now'];
$lowerMessage = mb_strtolower($message);
foreach ($spamWords as $word) {
    if (strpos($lowerMessage, $word) !== false) {
        setFlash('error', 'Mesajınız spam olarak algılandı.');
        redirect('index.php#contact');
    }
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect('index.php#contact');
}

// Veritabanına kaydet
try {
    $id = db()->insert('mesajlar', [
        'ad' => $name,
        'email' => $email,
        'telefon' => $phone,
        'mesaj' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), // XSS koruması
        'ip_adresi' => getClientIP() // IP kaydet
    ]);

    if ($id) {
        setFlash('success', 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.');
    } else {
        setFlash('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
    }
} catch (Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        setFlash('error', 'Hata: ' . $e->getMessage());
    } else {
        setFlash('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
    }
}

redirect('index.php#contact');
