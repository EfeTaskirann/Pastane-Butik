<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pastane\Services\IyzicoService;

/**
 * @covers \Pastane\Services\IyzicoService
 */
final class IyzicoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset env so tests are deterministic
        putenv('IYZICO_ENABLED=false');
        putenv('IYZICO_API_KEY=');
        putenv('IYZICO_SECRET=');
        putenv('IYZICO_WEBHOOK_SECRET=test-webhook-secret');
        $_ENV['IYZICO_ENABLED'] = 'false';
        $_ENV['IYZICO_API_KEY'] = '';
        $_ENV['IYZICO_SECRET'] = '';
        $_ENV['IYZICO_WEBHOOK_SECRET'] = 'test-webhook-secret';
    }

    public function test_disabled_when_no_keys(): void
    {
        $iyz = new IyzicoService();
        $this->assertFalse($iyz->isEnabled());
        $result = $iyz->initiate3DS([]);
        $this->assertSame('disabled', $result['status']);
    }

    public function test_enabled_when_keys_and_flag_set(): void
    {
        putenv('IYZICO_ENABLED=true');
        putenv('IYZICO_API_KEY=test-key');
        putenv('IYZICO_SECRET=test-secret');
        $_ENV['IYZICO_ENABLED'] = 'true';
        $_ENV['IYZICO_API_KEY'] = 'test-key';
        $_ENV['IYZICO_SECRET'] = 'test-secret';

        $iyz = new IyzicoService();
        $this->assertTrue($iyz->isEnabled());
    }

    public function test_webhook_signature_valid(): void
    {
        $iyz = new IyzicoService();
        $body = '{"paymentId":"123","status":"SUCCESS"}';
        $sig = hash_hmac('sha256', $body, 'test-webhook-secret');
        $this->assertTrue($iyz->verifyWebhookSignature($body, $sig));
    }

    public function test_webhook_signature_invalid(): void
    {
        $iyz = new IyzicoService();
        $body = '{"paymentId":"123"}';
        $bogusSig = hash_hmac('sha256', 'tampered', 'test-webhook-secret');
        $this->assertFalse($iyz->verifyWebhookSignature($body, $bogusSig));
    }

    public function test_webhook_signature_empty_inputs_rejected(): void
    {
        $iyz = new IyzicoService();
        $this->assertFalse($iyz->verifyWebhookSignature('', 'sig'));
        $this->assertFalse($iyz->verifyWebhookSignature('body', ''));
    }

    public function test_process_webhook_missing_payment_id(): void
    {
        $iyz = new IyzicoService();
        $called = false;
        $result = $iyz->processWebhook(['status' => 'SUCCESS'], function () use (&$called) {
            $called = true;
            return 'ok';
        });
        $this->assertFalse($result['processed']);
        $this->assertSame('missing_payment_id', $result['reason']);
        $this->assertFalse($called);
    }

    public function test_process_webhook_invokes_callback(): void
    {
        $iyz = new IyzicoService();
        $captured = [];
        $iyz->processWebhook(
            ['paymentId' => 'pay_123', 'status' => 'SUCCESS', 'paidPrice' => 250.00, 'conversationId' => 555],
            function (string $pid, string $status, array $data) use (&$captured) {
                $captured = compact('pid', 'status', 'data');
                return 'inserted';
            }
        );
        $this->assertSame('pay_123', $captured['pid']);
        $this->assertSame('SUCCESS', $captured['status']);
        $this->assertSame(250.00, $captured['data']['paidPrice']);
    }

    public function test_refund_disabled_returns_disabled(): void
    {
        $iyz = new IyzicoService();
        $r = $iyz->refund(['paymentTransactionId' => 'x', 'price' => '10', 'currency' => 'TRY']);
        $this->assertSame('disabled', $r['status']);
    }

    public function test_refund_validates_required_fields(): void
    {
        putenv('IYZICO_ENABLED=true');
        putenv('IYZICO_API_KEY=k');
        putenv('IYZICO_SECRET=s');
        $_ENV['IYZICO_ENABLED'] = 'true';
        $_ENV['IYZICO_API_KEY'] = 'k';
        $_ENV['IYZICO_SECRET'] = 's';

        $iyz = new IyzicoService();
        $r = $iyz->refund([]);
        $this->assertSame('error', $r['status']);
        $this->assertStringContainsString('paymentTransactionId', $r['errorMessage']);
    }
}
