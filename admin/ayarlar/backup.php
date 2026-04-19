<?php
/**
 * Admin — Veritabanı Yedekleme (Backup) UI
 *
 * Özellikler:
 *   - Mevcut backup dosyalarını listele (tarih, boyut, indirme)
 *   - "Şimdi Yedekle" butonu → cron script'i aynı prosesle çalıştır (exec)
 *   - "Geri Yükle" (TEHLİKELİ — confirm + setting.edit permission)
 *   - Dosya adı validation: sadece `backup-*.sql.gz` + realpath check
 *
 * Permission: `setting.edit`
 *
 * @package Pastane\Admin\Ayarlar
 * @since 2.0.0-sprint2
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_permission('setting.edit');

$BASE = dirname(__DIR__, 2);
$BACKUP_DIR = $BASE . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
$CRON_SCRIPT = $BASE . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'db-backup.php';

// Dizin yoksa oluştur
if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0755, true);
}

$sonuc = null;
$hata  = null;
$indirDosya = null;

/**
 * Dosya adı güvenlik doğrulaması
 *
 * Kabul: sadece `backup-YYYYMMDD-HHMMSS.sql.gz` veya `.sql` formatı.
 * Path traversal'a karşı realpath() ile backup dizini içinde olduğunu onayla.
 *
 * @param string $name
 * @param string $baseDir
 * @return string|null Tam yol veya null
 */
function pastane_validate_backup_filename(string $name, string $baseDir): ?string
{
    // Regex: backup-YYYYMMDD-HHMMSS.sql(.gz)?
    if (!preg_match('/^backup-\d{8}-\d{6}\.sql(\.gz)?$/', $name)) {
        return null;
    }
    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $name;
    $real = realpath($fullPath);
    $realBase = realpath($baseDir);
    if ($real === false || $realBase === false) {
        return null;
    }
    // Path traversal kontrolü
    if (!str_starts_with($real, $realBase . DIRECTORY_SEPARATOR) && $real !== $realBase) {
        return null;
    }
    return $real;
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $hata = 'Güvenlik doğrulaması başarısız (CSRF token).';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'backup-now':
                try {
                    // Cron script'i aynı PHP binary ile çağır
                    $php = escapeshellarg(PHP_BINARY);
                    $script = escapeshellarg($CRON_SCRIPT);
                    $cmd = "{$php} {$script} 2>&1";
                    exec($cmd, $output, $exit);
                    if ($exit === 0) {
                        setFlash('success', 'Backup başarıyla oluşturuldu.');
                    } else {
                        setFlash('error', 'Backup başarısız (exit ' . $exit . '). Log: ' . implode(' | ', array_slice($output, -3)));
                    }
                } catch (\Throwable $e) {
                    setFlash('error', 'Backup hatası: ' . $e->getMessage());
                }
                break;

            case 'delete':
                $fname = (string) ($_POST['filename'] ?? '');
                $path = pastane_validate_backup_filename($fname, $BACKUP_DIR);
                if ($path === null) {
                    setFlash('error', 'Geçersiz dosya adı.');
                } elseif (!file_exists($path)) {
                    setFlash('error', 'Dosya bulunamadı.');
                } else {
                    if (@unlink($path)) {
                        setFlash('success', 'Backup silindi: ' . basename($path));
                    } else {
                        setFlash('error', 'Dosya silinemedi (izin hatası).');
                    }
                }
                break;

            case 'restore':
                $fname = (string) ($_POST['filename'] ?? '');
                $onay = ($_POST['onay'] ?? '') === 'GERI_YUKLE';
                $path = pastane_validate_backup_filename($fname, $BACKUP_DIR);

                if (!$onay) {
                    setFlash('error', 'Geri yükleme için onay metnini girmelisiniz.');
                } elseif ($path === null) {
                    setFlash('error', 'Geçersiz dosya adı.');
                } elseif (!file_exists($path)) {
                    setFlash('error', 'Dosya bulunamadı.');
                } else {
                    // MySQL CLI + source veya PHP-native (küçük dosyalar için)
                    try {
                        $restored = pastane_restore_backup($path);
                        if ($restored) {
                            setFlash('success', 'Geri yükleme başarılı: ' . basename($path));
                        } else {
                            setFlash('error', 'Geri yükleme başarısız oldu (detay için log).');
                        }
                    } catch (\Throwable $e) {
                        setFlash('error', 'Geri yükleme hatası: ' . $e->getMessage());
                    }
                }
                break;

            case 'download':
                $fname = (string) ($_POST['filename'] ?? '');
                $path = pastane_validate_backup_filename($fname, $BACKUP_DIR);
                if ($path === null || !file_exists($path)) {
                    setFlash('error', 'Dosya bulunamadı.');
                } else {
                    // Direkt download response
                    header('Content-Type: application/gzip');
                    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                    header('Content-Length: ' . filesize($path));
                    header('Cache-Control: private, no-cache, no-store');
                    readfile($path);
                    exit;
                }
                break;

            default:
                setFlash('error', 'Bilinmeyen işlem.');
        }
    }

    // PRG
    header('Location: backup.php');
    exit;
}

/**
 * Backup dosyasını geri yükle
 *
 * @param string $fullPath
 * @return bool
 */
function pastane_restore_backup(string $fullPath): bool
{
    $isGz = str_ends_with($fullPath, '.gz');

    // Gzip'i decompress et temp SQL'e
    if ($isGz) {
        $tmpSql = $fullPath . '.decompressed.sql';
        $in = @gzopen($fullPath, 'rb');
        $out = @fopen($tmpSql, 'wb');
        if ($in === false || $out === false) {
            throw new \RuntimeException('Decompress için dosya açılamadı.');
        }
        while (!gzeof($in)) {
            fwrite($out, (string) gzread($in, 65536));
        }
        gzclose($in);
        fclose($out);
        $sqlFile = $tmpSql;
    } else {
        $sqlFile = $fullPath;
    }

    try {
        $pdo = db()->getPdo();
        $sql = (string) file_get_contents($sqlFile);

        // Basit statement parse (noktalı virgülle ayır)
        // NOT: büyük / karmaşık dumplarda mysql CLI kullanılması önerilir
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $statements = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $pdo->exec($stmt);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        return true;
    } finally {
        if ($isGz && isset($tmpSql) && file_exists($tmpSql)) {
            @unlink($tmpSql);
        }
    }
}

// Backup listesi
$backups = [];
foreach (glob($BACKUP_DIR . DIRECTORY_SEPARATOR . 'backup-*.sql*') ?: [] as $path) {
    $basename = basename($path);
    if (!preg_match('/^backup-\d{8}-\d{6}\.sql(\.gz)?$/', $basename)) {
        continue;
    }
    $backups[] = [
        'name'  => $basename,
        'size'  => filesize($path) ?: 0,
        'mtime' => filemtime($path) ?: 0,
    ];
}
// Yeniden → eskiye sırala
usort($backups, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

require __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="page-header">
        <h1>Veritabanı Yedekleme</h1>
        <p class="muted">Backup dosyalarını yönet, yeni yedek oluştur, geri yükleme yap.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Şimdi Yedekle</h2>
        </div>
        <div class="card-body">
            <form method="post" action="backup.php" data-confirm-submit="Yeni bir backup oluşturulacak. Devam edilsin mi?">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="backup-now">
                <button type="submit" class="btn btn-primary">Yedek Oluştur</button>
            </form>
            <p class="muted u-mt-12px">
                Komut: <code>bin/cron/db-backup.php</code>. Log: <code>storage/logs/db-backup.log</code>.
                Otomatik temizlik: 30 günden eski yedekler silinir.
            </p>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Mevcut Yedekler (<?= count($backups) ?>)</h2>
        </div>
        <div class="card-body">
            <?php if (empty($backups)): ?>
                <p class="muted">Henüz backup yok.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Dosya</th>
                            <th scope="col">Tarih</th>
                            <th scope="col">Boyut</th>
                            <th scope="col">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                            <tr>
                                <td><code><?= e($b['name']) ?></code></td>
                                <td><?= e(date('d.m.Y H:i:s', $b['mtime'])) ?></td>
                                <td><?= e(number_format($b['size'] / 1024 / 1024, 2, ',', '.')) ?> MB</td>
                                <td class="u-flex-row-gap-2-wrap">
                                    <form method="post" action="backup.php" class="u-d-inline">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="download">
                                        <input type="hidden" name="filename" value="<?= e($b['name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">İndir</button>
                                    </form>
                                    <form method="post" action="backup.php" class="u-d-inline" data-confirm-submit="Backup silinecek. Emin misiniz?">
                                        <?= csrfTokenField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="filename" value="<?= e($b['name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-warning"
                                            data-action="open-restore"
                                            data-filename="<?= e($b['name']) ?>">
                                        Geri Yükle
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <section class="card u-hidden" id="restore-section">
        <div class="card-header">
            <h2 class="u-text-danger">TEHLİKELİ: Geri Yükleme</h2>
        </div>
        <div class="card-body">
            <p><strong>Uyarı:</strong> Bu işlem mevcut veritabanını seçtiğiniz backup ile değiştirir. Geri alınamaz.</p>
            <form method="post" action="backup.php">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="filename" id="restore-filename" value="">
                <div class="form-group">
                    <label for="restore-file-display">Seçili dosya:</label>
                    <input type="text" id="restore-file-display" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="restore-onay">Onaylamak için kutuya <code>GERI_YUKLE</code> yazın:</label>
                    <input type="text" id="restore-onay" name="onay" class="form-control" required pattern="GERI_YUKLE">
                </div>
                <button type="submit" class="btn btn-danger">GERİ YÜKLE</button>
                <button type="button" class="btn btn-secondary" data-action="close-restore">İptal</button>
            </form>
        </div>
    </section>
</main>

<script nonce="<?= e(getCspNonce()) ?>">
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-action]');
        if (!trigger) return;

        const action = trigger.dataset.action;
        if (action === 'open-restore') {
            const fname = trigger.dataset.filename || '';
            const section = document.getElementById('restore-section');
            const hidden = document.getElementById('restore-filename');
            const display = document.getElementById('restore-file-display');
            if (section && hidden && display) {
                hidden.value = fname;
                display.value = fname;
                section.style.display = 'block';
                section.scrollIntoView({ behavior: 'smooth' });
            }
        } else if (action === 'close-restore') {
            const section = document.getElementById('restore-section');
            if (section) section.style.display = 'none';
        }
    });

    // Form submit confirm (data-confirm-submit)
    document.querySelectorAll('form[data-confirm-submit]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            const msg = f.getAttribute('data-confirm-submit') || 'Emin misiniz?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
