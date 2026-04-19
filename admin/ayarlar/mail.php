<?php
/**
 * Mail Ayarlari — Admin Panel
 *
 * SMTP konfigurasyonunu goruntule + test email gonder.
 * Ayarlar .env dosyasindan okunur (read-only); test butonu ile canli dogrulama yapilir.
 *
 * @package Pastane\Admin
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$mailConfig = require __DIR__ . '/../../config/mail.php';

$sonuc = null;
$hata  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $hata = 'Guvenlik dogrulamasi basarisiz (CSRF token).';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'test') {
            $aliciEmail = trim((string)($_POST['test_email'] ?? ''));

            if (!filter_var($aliciEmail, FILTER_VALIDATE_EMAIL)) {
                $hata = 'Gecersiz email adresi. Lutfen gecerli bir email giriniz.';
            } else {
                try {
                    $emailService = new EmailService();
                    $basarili = $emailService->sendTestEmail($aliciEmail);

                    if ($basarili) {
                        $sonuc = 'Test email basariyla gonderildi: ' . $aliciEmail;
                        setFlash('success', $sonuc);
                    } else {
                        $hata = 'Email gonderilemedi: ' . $emailService->getLastError();
                        setFlash('error', $hata);
                    }
                } catch (\Throwable $e) {
                    $hata = 'Email servisi hatasi: ' . $e->getMessage();
                    setFlash('error', $hata);
                }
            }
        }
    }

    // POST sonrasi redirect (PRG pattern)
    header('Location: mail.php');
    exit;
}

// Log dosyasi preview (son 20 satir)
$logPath = $mailConfig['log_path'] ?? '';
$logSatirlari = [];
if ($logPath !== '' && file_exists($logPath)) {
    $tumSatirlar = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logSatirlari = array_slice($tumSatirlar, -20);
}

require __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="page-header">
        <h1>Mail Ayarlari</h1>
        <p class="muted">SMTP konfigurasyonu ve test email gonderimi.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Mevcut Konfigurasyon</h2>
            <span class="badge <?= !empty($mailConfig['enabled']) ? 'badge-success' : 'badge-warning' ?>">
                <?= !empty($mailConfig['enabled']) ? 'Etkin' : 'Devre Disi' ?>
            </span>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th scope="row">Driver</th><td><code><?= e($mailConfig['driver'] ?? 'bilinmiyor') ?></code></td></tr>
                    <tr><th scope="row">SMTP Host</th><td><?= e($mailConfig['host'] ?? '-') ?></td></tr>
                    <tr><th scope="row">SMTP Port</th><td><?= e((string)($mailConfig['port'] ?? '-')) ?></td></tr>
                    <tr><th scope="row">Encryption</th><td><?= e($mailConfig['encryption'] ?? '-') ?></td></tr>
                    <tr><th scope="row">Kullanici Adi</th><td><?= e($mailConfig['username'] ?? '-') ?: '<em>(bos)</em>' ?></td></tr>
                    <tr><th scope="row">Sifre</th><td><?= !empty($mailConfig['password']) ? '<em>(ayarlanmis, gizli)</em>' : '<em>(bos)</em>' ?></td></tr>
                    <tr><th scope="row">Gonderici Adresi</th><td><?= e($mailConfig['from']['address'] ?? '-') ?></td></tr>
                    <tr><th scope="row">Gonderici Adi</th><td><?= e($mailConfig['from']['name'] ?? '-') ?></td></tr>
                </tbody>
            </table>
            <p class="muted u-mt-12px">
                Bu ayarlar <code>.env</code> dosyasindan okunur. Degisiklik yapmak icin <code>.env</code> dosyasini duzenleyin ve uygulamayi yeniden baslatin.
            </p>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Test Email Gonder</h2>
        </div>
        <div class="card-body">
            <form method="post" action="mail.php" class="form-inline">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="test">
                <div class="form-group">
                    <label for="test_email">Test Email Adresi</label>
                    <input
                        type="email"
                        id="test_email"
                        name="test_email"
                        class="form-control"
                        placeholder="ornek@example.com"
                        required
                        aria-label="Test email alici adresi"
                    >
                </div>
                <button type="submit" class="btn btn-primary">Test Email Gonder</button>
            </form>
            <p class="muted u-mt-12px">
                Test email gondererek SMTP yapilandirmanizin dogru calistigini dogrulayabilirsiniz.
                Gonderim denemeleri <code>storage/logs/email.log</code> dosyasina kaydedilir.
            </p>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Son Email Loglari</h2>
        </div>
        <div class="card-body">
            <?php if (empty($logSatirlari)): ?>
                <p class="muted">Henuz email gonderimi yapilmamis.</p>
            <?php else: ?>
                <pre class="log-output u-log-block" aria-label="Email log ciktisi"><?php foreach ($logSatirlari as $satir): ?><?= e($satir) . "\n" ?><?php endforeach; ?></pre>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
