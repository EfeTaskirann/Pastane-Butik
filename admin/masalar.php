<?php
/**
 * Masa Yonetimi - Admin Panel
 * MVC: MasaService ve QrKodService kullanir
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

// Service instances
$masaService = masa_service();
$qrKodService = qr_kod_service();

// POST islemleri (header'dan once)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: masalar.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'masa_ekle':
                $masaNo = (int)($_POST['masa_no'] ?? 0);
                $kapasite = (int)($_POST['kapasite'] ?? 4);
                $konum = trim($_POST['konum'] ?? '') ?: null;
                $masaService->masaEkle($masaNo, $kapasite, $konum);
                setFlash('success', 'Masa basariyla eklendi.');
                break;

            case 'masa_guncelle':
                $id = (int)($_POST['masa_id'] ?? 0);
                $data = [
                    'masa_no'  => (int)($_POST['masa_no'] ?? 0),
                    'kapasite' => (int)($_POST['kapasite'] ?? 4),
                    'konum'    => trim($_POST['konum'] ?? '') ?: null,
                ];
                $masaService->masaGuncelle($id, $data);
                setFlash('success', 'Masa basariyla guncellendi.');
                break;

            case 'masa_sil':
                $id = (int)($_POST['masa_id'] ?? 0);
                $masaService->masaSil($id);
                setFlash('success', 'Masa basariyla silindi.');
                break;

            case 'masa_aktif_et':
                $id = (int)($_POST['masa_id'] ?? 0);
                $musteriSayisi = (int)($_POST['musteri_sayisi'] ?? 1);
                $masaService->masaAktifEt($id, $musteriSayisi);
                setFlash('success', 'Masa aktif edildi.');
                break;

            case 'masa_kapat':
                $id = (int)($_POST['masa_id'] ?? 0);
                $result = $masaService->masaKapat($id);
                setFlash('success', $result['mesaj'] ?? 'Masa kapatildi.');
                break;
        }
    } catch (ValidationException $e) {
        setFlash('error', $e->getMessage());
    } catch (HttpException $e) {
        setFlash('error', $e->getMessage());
    } catch (\Exception $e) {
        setFlash('error', 'Bir hata olustu: ' . $e->getMessage());
    }

    header('Location: masalar.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Masalari listele
$masalar = $masaService->getMasalar();

// Istatistikler
$toplamMasa = count($masalar);
$aktifMasa = count(array_filter($masalar, fn($m) => $m['durum'] === 'aktif'));
$bosMasa = count(array_filter($masalar, fn($m) => $m['durum'] === 'bos'));
$kapaliMasa = count(array_filter($masalar, fn($m) => $m['durum'] === 'kapali'));
?>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Masa Yonetimi
    </h2>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" id="btnYeniMasaEkle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Yeni Masa Ekle
        </button>
    </div>
</div>

<!-- Stats -->
<div class="masa-stats">
    <div class="masa-stat-card masa-stat-card--toplam">
        <div class="masa-stat-card__sayi"><?= $toplamMasa ?></div>
        <div class="masa-stat-card__etiket">Toplam Masa</div>
    </div>
    <div class="masa-stat-card masa-stat-card--aktif">
        <div class="masa-stat-card__sayi"><?= $aktifMasa ?></div>
        <div class="masa-stat-card__etiket">Aktif Masa</div>
    </div>
    <div class="masa-stat-card masa-stat-card--bos">
        <div class="masa-stat-card__sayi"><?= $bosMasa ?></div>
        <div class="masa-stat-card__etiket">Bos Masa</div>
    </div>
    <div class="masa-stat-card masa-stat-card--kapali">
        <div class="masa-stat-card__sayi"><?= $kapaliMasa ?></div>
        <div class="masa-stat-card__etiket">Kapali Masa</div>
    </div>
</div>

<!-- Masa Grid -->
<?php if (empty($masalar)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </div>
                <h3>Henuz masa eklenmemis</h3>
                <p>Ilk masanizi ekleyerek baslayabilirsiniz.</p>
                <button type="button" class="btn btn-primary" id="btnYeniMasaEkleEmpty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Yeni Masa Ekle
                </button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="masa-grid">
        <?php foreach ($masalar as $masa):
            $durum = $masa['durum'] ?? 'bos';
            $durumLabel = match($durum) {
                'aktif' => 'Aktif',
                'kapali' => 'Kapali',
                default => 'Bos',
            };
            $konumLabel = match($masa['konum'] ?? '') {
                'dis_mekan' => 'Dis Mekan',
                'teras' => 'Teras',
                default => 'Ic Mekan',
            };
        ?>
            <div class="masa-card masa-card--<?= $durum ?>">
                <div class="masa-card__numara"><?= (int)$masa['masa_no'] ?></div>
                <div class="masa-card__bilgi">
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        <?= (int)$masa['kapasite'] ?> Kisi
                    </span>
                    <span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <?= e($konumLabel) ?>
                    </span>
                </div>
                <div class="masa-card__durum"><?= $durumLabel ?></div>

                <!-- Aksiyon Butonlari -->
                <div class="masa-card__aksiyonlar">
                    <?php if ($durum === 'bos'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="masa_aktif_et">
                            <input type="hidden" name="masa_id" value="<?= (int)$masa['id'] ?>">
                            <input type="hidden" name="musteri_sayisi" value="1">
                            <button type="submit" class="btn btn-sm btn-success">Aktif Et</button>
                        </form>
                    <?php elseif ($durum === 'aktif'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="masa_kapat">
                            <input type="hidden" name="masa_id" value="<?= (int)$masa['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Kapat</button>
                        </form>
                        <?php if (!empty($masa['qr_token'])): ?>
                        <button type="button" class="btn btn-sm btn-info js-qr-kod"
                                data-masa-id="<?= (int)$masa['id'] ?>"
                                data-masa-no="<?= (int)$masa['masa_no'] ?>"
                                data-qr-token="<?= e($masa['qr_token'] ?? '') ?>">QR</button>
                        <?php endif; ?>
                    <?php elseif ($durum === 'kapali'): ?>
                        <form method="POST" class="form-inline">
                            <?= csrfTokenField() ?>
                            <input type="hidden" name="action" value="masa_aktif_et">
                            <input type="hidden" name="masa_id" value="<?= (int)$masa['id'] ?>">
                            <input type="hidden" name="musteri_sayisi" value="1">
                            <button type="submit" class="btn btn-sm btn-success">Ac</button>
                        </form>
                    <?php endif; ?>

                    <button type="button" class="btn btn-sm btn-ghost btn-icon js-duzenle" data-tooltip="Duzenle" aria-label="Masa <?= (int)$masa['masa_no'] ?> duzenle"
                            data-masa-id="<?= (int)$masa['id'] ?>"
                            data-masa-no="<?= (int)$masa['masa_no'] ?>"
                            data-kapasite="<?= (int)$masa['kapasite'] ?>"
                            data-konum="<?= e($masa['konum'] ?? 'ic_mekan') ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost btn-icon text-danger js-sil" data-tooltip="Sil" aria-label="Masa <?= (int)$masa['masa_no'] ?> sil"
                            data-masa-id="<?= (int)$masa['id'] ?>"
                            data-masa-no="<?= (int)$masa['masa_no'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>

                    <?php if ($durum !== 'aktif' && !empty($masa['qr_token'])): ?>
                        <button type="button" class="btn btn-sm btn-ghost btn-icon js-qr-kod" data-tooltip="QR Kod" aria-label="Masa <?= (int)$masa['masa_no'] ?> QR Kod"
                                data-masa-id="<?= (int)$masa['id'] ?>"
                                data-masa-no="<?= (int)$masa['masa_no'] ?>"
                                data-qr-token="<?= e($masa['qr_token'] ?? '') ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"/>
                                <rect x="14" y="3" width="7" height="7"/>
                                <rect x="3" y="14" width="7" height="7"/>
                                <circle cx="17.5" cy="17.5" r="3.5"/>
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Masa Ekle/Duzenle Modal -->
<div class="modal-overlay" id="masaFormModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3 id="masaFormTitle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <span id="masaFormTitleText">Yeni Masa Ekle</span>
            </h3>
            <button class="modal-close js-masa-form-kapat" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form method="POST" id="masaForm">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" id="masaFormAction" value="masa_ekle">
            <input type="hidden" name="masa_id" id="masaFormId" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label for="masa_no">
                        Masa No <span class="required">*</span>
                    </label>
                    <input type="number"
                           id="masa_no"
                           name="masa_no"
                           class="form-control"
                           required
                           min="1"
                           placeholder="Ornek: 1">
                </div>

                <div class="form-group">
                    <label for="kapasite">
                        Kapasite <span class="required">*</span>
                    </label>
                    <input type="number"
                           id="kapasite"
                           name="kapasite"
                           class="form-control"
                           required
                           min="1"
                           max="50"
                           value="4"
                           placeholder="1-50 arasi">
                    <span class="form-hint">Masanin kac kisilik oldugunu belirtin (1-50)</span>
                </div>

                <div class="form-group">
                    <label for="konum">Konum</label>
                    <select id="konum" name="konum" class="form-control">
                        <option value="ic_mekan">Ic Mekan</option>
                        <option value="dis_mekan">Dis Mekan</option>
                        <option value="teras">Teras</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary js-masa-form-kapat">Iptal</button>
                <button type="submit" class="btn btn-primary" id="masaFormSubmitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span id="masaFormSubmitText">Masa Ekle</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Silme Onay Modal -->
<div class="modal-overlay" id="silModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-danger">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Masa Sil
            </h3>
            <button class="modal-close js-sil-modal-kapat" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body sil-modal-body">
            <p class="sil-modal-body__bilgi">
                <strong id="silMasaNo"></strong> numarali masayi silmek istediginize emin misiniz?
            </p>
            <p class="sil-modal-body__uyari">
                Bu islem geri alinamaz.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary js-sil-modal-kapat">Iptal</button>
            <button type="button" class="btn btn-danger" id="btnSilOnayla">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
                Sil
            </button>
        </div>
    </div>
</div>

<!-- Silme Formu (Gizli) -->
<form id="silForm" method="POST" class="form-hidden">
    <?= csrfTokenField() ?>
    <input type="hidden" name="action" value="masa_sil">
    <input type="hidden" name="masa_id" id="silMasaId">
</form>

<!-- QR Kod Modal -->
<div class="modal-overlay" id="qrModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <circle cx="17.5" cy="17.5" r="3.5"/>
                </svg>
                <span id="qrMasaBaslik">QR Kod</span>
            </h3>
            <button class="modal-close js-qr-modal-kapat" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body qr-modal">
            <div class="qr-modal__image">
                <img id="qrKodImg" src="" alt="QR Kod">
            </div>
            <p class="text-muted">
                Bu QR kodu masanin uzerine yerlestirin. Musteriler tarayarak menuye ulasabilir.
            </p>
        </div>
        <div class="modal-footer qr-modal__actions">
            <a id="qrIndirBtn" href="" download="" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Indir
            </a>
            <button type="button" class="btn btn-secondary" id="btnQrYazdir">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Yazdir
            </button>
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
(function() {
    'use strict';

    var baseUrl = <?= json_encode(config('app.url', 'http://localhost/pastane'), JSON_UNESCAPED_SLASHES) ?>;

    // ========================================
    // Modal Yardimci Fonksiyonlar
    // ========================================
    function modalAc(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function modalKapat(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.style.overflow = '';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================
    // Masa Ekle Modal
    // ========================================
    function masaEkleModalAc() {
        document.getElementById('masaFormAction').value = 'masa_ekle';
        document.getElementById('masaFormId').value = '';
        document.getElementById('masaFormTitleText').textContent = 'Yeni Masa Ekle';
        document.getElementById('masaFormSubmitText').textContent = 'Masa Ekle';
        document.getElementById('masa_no').value = '';
        document.getElementById('kapasite').value = '4';
        document.getElementById('konum').value = 'ic_mekan';
        modalAc('masaFormModal');
        document.getElementById('masa_no').focus();
    }

    // Yeni Masa Ekle butonlari
    var btnYeni = document.getElementById('btnYeniMasaEkle');
    if (btnYeni) btnYeni.addEventListener('click', masaEkleModalAc);

    var btnYeniEmpty = document.getElementById('btnYeniMasaEkleEmpty');
    if (btnYeniEmpty) btnYeniEmpty.addEventListener('click', masaEkleModalAc);

    // ========================================
    // Duzenle Butonlari (event delegation)
    // ========================================
    document.querySelectorAll('.js-duzenle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('masaFormAction').value = 'masa_guncelle';
            document.getElementById('masaFormId').value = this.dataset.masaId;
            document.getElementById('masaFormTitleText').textContent = 'Masa Duzenle';
            document.getElementById('masaFormSubmitText').textContent = 'Kaydet';
            document.getElementById('masa_no').value = this.dataset.masaNo;
            document.getElementById('kapasite').value = this.dataset.kapasite;
            document.getElementById('konum').value = this.dataset.konum || 'ic_mekan';
            modalAc('masaFormModal');
            document.getElementById('masa_no').focus();
        });
    });

    // Masa Form Kapat butonlari
    document.querySelectorAll('.js-masa-form-kapat').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalKapat('masaFormModal');
        });
    });

    // ========================================
    // Silme Modal
    // ========================================
    var silId = null;

    document.querySelectorAll('.js-sil').forEach(function(btn) {
        btn.addEventListener('click', function() {
            silId = this.dataset.masaId;
            document.getElementById('silMasaNo').textContent = 'Masa ' + this.dataset.masaNo;
            modalAc('silModal');
        });
    });

    document.querySelectorAll('.js-sil-modal-kapat').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalKapat('silModal');
            silId = null;
        });
    });

    var btnSilOnayla = document.getElementById('btnSilOnayla');
    if (btnSilOnayla) {
        btnSilOnayla.addEventListener('click', function() {
            if (silId) {
                document.getElementById('silMasaId').value = silId;
                document.getElementById('silForm').submit();
            }
        });
    }

    // ========================================
    // QR Kod Modal
    // ========================================
    document.querySelectorAll('.js-qr-kod').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var masaNo = this.dataset.masaNo;
            var qrToken = this.dataset.qrToken;
            var menuUrl = baseUrl + '/menu?t=' + encodeURIComponent(qrToken);
            var qrImgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(menuUrl);

            document.getElementById('qrMasaBaslik').textContent = 'Masa ' + masaNo + ' - QR Kod';
            document.getElementById('qrKodImg').src = qrImgUrl;
            document.getElementById('qrIndirBtn').href = qrImgUrl;
            document.getElementById('qrIndirBtn').download = 'masa-' + masaNo + '-qr.png';
            modalAc('qrModal');
        });
    });

    document.querySelectorAll('.js-qr-modal-kapat').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalKapat('qrModal');
        });
    });

    var btnQrYazdir = document.getElementById('btnQrYazdir');
    if (btnQrYazdir) {
        btnQrYazdir.addEventListener('click', function() {
            var imgSrc = document.getElementById('qrKodImg').src;
            var baslik = document.getElementById('qrMasaBaslik').textContent;
            var w = window.open('', '_blank', 'width=400,height=500');
            w.document.write('<html><head><title>' + escapeHtml(baslik) + '</title>');
            w.document.write('<style>body{text-align:center;font-family:Arial,sans-serif;padding:40px;}h2{margin-bottom:20px;}img{width:300px;height:300px;}</style>');
            w.document.write('</head><body>');
            w.document.write('<h2>' + escapeHtml(baslik) + '</h2>');
            w.document.write('<img src="' + escapeHtml(imgSrc) + '">');
            w.document.write('</body></html>');
            w.document.close();
            w.onload = function() { w.print(); };
        });
    }

    // ========================================
    // Modal Genel Olaylar
    // ========================================
    // Overlay click ile kapat
    ['masaFormModal', 'silModal', 'qrModal'].forEach(function(modalId) {
        var el = document.getElementById(modalId);
        if (el) {
            el.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                    if (modalId === 'silModal') silId = null;
                }
            });
        }
    });

    // ESC ile kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modalKapat('masaFormModal');
            modalKapat('silModal');
            modalKapat('qrModal');
            silId = null;
        }
    });

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
