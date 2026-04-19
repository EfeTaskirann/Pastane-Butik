<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MasaSiparisRepository;
use Pastane\Repositories\MasaOturumRepository;
use Pastane\Repositories\UrunRepository;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

/**
 * Masa Siparis Service
 *
 * QR Menu sistemi siparis yonetimi is mantigi.
 * Siparis olusturma, durum guncelleme, iptal islemleri.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class MasaSiparisService extends BaseService
{
    /**
     * Gecerli durum gecis kurallari
     *
     * Her durum icin gecis yapilabilecek durumlar.
     */
    public const DURUM_GECISLERI = [
        'beklemede'     => ['onaylandi', 'iptal'],
        'onaylandi'     => ['hazirlaniyor', 'iptal'],
        'hazirlaniyor'  => ['hazir'],
        'hazir'         => ['teslim_edildi'],
        'teslim_edildi' => [],
        'iptal'         => [],
    ];

    /**
     * Iptal edilebilir durumlar
     */
    public const IPTAL_EDILEBILIR_DURUMLAR = ['beklemede', 'onaylandi'];

    /**
     * Durum etiketleri (gorunum icin)
     */
    public const DURUM_ETIKETLERI = [
        'beklemede'     => 'Beklemede',
        'onaylandi'     => 'Onaylandi',
        'hazirlaniyor'  => 'Hazirlaniyor',
        'hazir'         => 'Hazir',
        'teslim_edildi' => 'Teslim Edildi',
        'iptal'         => 'Iptal',
    ];

    /**
     * Cache TTL sabitleri (saniye)
     */
    private const CACHE_TTL_ISTATISTIK = 30;

    /**
     * Cache key sabitleri
     */
    private const CACHE_KEY_ISTATISTIK = 'bugun_istatistikleri';

    /**
     * @var MasaSiparisRepository
     */
    protected MasaSiparisRepository $siparisRepository;

    /**
     * @var MasaOturumRepository
     */
    protected MasaOturumRepository $oturumRepository;

    /**
     * @var UrunRepository
     */
    protected UrunRepository $urunRepository;

    /**
     * Constructor
     *
     * @param MasaSiparisRepository|null $repository
     * @param MasaOturumRepository|null $oturumRepository
     * @param UrunRepository|null $urunRepository
     */
    public function __construct(
        ?MasaSiparisRepository $repository = null,
        ?MasaOturumRepository $oturumRepository = null,
        ?UrunRepository $urunRepository = null
    ) {
        $this->siparisRepository = $repository ?? new MasaSiparisRepository();
        $this->oturumRepository = $oturumRepository ?? new MasaOturumRepository();
        $this->urunRepository = $urunRepository ?? new UrunRepository();
        $this->repository = $this->siparisRepository;
    }

    /**
     * Yeni siparis olustur
     *
     * Oturum token'i ile kimlik dogrulama yapar, kalemleri ekler,
     * toplam tutari hesaplar.
     *
     * @param string $oturumToken Aktif oturum token'i
     * @param array $kalemler Siparis kalemleri [{urun_id, adet, porsiyon?, ozel_not?}, ...]
     * @param string|null $siparisNotu Genel siparis notu
     * @return array Olusturulan siparis ve kalemleri
     * @throws HttpException Oturum gecersiz veya aktif degilse
     * @throws ValidationException Kalemler bos veya gecersizse
     */
    public function siparisOlustur(string $oturumToken, array $kalemler, ?string $siparisNotu = null): array
    {
        // Oturum dogrula
        $oturum = $this->oturumRepository->findByOturumToken($oturumToken);
        if ($oturum === null) {
            throw HttpException::notFound('Gecersiz oturum. Lutfen QR kodu tekrar tarayin.');
        }

        if ($oturum['durum'] !== 'aktif') {
            throw HttpException::badRequest('Bu oturum artik aktif degil. Yeni siparis verilemez.');
        }

        // Kalem validasyonu
        if (empty($kalemler)) {
            throw new ValidationException('Siparis olusturmak icin en az bir kalem gereklidir.', [
                'kalemler' => ['En az bir urun secmelisiniz.'],
            ]);
        }

        // Siparis notu uzunluk kontrolu (max 500 karakter)
        if ($siparisNotu !== null && mb_strlen($siparisNotu) > 500) {
            throw new ValidationException('Siparis notu cok uzun.', [
                'siparis_notu' => ['Siparis notu en fazla 500 karakter olabilir.'],
            ]);
        }

        return $this->siparisRepository->transaction(function () use ($oturum, $kalemler, $siparisNotu) {
            // Kalemleri hazirla ve toplam tutari hesapla
            $hazirKalemler = [];
            $toplamTutar = 0;

            foreach ($kalemler as $kalem) {
                $urunId = (int)($kalem['urun_id'] ?? 0);

                // Adet validasyonu
                $orijinalAdet = isset($kalem['adet']) ? (int)$kalem['adet'] : 1;
                if ($orijinalAdet < 1) {
                    throw new ValidationException('Geçersiz adet değeri.', [
                        'adet' => ['Ürün adedi en az 1 olmalıdır.'],
                    ]);
                }
                if ($orijinalAdet > 99) {
                    throw new ValidationException('Adet siniri asildi.', [
                        'adet' => ['Bir kalem icin en fazla 99 adet siparis verilebilir.'],
                    ]);
                }
                $adet = $orijinalAdet;

                // Ozel not uzunluk kontrolu (max 200 karakter)
                $ozelNot = $kalem['ozel_not'] ?? null;
                if ($ozelNot !== null && mb_strlen((string)$ozelNot) > 200) {
                    throw new ValidationException('Ozel not cok uzun.', [
                        'ozel_not' => ['Kalem notu en fazla 200 karakter olabilir.'],
                    ]);
                }

                // Urun kontrolu
                $urun = $this->urunRepository->find($urunId);
                if ($urun === null) {
                    throw new ValidationException('Gecersiz urun.', [
                        'urun_id' => ['Urun ID ' . $urunId . ' bulunamadi.'],
                    ]);
                }

                // Urun adi — her iki kolon adini da destekle
                $urunAdi = $urun['isim'] ?? $urun['ad'] ?? 'Bilinmeyen Ürün';

                // Urun aktif mi kontrolu
                if (empty($urun['aktif']) || (int)$urun['aktif'] !== 1) {
                    throw new ValidationException('Urun aktif degil.', [
                        'urun_id' => [$urunAdi . ' su anda satin alinamaz.'],
                    ]);
                }

                // Stok durumu kontrolu
                if (isset($urun['stok_durumu']) && $urun['stok_durumu'] === 'tukendi') {
                    throw new ValidationException('Urun stokta yok.', [
                        'urun_id' => [$urunAdi . ' tukenmistir.'],
                    ]);
                }

                // Cafe menusu kontrolu
                if (isset($urun['cafe_menusu']) && (int)$urun['cafe_menusu'] === 0) {
                    throw new ValidationException('Bu urun cafe menusunde mevcut degil.', [
                        'urun_id' => [$urunAdi . ' cafe menusunde bulunmamaktadir.'],
                    ]);
                }

                // Porsiyon fiyat destegi
                $porsiyon = $kalem['porsiyon'] ?? null;
                $birimFiyat = (float)$urun['fiyat'];

                if ($porsiyon !== null) {
                    $porsiyonKolonlari = [
                        '4kisi'  => 'fiyat_4kisi',
                        '6kisi'  => 'fiyat_6kisi',
                        '8kisi'  => 'fiyat_8kisi',
                        '10kisi' => 'fiyat_10kisi',
                    ];

                    if (!isset($porsiyonKolonlari[$porsiyon])) {
                        throw new ValidationException('Gecersiz porsiyon degeri.', [
                            'porsiyon' => ['Gecerli porsiyon degerleri: 4kisi, 6kisi, 8kisi, 10kisi.'],
                        ]);
                    }

                    if (!empty($urun[$porsiyonKolonlari[$porsiyon]])) {
                        $birimFiyat = (float)$urun[$porsiyonKolonlari[$porsiyon]];
                    } else {
                        throw new ValidationException('Bu urun icin secilen porsiyon mevcut degil.', [
                            'porsiyon' => [$urunAdi . ' urunu icin ' . $porsiyon . ' porsiyonu tanimlanmamis.'],
                        ]);
                    }
                }
                $toplamFiyat = $birimFiyat * $adet;
                $toplamTutar += $toplamFiyat;

                $hazirKalemler[] = [
                    'urun_id'      => $urunId,
                    'urun_adi'     => $urunAdi,
                    'porsiyon'     => $porsiyon,
                    'adet'         => $adet,
                    'birim_fiyat'  => $birimFiyat,
                    'toplam_fiyat' => $toplamFiyat,
                    'ozel_not'     => $ozelNot,
                ];
            }

            // Siparisi olustur
            $siparisData = [
                'oturum_id'    => (int)$oturum['id'],
                'masa_id'      => (int)$oturum['masa_id'],
                'durum'        => 'beklemede',
                'toplam_tutar' => $toplamTutar,
                'siparis_notu' => $siparisNotu,
            ];

            $siparisId = $this->siparisRepository->create($siparisData);

            // Kalemleri ekle
            foreach ($hazirKalemler as $kalem) {
                $this->siparisRepository->addKalem((int)$siparisId, $kalem);
            }

            $this->clearCacheKeys(self::CACHE_KEY_ISTATISTIK);

            // Siparis detayini don
            $siparis = $this->siparisRepository->findOrFail((int)$siparisId);
            $siparis['kalemler'] = $this->siparisRepository->getSiparisKalemleri((int)$siparisId);

            return $siparis;
        });
    }

    /**
     * Siparis durumunu guncelle
     *
     * Durum gecis kurallarini kontrol eder.
     * Sadece gecerli gecisler kabul edilir.
     *
     * @param int $siparisId Siparis ID
     * @param string $yeniDurum Yeni durum
     * @return array ['siparis' => array, 'mesaj' => string]
     * @throws HttpException Siparis bulunamazsa
     * @throws ValidationException Gecersiz durum gecisiyse
     */
    public function siparisDurumGuncelle(int $siparisId, string $yeniDurum): array
    {
        $siparis = $this->siparisRepository->findOrFail($siparisId);
        $mevcutDurum = $siparis['durum'] ?? 'beklemede';

        // Durum gecis kontrolu
        $gecerliGecisler = self::DURUM_GECISLERI[$mevcutDurum] ?? [];
        if (!in_array($yeniDurum, $gecerliGecisler, true)) {
            throw new ValidationException(
                "'{$mevcutDurum}' durumundan '{$yeniDurum}' durumuna gecis yapilamaz.",
                [
                    'durum' => [
                        "Gecerli gecisler: " . (empty($gecerliGecisler)
                            ? 'yok (son durum)'
                            : implode(', ', $gecerliGecisler)),
                    ],
                ]
            );
        }

        $this->siparisRepository->updateDurum($siparisId, $yeniDurum);
        $this->clearCacheKeys(self::CACHE_KEY_ISTATISTIK);

        $siparis = $this->siparisRepository->findOrFail($siparisId);

        // $yeniDurum, validasyondan (DURUM_GECISLERI) gecmis oldugu icin
        // DURUM_ETIKETLERI icinde garantili olarak vardir — ?? fallback gerekmez.
        return [
            'siparis' => $siparis,
            'mesaj'   => 'Siparis durumu "' . self::DURUM_ETIKETLERI[$yeniDurum] . '" olarak guncellendi.',
        ];
    }

    /**
     * Siparisi iptal et
     *
     * Sadece beklemede veya onaylandi durumundaki siparisler iptal edilebilir.
     *
     * @param int $siparisId Siparis ID
     * @return array ['siparis' => array, 'mesaj' => string]
     * @throws HttpException Siparis bulunamazsa
     * @throws ValidationException Iptal edilemez durumdaysa
     */
    public function siparisIptal(int $siparisId): array
    {
        $siparis = $this->siparisRepository->findOrFail($siparisId);
        $mevcutDurum = $siparis['durum'] ?? '';

        if (!in_array($mevcutDurum, self::IPTAL_EDILEBILIR_DURUMLAR, true)) {
            throw new ValidationException(
                'Bu siparis iptal edilemez.',
                [
                    'durum' => [
                        'Sadece "beklemede" veya "onaylandi" durumundaki siparisler iptal edilebilir. '
                        . 'Mevcut durum: ' . $mevcutDurum,
                    ],
                ]
            );
        }

        $this->siparisRepository->updateDurum($siparisId, 'iptal');
        $this->clearCacheKeys(self::CACHE_KEY_ISTATISTIK);

        if (class_exists('SecurityAudit')) {
            \SecurityAudit::log(\SecurityAudit::ADMIN_ACTION, $_SESSION['admin_id'] ?? null, [
                'action'        => 'masa_siparis_iptal',
                'siparis_id'    => $siparisId,
                'onceki_durum'  => $mevcutDurum,
                'masa_id'       => $siparis['masa_id'] ?? null,
                'toplam_tutar'  => $siparis['toplam_tutar'] ?? null,
            ]);
        }

        $siparis = $this->siparisRepository->findOrFail($siparisId);

        return [
            'siparis' => $siparis,
            'mesaj'   => 'Siparis basariyla iptal edildi.',
        ];
    }

    /**
     * Masada odeme secimi
     *
     * Musteri online odeme yerine masada (nakit/POS) odemeyi secer.
     * Siparis durumu beklemede → onaylandi olarak guncellenir,
     * odeme_yontemi 'masada' olarak kaydedilir.
     * Garson teslim sirasinda odemeyi fiziksel olarak alir.
     *
     * @param int $siparisId Siparis ID
     * @param string $oturumToken Oturum token (guvenlik kontrolu)
     * @return array ['siparis' => array, 'mesaj' => string]
     * @throws HttpException Siparis bulunamazsa
     * @throws ValidationException Oturum uyusmazligi veya gecersiz durum
     */
    public function masadaOdemeSecimi(int $siparisId, string $oturumToken): array
    {
        // Oturum dogrula
        $oturum = $this->oturumRepository->findByOturumToken($oturumToken);
        if ($oturum === null) {
            throw HttpException::notFound('Gecersiz oturum.');
        }

        // Siparis getir
        $siparis = $this->siparisRepository->findOrFail($siparisId);

        // Siparis bu oturuma ait mi?
        if ((int)$siparis['oturum_id'] !== (int)$oturum['id']) {
            throw HttpException::forbidden('Bu siparis sizin oturumunuza ait degil.');
        }

        // Siparis beklemede olmali
        if (($siparis['durum'] ?? '') !== 'beklemede') {
            throw new ValidationException('Bu siparis icin odeme yontemi secilemez.', [
                'durum' => ['Siparis durumu: ' . ($siparis['durum'] ?? 'bilinmiyor')],
            ]);
        }

        // Zaten odenmis mi?
        if (($siparis['odeme_durumu'] ?? '') === 'odendi') {
            throw new ValidationException('Bu siparis zaten odenmis.', [
                'odeme_durumu' => ['Siparis icin odeme zaten tamamlanmis.'],
            ]);
        }

        // Odeme yontemi 'masada' olarak kaydet
        $this->siparisRepository->updateOdemeDurumu($siparisId, 'odenmedi', 'masada');

        // Siparis durumu beklemede → onaylandi
        $this->siparisRepository->updateDurum($siparisId, 'onaylandi');
        $this->clearCacheKeys(self::CACHE_KEY_ISTATISTIK);

        $siparis = $this->siparisRepository->findOrFail($siparisId);

        return [
            'siparis' => $siparis,
            'mesaj'   => 'Masada odeme secildi. Siparisiz hazirlaniyor, odemeyi garson geldiginde yapabilirsiniz.',
        ];
    }

    /**
     * Garson: masada fiziksel odeme alindi (nakit veya POS)
     *
     * Siparis odeme_durumu='odendi', odeme_yontemi='nakit'|'pos' olarak guncellenir.
     * Sadece 'hazir' veya 'teslim_edildi' durumundaki siparislerde izin verilir.
     *
     * @param int $siparisId Siparis ID
     * @param string $yontem 'nakit' veya 'pos'
     * @return array ['siparis' => array, 'mesaj' => string]
     * @throws HttpException Siparis bulunamazsa
     * @throws ValidationException Gecersiz yontem veya durum
     */
    public function garsonOdemeAl(int $siparisId, string $yontem): array
    {
        $izinliYontemler = ['nakit', 'pos'];
        if (!in_array($yontem, $izinliYontemler, true)) {
            throw new ValidationException('Gecersiz odeme yontemi.', [
                'odeme_yontemi' => ['Gecerli degerler: nakit, pos.'],
            ]);
        }

        $siparis = $this->siparisRepository->findOrFail($siparisId);
        $mevcutDurum = $siparis['durum'] ?? '';

        if (!in_array($mevcutDurum, ['hazir', 'teslim_edildi'], true)) {
            throw new ValidationException('Bu siparis icin odeme alinamaz.', [
                'durum' => ['Sadece hazir veya teslim edilen siparisler icin odeme alinabilir. Mevcut durum: ' . $mevcutDurum],
            ]);
        }

        if (($siparis['odeme_durumu'] ?? '') === 'odendi') {
            throw new ValidationException('Bu siparis zaten odenmis.', [
                'odeme_durumu' => ['Siparis icin odeme zaten tamamlanmis.'],
            ]);
        }

        $this->siparisRepository->updateOdemeDurumu($siparisId, 'odendi', $yontem);
        $this->clearCacheKeys(self::CACHE_KEY_ISTATISTIK);

        if (class_exists('SecurityAudit')) {
            \SecurityAudit::log(\SecurityAudit::ADMIN_ACTION, $_SESSION['admin_id'] ?? null, [
                'action'        => 'garson_odeme_al',
                'siparis_id'    => $siparisId,
                'odeme_yontemi' => $yontem,
                'masa_id'       => $siparis['masa_id'] ?? null,
                'toplam_tutar'  => $siparis['toplam_tutar'] ?? null,
            ]);
        }

        $siparis = $this->siparisRepository->findOrFail($siparisId);

        return [
            'siparis' => $siparis,
            'mesaj'   => $yontem === 'nakit' ? 'Nakit odeme alindi.' : 'POS odemesi alindi.',
        ];
    }

    /**
     * Aktif siparisleri getir
     *
     * Iptal ve teslim edilmis siparisler haric tum siparisler.
     *
     * @return array
     */
    public function getAktifSiparisler(): array
    {
        return $this->siparisRepository->getAktifSiparisler();
    }

    /**
     * Mutfak ekrani icin siparisleri getir
     *
     * Onaylandi ve hazirlaniyor durumundaki siparisler.
     *
     * @return array
     */
    public function getMutfakSiparisleri(): array
    {
        $siparisler = $this->siparisRepository->getMutfakSiparisleri();

        return $this->siparislereKalemEkle($siparisler);
    }

    /**
     * Hazir siparisleri getir (garson icin)
     *
     * Hazir durumunda olup teslim edilmeyi bekleyen siparisler.
     *
     * @return array
     */
    public function getHazirSiparisler(): array
    {
        return $this->siparisRepository->getHazirSiparisler();
    }

    /**
     * Siparis detayini getir
     *
     * Siparis bilgileri ve kalemleriyle birlikte.
     *
     * @param int $siparisId Siparis ID
     * @return array Siparis + kalemler
     * @throws HttpException Siparis bulunamazsa
     */
    public function getSiparisDetay(int $siparisId): array
    {
        $siparis = $this->siparisRepository->findOrFail($siparisId);
        $siparis['kalemler'] = $this->siparisRepository->getSiparisKalemleri($siparisId);

        return $siparis;
    }

    /**
     * Bugunun siparislerini getir (opsiyonel durum filtresi ile)
     *
     * Her siparisin kalemleri de eklenir.
     *
     * @param string|null $durumFiltre Belirli bir duruma gore filtrele
     * @return array
     */
    public function getBugununSiparisleri(?string $durumFiltre = null): array
    {
        $siparisler = $this->siparisRepository->getBugununSiparisleri($durumFiltre);

        return $this->siparislereKalemEkle($siparisler);
    }

    /**
     * Bugunun siparis istatistiklerini getir
     *
     * Sonuc 30 saniye cache'lenir. Siparis durum degisikliginde,
     * yeni siparis olusturuldiginda ve iptal isleminde cache temizlenir.
     *
     * @return array
     */
    public function getBugununIstatistikleri(): array
    {
        return \Cache::getInstance()->remember(
            self::CACHE_KEY_ISTATISTIK,
            fn () => $this->siparisRepository->getBugununIstatistikleri(),
            self::CACHE_TTL_ISTATISTIK
        );
    }

    /**
     * Durum etiketlerini getir
     *
     * Tum durumlarin Turkce gorunum etiketlerini doner.
     * Admin sayfasi ve diger katmanlar bu metodu kullanmalidir (DRY).
     *
     * @return array<string, string>
     */
    public function getDurumEtiketleri(): array
    {
        return self::DURUM_ETIKETLERI;
    }

    // =========================================================================
    // Dahili Yardimci Metodlar
    // =========================================================================

    /**
     * Siparis listesine kalemleri toplu ekle (N+1 sorgu onleme)
     *
     * Her siparis icin ayri sorgu yerine, tum siparis ID'lerini toplayip
     * tek sorguda tum kalemleri alir. N siparis icin N+1 sorguyu
     * 2 sorguya dusurur.
     *
     * @param array $siparisler Siparis listesi
     * @return array Kalemleri eklenmis siparis listesi
     */
    private function siparislereKalemEkle(array $siparisler): array
    {
        if (empty($siparisler)) {
            return $siparisler;
        }

        $siparisIds = array_map(fn ($s) => (int)$s['id'], $siparisler);
        $grupluKalemler = $this->siparisRepository->getKalemlerBySiparisIds($siparisIds);

        foreach ($siparisler as &$siparis) {
            $siparis['kalemler'] = $grupluKalemler[(int)$siparis['id']] ?? [];
        }
        unset($siparis);

        return $siparisler;
    }

    // =========================================================================
    // Raporlama Metodlari
    // =========================================================================

    /**
     * QR Menu masa raporlarini toplu getir
     *
     * Tum rapor verilerini tek bir cagri ile toplar ve doner.
     * Tarih parametreleri validate edilir; gecersiz tarih durumunda
     * ValidationException firlatilir.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d formatinda)
     * @param string $bitis Bitis tarihi (Y-m-d formatinda)
     * @return array {
     *     masa_ciro: array,
     *     saatlik_yogunluk: array,
     *     siparis_suresi: array,
     *     populer_urunler: array,
     *     gunluk_ozet: array,
     *     odeme_dagilimi: array
     * }
     * @throws ValidationException Tarih parametreleri gecersizse
     */
    public function getMasaRaporlari(string $baslangic, string $bitis): array
    {
        $this->validateTarihAraligi($baslangic, $bitis);

        return [
            'masa_ciro'         => $this->siparisRepository->getMasaBazliCiro($baslangic, $bitis),
            'saatlik_yogunluk'  => $this->siparisRepository->getSaatlikYogunluk($baslangic, $bitis),
            'siparis_suresi'    => $this->siparisRepository->getOrtalamaSiparisSuresi($baslangic, $bitis),
            'populer_urunler'   => $this->siparisRepository->getEnCokSiparisDen($baslangic, $bitis),
            'gunluk_ozet'       => $this->siparisRepository->getGunlukOzet($baslangic, $bitis),
            'odeme_dagilimi'    => $this->siparisRepository->getOdemeYontemiDagilimi($baslangic, $bitis),
        ];
    }

    /**
     * Tarih araligini validate et
     *
     * Y-m-d formatinda olup olmadigini ve baslangicin bitisten
     * once olup olmadigini kontrol eder.
     *
     * @param string $baslangic
     * @param string $bitis
     * @return void
     * @throws ValidationException
     */
    private function validateTarihAraligi(string $baslangic, string $bitis): void
    {
        $errors = [];

        $baslangicDate = \DateTimeImmutable::createFromFormat('Y-m-d', $baslangic);
        if ($baslangicDate === false || $baslangicDate->format('Y-m-d') !== $baslangic) {
            $errors['baslangic'] = ['Baslangic tarihi gecerli bir Y-m-d formatinda olmalidir.'];
        }

        $bitisDate = \DateTimeImmutable::createFromFormat('Y-m-d', $bitis);
        if ($bitisDate === false || $bitisDate->format('Y-m-d') !== $bitis) {
            $errors['bitis'] = ['Bitis tarihi gecerli bir Y-m-d formatinda olmalidir.'];
        }

        if (!empty($errors)) {
            throw new ValidationException('Gecersiz tarih parametreleri.', $errors);
        }

        // Tarih araligi kontrolu: baslangic <= bitis
        if ($baslangicDate > $bitisDate) {
            throw new ValidationException('Gecersiz tarih araligi.', [
                'baslangic' => ['Baslangic tarihi, bitis tarihinden sonra olamaz.'],
            ]);
        }

        // Maksimum 1 yillik aralik kontrolu (performans korumasi)
        $fark = $baslangicDate->diff($bitisDate);
        if ($fark->days > 366) {
            throw new ValidationException('Tarih araligi cok genis.', [
                'bitis' => ['Tarih araligi en fazla 1 yil (366 gun) olabilir.'],
            ]);
        }
    }
}
