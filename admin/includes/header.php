<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/auth.php';

// Çıkış işlemi - POST + CSRF ile güvenli
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    // CSRF token doğrula
    $token = $_POST['csrf_token'] ?? '';
    if (function_exists('verifyCSRF') && verifyCSRF()) {
        logout();
    } elseif (function_exists('validateSecureCSRFToken') && validateSecureCSRFToken($token)) {
        logout();
    } else {
        // CSRF doğrulama başarısız - sessiz şekilde reddet
        setFlash('error', 'Güvenlik doğrulama hatası.');
    }
}

requireLogin();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadMessages = getUnreadMessageCount();

// Asset base path — admin/ kokunden cagrilirsa ../assets, admin/ayarlar/ gibi alt-dizinden ../../assets
// PHP_SELF script yolu: /pastane/admin/dashboard.php veya /pastane/admin/ayarlar/2fa.php
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$adminDepth = 0; // admin/ koku icindeyse 0, admin/ayarlar/ icindeyse 1
if (preg_match('#/admin/([^/]+)/#', $scriptPath, $m)) {
    $adminDepth = 1;
}
$assetsBase = str_repeat('../', $adminDepth + 1) . 'assets';
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('admin.panel_title')) ?> - <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/admin.css?v=3.3">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/components/toast.css?v=1.0">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/components/form-validator.css?v=1.0">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/components/empty-state.css?v=1.0">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/utilities.css?v=1.0">
    <link rel="stylesheet" href="<?= e($assetsBase) ?>/css/themes/dark.css?v=1.0">
    <meta name="theme-color" content="#FDF8F5">
    <?php
    // FOUC prevention: theme'i script erken uygula (render oncesi)
    // CSP nonce kullanir, inline script guvenlidir
    $cspNonce = function_exists('getCspNonce') ? getCspNonce() : '';
    ?>
    <script nonce="<?= e($cspNonce) ?>">
    (function () {
        try {
            var stored = localStorage.getItem('pastane_theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>
</head>
<body>
    <a href="#main-content" class="skip-link"><?= e(t('a11y.skip_to_content')) ?></a>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?= e(SITE_NAME) ?></h2>
                <span><?= e(t('admin.panel_title')) ?></span>
            </div>

            <nav class="sidebar-nav" id="sidebar-nav" aria-label="<?= e(t('nav.menu')) ?>">
                <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <?= e(t('admin.dashboard')) ?>
                </a>

                <a href="urunler.php" class="nav-item <?= $currentPage === 'urunler' || $currentPage === 'urun-ekle' || $currentPage === 'urun-duzenle' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                    </svg>
                    <?= e(t('admin.products')) ?>
                </a>

                <a href="kategoriler.php" class="nav-item <?= $currentPage === 'kategoriler' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="8" y1="6" x2="21" y2="6"/>
                        <line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/>
                        <line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    <?= e(t('admin.categories')) ?>
                </a>

                <a href="takvim.php" class="nav-item <?= $currentPage === 'takvim' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <?= e(t('admin.calendar')) ?>
                </a>

                <a href="masalar.php" class="nav-item <?= $currentPage === 'masalar' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="4" rx="1"/>
                        <line x1="6" y1="11" x2="6" y2="20"/>
                        <line x1="18" y1="11" x2="18" y2="20"/>
                    </svg>
                    <?= e(t('admin.tables')) ?>
                </a>

                <a href="masa-siparisleri.php" class="nav-item <?= $currentPage === 'masa-siparisleri' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                        <rect x="9" y="3" width="6" height="4" rx="1"/>
                        <path d="M9 14l2 2 4-4"/>
                    </svg>
                    <?= e(t('admin.table_orders')) ?>
                </a>

                <a href="mutfak.php" class="nav-item <?= $currentPage === 'mutfak' ? 'active' : '' ?>" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 13V7a6 6 0 1112 0v6"/>
                        <path d="M4 13h16"/>
                        <path d="M7 13v4a5 5 0 0010 0v-4"/>
                    </svg>
                    <span><?= e(t('admin.kitchen')) ?></span>
                    <small class="nav-external-hint"><?= e(t('admin.new_tab')) ?></small>
                </a>

                <a href="garson.php" class="nav-item <?= $currentPage === 'garson' ? 'active' : '' ?>" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M6 21v-2a6 6 0 0112 0v2"/>
                        <path d="M15 11l2-2"/>
                        <circle cx="18" cy="8" r="1"/>
                    </svg>
                    <span><?= e(t('admin.waiter')) ?></span>
                    <small class="nav-external-hint"><?= e(t('admin.new_tab')) ?></small>
                </a>

                <a href="raporlar.php" class="nav-item <?= $currentPage === 'raporlar' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 20V10"/>
                        <path d="M12 20V4"/>
                        <path d="M6 20v-6"/>
                    </svg>
                    <?= e(t('admin.reports')) ?>
                </a>

                <a href="musteriler.php" class="nav-item <?= $currentPage === 'musteriler' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                        <path d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                    <?= e(t('admin.customers')) ?>
                </a>

                <a href="mesajlar.php" class="nav-item <?= $currentPage === 'mesajlar' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                    </svg>
                    <?= e(t('admin.messages')) ?>
                    <?php if ($unreadMessages > 0): ?>
                        <span class="badge"><?= $unreadMessages ?></span>
                    <?php endif; ?>
                </a>

                <a href="temalar.php" class="nav-item <?= $currentPage === 'temalar' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/>
                        <line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/>
                        <line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    <?= e(t('admin.themes')) ?>
                </a>

                <a href="activity-log.php" class="nav-item <?= $currentPage === 'activity-log' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <?= e(t('admin.activity_log')) ?>
                </a>

                <?php
                // Ayarlar alt-menusu — hangi sayfa aktifse genisletilmis baslar
                $ayarlarPages = ['ayarlar-index', '2fa', 'mail', 'mail-templates', 'sms', 'backup', 'genel'];
                $ayarlarPath  = basename(dirname($_SERVER['PHP_SELF']));
                $ayarlarAcik  = ($ayarlarPath === 'ayarlar') || in_array($currentPage, $ayarlarPages, true);
                ?>
                <div class="nav-group <?= $ayarlarAcik ? 'is-open' : '' ?>" data-nav-group="ayarlar">
                    <button
                        type="button"
                        class="nav-item nav-group-toggle <?= $ayarlarAcik ? 'active' : '' ?>"
                        aria-expanded="<?= $ayarlarAcik ? 'true' : 'false' ?>"
                        aria-controls="nav-group-ayarlar"
                        data-action="toggle-nav-group"
                        data-target="ayarlar"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
                        </svg>
                        <span><?= e(t('admin.settings')) ?></span>
                        <svg class="nav-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div class="nav-group-items" id="nav-group-ayarlar" <?= $ayarlarAcik ? '' : 'hidden' ?>>
                        <a href="ayarlar/index.php" class="nav-item nav-sub <?= $currentPage === 'index' && $ayarlarPath === 'ayarlar' ? 'active' : '' ?>"><?= e(t('admin.general')) ?></a>
                        <a href="ayarlar/2fa.php" class="nav-item nav-sub <?= $currentPage === '2fa' ? 'active' : '' ?>"><?= e(t('admin.two_factor')) ?></a>
                        <a href="ayarlar/mail.php" class="nav-item nav-sub <?= $currentPage === 'mail' ? 'active' : '' ?>"><?= e(t('admin.email_settings')) ?></a>
                        <a href="ayarlar/sms.php" class="nav-item nav-sub <?= $currentPage === 'sms' ? 'active' : '' ?>"><?= e(t('admin.sms_settings')) ?></a>
                        <a href="ayarlar/mail-templates.php" class="nav-item nav-sub <?= $currentPage === 'mail-templates' ? 'active' : '' ?>"><?= e(t('admin.email_templates')) ?></a>
                        <a href="ayarlar/backup.php" class="nav-item nav-sub <?= $currentPage === 'backup' ? 'active' : '' ?>"><?= e(t('admin.backup')) ?></a>
                    </div>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" rel="noopener noreferrer" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                        <polyline points="15 3 21 3 21 9"/>
                        <line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                    <?= e(t('admin.view_site')) ?>
                </a>
                <form method="POST" action="" class="u-m-0">
                    <input type="hidden" name="csrf_token" value="<?= function_exists('generateSecureCSRFToken') ? generateSecureCSRFToken() : (function_exists('generateCSRF') ? generateCSRF() : '') ?>">
                    <input type="hidden" name="logout" value="1">
                    <button type="submit" class="nav-item logout u-link-btn-reset">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        <?= e(t('nav.logout')) ?>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <header class="topbar">
                <button class="menu-toggle" id="menuToggle"
                        type="button"
                        aria-label="<?= e(t('a11y.toggle_menu')) ?>"
                        aria-expanded="false"
                        aria-controls="sidebar-nav">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <button class="theme-toggle"
                        type="button"
                        data-action="toggle-theme"
                        aria-label="<?= e(t('a11y.switch_to_dark')) ?>"
                        aria-pressed="false"
                        title="<?= e(t('a11y.toggle_theme')) ?>">
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
                <?php
                // Dil secici (TR/EN)
                $currentLocale = locale();
                $currentUri = $_SERVER['REQUEST_URI'] ?? '';
                $sep = (str_contains($currentUri, '?')) ? '&' : '?';
                ?>
                <div class="lang-switcher" role="group" aria-label="<?= e(t('a11y.language_menu')) ?>">
                    <a href="<?= e($currentUri . $sep) ?>lang=tr"
                       class="lang-link <?= $currentLocale === 'tr' ? 'is-active' : '' ?>"
                       aria-current="<?= $currentLocale === 'tr' ? 'true' : 'false' ?>"
                       title="<?= e(t('language.turkish')) ?>"><?= e(t('language.tr_short')) ?></a>
                    <span aria-hidden="true">|</span>
                    <a href="<?= e($currentUri . $sep) ?>lang=en"
                       class="lang-link <?= $currentLocale === 'en' ? 'is-active' : '' ?>"
                       aria-current="<?= $currentLocale === 'en' ? 'true' : 'false' ?>"
                       title="<?= e(t('language.english')) ?>"><?= e(t('language.en_short')) ?></a>
                </div>
                <div class="user-info">
                    <span><?= e(t('admin.hello_user', ['name' => $_SESSION['admin_user'] ?? ''])) ?></span>
                </div>
            </header>

            <div class="content-area">
                <?php
                // Flash mesajları göster
                $flash = getFlash();
                if ($flash):
                ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= e($flash['message']) ?>
                </div>
                <?php endif; ?>
