<?php
/**
 * API Dokümantasyon Gateway (Swagger UI)
 *
 * Bu dosya Swagger UI arayüzünü servis eder. Hassas endpoint listesini ifşa
 * etmemek için SADECE local/staging ortamlarında erişilebilir; production'da
 * 404 döner.
 *
 * Erişim:
 *   GET /pastane/api/docs.php           → Swagger UI
 *   GET /pastane/api/docs.php?spec=1    → openapi.yaml (raw)
 *
 * Ortam kontrolü: config('app.env') === 'production' ise erişim reddedilir.
 * Ek güvenlik: staging'de opsiyonel `API_DOCS_TOKEN` env ile ?token= kontrolü.
 *
 * @package Pastane\API
 * @since 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// ---------------------------------------------------------------------------
// 1) Ortam gating
// ---------------------------------------------------------------------------
$env = strtolower((string) config('app.env', 'production'));
$allowedEnvs = ['local', 'development', 'dev', 'staging', 'test', 'testing'];

if (!in_array($env, $allowedEnvs, true)) {
    // Production'da varlığını bile ifşa etme — 404 döndür
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo "Not Found";
    exit;
}

// ---------------------------------------------------------------------------
// 2) Opsiyonel token gate (staging için)
// ---------------------------------------------------------------------------
$requiredToken = getenv('API_DOCS_TOKEN') ?: (defined('API_DOCS_TOKEN') ? API_DOCS_TOKEN : null);
if (!empty($requiredToken)) {
    $providedToken = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_DOCS_TOKEN'] ?? '');
    if (!hash_equals((string) $requiredToken, $providedToken)) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        echo "Unauthorized: API docs token required.";
        exit;
    }
}

// ---------------------------------------------------------------------------
// 3) Raw OpenAPI YAML servis (?spec=1)
// ---------------------------------------------------------------------------
if (isset($_GET['spec'])) {
    $specPath = __DIR__ . '/../docs/api/openapi.yaml';
    if (!is_file($specPath)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'OpenAPI spec bulunamadı.']);
        exit;
    }

    header('Content-Type: application/yaml; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    readfile($specPath);
    exit;
}

// ---------------------------------------------------------------------------
// 4) Swagger UI HTML'i Servis Et
// ---------------------------------------------------------------------------
// CSP: Swagger UI CDN'ine izin ver. Production'a geçerken hiç servis edilmez.
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
    "img-src 'self' data: https://cdn.jsdelivr.net; " .
    "font-src 'self' data:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "object-src 'none'"
);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Spec path — docs.php üzerinden (token gate dahil) çek
$basePath = rtrim((string) config('app.base_path', '/pastane'), '/');
$specUrl = $basePath . '/api/docs.php?spec=1';
if (!empty($requiredToken)) {
    $specUrl .= '&token=' . urlencode((string) ($_GET['token'] ?? ''));
}

$htmlPath = __DIR__ . '/../public/api-docs/index.html';
if (!is_file($htmlPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "API docs UI bulunamadı.";
    exit;
}

// index.html'i oku ve {{SPEC_URL}} / {{ENV}} placeholder'larını değiştir
$html = (string) file_get_contents($htmlPath);

// URL parametresi ile enjekte et (HTML'e dokunmadan — Swagger UI JS'i okuyacak)
header('Content-Type: text/html; charset=utf-8');

// İstemciye env ve spec query param'larını yansıt
// Basit bir redirect değil; doğrudan HTML servis edip Swagger UI'ın
// window.location.search'ten okumasına izin veriyoruz.
if (!isset($_GET['env']) || !isset($_GET['spec_url'])) {
    $queryParams = array_merge($_GET, ['env' => $env]);
    $queryParams['spec_url'] = $specUrl;
    $newQuery = http_build_query($queryParams);
    $selfUrl = strtok((string) $_SERVER['REQUEST_URI'], '?') . '?' . $newQuery;
    // Sadece bir kere yönlendir; tekrar eden yönlendirmeyi önlemek için kontrol
    if (empty($_GET['env']) || empty($_GET['spec_url'])) {
        header('Location: ' . $selfUrl);
        exit;
    }
}

// Küçük bir şablonlama — spec URL'ini HTML'deki varsayılan yerine enjekte et
$html = str_replace(
    "'/pastane/docs/api/openapi.yaml'",
    "'" . addslashes($specUrl) . "'",
    $html
);

echo $html;
