<?php
/**
 * İletişim Mesajları - Kart/Modal Sistemi
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Silme işlemi (header'dan önce)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('iletisim_mesajlari', 'id = :id', ['id' => $id]);
    setFlash('success', 'Mesaj silindi.');
    header('Location: mesajlar.php');
    exit;
}

// Okundu işaretleme (header'dan önce)
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $id = (int)$_GET['read'];
    db()->update('iletisim_mesajlari', ['okundu' => 1], 'id = :id', ['id' => $id]);
    header('Location: mesajlar.php');
    exit;
}

// Tümünü okundu işaretle (header'dan önce)
if (isset($_GET['mark_all_read'])) {
    db()->query("UPDATE iletisim_mesajlari SET okundu = 1");
    setFlash('success', 'Tüm mesajlar okundu olarak işaretlendi.');
    header('Location: mesajlar.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Mesajları listele
$messages = db()->fetchAll("SELECT * FROM iletisim_mesajlari ORDER BY okundu ASC, created_at DESC");
$unreadCount = db()->fetch("SELECT COUNT(*) as count FROM iletisim_mesajlari WHERE okundu = 0")['count'];
?>

<style>
/* Mesaj Kartları */
.mesaj-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mesaj-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 1.2rem 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}

.mesaj-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.mesaj-card.unread {
    background: linear-gradient(to right, #f0f7ff, white);
    border-left-color: var(--admin-primary);
}

.mesaj-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.8rem;
}

.mesaj-sender {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.mesaj-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--admin-primary), #C4A4B4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
}

.mesaj-sender-info h4 {
    margin: 0;
    font-size: 1rem;
    color: var(--admin-dark);
}

.mesaj-sender-info .contact {
    font-size: 0.85rem;
    color: var(--admin-text-light);
    margin-top: 0.2rem;
}

.mesaj-meta {
    text-align: right;
}

.mesaj-date {
    font-size: 0.85rem;
    color: var(--admin-text-light);
}

.mesaj-status {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-top: 0.3rem;
}

.mesaj-status.new {
    background: #e3f2fd;
    color: #1976d2;
}

.mesaj-status.read {
    background: #e8f5e9;
    color: #388e3c;
}

.mesaj-preview {
    color: #555;
    font-size: 0.95rem;
    line-height: 1.5;
    word-break: break-word;
    overflow-wrap: break-word;
}

.mesaj-card-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 0.8rem;
    padding-top: 0.8rem;
    border-top: 1px solid #eee;
}

.mesaj-card-footer .btn {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

/* Modal */
.mesaj-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.mesaj-modal-overlay.active {
    display: flex;
}

.mesaj-modal {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mesaj-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mesaj-modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mesaj-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    color: var(--admin-text-light);
    transition: color 0.2s;
}

.mesaj-modal-close:hover {
    color: var(--admin-dark);
}

.mesaj-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.mesaj-detail-section {
    margin-bottom: 1.5rem;
}

.mesaj-detail-section:last-child {
    margin-bottom: 0;
}

.mesaj-detail-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--admin-text-light);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.mesaj-detail-value {
    color: var(--admin-dark);
    font-size: 1rem;
}

.mesaj-detail-value a {
    color: var(--admin-primary);
    text-decoration: none;
}

.mesaj-detail-value a:hover {
    text-decoration: underline;
}

.mesaj-content {
    background: #f8f9fa;
    padding: 1.2rem;
    border-radius: 10px;
    line-height: 1.7;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.mesaj-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 0.8rem;
    background: #f8f9fa;
}

/* Empty State */
.mesaj-empty {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.mesaj-empty svg {
    width: 80px;
    height: 80px;
    color: #ddd;
    margin-bottom: 1rem;
}

.mesaj-empty h3 {
    color: var(--admin-dark);
    margin-bottom: 0.5rem;
}

.mesaj-empty p {
    color: var(--admin-text-light);
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2>İletişim Mesajları</h2>
    <?php if ($unreadCount > 0): ?>
        <a href="?mark_all_read=1" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <polyline points="9 11 12 14 22 4"/>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
            </svg>
            Tümünü Okundu İşaretle
        </a>
    <?php endif; ?>
</div>

<?php if (empty($messages)): ?>
    <div class="mesaj-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        <h3>Henüz mesaj yok</h3>
        <p>İletişim formundan gelen mesajlar burada görünecek.</p>
    </div>
<?php else: ?>
    <div class="mesaj-list">
        <?php foreach ($messages as $msg):
            $ilkHarf = mb_strtoupper(mb_substr($msg['isim'], 0, 1));
            $tarih = date('d.m.Y', strtotime($msg['created_at']));
            $saat = date('H:i', strtotime($msg['created_at']));
        ?>
            <div class="mesaj-card <?= !$msg['okundu'] ? 'unread' : '' ?>"
                 onclick="openMesajModal(<?= htmlspecialchars(json_encode($msg), ENT_QUOTES, 'UTF-8') ?>)">
                <div class="mesaj-card-header">
                    <div class="mesaj-sender">
                        <div class="mesaj-avatar"><?= e($ilkHarf) ?></div>
                        <div class="mesaj-sender-info">
                            <h4><?= e($msg['isim']) ?></h4>
                            <div class="contact">
                                <?php if ($msg['email']): ?>
                                    <?= e($msg['email']) ?>
                                <?php elseif ($msg['telefon']): ?>
                                    <?= e($msg['telefon']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mesaj-meta">
                        <div class="mesaj-date"><?= $tarih ?> - <?= $saat ?></div>
                        <?php if (!$msg['okundu']): ?>
                            <span class="mesaj-status new">Yeni</span>
                        <?php else: ?>
                            <span class="mesaj-status read">Okundu</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mesaj-preview"><?= e(mb_strlen($msg['mesaj']) > 50 ? mb_substr($msg['mesaj'], 0, 50) . '...' : $msg['mesaj']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal -->
<div class="mesaj-modal-overlay" id="mesajModal">
    <div class="mesaj-modal">
        <div class="mesaj-modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                Mesaj Detayı
            </h3>
            <button class="mesaj-modal-close" onclick="closeMesajModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="mesaj-modal-body">
            <div class="mesaj-detail-section">
                <div class="mesaj-detail-label">Gönderen</div>
                <div class="mesaj-detail-value" id="modalSender"></div>
            </div>
            <div class="mesaj-detail-section" id="modalEmailSection">
                <div class="mesaj-detail-label">E-posta</div>
                <div class="mesaj-detail-value" id="modalEmail"></div>
            </div>
            <div class="mesaj-detail-section" id="modalPhoneSection">
                <div class="mesaj-detail-label">Telefon</div>
                <div class="mesaj-detail-value" id="modalPhone"></div>
            </div>
            <div class="mesaj-detail-section">
                <div class="mesaj-detail-label">Tarih</div>
                <div class="mesaj-detail-value" id="modalDate"></div>
            </div>
            <div class="mesaj-detail-section">
                <div class="mesaj-detail-label">Mesaj</div>
                <div class="mesaj-content" id="modalContent"></div>
            </div>
        </div>
        <div class="mesaj-modal-footer">
            <a href="#" id="modalMarkRead" class="btn btn-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Okundu İşaretle
            </a>
            <a href="#" id="modalDelete" class="btn btn-danger" onclick="return confirm('Bu mesajı silmek istediğinize emin misiniz?')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Sil
            </a>
        </div>
    </div>
</div>

<script>
let currentMesaj = null;

function openMesajModal(mesaj) {
    currentMesaj = mesaj;

    // Gönderen bilgisi
    document.getElementById('modalSender').textContent = mesaj.isim;

    // E-posta
    const emailSection = document.getElementById('modalEmailSection');
    const emailEl = document.getElementById('modalEmail');
    if (mesaj.email) {
        emailSection.style.display = 'block';
        emailEl.innerHTML = '<a href="mailto:' + mesaj.email + '">' + mesaj.email + '</a>';
    } else {
        emailSection.style.display = 'none';
    }

    // Telefon
    const phoneSection = document.getElementById('modalPhoneSection');
    const phoneEl = document.getElementById('modalPhone');
    if (mesaj.telefon) {
        phoneSection.style.display = 'block';
        phoneEl.innerHTML = '<a href="tel:' + mesaj.telefon + '">' + mesaj.telefon + '</a>';
    } else {
        phoneSection.style.display = 'none';
    }

    // Tarih
    const tarih = new Date(mesaj.created_at);
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    document.getElementById('modalDate').textContent = tarih.toLocaleDateString('tr-TR', options);

    // Mesaj içeriği
    document.getElementById('modalContent').textContent = mesaj.mesaj;

    // Okundu butonu
    const markReadBtn = document.getElementById('modalMarkRead');
    if (mesaj.okundu == 1) {
        markReadBtn.style.display = 'none';
    } else {
        markReadBtn.style.display = 'inline-flex';
        markReadBtn.href = '?read=' + mesaj.id;
    }

    // Sil butonu
    document.getElementById('modalDelete').href = '?delete=' + mesaj.id;

    // Modal'ı göster
    document.getElementById('mesajModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMesajModal() {
    document.getElementById('mesajModal').classList.remove('active');
    document.body.style.overflow = '';
    currentMesaj = null;
}

// Overlay'a tıklayınca kapat
document.getElementById('mesajModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMesajModal();
    }
});

// ESC tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('mesajModal').classList.contains('active')) {
        closeMesajModal();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
