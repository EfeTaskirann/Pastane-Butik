<?php
/**
 * SmsService Unit Tests
 *
 * SMS gönderim servisinin mantığını test eder. Gerçek NetGSM/Twilio
 * bağlantısı kurulmaz — `httpClient` callable override ile mock yanıt
 * üretilir. `log` driver ayrıca dosya tabanlı log testini sağlar.
 *
 * Kapsam:
 *   - Telefon normalize (E.164 uyumu)
 *   - Template render (placeholder replacement + path-traversal koruması)
 *   - Enabled=false davranışı (sessiz başarı)
 *   - NetGSM / Twilio driver success + failure
 *   - Geçersiz template / geçersiz driver hata yolları
 *
 * @package Pastane\Tests\Unit
 * @since 2.1.0-sprint3
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use SmsService;

class SmsServiceTest extends TestCase
{
    /**
     * @var string
     */
    private string $tempLogPath;

    /**
     * @var string
     */
    private string $tempTemplatesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogPath = sys_get_temp_dir() . '/pastane_sms_test_' . uniqid() . '.log';
        $this->tempTemplatesPath = sys_get_temp_dir() . '/pastane_sms_tpl_' . uniqid();
        @mkdir($this->tempTemplatesPath, 0755, true);

        file_put_contents(
            $this->tempTemplatesPath . '/siparis-onay.txt',
            'Merhaba {{musteri_adi}}, siparis #{{siparis_no}} alindi.'
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->tempLogPath);
        foreach (glob($this->tempTemplatesPath . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tempTemplatesPath);
        parent::tearDown();
    }

    /**
     * @return array Geçerli varsayılan test konfigürasyonu
     */
    private function testConfig(array $overrides = []): array
    {
        return array_merge([
            'driver'         => 'log',
            'enabled'        => true,
            'country_code'   => '90',
            'sender'         => 'TESTSMS',
            'api_key'        => 'test-user',
            'api_secret'     => 'test-pass',
            'templates_path' => $this->tempTemplatesPath,
            'log_path'       => $this->tempLogPath,
            'timeout'        => 5,
            'netgsm' => ['endpoint' => 'https://api.netgsm.test/sms/send/get'],
            'twilio' => ['endpoint_template' => 'https://api.twilio.test/2010-04-01/Accounts/%s/Messages.json'],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Telefon normalize
    // ------------------------------------------------------------------

    /**
     * @test
     */
    public function test_yerel_format_05xx_normalize_edilir(): void
    {
        $sms = new SmsService($this->testConfig());
        $this->assertSame('905551234567', $sms->normalizePhone('05551234567'));
    }

    /**
     * @test
     */
    public function test_ulke_kodlu_format_dokunulmaz(): void
    {
        $sms = new SmsService($this->testConfig());
        $this->assertSame('905551234567', $sms->normalizePhone('+90 555 123 45 67'));
        $this->assertSame('905551234567', $sms->normalizePhone('905551234567'));
    }

    /**
     * @test
     */
    public function test_eksik_hane_null_doner(): void
    {
        $sms = new SmsService($this->testConfig());
        $this->assertNull($sms->normalizePhone('12345'));
        $this->assertNull($sms->normalizePhone(''));
        $this->assertNull($sms->normalizePhone('abc'));
    }

    // ------------------------------------------------------------------
    // Template render
    // ------------------------------------------------------------------

    /**
     * @test
     */
    public function test_template_placeholder_replace_eder(): void
    {
        $sms = new SmsService($this->testConfig());
        $rendered = $sms->renderTemplate('siparis-onay', [
            'musteri_adi' => 'Ali',
            'siparis_no'  => 42,
        ]);
        $this->assertStringContainsString('Merhaba Ali', $rendered);
        $this->assertStringContainsString('#42', $rendered);
    }

    /**
     * @test
     */
    public function test_template_path_traversal_yakalanir(): void
    {
        $sms = new SmsService($this->testConfig());
        $this->expectException(\InvalidArgumentException::class);
        $sms->renderTemplate('../../etc/passwd', []);
    }

    /**
     * @test
     */
    public function test_olmayan_template_runtime_exception_firlatir(): void
    {
        $sms = new SmsService($this->testConfig());
        $this->expectException(\RuntimeException::class);
        $sms->renderTemplate('yok-boyle-bir-template', []);
    }

    // ------------------------------------------------------------------
    // send() - log driver
    // ------------------------------------------------------------------

    /**
     * @test
     */
    public function test_log_driver_basarili_gonderim_log_yazar(): void
    {
        $sms = new SmsService($this->testConfig());
        $ok = $sms->send('05551234567', 'Test mesaji');
        $this->assertTrue($ok);

        $this->assertFileExists($this->tempLogPath);
        $content = file_get_contents($this->tempLogPath);
        $this->assertStringContainsString('log_driver_gonderim', $content);
        $this->assertStringContainsString('905551234567', $content);
    }

    /**
     * @test
     */
    public function test_enabled_false_gonderim_yapmaz_ama_basarili_doner(): void
    {
        $sms = new SmsService($this->testConfig(['enabled' => false]));
        $ok = $sms->send('05551234567', 'Test');
        $this->assertTrue($ok);

        $content = file_get_contents($this->tempLogPath);
        $this->assertStringContainsString('gonderim_devre_disi', $content);
    }

    /**
     * @test
     */
    public function test_bos_mesaj_reddedilir(): void
    {
        $sms = new SmsService($this->testConfig());
        $ok = $sms->send('05551234567', '   ');
        $this->assertFalse($ok);
        $this->assertStringContainsString('boş', mb_strtolower($sms->getLastError(), 'UTF-8'));
    }

    /**
     * @test
     */
    public function test_gecersiz_telefon_reddedilir(): void
    {
        $sms = new SmsService($this->testConfig());
        $ok = $sms->send('123', 'Test');
        $this->assertFalse($ok);
        $this->assertStringContainsString('telefon', strtolower($sms->getLastError()));
    }

    /**
     * @test
     */
    public function test_desteklenmeyen_driver_hata_firlatir(): void
    {
        $sms = new SmsService($this->testConfig(['driver' => 'gercekdisi']));
        $ok = $sms->send('05551234567', 'Test');
        $this->assertFalse($ok);
        $this->assertStringContainsString('driver', strtolower($sms->getLastError()));
    }

    // ------------------------------------------------------------------
    // NetGSM driver (mock httpClient)
    // ------------------------------------------------------------------

    /**
     * @test
     */
    public function test_netgsm_basarili_yanit_success_doner(): void
    {
        $capturedUrl = null;
        $httpClient = function (string $method, string $url, array $opts) use (&$capturedUrl): array {
            $capturedUrl = $url;
            return ['status' => 200, 'body' => '00 12345'];
        };

        $sms = new SmsService($this->testConfig(['driver' => 'netgsm']), $httpClient);
        $ok = $sms->send('05551234567', 'Test');
        $this->assertTrue($ok, 'NetGSM "00 12345" yaniti basari sayilmali');
        $this->assertIsString($capturedUrl);
        $this->assertStringContainsString('gsmno=905551234567', (string) $capturedUrl);
        $this->assertStringContainsString('msgheader=TESTSMS', (string) $capturedUrl);
    }

    /**
     * @test
     */
    public function test_netgsm_hatali_body_failure_doner(): void
    {
        $httpClient = static fn (string $m, string $u, array $o): array => ['status' => 200, 'body' => '30'];
        $sms = new SmsService($this->testConfig(['driver' => 'netgsm']), $httpClient);
        $this->assertFalse($sms->send('05551234567', 'Test'));
    }

    /**
     * @test
     */
    public function test_netgsm_eksik_konfig_hata_verir(): void
    {
        $sms = new SmsService($this->testConfig([
            'driver'     => 'netgsm',
            'api_key'    => '',
            'api_secret' => '',
        ]));
        $this->assertFalse($sms->send('05551234567', 'Test'));
        $this->assertStringContainsString('netgsm', strtolower($sms->getLastError()));
    }

    // ------------------------------------------------------------------
    // sendTemplate
    // ------------------------------------------------------------------

    /**
     * @test
     */
    public function test_sendTemplate_render_edip_gonderir(): void
    {
        $sms = new SmsService($this->testConfig());
        $ok = $sms->sendTemplate('05551234567', 'siparis-onay', [
            'musteri_adi' => 'Zeynep',
            'siparis_no'  => 7,
        ]);
        $this->assertTrue($ok);

        $content = file_get_contents($this->tempLogPath);
        $this->assertStringContainsString('Merhaba Zeynep', $content);
        $this->assertStringContainsString('#7', $content);
    }

    /**
     * @test
     */
    public function test_sendTemplate_gecersiz_template_false_doner(): void
    {
        $sms = new SmsService($this->testConfig());
        $ok = $sms->sendTemplate('05551234567', 'yok_olan_tpl', []);
        $this->assertFalse($ok);
        $this->assertStringContainsString('template', strtolower($sms->getLastError()));
    }
}
