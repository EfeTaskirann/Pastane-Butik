<?php
/**
 * Kategori Yönetimi
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Silme işlemi (header'dan önce)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Kategoriye ait ürün var mı kontrol et
    $productCount = db()->fetch("SELECT COUNT(*) as count FROM urunler WHERE kategori_id = :id", ['id' => $id])['count'];

    if ($productCount > 0) {
        setFlash('error', 'Bu kategoriye ait ' . $productCount . ' ürün var. Önce ürünleri başka kategoriye taşıyın veya silin.');
    } else {
        db()->delete('kategoriler', 'id = :id', ['id' => $id]);
        setFlash('success', 'Kategori başarıyla silindi.');
    }

    header('Location: kategoriler.php');
    exit;
}

// Ekleme/Düzenleme POST işlemi (header'dan önce)
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isim = trim($_POST['isim'] ?? '');
    $sira = (int)($_POST['sira'] ?? 0);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if (empty($isim)) {
        $errors[] = 'Kategori adı gereklidir.';
    }

    if (empty($errors)) {
        $slug = slugify($isim);

        // Slug benzersizliği kontrolü
        $existing = db()->fetch(
            "SELECT id FROM kategoriler WHERE slug = :slug AND id != :id",
            ['slug' => $slug, 'id' => $edit_id]
        );

        if ($existing) {
            $slug .= '-' . time();
        }

        try {
            if ($edit_id) {
                // Güncelle
                db()->update('kategoriler', [
                    'isim' => $isim,
                    'slug' => $slug,
                    'sira' => $sira
                ], 'id = :id', ['id' => $edit_id]);
                setFlash('success', 'Kategori güncellendi.');
            } else {
                // Ekle
                db()->insert('kategoriler', [
                    'isim' => $isim,
                    'slug' => $slug,
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

require_once __DIR__ . '/includes/header.php';

// Düzenleme için kategori al
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editCategory = db()->fetch("SELECT * FROM kategoriler WHERE id = :id", ['id' => (int)$_GET['edit']]);
}

// Kategorileri listele
$categories = db()->fetchAll(
    "SELECT k.*, COUNT(u.id) as urun_sayisi
     FROM kategoriler k
     LEFT JOIN urunler u ON k.id = u.kategori_id
     GROUP BY k.id
     ORDER BY k.sira ASC, k.isim ASC"
);
?>

<h2 style="margin-bottom: 1.5rem;">Kategoriler</h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?= implode('<br>', array_map('e', $errors)) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem;">
    <!-- Kategori Listesi -->
    <div class="card">
        <div class="card-header">
            <h3>Tüm Kategoriler</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    <p>Henüz kategori eklenmemiş.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Kategori Adı</th>
                            <th>Slug</th>
                            <th>Ürün Sayısı</th>
                            <th>Sıra</th>
                            <th style="width: 100px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><strong><?= e($cat['isim']) ?></strong></td>
                            <td><code style="background: #F5E1E9; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;"><?= e($cat['slug']) ?></code></td>
                            <td><?= $cat['urun_sayisi'] ?></td>
                            <td><?= $cat['sira'] ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Düzenle">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </a>
                                    <a href="?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-danger btn-icon" title="Sil" onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Kategori Formu -->
    <div class="card" style="height: fit-content;">
        <div class="card-header">
            <h3><?= $editCategory ? 'Kategori Düzenle' : 'Yeni Kategori' ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="edit_id" value="<?= $editCategory['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="isim">Kategori Adı *</label>
                    <input type="text" id="isim" name="isim" class="form-control" required
                           value="<?= e($_POST['isim'] ?? ($editCategory['isim'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="sira">Sıra</label>
                    <input type="number" id="sira" name="sira" class="form-control" min="0"
                           value="<?= e($_POST['sira'] ?? ($editCategory['sira'] ?? '0')) ?>">
                    <small style="color: var(--admin-text-light);">Küçük numara önce gösterilir</small>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <?= $editCategory ? 'Güncelle' : 'Ekle' ?>
                </button>

                <?php if ($editCategory): ?>
                    <a href="kategoriler.php" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;">İptal</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
