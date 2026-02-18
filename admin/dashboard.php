<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/includes/header.php';

// İstatistikleri service layer üzerinden al
$urunService = urun_service();
$kategoriService = kategori_service();
$mesajService = mesaj_service();

$totalProducts = $urunService->count();
$totalCategories = $kategoriService->count();
$unreadMessages = $mesajService->getUnreadCount();

// Son eklenen ürünler — service üzerinden
$recentProducts = $urunService->getActive(null, 5);

// Son mesajlar — service üzerinden sıralı mesajlardan ilk 5
$allMessages = $mesajService->getAllOrdered();
$recentMessages = array_slice($allMessages, 0, 5);
?>

<h2 style="margin-bottom: 1.5rem;">Dashboard</h2>

<!-- İstatistik Kartları -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon products">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $totalProducts ?></h4>
            <span>Toplam Ürün</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon categories">
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
            <h4><?= $totalCategories ?></h4>
            <span>Kategori</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon messages">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $unreadMessages ?></h4>
            <span>Okunmamış Mesaj</span>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
    <!-- Son Ürünler -->
    <div class="card">
        <div class="card-header">
            <h3>Son Eklenen Ürünler</h3>
            <a href="urunler.php" class="btn btn-sm btn-secondary">Tümünü Gör</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentProducts)): ?>
                <div class="empty-state">
                    <p>Henüz ürün eklenmemiş.</p>
                </div>
            <?php else: ?>
                <table>
                    <tbody>
                    <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td style="width: 60px;">
                                <?php if ($product['gorsel']): ?>
                                    <img src="../uploads/products/<?= e($product['gorsel']) ?>" class="product-thumb" alt="<?= e($product['ad']) ?>">
                                <?php else: ?>
                                    <div class="product-thumb" style="background: #F5E1E9; display: flex; align-items: center; justify-content: center;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8B6F5C" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($product['ad']) ?></strong><br>
                                <small style="color: var(--admin-text-light);"><?= formatPrice($product['fiyat']) ?></small>
                            </td>
                            <td style="text-align: right;">
                                <span class="status <?= $product['aktif'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $product['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Son Mesajlar -->
    <div class="card">
        <div class="card-header">
            <h3>Son Mesajlar</h3>
            <a href="mesajlar.php" class="btn btn-sm btn-secondary">Tümünü Gör</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentMessages)): ?>
                <div class="empty-state">
                    <p>Henüz mesaj yok.</p>
                </div>
            <?php else: ?>
                <table>
                    <tbody>
                    <?php foreach ($recentMessages as $message): ?>
                        <tr>
                            <td>
                                <strong><?= e($message['ad']) ?></strong><br>
                                <small style="color: var(--admin-text-light);">
                                    <?= e(mb_substr($message['mesaj'], 0, 50)) ?>...
                                </small>
                            </td>
                            <td style="text-align: right;">
                                <?php if (!$message['okundu']): ?>
                                    <span class="status status-unread">Yeni</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
