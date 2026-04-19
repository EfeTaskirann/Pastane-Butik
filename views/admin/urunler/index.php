<?php
/**
 * Admin Urun Listesi — View
 *
 * Controller: Pastane\Controllers\Admin\UrunController::index()
 * Data (extract'den gelir):
 *   $products       (array)  — kategori bilgisiyle ürünler
 *   $allCategories  (array)  — filtre dropdown için tüm kategoriler
 *   $activeCount    (int)
 *   $inactiveCount  (int)
 *   $toastMessage   (?string) — flash toast (client-side)
 *
 * Kurallar:
 *   - Sadece HTML + escape'li PHP echo; logic/SQL YASAK
 *   - Tüm event binding'ler data-action + delegation (CSP uyumlu)
 *   - header.php / footer.php router tarafından yüklenir
 */

/** @var array $products */
/** @var array $allCategories */
/** @var int $activeCount */
/** @var int $inactiveCount */
/** @var ?string $toastMessage */

$products      = $products      ?? [];
$allCategories = $allCategories ?? [];
$activeCount   = $activeCount   ?? 0;
$inactiveCount = $inactiveCount ?? 0;
$toastMessage  = $toastMessage  ?? null;
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
<div class="stats-grid u-grid-auto-180-tight">
    <div class="stat-card stat-card--primary">
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
    <div class="stat-card stat-card--success">
        <div class="stat-icon success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= (int)$activeCount ?></h4>
            <span>Aktif Urun</span>
        </div>
    </div>
    <div class="stat-card stat-card--warning">
        <div class="stat-icon warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= (int)$inactiveCount ?></h4>
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
        <span class="badge badge-neutral" id="urun-toplam-badge"><?= count($products) ?> ürün</span>
    </div>

    <!-- Filter & Search Bar -->
    <div class="card-body u-toolbar-header">
        <div class="u-grid-detail-220">
            <div>
                <label for="urun-arama" class="sr-only">Ürün ara</label>
                <input type="search" id="urun-arama" class="form-control" placeholder="Ürün adı veya açıklama ara..." autocomplete="off" aria-label="Ürün adı veya açıklama ara">
            </div>
            <div>
                <label for="urun-kategori-filtre" class="sr-only">Kategori filtresi</label>
                <select id="urun-kategori-filtre" class="form-control" aria-label="Kategori filtresi">
                    <option value="">Tüm Kategoriler</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['isim']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card-body u-p-0">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                    </svg>
                </div>
                <h3>Henüz ürün eklenmemiş</h3>
                <p>Müşterilerinize sunmak istediğiniz ürünleri ekleyerek başlayın.</p>
                <a href="urun-ekle.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    İlk Ürünü Ekle
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="u-w-70px"></th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th class="u-text-right">Fiyat</th>
                            <th class="u-text-center">Durum</th>
                            <th class="u-w-140px">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php include __DIR__ . '/_row.php'; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Hidden Forms -->
<form id="toggleForm" method="POST" class="u-hidden">
    <?= csrfTokenField() ?>
    <input type="hidden" name="toggle_id" id="toggleId">
</form>

<form id="deleteForm" method="POST" class="u-hidden">
    <?= csrfTokenField() ?>
    <input type="hidden" name="delete_id" id="deleteId">
</form>

<?php include __DIR__ . '/_delete-modal.php'; ?>

<script nonce="<?= e(getCspNonce()) ?>">
(function() {
    'use strict';

    var deleteId = null;

    var toggleForm    = document.getElementById('toggleForm');
    var toggleIdInput = document.getElementById('toggleId');
    var deleteForm    = document.getElementById('deleteForm');
    var deleteIdInput = document.getElementById('deleteId');
    var deleteModal   = document.getElementById('deleteModal');
    var deleteNameEl  = document.getElementById('deleteProductName');

    /**
     * Ürün aktif/pasif toggle — gizli form submit
     * @param {string|number} id
     */
    function toggleProduct(id) {
        if (!toggleForm || !toggleIdInput) return;
        toggleIdInput.value = String(id);
        toggleForm.submit();
    }

    /**
     * Sil modal'ını aç
     * @param {string|number} id
     * @param {string} name
     */
    function openDeleteModal(id, name) {
        if (!deleteModal || !deleteNameEl) return;
        deleteId = id;
        deleteNameEl.textContent = name;
        deleteModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.classList.remove('active');
        document.body.style.overflow = '';
        deleteId = null;
    }

    function confirmDelete() {
        if (deleteId === null || !deleteForm || !deleteIdInput) return;
        deleteIdInput.value = String(deleteId);
        deleteForm.submit();
    }

    // Toggle & delete butonlarını event delegation ile bağla
    document.addEventListener('click', function(e) {
        var target = e.target.closest('[data-action]');
        if (!target) return;

        var action = target.getAttribute('data-action');

        if (action === 'toggle-product') {
            var id = target.getAttribute('data-id');
            if (id) toggleProduct(id);
        } else if (action === 'delete-product') {
            var delId   = target.getAttribute('data-id');
            var delName = target.getAttribute('data-name') || '';
            if (delId) openDeleteModal(delId, delName);
        } else if (action === 'close-delete-modal') {
            closeDeleteModal();
        } else if (action === 'confirm-delete') {
            confirmDelete();
        }
    });

    // Overlay (modal dışına) tıklayınca kapat
    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) closeDeleteModal();
        });
    }

    // ESC ile kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    // Ürün liste filtresi: kategori + arama (client-side)
    var searchInput    = document.getElementById('urun-arama');
    var categorySelect = document.getElementById('urun-kategori-filtre');
    var rows           = document.querySelectorAll('tr[data-urun-row]');
    var badge          = document.getElementById('urun-toplam-badge');

    if (searchInput && categorySelect && rows.length > 0) {
        var applyFilter = function() {
            var q = searchInput.value.trim().toLowerCase();
            var catId = categorySelect.value;
            var visible = 0;
            rows.forEach(function(row) {
                var matchQ = !q || (row.dataset.searchText || '').includes(q);
                var matchCat = !catId || row.dataset.kategoriId === catId;
                var show = matchQ && matchCat;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (badge) badge.textContent = visible + ' ürün';
        };
        searchInput.addEventListener('input', applyFilter);
        categorySelect.addEventListener('change', applyFilter);
    }
})();
</script>

<?php if ($toastMessage !== null): ?>
<script nonce="<?= e(getCspNonce()) ?>">
(function () {
    'use strict';
    var fire = function () {
        if (window.Toast && typeof window.Toast.success === 'function') {
            window.Toast.success(<?= json_encode($toastMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fire, { once: true });
    } else {
        setTimeout(fire, 0);
    }
})();
</script>
<?php endif; ?>
