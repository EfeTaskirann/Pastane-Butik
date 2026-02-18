<?php
/**
 * Ürün Düzenleme
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ID kontrolü (header'dan önce)
if (!$id) {
    header('Location: urunler.php');
    exit;
}

$product = db()->fetch("SELECT * FROM urunler WHERE id = :id", ['id' => $id]);

// Ürün bulunamadı kontrolü (header'dan önce)
if (!$product) {
    setFlash('error', 'Ürün bulunamadı.');
    header('Location: urunler.php');
    exit;
}

$categories = getCategories();
$errors = [];

// POST islemi (header'dan once)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF kontrolu
    if (!verifyCSRF()) {
        $errors[] = 'Guvenlik dogrulamasi basarisiz. Lutfen tekrar deneyin.';
    } else {
        $isim = trim($_POST['isim'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        $fiyat = floatval($_POST['fiyat'] ?? 0);
        $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $sira = (int)($_POST['sira'] ?? 0);

        // Porsiyon fiyatlari
        $fiyat_4kisi = !empty($_POST['fiyat_4kisi']) ? floatval($_POST['fiyat_4kisi']) : null;
        $fiyat_6kisi = !empty($_POST['fiyat_6kisi']) ? floatval($_POST['fiyat_6kisi']) : null;
        $fiyat_8kisi = !empty($_POST['fiyat_8kisi']) ? floatval($_POST['fiyat_8kisi']) : null;
        $fiyat_10kisi = !empty($_POST['fiyat_10kisi']) ? floatval($_POST['fiyat_10kisi']) : null;

        // Dogrulama
        if (empty($isim)) {
            $errors[] = 'Urun adi gereklidir.';
        }

        if ($fiyat < 0) {
            $errors[] = 'Fiyat 0 veya daha buyuk olmalidir.';
        }

        // Gorsel yukleme
        $gorsel = $product['gorsel'];

        // Gorsel silme
        if (isset($_POST['delete_image']) && $product['gorsel']) {
            deleteImage($product['gorsel']);
            $gorsel = null;
        }

        // Yeni gorsel yukleme (guvenli)
        if (!empty($_FILES['gorsel']['name'])) {
            $upload = secureUploadImage($_FILES['gorsel']);
            if ($upload['success']) {
                // Eski gorseli sil
                if ($product['gorsel']) {
                    deleteImage($product['gorsel']);
                }
                $gorsel = $upload['filename'];
            } else {
                $errors[] = $upload['error'];
            }
        }

        if (empty($errors)) {
            try {
                db()->update('urunler', [
                    'ad' => $isim,
                    'aciklama' => $aciklama,
                    'fiyat' => $fiyat,
                    'fiyat_4kisi' => $fiyat_4kisi,
                    'fiyat_6kisi' => $fiyat_6kisi,
                    'fiyat_8kisi' => $fiyat_8kisi,
                    'fiyat_10kisi' => $fiyat_10kisi,
                    'kategori_id' => $kategori_id,
                    'gorsel' => $gorsel,
                    'aktif' => $aktif,
                    'sira' => $sira
                ], 'id = :id', ['id' => $id]);

                setFlash('success', 'Urun basariyla guncellendi.');
                header('Location: urunler.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Bir hata olustu: ' . $e->getMessage();
            }
        }
    } // CSRF else kapanisi
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2>Ürünü Düzenle</h2>
    <a href="urunler.php" class="btn btn-secondary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Geri
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?= implode('<br>', array_map('e', $errors)) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfTokenField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="isim">Urun Adi *</label>
                    <input type="text" id="isim" name="isim" class="form-control" required
                           value="<?= e($_POST['isim'] ?? $product['ad']) ?>">
                </div>

                <div class="form-group">
                    <label for="kategori_id">Kategori</label>
                    <select id="kategori_id" name="kategori_id" class="form-control">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['kategori_id'] ?? $product['kategori_id']) == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['ad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-control"><?= e($_POST['aciklama'] ?? $product['aciklama']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fiyat">Temel Fiyat (₺)</label>
                    <input type="number" id="fiyat" name="fiyat" class="form-control" step="0.01" min="0"
                           value="<?= e($_POST['fiyat'] ?? $product['fiyat']) ?>">
                    <small style="color: var(--admin-text-light);">Porsiyon seçeneği olmayan ürünler için</small>
                </div>

                <div class="form-group">
                    <label for="sira">Sıra</label>
                    <input type="number" id="sira" name="sira" class="form-control" min="0"
                           value="<?= e($_POST['sira'] ?? $product['sira']) ?>">
                </div>
            </div>

            <!-- Porsiyon Fiyatları (Pasta için) -->
            <div class="form-group" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="vertical-align: middle; margin-right: 0.3rem;">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                        <path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Porsiyon Fiyatları (Pasta kategorisi için)
                </label>
                <small style="color: var(--admin-text-light); display: block; margin-bottom: 1rem;">Sadece pasta gibi porsiyon seçenekli ürünler için doldurun. Boş bırakılan alanlar gösterilmez.</small>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="fiyat_4kisi">4 Kişilik (₺)</label>
                        <input type="number" id="fiyat_4kisi" name="fiyat_4kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_4kisi'] ?? $product['fiyat_4kisi'] ?? '') ?>" placeholder="örn: 350">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="fiyat_6kisi">6 Kişilik (₺)</label>
                        <input type="number" id="fiyat_6kisi" name="fiyat_6kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_6kisi'] ?? $product['fiyat_6kisi'] ?? '') ?>" placeholder="örn: 450">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="fiyat_8kisi">8 Kişilik (₺)</label>
                        <input type="number" id="fiyat_8kisi" name="fiyat_8kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_8kisi'] ?? $product['fiyat_8kisi'] ?? '') ?>" placeholder="örn: 550">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="fiyat_10kisi">10+ Kişilik (₺)</label>
                        <input type="number" id="fiyat_10kisi" name="fiyat_10kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_10kisi'] ?? $product['fiyat_10kisi'] ?? '') ?>" placeholder="örn: 650">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="gorsel">Ürün Görseli</label>

                <?php if ($product['gorsel']): ?>
                    <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem;">
                        <img src="../uploads/products/<?= e($product['gorsel']) ?>" style="max-width: 150px; border-radius: 8px;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--admin-danger);">
                            <input type="checkbox" name="delete_image" value="1">
                            Görseli sil
                        </label>
                    </div>
                <?php endif; ?>

                <div class="file-upload" onclick="document.getElementById('gorsel').click()">
                    <input type="file" id="gorsel" name="gorsel" accept="image/*">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p>Yeni görsel yüklemek için tıklayın<br><small>JPG, PNG, WebP - Max 5MB</small></p>
                </div>
                <img id="preview" class="file-preview" style="display: none;">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="aktif" value="1" <?= ($_POST['aktif'] ?? $product['aktif']) ? 'checked' : '' ?>>
                    Ürün aktif (sitede görünür)
                </label>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                <a href="urunler.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
document.getElementById('gorsel').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
