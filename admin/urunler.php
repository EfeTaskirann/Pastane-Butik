<?php
/**
 * Urun Listesi - Professional UI
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// POST islemleri (guvenli)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: urunler.php');
        exit;
    }

    // Silme islemi
    if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $product = db()->fetch("SELECT gorsel FROM urunler WHERE id = :id", ['id' => $id]);

        if ($product) {
            // Gorseli sil
            if ($product['gorsel']) {
                deleteImage($product['gorsel']);
            }
            // Urunu sil
            db()->delete('urunler', 'id = :id', ['id' => $id]);
            setFlash('success', 'Urun basariyla silindi.');
        }

        header('Location: urunler.php');
        exit;
    }

    // Durum degistirme
    if (isset($_POST['toggle_id']) && is_numeric($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        $product = db()->fetch("SELECT aktif FROM urunler WHERE id = :id", ['id' => $id]);

        if ($product) {
            $newStatus = $product['aktif'] ? 0 : 1;
            db()->update('urunler', ['aktif' => $newStatus], 'id = :id', ['id' => $id]);
            setFlash('success', 'Urun durumu guncellendi.');
        }

        header('Location: urunler.php');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Urunleri listele
$products = db()->fetchAll(
    "SELECT u.*, k.ad as kategori_ad
     FROM urunler u
     LEFT JOIN kategoriler k ON u.kategori_id = k.id
     ORDER BY u.sira ASC, u.created_at DESC"
);

// Istatistikler
$activeCount = count(array_filter($products, fn($p) => $p['aktif'] == 1));
$inactiveCount = count($products) - $activeCount;
?>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
        </svg>
        Urunler
    </h2>
    <div class="page-header-actions">
        <a href="urun-ekle.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Yeni Urun Ekle
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card" style="--stat-color: var(--admin-primary);">
        <div class="stat-icon primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= count($products) ?></h4>
            <span>Toplam Urun</span>
        </div>
    </div>
    <div class="stat-card" style="--stat-color: var(--admin-success);">
        <div class="stat-icon success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $activeCount ?></h4>
            <span>Aktif Urun</span>
        </div>
    </div>
    <div class="stat-card" style="--stat-color: var(--admin-warning);">
        <div class="stat-icon warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $inactiveCount ?></h4>
            <span>Pasif Urun</span>
        </div>
    </div>
</div>

<!-- Products Card -->
<div class="card">
    <div class="card-header">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
            </svg>
            Urun Listesi
        </h3>
        <span class="badge badge-neutral"><?= count($products) ?> urun</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                    </svg>
                </div>
                <h3>Henuz urun eklenmemis</h3>
                <p>Musterilerinize sunmak istediginiz urunleri ekleyerek baslayin.</p>
                <a href="urun-ekle.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Ilk Urunu Ekle
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;"></th>
                            <th>Urun Adi</th>
                            <th>Kategori</th>
                            <th style="text-align: right;">Fiyat</th>
                            <th style="text-align: center;">Durum</th>
                            <th style="width: 140px;">Islemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['gorsel']): ?>
                                    <img src="../uploads/products/<?= e($product['gorsel']) ?>"
                                         class="product-thumb"
                                         alt="<?= e($product['ad']) ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="product-thumb-placeholder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <span class="cell-primary"><?= e($product['ad']) ?></span>
                                    <?php if ($product['aciklama']): ?>
                                        <div class="cell-muted truncate" style="max-width: 250px;">
                                            <?= e(mb_substr($product['aciklama'], 0, 60)) ?><?= mb_strlen($product['aciklama']) > 60 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($product['kategori_ad']): ?>
                                    <span class="badge badge-primary"><?= e($product['kategori_ad']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Kategorisiz</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <span class="cell-primary"><?= formatPrice($product['fiyat']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="status <?= $product['aktif'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="urun-duzenle.php?id=<?= $product['id'] ?>"
                                       class="btn btn-sm btn-ghost btn-icon"
                                       data-tooltip="Duzenle">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-ghost btn-icon"
                                            data-tooltip="<?= $product['aktif'] ? 'Pasif Yap' : 'Aktif Yap' ?>"
                                            onclick="toggleProduct(<?= $product['id'] ?>)">
                                        <?php if ($product['aktif']): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--admin-warning);">
                                                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                                                <line x1="1" y1="1" x2="23" y2="23"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--admin-success);">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-ghost btn-icon"
                                            data-tooltip="Sil"
                                            data-id="<?= $product['id'] ?>"
                                            data-name="<?= e($product['ad']) ?>"
                                            onclick="deleteProduct(this.dataset.id, this.dataset.name)">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--admin-danger);">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                        </svg>
                                    </button>
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

<!-- Hidden Forms -->
<form id="toggleForm" method="POST" style="display: none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="toggle_id" id="toggleId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <?= csrfTokenField() ?>
    <input type="hidden" name="delete_id" id="deleteId">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--admin-danger);">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Urun Sil
            </h3>
            <button class="modal-close" onclick="closeDeleteModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="text-align: center; color: var(--admin-text-secondary); margin: 0;">
                <strong id="deleteProductName" style="color: var(--admin-text);"></strong> urununu silmek istediginize emin misiniz?
            </p>
            <p style="text-align: center; font-size: var(--text-sm); color: var(--admin-danger); margin-top: var(--space-3); margin-bottom: 0;">
                Bu islem geri alinamaz ve urun gorseli de silinecektir.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Iptal</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Urunu Sil
            </button>
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
let deleteId = null;

function toggleProduct(id) {
    document.getElementById('toggleId').value = id;
    document.getElementById('toggleForm').submit();
}

function deleteProduct(id, name) {
    deleteId = id;
    document.getElementById('deleteProductName').textContent = name;
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
    deleteId = null;
}

function confirmDelete() {
    if (deleteId) {
        document.getElementById('deleteId').value = deleteId;
        document.getElementById('deleteForm').submit();
    }
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDeleteModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
