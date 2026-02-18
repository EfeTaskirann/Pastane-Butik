<?php
/**
 * Iletisim Mesajlari - Professional UI
 * MVC: MesajService kullanır
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// MesajService instance
$mesajService = mesaj_service();

// POST islemleri (guvenli)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: mesajlar.php');
        exit;
    }

    // Silme islemi
    if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $mesajService->delete($id);
        setFlash('success', 'Mesaj silindi.');
        header('Location: mesajlar.php');
        exit;
    }

    // Okundu isaretleme
    if (isset($_POST['read_id']) && is_numeric($_POST['read_id'])) {
        $id = (int)$_POST['read_id'];
        $mesajService->markAsRead($id);
        header('Location: mesajlar.php');
        exit;
    }

    // Tumunu okundu isaretle
    if (isset($_POST['mark_all_read'])) {
        $mesajService->markAllAsRead();
        setFlash('success', 'Tum mesajlar okundu olarak isaretlendi.');
        header('Location: mesajlar.php');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Mesajlari listele (service kullanarak)
$messages = $mesajService->getAllOrdered();
$unreadCount = $mesajService->getUnreadCount();
$totalCount = count($messages);
?>

<style>
/* Message Card Styles */
.message-grid {
    display: grid;
    gap: var(--space-4);
}

.message-card {
    background: var(--admin-card);
    border-radius: var(--radius-xl);
    padding: var(--space-5);
    cursor: pointer;
    transition: all var(--transition-base);
    border: 1px solid var(--admin-border-light);
    position: relative;
    overflow: hidden;
}

.message-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: transparent;
    transition: background var(--transition-base);
}

.message-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.message-card.unread {
    background: linear-gradient(to right, var(--admin-info-light), var(--admin-card));
}

.message-card.unread::before {
    background: var(--admin-info);
}

.message-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--space-3);
}

.message-sender {
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.message-sender-info h4 {
    font-size: var(--text-base);
    font-weight: var(--font-semibold);
    color: var(--admin-text);
    margin: 0;
}

.message-sender-info .contact {
    font-size: var(--text-sm);
    color: var(--admin-text-secondary);
    margin-top: var(--space-1);
}

.message-meta {
    text-align: right;
    flex-shrink: 0;
}

.message-date {
    font-size: var(--text-xs);
    color: var(--admin-text-light);
}

.message-preview {
    color: var(--admin-text-secondary);
    font-size: var(--text-sm);
    line-height: var(--leading-relaxed);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Message Detail Styles */
.message-detail-row {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--admin-border-light);
}

.message-detail-row:last-child {
    border-bottom: none;
}

.message-detail-label {
    font-size: var(--text-xs);
    font-weight: var(--font-semibold);
    color: var(--admin-text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.message-detail-value {
    font-size: var(--text-sm);
    color: var(--admin-text);
}

.message-detail-value a {
    color: var(--admin-primary);
    text-decoration: none;
}

.message-detail-value a:hover {
    text-decoration: underline;
}

.message-content-box {
    background: var(--admin-bg);
    padding: var(--space-5);
    border-radius: var(--radius-lg);
    line-height: var(--leading-relaxed);
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: var(--text-sm);
    color: var(--admin-text);
    margin-top: var(--space-4);
    border: 1px solid var(--admin-border-light);
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        Iletisim Mesajlari
    </h2>
    <?php if ($unreadCount > 0): ?>
        <div class="page-header-actions">
            <form method="POST" style="display: inline;">
                <?= csrfTokenField() ?>
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/>
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                    </svg>
                    Tumunu Okundu Isaretle
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card" style="--stat-color: var(--admin-primary);">
        <div class="stat-icon primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $totalCount ?></h4>
            <span>Toplam Mesaj</span>
        </div>
    </div>
    <div class="stat-card" style="--stat-color: var(--admin-info);">
        <div class="stat-icon info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
        </div>
        <div class="stat-info">
            <h4><?= $unreadCount ?></h4>
            <span>Okunmamis</span>
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
            <h4><?= $totalCount - $unreadCount ?></h4>
            <span>Okunmus</span>
        </div>
    </div>
</div>

<!-- Messages -->
<div class="card">
    <div class="card-header">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
            Gelen Mesajlar
        </h3>
        <span class="badge badge-neutral"><?= $totalCount ?> mesaj</span>
    </div>
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                    </svg>
                </div>
                <h3>Henuz mesaj yok</h3>
                <p>Iletisim formundan gelen mesajlar burada gorunecek.</p>
            </div>
        <?php else: ?>
            <div class="message-grid">
                <?php foreach ($messages as $msg):
                    $ilkHarf = mb_strtoupper(mb_substr($msg['ad'], 0, 1));
                    $tarih = date('d.m.Y', strtotime($msg['created_at']));
                    $saat = date('H:i', strtotime($msg['created_at']));
                ?>
                    <div class="message-card <?= !$msg['okundu'] ? 'unread' : '' ?>"
                         onclick="openMessageModal(<?= htmlspecialchars(json_encode($msg), ENT_QUOTES, 'UTF-8') ?>)">
                        <div class="message-card-header">
                            <div class="message-sender">
                                <div class="avatar avatar-primary"><?= e($ilkHarf) ?></div>
                                <div class="message-sender-info">
                                    <h4><?= e($msg['ad']) ?></h4>
                                    <div class="contact">
                                        <?php if ($msg['email']): ?>
                                            <?= e($msg['email']) ?>
                                        <?php elseif ($msg['telefon']): ?>
                                            <?= e($msg['telefon']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Iletisim bilgisi yok</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="message-meta">
                                <div class="message-date"><?= $tarih ?> <?= $saat ?></div>
                                <?php if (!$msg['okundu']): ?>
                                    <span class="badge badge-info" style="margin-top: var(--space-1);">Yeni</span>
                                <?php else: ?>
                                    <span class="badge badge-success" style="margin-top: var(--space-1);">Okundu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="message-preview"><?= e($msg['mesaj']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Message Detail Modal -->
<div class="modal-overlay" id="messageModal">
    <div class="modal">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                Mesaj Detayi
            </h3>
            <button class="modal-close" onclick="closeMessageModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="message-detail-row">
                <div class="message-detail-label">Gonderen</div>
                <div class="message-detail-value" id="modalSender"></div>
            </div>
            <div class="message-detail-row" id="modalEmailRow">
                <div class="message-detail-label">E-posta</div>
                <div class="message-detail-value" id="modalEmail"></div>
            </div>
            <div class="message-detail-row" id="modalPhoneRow">
                <div class="message-detail-label">Telefon</div>
                <div class="message-detail-value" id="modalPhone"></div>
            </div>
            <div class="message-detail-row">
                <div class="message-detail-label">Tarih</div>
                <div class="message-detail-value" id="modalDate"></div>
            </div>

            <div style="margin-top: var(--space-4);">
                <div class="message-detail-label" style="margin-bottom: var(--space-2);">Mesaj Icerigi</div>
                <div class="message-content-box" id="modalContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="markReadForm" style="display: inline;">
                <?= csrfTokenField() ?>
                <input type="hidden" name="read_id" id="markReadId">
                <button type="submit" id="modalMarkRead" class="btn btn-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Okundu Isaretle
                </button>
            </form>
            <button type="button" class="btn btn-danger" onclick="deleteMessage()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Sil
            </button>
        </div>
    </div>
</div>

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
                Mesaj Sil
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
                Bu mesaji silmek istediginize emin misiniz?
            </p>
            <p style="text-align: center; font-size: var(--text-sm); color: var(--admin-danger); margin-top: var(--space-3); margin-bottom: 0;">
                Bu islem geri alinamaz.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Iptal</button>
            <form method="POST" id="deleteForm" style="display: inline;">
                <?= csrfTokenField() ?>
                <input type="hidden" name="delete_id" id="deleteId">
                <button type="submit" class="btn btn-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                    Sil
                </button>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
let currentMessage = null;

function openMessageModal(mesaj) {
    currentMessage = mesaj;

    // Sender
    document.getElementById('modalSender').textContent = mesaj.ad;

    // Email
    const emailRow = document.getElementById('modalEmailRow');
    const emailEl = document.getElementById('modalEmail');
    if (mesaj.email) {
        emailRow.style.display = 'grid';
        emailEl.innerHTML = '<a href="mailto:' + escapeHtml(mesaj.email) + '">' + escapeHtml(mesaj.email) + '</a>';
    } else {
        emailRow.style.display = 'none';
    }

    // Phone
    const phoneRow = document.getElementById('modalPhoneRow');
    const phoneEl = document.getElementById('modalPhone');
    if (mesaj.telefon) {
        phoneRow.style.display = 'grid';
        phoneEl.innerHTML = '<a href="tel:' + escapeHtml(mesaj.telefon) + '">' + escapeHtml(mesaj.telefon) + '</a>';
    } else {
        phoneRow.style.display = 'none';
    }

    // Date
    const tarih = new Date(mesaj.created_at);
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    document.getElementById('modalDate').textContent = tarih.toLocaleDateString('tr-TR', options);

    // Content
    document.getElementById('modalContent').textContent = mesaj.mesaj;

    // Read button
    const markReadForm = document.getElementById('markReadForm');
    if (mesaj.okundu == 1) {
        markReadForm.style.display = 'none';
    } else {
        markReadForm.style.display = 'inline';
        document.getElementById('markReadId').value = mesaj.id;
    }

    // Show modal
    document.getElementById('messageModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('active');
    document.body.style.overflow = '';
    currentMessage = null;
}

function deleteMessage() {
    if (currentMessage) {
        document.getElementById('deleteId').value = currentMessage.id;
        closeMessageModal();
        document.getElementById('deleteModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.getElementById('messageModal').addEventListener('click', function(e) {
    if (e.target === this) closeMessageModal();
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Close modals on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMessageModal();
        closeDeleteModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
