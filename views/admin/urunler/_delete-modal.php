<?php
/**
 * Silme onay modalı partial
 *
 * Parent view: admin.urunler.index
 * Event binding: data-action="close-delete-modal" / "confirm-delete"
 *                (index.php içindeki nonce'lı script bloğu dinliyor)
 */
?>
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="u-text-admin-danger">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Ürün Sil
            </h3>
            <button type="button" class="modal-close" data-action="close-delete-modal" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="u-text-center u-text-admin-secondary u-m-0">
                <strong id="deleteProductName" class="u-text-admin-text"></strong> ürününü silmek istediğinize emin misiniz?
            </p>
            <p class="u-form-error-text">
                Bu işlem geri alınamaz ve ürün görseli de silinecektir.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-delete-modal">İptal</button>
            <button type="button" class="btn btn-danger" data-action="confirm-delete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Ürünü Sil
            </button>
        </div>
    </div>
</div>
