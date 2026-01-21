<?php
/**
 * Ürün Listesi
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Silme işlemi (header'dan önce yapılmalı)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $product = db()->fetch("SELECT gorsel FROM urunler WHERE id = :id", ['id' => $id]);

    if ($product) {
        // Görseli sil
        if ($product['gorsel']) {
            deleteImage($product['gorsel']);
        }
        // Ürünü sil
        db()->delete('urunler', 'id = :id', ['id' => $id]);
        setFlash('success', 'Ürün başarıyla silindi.');
    }

    header('Location: urunler.php');
    exit;
}

// Durum değiştirme (header'dan önce yapılmalı)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $product = db()->fetch("SELECT aktif FROM urunler WHERE id = :id", ['id' => $id]);

    if ($product) {
        $newStatus = $product['aktif'] ? 0 : 1;
        db()->update('urunler', ['aktif' => $newStatus], 'id = :id', ['id' => $id]);
        setFlash('success', 'Ürün durumu güncellendi.');
    }

    header('Location: urunler.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Ürünleri listele
$products = db()->fetchAll(
    "SELECT u.*, k.isim as kategori_isim
     FROM urunler u
     LEFT JOIN kategoriler k ON u.kategori_id = k.id
     ORDER BY u.sira ASC, u.created_at DESC"
);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2>Ürünler</h2>
    <a href="urun-ekle.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Yeni Ürün Ekle
    </a>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                </svg>
                <p>Henüz ürün eklenmemiş.</p>
                <a href="urun-ekle.php" class="btn btn-primary" style="margin-top: 1rem;">İlk Ürünü Ekle</a>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th style="width: 150px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['gorsel']): ?>
                                    <img src="../uploads/products/<?= e($product['gorsel']) ?>" class="product-thumb" alt="">
                                <?php else: ?>
                                    <div class="product-thumb" style="background: #F5E1E9; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B6F5C" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($product['isim']) ?></strong>
                            </td>
                            <td>
                                <?= e($product['kategori_isim'] ?? 'Kategorisiz') ?>
                            </td>
                            <td><?= formatPrice($product['fiyat']) ?></td>
                            <td>
                                <span class="status <?= $product['aktif'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="urun-duzenle.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Düzenle">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    <a href="?toggle=<?= $product['id'] ?>" class="btn btn-sm <?= $product['aktif'] ? 'btn-secondary' : 'btn-success' ?> btn-icon" title="<?= $product['aktif'] ? 'Pasif Yap' : 'Aktif Yap' ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <?php if ($product['aktif']): ?>
                                                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                                                <line x1="1" y1="1" x2="23" y2="23"/>
                                            <?php else: ?>
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            <?php endif; ?>
                                        </svg>
                                    </a>
                                    <a href="?delete=<?= $product['id'] ?>" class="btn btn-sm btn-danger btn-icon" title="Sil" onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
