<?php
/**
 * Ürün satırı partial
 *
 * Parent view: admin.urunler.index (foreach ile include edilir)
 * Scope: parent'tan $product miras alınır
 *
 * @var array $product  urunler tablosu + kategori_ad (LEFT JOIN)
 */
?>
<tr data-urun-row
    data-kategori-id="<?= (int)($product['kategori_id'] ?? 0) ?>"
    data-search-text="<?= e(mb_strtolower($product['isim'] . ' ' . ($product['aciklama'] ?? ''))) ?>">
    <td>
        <?php if (!empty($product['gorsel'])): ?>
            <img src="../uploads/products/<?= e($product['gorsel']) ?>"
                 class="product-thumb"
                 alt="<?= e($product['isim']) ?>"
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
            <span class="cell-primary"><?= e($product['isim']) ?></span>
            <?php if (!empty($product['aciklama'])): ?>
                <div class="cell-muted truncate u-max-w-250">
                    <?= e(mb_substr($product['aciklama'], 0, 60)) ?><?= mb_strlen($product['aciklama']) > 60 ? '...' : '' ?>
                </div>
            <?php endif; ?>
        </div>
    </td>
    <td>
        <?php if (!empty($product['kategori_ad'])): ?>
            <span class="badge badge-primary"><?= e($product['kategori_ad']) ?></span>
        <?php else: ?>
            <span class="badge badge-neutral">Kategorisiz</span>
        <?php endif; ?>
    </td>
    <td class="u-text-right">
        <span class="cell-primary"><?= formatPrice($product['fiyat']) ?></span>
    </td>
    <td class="u-text-center">
        <span class="status <?= $product['aktif'] ? 'status-active' : 'status-inactive' ?>">
            <?= $product['aktif'] ? 'Aktif' : 'Pasif' ?>
        </span>
    </td>
    <td>
        <div class="actions">
            <a href="urun-duzenle.php?id=<?= (int)$product['id'] ?>"
               class="btn btn-sm btn-ghost btn-icon"
               data-tooltip="Duzenle"
               aria-label="<?= e($product['isim']) ?> ürününü düzenle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </a>
            <button type="button"
                    class="btn btn-sm btn-ghost btn-icon"
                    data-tooltip="<?= $product['aktif'] ? 'Pasif Yap' : 'Aktif Yap' ?>"
                    aria-label="<?= e($product['isim']) ?> ürününü <?= $product['aktif'] ? 'pasif yap' : 'aktif yap' ?>"
                    data-action="toggle-product"
                    data-id="<?= (int)$product['id'] ?>">
                <?php if ($product['aktif']): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="u-text-warning" aria-hidden="true">
                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="u-text-admin-success" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                <?php endif; ?>
            </button>
            <button type="button"
                    class="btn btn-sm btn-ghost btn-icon"
                    data-tooltip="Sil"
                    aria-label="<?= e($product['isim']) ?> ürününü sil"
                    data-action="delete-product"
                    data-id="<?= (int)$product['id'] ?>"
                    data-name="<?= e($product['isim']) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="u-text-admin-danger" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    <line x1="10" y1="11" x2="10" y2="17"/>
                    <line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
            </button>
        </div>
    </td>
</tr>
