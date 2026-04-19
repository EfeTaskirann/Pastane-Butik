<?php
/**
 * Ayarlar — Genel Yonetim Sayfasi
 *
 * Tum site ayarlarini gruplara gore listeler ve duzenler.
 * Backend: Sprint 2'de `AyarService` + `ayarlar` tablosu eklenecek.
 * Hazir degilse UI, mevcut konfig degerlerini readonly olarak gosterir.
 *
 * @package Pastane\Admin
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// ========= Backend Entegrasyon Tespiti =========
/** Backend Sprint 2 bu sinifi olusturacak. Hazirsa dinamik UI, degilse fallback. */
$ayarService = null;
if (class_exists('AyarService')) {
    try {
        $ayarService = new AyarService();
    } catch (\Throwable $e) {
        $ayarService = null;
    }
}
// Namespaced alternatif
if (!$ayarService && class_exists('\\Pastane\\Services\\AyarService')) {
    try {
        $ayarService = new \Pastane\Services\AyarService();
    } catch (\Throwable $e) {
        $ayarService = null;
    }
}

// ========= Grup Tanimlari =========
/**
 * Ayar gruplari ve her bir grup icindeki ayar sema'si.
 * AyarService hazirken ayar listesi DB'den cekilir; yoksa bu liste ile
 * placeholder UI gosterilir.
 */
$ayarGruplari = [
    'site' => [
        'baslik'  => 'Site Ayarlari',
        'ikon'    => 'globe',
        'ayarlar' => [
            ['key' => 'site.name',             'label' => 'Site Adi',              'type' => 'string', 'desc' => 'Baslik ve email icinde kullanilir', 'default' => defined('SITE_NAME') ? SITE_NAME : 'Pastane'],
            ['key' => 'site.tagline',          'label' => 'Slogan',                'type' => 'string', 'desc' => 'Ana sayfa hero slogani'],
            ['key' => 'site.logo_url',         'label' => 'Logo URL',              'type' => 'string', 'desc' => 'Logo dosya yolu (/assets/img/logo.png)'],
            ['key' => 'site.maintenance_mode', 'label' => 'Bakim Modu',            'type' => 'bool',   'desc' => 'Etkinlestirildiginde siteye ziyaretciler giremez', 'default' => false],
            ['key' => 'site.timezone',         'label' => 'Saat Dilimi',           'type' => 'string', 'desc' => 'IANA timezone (orn: Europe/Istanbul)', 'default' => 'Europe/Istanbul'],
        ],
    ],
    'iletisim' => [
        'baslik'  => 'Iletisim Bilgileri',
        'ikon'    => 'phone',
        'ayarlar' => [
            ['key' => 'contact.phone',   'label' => 'Telefon',      'type' => 'string', 'desc' => 'Footer ve iletisim sayfasi'],
            ['key' => 'contact.email',   'label' => 'Email',        'type' => 'string', 'desc' => 'Musteri iletisim adresi'],
            ['key' => 'contact.address', 'label' => 'Adres',        'type' => 'json',   'desc' => 'Fiziki adres (cok satirli)'],
            ['key' => 'contact.hours',   'label' => 'Calisma Saatleri', 'type' => 'json', 'desc' => 'JSON: {"pzt":"09:00-22:00", ...}'],
        ],
    ],
    'siparis' => [
        'baslik'  => 'Siparis Ayarlari',
        'ikon'    => 'shopping-cart',
        'ayarlar' => [
            ['key' => 'order.min_amount',       'label' => 'Minimum Siparis Tutari',  'type' => 'int', 'desc' => 'TL cinsinden', 'default' => 100],
            ['key' => 'order.delivery_fee',     'label' => 'Teslimat Ucreti',         'type' => 'int', 'desc' => 'TL cinsinden'],
            ['key' => 'order.free_delivery_threshold', 'label' => 'Ucretsiz Teslimat Alt Limiti', 'type' => 'int'],
            ['key' => 'order.auto_confirm',     'label' => 'Otomatik Onayla',         'type' => 'bool'],
            ['key' => 'order.prep_time_minutes','label' => 'Ortalama Hazirlama (dk)', 'type' => 'int', 'default' => 30],
        ],
    ],
    'odeme' => [
        'baslik'  => 'Odeme Ayarlari',
        'ikon'    => 'credit-card',
        'ayarlar' => [
            ['key' => 'payment.cash_enabled',    'label' => 'Nakit Odeme',        'type' => 'bool', 'default' => true],
            ['key' => 'payment.card_enabled',    'label' => 'Kart Odeme',         'type' => 'bool', 'default' => true],
            ['key' => 'payment.online_enabled',  'label' => 'Online Odeme (iyzico)', 'type' => 'bool'],
            ['key' => 'payment.iban',            'label' => 'IBAN (havale)',      'type' => 'string'],
            ['key' => 'payment.account_holder',  'label' => 'Hesap Sahibi',       'type' => 'string'],
        ],
    ],
    'bildirim' => [
        'baslik'  => 'Bildirim Ayarlari',
        'ikon'    => 'bell',
        'ayarlar' => [
            ['key' => 'notify.email_new_order',     'label' => 'Yeni Siparis Email',   'type' => 'bool', 'default' => true],
            ['key' => 'notify.sms_new_order',       'label' => 'Yeni Siparis SMS',     'type' => 'bool'],
            ['key' => 'notify.email_low_stock',     'label' => 'Dusuk Stok Email',     'type' => 'bool'],
            ['key' => 'notify.admin_email',         'label' => 'Yonetici Email Adresi','type' => 'string', 'desc' => 'Bildirimler buraya gelir'],
            ['key' => 'notify.daily_report_enabled','label' => 'Gunluk Rapor Emaili',  'type' => 'bool'],
        ],
    ],
];

// ========= POST HANDLER =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz (CSRF token).');
        header('Location: index.php');
        exit;
    }

    $activeGroup = (string)($_POST['_group'] ?? '');
    $valuesRaw   = $_POST['ayarlar'] ?? [];

    if (!$ayarService) {
        setFlash('warning', 'Ayar backend henuz hazir degil. Degisiklikler kaydedilmedi. (AyarService sinifi bulunamadi — Sprint 2 Backend tamamlanmasi bekleniyor.)');
        header('Location: index.php?group=' . urlencode($activeGroup));
        exit;
    }

    $basariliSayisi = 0;
    $hataliAyarlar  = [];

    if (is_array($valuesRaw)) {
        foreach ($valuesRaw as $key => $val) {
            $key = (string)$key;
            // Checkbox bool: checkbox isaretlenmezse POST'a hic gelmez — aktif tab'in tum bool'larini infer et
            try {
                if (method_exists($ayarService, 'setKey')) {
                    $ayarService->setKey($key, $val);
                    $basariliSayisi++;
                } elseif (method_exists($ayarService, 'set')) {
                    $ayarService->set($key, $val);
                    $basariliSayisi++;
                } else {
                    $hataliAyarlar[] = $key . ' (set metodu yok)';
                }
            } catch (\Throwable $e) {
                $hataliAyarlar[] = $key . ' (' . $e->getMessage() . ')';
            }
        }
    }

    // Aktif grup icindeki bool ayarlari: POST'ta yoksa false yaz
    if (isset($ayarGruplari[$activeGroup]) && $ayarService) {
        foreach ($ayarGruplari[$activeGroup]['ayarlar'] as $ayar) {
            if ($ayar['type'] === 'bool' && !isset($valuesRaw[$ayar['key']])) {
                try {
                    if (method_exists($ayarService, 'setKey')) {
                        $ayarService->setKey($ayar['key'], '0');
                    } elseif (method_exists($ayarService, 'set')) {
                        $ayarService->set($ayar['key'], '0');
                    }
                } catch (\Throwable $e) {
                    // sessiz gec
                }
            }
        }
    }

    if ($basariliSayisi > 0 && empty($hataliAyarlar)) {
        setFlash('success', $basariliSayisi . ' ayar basariyla kaydedildi.');
    } elseif ($basariliSayisi > 0) {
        setFlash('warning', $basariliSayisi . ' ayar kaydedildi, ' . count($hataliAyarlar) . ' hata: ' . implode(', ', array_slice($hataliAyarlar, 0, 3)));
    } else {
        setFlash('error', 'Ayarlar kaydedilemedi. ' . implode(', ', array_slice($hataliAyarlar, 0, 3)));
    }

    header('Location: index.php?group=' . urlencode($activeGroup));
    exit;
}

// ========= Mevcut Degerleri Al =========
/**
 * AyarService varsa her key icin deger cek, yoksa default'lari goster.
 */
$mevcutDegerler = [];
foreach ($ayarGruplari as $grupKey => $grup) {
    foreach ($grup['ayarlar'] as $ayar) {
        $val = $ayar['default'] ?? '';
        if ($ayarService) {
            try {
                if (method_exists($ayarService, 'getKey')) {
                    $dbVal = $ayarService->getKey($ayar['key'], $val);
                    $val = $dbVal;
                } elseif (method_exists($ayarService, 'get')) {
                    $dbVal = $ayarService->get($ayar['key'], $val);
                    $val = $dbVal;
                }
            } catch (\Throwable $e) {
                // sessiz gec
            }
        }
        $mevcutDegerler[$ayar['key']] = $val;
    }
}

$aktifGrup = (string)($_GET['group'] ?? 'site');
if (!isset($ayarGruplari[$aktifGrup])) {
    $aktifGrup = 'site';
}

require __DIR__ . '/../includes/header.php';
?>

<main class="admin-main">
    <div class="page-header">
        <h1>Ayarlar</h1>
        <p class="muted">Site genelindeki ayarlari grup bazinda yonetin.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (!$ayarService): ?>
        <div class="alert alert-warning" role="alert">
            <strong>Not:</strong> <code>AyarService</code> sinifi henuz yuklenmedi (Sprint 2 Backend tamamlanmasi bekleniyor).
            Ayar degerleri varsayilan olarak gosteriliyor; "Kaydet" islemi yapilsa da veritabanina yazilmaz.
            Backend hazir olduktan sonra bu sayfa tam calisir hale gelecektir.
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="settings-tabs" role="tablist" aria-label="Ayar gruplari">
        <?php foreach ($ayarGruplari as $grupKey => $grup): ?>
            <button
                type="button"
                class="settings-tab <?= $grupKey === $aktifGrup ? 'is-active' : '' ?>"
                data-action="switch-tab"
                data-tab="<?= e($grupKey) ?>"
                role="tab"
                aria-selected="<?= $grupKey === $aktifGrup ? 'true' : 'false' ?>"
                aria-controls="panel-<?= e($grupKey) ?>"
                id="tab-<?= e($grupKey) ?>"
            >
                <?= e($grup['baslik']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($ayarGruplari as $grupKey => $grup): ?>
        <section
            class="settings-panel <?= $grupKey === $aktifGrup ? 'is-active' : '' ?>"
            id="panel-<?= e($grupKey) ?>"
            role="tabpanel"
            aria-labelledby="tab-<?= e($grupKey) ?>"
            <?= $grupKey === $aktifGrup ? '' : 'hidden' ?>
        >
            <div class="card">
                <div class="card-header">
                    <h2><?= e($grup['baslik']) ?></h2>
                </div>
                <div class="card-body">
                    <form method="post" action="index.php" data-loading>
                        <?= csrfTokenField() ?>
                        <input type="hidden" name="_group" value="<?= e($grupKey) ?>">

                        <?php foreach ($grup['ayarlar'] as $ayar):
                            $k       = $ayar['key'];
                            $label   = $ayar['label'];
                            $type    = $ayar['type'];
                            $desc    = $ayar['desc'] ?? '';
                            $val     = $mevcutDegerler[$k] ?? ($ayar['default'] ?? '');
                            $inputId = 'ayar_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);
                        ?>
                            <div class="settings-row">
                                <div>
                                    <label for="<?= e($inputId) ?>" class="settings-label"><?= e($label) ?></label>
                                    <?php if ($desc): ?>
                                        <div class="settings-desc"><?= e($desc) ?></div>
                                    <?php endif; ?>
                                    <div class="settings-desc u-font-mono"><?= e($k) ?></div>
                                </div>
                                <div>
                                    <?php if ($type === 'bool'): ?>
                                        <label class="toggle-switch" for="<?= e($inputId) ?>">
                                            <input
                                                type="checkbox"
                                                id="<?= e($inputId) ?>"
                                                name="ayarlar[<?= e($k) ?>]"
                                                value="1"
                                                <?= (!empty($val) && $val !== '0' && $val !== 'false') ? 'checked' : '' ?>
                                            >
                                            <span class="toggle-slider" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?= e($label) ?></span>
                                        </label>

                                    <?php elseif ($type === 'int'): ?>
                                        <input
                                            type="number"
                                            id="<?= e($inputId) ?>"
                                            name="ayarlar[<?= e($k) ?>]"
                                            class="form-control"
                                            value="<?= e((string)$val) ?>"
                                            step="1"
                                            data-validate="numeric"
                                        >

                                    <?php elseif ($type === 'json'): ?>
                                        <textarea
                                            id="<?= e($inputId) ?>"
                                            name="ayarlar[<?= e($k) ?>]"
                                            class="form-control u-font-mono u-text-13"
                                            rows="4"
                                        ><?= e(is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$val) ?></textarea>

                                    <?php else: /* string */ ?>
                                        <input
                                            type="text"
                                            id="<?= e($inputId) ?>"
                                            name="ayarlar[<?= e($k) ?>]"
                                            class="form-control"
                                            value="<?= e((string)$val) ?>"
                                            data-validate="max:500"
                                        >
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="form-actions u-mt-20px">
                            <button type="submit" class="btn btn-primary" <?= $ayarService ? '' : 'disabled' ?>>
                                Kaydet
                            </button>
                            <?php if (!$ayarService): ?>
                                <span class="muted u-align-self-center">(Backend hazir degil — buton devre disi)</span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

</main>

<script nonce="<?= e(getCspNonce()) ?>">
(function(){
    'use strict';

    // Tab switching (URL querystring guncelleme + panel toggle)
    document.addEventListener('click', function(e){
        var btn = e.target.closest('[data-action="switch-tab"]');
        if (!btn) return;

        var tab = btn.getAttribute('data-tab');
        if (!tab) return;

        // Aktif tab butonu
        document.querySelectorAll('.settings-tab').forEach(function(el){
            var isActive = (el.getAttribute('data-tab') === tab);
            el.classList.toggle('is-active', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        // Paneller
        document.querySelectorAll('.settings-panel').forEach(function(panel){
            var isActive = (panel.id === 'panel-' + tab);
            panel.classList.toggle('is-active', isActive);
            if (isActive) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });

        // URL guncelle (history API, reload yok)
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('group', tab);
            window.history.replaceState({}, '', url.toString());
        } catch (_) { /* IE/eski tarayici */ }
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
