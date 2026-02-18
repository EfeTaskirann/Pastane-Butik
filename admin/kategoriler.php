<?php
/**
 * Kategori Yönetimi - Professional UI
 * MVC: KategoriService kullanır
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Exceptions\HttpException;

// KategoriService instance
$kategoriService = kategori_service();

// Silme işlemi (POST ile güvenli)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCSRF()) {
        setFlash('error', 'Güvenlik doğrulaması başarısız.');
    } else {
        $id = (int)$_POST['delete_id'];

        try {
            $kategoriService->delete($id);
            setFlash('success', 'Kategori başarıyla silindi.');
        } catch (HttpException $e) {
            setFlash('error', $e->getMessage());
        }
    }
    header('Location: kategoriler.php');
    exit;
}

// Ekleme/Düzenleme POST işlemi (header'dan önce)
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    // CSRF kontrolü
    if (!verifyCSRF()) {
        $errors[] = 'Güvenlik doğrulaması başarısız.';
    } else {
        $isim = trim($_POST['isim'] ?? '');
        $sira = (int)($_POST['sira'] ?? 0);
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

        if (empty($isim)) {
            $errors[] = 'Kategori adı gereklidir.';
        }

        if (empty($errors)) {
            try {
                if ($edit_id) {
                    // Güncelle (service slug'ı otomatik oluşturur)
                    $kategoriService->update($edit_id, [
                        'ad' => $isim,
                        'sira' => $sira
                    ]);
                    setFlash('success', 'Kategori güncellendi.');
                } else {
                    // Ekle (service slug'ı otomatik oluşturur)
                    $kategoriService->create([
                        'ad' => $isim,
                        'sira' => $sira
                    ]);
                    setFlash('success', 'Kategori eklendi.');
                }
                header('Location: kategoriler.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

// Duzenleme icin kategori al
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editCategory = $kategoriService->find((int)$_GET['edit']);
}

// Kategorileri listele (service kullanarak)
$categories = $kategoriService->getAllWithProductCount();
$totalProducts = array_sum(array_column($categories, 'urun_sayisi'));
?>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="8" y1="6" x2="21" y2="6"/>
            <line x1="8" y1="12" x2="21" y2="12"/>
            <line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/>
            <line x1="3" y1="12" x2="3.01" y2="12"/>
            <line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        Kategoriler
    </h2>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div><?= implode('<br>', array_map('e', $errors)) ?></div>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card" style="--stat-color: var(--admin-primary);">
        <div class="stat-icon primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="8" y1="6" x2="21" y2="6"/>
                <line x1="8" y1="12" x2="21" y2="12"/>
                <line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/>
                <line x1="3" y1="12" x2="3.01" y2="12"/>
                <line x1="3" y1="18" x2="3.01" y2="18"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= count($categories) ?></h4>
            <span>Toplam Kategori</span>
        </div>
    </div>
    <div class="stat-card" style="--stat-color: var(--admin-success);">
        <div class="stat-icon success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $totalProducts ?></h4>
            <span>Toplam Ürün</span>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 340px; gap: var(--space-6);">
    <!-- Category List -->
    <div class="card">
        <div class="card-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                Tüm Kategoriler
            </h3>
            <span class="badge badge-neutral"><?= count($categories) ?> kategori</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </div>
                    <h3>Henüz kategori yok</h3>
                    <p>Ürünlerinizi düzenlemek için ilk kategorinizi ekleyin.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Kategori Adı</th>
                                <th>Slug</th>
                                <th style="text-align: center;">Ürün</th>
                                <th style="text-align: center;">Sıra</th>
                                <th style="width: 120px;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td class="cell-muted"><?= $index + 1 ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar avatar-sm avatar-accent">
                                            <?= mb_strtoupper(mb_substr($cat['ad'], 0, 1)) ?>
                                        </div>
                                        <span class="cell-primary"><?= e($cat['ad']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <code><?= e($cat['slug']) ?></code>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($cat['urun_sayisi'] > 0): ?>
                                        <span class="badge badge-success"><?= $cat['urun_sayisi'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-primary"><?= $cat['sira'] ?></span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-ghost btn-icon" data-tooltip="Duzenle" aria-label="<?= e($cat['ad']) ?> kategorisini düzenle">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-ghost btn-icon text-danger" data-tooltip="Sil" aria-label="<?= e($cat['ad']) ?> kategorisini sil" data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['ad']) ?>" onclick="deleteCategory(this.dataset.id, this.dataset.name)">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

    <!-- Category Form -->
    <div class="card" style="height: fit-content; position: sticky; top: calc(var(--space-8) + 60px);">
        <div class="card-header">
            <h3>
                <?php if ($editCategory): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Kategori Düzenle
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Yeni Kategori
                <?php endif; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrfTokenField() ?>
                <?php if ($editCategory): ?>
                    <input type="hidden" name="edit_id" value="<?= $editCategory['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="isim">
                        Kategori Adı <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="isim"
                           name="isim"
                           class="form-control"
                           required
                           placeholder="Örnek: Pastalar"
                           value="<?= e($_POST['isim'] ?? ($editCategory['ad'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="sira">Gösterim Sırası</label>
                    <input type="number"
                           id="sira"
                           name="sira"
                           class="form-control"
                           min="0"
                           placeholder="0"
                           value="<?= e($_POST['sira'] ?? ($editCategory['sira'] ?? '0')) ?>">
                    <span class="form-hint">Küçük numara önce gösterilir (0 = ilk sıra)</span>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full">
                        <?php if ($editCategory): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                                <polyline points="17 21 17 13 7 13 7 21"/>
                                <polyline points="7 3 7 8 15 8"/>
                            </svg>
                            Değişiklikleri Kaydet
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Kategori Ekle
                        <?php endif; ?>
                    </button>

                    <?php if ($editCategory): ?>
                        <a href="kategoriler.php" class="btn btn-secondary w-full">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            İptal
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (Hidden) -->
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
                Kategori Sil
            </h3>
            <button class="modal-close" onclick="closeDeleteModal()" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p style="text-align: center; color: var(--admin-text-secondary); margin: 0;">
                <strong id="deleteCategoryName" style="color: var(--admin-text);"></strong> kategorisini silmek istediğinize emin misiniz?
            </p>
            <p style="text-align: center; font-size: var(--text-sm); color: var(--admin-danger); margin-top: var(--space-3); margin-bottom: 0;">
                Bu işlem geri alınamaz.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">İptal</button>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Sil
            </button>
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
let deleteId = null;

function deleteCategory(id, name) {
    deleteId = id;
    document.getElementById('deleteCategoryName').textContent = name;
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
