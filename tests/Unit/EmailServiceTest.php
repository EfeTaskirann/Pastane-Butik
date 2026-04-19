<?php
/**
 * EmailService Unit Tests
 *
 * Email gönderim servisinin mantığını test eder. Gerçek SMTP bağlantısı
 * kurulmaz — 'log' driver ile test edilir.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use EmailService;

class EmailServiceTest extends TestCase
{
    /**
     * @var string Geçici log dosyası
     */
    private string $tempLogPath;

    /**
     * @var string Geçici template dizini
     */
    private string $tempTemplatesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogPath = sys_get_temp_dir() . '/pastane_email_test_' . uniqid() . '.log';
        $this->tempTemplatesPath = sys_get_temp_dir() . '/pastane_email_tpl_' . uniqid();
        @mkdir($this->tempTemplatesPath, 0755, true);

        // Test template'i oluştur
        file_put_contents(
            $this->tempTemplatesPath . '/test-template.html',
            '<h1>Merhaba {{ad}}</h1><p>Tutar: {{tutar}}</p>'
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->tempLogPath);
        @unlink($this->tempTemplatesPath . '/test-template.html');
        @rmdir($this->tempTemplatesPath);

        parent::tearDown();
    }

    /**
     * Test konfigurasyon builder
     *
     * @return array
     */
    private function testConfig(array $overrides = []): array
    {
        return array_merge([
            'driver'         => 'log',
            'templates_path' => $this->tempTemplatesPath,
            'log_path'       => $this->tempLogPath,
            'from'           => ['address' => 'test@example.com', 'name' => 'Test'],
            'enabled'        => true,
        ], $overrides);
    }

    /**
     * @test
     */
    public function test_gecerli_email_ile_gonderim_basarili(): void
    {
        $service = new EmailService($this->testConfig());

        $result = $service->send(
            'alici@example.com',
            'Test Konu',
            'test-template',
            ['ad' => 'Ali', 'tutar' => '100 TL']
        );

        $this->assertTrue($result);
        $this->assertSame('', $service->getLastError());
    }

    /**
     * @test
     */
    public function test_gecersiz_email_adresi_reddedilir(): void
    {
        $service = new EmailService($this->testConfig());

        $result = $service->send(
            'gecersiz-email',
            'Test',
            'test-template',
            []
        );

        $this->assertFalse($result);
        $this->assertStringContainsString('Gecersiz', $service->getLastError());
    }

    /**
     * @test
     */
    public function test_olmayan_template_hata_doner(): void
    {
        $service = new EmailService($this->testConfig());

        $result = $service->send(
            'alici@example.com',
            'Test',
            'olmayan-template',
            []
        );

        $this->assertFalse($result);
        $this->assertStringContainsString('Template', $service->getLastError());
    }

    /**
     * @test
     */
    public function test_template_degiskenleri_dogru_replace_edilir(): void
    {
        $service = new EmailService($this->testConfig());

        $rendered = $service->renderTemplate('test-template', [
            'ad' => 'Zeynep',
            'tutar' => '250,00 ₺',
        ]);

        $this->assertStringContainsString('Merhaba Zeynep', $rendered);
        $this->assertStringContainsString('250,00', $rendered);
    }

    /**
     * @test
     */
    public function test_template_html_escape_yapar(): void
    {
        $service = new EmailService($this->testConfig());

        $rendered = $service->renderTemplate('test-template', [
            'ad' => '<script>alert("xss")</script>',
            'tutar' => '0',
        ]);

        // Script tag escape edilmiş olmalı
        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;', $rendered);
    }

    /**
     * @test
     */
    public function test_log_dosyasina_gonderim_kaydi_yazilir(): void
    {
        $service = new EmailService($this->testConfig());

        $service->send('alici@example.com', 'Test', 'test-template', ['ad' => 'Ali', 'tutar' => '50']);

        $this->assertFileExists($this->tempLogPath);
        $logContent = file_get_contents($this->tempLogPath);
        $this->assertStringContainsString('alici@example.com', $logContent);
        $this->assertStringContainsString('gonderim_basarili', $logContent);
    }

    /**
     * @test
     */
    public function test_enabled_false_ise_gerçek_gonderim_yapmaz(): void
    {
        $service = new EmailService($this->testConfig(['enabled' => false]));

        $result = $service->send('alici@example.com', 'Test', 'test-template', ['ad' => 'X', 'tutar' => '0']);

        // Sessizce basarili sayilir ama log'da 'gonderim_devre_disi' olmali
        $this->assertTrue($result);
        $logContent = file_get_contents($this->tempLogPath);
        $this->assertStringContainsString('gonderim_devre_disi', $logContent);
    }

    /**
     * @test
     */
    public function test_desteklenmeyen_driver_hata_firlatir(): void
    {
        $service = new EmailService($this->testConfig(['driver' => 'gercekdisi_driver']));

        $result = $service->send('alici@example.com', 'Test', 'test-template', ['ad' => 'X', 'tutar' => '0']);

        $this->assertFalse($result);
        $this->assertStringContainsString('driver', strtolower($service->getLastError()));
    }

    /**
     * @test
     */
    public function test_triple_brace_raw_html_icerigi_escape_etmez(): void
    {
        // {{{ad}}} raw olmali, {{ad}} escape'li
        file_put_contents(
            $this->tempTemplatesPath . '/raw-template.html',
            '<div>Raw: {{{html_content}}}</div><div>Safe: {{html_content}}</div>'
        );

        $service = new EmailService($this->testConfig());

        $rendered = $service->renderTemplate('raw-template', [
            'html_content' => '<strong>kalin</strong>',
        ]);

        // Raw versiyon: <strong> olduğu gibi kalmış olmalı
        $this->assertStringContainsString('Raw: <strong>kalin</strong>', $rendered);
        // Safe versiyon: escape edilmiş olmalı
        $this->assertStringContainsString('Safe: &lt;strong&gt;', $rendered);

        @unlink($this->tempTemplatesPath . '/raw-template.html');
    }
}
