<?php
/**
 * Masa Siparis Yonetimi - Admin Panel
 * QR Menu sistemi siparis takibi ve durum yonetimi.
 * MVC: MasaSiparisService kullanir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

// Service instance
$siparisService = masa_siparis_service();

// POST islemleri (header'dan once)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: masa-siparisleri.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'durum_guncelle':
                $siparisId = (int)($_POST['siparis_id'] ?? 0);
                $yeniDurum = trim($_POST['yeni_durum'] ?? '');
                $result = $siparisService->siparisDurumGuncelle($siparisId, $yeniDurum);
                setFlash('success', $result['mesaj'] ?? 'Siparis durumu guncellendi.');
                break;

            case 'siparis_iptal':
                $siparisId = (int)($_POST['siparis_id'] ?? 0);
                $result = $siparisService->siparisIptal($siparisId);
                setFlash('success', $result['mesaj'] ?? 'Siparis iptal edildi.');
                break;
        }
    } catch (ValidationException $e) {
        setFlash('error', $e->getMessage());
    } catch (HttpException $e) {
        setFlash('error', $e->getMessage());
    } catch (\Exception $e) {
        setFlash('error', 'Bir hata olustu: ' . $e->getMessage());
    }

    $redirectFiltre = '';
    if (isset($_POST['filtre'])) {
        $gecerliPostFiltreler = ['tumu', 'beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];
        $redirectFiltre = in_array($_POST['filtre'], $gecerliPostFiltreler, true) ? $_POST['filtre'] : '';
    }
    header('Location: masa-siparisleri.php' . ($redirectFiltre !== '' ? '?filtre=' . urlencode($redirectFiltre) : ''));
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Filtre
$filtre = $_GET['filtre'] ?? 'tumu';
$gecerliFiltreler = ['tumu', 'beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];
if (!in_array($filtre, $gecerliFiltreler, true)) {
    $filtre = 'tumu';
}
$durumFiltre = ($filtre === 'tumu') ? null : $filtre;

// Verileri al
$istatistikler = $siparisService->getBugununIstatistikleri();
$siparisler = $siparisService->getBugununSiparisleri($durumFiltre);

// N+1 fix (Sprint 1): Tum siparislerin kalemlerini TEK sorguda getir
// Onceki: her kart render'inda ayri sorgu potansiyeli (veya bos kalem dizisi).
$siparisRepoN1 = new \Pastane\Repositories\MasaSiparisRepository();
$siparisIdsN1  = array_map(static fn ($s) => (int)$s['id'], $siparisler);
$kalemMapN1    = $siparisRepoN1->getKalemlerBySiparisIds($siparisIdsN1);
foreach ($siparisler as &$sRef) {
    $sRef['kalemler'] = $kalemMapN1[(int)$sRef['id']] ?? [];
}
unset($sRef);

// Aktif siparis sayisi (beklemede + onaylandi + hazirlaniyor + hazir)
$aktifSiparis = $istatistikler['beklemede'] + $istatistikler['onaylandi']
              + $istatistikler['hazirlaniyor'] + $istatistikler['hazir'];

// Durum etiketleri service'den alinir (DRY)
$durumEtiketleri = $siparisService->getDurumEtiketleri();

$durumRenkleri = [
    'beklemede'     => 'warning',
    'onaylandi'     => 'info',
    'hazirlaniyor'  => 'orange',
    'hazir'         => 'info',
    'teslim_edildi' => 'success',
    'iptal'         => 'danger',
];

$odemeDurumEtiketleri = [
    'odenmedi' => 'Odenmedi',
    'odendi'   => 'Odendi',
    'iade'     => 'Iade',
];
?>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
            <rect x="9" y="3" width="6" height="4" rx="1"/>
            <path d="M9 14l2 2 4-4"/>
        </svg>
        Masa Siparisleri
    </h2>
    <div class="page-header-actions">
        <button type="button" class="btn btn-secondary" id="yenileBtn" data-action="yenile" aria-label="Siparis listesini yenile">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"/>
                <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
            </svg>
            Yenile
        </button>
    </div>
</div>

<!-- Istatistik Kartlari -->
<div class="masa-stats">
    <div class="masa-stat-card siparis-stat-card--aktif">
        <div class="masa-stat-card__sayi"><?= $aktifSiparis ?></div>
        <div class="masa-stat-card__etiket">Aktif Siparis</div>
    </div>
    <div class="masa-stat-card siparis-stat-card--beklemede">
        <div class="masa-stat-card__sayi"><?= $istatistikler['beklemede'] ?></div>
        <div class="masa-stat-card__etiket">Bekleyen</div>
    </div>
    <div class="masa-stat-card siparis-stat-card--onaylandi">
        <div class="masa-stat-card__sayi"><?= $istatistikler['onaylandi'] ?></div>
        <div class="masa-stat-card__etiket">Onaylandi</div>
    </div>
    <div class="masa-stat-card siparis-stat-card--hazirlaniyor">
        <div class="masa-stat-card__sayi"><?= $istatistikler['hazirlaniyor'] ?></div>
        <div class="masa-stat-card__etiket">Hazirlanan</div>
    </div>
    <div class="masa-stat-card siparis-stat-card--hazir">
        <div class="masa-stat-card__sayi"><?= $istatistikler['hazir'] ?></div>
        <div class="masa-stat-card__etiket">Hazir</div>
    </div>
    <div class="masa-stat-card siparis-stat-card--teslim">
        <div class="masa-stat-card__sayi"><?= $istatistikler['teslim_edildi'] ?></div>
        <div class="masa-stat-card__etiket">Bugun Teslim</div>
    </div>
</div>

<!-- Filtre Butonlari -->
<div class="siparis-filtre">
    <a href="?filtre=tumu" class="btn btn-sm <?= $filtre === 'tumu' ? 'btn-primary' : 'btn-ghost' ?>">Tumu</a>
    <a href="?filtre=beklemede" class="btn btn-sm <?= $filtre === 'beklemede' ? 'btn-primary' : 'btn-ghost' ?>">Beklemede</a>
    <a href="?filtre=onaylandi" class="btn btn-sm <?= $filtre === 'onaylandi' ? 'btn-primary' : 'btn-ghost' ?>">Onaylandi</a>
    <a href="?filtre=hazirlaniyor" class="btn btn-sm <?= $filtre === 'hazirlaniyor' ? 'btn-primary' : 'btn-ghost' ?>">Hazirlaniyor</a>
    <a href="?filtre=hazir" class="btn btn-sm <?= $filtre === 'hazir' ? 'btn-primary' : 'btn-ghost' ?>">Hazir</a>
    <a href="?filtre=teslim_edildi" class="btn btn-sm <?= $filtre === 'teslim_edildi' ? 'btn-primary' : 'btn-ghost' ?>">Teslim Edildi</a>
    <a href="?filtre=iptal" class="btn btn-sm <?= $filtre === 'iptal' ? 'btn-primary' : 'btn-ghost' ?>">Iptal</a>
</div>

<!-- Siparis Listesi -->
<?php if (empty($siparisler)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                    </svg>
                </div>
                <h3>Siparis bulunamadi</h3>
                <p>Bu filtreye uygun siparis bulunmamaktadir.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="siparis-grid" id="siparisGrid">
        <?php foreach ($siparisler as $siparis):
            $durum = $siparis['durum'] ?? 'beklemede';
            $durumLabel = $durumEtiketleri[$durum] ?? $durum;
            $durumRenk = $durumRenkleri[$durum] ?? 'info';
            $masaNo = (int)($siparis['masa_no'] ?? 0);
            $siparisId = (int)$siparis['id'];
            $toplamTutar = (float)($siparis['toplam_tutar'] ?? 0);
            $odemeDurumu = $siparis['odeme_durumu'] ?? 'odenmedi';
            $siparisNotu = $siparis['siparis_notu'] ?? '';
            $kalemler = $siparis['kalemler'] ?? [];

            // Zaman hesaplama
            $siparisZamani = strtotime($siparis['siparis_zamani'] ?? 'now');
            $gecenDakika = max(0, (int)((time() - $siparisZamani) / 60));
            $saatStr = date('H:i', $siparisZamani);
            if ($gecenDakika < 60) {
                $zamanLabel = $saatStr . ' (' . $gecenDakika . ' dk once)';
            } else {
                $saat = (int)($gecenDakika / 60);
                $zamanLabel = $saatStr . ' (' . $saat . ' saat once)';
            }
        ?>
            <div class="siparis-kart siparis-kart--<?= e($durum) ?>" data-siparis-id="<?= $siparisId ?>">
                <!-- Kart Ust -->
                <div class="siparis-kart__ust">
                    <div class="siparis-kart__masa">
                        <span class="siparis-kart__masa-badge">Masa <?= $masaNo ?></span>
                        <span class="siparis-kart__no">#<?= $siparisId ?></span>
                    </div>
                    <div class="siparis-kart__zaman">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <?= e($zamanLabel) ?>
                    </div>
                </div>

                <!-- Durum Badge -->
                <div class="siparis-kart__durum-bar">
                    <span class="siparis-kart__durum-badge siparis-kart__durum-badge--<?= e($durumRenk) ?>">
                        <?= e($durumLabel) ?>
                    </span>
                    <?php if (($siparis['odeme_yontemi'] ?? '') === 'masada'): ?>
                        <span class="siparis-kart__odeme-badge siparis-kart__odeme-badge--masada">Masada Odeme</span>
                    <?php endif; ?>
                </div>

                <!-- Kart Orta - Urun Listesi -->
                <div class="siparis-kart__icerik">
                    <ul class="siparis-kart__urunler">
                        <?php foreach ($kalemler as $kalem):
                            $urunAdi = $kalem['urun_adi'] ?? 'Bilinmeyen Urun';
                            $adet = (int)($kalem['adet'] ?? 1);
                            $porsiyon = $kalem['porsiyon'] ?? null;
                            $ozelNot = $kalem['ozel_not'] ?? '';
                        ?>
                            <li class="siparis-kart__urun">
                                <div class="siparis-kart__urun-bilgi">
                                    <span class="siparis-kart__urun-adet"><?= $adet ?>x</span>
                                    <span class="siparis-kart__urun-adi"><?= e($urunAdi) ?></span>
                                    <?php if ($porsiyon): ?>
                                        <span class="siparis-kart__urun-porsiyon">(<?= e($porsiyon) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ozelNot)): ?>
                                    <div class="siparis-kart__urun-not">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                                        </svg>
                                        <?= e($ozelNot) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($siparisNotu)): ?>
                        <div class="siparis-kart__siparis-notu">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <?= e($siparisNotu) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Kart Alt -->
                <div class="siparis-kart__alt">
                    <div class="siparis-kart__tutar">
                        <?= number_format($toplamTutar, 2, ',', '.') ?> TL
                    </div>
                    <span class="siparis-kart__odeme-badge siparis-kart__odeme-badge--<?= e($odemeDurumu) ?>">
                        <?= e($odemeDurumEtiketleri[$odemeDurumu] ?? $odemeDurumu) ?>
                    </span>
                </div>

                <!-- Durum Degistirme Butonlari -->
                <div class="siparis-kart__aksiyonlar">
                    <?php if ($durum === 'beklemede'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="durum_guncelle">
                            <input type="hidden" name="siparis_id" value="<?= $siparisId ?>">
                            <input type="hidden" name="yeni_durum" value="onaylandi">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <button type="submit" class="btn btn-sm btn-success" aria-label="Siparis #<?= $siparisId ?> onayla">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Onayla
                            </button>
                        </form>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="siparis_iptal">
                            <input type="hidden" name="siparis_id" value="<?= $siparisId ?>">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    aria-label="Siparis #<?= $siparisId ?> iptal et"
                                    data-siparis-id="<?= $siparisId ?>"
                                    data-action="iptal-onayla">Iptal Et</button>
                        </form>

                    <?php elseif ($durum === 'onaylandi'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="durum_guncelle">
                            <input type="hidden" name="siparis_id" value="<?= $siparisId ?>">
                            <input type="hidden" name="yeni_durum" value="hazirlaniyor">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <button type="submit" class="btn btn-sm siparis-btn--hazirlaniyor" aria-label="Siparis #<?= $siparisId ?> hazirlaniyor olarak isaretle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                </svg>
                                Hazirlaniyor
                            </button>
                        </form>

                    <?php elseif ($durum === 'hazirlaniyor'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="durum_guncelle">
                            <input type="hidden" name="siparis_id" value="<?= $siparisId ?>">
                            <input type="hidden" name="yeni_durum" value="hazir">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <button type="submit" class="btn btn-sm btn-info" aria-label="Siparis #<?= $siparisId ?> hazir olarak isaretle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Hazir
                            </button>
                        </form>

                    <?php elseif ($durum === 'hazir'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="durum_guncelle">
                            <input type="hidden" name="siparis_id" value="<?= $siparisId ?>">
                            <input type="hidden" name="yeni_durum" value="teslim_edildi">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <button type="submit" class="btn btn-sm btn-success" aria-label="Siparis #<?= $siparisId ?> teslim edildi olarak isaretle">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Teslim Edildi
                            </button>
                        </form>

                    <?php elseif ($durum === 'teslim_edildi'): ?>
                        <span class="siparis-kart__son-durum siparis-kart__son-durum--success">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Teslim Edildi
                        </span>

                    <?php elseif ($durum === 'iptal'): ?>
                        <span class="siparis-kart__son-durum siparis-kart__son-durum--danger">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Iptal Edildi
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Iptal Onay Modal -->
<div class="modal-overlay" id="iptalModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-danger">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Siparis Iptal
            </h3>
            <button class="modal-close" data-action="iptal-kapat" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body sil-modal-body">
            <p class="sil-modal-body__bilgi">
                <strong id="iptalSiparisNo"></strong> numarali siparisi iptal etmek istediginize emin misiniz?
            </p>
            <p class="sil-modal-body__uyari">
                Bu islem geri alinamaz.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="iptal-kapat">Vazgec</button>
            <button type="button" class="btn btn-danger" id="iptalOnaylaBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                Iptal Et
            </button>
        </div>
    </div>
</div>

<style nonce="<?= getCspNonce() ?>">
.yenileme-toast {
    position: fixed;
    top: 16px;
    right: 16px;
    background: var(--admin-primary);
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 9999;
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
}
.yenileme-toast.active {
    opacity: 1;
    transform: translateY(0);
}
.yenileme-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: yenileme-spin 0.6s linear infinite;
}
@keyframes yenileme-spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="yenileme-toast" id="yenilemeToast">
    <div class="yenileme-spinner"></div>
    Yenileniyor...
</div>

<script nonce="<?= getCspNonce() ?>">
(function() {
    'use strict';

    var toast = document.getElementById('yenilemeToast');

    function showLoading() {
        toast.classList.add('active');
    }

    function hideLoading() {
        toast.classList.remove('active');
    }

    /**
     * AJAX ile siparis kartlarini yenile (full page reload yerine)
     */
    function ajaxYenile() {
        showLoading();
        var filtre = new URLSearchParams(window.location.search).get('filtre') || 'tumu';
        var url = window.location.pathname + '?filtre=' + encodeURIComponent(filtre);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status === 200) {
                // Gelen HTML'den sadece ilgili alanlari guncelle
                var parser = new DOMParser();
                var doc = parser.parseFromString(xhr.responseText, 'text/html');

                // Istatistik kartlarini guncelle
                var yeniStats = doc.querySelector('.masa-stats');
                var mevcutStats = document.querySelector('.masa-stats');
                if (yeniStats && mevcutStats) {
                    mevcutStats.innerHTML = yeniStats.innerHTML;
                }

                // Siparis grid'ini guncelle
                var yeniGrid = doc.querySelector('#siparisGrid');
                var mevcutGrid = document.querySelector('#siparisGrid');
                var yeniEmpty = doc.querySelector('.empty-state');
                var mevcutContainer = mevcutGrid ? mevcutGrid.parentElement : document.querySelector('.card');

                if (yeniGrid && mevcutGrid) {
                    mevcutGrid.innerHTML = yeniGrid.innerHTML;
                    bindIptalEvents();
                } else if (yeniGrid && !mevcutGrid) {
                    // Bos state -> grid'e gecis
                    if (mevcutContainer) {
                        mevcutContainer.outerHTML = '<div class="siparis-grid" id="siparisGrid">' + yeniGrid.innerHTML + '</div>';
                        bindIptalEvents();
                    }
                } else if (!yeniGrid && mevcutGrid) {
                    // Grid -> bos state'e gecis
                    var emptyCard = doc.querySelector('.card .empty-state');
                    if (emptyCard) {
                        mevcutGrid.outerHTML = emptyCard.closest('.card').outerHTML;
                    }
                }
            }
            hideLoading();
        };
        xhr.onerror = function() {
            hideLoading();
        };
        xhr.send();
    }

    // ========================================
    // Iptal Onay Modal
    // ========================================
    var iptalForm = null;

    function bindIptalEvents() {
        document.querySelectorAll('[data-action="iptal-onayla"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var siparisId = this.dataset.siparisId;
                iptalForm = this.closest('form');
                document.getElementById('iptalSiparisNo').textContent = '#' + siparisId;
                document.getElementById('iptalModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });
    }

    bindIptalEvents();

    document.getElementById('iptalOnaylaBtn').addEventListener('click', function() {
        if (iptalForm) {
            iptalForm.submit();
        }
    });

    document.querySelectorAll('[data-action="iptal-kapat"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('iptalModal').classList.remove('active');
            document.body.style.overflow = '';
            iptalForm = null;
        });
    });

    // Overlay click ile kapat
    document.getElementById('iptalModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
            iptalForm = null;
        }
    });

    // ESC ile kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('iptalModal').classList.remove('active');
            document.body.style.overflow = '';
            iptalForm = null;
        }
    });

    // ========================================
    // Manuel Yenileme
    // ========================================
    document.getElementById('yenileBtn').addEventListener('click', function() {
        ajaxYenile();
    });

    // ========================================
    // Otomatik Yenileme (30 saniye) — AJAX ile
    // ========================================
    var yenilemeInterval = setInterval(function() {
        ajaxYenile();
    }, 30000);

    // Sayfa gorunmez oldugunda yenilemeyi durdur
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(yenilemeInterval);
        } else {
            yenilemeInterval = setInterval(function() {
                ajaxYenile();
            }, 30000);
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
