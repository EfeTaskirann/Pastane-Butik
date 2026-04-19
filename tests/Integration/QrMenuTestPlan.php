<?php
/**
 * QR Menu Sistemi Entegrasyon Test Plani
 *
 * MasaService, MasaSiparisService, OdemeService, MasaOturumService
 * ve MasaSiparisValidator icin kapsamli test senaryolari.
 *
 * Bu testler su anda `markTestIncomplete` ile isaretlenmistir;
 * gercek veritabani baglantisi saglandi ktan sonra stub/mock
 * katmani kaldirilarak tam entegrasyon testine donusturulecektir.
 *
 * @package Pastane\Tests\Integration
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Pastane\Tests\Integration;

use Pastane\Tests\TestCase;
use Pastane\Services\MasaService;
use Pastane\Services\MasaSiparisService;
use Pastane\Services\MasaOturumService;
use Pastane\Services\OdemeService;
use Pastane\Validators\MasaSiparisValidator;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

class QrMenuTestPlan extends TestCase
{
    // =========================================================================
    // MasaService Testleri
    // =========================================================================

    /**
     * Basarili masa ekleme islemini dogrular.
     *
     * Beklenen davranis:
     * - Gecerli masa_no, kapasite ve konum verildiginde masa olusturulur
     * - Donen dizide masa_no, kapasite, konum, qr_token ve durum='bos' alanlar i bulunur
     * - QR token 64 karakter (32 byte hex) uzunlugundadir
     *
     * @test
     */
    public function testMasaEkleBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository mock/stub entegrasyonu yapilacak.');

        // Arrange
        $masaNo = 99;
        $kapasite = 4;
        $konum = 'Bahce';

        // Act
        $service = new MasaService();
        $sonuc = $service->masaEkle($masaNo, $kapasite, $konum);

        // Assert
        $this->assertIsArray($sonuc);
        $this->assertEquals($masaNo, $sonuc['masa_no']);
        $this->assertEquals($kapasite, $sonuc['kapasite']);
        $this->assertEquals($konum, $sonuc['konum']);
        $this->assertEquals('bos', $sonuc['durum']);
        $this->assertArrayHasKey('qr_token', $sonuc);
        $this->assertEquals(64, strlen($sonuc['qr_token']), 'QR token 64 karakter (32 byte hex) olmali');
    }

    /**
     * Ayni masa numarasiyla ikinci masa ekleme girisimini dogrular.
     *
     * Beklenen davranis:
     * - Zaten mevcut bir masa_no ile masaEkle cagrildiginda ValidationException firlatilir
     * - Hata mesaji 'zaten kullaniliyor' ifadesini icerir
     *
     * @test
     */
    public function testMasaEkleDuplicateMasaNo(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository mock/stub entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaService();
        $service->masaEkle(1, 4, 'Ic Mekan');

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('zaten kullaniliyor');
        $service->masaEkle(1, 2, 'Dis Mekan');
    }

    /**
     * Basarili masa aktif etme islemini dogrular.
     *
     * Beklenen davranis:
     * - Bos durumdaki masanin durumu 'aktif' olarak guncellenir
     * - Yeni oturum olusturulur ve oturum_token uretilir
     * - Donen sonuc ['masa' => ..., 'oturum' => ...] yapisindadir
     * - Oturum durumu 'aktif' olmalidir
     *
     * @test
     */
    public function testMasaAktifEtBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository ve MasaOturumRepository entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaService();
        $masa = $service->masaEkle(10, 6, 'Teras');
        $masaId = (int) $masa['id'];

        // Act
        $sonuc = $service->masaAktifEt($masaId, 3);

        // Assert
        $this->assertArrayHasKey('masa', $sonuc);
        $this->assertArrayHasKey('oturum', $sonuc);
        $this->assertEquals('aktif', $sonuc['masa']['durum']);
        $this->assertEquals('aktif', $sonuc['oturum']['durum']);
        $this->assertArrayHasKey('oturum_token', $sonuc['oturum']);
        $this->assertEquals(3, $sonuc['oturum']['musteri_sayisi']);
    }

    /**
     * Zaten aktif olan masanin tekrar aktif edilme girisimini dogrular.
     *
     * Beklenen davranis:
     * - Aktif oturumu olan masada masaAktifEt cagrildiginda HttpException firlatilir
     * - Hata mesaji 'zaten aktif bir oturum' ifadesini icerir
     *
     * @test
     */
    public function testMasaAktifEtZatenAktif(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository ve MasaOturumRepository entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaService();
        $masa = $service->masaEkle(11, 4);
        $masaId = (int) $masa['id'];
        $service->masaAktifEt($masaId, 2);

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('zaten aktif bir oturum');
        $service->masaAktifEt($masaId, 1);
    }

    /**
     * Basarili masa kapatma islemini dogrular.
     *
     * Beklenen davranis:
     * - Tum siparisler teslim edilmis/iptal edilmis masada masaKapat basarili olur
     * - Masanin durumu 'bos' olarak guncellenir
     * - Oturum durumu 'tamamlandi' olur
     * - Donen sonuc mesaj icerir
     *
     * @test
     */
    public function testMasaKapatBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — tum siparis/oturum lifecycle entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaService();
        $masa = $service->masaEkle(12, 4);
        $masaId = (int) $masa['id'];
        $service->masaAktifEt($masaId, 1);
        // ... siparisler olustur ve teslim et ...

        // Act
        $sonuc = $service->masaKapat($masaId);

        // Assert
        $this->assertArrayHasKey('masa', $sonuc);
        $this->assertArrayHasKey('mesaj', $sonuc);
        $this->assertEquals('bos', $sonuc['masa']['durum']);
        $this->assertStringContainsString('basariyla kapatildi', $sonuc['mesaj']);
    }

    /**
     * Aktif siparisi olan masanin kapatilma girisimini dogrular.
     *
     * Beklenen davranis:
     * - Teslim edilmemis siparis varken masaKapat HttpException firlatir
     * - Hata mesaji 'siparisler teslim edilmeden' ifadesini icerir
     *
     * @test
     */
    public function testMasaKapatAktifSiparisVar(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — siparis lifecycle entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaService();
        $masa = $service->masaEkle(13, 4);
        $masaId = (int) $masa['id'];
        $service->masaAktifEt($masaId, 1);
        // ... beklemede durumunda siparis olustur ...

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('siparisler teslim edilmeden');
        $service->masaKapat($masaId);
    }

    /**
     * Kapasite validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Kapasite < 1 ise ValidationException firlatilir
     * - Kapasite > 50 ise ValidationException firlatilir
     * - Kapasite 1-50 arasinda ise basarili olur
     *
     * @test
     */
    public function testKapasiteValidasyonu(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository mock/stub entegrasyonu yapilacak.');

        $service = new MasaService();

        // Kapasite 0 — gecersiz
        try {
            $service->masaEkle(20, 0);
            $this->fail('Kapasite 0 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Kapasite 1 ile 50 arasinda', $e->getMessage());
        }

        // Kapasite 51 — gecersiz
        try {
            $service->masaEkle(21, 51);
            $this->fail('Kapasite 51 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Kapasite 1 ile 50 arasinda', $e->getMessage());
        }

        // Kapasite 1 — sinir degeri, basarili olmali
        $sonuc = $service->masaEkle(22, 1);
        $this->assertEquals(1, $sonuc['kapasite']);

        // Kapasite 50 — sinir degeri, basarili olmali
        $sonuc = $service->masaEkle(23, 50);
        $this->assertEquals(50, $sonuc['kapasite']);
    }

    /**
     * Her masa icin uretilen QR token'in benzersiz oldugunu dogrular.
     *
     * Beklenen davranis:
     * - Farkli masalar icin farkli QR token'lar uretilir
     * - Token formati 64 karakter hex string olur
     *
     * @test
     */
    public function testQrTokenBenzersiz(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository mock/stub entegrasyonu yapilacak.');

        $service = new MasaService();

        $masa1 = $service->masaEkle(30, 4);
        $masa2 = $service->masaEkle(31, 4);

        $this->assertNotEquals(
            $masa1['qr_token'],
            $masa2['qr_token'],
            'Farkli masalar icin farkli QR token uretilmeli'
        );

        // Hex format kontrolu
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $masa1['qr_token']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $masa2['qr_token']);
    }

    // =========================================================================
    // MasaSiparisService Testleri
    // =========================================================================

    /**
     * Basarili siparis olusturma islemini dogrular.
     *
     * Beklenen davranis:
     * - Gecerli oturum token ve kalemlerle siparis olusturulur
     * - Siparis durumu 'beklemede' olur
     * - Toplam tutar dogru hesaplanir
     * - Kalemler siparise eklenir
     *
     * @test
     */
    public function testSiparisOlusturBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository ve UrunRepository entegrasyonu yapilacak.');

        // Arrange
        $service = new MasaSiparisService();
        $oturumToken = 'gecerli_test_token';
        $kalemler = [
            ['urun_id' => 1, 'adet' => 2, 'ozel_not' => 'Sekerli olsun'],
            ['urun_id' => 2, 'adet' => 1],
        ];

        // Act
        $sonuc = $service->siparisOlustur($oturumToken, $kalemler, 'Acele olsun');

        // Assert
        $this->assertIsArray($sonuc);
        $this->assertEquals('beklemede', $sonuc['durum']);
        $this->assertArrayHasKey('toplam_tutar', $sonuc);
        $this->assertGreaterThan(0, (float) $sonuc['toplam_tutar']);
        $this->assertArrayHasKey('kalemler', $sonuc);
        $this->assertCount(2, $sonuc['kalemler']);
    }

    /**
     * Bos kalem listesiyle siparis olusturma girisimini dogrular.
     *
     * Beklenen davranis:
     * - Kalemler dizisi bos iken siparisOlustur cagrildığında ValidationException firlatilir
     * - Hata mesaji 'en az bir kalem' ifadesini icerir
     *
     * @test
     */
    public function testSiparisOlusturBosKalemler(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('en az bir kalem');
        $service->siparisOlustur('gecerli_token', []);
    }

    /**
     * Gecersiz oturum token ile siparis olusturma girisimini dogrular.
     *
     * Beklenen davranis:
     * - Var olmayan oturum token ile siparis olustu ruldiginda HttpException firlatilir
     * - HTTP status kodu 404 olur
     * - Hata mesaji 'Gecersiz oturum' ifadesini icerir
     *
     * @test
     */
    public function testSiparisOlusturGecersizOturum(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaOturumRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();
        $kalemler = [['urun_id' => 1, 'adet' => 1]];

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Gecersiz oturum');
        $service->siparisOlustur('olmayan_token_xyz', $kalemler);
    }

    /**
     * Stokta olmayan urun ile siparis olusturma girisimini dogrular.
     *
     * Beklenen davranis:
     * - stok_durumu='tukendi' olan urun icin siparis verildiginde ValidationException firlatilir
     * - Hata mesaji 'tukenmistir' ifadesini icerir
     *
     * @test
     */
    public function testSiparisOlusturTukenmisUrun(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — UrunRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();
        // stok_durumu='tukendi' olan bir urun ID'si ile
        $kalemler = [['urun_id' => 999, 'adet' => 1]];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('tukenmistir');
        $service->siparisOlustur('gecerli_token', $kalemler);
    }

    /**
     * Cafe menusunde olmayan urun ile siparis olusturma girisimini dogrular.
     *
     * Beklenen davranis:
     * - cafe_menusu=0 olan urunle siparis olusturuldiginda ValidationException firlatilir
     * - Hata mesaji 'cafe menusunde' ifadesini icerir
     *
     * @test
     */
    public function testSiparisOlusturCafeMenusuDegil(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — UrunRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();
        // cafe_menusu=0 olan bir urun ID'si ile
        $kalemler = [['urun_id' => 888, 'adet' => 1]];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cafe menusunde');
        $service->siparisOlustur('gecerli_token', $kalemler);
    }

    /**
     * Basarili siparis durum gecisini dogrular.
     *
     * Beklenen davranis:
     * - beklemede -> onaylandi gecisi basarili olur
     * - onaylandi -> hazirlaniyor gecisi basarili olur
     * - hazirlaniyor -> hazir gecisi basarili olur
     * - hazir -> teslim_edildi gecisi basarili olur
     * - Her geciste siparis durumu dogru guncellenir
     *
     * @test
     */
    public function testSiparisDurumGecisiBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // beklemede -> onaylandi
        $sonuc = $service->siparisDurumGuncelle(1, 'onaylandi');
        $this->assertEquals('onaylandi', $sonuc['siparis']['durum']);
        $this->assertArrayHasKey('mesaj', $sonuc);

        // onaylandi -> hazirlaniyor
        $sonuc = $service->siparisDurumGuncelle(1, 'hazirlaniyor');
        $this->assertEquals('hazirlaniyor', $sonuc['siparis']['durum']);

        // hazirlaniyor -> hazir
        $sonuc = $service->siparisDurumGuncelle(1, 'hazir');
        $this->assertEquals('hazir', $sonuc['siparis']['durum']);

        // hazir -> teslim_edildi
        $sonuc = $service->siparisDurumGuncelle(1, 'teslim_edildi');
        $this->assertEquals('teslim_edildi', $sonuc['siparis']['durum']);
    }

    /**
     * Gecersiz siparis durum gecisini dogrular.
     *
     * Beklenen davranis:
     * - beklemede -> hazir gecisi (atlama) ValidationException firlatir
     * - teslim_edildi -> onaylandi gecisi (geri alma) ValidationException firlatir
     * - iptal -> beklemede gecisi (kapali durumdan gecis) ValidationException firlatir
     * - Hata mesaji gecerli gecisleri listeler
     *
     * @test
     */
    public function testSiparisDurumGecisiGecersiz(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // beklemede -> hazir (gecersiz, atlama yapilmaz)
        try {
            $service->siparisDurumGuncelle(1, 'hazir');
            $this->fail('Gecersiz durum gecisi icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('gecis yapilamaz', $e->getMessage());
        }

        // teslim_edildi -> onaylandi (son durumdan geri alinmaz)
        try {
            $service->siparisDurumGuncelle(2, 'onaylandi');
            $this->fail('Son durumdan geri gecis icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('gecis yapilamaz', $e->getMessage());
        }

        // iptal -> beklemede (iptalden geri gecis yok)
        try {
            $service->siparisDurumGuncelle(3, 'beklemede');
            $this->fail('Iptal durumundan gecis icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('gecis yapilamaz', $e->getMessage());
        }
    }

    /**
     * Basarili siparis iptalini dogrular.
     *
     * Beklenen davranis:
     * - 'beklemede' durumundaki siparis basariyla iptal edilir
     * - 'onaylandi' durumundaki siparis basariyla iptal edilir
     * - Iptal sonrasi siparis durumu 'iptal' olur
     * - Sonuc mesaji 'iptal edildi' ifadesini icerir
     *
     * @test
     */
    public function testSiparisIptalBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // beklemede durumunda iptal
        $sonuc = $service->siparisIptal(1);
        $this->assertEquals('iptal', $sonuc['siparis']['durum']);
        $this->assertStringContainsString('iptal edildi', $sonuc['mesaj']);

        // onaylandi durumunda iptal
        $sonuc2 = $service->siparisIptal(2);
        $this->assertEquals('iptal', $sonuc2['siparis']['durum']);
    }

    /**
     * Teslim edilmis siperisin iptal edilme girisimini dogrular.
     *
     * Beklenen davranis:
     * - 'teslim_edildi' durumundaki siparis iptal edilmeye calisildiginda ValidationException firlatilir
     * - 'hazirlaniyor' durumundaki siparis de iptal edilemez
     * - Hata mesaji 'iptal edilemez' ifadesini icerir
     *
     * @test
     */
    public function testSiparisIptalZatenTeslimEdilmis(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // teslim_edildi durumunda iptal denemesi
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('iptal edilemez');
        $service->siparisIptal(1); // durum: teslim_edildi
    }

    /**
     * Siparis kalemi adet validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Adet < 1 ise ValidationException firlatilir ('en az 1')
     * - Adet > 99 ise ValidationException firlatilir ('en fazla 99')
     * - Adet 1-99 arasinda ise basarili olur
     *
     * @test
     */
    public function testAdetValidasyonu(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository ve UrunRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // Adet 0 — gecersiz
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 0],
            ]);
            $this->fail('Adet 0 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('en az 1', $e->getMessage());
        }

        // Adet 100 — gecersiz
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 100],
            ]);
            $this->fail('Adet 100 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('en fazla 99', $e->getMessage());
        }
    }

    /**
     * Ozel not uzunluk validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Kalem ozel notu 200 karakterden uzunsa ValidationException firlatilir
     * - Siparis notu 500 karakterden uzunsa ValidationException firlatilir
     * - Sinir degerlerde (200 ve 500 karakter) basarili olur
     *
     * @test
     */
    public function testNotUzunlukValidasyonu(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaSiparisRepository ve UrunRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // Kalem ozel notu 201 karakter — gecersiz
        $uzunNot = str_repeat('a', 201);
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 1, 'ozel_not' => $uzunNot],
            ]);
            $this->fail('201 karakter ozel not icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('en fazla 200 karakter', $e->getMessage());
        }

        // Siparis notu 501 karakter — gecersiz
        $uzunSiparisNotu = str_repeat('b', 501);
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 1],
            ], $uzunSiparisNotu);
            $this->fail('501 karakter siparis notu icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('en fazla 500 karakter', $e->getMessage());
        }
    }

    /**
     * Porsiyon secimi validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Gecerli porsiyon degerleri (4kisi, 6kisi, 8kisi, 10kisi) kabul edilir
     * - Gecersiz porsiyon degeri ('3kisi', 'buyuk' vb.) ValidationException firlatir
     * - Porsiyon secildiginde birim fiyat ilgili porsiyon fiyatindan alinir
     * - Urun icin tanimlanmamis porsiyon secildiginde hata verir
     *
     * @test
     */
    public function testPorsiyonValidasyonu(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — UrunRepository entegrasyonu yapilacak.');

        $service = new MasaSiparisService();

        // Gecersiz porsiyon degeri
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 1, 'porsiyon' => '3kisi'],
            ]);
            $this->fail('Gecersiz porsiyon degeri icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Gecersiz porsiyon', $e->getMessage());
        }

        // Gecerli porsiyon ama uurun icin tanimlanmamis
        try {
            $service->siparisOlustur('gecerli_token', [
                ['urun_id' => 1, 'adet' => 1, 'porsiyon' => '10kisi'],
            ]);
            $this->fail('Tanimlanmamis porsiyon icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('porsiyon mevcut degil', $e->getMessage());
        }
    }

    // =========================================================================
    // OdemeService Testleri
    // =========================================================================

    /**
     * Basarili odeme baslatma islemini dogrular.
     *
     * Beklenen davranis:
     * - Gecerli siparis ve musteri bilgileriyle odeme baslatilir
     * - Sonucta basarili=true, odeme_id ve token alanlari bulunur
     * - Odeme kaydinin durumu 'baslatildi' olur
     *
     * @test
     */
    public function testOdemeBaslatBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository ve MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new OdemeService();
        $musteri = [
            'ad'      => 'Ali',
            'soyad'   => 'Veli',
            'email'   => 'ali@example.com',
            'telefon' => '05551234567',
            'ip'      => '127.0.0.1',
        ];

        $sonuc = $service->odemeBaslat(1, $musteri, 'https://example.com/callback');

        $this->assertTrue($sonuc['basarili']);
        $this->assertArrayHasKey('odeme_id', $sonuc);
        $this->assertIsInt($sonuc['odeme_id']);
        $this->assertArrayHasKey('token', $sonuc);
    }

    /**
     * Zaten odenmis siparis icin tekrar odeme baslatma girisimini dogrular.
     *
     * Beklenen davranis:
     * - odeme_durumu='odendi' olan siparis icin odemeBaslat cagrildiginda ValidationException firlatilir
     * - Hata mesaji 'zaten odenmis' ifadesini icerir
     *
     * @test
     */
    public function testOdemeBaslatZatenOdenmis(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository ve MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new OdemeService();
        $musteri = [
            'ad'      => 'Ali',
            'soyad'   => 'Veli',
            'email'   => 'ali@example.com',
            'telefon' => '05551234567',
            'ip'      => '127.0.0.1',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('zaten odenmis');
        $service->odemeBaslat(1, $musteri, 'https://example.com/callback'); // siparis zaten odenmis
    }

    /**
     * Basarili odeme dogrulama islemini dogrular.
     *
     * Beklenen davranis:
     * - Gecerli callback token ile odemeDogrula basarili olur
     * - Sonucta basarili=true ve siparis_id alanlari bulunur
     * - Odeme durumu 'basarili' olarak guncellenir
     * - Siparis odeme_durumu 'odendi' olarak guncellenir
     *
     * @test
     */
    public function testOdemeDogrulaBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository entegrasyonu yapilacak.');

        $service = new OdemeService();

        // Once odeme baslat
        $musteri = [
            'ad'      => 'Test',
            'soyad'   => 'User',
            'email'   => 'test@example.com',
            'telefon' => '05551234567',
            'ip'      => '127.0.0.1',
        ];
        $odeme = $service->odemeBaslat(1, $musteri, 'https://example.com/callback');
        $token = $odeme['token'];

        // Odeme dogrula
        $sonuc = $service->odemeDogrula($token);

        $this->assertTrue($sonuc['basarili']);
        $this->assertArrayHasKey('siparis_id', $sonuc);
        $this->assertStringContainsString('tamamlandi', $sonuc['mesaj']);
    }

    /**
     * Gecersiz token ile odeme dogrulama girisimini dogrular.
     *
     * Beklenen davranis:
     * - Var olmayan/gecersiz token ile odemeDogrula cagrildiginda HttpException firlatilir
     * - Hata mesaji 'bulunamadi' ifadesini icerir
     *
     * @test
     */
    public function testOdemeDogrulaGecersizToken(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository entegrasyonu yapilacak.');

        $service = new OdemeService();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('bulunamadi');
        $service->odemeDogrula('gecersiz_token_xyz_123');
    }

    /**
     * Odeme tutar uyusmazligini dogrular.
     *
     * Beklenen davranis:
     * - Gateway'den gelen tutar, orijinal odeme tutarindan farkliysa HttpException firlatilir
     * - 0.01 TL tolerans siniri vardir (abs fark > 0.01)
     * - Odeme durumu 'basarisiz' olarak guncellenir
     * - Hata mesaji 'tutar' ile ilgili ifade icerir
     *
     * @test
     */
    public function testOdemeTutarUyusmazligi(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository mock/stub entegrasyonu yapilacak.');

        // Bu test, gateway'in farkli tutar dondurdugu senaryoyu simule eder
        // Gateway mock/stub ile tutar uyusmazligi olusturulmali
        $service = new OdemeService();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tutar');

        // Gateway mock ile farkli tutar donen dogrulama cagrisi
        $service->odemeDogrula('tutar_uyusmazligi_token');
    }

    /**
     * Odeme idempotency davranisini dogrular.
     *
     * Beklenen davranis:
     * - Zaten basarili olan odeme icin tekrar odemeDogrula cagrildigi nda
     *   hata firlatmaz, ayni basarili sonucu doner
     * - Sonucta 'zaten onaylanmis' mesaji bulunur
     * - Siparis durumu degismez (tekrar islenmez)
     *
     * @test
     */
    public function testOdemeIdempotency(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository entegrasyonu yapilacak.');

        $service = new OdemeService();

        // Ilk dogrulama — basarili
        $musteri = [
            'ad'      => 'Test',
            'soyad'   => 'User',
            'email'   => 'test@example.com',
            'telefon' => '05551234567',
            'ip'      => '127.0.0.1',
        ];
        $odeme = $service->odemeBaslat(1, $musteri, 'https://example.com/callback');
        $token = $odeme['token'];

        $sonuc1 = $service->odemeDogrula($token);
        $this->assertTrue($sonuc1['basarili']);

        // Ikinci dogrulama — idempotent olmali
        $sonuc2 = $service->odemeDogrula($token);
        $this->assertTrue($sonuc2['basarili']);
        $this->assertStringContainsString('zaten onaylanmis', $sonuc2['mesaj']);
    }

    /**
     * Basarili iade islemini dogrular.
     *
     * Beklenen davranis:
     * - Odenmis siparis icin iade baslatilir
     * - Tam iade (tutar=null) halinde tum tutar iade edilir
     * - Kismi iade halinde belirtilen tutar iade edilir
     * - Sonucta basarili=true ve iade_tutar alanlari bulunur
     * - Siparis odeme durumu 'iade' olarak guncellenir
     *
     * @test
     */
    public function testIadeBaslatBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — OdemeRepository ve MasaSiparisRepository entegrasyonu yapilacak.');

        $service = new OdemeService();

        // Tam iade
        $sonuc = $service->iadeBaslat(1); // siparis_id=1, tutar=null -> tam iade
        $this->assertTrue($sonuc['basarili']);
        $this->assertArrayHasKey('iade_tutar', $sonuc);
        $this->assertGreaterThan(0, $sonuc['iade_tutar']);
        $this->assertStringContainsString('basariyla tamamlandi', $sonuc['mesaj']);
    }

    // =========================================================================
    // MasaOturumService Testleri
    // =========================================================================

    /**
     * Aktif masa icin oturum dogrulama islemini dogrular.
     *
     * Beklenen davranis:
     * - Aktif oturumu olan masanin QR token'i ile oturumDogrula cagrildigi nda
     *   gecerli=true donmeli
     * - Sonucta masa bilgileri ve oturum_token yer almali
     * - Hosgeldiniz mesaji donmeli
     *
     * @test
     */
    public function testOturumDogrulaAktifMasa(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository ve MasaOturumRepository entegrasyonu yapilacak.');

        $service = new MasaOturumService();

        // Aktif masa icin gecerli QR token
        $sonuc = $service->oturumDogrula('gecerli_qr_token_aktif_masa');

        $this->assertTrue($sonuc['gecerli']);
        $this->assertNotNull($sonuc['masa']);
        $this->assertNotNull($sonuc['oturum_token']);
        $this->assertStringContainsString('Hosgeldiniz', $sonuc['mesaj']);
    }

    /**
     * Inaktif masa icin oturum dogrulama islemini dogrular.
     *
     * Beklenen davranis:
     * - Aktif oturumu olmayan (bos) masanin QR token'i ile oturumDogrula
     *   cagrildigi nda gecerli=false donmeli
     * - oturum_token null olmali
     * - Masa bilgisi donmeli ama oturum aktif degil mesaji verilmeli
     * - Gecersiz QR token ile cagrildiginda gecerli=false ve masa=null donmeli
     *
     * @test
     */
    public function testOturumDogrulaInaktifMasa(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaRepository ve MasaOturumRepository entegrasyonu yapilacak.');

        $service = new MasaOturumService();

        // Var olan ama inaktif masa
        $sonuc = $service->oturumDogrula('gecerli_qr_token_inaktif_masa');
        $this->assertFalse($sonuc['gecerli']);
        $this->assertNotNull($sonuc['masa']);
        $this->assertNull($sonuc['oturum_token']);
        $this->assertStringContainsString('aktif degil', $sonuc['mesaj']);

        // Var olmayan QR token
        $sonucGecersiz = $service->oturumDogrula('olmayan_qr_token_xyz');
        $this->assertFalse($sonucGecersiz['gecerli']);
        $this->assertNull($sonucGecersiz['masa']);
        $this->assertNull($sonucGecersiz['oturum_token']);
        $this->assertStringContainsString('Gecersiz QR', $sonucGecersiz['mesaj']);
    }

    /**
     * Basarili oturum kapatma islemini dogrular.
     *
     * Beklenen davranis:
     * - Aktif oturum basariyla kapatilir
     * - Oturum durumu 'tamamlandi' olarak guncellenir
     * - Zaten kapanmis oturum kapatilmaya calisildigi nda HttpException firlatilir
     *
     * @test
     */
    public function testOturumKapatBasarili(): void
    {
        $this->markTestIncomplete('DB baglantisi gerekli — MasaOturumRepository entegrasyonu yapilacak.');

        $service = new MasaOturumService();

        // Aktif oturumu kapat
        $sonuc = $service->oturumKapat(1, 'tamamlandi');
        $this->assertEquals('tamamlandi', $sonuc['durum']);

        // Zaten kapanmis oturumu tekrar kapatma girisiimi
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('zaten kapatilmis');
        $service->oturumKapat(1, 'tamamlandi');
    }

    // =========================================================================
    // MasaSiparisValidator Testleri
    // =========================================================================

    /**
     * Gecerli durum guncelleme validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Gecerli durum degerlerinden biri gonderildiginde validasyon basarili olur
     * - Donen dizide yeni_durum alani bulunur
     *
     * @test
     */
    public function testDurumGuncelleGecerli(): void
    {
        $validator = new MasaSiparisValidator('durum_guncelle');

        $gecerliDurumlar = ['beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];

        foreach ($gecerliDurumlar as $durum) {
            $sonuc = $validator->validate(['yeni_durum' => $durum]);
            $this->assertEquals($durum, $sonuc['yeni_durum'], "'{$durum}' durumu gecerli olmali");
        }
    }

    /**
     * Gecersiz durum guncelleme validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Gecersiz durum degeri ('bilinmeyen', '', null vb.) gonderildiginde ValidationException firlatilir
     * - Bos string gonderildiginde 'required' hatasi verilir
     * - Olmayan durum degeri icin 'in' hatasi verilir
     *
     * @test
     */
    public function testDurumGuncelleGecersiz(): void
    {
        $validator = new MasaSiparisValidator('durum_guncelle');

        // Gecersiz durum degeri
        try {
            $validator->validate(['yeni_durum' => 'bilinmeyen_durum']);
            $this->fail('Gecersiz durum icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertIsArray($e->getErrors());
            $this->assertArrayHasKey('yeni_durum', $e->getErrors());
        }

        // Bos deger
        $validator2 = new MasaSiparisValidator('durum_guncelle');
        try {
            $validator2->validate(['yeni_durum' => '']);
            $this->fail('Bos durum icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('yeni_durum', $e->getErrors());
        }

        // Eksik alan
        $validator3 = new MasaSiparisValidator('durum_guncelle');
        try {
            $validator3->validate([]);
            $this->fail('Eksik durum alani icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('yeni_durum', $e->getErrors());
        }
    }

    /**
     * Gecerli siparis olusturma validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Gecerli oturum_token ve kalemler ile validasyon basarili olur
     * - Siparis notu opsiyonel (nullable) olmali
     * - Donen dizi oturum_token, kalemler ve siparis_notu alanlarini icermeli
     *
     * @test
     */
    public function testSiparisOlusturGecerli(): void
    {
        $validator = new MasaSiparisValidator('siparis_olustur');

        $data = [
            'oturum_token' => 'test_token_123',
            'kalemler'     => [
                ['urun_id' => 1, 'adet' => 2],
                ['urun_id' => 3, 'adet' => 1, 'ozel_not' => 'Az sekerli'],
            ],
            'siparis_notu' => 'Acele olsun',
        ];

        $sonuc = $validator->validate($data);

        $this->assertArrayHasKey('oturum_token', $sonuc);
        $this->assertEquals('test_token_123', $sonuc['oturum_token']);
        $this->assertArrayHasKey('kalemler', $sonuc);
    }

    /**
     * Bos kalemlerle siparis olusturma validasyonunu dogrular (Validator katmani).
     *
     * Beklenen davranis:
     * - Kalemler bos dizi oldugunda ValidationException firlatilir
     * - Kalemler null/eksik oldugunda ValidationException firlatilir
     * - Hata mesajinda 'kalemler' alani yer almali
     *
     * @test
     */
    public function testValidatorSiparisOlusturBosKalemler(): void
    {
        // Bos kalemler dizisi
        $validator = new MasaSiparisValidator('siparis_olustur');
        try {
            $validator->validate([
                'oturum_token' => 'test_token',
                'kalemler'     => [],
            ]);
            $this->fail('Bos kalemler icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kalemler', $e->getErrors());
        }

        // Eksik kalemler alani
        $validator2 = new MasaSiparisValidator('siparis_olustur');
        try {
            $validator2->validate([
                'oturum_token' => 'test_token',
            ]);
            $this->fail('Eksik kalemler icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kalemler', $e->getErrors());
        }
    }

    /**
     * Gecersiz adet ile siparis olusturma validasyonunu dogrular.
     *
     * Beklenen davranis:
     * - Adet 0 veya negatif ise validasyon hatasi verilir
     * - Adet > 99 ise validasyon hatasi verilir
     * - Hata mesajinda 'kalemler' alani ve adet bilgisi yer almali
     *
     * @test
     */
    public function testSiparisOlusturGecersizAdet(): void
    {
        // Adet 0
        $validator = new MasaSiparisValidator('siparis_olustur');
        try {
            $validator->validate([
                'oturum_token' => 'test_token',
                'kalemler'     => [
                    ['urun_id' => 1, 'adet' => 0],
                ],
            ]);
            $this->fail('Adet 0 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('kalemler', $errors);
            $kalemHatalari = implode(' ', $errors['kalemler']);
            $this->assertStringContainsString('1 ile 99', $kalemHatalari);
        }

        // Adet 100
        $validator2 = new MasaSiparisValidator('siparis_olustur');
        try {
            $validator2->validate([
                'oturum_token' => 'test_token',
                'kalemler'     => [
                    ['urun_id' => 1, 'adet' => 100],
                ],
            ]);
            $this->fail('Adet 100 icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('kalemler', $errors);
            $kalemHatalari = implode(' ', $errors['kalemler']);
            $this->assertStringContainsString('1 ile 99', $kalemHatalari);
        }

        // Negatif adet
        $validator3 = new MasaSiparisValidator('siparis_olustur');
        try {
            $validator3->validate([
                'oturum_token' => 'test_token',
                'kalemler'     => [
                    ['urun_id' => 1, 'adet' => -5],
                ],
            ]);
            $this->fail('Negatif adet icin ValidationException bekleniyor');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('kalemler', $errors);
        }
    }
}
