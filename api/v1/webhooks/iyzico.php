<?php

declare(strict_types=1);

/**
 * api/v1/webhooks/iyzico.php — Iyzico Webhook Handler (P2-18)
 *
 * Iyzico ödeme platformundan gelen async webhook event'lerini işler.
 *
 * Güvenlik:
 *   - HMAC-SHA256 signature doğrulama (webhook secret env'de tutulur)
 *   - Idempotency: aynı payment ID ile gelen duplicate request'ler 200 OK döner
 *   - Rate limit: 60/dk/IP (üst RateLimiter)
 *   - Sadece POST kabul edilir, JSON body
 *
 * Iyzico tarafında webhook URL:
 *   https://your-domain.com/api/v1/webhooks/iyzico
 */

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

use Pastane\Services\IyzicoService;

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Rate limit (RateLimiter helper varsa)
if (class_exists('RateLimiter')) {
    /** @var \RateLimiter $rl */
    $rl = new \RateLimiter();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (method_exists($rl, 'check')) {
        $allowed = $rl->check('iyzico-webhook', $ip, 60, 60);
        if ($allowed === false) {
            http_response_code(429);
            echo json_encode(['error' => 'rate_limited']);
            exit;
        }
    }
}

// Body
$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'empty_body']);
    exit;
}

// Signature
$signature = $_SERVER['HTTP_X_IYZ_SIGNATURE'] ?? '';
$iyz = new IyzicoService();

if (!$iyz->verifyWebhookSignature($body, $signature)) {
    http_response_code(401);
    if (class_exists('Logger')) {
        \Logger::warning('Iyzico webhook signature mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '?']);
    }
    echo json_encode(['error' => 'signature_mismatch']);
    exit;
}

// Decode
$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// İşle
try {
    $result = $iyz->processWebhook($payload, function (string $paymentId, string $status, array $data): string {
        // Sipariş güncelleme — odeme_islemleri tablosuna durumu yaz
        if (!class_exists('\\Database')) {
            return 'no_database';
        }
        $db = \Database::getInstance();

        $mappedStatus = match ($status) {
            'SUCCESS', 'success', 'completed' => 'basarili',
            'FAILURE', 'failed', 'declined' => 'basarisiz',
            'PENDING', 'pending' => 'baslatildi',
            'REFUNDED', 'refunded' => 'iade',
            default => 'baslatildi',
        };

        // Existing record var mı?
        $existing = $db->query(
            'SELECT id FROM odeme_islemleri WHERE external_id = :eid LIMIT 1',
            ['eid' => $paymentId]
        );

        if (!empty($existing)) {
            $db->query(
                'UPDATE odeme_islemleri SET durum = :d, updated_at = NOW() WHERE external_id = :eid',
                ['d' => $mappedStatus, 'eid' => $paymentId]
            );
            return 'updated';
        }

        // Yeni kayıt — sipariş ile bağlantı için conversationId beklenir
        $orderId = (int)($data['conversationId'] ?? 0);
        if ($orderId === 0) {
            return 'no_conversation_id';
        }

        $db->query(
            'INSERT INTO odeme_islemleri (siparis_id, external_id, durum, tutar, created_at) VALUES (:s, :eid, :d, :t, NOW())',
            [
                's' => $orderId,
                'eid' => $paymentId,
                'd' => $mappedStatus,
                't' => (float)($data['paidPrice'] ?? $data['price'] ?? 0),
            ]
        );
        return 'inserted';
    });

    http_response_code(200);
    echo json_encode($result);
} catch (\Throwable $e) {
    http_response_code(500);
    if (class_exists('Logger')) {
        \Logger::error('Iyzico webhook handler error', ['error' => $e->getMessage()]);
    }
    echo json_encode(['error' => 'internal']);
}
