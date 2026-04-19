<?php
/**
 * Ürün Ekleme
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

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

        // Porsiyon fiyatlari (pasta kategorisi icin)
        $fiyat_4kisi = !empty($_POST['fiyat_4kisi']) ? floatval($_POST['fiyat_4kisi']) : null;
        $fiyat_6kisi = !empty($_POST['fiyat_6kisi']) ? floatval($_POST['fiyat_6kisi']) : null;
        $fiyat_8kisi = !empty($_POST['fiyat_8kisi']) ? floatval($_POST['fiyat_8kisi']) : null;
        $fiyat_10kisi = !empty($_POST['fiyat_10kisi']) ? floatval($_POST['fiyat_10kisi']) : null;

        // QR menu ayarlari
        $cafe_menusu = isset($_POST['cafe_menusu']) ? 1 : 0;
        $hazirlanma_suresi = !empty($_POST['hazirlanma_suresi']) ? (int)$_POST['hazirlanma_suresi'] : null;
        $stok_durumu = in_array($_POST['stok_durumu'] ?? '', ['var','tukendi','sinirli'], true)
            ? $_POST['stok_durumu'] : 'var';

        // Dogrulama
        if (empty($isim)) {
            $errors[] = 'Urun adi gereklidir.';
        }

        if ($fiyat < 0) {
            $errors[] = 'Fiyat 0 veya daha buyuk olmalidir.';
        }

        // Gorsel yukleme (guvenli)
        $gorsel = null;
        if (!empty($_FILES['gorsel']['name'])) {
            $upload = secureUploadImage($_FILES['gorsel']);
            if ($upload['success']) {
                $gorsel = $upload['filename'];
            } else {
                $errors[] = $upload['error'];
            }
        }

    if (empty($errors)) {
        try {
            $urunService = urun_service();
            $urunService->create([
                'isim' => $isim,
                'aciklama' => $aciklama,
                'fiyat' => $fiyat,
                'fiyat_4kisi' => $fiyat_4kisi,
                'fiyat_6kisi' => $fiyat_6kisi,
                'fiyat_8kisi' => $fiyat_8kisi,
                'fiyat_10kisi' => $fiyat_10kisi,
                'kategori_id' => $kategori_id,
                'gorsel' => $gorsel,
                'aktif' => $aktif,
                'cafe_menusu' => $cafe_menusu,
                'hazirlanma_suresi' => $hazirlanma_suresi,
                'stok_durumu' => $stok_durumu,
                'sira' => $sira,
            ]);

            setFlash('success', 'Ürün başarıyla eklendi.');
            header('Location: urunler.php');
            exit;
        } catch (\Pastane\Exceptions\ValidationException $e) {
            $errors = array_merge($errors, array_values($e->getErrors()));
        } catch (Exception $e) {
            $errors[] = 'Bir hata olustu: ' . $e->getMessage();
        }
    }
    } // CSRF else kapanisi
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="u-page-header-row">
    <h2>Yeni Ürün Ekle</h2>
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
                    <label for="isim">Ürün Adı *</label>
                    <input type="text" id="isim" name="isim" class="form-control" required
                           value="<?= e($_POST['isim'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="kategori_id">Kategori</label>
                    <select id="kategori_id" name="kategori_id" class="form-control">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['kategori_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['isim']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="aciklama">Açıklama</label>
                <textarea id="aciklama" name="aciklama" class="form-control"><?= e($_POST['aciklama'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fiyat">Temel Fiyat (₺)</label>
                    <input type="number" id="fiyat" name="fiyat" class="form-control" step="0.01" min="0"
                           value="<?= e($_POST['fiyat'] ?? '0') ?>">
                    <small class="u-text-admin-light">Porsiyon seçeneği olmayan ürünler için</small>
                </div>

                <div class="form-group">
                    <label for="sira">Sıra</label>
                    <input type="number" id="sira" name="sira" class="form-control" min="0"
                           value="<?= e($_POST['sira'] ?? '0') ?>">
                </div>
            </div>

            <!-- Porsiyon Fiyatları (Pasta için) -->
            <div class="form-group u-soft-card">
                <label class="u-font-semibold u-mb-3 u-d-block">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" class="u-icon-inline-sm">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                        <path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    Porsiyon Fiyatları (Pasta kategorisi için)
                </label>
                <small class="u-form-help">Sadece pasta gibi porsiyon seçenekli ürünler için doldurun. Boş bırakılan alanlar gösterilmez.</small>
                <div class="u-grid-4">
                    <div class="form-group u-mb-0">
                        <label for="fiyat_4kisi">4 Kişilik (₺)</label>
                        <input type="number" id="fiyat_4kisi" name="fiyat_4kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_4kisi'] ?? '') ?>" placeholder="örn: 350">
                    </div>
                    <div class="form-group u-mb-0">
                        <label for="fiyat_6kisi">6 Kişilik (₺)</label>
                        <input type="number" id="fiyat_6kisi" name="fiyat_6kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_6kisi'] ?? '') ?>" placeholder="örn: 450">
                    </div>
                    <div class="form-group u-mb-0">
                        <label for="fiyat_8kisi">8 Kişilik (₺)</label>
                        <input type="number" id="fiyat_8kisi" name="fiyat_8kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_8kisi'] ?? '') ?>" placeholder="örn: 550">
                    </div>
                    <div class="form-group u-mb-0">
                        <label for="fiyat_10kisi">10+ Kişilik (₺)</label>
                        <input type="number" id="fiyat_10kisi" name="fiyat_10kisi" class="form-control" step="0.01" min="0"
                               value="<?= e($_POST['fiyat_10kisi'] ?? '') ?>" placeholder="örn: 650">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="gorsel">Ürün Görseli</label>
                <div class="file-upload" data-action="trigger-file-upload" data-target="gorsel" role="button" tabindex="0">
                    <input type="file" id="gorsel" name="gorsel" accept="image/*">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p>Görsel yüklemek için tıklayın<br><small>JPG, PNG, WebP - Max 5MB</small></p>
                </div>
                <img id="preview" class="file-preview u-hidden">
            </div>

            <div class="form-group">
                <label class="u-check-row">
                    <input type="checkbox" name="aktif" value="1" <?= ($_POST['aktif'] ?? '1') ? 'checked' : '' ?>>
                    Ürün aktif (sitede görünür)
                </label>
            </div>

            <!-- QR Menu / Cafe Ayarlari -->
            <div class="form-group u-info-banner">
                <label class="u-font-semibold u-mb-3 u-d-block">
                    QR Menü / Kafe Ayarları
                </label>
                <small class="u-form-help">
                    Masa QR menüsünde gösterim, hazırlama süresi ve stok durumu.
                </small>

                <div class="u-grid-3">
                    <div class="form-group u-mb-0">
                        <label class="u-check-row">
                            <input type="checkbox" name="cafe_menusu" value="1" <?= (!isset($_POST['cafe_menusu']) || $_POST['cafe_menusu']) ? 'checked' : '' ?>>
                            QR menüde görünsün
                        </label>
                    </div>
                    <div class="form-group u-mb-0">
                        <label for="hazirlanma_suresi">Hazırlanma Süresi (dk)</label>
                        <input type="number" id="hazirlanma_suresi" name="hazirlanma_suresi" class="form-control" min="0" max="300"
                               value="<?= e($_POST['hazirlanma_suresi'] ?? '') ?>" placeholder="örn: 15">
                    </div>
                    <div class="form-group u-mb-0">
                        <label for="stok_durumu">Stok Durumu</label>
                        <select id="stok_durumu" name="stok_durumu" class="form-control">
                            <?php $curStok = $_POST['stok_durumu'] ?? 'var'; ?>
                            <option value="var" <?= $curStok === 'var' ? 'selected' : '' ?>>Var</option>
                            <option value="sinirli" <?= $curStok === 'sinirli' ? 'selected' : '' ?>>Sınırlı</option>
                            <option value="tukendi" <?= $curStok === 'tukendi' ? 'selected' : '' ?>>Tükendi</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="u-flex-row-gap-4">
                <button type="submit" class="btn btn-primary">Ürünü Kaydet</button>
                <a href="urunler.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
(function() {
    'use strict';
    const gorselInput = document.getElementById('gorsel');
    if (gorselInput) {
        gorselInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const preview = document.getElementById('preview');
                    if (preview) {
                        preview.src = ev.target.result;
                        preview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // File upload alanı tıklandığında/klavyeyle tetiklendiğinde input'u aç
    function triggerUpload(trigger) {
        const targetId = trigger.dataset.target;
        const input = targetId ? document.getElementById(targetId) : null;
        if (input) input.click();
    }

    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('[data-action="trigger-file-upload"]');
        if (trigger && !e.target.matches('input[type="file"]')) {
            triggerUpload(trigger);
        }
    });

    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-action="trigger-file-upload"]')) {
            e.preventDefault();
            triggerUpload(e.target);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
