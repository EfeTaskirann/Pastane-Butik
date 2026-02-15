<?php
/**
 * Admin Giris Sayfasi (Guvenlik Guclendirilmis)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Cikis kontrolu
if (isset($_GET['logout'])) {
    logout();
}

// Zaten giris yapmissa dashboard'a yonlendir
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$locked = false;

// Form gonderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting kontrolu
    if (!checkRateLimit('login', 10, 60)) {
        $error = 'Cok fazla istek. Lutfen biraz bekleyin.';
    } else {
        // CSRF kontrolu
        if (!verifyCSRF()) {
            $error = 'Guvenlik dogrulamasi basarisiz. Lutfen sayfayi yenileyip tekrar deneyin.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = 'Kullanici adi ve sifre gereklidir.';
            } else {
                $result = login($username, $password);

                if ($result['success']) {
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = $result['error'];
                    $locked = $result['locked'] ?? false;
                }
            }
        }
    }
}

// Hesap kilitli mi kontrol et (sayfa yuklendiginde)
$username = trim($_POST['username'] ?? '');
if (!empty($username) && isAccountLocked($username)) {
    $remaining = getRemainingLockoutTime($username);
    $minutes = ceil($remaining / 60);
    $error = "Hesap gecici olarak kilitlendi. {$minutes} dakika sonra tekrar deneyin.";
    $locked = true;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Giris - <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .form-group.disabled input {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .security-notice {
            font-size: 0.75rem;
            color: #888;
            margin-top: 1rem;
            text-align: center;
        }
        .security-notice svg {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <h1><?= e(SITE_NAME) ?></h1>
            <p>Admin Paneline Giris</p>

            <?php if ($error): ?>
                <div class="alert <?= $locked ? 'alert-warning' : 'alert-error' ?>">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" <?= $locked ? 'class="disabled-form"' : '' ?>>
                <?= csrfTokenField() ?>

                <div class="form-group <?= $locked ? 'disabled' : '' ?>">
                    <label for="username">Kullanici Adi</label>
                    <input type="text" id="username" name="username" class="form-control"
                           required autocomplete="username"
                           value="<?= e($_POST['username'] ?? '') ?>"
                           <?= $locked ? 'disabled' : 'autofocus' ?>>
                </div>

                <div class="form-group <?= $locked ? 'disabled' : '' ?>">
                    <label for="password">Sifre</label>
                    <input type="password" id="password" name="password" class="form-control"
                           required autocomplete="current-password"
                           <?= $locked ? 'disabled' : '' ?>>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;"
                        <?= $locked ? 'disabled' : '' ?>>
                    <?= $locked ? 'Hesap Kilitli' : 'Giris Yap' ?>
                </button>
            </form>

            <div class="security-notice">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Guvenli baglanti
            </div>

            <p style="margin-top: 1rem; font-size: 0.8rem;">
                <a href="../index.php" style="color: var(--admin-primary);">&larr; Siteye Don</a>
            </p>
        </div>
    </div>

    <script>
        // Kilitli ise geri sayim goster
        <?php if ($locked && isset($remaining) && $remaining > 0): ?>
        (function() {
            let remaining = <?= $remaining ?>;
            const btn = document.querySelector('button[type="submit"]');

            function updateTimer() {
                if (remaining <= 0) {
                    location.reload();
                    return;
                }

                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                btn.textContent = `Kilitli (${minutes}:${seconds.toString().padStart(2, '0')})`;
                remaining--;
                setTimeout(updateTimer, 1000);
            }

            updateTimer();
        })();
        <?php endif; ?>
    </script>
</body>
</html>
