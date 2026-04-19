<?php
/**
 * SiparisService Unit Tests
 *
 * Klasik sipariş (site/WhatsApp kanalı) servisinin iş mantığını mock repository'lerle test eder.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Services\SiparisService;
use Pastane\Services\MusteriService;
use Pastane\Repositories\SiparisRepository;
use Pastane\Exceptions\ValidationException;

class SiparisServiceTest extends TestCase
{
    /**
     * Helper: mock'lu SiparisService
     *
     * @param array|null $siparisFind find/findOrFail return
     * @return array [service, siparisRepo, musteriService]
     */
    private function makeService(?array $siparisFind = null): array
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('getPuanAyarlari')->willReturn([
            'pasta' => 15, 'cupcake' => 8, 'cheesecake' => 12, 'kurabiye' => 6, 'ozel' => 20,
        ]);
        $siparisRepo->method('getKategoriFiyatlari')->willReturn([
            'pasta' => 450, 'cupcake' => 45, 'cheesecake' => 380, 'kurabiye' => 180, 'ozel' => 0,
        ]);

        $siparisRepo->method('create')->willReturn(1);
        $siparisRepo->method('find')->willReturn($siparisFind ?? [
            'id' => 1,
            'tarih' => '2026-04-20',
            'kategori' => 'pasta',
            'tamamlandi' => 0,
            'toplam_tutar' => 450.0,
            'telefon' => '05551234567',
            'musteri_adi' => 'Test Musteri',
        ]);
        $siparisRepo->method('findOrFail')->willReturn($siparisFind ?? [
            'id' => 1,
            'tarih' => '2026-04-20',
            'kategori' => 'pasta',
            'tamamlandi' => 0,
            'toplam_tutar' => 450.0,
            'telefon' => '05551234567',
            'musteri_adi' => 'Test Musteri',
        ]);
        $siparisRepo->method('updateStatus')->willReturn(true);
        $siparisRepo->method('markCustomerRecorded')->willReturn(true);

        $service = new SiparisService($siparisRepo, $musteriService);

        return [$service, $siparisRepo, $musteriService];
    }

    // ========================================================================
    // 1. Yeni siparis — gecerli veri ile olusturulur
    // ========================================================================

    /**
     * @test
     */
    public function test_gecerli_veri_ile_siparis_olusturulur(): void
    {
        [$service, $siparisRepo] = $this->makeService();

        $siparisRepo->expects($this->once())
            ->method('create')
            ->willReturn(1);

        $sonuc = $service->create([
            'tarih'    => date('Y-m-d', strtotime('+7 days')),
            'kategori' => 'pasta',
            'adet'     => 1,
            'birim_fiyat' => 450.0,
        ]);

        $this->assertSame(1, $sonuc['id']);
    }

    // ========================================================================
    // 2. Validasyon — tarih ve kategori zorunlu
    // ========================================================================

    /**
     * @test
     */
    public function test_tarih_alani_zorunlu(): void
    {
        [$service] = $this->makeService();

        $this->expectException(ValidationException::class);
        $service->create(['kategori' => 'pasta']);
    }

    /**
     * @test
     */
    public function test_kategori_alani_zorunlu(): void
    {
        [$service] = $this->makeService();

        $this->expectException(ValidationException::class);
        $service->create(['tarih' => '2026-04-20']);
    }

    /**
     * @test
     */
    public function test_gecersiz_kategori_reddedilir(): void
    {
        [$service] = $this->makeService();

        $this->expectException(ValidationException::class);
        $service->create([
            'tarih' => '2026-04-20',
            'kategori' => 'bilinmeyen_kategori',
        ]);
    }

    /**
     * @test
     */
    public function test_yanlis_tarih_formati_reddedilir(): void
    {
        [$service] = $this->makeService();

        $this->expectException(ValidationException::class);
        $service->create([
            'tarih' => '20-04-2026', // yanlis format
            'kategori' => 'pasta',
        ]);
    }

    // ========================================================================
    // 3. Fiyat hesaplama — adet x birim_fiyat
    // ========================================================================

    /**
     * @test
     */
    public function test_toplam_tutar_adet_ile_carpilarak_hesaplanir(): void
    {
        $kaydedilenData = [];

        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('getPuanAyarlari')->willReturn(['pasta' => 15, 'ozel' => 20]);
        $siparisRepo->method('getKategoriFiyatlari')->willReturn(['pasta' => 450]);
        $siparisRepo->method('create')->willReturnCallback(function ($data) use (&$kaydedilenData) {
            $kaydedilenData = $data;
            return 1;
        });
        $siparisRepo->method('find')->willReturn(['id' => 1, 'toplam_tutar' => 900.0]);

        $service = new SiparisService($siparisRepo, $musteriService);
        $service->create([
            'tarih'       => '2026-04-20',
            'kategori'    => 'pasta',
            'adet'        => 2,
            'birim_fiyat' => 450.0,
        ]);

        $this->assertSame(900.0, $kaydedilenData['toplam_tutar']);
        $this->assertSame(2, $kaydedilenData['adet']);
    }

    // ========================================================================
    // 4. Tamamla (durum guncelle) — teslim_edildi → sadakat programi
    // ========================================================================

    /**
     * @test
     */
    public function test_teslim_edildi_durumu_sadakat_programini_tetikler(): void
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'tarih' => '2026-04-20',
            'kategori' => 'pasta',
            'tamamlandi' => 0, // once teslim edilmemis
            'toplam_tutar' => 450.0,
            'telefon' => '05551234567',
            'musteri_adi' => 'Test',
            'adres' => null,
        ]);
        $siparisRepo->method('find')->willReturn(['id' => 1, 'tamamlandi' => 1]);
        $siparisRepo->method('updateStatus')->willReturn(true);
        $siparisRepo->method('markCustomerRecorded')->willReturn(true);

        // MusteriService'in cagrilmasini bekleriz
        $musteriService->expects($this->once())
            ->method('recordDeliveredOrder')
            ->with('05551234567', 450.0, 'Test', null)
            ->willReturn([
                'hediye_kazanildi' => false,
                'musteri' => ['siparis_sayisi' => 3],
            ]);

        $service = new SiparisService($siparisRepo, $musteriService);
        $result = $service->updateStatus(1, 'teslim_edildi');

        $this->assertFalse($result['hediye_kazanildi']);
    }

    /**
     * @test
     */
    public function test_teslim_edildi_durumu_5_sipariste_hediye_verir(): void
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'tamamlandi' => 0,
            'toplam_tutar' => 450.0,
            'telefon' => '05551234567',
            'musteri_adi' => 'Sadik Musteri',
        ]);
        $siparisRepo->method('find')->willReturn(['id' => 1, 'tamamlandi' => 1]);
        $siparisRepo->method('updateStatus')->willReturn(true);
        $siparisRepo->method('markCustomerRecorded')->willReturn(true);

        $musteriService->method('recordDeliveredOrder')->willReturn([
            'hediye_kazanildi' => true,
            'musteri' => ['siparis_sayisi' => 5],
        ]);

        $service = new SiparisService($siparisRepo, $musteriService);
        $result = $service->updateStatus(1, 'teslim_edildi');

        $this->assertTrue($result['hediye_kazanildi']);
        $this->assertStringContainsString('HEDİYE', $result['mesaj']);
    }

    // ========================================================================
    // 5. Geri alma — teslim_edildi'den baska duruma gecis
    // ========================================================================

    /**
     * @test
     */
    public function test_teslim_edildi_durumundan_geri_alinirsa_hediye_iptal_edilir(): void
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'tamamlandi' => 1, // teslim edilmis
            'toplam_tutar' => 450.0,
            'telefon' => '05551234567',
        ]);
        $siparisRepo->method('find')->willReturn(['id' => 1, 'tamamlandi' => 0]);
        $siparisRepo->method('updateStatus')->willReturn(true);

        $musteriService->expects($this->once())
            ->method('reverseDeliveredOrder')
            ->willReturn([
                'hediye_geri_alindi' => true,
            ]);

        $service = new SiparisService($siparisRepo, $musteriService);
        $result = $service->updateStatus(1, 'beklemede');

        $this->assertTrue($result['hediye_geri_alindi']);
    }

    // ========================================================================
    // 6. Silme vs arsivleme
    // ========================================================================

    /**
     * @test
     */
    public function test_tamamlanmis_siparis_arsivlenir_silinmez(): void
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'tamamlandi' => 1, // tamamlanmis
        ]);
        $siparisRepo->method('archive')->willReturn(true);
        $siparisRepo->expects($this->never())->method('delete');
        $siparisRepo->expects($this->once())->method('archive')->with(1);

        $service = new SiparisService($siparisRepo, $musteriService);
        $sonuc = $service->deleteOrArchive(1);

        $this->assertTrue($sonuc['arsivlendi']);
        $this->assertFalse($sonuc['silindi']);
    }

    /**
     * @test
     */
    public function test_beklemede_siparis_silinir_arsivlenmez(): void
    {
        $siparisRepo = $this->createMock(SiparisRepository::class);
        $musteriService = $this->createMock(MusteriService::class);

        $siparisRepo->method('findOrFail')->willReturn([
            'id' => 1,
            'tamamlandi' => 0,
        ]);
        $siparisRepo->method('delete')->willReturn(true);
        $siparisRepo->expects($this->never())->method('archive');
        $siparisRepo->expects($this->once())->method('delete')->with(1);

        $service = new SiparisService($siparisRepo, $musteriService);
        $sonuc = $service->deleteOrArchive(1);

        $this->assertTrue($sonuc['silindi']);
        $this->assertFalse($sonuc['arsivlendi']);
    }

    // ========================================================================
    // 7. Listeleme filtreleri — date range
    // ========================================================================

    /**
     * @test
     */
    public function test_tarih_aralığında_siparisler_dondurulur(): void
    {
        [$service, $siparisRepo] = $this->makeService();

        $siparisRepo->method('getByDateRange')->willReturn([
            ['id' => 1, 'tarih' => '2026-04-15'],
            ['id' => 2, 'tarih' => '2026-04-18'],
        ]);

        $sonuc = $service->getByDateRange('2026-04-01', '2026-04-30');
        $this->assertCount(2, $sonuc);
    }

    /**
     * @test
     */
    public function test_musteri_telefonuna_gore_siparis_gecmisi_dondurulur(): void
    {
        [$service, $siparisRepo] = $this->makeService();

        $siparisRepo->method('getByPhone')->willReturn([
            ['id' => 1, 'telefon' => '05551234567'],
            ['id' => 2, 'telefon' => '05551234567'],
        ]);

        $sonuc = $service->getCustomerOrderHistory('05551234567', 5);
        $this->assertCount(2, $sonuc);
    }

    // ========================================================================
    // 8. Rapor aggregasyonu — yoğunluk kategorisi
    // ========================================================================

    /**
     * @test
     */
    public function test_workload_kategorileri_dogru_hesaplanir(): void
    {
        [$service] = $this->makeService();

        $bos = $service->getWorkloadCategory(5);
        $this->assertSame('bos', $bos['durum']);

        $uygun = $service->getWorkloadCategory(30);
        $this->assertSame('uygun', $uygun['durum']);

        $yogun = $service->getWorkloadCategory(70);
        $this->assertSame('yogun', $yogun['durum']);

        $dolu = $service->getWorkloadCategory(120);
        $this->assertSame('dolu', $dolu['durum']);
    }

    /**
     * @test
     */
    public function test_workload_sinir_degerler_dogru_kategorilenir(): void
    {
        [$service] = $this->makeService();

        // Tam esik degerlerde hangi kategoriye duser
        $this->assertSame('bos', $service->getWorkloadCategory(SiparisService::WORKLOAD_BOS)['durum']); // 10
        $this->assertSame('uygun', $service->getWorkloadCategory(SiparisService::WORKLOAD_UYGUN)['durum']); // 40
        $this->assertSame('yogun', $service->getWorkloadCategory(SiparisService::WORKLOAD_YOGUN)['durum']); // 80
    }

    // ========================================================================
    // 9. ENUM — STATUSES ve CATEGORIES sabitleri dogru
    // ========================================================================

    /**
     * @test
     */
    public function test_statuses_sabiti_turkce_etiketler_icerir(): void
    {
        $expected = ['beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal'];

        foreach ($expected as $durum) {
            $this->assertArrayHasKey($durum, SiparisService::STATUSES);
        }

        $this->assertSame('Teslim Edildi', SiparisService::STATUSES['teslim_edildi']);
    }

    /**
     * @test
     */
    public function test_categories_sabiti_gecerli_kategorileri_icerir(): void
    {
        $expected = ['pasta', 'cupcake', 'cheesecake', 'kurabiye', 'ozel'];
        $this->assertSame($expected, SiparisService::CATEGORIES);
    }
}
