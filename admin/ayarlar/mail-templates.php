<?php

declare(strict_types=1);

/**
 * admin/ayarlar/mail-templates.php — Email Template Editor (P2-19)
 *
 * Storage'daki HTML template'leri listeler, düzenler, önizleme yapar ve
 * test email gönderimi sağlar. Source of truth: `storage/views/emails/*.html`
 * + `storage/views/emails/meta.json`.
 *
 * Güvenlik:
 *   - CSRF token zorunlu (save, send-test)
 *   - Input path traversal: template adı `[a-z0-9-]` regex match
 *   - Max template size: 200 KB
 *   - HTML output kullanıcıya her zaman iframe sandbox ile gösterilir
 */

require_once __DIR__ . '/../includes/header.php';

$templatesDir = dirname(__DIR__, 2) . '/storage/views/emails';
$metaPath = $templatesDir . '/meta.json';

// ---- Meta load ----
$meta = [];
if (is_file($metaPath)) {
    $raw = file_get_contents($metaPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
}

// ---- Template listeleme ----
$templates = [];
foreach (glob($templatesDir . '/*.html') ?: [] as $file) {
    $name = basename($file, '.html');
    $templates[$name] = [
        'name' => $name,
        'filename' => basename($file),
        'size' => filesize($file) ?: 0,
        'modified' => filemtime($file) ?: 0,
        'meta' => $meta[$name] ?? null,
    ];
}

$selected = $_GET['t'] ?? '';
if ($selected !== '' && !preg_match('/^[a-z0-9_-]+$/', $selected)) {
    $selected = '';
}

$currentContent = '';
$currentMeta = null;
$currentError = '';

if ($selected !== '' && isset($templates[$selected])) {
    $path = $templatesDir . '/' . $templates[$selected]['filename'];
    $currentContent = (string) file_get_contents($path);
    $currentMeta = $meta[$selected] ?? null;
}

// ---- Save POST ----
$saveMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $postName = $_POST['name'] ?? '';
    $postBody = $_POST['body'] ?? '';

    if (!preg_match('/^[a-z0-9_-]+$/', $postName)) {
        $currentError = 'Geçersiz şablon adı.';
    } elseif (strlen($postBody) > 200 * 1024) {
        $currentError = 'Şablon 200 KB sınırını aşıyor.';
    } elseif (!verifyCSRF()) {
        $currentError = 'Güvenlik kontrolü başarısız (CSRF).';
    } else {
        $path = $templatesDir . '/' . $postName . '.html';
        // Atomic write
        $tmpPath = $path . '.tmp';
        $wrote = file_put_contents($tmpPath, $postBody, LOCK_EX);
        if ($wrote !== false) {
            rename($tmpPath, $path);
            $saveMessage = 'Şablon kaydedildi.';
            $selected = $postName;
            $currentContent = $postBody;
        } else {
            $currentError = 'Dosya yazılamadı.';
        }
    }
}

// ---- Test gönderim POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send-test') {
    $postName = $_POST['name'] ?? '';
    $postTo = $_POST['to'] ?? '';

    if (!filter_var($postTo, FILTER_VALIDATE_EMAIL)) {
        $currentError = 'Geçersiz alıcı e-posta.';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $postName)) {
        $currentError = 'Geçersiz şablon adı.';
    } elseif (!verifyCSRF()) {
        $currentError = 'Güvenlik kontrolü başarısız (CSRF).';
    } elseif (!class_exists('EmailService')) {
        $currentError = 'EmailService mevcut değil.';
    } else {
        $meta_ = $meta[$postName] ?? null;
        $sample = $meta_['sample_data'] ?? ['site_adi' => 'Test', 'musteri_adi' => 'Test Kullanıcı'];
        $body = (string) file_get_contents($templatesDir . '/' . $postName . '.html');

        $rendered = $body;
        foreach ($sample as $k => $v) {
            $rendered = str_replace('{{{' . $k . '}}}', (string) $v, $rendered);
            $rendered = str_replace('{{' . $k . '}}', htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $rendered);
        }

        try {
            $emailService = new \EmailService();
            $subject = $meta_['subject_default'] ?? ('Test: ' . $postName);
            $ok = $emailService->send($postTo, $subject, $rendered);
            $saveMessage = $ok ? 'Test e-posta gönderildi.' : 'Gönderim başarısız — SMTP yapılandırmasını kontrol edin.';
        } catch (\Throwable $e) {
            $currentError = 'Gönderim hatası: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
        $selected = $postName;
    }
}

$cspNonce = function_exists('getCspNonce') ? getCspNonce() : '';

?>
<h2 class="u-mb-5">E-posta Şablonları</h2>

<?php if ($saveMessage !== ''): ?>
    <div class="alert alert-success"><?= e($saveMessage) ?></div>
<?php endif; ?>

<?php if ($currentError !== ''): ?>
    <div class="alert alert-danger"><?= $currentError /* already escaped */ ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Şablon Listesi</h3>
    </div>
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <?php
            $empty = [
                'title' => 'Henüz e-posta şablonu yok',
                'description' => 'storage/views/emails/ klasörüne bir .html dosyası ekleyin.',
            ];
            require __DIR__ . '/../../views/admin/_partials/empty-state.php';
            ?>
        <?php else: ?>
            <table class="u-w-100">
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Konu</th>
                        <th>Değişkenler</th>
                        <th>Boyut</th>
                        <th>Güncellenme</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr<?= $selected === $t['name'] ? ' class="is-selected"' : '' ?>>
                        <td><strong><?= e($t['name']) ?></strong></td>
                        <td><?= e($t['meta']['subject_default'] ?? '-') ?></td>
                        <td>
                            <?php foreach ((array)($t['meta']['variables'] ?? []) as $v): ?>
                                <code><?= e($v) ?></code>
                            <?php endforeach; ?>
                        </td>
                        <td><?= number_format((int)$t['size']) ?> B</td>
                        <td><?= date('d.m.Y H:i', (int)$t['modified']) ?></td>
                        <td>
                            <a href="?t=<?= e($t['name']) ?>" class="btn btn-sm btn-secondary">Düzenle</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($selected !== '' && isset($templates[$selected])): ?>
<div class="card u-mt-5">
    <div class="card-header">
        <h3>Düzenle: <?= e($selected) ?></h3>
    </div>
    <div class="card-body">
        <?php if ($currentMeta): ?>
            <p class="u-text-muted u-mb-3"><?= e($currentMeta['description'] ?? '') ?></p>
            <?php if (!empty($currentMeta['variables'])): ?>
                <div class="u-mb-3">
                    <strong>Kullanılabilir değişkenler:</strong>
                    <?php foreach ($currentMeta['variables'] as $v): ?>
                        <code class="u-mr-1"><?= e('{{' . $v . '}}') ?></code>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" action="mail-templates.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="name" value="<?= e($selected) ?>">
            <?= csrfTokenField() ?>

            <label for="body"><strong>HTML Şablon</strong></label>
            <textarea id="body" name="body" rows="22" class="u-w-100 u-font-mono"><?= e($currentContent) ?></textarea>

            <div class="u-flex-row-gap-4 u-mt-3">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <button type="button" class="btn btn-secondary" data-action="toggle-preview">Önizleme</button>
                <button type="button" class="btn btn-secondary" data-action="show-test-form">Test E-posta Gönder</button>
            </div>
        </form>

        <div id="preview-wrap" class="u-hidden u-mt-4">
            <h4>Önizleme (sample data ile)</h4>
            <iframe id="preview-frame" sandbox="allow-same-origin" class="u-w-100 u-iframe-preview"></iframe>
        </div>

        <form id="test-send-form" method="post" action="mail-templates.php" class="u-hidden u-mt-4">
            <input type="hidden" name="action" value="send-test">
            <input type="hidden" name="name" value="<?= e($selected) ?>">
            <?= csrfTokenField() ?>
            <label for="to"><strong>Test alıcı:</strong></label>
            <input type="email" id="to" name="to" required placeholder="test@example.com">
            <button type="submit" class="btn btn-primary btn-sm">Gönder</button>
        </form>
    </div>
</div>

<script nonce="<?= e($cspNonce) ?>">
(function () {
    'use strict';

    var previewWrap = document.getElementById('preview-wrap');
    var previewFrame = document.getElementById('preview-frame');
    var testForm = document.getElementById('test-send-form');
    var body = document.getElementById('body');

    var sampleData = <?= json_encode(
        $currentMeta['sample_data'] ?? [],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    ) ?>;

    function renderSample(src) {
        var out = src;
        // Raw {{{x}}} önce — aksi halde {{ kısmen tüketilir
        Object.keys(sampleData).forEach(function (k) {
            var re1 = new RegExp('\\{\\{\\{' + k + '\\}\\}\\}', 'g');
            out = out.replace(re1, String(sampleData[k]));
        });
        Object.keys(sampleData).forEach(function (k) {
            var re2 = new RegExp('\\{\\{' + k + '\\}\\}', 'g');
            // Basit HTML escape sample'daki string değerler için
            var safe = String(sampleData[k])
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            out = out.replace(re2, safe);
        });
        return out;
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-action]');
        if (!t) return;
        var action = t.getAttribute('data-action');
        if (action === 'toggle-preview') {
            previewWrap.classList.toggle('u-hidden');
            if (!previewWrap.classList.contains('u-hidden')) {
                var doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                doc.open();
                doc.write(renderSample(body.value));
                doc.close();
            }
        } else if (action === 'show-test-form') {
            testForm.classList.toggle('u-hidden');
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
