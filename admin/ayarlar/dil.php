<?php

declare(strict_types=1);

/**
 * admin/ayarlar/dil.php — Translation Editor (TR/EN side-by-side)
 *
 * `lang/tr.php` ve `lang/en.php` dosyalarini yan yana goster, inline duzenleme,
 * yeni key ekleme, eksik EN ceviri rozeti, runtime missing key listesi.
 *
 * Save sonrasi:
 *   - Atomic write (`*.tmp` -> rename)
 *   - var_export-based, sorted, comment header
 *   - Backup (`lang/<locale>.php.backup-<timestamp>`)
 *
 * Guvenlik:
 *   - requireLogin() + CSRF token (POST)
 *   - Key regex `[a-zA-Z0-9._-]+` zorunlu (path traversal/injection block)
 *   - Maks 65535 char per value
 */

require_once __DIR__ . '/../includes/header.php';

$rootBase = dirname(__DIR__, 2);
$trPath = $rootBase . '/lang/tr.php';
$enPath = $rootBase . '/lang/en.php';
$missingPath = $rootBase . '/storage/i18n-missing.json';

$flash = null;

// ----- POST handler -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        setFlash('error', 'Guvenlik dogrulamasi basarisiz (CSRF token).');
        header('Location: dil.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'save':
                $saveResult = saveTranslations($trPath, $enPath, $_POST['translations'] ?? []);
                setFlash('success', "{$saveResult} ceviri kaydedildi.");
                break;

            case 'add':
                $newKey = trim((string)($_POST['new_key'] ?? ''));
                $newTr = (string)($_POST['new_tr'] ?? '');
                $newEn = (string)($_POST['new_en'] ?? '');

                if (!preg_match('/^[a-zA-Z0-9._-]+$/', $newKey)) {
                    throw new RuntimeException('Gecersiz key formati. Sadece [a-zA-Z0-9._-] kullanilabilir.');
                }
                if (mb_strlen($newTr) > 65535 || mb_strlen($newEn) > 65535) {
                    throw new RuntimeException('Ceviri 65535 karakter sinirini astı.');
                }

                $tr = loadLangFile($trPath);
                $en = loadLangFile($enPath);
                setNestedValue($tr, $newKey, $newTr !== '' ? $newTr : $newKey);
                setNestedValue($en, $newKey, $newEn !== '' ? $newEn : ($newTr !== '' ? $newTr : $newKey));
                writeLangFile($trPath, $tr, 'tr');
                writeLangFile($enPath, $en, 'en');

                setFlash('success', "Yeni key eklendi: {$newKey}");
                break;

            case 'remove':
                $removeKey = (string)($_POST['key'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9._-]+$/', $removeKey)) {
                    throw new RuntimeException('Gecersiz key formati.');
                }
                $tr = loadLangFile($trPath);
                $en = loadLangFile($enPath);
                unsetNestedValue($tr, $removeKey);
                unsetNestedValue($en, $removeKey);
                writeLangFile($trPath, $tr, 'tr');
                writeLangFile($enPath, $en, 'en');
                setFlash('success', "Key silindi: {$removeKey}");
                break;

            case 'clear_missing':
                if (is_file($missingPath)) {
                    @unlink($missingPath);
                }
                setFlash('success', 'Runtime missing key listesi temizlendi.');
                break;

            default:
                throw new RuntimeException('Bilinmeyen islem.');
        }
    } catch (\Throwable $e) {
        setFlash('error', 'Hata: ' . $e->getMessage());
    }

    header('Location: dil.php');
    exit;
}

// ----- Lang dosyalari yukle -----
$tr = loadLangFile($trPath);
$en = loadLangFile($enPath);
$trFlat = flattenLang($tr);
$enFlat = flattenLang($en);

ksort($trFlat);
ksort($enFlat);

// All keys (union)
$allKeys = array_unique(array_merge(array_keys($trFlat), array_keys($enFlat)));
sort($allKeys);

// Group by top-level prefix
$grouped = [];
foreach ($allKeys as $key) {
    $top = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : '_root';
    $grouped[$top][] = $key;
}
ksort($grouped);

// Missing EN list
$missingInEn = array_diff(array_keys($trFlat), array_keys($enFlat));
$missingInTr = array_diff(array_keys($enFlat), array_keys($trFlat));

// Runtime missing
$runtimeMissing = [];
if (is_file($missingPath)) {
    $raw = @file_get_contents($missingPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $runtimeMissing = $decoded;
    }
}

$cspNonce = function_exists('getCspNonce') ? getCspNonce() : '';
$activeGroup = (string)($_GET['group'] ?? array_key_first($grouped) ?? '_root');
?>

<h2 class="u-mb-3">Dil / Ceviri Yonetimi</h2>
<p class="u-text-muted u-mb-4">
    TR/EN cevirileri yan yana duzenleyin. Yeni key ekleyince her iki dil dosyasi atomik olarak guncellenir.
    Eksik EN cevirisi olan key'ler <span class="badge badge-warning">EKSIK EN</span> rozeti gosterir.
</p>

<?php if ($flashMsg = getFlash()): ?>
    <div class="alert alert-<?= e($flashMsg['type']) ?>"><?= e($flashMsg['message']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid u-mb-5">
    <div class="stat-card">
        <div class="stat-info">
            <h4><?= count($allKeys) ?></h4>
            <span>Toplam Key</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h4><?= count($trFlat) ?></h4>
            <span>Turkce Ceviri</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h4><?= count($enFlat) ?></h4>
            <span>Ingilizce Ceviri</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h4><?= count($missingInEn) ?></h4>
            <span>Eksik EN</span>
        </div>
    </div>
</div>

<?php if (!empty($runtimeMissing)): ?>
<div class="card u-mb-4">
    <div class="card-header">
        <h3>Runtime Missing Keys (sayfa kullaniminda tespit edildi)</h3>
        <form method="post" action="dil.php" class="u-d-inline">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="clear_missing">
            <button type="submit" class="btn btn-sm btn-secondary">Listeyi Temizle</button>
        </form>
    </div>
    <div class="card-body">
        <?php foreach ($runtimeMissing as $loc => $keys): ?>
            <h4><?= e(strtoupper($loc)) ?> icin eksik <?= count($keys) ?> key:</h4>
            <ul>
                <?php foreach (array_slice($keys, 0, 20, true) as $k => $info): ?>
                    <li>
                        <code><?= e($k) ?></code>
                        <small class="u-text-muted">
                            (<?= (int)($info['count'] ?? 0) ?>x, son: <?= e($info['last_seen'] ?? $info['first_seen'] ?? '?') ?>)
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Yeni Key Ekle -->
<div class="card u-mb-4">
    <div class="card-header"><h3>Yeni Ceviri Ekle</h3></div>
    <div class="card-body">
        <form method="post" action="dil.php" class="form-grid">
            <?= csrfTokenField() ?>
            <input type="hidden" name="action" value="add">

            <div class="form-row u-flex-row-gap-3">
                <div class="form-group u-flex-1">
                    <label for="new_key">Key (dot.notation)</label>
                    <input type="text" id="new_key" name="new_key" required
                           placeholder="ornek: nav.contact"
                           pattern="[a-zA-Z0-9._-]+"
                           class="form-control u-font-mono">
                </div>
                <div class="form-group u-flex-1">
                    <label for="new_tr">Turkce</label>
                    <input type="text" id="new_tr" name="new_tr" required class="form-control" placeholder="Iletisim">
                </div>
                <div class="form-group u-flex-1">
                    <label for="new_en">English</label>
                    <input type="text" id="new_en" name="new_en" required class="form-control" placeholder="Contact">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Ekle</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Group Tabs -->
<div class="tabs u-mb-3">
    <?php foreach ($grouped as $g => $_keys): ?>
        <a href="?group=<?= e($g) ?>"
           class="tab-link <?= $g === $activeGroup ? 'active' : '' ?>">
            <?= e($g) ?> <small>(<?= count($_keys) ?>)</small>
        </a>
    <?php endforeach; ?>
</div>

<!-- Editor -->
<form method="post" action="dil.php" id="translationsForm">
    <?= csrfTokenField() ?>
    <input type="hidden" name="action" value="save">

    <div class="card">
        <div class="card-header">
            <h3>Grup: <code><?= e($activeGroup) ?></code></h3>
            <button type="submit" class="btn btn-primary">Tumunu Kaydet</button>
        </div>
        <div class="card-body u-p-0">
            <table class="u-w-100">
                <thead>
                    <tr>
                        <th class="u-w-25">Key</th>
                        <th>Turkce</th>
                        <th>English</th>
                        <th class="u-w-100px">Islem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($grouped[$activeGroup] ?? [] as $key):
                    $trVal = $trFlat[$key] ?? '';
                    $enVal = $enFlat[$key] ?? '';
                    $needsEn = !isset($enFlat[$key]) || str_starts_with((string)$enVal, '[TR] ');
                    $needsTr = !isset($trFlat[$key]) || str_starts_with((string)$trVal, '[TODO TR] ');
                ?>
                    <tr>
                        <td>
                            <code><?= e($key) ?></code>
                            <?php if ($needsEn): ?>
                                <br><span class="badge badge-warning">EKSIK EN</span>
                            <?php endif; ?>
                            <?php if ($needsTr): ?>
                                <br><span class="badge badge-danger">EKSIK TR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text"
                                   name="translations[<?= e($key) ?>][tr]"
                                   value="<?= e((string)$trVal) ?>"
                                   class="form-control">
                        </td>
                        <td>
                            <input type="text"
                                   name="translations[<?= e($key) ?>][en]"
                                   value="<?= e((string)$enVal) ?>"
                                   class="form-control <?= $needsEn ? 'is-warning' : '' ?>">
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-danger"
                                    data-action="remove-key"
                                    data-key="<?= e($key) ?>">Sil</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer u-text-right">
            <button type="submit" class="btn btn-primary">Tumunu Kaydet</button>
        </div>
    </div>
</form>

<!-- Hidden remove form -->
<form method="post" action="dil.php" id="removeKeyForm" class="u-hidden">
    <?= csrfTokenField() ?>
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="key" id="removeKeyValue">
</form>

<script nonce="<?= e($cspNonce) ?>">
(function() {
    'use strict';
    document.addEventListener('click', function(e) {
        var t = e.target.closest('[data-action="remove-key"]');
        if (!t) return;
        var key = t.getAttribute('data-key');
        if (!confirm('"' + key + '" key silinecek (TR + EN). Emin misiniz?')) return;
        document.getElementById('removeKeyValue').value = key;
        document.getElementById('removeKeyForm').submit();
    });
})();
</script>

<style nonce="<?= e($cspNonce) ?>">
.tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; border-bottom: 2px solid var(--border-color, #e0d6c8); padding-bottom: 0.5rem; }
.tab-link { padding: 0.5rem 1rem; text-decoration: none; color: var(--text-secondary); border-radius: 6px 6px 0 0; transition: background 0.15s; }
.tab-link:hover { background: var(--bg-soft, #faf3ee); }
.tab-link.active { background: var(--admin-primary, #7a3d55); color: #fff; font-weight: 500; }
.is-warning { border-color: #f0ad4e !important; background: #fff8e6; }
.badge-warning { background: #f0ad4e; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.badge-danger { background: #d9534f; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
.form-grid .form-row { gap: 1rem; align-items: flex-end; }
.form-grid .form-group.u-flex-1 { flex: 1; }
table th { padding: 12px 16px; text-align: left; background: var(--bg-soft); }
table td { padding: 8px 16px; border-bottom: 1px solid var(--border-color); vertical-align: top; }
table input.form-control { width: 100%; }
.card-footer { padding: 16px 20px; border-top: 1px solid var(--border-color); }
.u-w-100px { width: 100px; }
.u-w-25 { width: 25%; }
.u-flex-1 { flex: 1; }
.u-d-inline { display: inline; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php

// ===== Helpers =====

function loadLangFile(string $path): array
{
    if (!is_file($path)) return [];
    $data = include $path;
    return is_array($data) ? $data : [];
}

function flattenLang(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $newKey = $prefix === '' ? (string)$k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out = array_merge($out, flattenLang($v, $newKey));
        } else {
            $out[$newKey] = $v;
        }
    }
    return $out;
}

function setNestedValue(array &$arr, string $key, string $value): void
{
    $segments = explode('.', $key);
    $current = &$arr;
    foreach ($segments as $i => $seg) {
        if ($i === count($segments) - 1) {
            $current[$seg] = $value;
            return;
        }
        if (!isset($current[$seg]) || !is_array($current[$seg])) {
            $current[$seg] = [];
        }
        $current = &$current[$seg];
    }
}

function unsetNestedValue(array &$arr, string $key): void
{
    $segments = explode('.', $key);
    $current = &$arr;
    $parents = [];
    foreach ($segments as $i => $seg) {
        if ($i === count($segments) - 1) {
            unset($current[$seg]);
            return;
        }
        if (!isset($current[$seg]) || !is_array($current[$seg])) {
            return;
        }
        $parents[] = &$current;
        $current = &$current[$seg];
    }
}

function writeLangFile(string $path, array $data, string $locale): void
{
    // Sort recursively
    sortRecursive($data);

    // Atomic backup
    if (is_file($path)) {
        $backup = $path . '.backup-' . date('Ymd-His');
        @copy($path, $backup);
    }

    $header = "<?php\n\ndeclare(strict_types=1);\n\n";
    $header .= "/**\n * Pastane — Translations ({$locale})\n";
    $header .= " * Edited via admin/ayarlar/dil.php — auto-sorted on save.\n";
    $header .= " */\n\nreturn ";
    $body = var_export($data, true) . ";\n";

    // var_export style normalization: array () -> []
    $body = preg_replace('/array \(/', '[', $body);
    $body = preg_replace('/^(\s*)\)/m', '$1]', $body);

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $header . $body, LOCK_EX) === false) {
        throw new RuntimeException("Lang dosyasi yazilamadi: $path");
    }
    @rename($tmp, $path);
}

function sortRecursive(array &$arr): void
{
    ksort($arr);
    foreach ($arr as &$v) {
        if (is_array($v)) sortRecursive($v);
    }
}

function saveTranslations(string $trPath, string $enPath, array $translations): int
{
    $tr = loadLangFile($trPath);
    $en = loadLangFile($enPath);
    $count = 0;

    foreach ($translations as $key => $values) {
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9._-]+$/', $key)) {
            continue;
        }
        if (!is_array($values)) continue;

        $trVal = (string)($values['tr'] ?? '');
        $enVal = (string)($values['en'] ?? '');

        if (mb_strlen($trVal) > 65535 || mb_strlen($enVal) > 65535) {
            continue;
        }

        if ($trVal !== '') setNestedValue($tr, $key, $trVal);
        if ($enVal !== '') setNestedValue($en, $key, $enVal);
        $count++;
    }

    writeLangFile($trPath, $tr, 'tr');
    writeLangFile($enPath, $en, 'en');

    return $count;
}
