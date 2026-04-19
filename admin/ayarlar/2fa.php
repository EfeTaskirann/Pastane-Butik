<?php
/**
 * Iki Faktorlu Dogrulama (2FA) Kurulum Ekrani — Admin Panel
 *
 * Admin kullanicisinin TOTP tabanli 2FA'yi etkinlestirmesi veya devre disi birakmasi icin UI.
 * Flow:
 *   - 2FA aktif degil: "Kur" butonu -> secret generate -> QR/secret goster -> TOTP kod dogrula -> backup codes
 *   - 2FA aktif: durum goster + "Devre Disi Birak" (sifre + TOTP ister)
 *
 * @package Pastane\Admin
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId       = (int)($_SESSION['admin_id'] ?? 0);
$userName     = (string)($_SESSION['admin_user'] ?? '');
$is2FAEnabled = TwoFactorAuth::isEnabled($userId);

// Setup akisi icin pending secret session'da tutulur (henuz DB'ye yazilmadi)
if (!isset($_SESSION['2fa_pending'])) {
    $_SESSION['2fa_pending'] = null;
}

// Backup codes son gosterim (etkinlestirme anindan sonra 1 defalik)
$showBackupCodes = null;
if (!empty($_SESSION['2fa_backup_codes_once'])) {
    $showBackupCodes = $_SESSION['2fa_backup_codes_once'];
    unset($_SESSION['2fa_backup_codes_once']);
}

// -------- POST HANDLER --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz (CSRF token).');
        header('Location: 2fa.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'setup_start':
                // Yeni secret olustur, pending olarak sakla
                if ($is2FAEnabled) {
                    setFlash('warning', '2FA zaten etkin. Once devre disi birakin.');
                    break;
                }
                $secret = TwoFactorAuth::generateSecret();
                $_SESSION['2fa_pending'] = [
                    'secret'     => $secret,
                    'created_at' => time(),
                ];
                setFlash('info', 'Kurulum baslatildi. QR kodu taratin ve 6 haneli kodu girin.');
                break;

            case 'setup_verify':
                // Pending secret'i dogrula ve etkinlestir
                $pending = $_SESSION['2fa_pending'] ?? null;
                if (!$pending || empty($pending['secret'])) {
                    setFlash('error', 'Kurulum oturumu bulunamadi. Lutfen tekrar baslatin.');
                    break;
                }
                // Pending 15 dakikadan uzunsa iptal
                if (time() - (int)$pending['created_at'] > 900) {
                    $_SESSION['2fa_pending'] = null;
                    setFlash('error', 'Kurulum suresi doldu. Lutfen tekrar baslatin.');
                    break;
                }

                $code = preg_replace('/\s+/', '', (string)($_POST['totp_code'] ?? ''));
                if (!TwoFactorAuth::verify($pending['secret'], $code)) {
                    setFlash('error', 'Dogrulama kodu hatali veya suresi gecmis. Tekrar deneyin.');
                    break;
                }

                $backupCodes = TwoFactorAuth::generateBackupCodes(10);
                $ok = TwoFactorAuth::enable($userId, $pending['secret'], $backupCodes);

                if (!$ok) {
                    setFlash('error', '2FA etkinlestirilemedi. Veritabani hatasi.');
                    break;
                }

                // Pending temizle, backup codes 1 defalik gosterim icin sessiona koy
                $_SESSION['2fa_pending']              = null;
                $_SESSION['2fa_backup_codes_once']    = $backupCodes;
                setFlash('success', '2FA basariyla etkinlestirildi. Yedek kodlarinizi mutlaka saklayin!');
                break;

            case 'setup_cancel':
                $_SESSION['2fa_pending'] = null;
                setFlash('info', 'Kurulum iptal edildi.');
                break;

            case 'disable':
                if (!$is2FAEnabled) {
                    setFlash('warning', '2FA zaten devre disi.');
                    break;
                }
                $password = (string)($_POST['current_password'] ?? '');
                $code     = preg_replace('/\s+/', '', (string)($_POST['totp_code'] ?? ''));

                // Sifre dogrula
                $user = db()->fetch(
                    "SELECT sifre_hash, two_factor_secret FROM admin_kullanicilar WHERE id = ?",
                    [$userId]
                );
                if (!$user || !password_verify($password, $user['sifre_hash'])) {
                    setFlash('error', 'Mevcut sifre hatali.');
                    break;
                }
                // TOTP dogrula
                if (!TwoFactorAuth::verify((string)$user['two_factor_secret'], $code)) {
                    setFlash('error', 'Dogrulama kodu hatali.');
                    break;
                }

                $ok = TwoFactorAuth::disable($userId);
                if ($ok) {
                    setFlash('success', '2FA devre disi birakildi.');
                } else {
                    setFlash('error', '2FA devre disi birakilamadi.');
                }
                break;

            default:
                setFlash('error', 'Bilinmeyen islem.');
        }
    } catch (\Throwable $e) {
        setFlash('error', '2FA islemi sirasinda hata: ' . $e->getMessage());
    }

    header('Location: 2fa.php');
    exit;
}

// -------- RENDER --------
$pending          = $_SESSION['2fa_pending'] ?? null;
$pendingSecret    = $pending['secret']    ?? '';
$provisioningUri  = $pendingSecret !== ''
    ? TwoFactorAuth::getProvisioningUri($pendingSecret, $userName)
    : '';

// Ekrandaki QR icin: img-src 'self' data: blob: https: -> Google Charts API kullanilabilir
// Privacy-friendly alternatif: kullanicinin secret + URI metinini authenticator app'e manuel girmesi.
$qrImgUrl = $provisioningUri !== ''
    ? TwoFactorAuth::getQRCodeUrl($provisioningUri, 256)
    : '';

require __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="page-header">
        <h1>Iki Faktorlu Dogrulama (2FA)</h1>
        <p class="muted">Hesabiniza ek guvenlik katmani ekleyin. TOTP (Google Authenticator, Authy, 1Password vb.) uyumludur.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($is2FAEnabled): ?>
        <!-- =============== 2FA AKTIF =============== -->
        <section class="card">
            <div class="card-header">
                <h2>Durum</h2>
                <span class="badge badge-success">Aktif</span>
            </div>
            <div class="card-body">
                <p>Hesabiniz iki faktorlu dogrulama ile korunuyor. Her girise authenticator uygulamanizdan 6 haneli kod istenecek.</p>

                <details class="two-fa-disable-details">
                    <summary>2FA'yi Devre Disi Birak</summary>
                    <form method="post" action="2fa.php" class="form-stacked u-max-w-420 u-mt-16px" data-loading>
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="action" value="disable">

                        <div class="form-group">
                            <label for="current_password">Mevcut Sifre</label>
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-control"
                                required
                                autocomplete="current-password"
                                data-validate="required|min:8"
                            >
                        </div>

                        <div class="form-group">
                            <label for="totp_code_disable">6 Haneli Dogrulama Kodu</label>
                            <input
                                type="text"
                                id="totp_code_disable"
                                name="totp_code"
                                class="form-control"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                autocomplete="one-time-code"
                                required
                                data-validate="required|numeric|min:6|max:6"
                            >
                        </div>

                        <button type="submit" class="btn btn-danger">Devre Disi Birak</button>
                        <p class="muted u-mt-12px">
                            Dikkat: 2FA devre disi birakildiktan sonra hesabiniz sadece sifre ile korunur.
                        </p>
                    </form>
                </details>
            </div>
        </section>

        <?php if ($showBackupCodes): ?>
            <section class="card">
                <div class="card-header">
                    <h2>Yedek Kodlariniz</h2>
                    <span class="badge badge-warning">Bir defa gosterilir</span>
                </div>
                <div class="card-body">
                    <p><strong>Bu kodlari hemen saklayin!</strong> Telefonunuzu kaybederseniz girise bu kodlardan birini kullanabilirsiniz. Her kod tek seferliktir.</p>
                    <ul class="backup-codes-list" id="backupCodesList">
                        <?php foreach ($showBackupCodes as $code): ?>
                            <li><code><?= e($code) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="form-actions u-mt-16px">
                        <button type="button" class="btn btn-secondary" data-action="copy-backup-codes">Panoya Kopyala</button>
                        <button type="button" class="btn btn-secondary" data-action="download-backup-codes">.txt Olarak Indir</button>
                    </div>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($pending): ?>
        <!-- =============== KURULUM DEVAM EDIYOR =============== -->
        <section class="card">
            <div class="card-header">
                <h2>Adim 1: Authenticator Uygulamasina Ekleyin</h2>
            </div>
            <div class="card-body">
                <p>Telefonunuzdaki authenticator uygulamasini acin ve asagidaki QR kodu taratin. QR taratamiyorsaniz, "Manuel Giris" secenegi ile secret'i elle kopyalayin.</p>

                <div class="two-fa-qr-wrap">
                    <?php if ($qrImgUrl !== ''): ?>
                        <img
                            src="<?= e($qrImgUrl) ?>"
                            alt="2FA QR Kodu"
                            width="256"
                            height="256"
                            class="two-fa-qr"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                        >
                        <p class="muted u-text-center u-mt-8px u-text-12">
                            QR yuklenemiyor mu? Manuel giris yapin.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="two-fa-manual">
                    <label for="pending_secret" class="form-label">Manuel Giris (Secret Key):</label>
                    <div class="input-group">
                        <input
                            type="text"
                            id="pending_secret"
                            class="form-control"
                            value="<?= e($pendingSecret) ?>"
                            readonly
                            aria-label="2FA secret key"
                        >
                        <button type="button" class="btn btn-secondary" data-action="copy-value" data-target="#pending_secret">Kopyala</button>
                    </div>
                    <p class="muted u-mt-8px">
                        Hesap adi: <strong><?= e($userName) ?></strong> |
                        Yayinci: <strong><?= e(defined('SITE_NAME') ? SITE_NAME : 'Pastane') ?></strong>
                    </p>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2>Adim 2: Kodu Dogrulayin</h2>
            </div>
            <div class="card-body">
                <form method="post" action="2fa.php" class="form-stacked u-max-w-420" data-loading>
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="setup_verify">

                    <div class="form-group">
                        <label for="totp_code_verify">Uygulamadaki 6 Haneli Kod</label>
                        <input
                            type="text"
                            id="totp_code_verify"
                            name="totp_code"
                            class="form-control"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                            autofocus
                            data-validate="required|numeric|min:6|max:6"
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Dogrula ve Etkinlestir</button>
                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-action="cancel-setup"
                        >Iptal</button>
                    </div>
                </form>

                <!-- Iptal formu (ayri -> CSRF tek basina) -->
                <form method="post" action="2fa.php" id="cancelForm" class="u-hidden">
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="setup_cancel">
                </form>
            </div>
        </section>

    <?php else: ?>
        <!-- =============== 2FA PASIF — KURULUM BASLAT =============== -->
        <section class="card">
            <div class="card-header">
                <h2>Durum</h2>
                <span class="badge badge-warning">Etkin Degil</span>
            </div>
            <div class="card-body">
                <p>Hesabiniz henuz iki faktorlu dogrulama ile korunmuyor. Etkinlestirmek icin asagidaki adimlari izleyin:</p>
                <ol class="steps-list">
                    <li>Telefonunuza bir authenticator uygulamasi kurun (Google Authenticator, Authy, 1Password, Microsoft Authenticator).</li>
                    <li>Asagidaki <strong>"Kurulumu Baslat"</strong> butonuna basin.</li>
                    <li>QR kodu taratin veya secret'i manuel girin.</li>
                    <li>Uygulamadaki 6 haneli kodu girerek etkinlestirin.</li>
                    <li>Size verilecek <strong>yedek kodlari</strong> guvenli bir yere kaydedin.</li>
                </ol>

                <form method="post" action="2fa.php" class="u-mt-16px" data-loading>
                    <?= csrfTokenField() ?>
                    <input type="hidden" name="action" value="setup_start">
                    <button type="submit" class="btn btn-primary">Kurulumu Baslat</button>
                </form>
            </div>
        </section>
    <?php endif; ?>

</main>

<script nonce="<?= e(getCspNonce()) ?>">
(function(){
    'use strict';

    // Kopyalama yardimci fonksiyonu
    function copyToClipboard(text, successMsg) {
        successMsg = successMsg || 'Kopyalandi';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){
                if (window.Toast) Toast.success(successMsg);
            }).catch(function(){
                fallbackCopy(text, successMsg);
            });
        } else {
            fallbackCopy(text, successMsg);
        }
    }
    function fallbackCopy(text, successMsg) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (window.Toast) Toast.success(successMsg);
        } catch (e) {
            if (window.Toast) Toast.error('Kopyalama basarisiz');
        }
        document.body.removeChild(ta);
    }

    // Backup codes toplam
    function getBackupCodes() {
        var list = document.getElementById('backupCodesList');
        if (!list) return [];
        var codes = [];
        list.querySelectorAll('code').forEach(function(el){
            codes.push(el.textContent.trim());
        });
        return codes;
    }

    // Global click delegation
    document.addEventListener('click', function(e){
        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        var action = btn.getAttribute('data-action');

        if (action === 'copy-value') {
            var targetSel = btn.getAttribute('data-target');
            if (!targetSel) return;
            var target = document.querySelector(targetSel);
            if (!target) return;
            copyToClipboard(target.value || target.textContent || '', 'Kopyalandi');
        }

        if (action === 'copy-backup-codes') {
            var codes = getBackupCodes();
            if (!codes.length) return;
            copyToClipboard(codes.join('\n'), 'Yedek kodlar kopyalandi');
        }

        if (action === 'download-backup-codes') {
            var codesDl = getBackupCodes();
            if (!codesDl.length) return;
            var header = 'Pastane Yonetim Paneli — 2FA Yedek Kodlari\n';
            header += 'Kullanici: <?= e($userName) ?>\n';
            header += 'Olusturma: ' + new Date().toISOString() + '\n';
            header += '-----------------------------------------\n';
            header += 'Her kod tek seferliktir. Guvenli saklayin.\n\n';
            var body = codesDl.join('\n');
            var blob = new Blob([header + body + '\n'], { type: 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'pastane-2fa-yedek-kodlar.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function(){ URL.revokeObjectURL(url); }, 0);
            if (window.Toast) Toast.success('Indirme baslatildi');
        }
    });

    // Cancel button click -> cancel form submit
    document.addEventListener('click', function(e){
        var cb = e.target.closest('[data-action="cancel-setup"]');
        if (!cb) return;
        e.preventDefault();
        var cf = document.getElementById('cancelForm');
        if (cf) cf.submit();
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
