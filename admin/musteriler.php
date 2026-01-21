<?php
/**
 * Kayıtlı Müşteriler - Sadakat Programı Takibi
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Arama ve filtreleme
$arama = trim($_GET['arama'] ?? '');
$siralama = $_GET['siralama'] ?? 'siparis_sayisi';
$yonSirala = $_GET['yon'] ?? 'DESC';

// Geçerli sıralama alanları
$gecerliSiralama = ['siparis_sayisi', 'son_siparis_tarihi', 'isim', 'telefon', 'hediye_hak_edildi'];
if (!in_array($siralama, $gecerliSiralama)) {
    $siralama = 'siparis_sayisi';
}
$yonSirala = strtoupper($yonSirala) === 'ASC' ? 'ASC' : 'DESC';

// Müşterileri getir
try {
    $sql = "SELECT * FROM musteriler";
    $params = [];

    if ($arama) {
        $sql .= " WHERE (telefon LIKE :arama OR isim LIKE :arama2 OR adres LIKE :arama3)";
        $params['arama'] = "%$arama%";
        $params['arama2'] = "%$arama%";
        $params['arama3'] = "%$arama%";
    }

    $sql .= " ORDER BY $siralama $yonSirala";
    $musteriler = db()->fetchAll($sql, $params);
} catch (Exception $e) {
    $musteriler = [];
}

// Toplam istatistikler
try {
    $stats = db()->fetch("SELECT COUNT(*) as toplam_musteri, SUM(siparis_sayisi) as toplam_siparis, SUM(hediye_hak_edildi) as toplam_hediye FROM musteriler");
} catch (Exception $e) {
    $stats = ['toplam_musteri' => 0, 'toplam_siparis' => 0, 'toplam_hediye' => 0];
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.musteri-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
}

.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-card .stat-icon svg {
    width: 24px;
    height: 24px;
}

.stat-card.musteri .stat-icon { background: #e3f2fd; color: #1976d2; }
.stat-card.siparis .stat-icon { background: #e8f5e9; color: #388e3c; }
.stat-card.hediye .stat-icon { background: #fff3e0; color: #f57c00; }

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 600;
    color: var(--admin-dark);
}

.stat-card .stat-label {
    color: var(--admin-text-light);
    font-size: 0.9rem;
}

.musteri-filters {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.musteri-filters .search-box {
    flex: 1;
    min-width: 200px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f8f9fa;
    padding: 0.6rem 1rem;
    border-radius: 8px;
}

.musteri-filters .search-box input {
    border: none;
    background: none;
    flex: 1;
    font-size: 0.95rem;
    outline: none;
}

.musteri-filters .search-box svg {
    color: var(--admin-text-light);
    width: 18px;
    height: 18px;
}

.musteri-filters select {
    padding: 0.6rem 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.95rem;
    background: white;
}

.musteri-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
}

.musteri-table table {
    width: 100%;
    border-collapse: collapse;
}

.musteri-table th {
    background: #f8f9fa;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--admin-text-light);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.musteri-table th a {
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.musteri-table th a:hover {
    color: var(--admin-primary);
}

.musteri-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.musteri-table tr:last-child td {
    border-bottom: none;
}

.musteri-table tr:hover {
    background: #fafafa;
}

.musteri-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--admin-primary), #C4A4B4);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1rem;
}

.musteri-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.musteri-info .name {
    font-weight: 500;
}

.musteri-info .phone {
    color: var(--admin-text-light);
    font-size: 0.9rem;
}

.siparis-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 50%;
    font-weight: 600;
}

.hediye-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.8rem;
    background: #fff3e0;
    color: #f57c00;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.hediye-badge.kazanildi {
    background: #e8f5e9;
    color: #388e3c;
}

.sonraki-hediye {
    font-size: 0.85rem;
    color: var(--admin-text-light);
}

.sonraki-hediye .progress {
    display: inline-block;
    width: 60px;
    height: 6px;
    background: #eee;
    border-radius: 3px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.sonraki-hediye .progress-fill {
    height: 100%;
    background: var(--admin-primary);
    border-radius: 3px;
}

.adres-text {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #666;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--admin-text-light);
}

.empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<h2 style="margin-bottom: 1.5rem;">Kayıtlı Müşteriler</h2>

<!-- İstatistik Kartları -->
<div class="musteri-stats">
    <div class="stat-card musteri">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                <path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
        </div>
        <div class="stat-value"><?= $stats['toplam_musteri'] ?? 0 ?></div>
        <div class="stat-label">Toplam Müşteri</div>
    </div>

    <div class="stat-card siparis">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            </svg>
        </div>
        <div class="stat-value"><?= $stats['toplam_siparis'] ?? 0 ?></div>
        <div class="stat-label">Tamamlanan Sipariş</div>
    </div>

    <div class="stat-card hediye">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 12 20 22 4 22 4 12"/>
                <rect x="2" y="7" width="20" height="5"/>
                <line x1="12" y1="22" x2="12" y2="7"/>
                <path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/>
                <path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/>
            </svg>
        </div>
        <div class="stat-value"><?= $stats['toplam_hediye'] ?? 0 ?></div>
        <div class="stat-label">Verilen Hediye</div>
    </div>
</div>

<!-- Filtreler -->
<form method="GET" class="musteri-filters">
    <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" name="arama" placeholder="İsim, telefon veya adres ara..." value="<?= e($arama) ?>">
    </div>

    <select name="siralama" onchange="this.form.submit()">
        <option value="siparis_sayisi" <?= $siralama === 'siparis_sayisi' ? 'selected' : '' ?>>Sipariş Sayısına Göre</option>
        <option value="son_siparis_tarihi" <?= $siralama === 'son_siparis_tarihi' ? 'selected' : '' ?>>Son Siparişe Göre</option>
        <option value="isim" <?= $siralama === 'isim' ? 'selected' : '' ?>>İsme Göre</option>
        <option value="hediye_hak_edildi" <?= $siralama === 'hediye_hak_edildi' ? 'selected' : '' ?>>Hediye Sayısına Göre</option>
    </select>

    <select name="yon" onchange="this.form.submit()">
        <option value="DESC" <?= $yonSirala === 'DESC' ? 'selected' : '' ?>>Azalan</option>
        <option value="ASC" <?= $yonSirala === 'ASC' ? 'selected' : '' ?>>Artan</option>
    </select>

    <button type="submit" class="btn btn-primary">Ara</button>
    <?php if ($arama): ?>
        <a href="musteriler.php" class="btn btn-secondary">Temizle</a>
    <?php endif; ?>
</form>

<!-- Müşteri Tablosu -->
<div class="musteri-table">
    <?php if (empty($musteriler)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
            <h3>Henüz kayıtlı müşteri yok</h3>
            <p>Siparişler tamamlandığında müşteriler otomatik kaydedilecek.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Müşteri</th>
                    <th>Telefon</th>
                    <th>Adres</th>
                    <th style="text-align: center;">Sipariş</th>
                    <th>Hediye Durumu</th>
                    <th>Son Sipariş</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($musteriler as $musteri):
                    $siparisKalan = $musteri['siparis_sayisi'] % 5;
                    $sonrakiHediyeIcin = 5 - $siparisKalan;
                    $ilkHarf = mb_strtoupper(mb_substr($musteri['isim'] ?: $musteri['telefon'], 0, 1));
                ?>
                    <tr>
                        <td>
                            <div class="musteri-info">
                                <div class="musteri-avatar"><?= e($ilkHarf) ?></div>
                                <div>
                                    <div class="name"><?= $musteri['isim'] ? e($musteri['isim']) : '<em style="color: #999;">İsim yok</em>' ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="phone"><?= e($musteri['telefon']) ?></span>
                        </td>
                        <td>
                            <?php if ($musteri['adres']): ?>
                                <div class="adres-text" title="<?= e($musteri['adres']) ?>"><?= e($musteri['adres']) ?></div>
                            <?php else: ?>
                                <em style="color: #999;">-</em>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="siparis-badge"><?= $musteri['siparis_sayisi'] ?></span>
                        </td>
                        <td>
                            <?php if ($musteri['hediye_hak_edildi'] > 0): ?>
                                <span class="hediye-badge kazanildi">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 12 20 22 4 22 4 12"/>
                                        <rect x="2" y="7" width="20" height="5"/>
                                    </svg>
                                    <?= $musteri['hediye_hak_edildi'] ?> hediye
                                </span>
                            <?php endif; ?>
                            <div class="sonraki-hediye">
                                <?= $sonrakiHediyeIcin ?> sipariş sonra hediye
                                <span class="progress">
                                    <span class="progress-fill" style="width: <?= ($siparisKalan / 5) * 100 ?>%"></span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($musteri['son_siparis_tarihi']): ?>
                                <?= date('d.m.Y', strtotime($musteri['son_siparis_tarihi'])) ?>
                            <?php else: ?>
                                <em style="color: #999;">-</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
