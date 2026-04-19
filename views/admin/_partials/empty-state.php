<?php
/**
 * views/admin/_partials/empty-state.php (P3-21)
 *
 * Admin listing sayfalarında 0 kayıt durumu için standart komponent.
 *
 * Parametreler ($data veya eski yol $args):
 *   - icon        : SVG path veya Unicode/emoji (default: kutu)
 *   - title       : Ana mesaj (örn. "Henüz ürün yok")
 *   - description : Alt açıklama (kısa cümle)
 *   - cta_label   : Buton metni (default: null — gösterilmez)
 *   - cta_url     : Buton href (varsa)
 *   - cta_icon    : Opsiyonel ikon (önek)
 *
 * Kullanım:
 *   <?php
 *   $empty = [
 *       'title' => 'Henüz ürün yok',
 *       'description' => 'İlk ürününüzü ekleyerek başlayın.',
 *       'cta_label' => 'Yeni Ürün Ekle',
 *       'cta_url' => 'urun-ekle.php',
 *   ];
 *   require __DIR__ . '/_partials/empty-state.php';
 *   ?>
 */

$title = $empty['title'] ?? $title ?? 'Henüz kayıt bulunmuyor';
$description = $empty['description'] ?? $description ?? 'Bu listede gösterilecek bir kayıt yok.';
$icon = $empty['icon'] ?? $icon ?? null;
$ctaLabel = $empty['cta_label'] ?? $cta_label ?? null;
$ctaUrl = $empty['cta_url'] ?? $cta_url ?? null;
$ctaIcon = $empty['cta_icon'] ?? $cta_icon ?? '+';

$escape = static function (?string $v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<div class="empty-state" role="status" aria-live="polite">
    <div class="empty-state__icon" aria-hidden="true">
        <?php if ($icon && strpos($icon, '<svg') === 0): ?>
            <?= $icon /* trusted caller-side SVG */ ?>
        <?php elseif ($icon): ?>
            <span class="empty-state__emoji"><?= $escape($icon) ?></span>
        <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
                <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 20 L32 8 L54 20 L54 48 L32 60 L10 48 Z"/>
                    <path d="M10 20 L32 32 L54 20"/>
                    <path d="M32 32 L32 60"/>
                </g>
            </svg>
        <?php endif; ?>
    </div>

    <h3 class="empty-state__title"><?= $escape($title) ?></h3>
    <p class="empty-state__description"><?= $escape($description) ?></p>

    <?php if ($ctaLabel && $ctaUrl): ?>
        <a href="<?= $escape($ctaUrl) ?>" class="btn btn-primary empty-state__cta">
            <span aria-hidden="true" class="empty-state__cta-icon"><?= $escape($ctaIcon) ?></span>
            <?= $escape($ctaLabel) ?>
        </a>
    <?php endif; ?>
</div>
