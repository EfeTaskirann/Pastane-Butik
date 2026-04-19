<?php
/**
 * MasaSiparisService Unit Tests
 *
 * QR Menü sipariş servisinin iş mantığını mock repository'lerle test eder.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Services\MasaSiparisService;
use Pastane\Repositories\MasaSiparisRepository;
use Pastane\Repositories\MasaOturumRepository;
use Pastane\Repositories\UrunRepository;
use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

class MasaSiparisServiceTest extends TestCase
{
    /**
     * Helper: Mock'lu service olustur.
     *
     * @param array|null $oturum findByOturumToken dönüşü
     * @param array|null $urun UrunRepository::find dönüşü
     * @param array $siparisFindReturn Siparis findOrFail dönüşü (default: id=1, durum=beklemede)
     * @return array [service, siparisRepo, oturumRepo, urunRepo]
     */
    private function makeService(
        ?array $oturum = null,
        ?array $urun = null,
        array $siparisFindReturn = ['id' => 1, 'durum' => 'beklemede', 'oturum_id' => 1, 'masa_id' => 5, 'toplam_tutar' => 150.0]
    ): array {
        $siparisRepo = $this->createMock(MasaSiparisRepository::class);
        $oturumRepo = $this->createMock(MasaOturumRepository::class);
        $urunRepo = $this->createMock(UrunRepository::class);

        $oturumRepo->method('findByOturumToken')->willReturn($oturum);
        $urunRepo->method('find')->willReturn($urun);

        $siparisRepo->method('findOrFail')->willReturn($siparisFindReturn);
        $siparisRepo->method('find')->willReturn($siparisFindReturn);
        $siparisRepo->method('create')->willReturn(1);
        $siparisRepo->method('addKalem')->willReturn(1);
        $siparisRepo->method('updateDurum')->willReturn(true);
        $siparisRepo->method('updateOdemeDurumu')->willReturn(true);
        $siparisRepo->method('getSiparisKalemleri')->willReturn([]);
        $siparisRepo->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $service = new MasaSiparisService($siparisRepo, $oturumRepo, $urunRepo);

        return [$service, $siparisRepo, $oturumRepo, $urunRepo];
    }

    // ========================================================================
    // 1. Siparis olusturma — basarili
    // ========================================================================

    /**
     * @test
     */
    public function test_gecerli_oturum_ve_urun_ile_siparis_olusturulur(): void
    {
        [$service, $siparisRepo] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif', 'oturum_token' => 'TKN'],
            urun: ['id' => 10, 'isim' => 'Cheesecake', 'fiyat' => 75.0, 'aktif' => 1, 'cafe_menusu' => 1, 'stok_durumu' => 'var']
        );

        // create çağrısı bekleniyor
        $siparisRepo->expects($this->once())
            ->method('create')
            ->willReturn(1);

        $sonuc = $service->siparisOlustur('TKN', [
            ['urun_id' => 10, 'adet' => 2],
        ]);

        $this->assertSame(1, $sonuc['id']);
    }

    // ========================================================================
    // 2. Fiyat hesaplama — adet * birim_fiyat
    // ========================================================================

    /**
     * @test
     */
    public function test_fiyat_hesaplama_adet_ile_carpilarak_yapilir(): void
    {
        $kaydedilenToplamTutar = 0;

        $siparisRepo = $this->createMock(MasaSiparisRepository::class);
        $oturumRepo = $this->createMock(MasaOturumRepository::class);
        $urunRepo = $this->createMock(UrunRepository::class);

        $oturumRepo->method('findByOturumToken')->willReturn(['id' => 1, 'masa_id' => 5, 'durum' => 'aktif']);
        $urunRepo->method('find')->willReturn(['id' => 10, 'isim' => 'Pasta', 'fiyat' => 50.0, 'aktif' => 1, 'cafe_menusu' => 1]);

        $siparisRepo->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());
        $siparisRepo->method('create')->willReturnCallback(function ($data) use (&$kaydedilenToplamTutar) {
            $kaydedilenToplamTutar = $data['toplam_tutar'];
            return 1;
        });
        $siparisRepo->method('addKalem')->willReturn(1);
        $siparisRepo->method('findOrFail')->willReturn(['id' => 1]);
        $siparisRepo->method('getSiparisKalemleri')->willReturn([]);

        $service = new MasaSiparisService($siparisRepo, $oturumRepo, $urunRepo);

        $service->siparisOlustur('TKN', [
            ['urun_id' => 10, 'adet' => 3], // 50 * 3 = 150
        ]);

        $this->assertEqualsWithDelta(150.0, $kaydedilenToplamTutar, 0.01);
    }

    // ========================================================================
    // 3. Adet validasyonu — negatif/sifir reddedilir
    // ========================================================================

    /**
     * @test
     */
    public function test_adet_sifir_veya_negatif_ise_reddedilir(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif'],
            urun: ['id' => 10, 'isim' => 'Pasta', 'fiyat' => 50.0, 'aktif' => 1, 'cafe_menusu' => 1]
        );

        $this->expectException(ValidationException::class);
        $service->siparisOlustur('TKN', [['urun_id' => 10, 'adet' => 0]]);
    }

    /**
     * @test
     */
    public function test_adet_99_uzerinde_ise_reddedilir(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif'],
            urun: ['id' => 10, 'isim' => 'Pasta', 'fiyat' => 50.0, 'aktif' => 1, 'cafe_menusu' => 1]
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/siniri/i');

        $service->siparisOlustur('TKN', [['urun_id' => 10, 'adet' => 100]]);
    }

    // ========================================================================
    // 4. Masa oturum iliskisi — gecersiz oturum reddedilir
    // ========================================================================

    /**
     * @test
     */
    public function test_gecersiz_oturum_token_ile_siparis_reddedilir(): void
    {
        [$service] = $this->makeService(oturum: null);

        $this->expectException(HttpException::class);
        $service->siparisOlustur('GECERSIZ_TOKEN', [['urun_id' => 10, 'adet' => 1]]);
    }

    /**
     * @test
     */
    public function test_pasif_oturum_ile_siparis_verilemez(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'tamamlandi']
        );

        $this->expectException(HttpException::class);
        $service->siparisOlustur('TKN', [['urun_id' => 10, 'adet' => 1]]);
    }

    // ========================================================================
    // 5. Siparis iptal — durum kontrolu
    // ========================================================================

    /**
     * @test
     */
    public function test_beklemede_durumundaki_siparis_iptal_edilebilir(): void
    {
        [$service, $siparisRepo] = $this->makeService(
            siparisFindReturn: ['id' => 1, 'durum' => 'beklemede', 'masa_id' => 5, 'toplam_tutar' => 50.0]
        );

        $siparisRepo->expects($this->once())
            ->method('updateDurum')
            ->with(1, 'iptal')
            ->willReturn(true);

        $sonuc = $service->siparisIptal(1);

        $this->assertStringContainsString('iptal', strtolower($sonuc['mesaj']));
    }

    /**
     * @test
     */
    public function test_teslim_edilmis_siparis_iptal_edilemez(): void
    {
        [$service] = $this->makeService(
            siparisFindReturn: ['id' => 1, 'durum' => 'teslim_edildi', 'masa_id' => 5]
        );

        $this->expectException(ValidationException::class);
        $service->siparisIptal(1);
    }

    // ========================================================================
    // 6. Durum gecisi — kurallara uygun olmali
    // ========================================================================

    /**
     * @test
     */
    public function test_beklemede_onaylandi_gecisi_gecerli(): void
    {
        [$service, $siparisRepo] = $this->makeService(
            siparisFindReturn: ['id' => 1, 'durum' => 'beklemede']
        );

        $siparisRepo->expects($this->once())
            ->method('updateDurum')
            ->with(1, 'onaylandi')
            ->willReturn(true);

        $sonuc = $service->siparisDurumGuncelle(1, 'onaylandi');
        $this->assertArrayHasKey('siparis', $sonuc);
        $this->assertArrayHasKey('mesaj', $sonuc);
    }

    /**
     * @test
     */
    public function test_gecersiz_durum_gecisi_reddedilir(): void
    {
        [$service] = $this->makeService(
            siparisFindReturn: ['id' => 1, 'durum' => 'teslim_edildi']
        );

        // teslim_edildi'den baska duruma gecilemez
        $this->expectException(ValidationException::class);
        $service->siparisDurumGuncelle(1, 'beklemede');
    }

    /**
     * @test
     */
    public function test_hazir_durumundan_beklemede_ye_donulemez(): void
    {
        [$service] = $this->makeService(
            siparisFindReturn: ['id' => 1, 'durum' => 'hazir']
        );

        $this->expectException(ValidationException::class);
        $service->siparisDurumGuncelle(1, 'beklemede');
    }

    // ========================================================================
    // 7. Kalem listesi bos ise reddedilir
    // ========================================================================

    /**
     * @test
     */
    public function test_bos_kalem_listesi_ile_siparis_olusturulamaz(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif']
        );

        $this->expectException(ValidationException::class);
        $service->siparisOlustur('TKN', []);
    }

    // ========================================================================
    // 8. Pasif urun siparise eklenemez
    // ========================================================================

    /**
     * @test
     */
    public function test_aktif_olmayan_urun_siparise_eklenemez(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif'],
            urun: ['id' => 10, 'isim' => 'Pasta', 'fiyat' => 50.0, 'aktif' => 0, 'cafe_menusu' => 1]
        );

        $this->expectException(ValidationException::class);
        $service->siparisOlustur('TKN', [['urun_id' => 10, 'adet' => 1]]);
    }

    /**
     * @test
     */
    public function test_stoku_tukenmis_urun_siparise_eklenemez(): void
    {
        [$service] = $this->makeService(
            oturum: ['id' => 1, 'masa_id' => 5, 'durum' => 'aktif'],
            urun: ['id' => 10, 'isim' => 'Pasta', 'fiyat' => 50.0, 'aktif' => 1, 'cafe_menusu' => 1, 'stok_durumu' => 'tukendi']
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/stok/i');

        $service->siparisOlustur('TKN', [['urun_id' => 10, 'adet' => 1]]);
    }

    // ========================================================================
    // 9. ENUM tutarlilik — Migration ile MasaSiparisRepository eslesmeli
    // ========================================================================

    /**
     * @test
     */
    public function test_masa_siparis_durum_enum_migration_ile_eslesir(): void
    {
        // CLAUDE.md "tekrarlanan hatalar": ENUM degerleri migration ile eslesmeli
        // Migration: masa_siparisleri.durum ENUM('beklemede','onaylandi','hazirlaniyor','hazir','teslim_edildi','iptal')
        $expected = ['beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];
        $this->assertSame($expected, MasaSiparisRepository::VALID_DURUMLAR);

        // Odeme durumu ENUM
        $expectedOdeme = ['odenmedi', 'odendi', 'iade'];
        $this->assertSame($expectedOdeme, MasaSiparisRepository::VALID_ODEME_DURUMLARI);
    }

    // ========================================================================
    // 10. Durum gecis kurallari — DURUM_GECISLERI sabiti
    // ========================================================================

    /**
     * @test
     */
    public function test_durum_gecis_kurallari_tam_liste_icerir(): void
    {
        $beklenenDurumlar = ['beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];

        foreach ($beklenenDurumlar as $durum) {
            $this->assertArrayHasKey($durum, MasaSiparisService::DURUM_GECISLERI);
        }

        // Son durumlardan (teslim_edildi ve iptal) cikis olmamali
        $this->assertSame([], MasaSiparisService::DURUM_GECISLERI['teslim_edildi']);
        $this->assertSame([], MasaSiparisService::DURUM_GECISLERI['iptal']);
    }
}
