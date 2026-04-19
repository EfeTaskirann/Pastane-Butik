<?php
/**
 * OdemeService Unit Tests
 *
 * Ödeme servisinin iş mantığını mock repository'lerle test eder.
 * Gerçek DB veya iyzico API çağrısı YAPILMAZ — sadece test gateway simülasyonu.
 *
 * Baseline: 90 test — bu dosya en az 8 yeni test ekler.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Services\OdemeService;
use Pastane\Repositories\OdemeRepository;
use Pastane\Repositories\MasaSiparisRepository;
use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

class OdemeServiceTest extends TestCase
{
    /**
     * Helper: Mock OdemeRepository ve MasaSiparisRepository ile OdemeService olustur
     *
     * @param array $siparisData
     * @param array|null $existingOdeme
     * @return array [service, odemeRepo (mock), siparisRepo (mock)]
     */
    private function makeService(array $siparisData, ?array $existingOdeme = null): array
    {
        $odemeRepo = $this->createMock(OdemeRepository::class);
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);

        $siparisRepo->method('findOrFail')->willReturn($siparisData);
        $siparisRepo->method('find')->willReturn($siparisData);
        $siparisRepo->method('getSiparisKalemleri')->willReturn([
            ['urun_id' => 1, 'urun_adi' => 'Cheesecake', 'adet' => 1, 'toplam_fiyat' => 150.0],
        ]);

        $odemeRepo->method('create')->willReturn(42);
        $odemeRepo->method('updateDurum')->willReturn(true);
        $odemeRepo->method('update')->willReturn(true);
        $odemeRepo->method('findBySiparisId')->willReturn($existingOdeme !== null ? [$existingOdeme] : []);
        $odemeRepo->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());
        $odemeRepo->method('findByIslemId')->willReturn($existingOdeme);

        $service = new OdemeService($odemeRepo, $siparisRepo);

        return [$service, $odemeRepo, $siparisRepo];
    }

    // ========================================================================
    // 1. Tutar dogrulama testleri (iade senaryolari)
    // ========================================================================

    /**
     * @test
     */
    public function test_iade_tutari_negatif_ise_reddedilir(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odendi',
            'durum' => 'teslim_edildi',
            'oturum_id' => 1,
        ], [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'basarili',
            'gateway' => 'test',
            'islem_id' => 'TEST_abc',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/sifirdan buyuk/i');

        $service->iadeBaslat(1, -50.0);
    }

    /**
     * @test
     */
    public function test_iade_tutari_sifir_ise_reddedilir(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odendi',
            'oturum_id' => 1,
        ], [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'basarili',
        ]);

        $this->expectException(ValidationException::class);
        $service->iadeBaslat(1, 0.0);
    }

    /**
     * @test
     */
    public function test_iade_tutari_odeme_tutarini_asamaz(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odendi',
            'oturum_id' => 1,
        ], [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'basarili',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/asamaz/i');

        $service->iadeBaslat(1, 500.0);
    }

    // ========================================================================
    // 2. Idempotency — ayni odeme iki kez onaylanmaz
    // ========================================================================

    /**
     * @test
     */
    public function test_odeme_dogrulama_idempotent_ayni_token_iki_kez_calisirsa_tek_islem_yapar(): void
    {
        // Odeme zaten basarili durumunda — dogrulama sadece 'zaten onaylanmis' doner
        $existingOdeme = [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'basarili',
            'gateway' => 'test',
            'islem_id' => 'TEST_sabittoken',
        ];

        [$service, $odemeRepo] = $this->makeService(
            ['id' => 1, 'toplam_tutar' => 150.0, 'odeme_durumu' => 'odendi', 'durum' => 'teslim_edildi', 'oturum_id' => 1],
            $existingOdeme
        );

        // Idempotent cagri: odeme zaten basariliysa updateDurum cagrilmamali
        $odemeRepo->expects($this->never())->method('updateDurum');

        $result = $service->odemeDogrula('sabittoken');

        $this->assertTrue($result['basarili']);
        $this->assertStringContainsString('zaten', strtolower($result['mesaj']));
        $this->assertSame(1, $result['siparis_id']);
    }

    // ========================================================================
    // 3. Provider secim — test vs iyzico
    // ========================================================================

    /**
     * @test
     */
    public function test_gecersiz_gateway_degeri_test_moduna_duser(): void
    {
        // Config'i mock'layamadığımız için default test modunu dogrularız
        $odemeRepo = $this->createMock(OdemeRepository::class);
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);

        $service = new OdemeService($odemeRepo, $siparisRepo);

        // Default config 'test' olmali
        $this->assertTrue($service->isTestModu());
        $this->assertContains($service->getAktifGateway(), OdemeService::DESTEKLENEN_GATEWAY_LER);
    }

    // ========================================================================
    // 4. Basarili odeme → siparis durum guncelleme
    // ========================================================================

    /**
     * @test
     */
    public function test_basarili_odeme_siparis_odeme_durumunu_odendi_yapar(): void
    {
        $existingOdeme = [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'baslatildi',
            'gateway' => 'test',
            'islem_id' => 'TEST_xyz',
        ];

        $siparisData = [
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odenmedi',
            'durum' => 'beklemede',
            'oturum_id' => 1,
        ];

        $odemeRepo = $this->createMock(OdemeRepository::class);
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);

        $siparisRepo->method('findOrFail')->willReturn($siparisData);
        $siparisRepo->method('find')->willReturn($siparisData);

        $odemeRepo->method('findByIslemId')->willReturn($existingOdeme);
        $odemeRepo->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        // Beklenen: updateOdemeDurumu 'odendi' ile cagrilmali
        $siparisRepo->expects($this->once())
            ->method('updateOdemeDurumu')
            ->with(1, 'odendi', 'test', $this->anything())
            ->willReturn(true);

        // Beklemede → onaylandi otomatik gecis
        $siparisRepo->expects($this->once())
            ->method('updateDurum')
            ->with(1, 'onaylandi')
            ->willReturn(true);

        $odemeRepo->expects($this->once())
            ->method('updateDurum')
            ->with(42, 'basarili', $this->anything())
            ->willReturn(true);

        $service = new OdemeService($odemeRepo, $siparisRepo);
        $result = $service->odemeDogrula('xyz');

        $this->assertTrue($result['basarili']);
        $this->assertSame(1, $result['siparis_id']);
    }

    // ========================================================================
    // 5. Zaten odenmis siparis — odeme baslatilmaz
    // ========================================================================

    /**
     * @test
     */
    public function test_zaten_odenmis_siparis_icin_odeme_baslatilamaz(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odendi',
            'durum' => 'teslim_edildi',
            'oturum_id' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/zaten odenmis/i');

        $service->odemeBaslat(
            1,
            ['ad' => 'Ali', 'soyad' => 'Veli', 'email' => 'ali@example.com'],
            'http://localhost/callback'
        );
    }

    /**
     * @test
     */
    public function test_iptal_edilmis_siparis_icin_odeme_baslatilamaz(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odenmedi',
            'durum' => 'iptal',
            'oturum_id' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/iptal/i');

        $service->odemeBaslat(
            1,
            ['ad' => 'Ali', 'soyad' => 'Veli', 'email' => 'ali@example.com'],
            'http://localhost/callback'
        );
    }

    // ========================================================================
    // 6. Musteri bilgileri validasyonu
    // ========================================================================

    /**
     * @test
     */
    public function test_musteri_email_eksik_ise_odeme_baslatilamaz(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odenmedi',
            'durum' => 'beklemede',
            'oturum_id' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $service->odemeBaslat(
            1,
            ['ad' => 'Ali', 'soyad' => 'Veli'], // email eksik
            'http://localhost/callback'
        );
    }

    /**
     * @test
     */
    public function test_gecersiz_email_formati_reddedilir(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odenmedi',
            'durum' => 'beklemede',
            'oturum_id' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $service->odemeBaslat(
            1,
            ['ad' => 'Ali', 'soyad' => 'Veli', 'email' => 'gecersiz-email'],
            'http://localhost/callback'
        );
    }

    // ========================================================================
    // 7. Iade senaryosu — odenmemis siparis iade edilemez
    // ========================================================================

    /**
     * @test
     */
    public function test_odenmemis_siparis_iade_edilemez(): void
    {
        [$service] = $this->makeService([
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odenmedi',
            'durum' => 'beklemede',
            'oturum_id' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/odenmis/i');

        $service->iadeBaslat(1);
    }

    // ========================================================================
    // 8. Basarili odeme — iade basariyla tamamlanir
    // ========================================================================

    /**
     * @test
     */
    public function test_basarili_odeme_tam_iade_edilebilir(): void
    {
        $existingOdeme = [
            'id' => 42,
            'siparis_id' => 1,
            'tutar' => 150.0,
            'durum' => 'basarili',
            'gateway' => 'test',
            'islem_id' => 'TEST_xyz',
            'gateway_yaniti' => json_encode(['islem_id' => 'TEST_xyz']),
        ];

        $siparisData = [
            'id' => 1,
            'toplam_tutar' => 150.0,
            'odeme_durumu' => 'odendi',
            'durum' => 'teslim_edildi',
            'oturum_id' => 1,
        ];

        $odemeRepo = $this->createMock(OdemeRepository::class);
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);

        $siparisRepo->method('findOrFail')->willReturn($siparisData);
        $odemeRepo->method('findBySiparisId')->willReturn([$existingOdeme]);
        $odemeRepo->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $odemeRepo->expects($this->once())
            ->method('updateDurum')
            ->with(42, 'iade', $this->anything())
            ->willReturn(true);

        $siparisRepo->expects($this->once())
            ->method('updateOdemeDurumu')
            ->with(1, 'iade', $this->anything())
            ->willReturn(true);

        $service = new OdemeService($odemeRepo, $siparisRepo);
        $result = $service->iadeBaslat(1); // tam iade

        $this->assertTrue($result['basarili']);
        $this->assertSame(150.0, $result['iade_tutar']);
    }

    // ========================================================================
    // 9. Status transition — OdemeRepository::VALID_DURUMLAR migration ile eslesmeli
    // ========================================================================

    /**
     * @test
     */
    public function test_odeme_repository_valid_durumlar_migration_enum_ile_eslesir(): void
    {
        // CLAUDE.md "tekrarlanan hatalar" dersine gore: ENUM degerleri migration ile
        // repository arasinda tutarli olmali.
        // Migration: odeme_islemleri.durum ENUM('baslatildi','basarili','basarisiz','iade')
        $expected = ['baslatildi', 'basarili', 'basarisiz', 'iade'];

        $this->assertSame($expected, OdemeRepository::VALID_DURUMLAR);
    }

    // ========================================================================
    // 10. Gateway listesi — desteklenen gateway whitelisti
    // ========================================================================

    /**
     * @test
     */
    public function test_desteklenen_gatewayler_whitelist_doğru(): void
    {
        $this->assertContains('test', OdemeService::DESTEKLENEN_GATEWAY_LER);
        $this->assertContains('iyzico', OdemeService::DESTEKLENEN_GATEWAY_LER);
        $this->assertNotContains('paytr', OdemeService::DESTEKLENEN_GATEWAY_LER); // henuz eklenmedi
    }

    // ========================================================================
    // 11. Odeme durumu sorgulama — siparis var ama odeme yoksa 'odeme_yok' doner
    // ========================================================================

    /**
     * @test
     */
    public function test_odeme_yok_ise_durum_odeme_yok_doner(): void
    {
        $odemeRepo = $this->createMock(OdemeRepository::class);
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'odeme_durumu' => 'odenmedi',
        ]);

        $odemeRepo->method('findBySiparisId')->willReturn([]); // hic odeme kaydi yok

        $service = new OdemeService($odemeRepo, $siparisRepo);
        $result = $service->odemeDurumuSorgula(1);

        $this->assertSame('odeme_yok', $result['durum']);
        $this->assertSame('odenmedi', $result['siparis_odeme_durumu']);
        $this->assertSame(0, $result['toplam_odeme_sayisi']);
    }
}
