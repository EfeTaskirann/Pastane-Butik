<?php
/**
 * Admin — SMS Ayarları UI
 *
 * Özellikler:
 *   - Mevcut SMS driver / config görüntüle
 *   - Test SMS gönder
 *   - Son gönderim logları
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

$smsConfigPath = __DIR__ . '/../../config/sms.php';
$smsConfig = file_exists($smsConfigPath) ? require $smsConfigPath : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Güvenlik doğrulaması başarısız (CSRF token).');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'test-sms') {
            $telefon = trim((string) ($_POST['test_phone'] ?? ''));

            try {
                $smsService = new SmsService();
                $normalized = $smsService->normalizePhone($telefon);
                if ($normalized === null) {
                    setFlash('error', 'Geçersiz telefon numarası. Örnek: 05551234567');
                } else {
                    $ok = $smsService->sendTestSms($telefon);
                    if ($ok) {
                        setFlash('success', 'Test SMS gönderildi: +' . $normalized);
                    } else {
                        setFlash('error', 'SMS gönderilemedi: ' . $smsService->getLastError());
                    }
                }
            } catch (\Throwable $e) {
                setFlash('error', 'SMS servisi hatası: ' . $e->getMessage());
            }
        }
    }

    header('Location: sms.php');
    exit;
}

// Log önizleme
$logSatirlari = [];
$logPath = (string) ($smsConfig['log_path'] ?? '');
if ($logPath !== '' && file_exists($logPath)) {
    $tumSatirlar = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $logSatirlari = array_slice($tumSatirlar, -20);
}

require __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="page-header">
        <h1>SMS Ayarları</h1>
        <p class="muted">SMS driver yapılandırması ve test gönderimi.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Mevcut Konfigürasyon</h2>
            <span class="badge <?= !empty($smsConfig['enabled']) ? 'badge-success' : 'badge-warning' ?>">
                <?= !empty($smsConfig['enabled']) ? 'Etkin' : 'Devre Dışı' ?>
            </span>
        </div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th scope="row">Driver</th><td><code><?= e((string) ($smsConfig['driver'] ?? '-')) ?></code></td></tr>
                    <tr><th scope="row">API Key</th><td><?= !empty($smsConfig['api_key']) ? '<em>(ayarlı)</em>' : '<em>(boş)</em>' ?></td></tr>
                    <tr><th scope="row">API Secret</th><td><?= !empty($smsConfig['api_secret']) ? '<em>(ayarlı, gizli)</em>' : '<em>(boş)</em>' ?></td></tr>
                    <tr><th scope="row">Sender</th><td><?= e((string) ($smsConfig['sender'] ?? '-')) ?></td></tr>
                    <tr><th scope="row">Ülke Kodu</th><td>+<?= e((string) ($smsConfig['country_code'] ?? '90')) ?></td></tr>
                    <tr><th scope="row">Template Dizini</th><td><code><?= e((string) ($smsConfig['templates_path'] ?? '')) ?></code></td></tr>
                </tbody>
            </table>
            <p class="muted u-mt-12px">
                Bu ayarlar <code>.env</code> dosyasından okunur. Değişiklik için <code>.env</code> dosyasını düzenleyip uygulamayı yeniden başlatın.
                Desteklenen driver'lar: <code>log</code>, <code>netgsm</code>, <code>twilio</code>.
            </p>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Test SMS Gönder</h2>
        </div>
        <div class="card-body">
            <form method="post" action="sms.php" class="form-inline">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="test-sms">
                <div class="form-group">
                    <label for="test_phone">Test Telefon Numarası</label>
                    <input
                        type="tel"
                        id="test_phone"
                        name="test_phone"
                        class="form-control"
                        placeholder="05551234567"
                        required
                        pattern="[0-9+\s\(\)\-]{10,20}"
                        aria-label="Test SMS alıcı telefon numarası"
                    >
                </div>
                <button type="submit" class="btn btn-primary">Test SMS Gönder</button>
            </form>
            <p class="muted u-mt-12px">
                Türk mobil formatları desteklenir: <code>05551234567</code>, <code>+905551234567</code>, <code>5551234567</code>.
                Gönderim logu: <code><?= e((string) ($smsConfig['log_path'] ?? 'storage/logs/sms.log')) ?></code>.
            </p>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2>Son SMS Logları</h2>
        </div>
        <div class="card-body">
            <?php if (empty($logSatirlari)): ?>
                <p class="muted">Henüz SMS gönderimi yapılmamış.</p>
            <?php else: ?>
                <pre class="log-output u-log-block" aria-label="SMS log çıktısı"><?php foreach ($logSatirlari as $satir): ?><?= e($satir) . "\n" ?><?php endforeach; ?></pre>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
