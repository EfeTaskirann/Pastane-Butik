<?php
/**
 * Tema Yonetimi — Admin Panel
 * Mevsimsel temalari aktif/inaktif yapma
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// TemaService instance
$temaService = tema_service();

// POST islemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
    } else {
        $action = $_POST['action'] ?? '';
        $temaId = isset($_POST['tema_id']) ? (int)$_POST['tema_id'] : 0;

        try {
            if ($action === 'activate' && $temaId > 0) {
                $temaService->activate($temaId);
                setFlash('success', 'Tema basariyla aktif edildi.');
            } elseif ($action === 'deactivate' && $temaId > 0) {
                $temaService->deactivate($temaId);
                setFlash('success', 'Tema deaktif edildi. Site varsayilan gorunume dondu.');
            }
        } catch (\Throwable $e) {
            setFlash('error', 'Islem sirasinda hata olustu: ' . $e->getMessage());
        }
    }

    header('Location: temalar.php');
    exit;
}

// Tum temalari getir
$temalar = $temaService->getAll();
$activeTema = $temaService->getActive();

// Tema ikon SVG'leri
$temaIkonlari = [
    'snowflake' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
        <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M19.07 4.93L4.93 19.07"/>
        <circle cx="12" cy="12" r="3" fill="currentColor" opacity="0.15"/>
        <path d="M12 5l-1.5 1.5M12 5l1.5 1.5M12 19l-1.5-1.5M12 19l1.5-1.5M5 12l1.5-1.5M5 12l1.5 1.5M19 12l-1.5-1.5M19 12l-1.5 1.5"/>
    </svg>',
    'sun' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
        <circle cx="12" cy="12" r="5" fill="currentColor" opacity="0.15"/>
        <line x1="12" y1="1" x2="12" y2="3"/>
        <line x1="12" y1="21" x2="12" y2="23"/>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
        <line x1="1" y1="12" x2="3" y2="12"/>
        <line x1="21" y1="12" x2="23" y2="12"/>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
    </svg>',
];

// Tema renk paletleri (preview)
$temaRenkleri = [
    'kis' => ['#E8EFF5', '#B8CCE0', '#8BACC4', '#6B7B8D', '#D4A574', '#3D4F5F'],
    'yaz' => ['#FFF0E6', '#FFB088', '#FF8C69', '#FFD93D', '#6BCB77', '#FF5C8D'],
];

// Tema preview gradientleri
$temaGradientleri = [
    'kis' => 'linear-gradient(135deg, #E8EFF5 0%, #B8CCE0 40%, #8BACC4 100%)',
    'yaz' => 'linear-gradient(135deg, #FFF0E6 0%, #FFB088 40%, #FF8C69 100%)',
];

$currentPage = 'temalar';
$pageTitle = 'Tema Yonetimi';
require_once __DIR__ . '/includes/header.php';
?>

<style nonce="<?= getCspNonce() ?>">
    .tema-header {
        margin-bottom: 2rem;
    }

    .tema-header h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--admin-text);
        margin: 0 0 0.5rem 0;
    }

    .tema-header p {
        color: var(--admin-text-secondary);
        margin: 0;
        font-size: 0.9rem;
    }

    .tema-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .tema-status-badge.aktif {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .tema-status-badge.aktif::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10B981;
        animation: statusPulse 2s ease-in-out infinite;
    }

    .tema-status-badge.inaktif {
        background: rgba(148, 163, 184, 0.1);
        color: #64748B;
    }

    @keyframes statusPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    .tema-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .tema-card {
        background: var(--admin-card, #fff);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.04);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .tema-card.aktif {
        border-color: #10B981;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);
    }

    .tema-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    /* Preview alani */
    .tema-preview {
        height: 140px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .tema-preview-ikon {
        position: relative;
        z-index: 2;
        color: rgba(255, 255, 255, 0.9);
        filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.15));
    }

    /* Dekoratif parcaciklar preview'da */
    .tema-preview-particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
        z-index: 1;
    }

    .tema-preview-particles span {
        position: absolute;
        opacity: 0.5;
        animation: previewFloat 4s ease-in-out infinite;
    }

    @keyframes previewFloat {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-15px) rotate(10deg); }
    }

    /* Renk paleti */
    .tema-renk-paleti {
        position: absolute;
        bottom: 12px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 6px;
        z-index: 3;
    }

    .tema-renk-paleti span {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        transition: transform 0.2s ease;
    }

    .tema-renk-paleti span:hover {
        transform: scale(1.2);
    }

    /* Kart icerigi */
    .tema-body {
        padding: 1.25rem 1.5rem;
    }

    .tema-body-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .tema-body h3 {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--admin-text);
        margin: 0;
    }

    .tema-body p {
        color: var(--admin-text-secondary);
        font-size: 0.85rem;
        margin: 0 0 1.25rem 0;
        line-height: 1.5;
    }

    /* Toggle switch */
    .tema-toggle-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 1rem;
        border-top: 1px solid rgba(0, 0, 0, 0.06);
    }

    .tema-toggle-label {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--admin-text);
    }

    .tema-toggle {
        position: relative;
        width: 52px;
        height: 28px;
        display: inline-block;
    }

    .tema-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .tema-toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #CBD5E1;
        border-radius: 28px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .tema-toggle-slider::before {
        content: '';
        position: absolute;
        width: 22px;
        height: 22px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .tema-toggle input:checked + .tema-toggle-slider {
        background: #10B981;
    }

    .tema-toggle input:checked + .tema-toggle-slider::before {
        transform: translateX(24px);
    }

    .tema-toggle input:focus-visible + .tema-toggle-slider {
        outline: 2px solid var(--admin-primary);
        outline-offset: 2px;
    }

    /* Bilgi notu */
    .tema-info {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        background: rgba(59, 130, 246, 0.05);
        border: 1px solid rgba(59, 130, 246, 0.1);
        border-radius: 12px;
        margin-bottom: 1.5rem;
        font-size: 0.85rem;
        color: #475569;
        line-height: 1.5;
    }

    .tema-info svg {
        flex-shrink: 0;
        color: #3B82F6;
        margin-top: 1px;
    }

    /* Responsive */
    @media (max-width: 860px) {
        .tema-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="tema-header">
    <h2>Tema Yonetimi</h2>
    <p>Mevsimsel temalar ile sitenizin gorunumunu degistirin. Ayni anda yalnizca bir tema aktif olabilir.</p>
</div>

<div class="tema-info">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="16" x2="12" y2="12"/>
        <line x1="12" y1="8" x2="12.01" y2="8"/>
    </svg>
    <div>
        Bir temayi aktif ettiginizde, sitenizin renkleri, animasyonlari ve dekoratif ogeleri degisir.
        Aktif temayi kapatirsaniz site varsayilan gorunumune doner. Degisiklikler aninda uygulanir.
    </div>
</div>

<div class="tema-grid">
    <?php foreach ($temalar as $tema): ?>
        <?php
        $isActive = (int)$tema['aktif'] === 1;
        $slug = e($tema['slug']);
        $renkler = $temaRenkleri[$slug] ?? [];
        $gradient = $temaGradientleri[$slug] ?? 'linear-gradient(135deg, #E8EFF5, #B8CCE0)';
        $ikon = $temaIkonlari[$tema['ikon'] ?? ''] ?? '';
        ?>
        <div class="tema-card <?= $isActive ? 'aktif' : '' ?>">
            <!-- Preview Alani -->
            <?php /* Dinamik gradient — PHP $gradient uretimi, CSS custom property atar */ ?>
            <div class="tema-preview" style="--u-tema-gradient: <?= e($gradient) ?>;">
                <!-- Dekoratif Parcaciklar -->
                <div class="tema-preview-particles">
                    <?php if ($slug === 'kis'): ?>
                        <span class="tema-particle tema-particle--p1">&#10052;</span>
                        <span class="tema-particle tema-particle--p2">&#10053;</span>
                        <span class="tema-particle tema-particle--p3">&#10054;</span>
                        <span class="tema-particle tema-particle--p4">&#10052;</span>
                        <span class="tema-particle tema-particle--p5">&#10053;</span>
                    <?php elseif ($slug === 'yaz'): ?>
                        <span class="tema-particle tema-particle--y1">&#127827;</span>
                        <span class="tema-particle tema-particle--y2">&#127819;</span>
                        <span class="tema-particle tema-particle--y3">&#127818;</span>
                        <span class="tema-particle tema-particle--y4">&#127826;</span>
                        <span class="tema-particle tema-particle--y5">&#129744;</span>
                    <?php endif; ?>
                </div>

                <!-- Ana Ikon -->
                <div class="tema-preview-ikon">
                    <?= $ikon ?>
                </div>

                <!-- Renk Paleti -->
                <?php if (!empty($renkler)): ?>
                    <div class="tema-renk-paleti">
                        <?php foreach ($renkler as $renk): ?>
                            <?php /* Dinamik renk noktası — tema palet rengi, custom property */ ?>
                            <span class="tema-renk-noktasi" style="--u-tema-renk: <?= e($renk) ?>;"></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kart Icerigi -->
            <div class="tema-body">
                <div class="tema-body-header">
                    <h3><?= e($tema['isim']) ?></h3>
                    <span class="tema-status-badge <?= $isActive ? 'aktif' : 'inaktif' ?>">
                        <?= $isActive ? 'Aktif' : 'Inaktif' ?>
                    </span>
                </div>

                <p><?= e($tema['aciklama'] ?? '') ?></p>

                <!-- Toggle Switch -->
                <div class="tema-toggle-container">
                    <span class="tema-toggle-label">
                        <?= $isActive ? 'Tema aktif' : 'Temayi aktif et' ?>
                    </span>
                    <form method="POST" class="u-m-0">
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="tema_id" value="<?= (int)$tema['id'] ?>">
                        <input type="hidden" name="action" value="<?= $isActive ? 'deactivate' : 'activate' ?>">
                        <label class="tema-toggle">
                            <input type="checkbox"
                                   class="tema-toggle-input"
                                   <?= $isActive ? 'checked' : '' ?>
                                   aria-label="<?= e($tema['isim']) ?> temayi <?= $isActive ? 'kapat' : 'ac' ?>">
                            <span class="tema-toggle-slider"></span>
                        </label>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($temalar)): ?>
<div class="u-empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48" class="u-opacity-40 u-mb-4">
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 6v6l4 2"/>
    </svg>
    <p>Henuz tema tanimlanmamis. Veritabanina tema kayitlarini ekleyin.</p>
</div>
<?php endif; ?>

<script nonce="<?= getCspNonce() ?>">
document.querySelectorAll('.tema-toggle-input').forEach(function(input) {
    input.addEventListener('change', function() {
        this.closest('form').submit();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
