<?php
/**
 * Admin - Satış Raporları
 */

require_once __DIR__ . '/includes/header.php';

// Tarih parametreleri
$buAyBaslangic = date('Y-m-01');
$bugun = date('Y-m-d');
$gelecekYilSonu = date('Y-12-31', strtotime('+1 year'));
$baslangic = $_GET['baslangic'] ?? $buAyBaslangic;
$bitis = $_GET['bitis'] ?? $bugun;

// Hızlı filtre kontrolü
$filtre = $_GET['filtre'] ?? 'bu_ay';

switch ($filtre) {
    case 'bu_hafta':
        $baslangic = date('Y-m-d', strtotime('monday this week'));
        $bitis = $bugun;
        break;
    case 'gecen_ay':
        $baslangic = date('Y-m-01', strtotime('-1 month'));
        $bitis = date('Y-m-t', strtotime('-1 month'));
        break;
    case 'son_3_ay':
        $baslangic = date('Y-m-01', strtotime('-2 months'));
        $bitis = $bugun;
        break;
    case 'bu_yil':
        $baslangic = date('Y-01-01');
        $bitis = $bugun;
        break;
    case 'ileri_tarihli':
        // Bugünden itibaren gelecek yılın sonuna kadar
        $baslangic = $bugun;
        $bitis = $gelecekYilSonu;
        break;
    case 'tum_onaylanmis':
        // Tüm zamanlar (en baştan gelecek yılın sonuna kadar)
        $baslangic = '2020-01-01';
        $bitis = $gelecekYilSonu;
        break;
    case 'ozel':
        // Parametrelerden al
        break;
    default: // bu_ay
        $baslangic = $buAyBaslangic;
        $bitis = $bugun;
}

// Verileri çek
$ozet = getSatisOzeti($baslangic, $bitis);
$karsilastirma = getGecenAyKarsilastirma($baslangic, $bitis);
$gunlukSatislar = getGunlukSatislar($baslangic, $bitis);
$kategoriDagilimi = getKategoriDagilimi($baslangic, $bitis);
$enCokSatanlar = getEnCokSatanlar($baslangic, $bitis, 5);
$musteriAnalizi = getMusteriAnalizi($baslangic, $bitis);
$haftalikKarsilastirma = getHaftalikKarsilastirma();
$odemeTipiDagilimi = getOdemeTipiDagilimi($baslangic, $bitis);
$kanalDagilimi = getKanalDagilimi($baslangic, $bitis);

// Kategori isimleri
$kategoriLabels = [
    'pasta' => 'Pasta',
    'cupcake' => 'Cupcake',
    'cheesecake' => 'Cheesecake',
    'kurabiye' => 'Kurabiye',
    'ozel' => 'Özel Sipariş'
];

// Pastel renkler
$renkler = [
    'pasta' => '#E8B4B8',
    'cupcake' => '#B8D4E8',
    'cheesecake' => '#F5D4B0',
    'kurabiye' => '#B8E8C4',
    'ozel' => '#D4B8E8'
];
?>

<style>
.rapor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.rapor-header h2 {
    margin: 0;
}

.filtre-grubu {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filtre-btn {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.filtre-btn:hover {
    border-color: var(--admin-primary);
}

.filtre-btn.active {
    background: var(--admin-primary);
    color: white;
    border-color: var(--admin-primary);
}

.tarih-secici {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tarih-secici input {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.ozet-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.ozet-kart {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.ozet-kart .baslik {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.ozet-kart .deger {
    font-size: 2rem;
    font-weight: 600;
    color: #333;
}

.ozet-kart .deger.para {
    color: var(--admin-primary);
}

.ozet-kart .degisim {
    font-size: 0.85rem;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.ozet-kart .degisim.pozitif {
    color: #4CAF50;
}

.ozet-kart .degisim.negatif {
    color: #F44336;
}

.grafik-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .grafik-grid {
        grid-template-columns: 1fr;
    }
}

.grafik-kart {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.grafik-kart h3 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: #333;
}

.grafik-container {
    position: relative;
    height: 300px;
}

.tablo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

.tablo-kart {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.tablo-kart .kart-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eee;
    font-weight: 600;
}

.tablo-kart table {
    width: 100%;
    border-collapse: collapse;
}

.tablo-kart th,
.tablo-kart td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.tablo-kart th {
    background: #f8f9fa;
    font-weight: 500;
    font-size: 0.85rem;
    color: #666;
}

.tablo-kart tr:last-child td {
    border-bottom: none;
}

.tablo-kart .tutar {
    font-weight: 600;
    color: var(--admin-primary);
}

.kategori-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
}

.kategori-renk {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.musteri-stat {
    display: flex;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.musteri-stat:last-child {
    border-bottom: none;
}

.musteri-stat .label {
    color: #666;
}

.musteri-stat .value {
    font-weight: 600;
}

.donem-bilgi {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    color: #666;
}

.bos-veri {
    text-align: center;
    padding: 3rem;
    color: #999;
}

.bos-veri svg {
    width: 64px;
    height: 64px;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Modal Popup Stilleri */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 1;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 900px;
    max-height: 85vh;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
}

.modal-overlay.active .modal-content {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #333;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: white;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.modal-close:hover {
    background: #ff4444;
    color: white;
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

/* Modal içi tablo stilleri */
.modal-body table {
    width: 100%;
    border-collapse: collapse;
}

.modal-body th,
.modal-body td {
    padding: 1rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.modal-body th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.9rem;
    color: #555;
    position: sticky;
    top: 0;
}

.modal-body tr:hover {
    background: #fafafa;
}

.modal-body .tutar {
    font-weight: 600;
    color: var(--admin-primary);
    font-size: 1.05rem;
}

/* Tıklanabilir kartlar */
.tablo-kart .kart-header,
.grafik-kart h3 {
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tablo-kart .kart-header:hover,
.grafik-kart h3:hover {
    background: #f0f0f0;
}

.tablo-kart .kart-header::after,
.grafik-kart h3::after {
    content: '⛶';
    font-size: 1.1rem;
    opacity: 0.3;
    transition: all 0.2s;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    background: #f0f0f0;
}

.tablo-kart .kart-header:hover::after,
.grafik-kart h3:hover::after {
    opacity: 1;
    background: var(--admin-primary);
    color: white;
}

.grafik-kart h3 {
    padding: 0.5rem;
    margin: -0.5rem -0.5rem 1rem -0.5rem;
    border-radius: 8px;
}

/* Modal grafik container */
.modal-grafik-container {
    height: 400px;
    position: relative;
}

/* Müşteri analizi modal */
.modal-body .musteri-stat {
    display: flex;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 1.1rem;
}

.modal-body .musteri-stat:last-child {
    border-bottom: none;
}

.modal-body .musteri-stat .label {
    color: #555;
}

.modal-body .musteri-stat .value {
    font-weight: 600;
    font-size: 1.25rem;
}
</style>

<div class="rapor-header">
    <h2>Satış Raporları</h2>

    <div class="filtre-grubu">
        <a href="?filtre=bu_hafta" class="filtre-btn <?= $filtre === 'bu_hafta' ? 'active' : '' ?>">Bu Hafta</a>
        <a href="?filtre=bu_ay" class="filtre-btn <?= $filtre === 'bu_ay' ? 'active' : '' ?>">Bu Ay</a>
        <a href="?filtre=gecen_ay" class="filtre-btn <?= $filtre === 'gecen_ay' ? 'active' : '' ?>">Geçen Ay</a>
        <a href="?filtre=son_3_ay" class="filtre-btn <?= $filtre === 'son_3_ay' ? 'active' : '' ?>">Son 3 Ay</a>
        <a href="?filtre=bu_yil" class="filtre-btn <?= $filtre === 'bu_yil' ? 'active' : '' ?>">Bu Yıl</a>
        <a href="?filtre=ileri_tarihli" class="filtre-btn <?= $filtre === 'ileri_tarihli' ? 'active' : '' ?>" title="Bugünden sonraki siparişler">İleri Tarihli</a>
        <a href="?filtre=tum_onaylanmis" class="filtre-btn <?= $filtre === 'tum_onaylanmis' ? 'active' : '' ?>" title="Tüm tamamlanmış siparişler">Tümü</a>
    </div>
</div>

<form class="tarih-secici" style="margin-bottom: 1.5rem;">
    <input type="hidden" name="filtre" value="ozel">
    <label>Tarih Aralığı:</label>
    <input type="date" name="baslangic" value="<?= e($baslangic) ?>">
    <span>-</span>
    <input type="date" name="bitis" value="<?= e($bitis) ?>">
    <button type="submit" class="btn btn-primary btn-sm">Uygula</button>
</form>

<div class="donem-bilgi">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.5rem;">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
    </svg>
    <strong><?= date('d.m.Y', strtotime($baslangic)) ?></strong> - <strong><?= date('d.m.Y', strtotime($bitis)) ?></strong> tarihleri arası
</div>

<!-- Özet Kartları -->
<div class="ozet-grid">
    <div class="ozet-kart">
        <div class="baslik">Toplam Satış</div>
        <div class="deger para"><?= formatPrice($ozet['toplam_satis']) ?></div>
        <div class="degisim <?= $karsilastirma >= 0 ? 'pozitif' : 'negatif' ?>">
            <?php if ($karsilastirma >= 0): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                    <polyline points="17 6 23 6 23 12"/>
                </svg>
            <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                    <polyline points="17 18 23 18 23 12"/>
                </svg>
            <?php endif; ?>
            <?= abs($karsilastirma) ?>% geçen aya göre
        </div>
    </div>

    <div class="ozet-kart">
        <div class="baslik">Sipariş Sayısı</div>
        <div class="deger"><?= number_format($ozet['siparis_sayisi']) ?></div>
        <div class="degisim">Tamamlanan siparişler</div>
    </div>

    <div class="ozet-kart">
        <div class="baslik">Ortalama Sepet</div>
        <div class="deger para"><?= formatPrice($ozet['ortalama_sepet']) ?></div>
        <div class="degisim">Sipariş başına ortalama</div>
    </div>

    <div class="ozet-kart">
        <div class="baslik">Toplam Ürün</div>
        <div class="deger"><?= number_format($ozet['toplam_kisi']) ?></div>
        <div class="degisim">Satılan ürün adedi</div>
    </div>
</div>

<?php if ($ozet['siparis_sayisi'] > 0): ?>

<!-- Grafikler -->
<div class="grafik-grid">
    <div class="grafik-kart">
        <h3>Günlük Satış Trendi</h3>
        <div class="grafik-container">
            <canvas id="gunlukChart"></canvas>
        </div>
    </div>

    <div class="grafik-kart">
        <h3>Kategori Dağılımı</h3>
        <div class="grafik-container">
            <canvas id="kategoriChart"></canvas>
        </div>
    </div>
</div>

<div class="grafik-grid">
    <div class="grafik-kart">
        <h3>Haftalık Karşılaştırma</h3>
        <div class="grafik-container">
            <canvas id="haftalikChart"></canvas>
        </div>
    </div>

    <div class="grafik-kart">
        <h3>Kanal Dağılımı</h3>
        <div class="grafik-container">
            <canvas id="kanalChart"></canvas>
        </div>
    </div>
</div>

<!-- Tablolar -->
<div class="tablo-grid">
    <div class="tablo-kart">
        <div class="kart-header">En Çok Satanlar</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kategori</th>
                    <th>Adet</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enCokSatanlar as $index => $item): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <span class="kategori-badge" style="background: <?= $renkler[$item['kategori']] ?? '#eee' ?>20;">
                            <span class="kategori-renk" style="background: <?= $renkler[$item['kategori']] ?? '#ccc' ?>;"></span>
                            <?= $kategoriLabels[$item['kategori']] ?? $item['kategori'] ?>
                        </span>
                    </td>
                    <td><?= number_format($item['toplam_kisi']) ?></td>
                    <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="tablo-kart">
        <div class="kart-header">Müşteri Analizi</div>
        <div class="musteri-stat">
            <span class="label">Toplam Müşteri</span>
            <span class="value"><?= number_format($musteriAnalizi['toplam_musteri']) ?></span>
        </div>
        <div class="musteri-stat">
            <span class="label">Yeni Müşteri</span>
            <span class="value" style="color: #4CAF50;"><?= number_format($musteriAnalizi['yeni_musteri']) ?></span>
        </div>
        <div class="musteri-stat">
            <span class="label">Tekrar Eden Müşteri</span>
            <span class="value" style="color: var(--admin-primary);"><?= number_format($musteriAnalizi['tekrar_eden']) ?></span>
        </div>
        <div class="musteri-stat">
            <span class="label">Tekrar Oranı</span>
            <span class="value">
                <?php
                $tekrarOrani = $musteriAnalizi['toplam_musteri'] > 0
                    ? round(($musteriAnalizi['tekrar_eden'] / $musteriAnalizi['toplam_musteri']) * 100, 1)
                    : 0;
                echo $tekrarOrani . '%';
                ?>
            </span>
        </div>
    </div>

    <div class="tablo-kart">
        <div class="kart-header">Ödeme Tipi Dağılımı</div>
        <table>
            <thead>
                <tr>
                    <th>Tip</th>
                    <th>Sipariş</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $odemeTipleri = ['online' => 'Online (Site)', 'fiziksel' => 'Fiziksel (Mağaza)'];
                foreach ($odemeTipiDagilimi as $item):
                ?>
                <tr>
                    <td><?= $odemeTipleri[$item['odeme_tipi']] ?? $item['odeme_tipi'] ?></td>
                    <td><?= number_format($item['siparis_sayisi']) ?></td>
                    <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="tablo-kart">
        <div class="kart-header">Satış Kanalları</div>
        <table>
            <thead>
                <tr>
                    <th>Kanal</th>
                    <th>Sipariş</th>
                    <th>Tutar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $kanallar = ['site' => 'Web Sitesi', 'telefon' => 'Telefon', 'cafe' => 'Cafe/Mağaza'];
                foreach ($kanalDagilimi as $item):
                ?>
                <tr>
                    <td><?= $kanallar[$item['kanal']] ?? $item['kanal'] ?></td>
                    <td><?= number_format($item['siparis_sayisi']) ?></td>
                    <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js (Local) -->
<script src="../assets/js/vendor/chart.min.js"></script>

<script nonce="<?= getCspNonce() ?>">
// Grafik renkleri
const renkler = {
    primary: '#D4A5A5',
    secondary: '#B8D4E8',
    success: '#B8E8C4',
    warning: '#F5D4B0',
    info: '#D4B8E8',
    grid: '#f0f0f0'
};

// Günlük satış verileri
const gunlukData = <?= json_encode($gunlukSatislar, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Günlük Satış Grafiği
new Chart(document.getElementById('gunlukChart'), {
    type: 'line',
    data: {
        labels: gunlukData.map(d => {
            const tarih = new Date(d.tarih);
            return tarih.getDate() + '/' + (tarih.getMonth() + 1);
        }),
        datasets: [{
            label: 'Satış (₺)',
            data: gunlukData.map(d => d.toplam_tutar),
            borderColor: renkler.primary,
            backgroundColor: renkler.primary + '40',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: renkler.grid }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// Kategori dağılımı verileri
const kategoriData = <?= json_encode($kategoriDagilimi, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const kategoriLabels = <?= json_encode($kategoriLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const kategoriRenkler = <?= json_encode($renkler, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Kategori Dağılımı Grafiği
new Chart(document.getElementById('kategoriChart'), {
    type: 'doughnut',
    data: {
        labels: kategoriData.map(d => kategoriLabels[d.kategori] || d.kategori),
        datasets: [{
            data: kategoriData.map(d => d.toplam_tutar),
            backgroundColor: kategoriData.map(d => kategoriRenkler[d.kategori] || '#ccc'),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15 }
            }
        }
    }
});

// Haftalık karşılaştırma verileri
const haftalikData = <?= json_encode($haftalikKarsilastirma, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Haftalık Karşılaştırma Grafiği
new Chart(document.getElementById('haftalikChart'), {
    type: 'bar',
    data: {
        labels: haftalikData.map(d => d.hafta),
        datasets: [{
            label: 'Satış (₺)',
            data: haftalikData.map(d => d.toplam_tutar),
            backgroundColor: [renkler.info, renkler.warning, renkler.secondary, renkler.primary],
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: renkler.grid }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

// Kanal dağılımı verileri
const kanalData = <?= json_encode($kanalDagilimi, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const kanalLabels = {'site': 'Web Sitesi', 'telefon': 'Telefon', 'cafe': 'Cafe/Mağaza'};
const kanalRenkler = ['#B8D4E8', '#F5D4B0', '#B8E8C4'];

// Kanal Dağılımı Grafiği
new Chart(document.getElementById('kanalChart'), {
    type: 'pie',
    data: {
        labels: kanalData.map(d => kanalLabels[d.kanal] || d.kanal),
        datasets: [{
            data: kanalData.map(d => d.toplam_tutar),
            backgroundColor: kanalRenkler,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15 }
            }
        }
    }
});
</script>

<!-- Modal Popup -->
<div id="raporModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Detay</h3>
            <button class="modal-close" onclick="closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- İçerik JavaScript ile doldurulacak -->
        </div>
    </div>
</div>

<script nonce="<?= getCspNonce() ?>">
// Modal fonksiyonları
const modal = document.getElementById('raporModal');
const modalTitle = document.getElementById('modalTitle');
const modalBody = document.getElementById('modalBody');

function openModal(title, content) {
    modalTitle.textContent = title;
    modalBody.innerHTML = content;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Overlay'e tıklayınca kapat
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeModal();
    }
});

// ESC tuşu ile kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
        closeModal();
    }
});

// Tablo kartları için modal içerikleri
const modalContents = {
    'en-cok-satanlar': {
        title: 'En Çok Satanlar - Detaylı Görünüm',
        content: `
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kategori</th>
                        <th>Toplam Adet</th>
                        <th>Toplam Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enCokSatanlar as $index => $item):
                        // XSS koruması - sadece bilinen renkler kullan
                        $kategoriKey = $item['kategori'] ?? '';
                        $bgRenk = isset($renkler[$kategoriKey]) ? e($renkler[$kategoriKey]) : '#eee';
                        $dotRenk = isset($renkler[$kategoriKey]) ? e($renkler[$kategoriKey]) : '#ccc';
                        $label = isset($kategoriLabels[$kategoriKey]) ? e($kategoriLabels[$kategoriKey]) : e($kategoriKey);
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: #888;"><?= $index + 1 ?></td>
                        <td>
                            <span class="kategori-badge" style="background: <?= $bgRenk ?>30; padding: 0.5rem 1rem;">
                                <span class="kategori-renk" style="background: <?= $dotRenk ?>;"></span>
                                <?= $label ?>
                            </span>
                        </td>
                        <td style="font-size: 1.1rem;"><?= number_format($item['toplam_kisi']) ?> adet</td>
                        <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        `
    },
    'musteri-analizi': {
        title: 'Müşteri Analizi - Detaylı Görünüm',
        content: `
            <div class="musteri-stat">
                <span class="label">Toplam Müşteri</span>
                <span class="value"><?= number_format($musteriAnalizi['toplam_musteri']) ?></span>
            </div>
            <div class="musteri-stat">
                <span class="label">Yeni Müşteri</span>
                <span class="value" style="color: #4CAF50;"><?= number_format($musteriAnalizi['yeni_musteri']) ?></span>
            </div>
            <div class="musteri-stat">
                <span class="label">Tekrar Eden Müşteri</span>
                <span class="value" style="color: var(--admin-primary);"><?= number_format($musteriAnalizi['tekrar_eden']) ?></span>
            </div>
            <div class="musteri-stat">
                <span class="label">Tekrar Oranı</span>
                <span class="value"><?= $tekrarOrani ?>%</span>
            </div>
            <div class="musteri-stat" style="background: #f8f9fa; margin-top: 1rem; border-radius: 8px;">
                <span class="label">Ortalama Sipariş Değeri</span>
                <span class="value" style="color: var(--admin-primary);"><?= formatPrice($ozet['ortalama_sepet']) ?></span>
            </div>
        `
    },
    'odeme-tipi': {
        title: 'Ödeme Tipi Dağılımı - Detaylı Görünüm',
        content: `
            <table>
                <thead>
                    <tr>
                        <th>Ödeme Tipi</th>
                        <th>Sipariş Sayısı</th>
                        <th>Toplam Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($odemeTipiDagilimi as $item): ?>
                    <tr>
                        <td style="font-weight: 500;">
                            <?php if ($item['odeme_tipi'] === 'online'): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Online (Site)
                            <?php else: ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg> Fiziksel (Mağaza)
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 1.1rem;"><?= number_format($item['siparis_sayisi']) ?> sipariş</td>
                        <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        `
    },
    'satis-kanallari': {
        title: 'Satış Kanalları - Detaylı Görünüm',
        content: `
            <table>
                <thead>
                    <tr>
                        <th>Satış Kanalı</th>
                        <th>Sipariş Sayısı</th>
                        <th>Toplam Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kanalDagilimi as $item): ?>
                    <tr>
                        <td style="font-weight: 500;">
                            <?php
                            $kanalSvg = [
                                'site' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>',
                                'telefon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>',
                                'cafe' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>'
                            ];
                            echo ($kanalSvg[$item['kanal']] ?? '') . ' ' . ($kanallar[$item['kanal']] ?? $item['kanal']);
                            ?>
                        </td>
                        <td style="font-size: 1.1rem;"><?= number_format($item['siparis_sayisi']) ?> sipariş</td>
                        <td class="tutar"><?= formatPrice($item['toplam_tutar']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        `
    }
};

// Grafik modal içerikleri
const grafikConfigs = {
    'gunluk': {
        title: 'Günlük Satış Trendi - Detaylı Görünüm',
        type: 'line',
        getData: () => ({
            labels: gunlukData.map(d => {
                const tarih = new Date(d.tarih);
                return tarih.getDate() + '/' + (tarih.getMonth() + 1);
            }),
            datasets: [{
                label: 'Satış (₺)',
                data: gunlukData.map(d => d.toplam_tutar),
                borderColor: renkler.primary,
                backgroundColor: renkler.primary + '40',
                fill: true,
                tension: 0.4
            }]
        }),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    },
    'kategori': {
        title: 'Kategori Dağılımı - Detaylı Görünüm',
        type: 'doughnut',
        getData: () => ({
            labels: kategoriData.map(d => kategoriLabels[d.kategori] || d.kategori),
            datasets: [{
                data: kategoriData.map(d => d.toplam_tutar),
                backgroundColor: kategoriData.map(d => kategoriRenkler[d.kategori] || '#ccc'),
                borderWidth: 0
            }]
        }),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 14 } } } }
        }
    },
    'haftalik': {
        title: 'Haftalık Karşılaştırma - Detaylı Görünüm',
        type: 'bar',
        getData: () => ({
            labels: haftalikData.map(d => d.hafta),
            datasets: [{
                label: 'Satış (₺)',
                data: haftalikData.map(d => d.toplam_tutar),
                backgroundColor: [renkler.info, renkler.warning, renkler.secondary, renkler.primary],
                borderRadius: 8
            }]
        }),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                x: { grid: { display: false } }
            }
        }
    },
    'kanal': {
        title: 'Kanal Dağılımı - Detaylı Görünüm',
        type: 'pie',
        getData: () => ({
            labels: kanalData.map(d => kanalLabels[d.kanal] || d.kanal),
            datasets: [{
                data: kanalData.map(d => d.toplam_tutar),
                backgroundColor: kanalRenkler,
                borderWidth: 0
            }]
        }),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { size: 14 } } } }
        }
    }
};

let modalChart = null;

function openGrafikModal(grafikKey) {
    const config = grafikConfigs[grafikKey];
    if (!config) return;

    modalTitle.textContent = config.title;
    modalBody.innerHTML = '<div class="modal-grafik-container"><canvas id="modalChart"></canvas></div>';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Önceki chart'ı temizle
    if (modalChart) {
        modalChart.destroy();
        modalChart = null;
    }

    // Yeni chart oluştur
    setTimeout(() => {
        const ctx = document.getElementById('modalChart');
        if (ctx) {
            modalChart = new Chart(ctx, {
                type: config.type,
                data: config.getData(),
                options: config.options
            });
        }
    }, 100);
}

// Modal kapanırken chart'ı temizle
const originalCloseModal = closeModal;
closeModal = function() {
    if (modalChart) {
        modalChart.destroy();
        modalChart = null;
    }
    originalCloseModal();
};

// Kart başlıklarına tıklama olayları ekle
document.addEventListener('DOMContentLoaded', function() {
    // Tablo kartları
    const tabloKartlar = document.querySelectorAll('.tablo-kart');
    tabloKartlar.forEach((kart, index) => {
        const header = kart.querySelector('.kart-header');
        if (header) {
            const keys = ['en-cok-satanlar', 'musteri-analizi', 'odeme-tipi', 'satis-kanallari'];
            const key = keys[index];
            if (modalContents[key]) {
                header.addEventListener('click', function() {
                    openModal(modalContents[key].title, modalContents[key].content);
                });
            }
        }
    });

    // Grafik kartları
    const grafikKartlar = document.querySelectorAll('.grafik-kart');
    const grafikKeys = ['gunluk', 'kategori', 'haftalik', 'kanal'];
    grafikKartlar.forEach((kart, index) => {
        const header = kart.querySelector('h3');
        if (header && grafikKeys[index]) {
            header.addEventListener('click', function() {
                openGrafikModal(grafikKeys[index]);
            });
        }
    });
});
</script>

<?php else: ?>

<div class="bos-veri">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M9 17H5a2 2 0 00-2 2v2h18v-2a2 2 0 00-2-2h-4"/>
        <path d="M12 17V3"/>
        <path d="M7 8l5-5 5 5"/>
    </svg>
    <h3>Bu dönemde tamamlanmış sipariş bulunmuyor</h3>
    <p>Farklı bir tarih aralığı seçebilir veya sipariş ekleyebilirsiniz.</p>
    <a href="takvim.php" class="btn btn-primary" style="margin-top: 1rem;">Siparişlere Git</a>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
