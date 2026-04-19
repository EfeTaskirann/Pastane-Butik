<?php
/**
 * 403 — Yetki Yok (Forbidden)
 *
 * require_permission() helper'ı, kullanıcının gerekli izni olmadığında
 * bu sayfayı include eder. $requiredPermission değişkeni opsiyonel context.
 *
 * Bağımsız olarak da çağrılabilir (örn. manuel erişim):
 *   /admin/403.php → standalone render
 */

// Bağımsız erişim desteği — include edilmediyse bootstrap + auth yükle
if (!defined('PASTANE_LOADED')) {
    require_once __DIR__ . '/../includes/bootstrap.php';
    require_once __DIR__ . '/includes/auth.php';
    // Giriş yapmamışsa login'e yönlendir
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    http_response_code(403);
}

$requiredPermission = $requiredPermission ?? ($_GET['perm'] ?? null);
$nonce = function_exists('getCspNonce') ? getCspNonce() : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Yetki Yok | <?= e(defined('SITE_NAME') ? SITE_NAME : 'Admin') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=3.2">
    <style<?= $nonce ? ' nonce="' . e($nonce) . '"' : '' ?>>
        .forbidden-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: 'Poppins', sans-serif;
        }
        .forbidden-card {
            max-width: 520px;
            text-align: center;
            padding: 3rem 2rem;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, .08);
        }
        .forbidden-code {
            font-size: 6rem;
            font-weight: 600;
            color: #e74c3c;
            line-height: 1;
            margin: 0 0 .5rem;
        }
        .forbidden-title {
            font-size: 1.5rem;
            margin: 0 0 1rem;
            color: #2c3e50;
        }
        .forbidden-desc {
            color: #7f8c8d;
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }
        .forbidden-perm {
            display: inline-block;
            background: #fff5f5;
            color: #c0392b;
            border: 1px solid #fadbd8;
            padding: .3rem .75rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: .9rem;
            margin-bottom: 1.5rem;
        }
        .forbidden-actions {
            display: flex;
            gap: .75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .forbidden-actions a {
            display: inline-block;
            padding: .7rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform .15s ease;
        }
        .forbidden-actions a:hover { transform: translateY(-1px); }
        .btn-primary-403 { background: #3498db; color: #fff; }
        .btn-secondary-403 { background: #ecf0f1; color: #34495e; }
    </style>
</head>
<body>
    <div class="forbidden-wrapper">
        <div class="forbidden-card" role="alert" aria-labelledby="forbidden-title">
            <div class="forbidden-code" aria-hidden="true">403</div>
            <h1 id="forbidden-title" class="forbidden-title">Bu işlem için yetkiniz yok</h1>
            <p class="forbidden-desc">
                İstediğiniz sayfaya erişim için gereken izin hesabınızda tanımlı değil.
                Lütfen sistem yöneticisi ile iletişime geçin.
            </p>
            <?php if ($requiredPermission): ?>
                <div class="forbidden-perm" aria-label="Gereken izin">
                    Gereken izin: <?= e($requiredPermission) ?>
                </div>
            <?php endif; ?>
            <div class="forbidden-actions">
                <a href="dashboard.php" class="btn-primary-403">Panele Dön</a>
                <a href="javascript:history.back()" class="btn-secondary-403">Geri</a>
            </div>
        </div>
    </div>
</body>
</html>
