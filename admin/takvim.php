<?php
/**
 * Admin - Takvim ve Sipariş Yönetimi
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Puan ayarlarını al
$puanAyarlari = [];
try {
    $ayarlar = db()->fetchAll("SELECT kategori, puan FROM siparis_puan_ayarlari");
    foreach ($ayarlar as $a) {
        $puanAyarlari[$a['kategori']] = $a['puan'];
    }
} catch (Exception $e) {
    // Tablo yoksa varsayılan değerler
    $puanAyarlari = ['pasta' => 15, 'cupcake' => 8, 'cheesecake' => 12, 'kurabiye' => 6, 'ozel' => 20];
}

// Sipariş ekleme/silme (header'dan önce)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ekle') {
        $tarih = $_POST['tarih'] ?? '';
        $kategori = $_POST['kategori'] ?? '';
        $adet = (int)($_POST['adet'] ?? 1);
        $musteriAdi = $_POST['musteri_adi'] ?? '';
        $telefon = trim($_POST['telefon'] ?? '');
        $adres = $_POST['adres'] ?? '';
        $notlar = $_POST['notlar'] ?? '';

        // Özel sipariş için puan manuel girilir
        if ($kategori === 'ozel') {
            $puan = (int)($_POST['ozel_puan'] ?? $puanAyarlari['ozel']);
        } else {
            $puan = $puanAyarlari[$kategori] ?? 10;
        }

        if ($tarih && $kategori) {
            try {
                db()->insert('siparisler', [
                    'tarih' => $tarih,
                    'kategori' => $kategori,
                    'adet' => $adet,
                    'puan' => $puan,
                    'musteri_adi' => $musteriAdi,
                    'telefon' => $telefon,
                    'adres' => $adres,
                    'notlar' => $notlar
                ]);
                setFlash('success', 'Sipariş başarıyla eklendi.');
            } catch (Exception $e) {
                setFlash('error', 'Sipariş eklenirken hata oluştu.');
            }
        }
        header('Location: takvim.php');
        exit;
    }

    if ($_POST['action'] === 'sil' && isset($_POST['id'])) {
        try {
            db()->delete('siparisler', 'id = :id', ['id' => $_POST['id']]);
            setFlash('success', 'Sipariş silindi.');
        } catch (Exception $e) {
            setFlash('error', 'Sipariş silinirken hata oluştu.');
        }
        header('Location: takvim.php');
        exit;
    }

    // Tamamlandı durumunu değiştir
    if ($_POST['action'] === 'tamamla' && isset($_POST['id'])) {
        $yeniDurum = (int)($_POST['tamamlandi'] ?? 0);
        $siparisId = (int)$_POST['id'];

        try {
            // Siparişi al
            $siparis = db()->fetch("SELECT * FROM siparisler WHERE id = ?", [$siparisId]);

            if ($siparis) {
                // Tamamlandı olarak işaretleniyorsa ve daha önce müşteri kaydedilmediyse
                if ($yeniDurum == 1 && empty($siparis['musteri_kaydedildi']) && !empty($siparis['telefon'])) {
                    // Müşteriyi kaydet veya güncelle
                    $mevcutMusteri = db()->fetch("SELECT * FROM musteriler WHERE telefon = ?", [$siparis['telefon']]);

                    if ($mevcutMusteri) {
                        // Mevcut müşteriyi güncelle
                        $yeniSiparisSayisi = $mevcutMusteri['siparis_sayisi'] + 1;
                        $yeniHediyeHakki = floor($yeniSiparisSayisi / 5); // Her 5 siparişte 1 hediye

                        db()->update('musteriler', [
                            'isim' => $siparis['musteri_adi'] ?: $mevcutMusteri['isim'],
                            'adres' => $siparis['adres'] ?: $mevcutMusteri['adres'],
                            'siparis_sayisi' => $yeniSiparisSayisi,
                            'son_siparis_tarihi' => $siparis['tarih'],
                            'hediye_hak_edildi' => $yeniHediyeHakki
                        ], 'id = :id', ['id' => $mevcutMusteri['id']]);
                    } else {
                        // Yeni müşteri oluştur
                        db()->insert('musteriler', [
                            'telefon' => $siparis['telefon'],
                            'isim' => $siparis['musteri_adi'],
                            'adres' => $siparis['adres'],
                            'siparis_sayisi' => 1,
                            'son_siparis_tarihi' => $siparis['tarih'],
                            'hediye_hak_edildi' => 0
                        ]);
                    }

                    // Siparişi müşteri kaydedildi olarak işaretle (geri alınsa bile sayılmayacak)
                    db()->update('siparisler', [
                        'tamamlandi' => $yeniDurum,
                        'musteri_kaydedildi' => 1
                    ], 'id = :id', ['id' => $siparisId]);
                } else {
                    // Sadece tamamlandı durumunu güncelle (müşteri kaydı yapma)
                    db()->update('siparisler', ['tamamlandi' => $yeniDurum], 'id = :id', ['id' => $siparisId]);
                }

                setFlash('success', $yeniDurum ? 'Sipariş tamamlandı olarak işaretlendi.' : 'Sipariş devam ediyor olarak işaretlendi.');
            }
        } catch (Exception $e) {
            setFlash('error', 'Durum güncellenirken hata oluştu: ' . $e->getMessage());
        }
        header('Location: takvim.php?tarih=' . ($_POST['tarih'] ?? date('Y-m-d')));
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

// Seçili tarih
$seciliTarih = $_GET['tarih'] ?? date('Y-m-d');

// O güne ait siparişler
$gunSiparisleri = db()->fetchAll(
    "SELECT * FROM siparisler WHERE tarih = ? ORDER BY created_at DESC",
    [$seciliTarih]
);

// Toplam puan hesapla (sadece tamamlanmamış siparişler)
$gunToplamPuan = 0;
foreach ($gunSiparisleri as $s) {
    // Tamamlanmış siparişlerin puanı sayılmaz
    if (empty($s['tamamlandi'])) {
        $gunToplamPuan += $s['puan'] * $s['adet'];
    }
}

// Yoğunluk durumu (Yumuşak Pastel Tonlar)
function getYogunlukDurumu($puan) {
    if ($puan <= 10) return ['durum' => 'Boş', 'renk' => '#B8D4B8', 'class' => 'bos'];
    if ($puan <= 40) return ['durum' => 'Uygun', 'renk' => '#B8D4E8', 'class' => 'uygun'];
    if ($puan <= 80) return ['durum' => 'Yoğun', 'renk' => '#F5D4B0', 'class' => 'yogun'];
    return ['durum' => 'Dolu', 'renk' => '#E8C4C4', 'class' => 'dolu'];
}

$yogunluk = getYogunlukDurumu($gunToplamPuan);

// 30 günlük takvim verisi
$takvimVerisi = [];
for ($i = 0; $i < 30; $i++) {
    $tarih = date('Y-m-d', strtotime("+$i days"));
    $takvimVerisi[$tarih] = 0;
}

$siparisToplamlar = db()->fetchAll(
    "SELECT tarih, SUM(puan * adet) as toplam FROM siparisler
     WHERE tarih >= ? AND tarih <= ? AND (tamamlandi = 0 OR tamamlandi IS NULL) GROUP BY tarih",
    [date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]
);

foreach ($siparisToplamlar as $st) {
    $takvimVerisi[$st['tarih']] = (int)$st['toplam'];
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
</style>

<h2 style="margin-bottom: 1.5rem;">Takvim & Sipariş Yönetimi</h2>

<div class="takvim-container">
    <!-- Takvim -->
    <div>
        <div class="takvim-grid">
            <div class="takvim-header">Pzt</div>
            <div class="takvim-header">Sal</div>
            <div class="takvim-header">Çar</div>
            <div class="takvim-header">Per</div>
            <div class="takvim-header">Cum</div>
            <div class="takvim-header">Cmt</div>
            <div class="takvim-header">Paz</div>

            <?php
            // İlk günün haftanın kaçıncı günü olduğunu bul
            $ilkGun = date('N', strtotime(date('Y-m-d')));

            // Boşlukları doldur
            for ($i = 1; $i < $ilkGun; $i++) {
                echo '<div></div>';
            }

            foreach ($takvimVerisi as $tarih => $puan):
                $yd = getYogunlukDurumu($puan);
                $secili = $tarih === $seciliTarih ? 'secili' : '';
            ?>
                <a href="?tarih=<?= $tarih ?>" class="takvim-gun <?= $yd['class'] ?> <?= $secili ?>">
                    <span class="gun-sayi"><?= date('d', strtotime($tarih)) ?></span>
                    <span class="gun-ay"><?= ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'][date('n', strtotime($tarih))] ?></span>
                </a>
            <?php endforeach; ?>
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
        <div class="sidebar-panel" style="margin-bottom: 1rem;">
            <div class="panel-header">
                <h3><?= date('d F Y', strtotime($seciliTarih)) ?></h3>
                <span class="yogunluk-badge <?= $yogunluk['class'] ?>"><?= $yogunluk['durum'] ?></span>
            </div>
            <div class="panel-body">
                <div style="text-align: center; margin-bottom: 1rem;">
                    <div style="font-size: 2rem; font-weight: 600; color: <?= $yogunluk['renk'] ?>;"><?= $gunToplamPuan ?></div>
                    <small style="color: var(--admin-text-light);">/ 100 puan</small>
                </div>
                <div class="puan-bar">
                    <div class="puan-bar-fill" style="width: <?= min($gunToplamPuan, 100) ?>%; background: <?= $yogunluk['renk'] ?>;"></div>
                </div>
            </div>
        </div>

        <!-- Sipariş Ekle -->
        <div class="sidebar-panel" style="margin-bottom: 1rem;">
            <div class="panel-header">
                <h3>Sipariş Ekle</h3>
            </div>
            <div class="panel-body">
                <form method="POST" class="siparis-form">
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

                    <div class="form-group">
                        <label>Müşteri Adı (opsiyonel)</label>
                        <input type="text" name="musteri_adi" placeholder="Müşteri adı...">
                    </div>

                    <div class="form-group">
                        <label>Telefon Numarası</label>
                        <input type="tel" name="telefon" placeholder="05XX XXX XX XX" pattern="[0-9]{10,11}">
                        <small style="color: var(--admin-text-light);">Sadakat programı için gerekli</small>
                    </div>

                    <div class="form-group">
                        <label>Adres (opsiyonel)</label>
                        <textarea name="adres" rows="2" placeholder="Teslimat adresi..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Notlar (opsiyonel)</label>
                        <textarea name="notlar" rows="2" placeholder="Sipariş notları..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Sipariş Ekle</button>
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
                    <p style="text-align: center; color: var(--admin-text-light);">Bu tarihte sipariş yok.</p>
                <?php else: ?>
                    <div class="siparis-listesi">
                        <?php foreach ($gunSiparisleri as $index => $siparis):
                            $tamamlandi = !empty($siparis['tamamlandi']);
                        ?>
                            <div class="siparis-item <?= $tamamlandi ? 'tamamlandi' : '' ?>" onclick="openSiparisModal(<?= $index ?>)">
                                <div class="siparis-info">
                                    <strong>
                                        <?= $kategoriLabels[$siparis['kategori']] ?> x<?= $siparis['adet'] ?>
                                        <?php if ($tamamlandi): ?>
                                            <span class="siparis-durum-badge tamamlandi">Tamamlandı</span>
                                        <?php endif; ?>
                                    </strong>
                                    <?php if ($siparis['musteri_adi']): ?>
                                        <small><?= e($siparis['musteri_adi']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($siparis['notlar'] || ($siparis['adres'] ?? '')): ?>
                                        <div class="siparis-ozet">
                                            <?= $siparis['notlar'] ? mb_substr(e($siparis['notlar']), 0, 30) . (mb_strlen($siparis['notlar']) > 30 ? '...' : '') : '' ?>
                                            <?= ($siparis['adres'] ?? '') ? '📍' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="siparis-puan"><?= $siparis['puan'] * $siparis['adet'] ?> p</span>
                                <form method="POST" style="display: inline;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="action" value="sil">
                                    <input type="hidden" name="id" value="<?= $siparis['id'] ?>">
                                    <button type="submit" class="btn-sil" onclick="return confirm('Siparişi silmek istediğinize emin misiniz?')">
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
            <button class="modal-close" onclick="closeSiparisModal()">
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
                    <span class="modal-durum-badge tamamlandi" id="modalDurumTamamlandi" style="display: none;">
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
                    <small id="modalPuanNot" style="display: none; margin-left: 0.5rem; color: #4caf50;">(Sayılmıyor)</small>
                </div>
            </div>
            <div class="modal-detail-row" id="modalMusteriRow" style="display: none;">
                <div class="modal-detail-label">Müşteri</div>
                <div class="modal-detail-value" id="modalMusteri"></div>
            </div>
            <div class="modal-detail-row" id="modalTelefonRow" style="display: none;">
                <div class="modal-detail-label">Telefon</div>
                <div class="modal-detail-value" id="modalTelefon"></div>
            </div>
            <div class="modal-detail-row" id="modalAdresRow" style="display: none;">
                <div class="modal-detail-label">Adres</div>
                <div class="modal-detail-value notlar" id="modalAdres"></div>
            </div>
            <div class="modal-detail-row" id="modalNotlarRow" style="display: none;">
                <div class="modal-detail-label">Notlar</div>
                <div class="modal-detail-value notlar" id="modalNotlar"></div>
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="modalTamamlaForm" style="display: inline;">
                <input type="hidden" name="action" value="tamamla">
                <input type="hidden" name="id" id="modalSiparisId">
                <input type="hidden" name="tamamlandi" id="modalTamamlandiValue">
                <input type="hidden" name="tarih" value="<?= $seciliTarih ?>">
                <button type="submit" class="btn-tamamla devam-et" id="btnTamamla">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Tamamlandı İşaretle
                </button>
                <button type="submit" class="btn-tamamla geri-al" id="btnGeriAl" style="display: none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/>
                        <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
                    </svg>
                    Devam Ediyor Yap
                </button>
            </form>
            <button class="btn btn-secondary" onclick="closeSiparisModal()">Kapat</button>
        </div>
    </div>
</div>

<script>
// Sipariş verileri
const siparisler = <?= json_encode(array_values($gunSiparisleri), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const kategoriLabels = <?= json_encode($kategoriLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function openSiparisModal(index) {
    const siparis = siparisler[index];
    if (!siparis) return;

    const tamamlandi = siparis.tamamlandi == 1;

    document.getElementById('modalKategori').textContent = kategoriLabels[siparis.kategori] || siparis.kategori;
    document.getElementById('modalAdet').textContent = siparis.adet;
    document.getElementById('modalPuan').textContent = (siparis.puan * siparis.adet) + ' puan';

    // Durum gösterimi
    document.getElementById('modalDurumDevam').style.display = tamamlandi ? 'none' : 'inline-flex';
    document.getElementById('modalDurumTamamlandi').style.display = tamamlandi ? 'inline-flex' : 'none';
    document.getElementById('modalPuanNot').style.display = tamamlandi ? 'inline' : 'none';

    // Form ve butonlar
    document.getElementById('modalSiparisId').value = siparis.id;
    document.getElementById('modalTamamlandiValue').value = tamamlandi ? '0' : '1';
    document.getElementById('btnTamamla').style.display = tamamlandi ? 'none' : 'flex';
    document.getElementById('btnGeriAl').style.display = tamamlandi ? 'flex' : 'none';

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

    // Adres
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

    document.getElementById('siparisModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSiparisModal() {
    document.getElementById('siparisModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Modal dışına tıklayınca kapat
document.getElementById('siparisModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeSiparisModal();
    }
});

// ESC tuşuyla kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSiparisModal();
    }
});

// Özel sipariş puan göster/gizle
document.getElementById('kategoriSelect').addEventListener('change', function() {
    const ozelGroup = document.getElementById('ozelPuanGroup');
    if (this.value === 'ozel') {
        ozelGroup.classList.add('show');
    } else {
        ozelGroup.classList.remove('show');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
