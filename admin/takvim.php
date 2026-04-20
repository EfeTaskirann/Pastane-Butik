<?php
/**
 * Admin - Takvim ve Sipariş Yönetimi
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// SiparisService kullan
$siparisService = siparis_service();

// Puan ayarlarını al
$puanAyarlari = $siparisService->getPuanAyarlari();

// Kategori varsayılan fiyatları
$kategoriFiyatlari = $siparisService->getKategoriFiyatlari();

// Sipariş ekleme/silme (header'dan önce)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Token kontrolü - GÜVENLİK
    if (!verifyCSRF()) {
        setFlash('error', 'Güvenlik doğrulaması başarısız. Lütfen tekrar deneyin.');
        header('Location: takvim.php');
        exit;
    }

    if ($_POST['action'] === 'ekle') {
        $tarih = $_POST['tarih'] ?? '';
        $kategori = $_POST['kategori'] ?? '';

        if ($tarih && $kategori) {
            try {
                $siparisService->create([
                    'tarih' => $tarih,
                    'kategori' => $kategori,
                    'adet' => (int)($_POST['adet'] ?? 1),
                    'musteri_adi' => $_POST['musteri_adi'] ?? '',
                    'telefon' => trim($_POST['telefon'] ?? ''),
                    'adres' => $_POST['adres'] ?? '',
                    'notlar' => $_POST['notlar'] ?? '',
                    'birim_fiyat' => (float)($_POST['birim_fiyat'] ?? 0),
                    'odeme_tipi' => $_POST['odeme_tipi'] ?? 'online',
                    'kanal' => $_POST['kanal'] ?? 'site',
                    'ozel_puan' => (int)($_POST['ozel_puan'] ?? 0)
                ]);
                setFlash('success', 'Sipariş başarıyla eklendi.');
            } catch (Exception $e) {
                setFlash('error', 'Sipariş eklenirken hata oluştu: ' . $e->getMessage());
            }
        }
        header('Location: takvim.php');
        exit;
    }

    if ($_POST['action'] === 'sil' && isset($_POST['id'])) {
        try {
            $result = $siparisService->deleteOrArchive((int)$_POST['id']);
            setFlash('success', $result['mesaj']);
        } catch (Exception $e) {
            setFlash('error', 'Sipariş silinirken hata oluştu: ' . $e->getMessage());
        }
        header('Location: takvim.php');
        exit;
    }

    // Durum değiştir
    if ($_POST['action'] === 'durum_degistir' && isset($_POST['id'])) {
        $yeniDurum = $_POST['durum'] ?? 'beklemede';
        $siparisId = (int)$_POST['id'];

        try {
            $result = $siparisService->updateStatus($siparisId, $yeniDurum);
            setFlash('success', $result['mesaj']);
        } catch (Exception $e) {
            setFlash('error', 'Durum güncellenirken hata oluştu: ' . $e->getMessage());
        }
        $redirectTarih = $_POST['tarih'] ?? date('Y-m-d');
        // Tarih format dogrulama (YYYY-MM-DD) — header injection ve open redirect onlemi
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirectTarih)) {
            $redirectTarih = date('Y-m-d');
        }
        header('Location: takvim.php?tarih=' . urlencode($redirectTarih));
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Seçili tarih
$seciliTarih = $_GET['tarih'] ?? date('Y-m-d');

// Görüntülenen ay/yıl (URL'den veya seçili tarihten)
$gorunenAy = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('n', strtotime($seciliTarih));
$gorunenYil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y', strtotime($seciliTarih));

// Ay sınırları kontrolü
if ($gorunenAy < 1) {
    $gorunenAy = 12;
    $gorunenYil--;
} elseif ($gorunenAy > 12) {
    $gorunenAy = 1;
    $gorunenYil++;
}

// Önceki ve sonraki ay
$oncekiAy = $gorunenAy - 1;
$oncekiYil = $gorunenYil;
if ($oncekiAy < 1) {
    $oncekiAy = 12;
    $oncekiYil--;
}

$sonrakiAy = $gorunenAy + 1;
$sonrakiYil = $gorunenYil;
if ($sonrakiAy > 12) {
    $sonrakiAy = 1;
    $sonrakiYil++;
}

// Türkçe ay isimleri
$ayIsimleri = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

// Ayın ilk ve son günü
$ayinIlkGunu = sprintf('%04d-%02d-01', $gorunenYil, $gorunenAy);
$ayinSonGunu = date('Y-m-t', strtotime($ayinIlkGunu));
$ayinGunSayisi = (int)date('t', strtotime($ayinIlkGunu));

// Ayın ilk gününün haftanın hangi günü olduğu (1=Pazartesi, 7=Pazar)
$ilkGunHaftaGunu = (int)date('N', strtotime($ayinIlkGunu));

// O güne ait siparişler (service kullanarak)
$gunSiparisleri = $siparisService->getByDate($seciliTarih);

// `siparisler` tablosunda `durum` ENUM kolonu yok — sadece `tamamlandi` BOOL var.
// Modal JS `siparis.durum` field bekledigi icin server-side virtual field uret.
foreach ($gunSiparisleri as &$_s) {
    if (!isset($_s['durum'])) {
        $_s['durum'] = ((int)($_s['tamamlandi'] ?? 0) === 1) ? 'teslim_edildi' : 'beklemede';
    }
}
unset($_s);

// Günün toplam iş yükü (sadece bekleyen siparişler)
$gunToplamPuan = $siparisService->getDayWorkload($seciliTarih, true);

// Yoğunluk durumu (service'den)
$yogunlukKategori = $siparisService->getWorkloadCategory($gunToplamPuan);
$yogunluk = [
    'durum' => $yogunlukKategori['label'],
    'renk' => $yogunlukKategori['renk'],
    'class' => $yogunlukKategori['durum']
];

// Ay için takvim verisi (service kullanarak)
$takvimVerisi = $siparisService->getCalendarData($ayinIlkGunu, $ayinSonGunu);

// Boş günler için 0 değeri ata
for ($gun = 1; $gun <= $ayinGunSayisi; $gun++) {
    $tarih = sprintf('%04d-%02d-%02d', $gorunenYil, $gorunenAy, $gun);
    if (!isset($takvimVerisi[$tarih])) {
        $takvimVerisi[$tarih] = 0;
    }
}

$kategoriLabels = [
    'pasta' => 'Pasta',
    'cupcake' => 'Cupcake',
    'cheesecake' => 'Cheesecake',
    'kurabiye' => 'Kurabiye',
    'ozel' => 'Özel Sipariş'
];
?>

<style>
.takvim-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .takvim-container {
        grid-template-columns: 1fr;
    }
}

.takvim-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.takvim-header {
    text-align: center;
    font-weight: 600;
    color: var(--admin-text-light);
    padding: 0.5rem;
    font-size: 0.85rem;
}

.takvim-gun {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
    border: 2px solid transparent;
}

.takvim-gun:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.takvim-gun.secili {
    border-color: var(--admin-primary);
}

.takvim-gun .gun-sayi {
    font-weight: 600;
    font-size: 1.1rem;
}

.takvim-gun .gun-ay {
    font-size: 0.7rem;
    opacity: 0.7;
}

.takvim-gun .gun-adi {
    font-size: 0.65rem;
    opacity: 0.8;
    margin-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.takvim-gun.bos { background: #D8ECD8; color: #4A7A4A; }
.takvim-gun.uygun { background: #D8ECF8; color: #4A7A9A; }
.takvim-gun.yogun { background: #F8E8D0; color: #8A6A4A; }
.takvim-gun.dolu { background: #F0D8D8; color: #8A5A5A; }

.sidebar-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.panel-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header h3 {
    margin: 0;
    font-size: 1rem;
}

.yogunluk-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.yogunluk-badge.bos { background: #D8ECD8; color: #4A7A4A; }
.yogunluk-badge.uygun { background: #D8ECF8; color: #4A7A9A; }
.yogunluk-badge.yogun { background: #F8E8D0; color: #8A6A4A; }
.yogunluk-badge.dolu { background: #F0D8D8; color: #8A5A5A; }

.panel-body {
    padding: 1.5rem;
}

.puan-bar {
    height: 8px;
    background: #eee;
    border-radius: 4px;
    margin: 1rem 0;
    overflow: hidden;
}

.puan-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.siparis-form .form-group {
    margin-bottom: 1rem;
}

.siparis-form label {
    display: block;
    margin-bottom: 0.3rem;
    font-size: 0.9rem;
    color: var(--admin-text-light);
}

.siparis-form input,
.siparis-form select,
.siparis-form textarea {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.95rem;
}

.siparis-form input:focus,
.siparis-form select:focus {
    border-color: var(--admin-primary);
    outline: none;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.ozel-puan-group {
    display: none;
}

.ozel-puan-group.show {
    display: block;
}

.siparis-listesi {
    max-height: 300px;
    overflow-y: auto;
}

.siparis-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.siparis-item:last-child {
    margin-bottom: 0;
}

.siparis-info {
    flex: 1;
}

.siparis-info strong {
    display: block;
    font-size: 0.95rem;
}

.siparis-info small {
    color: var(--admin-text-light);
}

.siparis-notlar {
    margin-top: 0.4rem;
    padding: 0.4rem 0.6rem;
    background: #fff;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #666;
    border-left: 3px solid var(--admin-primary);
    line-height: 1.4;
}

.siparis-puan {
    background: var(--admin-primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-right: 0.5rem;
}

.btn-sil {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 0.3rem;
}

.btn-sil:hover {
    color: #a71d2a;
}

.legend {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.legend-color.bos { background: #B8D4B8; }
.legend-color.uygun { background: #B8D4E8; }
.legend-color.yogun { background: #F5D4B0; }
.legend-color.dolu { background: #E8C4C4; }

/* Sipariş Item - Tıklanabilir */
.siparis-item {
    cursor: pointer;
    transition: all 0.2s ease;
}

.siparis-item:hover {
    background: #e9ecef;
    transform: translateX(3px);
}

.siparis-item .siparis-info {
    overflow: hidden;
}

.siparis-item .siparis-info strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.siparis-item .siparis-notlar {
    display: none;
}

.siparis-item .siparis-ozet {
    font-size: 0.8rem;
    color: #888;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* Modal Stilleri */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

.modal-overlay.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #eee;
    background: linear-gradient(135deg, var(--admin-primary), #C4A4B4);
    color: white;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    color: white;
}

.modal-close:hover {
    background: rgba(255,255,255,0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

.modal-detail-row {
    display: flex;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.modal-detail-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.modal-detail-label {
    width: 120px;
    font-weight: 600;
    color: #666;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.modal-detail-value {
    flex: 1;
    color: #333;
    font-size: 0.95rem;
    word-break: break-word;
}

.modal-detail-value.notlar {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 10px;
    border-left: 4px solid var(--admin-primary);
    line-height: 1.6;
    white-space: pre-wrap;
}

.modal-puan-badge {
    display: inline-block;
    background: var(--admin-primary);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 1.1rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background: #f8f9fa;
}

.modal-footer .btn {
    padding: 0.6rem 1.2rem;
}

/* Tamamlanmış sipariş stilleri */
.siparis-item.tamamlandi {
    background: #e8f5e9;
    border-left: 3px solid #4caf50;
}

.siparis-item.tamamlandi .siparis-puan {
    background: #4caf50;
    text-decoration: line-through;
    opacity: 0.7;
}

.siparis-durum-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 0.5rem;
}

.siparis-durum-badge.devam {
    background: #fff3cd;
    color: #856404;
}

.siparis-durum-badge.tamamlandi {
    background: #d4edda;
    color: #155724;
}

.modal-durum-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.95rem;
}

.modal-durum-badge.devam {
    background: #fff3cd;
    color: #856404;
}

.modal-durum-badge.tamamlandi {
    background: #d4edda;
    color: #155724;
}

.modal-durum-badge svg {
    width: 18px;
    height: 18px;
}

.btn-tamamla {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-tamamla.devam-et {
    background: #4caf50;
    color: white;
}

.btn-tamamla.devam-et:hover {
    background: #43a047;
}

.btn-tamamla.geri-al {
    background: #ff9800;
    color: white;
}

.btn-tamamla.geri-al:hover {
    background: #f57c00;
}

/* Ay navigasyonu */
.takvim-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 12px 12px 0 0;
    border-bottom: 1px solid #eee;
}

.takvim-nav h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #333;
}

.takvim-nav-buttons {
    display: flex;
    gap: 0.5rem;
}

.takvim-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: #666;
}

.takvim-nav-btn:hover {
    background: var(--admin-primary);
    border-color: var(--admin-primary);
    color: white;
}

.takvim-nav-btn svg {
    width: 18px;
    height: 18px;
}

.btn-bugun {
    width: auto;
    padding: 0 1rem;
    font-size: 0.85rem;
}

.takvim-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.takvim-grid {
    padding: 1rem 1.5rem 1.5rem;
}

.takvim-gun .gun-ay {
    display: none; /* Ay içinde ay göstermeye gerek yok */
}

.takvim-gun.gecmis {
    opacity: 0.5;
}

.takvim-gun.bugun {
    border: 2px solid var(--admin-primary) !important;
    font-weight: 600;
}

.takvim-gun.bos-gun {
    background: transparent;
    cursor: default;
}

.takvim-gun.bos-gun:hover {
    transform: none;
    box-shadow: none;
}
</style>

<h2 class="u-mb-5">Takvim & Sipariş Yönetimi</h2>

<div class="takvim-container">
    <!-- Takvim -->
    <div>
        <div class="takvim-wrapper">
            <!-- Ay Navigasyonu -->
            <div class="takvim-nav">
                <div class="takvim-nav-buttons">
                    <a href="?ay=<?= $oncekiAy ?>&yil=<?= $oncekiYil ?>&tarih=<?= $seciliTarih ?>" class="takvim-nav-btn" title="Önceki Ay">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                    </a>
                </div>

                <h3><?= $ayIsimleri[$gorunenAy] ?> <?= $gorunenYil ?></h3>

                <div class="takvim-nav-buttons">
                    <a href="?ay=<?= date('n') ?>&yil=<?= date('Y') ?>&tarih=<?= date('Y-m-d') ?>" class="takvim-nav-btn btn-bugun" title="Bugün">
                        Bugün
                    </a>
                    <a href="?ay=<?= $sonrakiAy ?>&yil=<?= $sonrakiYil ?>&tarih=<?= $seciliTarih ?>" class="takvim-nav-btn" title="Sonraki Ay">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Takvim Grid -->
            <div class="takvim-grid">
                <div class="takvim-header">Pzt</div>
                <div class="takvim-header">Sal</div>
                <div class="takvim-header">Çar</div>
                <div class="takvim-header">Per</div>
                <div class="takvim-header">Cum</div>
                <div class="takvim-header">Cmt</div>
                <div class="takvim-header">Paz</div>

                <?php
                // Ayın başındaki boşluklar (Pazartesi'den önceki günler)
                for ($i = 1; $i < $ilkGunHaftaGunu; $i++) {
                    echo '<div class="takvim-gun bos-gun"></div>';
                }

                // Bugünün tarihi
                $bugun = date('Y-m-d');

                // Hafta günü isimleri
                $haftaGunleri = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

                // Ayın günlerini göster
                foreach ($takvimVerisi as $tarih => $puan):
                    $yd = getYogunlukDurumu($puan);
                    $secili = $tarih === $seciliTarih ? 'secili' : '';
                    $gecmis = $tarih < $bugun ? 'gecmis' : '';
                    $bugunMu = $tarih === $bugun ? 'bugun' : '';
                    $haftaGunuIdx = (int)date('w', strtotime($tarih));
                    $haftaGunuAdi = $haftaGunleri[$haftaGunuIdx];
                ?>
                    <a href="?ay=<?= $gorunenAy ?>&yil=<?= $gorunenYil ?>&tarih=<?= $tarih ?>" class="takvim-gun <?= $yd['class'] ?> <?= $secili ?> <?= $gecmis ?> <?= $bugunMu ?>" title="<?= (int)date('d', strtotime($tarih)) ?> <?= $ayIsimleri[$gorunenAy] ?> - <?= $haftaGunuAdi ?>">
                        <span class="gun-sayi"><?= (int)date('d', strtotime($tarih)) ?></span>
                        <span class="gun-adi"><?= $haftaGunuAdi ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="legend">
            <div class="legend-item"><div class="legend-color bos"></div> Boş (0-10)</div>
            <div class="legend-item"><div class="legend-color uygun"></div> Uygun (10-40)</div>
            <div class="legend-item"><div class="legend-color yogun"></div> Yoğun (40-80)</div>
            <div class="legend-item"><div class="legend-color dolu"></div> Dolu (80-100)</div>
        </div>
    </div>

    <!-- Sağ Panel -->
    <div>
        <!-- Seçili Gün Bilgisi -->
        <div class="sidebar-panel u-mb-4">
            <div class="panel-header">
                <h3><?= date('d', strtotime($seciliTarih)) ?> <?= $ayIsimleri[(int)date('n', strtotime($seciliTarih))] ?> <?= date('Y', strtotime($seciliTarih)) ?></h3>
                <span class="yogunluk-badge <?= $yogunluk['class'] ?>"><?= $yogunluk['durum'] ?></span>
            </div>
            <div class="panel-body">
                <div class="u-tr-header-center">
                    <?php /* Dinamik renk — PHP uretimi, CSS custom property ile */ ?>
                    <div class="u-text-2xl u-font-semibold" style="--u-renk: <?= e($yogunluk['renk']) ?>; color: var(--u-renk);"><?= $gunToplamPuan ?></div>
                    <small class="u-text-admin-light">/ 100 puan</small>
                </div>
                <div class="puan-bar">
                    <?php /* Dinamik genislik + renk — style ile CSS custom property atar */ ?>
                    <div class="puan-bar-fill u-progress-fill--colored" style="--u-progress: <?= (int)min($gunToplamPuan, 100) ?>%; --u-progress-color: <?= e($yogunluk['renk']) ?>;"></div>
                </div>
            </div>
        </div>

        <!-- Sipariş Ekle -->
        <div class="sidebar-panel u-mb-4">
            <div class="panel-header">
                <h3>Sipariş Ekle</h3>
            </div>
            <div class="panel-body">
                <form method="POST" class="siparis-form">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="ekle">
                    <input type="hidden" name="tarih" value="<?= $seciliTarih ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="kategori" id="kategoriSelect" required>
                                <option value="pasta">Pasta (<?= $puanAyarlari['pasta'] ?? 15 ?> puan)</option>
                                <option value="cupcake">Cupcake (<?= $puanAyarlari['cupcake'] ?? 8 ?> puan)</option>
                                <option value="cheesecake">Cheesecake (<?= $puanAyarlari['cheesecake'] ?? 12 ?> puan)</option>
                                <option value="kurabiye">Kurabiye (<?= $puanAyarlari['kurabiye'] ?? 6 ?> puan)</option>
                                <option value="ozel">Özel Sipariş</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Adet</label>
                            <input type="number" name="adet" value="1" min="1" required>
                        </div>
                    </div>

                    <div class="form-group ozel-puan-group" id="ozelPuanGroup">
                        <label>Özel Puan</label>
                        <input type="number" name="ozel_puan" value="20" min="1" max="100">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Birim Fiyat (₺)</label>
                            <input type="number" name="birim_fiyat" id="birimFiyat" step="0.01" min="0" placeholder="0.00">
                            <small class="u-text-admin-light">Özel sipariş için manuel girin</small>
                        </div>
                        <div class="form-group">
                            <label>Ödeme Tipi</label>
                            <select name="odeme_tipi">
                                <option value="online">Online (Site)</option>
                                <option value="fiziksel">Fiziksel (Mağaza)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sipariş Kanalı</label>
                        <select name="kanal">
                            <option value="site">Web Sitesi</option>
                            <option value="telefon">Telefon</option>
                            <option value="cafe">Cafe/Mağaza</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Müşteri Adı (opsiyonel)</label>
                        <input type="text" name="musteri_adi" placeholder="Müşteri adı...">
                    </div>

                    <div class="form-group">
                        <label>Telefon Numarası</label>
                        <input type="tel" name="telefon" placeholder="05XX XXX XX XX" pattern="[0-9]{10,11}">
                        <small class="u-text-admin-light">Sadakat programı için gerekli</small>
                    </div>

                    <div class="form-group">
                        <label>Adres (opsiyonel)</label>
                        <textarea name="adres" rows="2" placeholder="Teslimat adresi..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Notlar (opsiyonel)</label>
                        <textarea name="notlar" rows="2" placeholder="Sipariş notları..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary u-w-100">Sipariş Ekle</button>
                </form>
            </div>
        </div>

        <!-- O Günün Siparişleri -->
        <div class="sidebar-panel">
            <div class="panel-header">
                <h3>Siparişler (<?= count($gunSiparisleri) ?>)</h3>
            </div>
            <div class="panel-body">
                <?php if (empty($gunSiparisleri)): ?>
                    <p class="u-text-center u-text-admin-light">Bu tarihte sipariş yok.</p>
                <?php else: ?>
                    <div class="siparis-listesi">
                        <?php foreach ($gunSiparisleri as $index => $siparis):
                            $teslimEdildi = (bool)($siparis['tamamlandi'] ?? false);
                            $durumClass = $teslimEdildi ? 'tamamlandi' : '';
                            $durumLabels = [
                                'beklemede' => 'Beklemede',
                                'onaylandi' => 'Onaylandı',
                                'hazirlaniyor' => 'Hazırlanıyor',
                                'teslim_edildi' => 'Teslim Edildi',
                                'iptal' => 'İptal'
                            ];
                        ?>
                            <div class="siparis-item <?= $durumClass ?>" data-action="open-siparis-modal" data-index="<?= $index ?>" role="button" tabindex="0">
                                <div class="siparis-info">
                                    <strong>
                                        <?= $kategoriLabels[$siparis['kategori']] ?? $siparis['kategori'] ?> x<?= $siparis['adet'] ?? 1 ?>
                                        <?php if ($teslimEdildi): ?>
                                            <span class="siparis-durum-badge tamamlandi">Teslim Edildi</span>
                                        <?php endif; ?>
                                    </strong>
                                    <?php if ($siparis['musteri_adi'] ?? ''): ?>
                                        <small><?= e($siparis['musteri_adi']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($siparis['notlar'] || ($siparis['adres'] ?? '')): ?>
                                        <div class="siparis-ozet">
                                            <?= $siparis['notlar'] ? mb_substr(e($siparis['notlar']), 0, 30) . (mb_strlen($siparis['notlar']) > 30 ? '...' : '') : '' ?>
                                            <?= ($siparis['adres'] ?? '') ? '📍' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="siparis-puan"><?= ($siparis['puan'] ?? 0) * ($siparis['adet'] ?? 1) ?> p</span>
                                <form method="POST" class="siparis-sil-form u-d-inline" data-action="stop-propagation">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="action" value="sil">
                                    <input type="hidden" name="id" value="<?= $siparis['id'] ?>">
                                    <button type="submit" class="btn-sil" data-action="confirm-siparis-sil">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sipariş Detay Modal -->
<div class="modal-overlay" id="siparisModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Sipariş Detayı</h3>
            <button type="button" class="modal-close" data-action="close-siparis-modal" aria-label="Kapat">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-detail-row">
                <div class="modal-detail-label">Durum</div>
                <div class="modal-detail-value">
                    <span class="modal-durum-badge devam" id="modalDurumDevam">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Devam Ediyor
                    </span>
                    <span class="modal-durum-badge tamamlandi u-hidden" id="modalDurumTamamlandi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Tamamlandı
                    </span>
                </div>
            </div>
            <div class="modal-detail-row">
                <div class="modal-detail-label">Kategori</div>
                <div class="modal-detail-value" id="modalKategori"></div>
            </div>
            <div class="modal-detail-row">
                <div class="modal-detail-label">Adet</div>
                <div class="modal-detail-value" id="modalAdet"></div>
            </div>
            <div class="modal-detail-row">
                <div class="modal-detail-label">Puan</div>
                <div class="modal-detail-value">
                    <span class="modal-puan-badge" id="modalPuan"></span>
                    <small id="modalPuanNot" class="u-badge-link-success">(Sayılmıyor)</small>
                </div>
            </div>
            <div class="modal-detail-row" id="modalFiyatRow">
                <div class="modal-detail-label">Tutar</div>
                <div class="modal-detail-value">
                    <span id="modalBirimFiyat" class="u-text-soft"></span>
                    <strong id="modalToplamTutar" class="u-edit-icon"></strong>
                </div>
            </div>
            <div class="modal-detail-row" id="modalOdemeRow">
                <div class="modal-detail-label">Ödeme</div>
                <div class="modal-detail-value" id="modalOdemeTipi"></div>
            </div>
            <div class="modal-detail-row" id="modalKanalRow">
                <div class="modal-detail-label">Kanal</div>
                <div class="modal-detail-value" id="modalKanal"></div>
            </div>
            <div class="modal-detail-row u-hidden" id="modalMusteriRow">
                <div class="modal-detail-label">Müşteri</div>
                <div class="modal-detail-value" id="modalMusteri"></div>
            </div>
            <div class="modal-detail-row u-hidden" id="modalTelefonRow">
                <div class="modal-detail-label">Telefon</div>
                <div class="modal-detail-value" id="modalTelefon"></div>
            </div>
            <div class="modal-detail-row u-hidden" id="modalAdresRow">
                <div class="modal-detail-label">Adres</div>
                <div class="modal-detail-value notlar" id="modalAdres"></div>
            </div>
            <div class="modal-detail-row u-hidden" id="modalNotlarRow">
                <div class="modal-detail-label">Notlar</div>
                <div class="modal-detail-value notlar" id="modalNotlar"></div>
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="modalDurumForm" class="u-d-inline">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="durum_degistir">
                <input type="hidden" name="id" id="modalSiparisId">
                <input type="hidden" name="durum" id="modalDurumValue">
                <input type="hidden" name="tarih" value="<?= $seciliTarih ?>">
                <select id="modalDurumSelect" class="u-select-minimal">
                    <option value="beklemede">Beklemede</option>
                    <option value="onaylandi">Onaylandı</option>
                    <option value="hazirlaniyor">Hazırlanıyor</option>
                    <option value="teslim_edildi">Teslim Edildi</option>
                    <option value="iptal">İptal</option>
                </select>
                <button type="submit" class="btn btn-primary">
                    Durumu Güncelle
                </button>
            </form>
            <button type="button" class="btn btn-secondary" data-action="close-siparis-modal">Kapat</button>
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
(function() {
    'use strict';
    // Sipariş verileri
    const siparisler = <?= json_encode(array_values($gunSiparisleri), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const kategoriLabels = <?= json_encode($kategoriLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const kategoriFiyatlari = <?= json_encode($kategoriFiyatlari, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const siparisModal = document.getElementById('siparisModal');

    function openSiparisModal(index) {
        const siparis = siparisler[index];
        if (!siparis) return;

        const durum = siparis.durum || 'beklemede';
        const teslimEdildi = durum === 'teslim_edildi';
        const kisiSayisi = parseInt(siparis.adet) || 1;
        const puan = parseFloat(siparis.puan) || 0;

        document.getElementById('modalKategori').textContent = kategoriLabels[siparis.kategori] || siparis.kategori;
        document.getElementById('modalAdet').textContent = kisiSayisi;
        document.getElementById('modalPuan').textContent = (puan * kisiSayisi) + ' puan';

        // Fiyat bilgileri
        const birimFiyat = parseFloat(siparis.birim_fiyat) || 0;
        const toplamTutar = parseFloat(siparis.toplam_tutar) || 0;
        document.getElementById('modalBirimFiyat').textContent = kisiSayisi > 1 ? `${birimFiyat.toFixed(2)} ₺ x ${kisiSayisi} =` : '';
        document.getElementById('modalToplamTutar').textContent = `${toplamTutar.toFixed(2)} ₺`;

        // Ödeme tipi
        const odemeTipleri = {'online': 'Online (Site)', 'fiziksel': 'Fiziksel (Mağaza)'};
        document.getElementById('modalOdemeTipi').textContent = odemeTipleri[siparis.odeme_tipi] || siparis.odeme_tipi || 'Online';

        // Kanal
        const kanallar = {'site': 'Web Sitesi', 'telefon': 'Telefon', 'cafe': 'Cafe/Mağaza'};
        document.getElementById('modalKanal').textContent = kanallar[siparis.kanal] || siparis.kanal || 'Web Sitesi';

        // Durum gösterimi
        document.getElementById('modalDurumDevam').style.display = teslimEdildi ? 'none' : 'inline-flex';
        document.getElementById('modalDurumTamamlandi').style.display = teslimEdildi ? 'inline-flex' : 'none';
        document.getElementById('modalPuanNot').style.display = teslimEdildi ? 'inline' : 'none';

        // Form - mevcut durumu seç
        document.getElementById('modalSiparisId').value = siparis.id;
        document.getElementById('modalDurumSelect').value = durum;
        document.getElementById('modalDurumValue').value = durum;

        // Müşteri
        const musteriRow = document.getElementById('modalMusteriRow');
        if (siparis.musteri_adi) {
            document.getElementById('modalMusteri').textContent = siparis.musteri_adi;
            musteriRow.style.display = 'flex';
        } else {
            musteriRow.style.display = 'none';
        }

        // Telefon
        const telefonRow = document.getElementById('modalTelefonRow');
        if (siparis.telefon) {
            document.getElementById('modalTelefon').textContent = siparis.telefon;
            telefonRow.style.display = 'flex';
        } else {
            telefonRow.style.display = 'none';
        }

        // Adres (ozel_istekler alanında)
        const adresRow = document.getElementById('modalAdresRow');
        if (siparis.adres) {
            document.getElementById('modalAdres').textContent = siparis.adres;
            adresRow.style.display = 'flex';
        } else {
            adresRow.style.display = 'none';
        }

        // Notlar
        const notlarRow = document.getElementById('modalNotlarRow');
        if (siparis.notlar) {
            document.getElementById('modalNotlar').textContent = siparis.notlar;
            notlarRow.style.display = 'flex';
        } else {
            notlarRow.style.display = 'none';
        }

        if (siparisModal) siparisModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSiparisModal() {
        if (siparisModal) siparisModal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Event delegation (CSP uyumlu — inline onclick yerine)
    document.addEventListener('click', function(e) {
        // Siparis item tıklama (modal aç)
        const openTrigger = e.target.closest('[data-action="open-siparis-modal"]');
        if (openTrigger && !e.target.closest('.siparis-sil-form')) {
            const idx = parseInt(openTrigger.dataset.index, 10);
            if (!isNaN(idx)) openSiparisModal(idx);
            return;
        }
        // Silme butonu (form içinde) — bubbling'i durdur ki parent modal açmasın
        const stopTrigger = e.target.closest('[data-action="stop-propagation"]');
        if (stopTrigger) {
            e.stopPropagation();
        }
        // Modal kapat butonu
        if (e.target.closest('[data-action="close-siparis-modal"]')) {
            closeSiparisModal();
        }
    });

    // Klavye ile siparis item açma (erişilebilirlik)
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-action="open-siparis-modal"]')) {
            e.preventDefault();
            const idx = parseInt(e.target.dataset.index, 10);
            if (!isNaN(idx)) openSiparisModal(idx);
        }
    });

    // Silme onay dialog'u (submit öncesi confirm)
    document.addEventListener('submit', function(e) {
        if (e.target.matches('.siparis-sil-form')) {
            if (!window.confirm('Siparişi silmek istediğinize emin misiniz?')) {
                e.preventDefault();
            }
        }
    });

    // Durum select değişince hidden input'u güncelle
    const durumSelect = document.getElementById('modalDurumSelect');
    const durumValueInput = document.getElementById('modalDurumValue');
    if (durumSelect && durumValueInput) {
        durumSelect.addEventListener('change', function() {
            durumValueInput.value = this.value;
        });
    }

    // Modal dışına tıklayınca kapat
    if (siparisModal) {
        siparisModal.addEventListener('click', function(e) {
            if (e.target === this) closeSiparisModal();
        });
    }

    // ESC tuşuyla kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSiparisModal();
    });

    // Özel sipariş puan göster/gizle ve fiyat otomatik doldur
    const kategoriSelect = document.getElementById('kategoriSelect');
    if (kategoriSelect) {
        kategoriSelect.addEventListener('change', function() {
            const ozelGroup = document.getElementById('ozelPuanGroup');
            const birimFiyatInput = document.getElementById('birimFiyat');
            const kategori = this.value;

            if (kategori === 'ozel') {
                if (ozelGroup) ozelGroup.classList.add('show');
                if (birimFiyatInput) {
                    birimFiyatInput.value = '';
                    birimFiyatInput.placeholder = 'Manuel girin';
                    birimFiyatInput.required = true;
                }
            } else {
                if (ozelGroup) ozelGroup.classList.remove('show');
                if (birimFiyatInput) {
                    birimFiyatInput.value = kategoriFiyatlari[kategori] || '';
                    birimFiyatInput.placeholder = '0.00';
                    birimFiyatInput.required = false;
                }
            }
        });
    }

    // Sayfa yüklendiğinde ilk kategori için fiyat ayarla
    document.addEventListener('DOMContentLoaded', function() {
        const ks = document.getElementById('kategoriSelect');
        const bf = document.getElementById('birimFiyat');
        if (ks && bf) {
            const kategori = ks.value;
            if (kategori !== 'ozel') {
                bf.value = kategoriFiyatlari[kategori] || '';
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
