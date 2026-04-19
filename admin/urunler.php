<?php
/**
 * Ürün Listesi — Admin router
 *
 * Controller: Pastane\Controllers\Admin\UrunController
 * View:       views/admin/urunler/index.php (+ _row.php, _delete-modal.php)
 *
 * Sprint 2'de Controller+View pattern'e geçirildi. Bu dosya artık SADECE
 * bootstrap → auth → permission → POST action dispatch → GET view render.
 * Referans: docs/ADMIN_CONTROLLER_PATTERN.md
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
require_permission('product.view');

$controller = new \Pastane\Controllers\Admin\UrunController();

// POST → action, redirect + exit (view render ETMEZ).
// Dolayısıyla header.php'yi POST'ta include etmiyoruz — gereksiz DB/HTML yükü.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $controller->handle();
    exit;
}

// GET → header + view + footer
require_once __DIR__ . '/includes/header.php';
$controller->handle();
require_once __DIR__ . '/includes/footer.php';
