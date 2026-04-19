<?php
/**
 * Admin Activity Log (Security Audit Viewer)
 *
 * SecurityAudit tarafindan yazilan `security_events` tablosunu goruntuler.
 * Filtreler: tarih araligi, kullanici, event type, IP arama.
 * Pagination: 25 kayit/sayfa.
 *
 * RBAC entegrasyonu henuz yok — sadece requireLogin() kullaniyoruz.
 * Tech Lead RBAC'yi eklediğinde bu sayfaya 'logs.view' permission'i baglanmalidir.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// ---- Filtre parametrelerini topla ----
$filters = [
    'from'       => trim((string)($_GET['from'] ?? '')),
    'to'         => trim((string)($_GET['to'] ?? '')),
    'user_id'    => trim((string)($_GET['user_id'] ?? '')),
    'event_type' => trim((string)($_GET['event_type'] ?? '')),
    'ip'         => trim((string)($_GET['ip'] ?? '')),
];

$page = max(1, (int)($_GET['sayfa'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// ---- WHERE clause'u guvenli bicimde uret ----
$conditions = [];
$params = [];

// Tarih formati dogrulama (YYYY-MM-DD)
$dateRegex = '/^\d{4}-\d{2}-\d{2}$/';

if ($filters['from'] !== '' && preg_match($dateRegex, $filters['from'])) {
    $conditions[] = 'se.created_at >= :from_date';
    $params['from_date'] = $filters['from'] . ' 00:00:00';
}

if ($filters['to'] !== '' && preg_match($dateRegex, $filters['to'])) {
    $conditions[] = 'se.created_at <= :to_date';
    $params['to_date'] = $filters['to'] . ' 23:59:59';
}

if ($filters['user_id'] !== '' && ctype_digit($filters['user_id'])) {
    $conditions[] = 'se.user_id = :user_id';
    $params['user_id'] = (int)$filters['user_id'];
}

if ($filters['event_type'] !== '') {
    $conditions[] = 'se.event_type = :event_type';
    $params['event_type'] = $filters['event_type'];
}

if ($filters['ip'] !== '') {
    // IP arama: tam eslesme veya wildcard (LIKE)
    $conditions[] = 'se.ip_address LIKE :ip';
    $params['ip'] = '%' . $filters['ip'] . '%';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// ---- Kayitlari cek ----
$pdo = db()->getPdo();

// `security_events` tablosu mevcut mu — migration calistirilmadiysa graceful fallback
$tableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'security_events'");
    $tableExists = $check && $check->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

$totalCount = 0;
$totalPages = 1;
$events = [];
$tableMissingError = null;

if ($tableExists) {
    try {
        // Toplam
        $countSql = "SELECT COUNT(*) FROM security_events se {$whereSql}";
        $stmt = $pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        $totalCount = (int)$stmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalCount / $perPage));

        // Sayfa sinirini asan ?sayfa= degerini duzelt
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
        }

        // Liste (admin_kullanicilar ile LEFT JOIN — username goster)
        $listSql = "
            SELECT
                se.id,
                se.event_type,
                se.user_id,
                se.ip_address,
                se.user_agent,
                se.details,
                se.created_at,
                ak.kullanici_adi AS kullanici_adi
            FROM security_events se
            LEFT JOIN admin_kullanicilar ak ON ak.id = se.user_id
            {$whereSql}
            ORDER BY se.created_at DESC, se.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($listSql);
        foreach ($params as $k => $v) {
            if (is_int($v)) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $k, $v);
            }
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tableMissingError = $e->getMessage();
        $events = [];
        $totalCount = 0;
        $totalPages = 1;
    }
} else {
    $tableMissingError = 'security_events tablosu henuz olusturulmamis. Migration calistirin: database/migrations/2024_01_01_000002_create_security_tables.php';
}

// ---- Filtre secenekleri (dropdown'lar icin) ----
// Kullanicilar
try {
    $users = $pdo->query("SELECT id, kullanici_adi FROM admin_kullanicilar ORDER BY kullanici_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Event type'lari — SecurityAudit class'indaki sabitlerden al (kullanici-friendly etiket ile)
$eventTypeLabels = [
    SecurityAudit::LOGIN_SUCCESS          => 'Giris basarili',
    SecurityAudit::LOGIN_FAILED           => 'Giris basarisiz',
    SecurityAudit::LOGOUT                 => 'Cikis',
    SecurityAudit::PASSWORD_CHANGE        => 'Sifre degistirildi',
    SecurityAudit::PASSWORD_RESET_REQUEST => 'Sifre sifirlama istegi',
    SecurityAudit::PASSWORD_RESET_COMPLETE => 'Sifre sifirlama tamamlandi',
    SecurityAudit::TWO_FACTOR_ENABLED     => '2FA acildi',
    SecurityAudit::TWO_FACTOR_DISABLED    => '2FA kapatildi',
    SecurityAudit::TWO_FACTOR_FAILED      => '2FA basarisiz',
    SecurityAudit::ACCOUNT_LOCKED         => 'Hesap kilitlendi',
    SecurityAudit::ACCOUNT_UNLOCKED       => 'Hesap acildi',
    SecurityAudit::API_TOKEN_CREATED      => 'API token olusturuldu',
    SecurityAudit::API_TOKEN_REVOKED      => 'API token iptal',
    SecurityAudit::PERMISSION_DENIED      => 'Yetki reddedildi',
    SecurityAudit::RATE_LIMIT_EXCEEDED    => 'Rate limit asildi',
    SecurityAudit::SUSPICIOUS_ACTIVITY    => 'Supheli aktivite',
    SecurityAudit::DATA_EXPORT            => 'Veri disa aktarildi',
    SecurityAudit::DATA_DELETE            => 'Veri silindi',
    SecurityAudit::ADMIN_ACTION           => 'Admin islemi',
];

// Event badge renk sinifi
$eventBadgeClass = function (string $eventType): string {
    $dangerTypes = [
        SecurityAudit::LOGIN_FAILED,
        SecurityAudit::ACCOUNT_LOCKED,
        SecurityAudit::SUSPICIOUS_ACTIVITY,
        SecurityAudit::RATE_LIMIT_EXCEEDED,
        SecurityAudit::PERMISSION_DENIED,
        SecurityAudit::TWO_FACTOR_FAILED,
        SecurityAudit::DATA_DELETE,
    ];
    $successTypes = [
        SecurityAudit::LOGIN_SUCCESS,
        SecurityAudit::TWO_FACTOR_ENABLED,
        SecurityAudit::ACCOUNT_UNLOCKED,
        SecurityAudit::PASSWORD_RESET_COMPLETE,
    ];
    $warningTypes = [
        SecurityAudit::PASSWORD_CHANGE,
        SecurityAudit::TWO_FACTOR_DISABLED,
        SecurityAudit::API_TOKEN_REVOKED,
        SecurityAudit::ADMIN_ACTION,
        SecurityAudit::PASSWORD_RESET_REQUEST,
    ];

    if (in_array($eventType, $dangerTypes, true)) {
        return 'badge-danger';
    }
    if (in_array($eventType, $successTypes, true)) {
        return 'badge-success';
    }
    if (in_array($eventType, $warningTypes, true)) {
        return 'badge-warning';
    }
    return 'badge-neutral';
};

// Query string builder — pagination linkleri icin aktif filtreleri korur
$buildQueryString = function (array $overrides = []) use ($filters, $page) {
    $q = array_filter([
        'from'       => $filters['from'],
        'to'         => $filters['to'],
        'user_id'    => $filters['user_id'],
        'event_type' => $filters['event_type'],
        'ip'         => $filters['ip'],
        'sayfa'      => $page,
    ], static function ($v) { return $v !== '' && $v !== null; });
    $q = array_merge($q, $overrides);
    return http_build_query($q);
};

// User-agent kisalt
$shortenUserAgent = function (?string $ua): string {
    if (!$ua) return '—';
    $ua = trim($ua);
    if (mb_strlen($ua) <= 40) return $ua;
    return mb_substr($ua, 0, 38) . '...';
};

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
        Aktivite Loglari
    </h2>
    <div class="page-header-actions">
        <span class="badge badge-neutral" aria-label="Filtrelenmis sonuc sayisi">
            <?= number_format($totalCount, 0, ',', '.') ?> kayit
        </span>
    </div>
</div>

<?php if ($tableMissingError): ?>
<div class="alert alert-warning u-mb-4" role="alert">
    <strong>Dikkat:</strong> <?= e($tableMissingError) ?>
</div>
<?php endif; ?>

<!-- Filter Card -->
<div class="card u-mb-4">
    <div class="card-header">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Filtreler
        </h3>
    </div>
    <div class="card-body">
        <form method="GET" action="activity-log.php" id="filterForm">
            <div class="u-grid-auto-180">
                <div>
                    <label for="filter-from" class="u-form-label-sm">Baslangic</label>
                    <input type="date" id="filter-from" name="from" class="form-control" value="<?= e($filters['from']) ?>" aria-label="Baslangic tarihi">
                </div>
                <div>
                    <label for="filter-to" class="u-form-label-sm">Bitis</label>
                    <input type="date" id="filter-to" name="to" class="form-control" value="<?= e($filters['to']) ?>" aria-label="Bitis tarihi">
                </div>
                <div>
                    <label for="filter-user" class="u-form-label-sm">Kullanici</label>
                    <select id="filter-user" name="user_id" class="form-control" aria-label="Kullanici filtresi">
                        <option value="">Tum kullanicilar</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ($filters['user_id'] !== '' && (int)$filters['user_id'] === (int)$u['id']) ? 'selected' : '' ?>>
                                <?= e($u['kullanici_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter-event" class="u-form-label-sm">Event tipi</label>
                    <select id="filter-event" name="event_type" class="form-control" aria-label="Event tipi filtresi">
                        <option value="">Tum tipler</option>
                        <?php foreach ($eventTypeLabels as $type => $label): ?>
                            <option value="<?= e($type) ?>" <?= $filters['event_type'] === $type ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter-ip" class="u-form-label-sm">IP adresi</label>
                    <input type="text" id="filter-ip" name="ip" class="form-control" value="<?= e($filters['ip']) ?>" placeholder="192.168.1.1" aria-label="IP adresi araması" inputmode="numeric">
                </div>
                <div class="u-flex-end-gap">
                    <button type="submit" class="btn btn-primary u-flex-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        Filtrele
                    </button>
                    <a href="activity-log.php" class="btn btn-secondary" aria-label="Filtreleri temizle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Events Card -->
<div class="card">
    <div class="card-header">
        <h3>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Kayitlar (Sayfa <?= $page ?>/<?= $totalPages ?>)
        </h3>
    </div>
    <div class="card-body u-p-0">
        <?php if (empty($events)): ?>
            <div class="empty-state u-p-8">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3>Kayit bulunamadi</h3>
                <p>Secili filtre kriterlerine uyan guvenlik olayi yok.</p>
            </div>
        <?php else: ?>
            <!-- Desktop tablo -->
            <div class="table-container activity-log-table">
                <table>
                    <thead>
                        <tr>
                            <th>Tarih / Saat</th>
                            <th>Kullanici</th>
                            <th>Event</th>
                            <th>IP</th>
                            <th>User-Agent</th>
                            <th class="u-nowrap-center-80">Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $etype = (string)$event['event_type'];
                            $label = $eventTypeLabels[$etype] ?? $etype;
                            $badgeCls = $eventBadgeClass($etype);
                            // `details` kolonu JSON string — parse edip data-attribute ile aktar
                            $detailsJson = $event['details'] ?? null;
                            $detailsEncoded = $detailsJson ?: '{}';
                            // Detay modal verisi icin paket (user-agent + username'i de icerir)
                            $modalPayload = [
                                'id'         => (int)$event['id'],
                                'created_at' => $event['created_at'],
                                'event_type' => $etype,
                                'event_label'=> $label,
                                'user_id'    => $event['user_id'],
                                'username'   => $event['kullanici_adi'],
                                'ip'         => $event['ip_address'],
                                'user_agent' => $event['user_agent'],
                                'details'    => $detailsJson ? json_decode($detailsJson, true) : null,
                            ];
                            $modalPayloadJson = json_encode(
                                $modalPayload,
                                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                            );
                            ?>
                            <tr>
                                <td>
                                    <div class="cell-primary"><?= e(date('d.m.Y', strtotime($event['created_at']))) ?></div>
                                    <div class="cell-muted"><?= e(date('H:i:s', strtotime($event['created_at']))) ?></div>
                                </td>
                                <td>
                                    <?php if ($event['kullanici_adi']): ?>
                                        <span class="cell-primary"><?= e($event['kullanici_adi']) ?></span>
                                        <div class="cell-muted">#<?= (int)$event['user_id'] ?></div>
                                    <?php elseif ($event['user_id']): ?>
                                        <span class="cell-muted">Silinmis #<?= (int)$event['user_id'] ?></span>
                                    <?php else: ?>
                                        <span class="cell-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= e($badgeCls) ?>" title="<?= e($etype) ?>">
                                        <?= e($label) ?>
                                    </span>
                                </td>
                                <td>
                                    <code class="u-text-xs"><?= e($event['ip_address'] ?? '—') ?></code>
                                </td>
                                <td>
                                    <span class="cell-muted u-text-xs" title="<?= e($event['user_agent'] ?? '') ?>">
                                        <?= e($shortenUserAgent($event['user_agent'])) ?>
                                    </span>
                                </td>
                                <td class="u-text-center">
                                    <button type="button"
                                            class="btn btn-sm btn-ghost btn-icon"
                                            data-action="open-event-detail"
                                            data-payload="<?= e($modalPayloadJson) ?>"
                                            aria-label="Kayit #<?= (int)$event['id'] ?> detaylarini goster"
                                            data-tooltip="Detay">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="12" y1="16" x2="12" y2="12"/>
                                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile kart gorunumu -->
            <div class="activity-log-cards">
                <?php foreach ($events as $event): ?>
                    <?php
                    $etype = (string)$event['event_type'];
                    $label = $eventTypeLabels[$etype] ?? $etype;
                    $badgeCls = $eventBadgeClass($etype);
                    $detailsJson = $event['details'] ?? null;
                    $modalPayload = [
                        'id'         => (int)$event['id'],
                        'created_at' => $event['created_at'],
                        'event_type' => $etype,
                        'event_label'=> $label,
                        'user_id'    => $event['user_id'],
                        'username'   => $event['kullanici_adi'],
                        'ip'         => $event['ip_address'],
                        'user_agent' => $event['user_agent'],
                        'details'    => $detailsJson ? json_decode($detailsJson, true) : null,
                    ];
                    $modalPayloadJson = json_encode(
                        $modalPayload,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                    ?>
                    <div class="activity-log-card">
                        <div class="activity-log-card-top">
                            <span class="badge <?= e($badgeCls) ?>"><?= e($label) ?></span>
                            <span class="cell-muted"><?= e(date('d.m.Y H:i', strtotime($event['created_at']))) ?></span>
                        </div>
                        <dl class="activity-log-card-body">
                            <div>
                                <dt>Kullanici</dt>
                                <dd>
                                    <?php if ($event['kullanici_adi']): ?>
                                        <?= e($event['kullanici_adi']) ?> (#<?= (int)$event['user_id'] ?>)
                                    <?php elseif ($event['user_id']): ?>
                                        Silinmis #<?= (int)$event['user_id'] ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>IP</dt>
                                <dd><code><?= e($event['ip_address'] ?? '—') ?></code></dd>
                            </div>
                            <div>
                                <dt>User-Agent</dt>
                                <dd><?= e($shortenUserAgent($event['user_agent'])) ?></dd>
                            </div>
                        </dl>
                        <div class="activity-log-card-footer">
                            <button type="button"
                                    class="btn btn-sm btn-secondary"
                                    data-action="open-event-detail"
                                    data-payload="<?= e($modalPayloadJson) ?>"
                                    aria-label="Kayit #<?= (int)$event['id'] ?> detaylarini goster">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                Detay
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer u-table-footer-bar">
        <div class="cell-muted u-text-sm">
            Toplam <strong><?= number_format($totalCount, 0, ',', '.') ?></strong> kayit,
            <strong><?= $page ?></strong>/<strong><?= $totalPages ?></strong> sayfa gosteriliyor.
        </div>
        <nav class="pagination" aria-label="Sayfalama">
            <?php
            // Sayfa baglantilari (akilli cikti — coz sayfa varsa ilk/son + civar)
            $range = 2;
            $links = [];
            if ($page > 1) {
                $links[] = ['label' => '‹', 'page' => $page - 1, 'aria' => 'Onceki sayfa'];
            }
            $start = max(1, $page - $range);
            $end = min($totalPages, $page + $range);
            if ($start > 1) {
                $links[] = ['label' => '1', 'page' => 1];
                if ($start > 2) {
                    $links[] = ['label' => '…', 'page' => null];
                }
            }
            for ($i = $start; $i <= $end; $i++) {
                $links[] = ['label' => (string)$i, 'page' => $i, 'active' => ($i === $page)];
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) {
                    $links[] = ['label' => '…', 'page' => null];
                }
                $links[] = ['label' => (string)$totalPages, 'page' => $totalPages];
            }
            if ($page < $totalPages) {
                $links[] = ['label' => '›', 'page' => $page + 1, 'aria' => 'Sonraki sayfa'];
            }
            ?>
            <?php foreach ($links as $link): ?>
                <?php if ($link['page'] === null): ?>
                    <span class="pagination-item disabled"><?= e($link['label']) ?></span>
                <?php elseif (!empty($link['active'])): ?>
                    <span class="pagination-item active" aria-current="page"><?= e($link['label']) ?></span>
                <?php else: ?>
                    <a class="pagination-item"
                       href="?<?= e($buildQueryString(['sayfa' => $link['page']])) ?>"
                       <?= isset($link['aria']) ? 'aria-label="' . e($link['aria']) . '"' : '' ?>>
                        <?= e($link['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="eventDetailModal" data-modal role="dialog" aria-modal="true" aria-labelledby="eventDetailTitle" aria-hidden="true">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="eventDetailTitle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                Kayit Detayi
            </h3>
            <button type="button" class="modal-close" data-action="close-event-detail" aria-label="Kapat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <dl class="event-detail-summary">
                <div><dt>Kayit ID</dt><dd id="evDetId">—</dd></div>
                <div><dt>Tarih</dt><dd id="evDetDate">—</dd></div>
                <div><dt>Event</dt><dd id="evDetType">—</dd></div>
                <div><dt>Kullanici</dt><dd id="evDetUser">—</dd></div>
                <div><dt>IP adresi</dt><dd id="evDetIp">—</dd></div>
                <div class="event-detail-wide"><dt>User-Agent</dt><dd id="evDetUa">—</dd></div>
            </dl>
            <div class="event-detail-raw">
                <label for="evDetJson" class="u-form-hint">Ham JSON (details)</label>
                <pre id="evDetJson" tabindex="0">—</pre>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-action="close-event-detail">Kapat</button>
        </div>
    </div>
</div>

<style nonce="<?= e(getCspNonce()) ?>">
/* Pagination */
.pagination {
    display: inline-flex;
    gap: 0.25rem;
    align-items: center;
}
.pagination-item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 0.75rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--admin-border);
    background: var(--admin-card);
    color: var(--admin-text);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    text-decoration: none;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.pagination-item:hover:not(.active):not(.disabled) {
    background: var(--admin-primary-50);
    border-color: var(--admin-primary-200);
}
.pagination-item.active {
    background: var(--admin-primary);
    color: #fff;
    border-color: var(--admin-primary);
}
.pagination-item.disabled {
    color: var(--admin-text-light);
    cursor: default;
    border-style: dashed;
}

/* Event detail modal */
.event-detail-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem 1.5rem;
    margin: 0 0 1.25rem;
}
.event-detail-summary > div {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}
.event-detail-summary dt {
    font-size: var(--text-xs);
    color: var(--admin-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.event-detail-summary dd {
    font-size: var(--text-sm);
    color: var(--admin-text);
    margin: 0;
    word-break: break-word;
}
.event-detail-summary .event-detail-wide {
    grid-column: 1 / -1;
}
.event-detail-raw pre {
    background: #0F172A;
    color: #E2E8F0;
    padding: 0.875rem 1rem;
    border-radius: var(--radius-md);
    overflow: auto;
    max-height: 360px;
    font-family: var(--font-mono);
    font-size: var(--text-xs);
    line-height: 1.55;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Mobile kart gorunumu */
.activity-log-cards {
    display: none;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
}
.activity-log-card {
    background: var(--admin-card);
    border: 1px solid var(--admin-border);
    border-radius: var(--radius-lg);
    padding: 0.875rem 1rem;
    box-shadow: var(--shadow-xs);
}
.activity-log-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}
.activity-log-card-body {
    display: grid;
    grid-template-columns: 90px 1fr;
    gap: 0.375rem 0.75rem;
    margin: 0 0 0.75rem;
    font-size: var(--text-sm);
}
.activity-log-card-body > div {
    display: contents;
}
.activity-log-card-body dt {
    color: var(--admin-text-secondary);
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    align-self: center;
}
.activity-log-card-body dd {
    margin: 0;
    color: var(--admin-text);
    word-break: break-word;
}
.activity-log-card-footer {
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .activity-log-table { display: none; }
    .activity-log-cards { display: flex; }
    .event-detail-summary { grid-template-columns: 1fr; }
}
</style>

<script nonce="<?= e(getCspNonce()) ?>">
(function () {
    'use strict';

    var modal       = document.getElementById('eventDetailModal');
    var elId        = document.getElementById('evDetId');
    var elDate      = document.getElementById('evDetDate');
    var elType      = document.getElementById('evDetType');
    var elUser      = document.getElementById('evDetUser');
    var elIp        = document.getElementById('evDetIp');
    var elUa        = document.getElementById('evDetUa');
    var elJson      = document.getElementById('evDetJson');

    if (!modal) return;

    function formatDateTr(iso) {
        if (!iso) return '—';
        try {
            var d = new Date(iso.replace(' ', 'T'));
            if (isNaN(d.getTime())) return String(iso);
            var pad = function (n) { return n < 10 ? '0' + n : String(n); };
            return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() +
                   ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        } catch (e) {
            return String(iso);
        }
    }

    function openDetail(payload) {
        if (!payload) return;
        elId.textContent   = '#' + (payload.id || '—');
        elDate.textContent = formatDateTr(payload.created_at);
        elType.textContent = (payload.event_label || payload.event_type || '—') +
                             (payload.event_type && payload.event_label ? ' (' + payload.event_type + ')' : '');

        if (payload.username) {
            elUser.textContent = payload.username + ' (#' + payload.user_id + ')';
        } else if (payload.user_id) {
            elUser.textContent = 'Silinmis #' + payload.user_id;
        } else {
            elUser.textContent = '—';
        }

        elIp.textContent = payload.ip || '—';
        elUa.textContent = payload.user_agent || '—';

        if (payload.details && typeof payload.details === 'object') {
            try {
                elJson.textContent = JSON.stringify(payload.details, null, 2);
            } catch (e) {
                elJson.textContent = '—';
            }
        } else if (payload.details) {
            elJson.textContent = String(payload.details);
        } else {
            elJson.textContent = '(bos)';
        }

        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-action="open-event-detail"]');
        if (opener) {
            var raw = opener.getAttribute('data-payload');
            if (!raw) return;
            try {
                var payload = JSON.parse(raw);
                openDetail(payload);
            } catch (err) {
                if (window.Toast) { Toast.error('Kayit detayi ayristirilamadi'); }
            }
            return;
        }

        var closer = e.target.closest('[data-action="close-event-detail"]');
        if (closer) {
            closeDetail();
        }
    });

    // Overlay click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeDetail();
    });

    // ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeDetail();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
