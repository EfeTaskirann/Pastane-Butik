<?php
/**
 * Admin Giriş Sayfası
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Çıkış kontrolü
if (isset($_GET['logout'])) {
    logout();
}

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        if (login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Geçersiz kullanıcı adı veya şifre.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Giriş - <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <h1><?= e(SITE_NAME) ?></h1>
            <p>Admin Paneline Giriş</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Kullanıcı Adı</label>
                    <input type="text" id="username" name="username" class="form-control" required
                           value="<?= e($_POST['username'] ?? '') ?>" autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Giriş Yap
                </button>
            </form>

            <p style="margin-top: 1.5rem; font-size: 0.8rem;">
                <a href="../index.php" style="color: var(--admin-primary);">&larr; Siteye Dön</a>
            </p>
        </div>
    </div>
</body>
</html>
