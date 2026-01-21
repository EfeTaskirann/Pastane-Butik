<?php
/**
 * İletişim Formu İşleme
 */

session_start();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#contact');
}

// Form verilerini al ve temizle
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

// Doğrulama
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Lütfen geçerli bir ad girin.';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Lütfen geçerli bir e-posta adresi girin.';
}

if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Mesajınız en az 10 karakter olmalıdır.';
}

// Spam koruması (basit)
if (isset($_POST['website']) && !empty($_POST['website'])) {
    // Honeypot - bot yakalandı
    redirect('index.php#contact');
}

if (!empty($errors)) {
    setFlash('error', implode('<br>', $errors));
    redirect('index.php#contact');
}

// Veritabanına kaydet
try {
    $id = db()->insert('iletisim_mesajlari', [
        'isim' => $name,
        'email' => $email,
        'telefon' => $phone,
        'mesaj' => $message
    ]);

    if ($id) {
        // E-posta gönderme (opsiyonel)
        // sendNotificationEmail($name, $email, $phone, $message);

        setFlash('success', 'Mesajınız başarıyla gönderildi. En kısa sürede size dönüş yapacağız.');
    } else {
        setFlash('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
    }
} catch (Exception $e) {
    if (DEBUG_MODE) {
        setFlash('error', 'Hata: ' . $e->getMessage());
    } else {
        setFlash('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
    }
}

redirect('index.php#contact');
