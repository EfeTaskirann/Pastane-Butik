<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MasaRepository;
use Pastane\Repositories\MasaOturumRepository;
use Pastane\Repositories\MasaSiparisRepository;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

/**
 * Masa Service
 *
 * QR Menu sistemi masa yonetimi is mantigi.
 * Masa ekleme, guncelleme, silme, aktif etme ve kapatma islemleri.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class MasaService extends BaseService
{
    /**
     * @var MasaRepository
     */
    protected MasaRepository $masaRepository;

    /**
     * @var MasaOturumRepository
     */
    protected MasaOturumRepository $oturumRepository;

    /**
     * @var MasaSiparisRepository
     */
    protected MasaSiparisRepository $siparisRepository;

    /**
     * Constructor
     *
     * @param MasaRepository|null $repository
     * @param MasaOturumRepository|null $oturumRepository
     * @param MasaSiparisRepository|null $siparisRepository
     */
    public function __construct(
        ?MasaRepository $repository = null,
        ?MasaOturumRepository $oturumRepository = null,
        ?MasaSiparisRepository $siparisRepository = null
    ) {
        $this->masaRepository = $repository ?? new MasaRepository();
        $this->oturumRepository = $oturumRepository ?? new MasaOturumRepository();
        $this->siparisRepository = $siparisRepository ?? new MasaSiparisRepository();
        $this->repository = $this->masaRepository;
    }

    /**
     * Cache TTL sabitleri (saniye)
     */
    private const CACHE_TTL_MASALAR = 60;

    /**
     * Cache key sabitleri
     */
    private const CACHE_KEY_MASALAR = 'masalar_listesi';

    /**
     * Yeni masa ekle
     *
     * Otomatik QR token uretir.
     *
     * @param int $masaNo Masa numarasi
     * @param int $kapasite Oturma kapasitesi
     * @param string|null $konum Masa konumu (ic mekan, dis mekan vb.)
     * @return array Olusturulan masa
     * @throws ValidationException Masa numarasi zaten mevcutsa
     */
    public function masaEkle(int $masaNo, int $kapasite, ?string $konum = null): array
    {
        // Kapasite validasyonu (min 1, max 50)
        if ($kapasite < 1 || $kapasite > 50) {
            throw new ValidationException('Gecersiz kapasite degeri.', [
                'kapasite' => ['Kapasite 1 ile 50 arasinda olmalidir.'],
            ]);
        }

        // Konum uzunluk kontrolu (defense-in-depth)
        if ($konum !== null && mb_strlen($konum) > 50) {
            throw new ValidationException('Konum 50 karakteri gecemez.', [
                'konum' => ['Konum 50 karakteri gecemez.'],
            ]);
        }

        // Masa numarasi benzersizlik kontrolu
        $mevcut = $this->masaRepository->findByMasaNo($masaNo);
        if ($mevcut !== null) {
            throw new ValidationException('Bu masa numarasi zaten kullaniliyor.', [
                'masa_no' => ['Masa numarasi ' . $masaNo . ' zaten mevcut.'],
            ]);
        }

        $qrToken = bin2hex(random_bytes(32));

        $data = [
            'masa_no'  => $masaNo,
            'kapasite' => $kapasite,
            'konum'    => $konum,
            'qr_token' => $qrToken,
            'durum'    => 'bos',
        ];

        $id = $this->masaRepository->create($data);
        $this->clearCacheKeys(self::CACHE_KEY_MASALAR);

        return $this->masaRepository->findOrFail($id);
    }

    /**
     * Masa bilgilerini guncelle
     *
     * @param int $id Masa ID
     * @param array $data Guncellenecek alanlar (masa_no, kapasite, konum)
     * @return array Guncellenmis masa
     * @throws HttpException Masa bulunamazsa
     * @throws ValidationException Masa numarasi baska masada kullaniliyorsa
     */
    public function masaGuncelle(int $id, array $data): array
    {
        $masa = $this->masaRepository->findOrFail($id);

        // Korumali alanlari kaldir — bu degerler sadece masaAktifEt/masaKapat ile degismeli
        unset($data['durum'], $data['aktif_oturum_id'], $data['qr_token']);

        // Bos data kontrolu — unset sonrasi guncellenecek alan kalmamis olabilir
        if (empty($data)) {
            throw new ValidationException('Güncellenecek alan belirtilmedi.', []);
        }

        // Konum uzunluk kontrolu (defense-in-depth)
        if (isset($data['konum']) && mb_strlen((string)$data['konum']) > 50) {
            throw new ValidationException('Konum 50 karakteri gecemez.', [
                'konum' => ['Konum 50 karakteri gecemez.'],
            ]);
        }

        // Kapasite validasyonu (guncelleme sirasinda da)
        if (isset($data['kapasite'])) {
            $kapasite = (int)$data['kapasite'];
            if ($kapasite < 1 || $kapasite > 50) {
                throw new ValidationException('Gecersiz kapasite degeri.', [
                    'kapasite' => ['Kapasite 1 ile 50 arasinda olmalidir.'],
                ]);
            }
        }

        // Masa numarasi degistiriliyorsa benzersizlik kontrolu
        if (isset($data['masa_no']) && (int)$data['masa_no'] !== (int)$masa['masa_no']) {
            $mevcut = $this->masaRepository->findByMasaNo((int)$data['masa_no']);
            if ($mevcut !== null) {
                throw new ValidationException('Bu masa numarasi zaten kullaniliyor.', [
                    'masa_no' => ['Masa numarasi ' . $data['masa_no'] . ' baska bir masada kullaniliyor.'],
                ]);
            }
        }

        $this->masaRepository->update($id, $data);
        $this->clearCacheKeys(self::CACHE_KEY_MASALAR);

        return $this->masaRepository->findOrFail($id);
    }

    /**
     * Masayi sil
     *
     * Aktif oturumu olan masa silinemez.
     *
     * @param int $id Masa ID
     * @return bool
     * @throws HttpException Masa bulunamazsa veya aktif oturumu varsa
     */
    public function masaSil(int $id): bool
    {
        $masa = $this->masaRepository->findOrFail($id);

        // Aktif oturum kontrolu
        $aktifOturum = $this->oturumRepository->getAktifOturum($id);
        if ($aktifOturum !== null) {
            throw HttpException::badRequest('Aktif oturumu olan masa silinemez. Once oturumu kapatiniz.');
        }

        $result = $this->masaRepository->delete($id);
        $this->clearCacheKeys(self::CACHE_KEY_MASALAR);

        return $result;
    }

    /**
     * Masayi aktif hale getir ve yeni oturum olustur
     *
     * @param int $id Masa ID
     * @param int $musteriSayisi Musteri sayisi
     * @return array ['masa' => array, 'oturum' => array]
     * @throws HttpException Masa bulunamazsa veya zaten aktifse
     */
    public function masaAktifEt(int $id, int $musteriSayisi = 1): array
    {
        $masa = $this->masaRepository->findOrFail($id);

        // Musteri sayisi validasyonu
        $musteriSayisi = (int)$musteriSayisi;
        if ($musteriSayisi < 1) {
            $musteriSayisi = 1;
        }
        if ($musteriSayisi > $masa['kapasite']) {
            throw new ValidationException('Müşteri sayısı masa kapasitesini aşamaz.', [
                'musteri_sayisi' => ['Maksimum kapasite: ' . $masa['kapasite']],
            ]);
        }

        // Zaten aktif oturum var mi?
        $aktifOturum = $this->oturumRepository->getAktifOturum($id);
        if ($aktifOturum !== null) {
            throw HttpException::badRequest('Bu masada zaten aktif bir oturum bulunuyor.');
        }

        return $this->masaRepository->transaction(function () use ($id, $musteriSayisi) {
            // Oturum token uret
            $oturumToken = bin2hex(random_bytes(32));

            // Oturumu dogrudan repository uzerinden olustur (service locator kullanmadan)
            $oturumId = $this->oturumRepository->create([
                'masa_id'        => $id,
                'oturum_token'   => $oturumToken,
                'durum'          => 'aktif',
                'musteri_sayisi' => $musteriSayisi,
            ]);

            // Masa durumunu guncelle
            $this->masaRepository->updateDurum($id, 'aktif');
            $this->masaRepository->updateAktifOturum($id, (int)$oturumId);

            $this->clearCacheKeys(self::CACHE_KEY_MASALAR);

            $masa = $this->masaRepository->findOrFail($id);
            $oturum = $this->oturumRepository->find((int)$oturumId);

            return [
                'masa'   => $masa,
                'oturum' => $oturum,
            ];
        });
    }

    /**
     * Masayi kapat ve oturumu bitir
     *
     * Tum siparisler teslim edilmis veya iptal edilmis olmalidir.
     *
     * @param int $id Masa ID
     * @return array ['masa' => array, 'mesaj' => string]
     * @throws HttpException Masa bulunamazsa veya acik siparis varsa
     */
    public function masaKapat(int $id): array
    {
        $masa = $this->masaRepository->findOrFail($id);

        $aktifOturum = $this->oturumRepository->getAktifOturum($id);
        if ($aktifOturum === null) {
            throw HttpException::badRequest('Bu masada aktif oturum bulunmuyor.');
        }

        // Acik siparis kontrolu
        $tumKapandi = $this->siparisRepository->tumSiparislerKapandiMi((int)$aktifOturum['id']);
        if (!$tumKapandi) {
            throw HttpException::badRequest(
                'Tum siparisler teslim edilmeden veya iptal edilmeden masa kapatilamaz.'
            );
        }

        return $this->masaRepository->transaction(function () use ($id, $aktifOturum) {
            // Oturumu kapat
            $this->oturumRepository->oturumuKapat((int)$aktifOturum['id'], 'tamamlandi');

            // Masa durumunu guncelle
            $this->masaRepository->updateDurum($id, 'bos');
            $this->masaRepository->updateAktifOturum($id, null);

            $this->clearCacheKeys(self::CACHE_KEY_MASALAR);

            $masa = $this->masaRepository->findOrFail($id);

            return [
                'masa'  => $masa,
                'mesaj' => 'Masa ' . $masa['masa_no'] . ' basariyla kapatildi.',
            ];
        });
    }

    /**
     * Tum masalari listele
     *
     * Sonuc 60 saniye cache'lenir. Masa ekle/guncelle/sil/aktif et/kapat
     * islemlerinde cache otomatik olarak temizlenir.
     *
     * @return array
     */
    public function getMasalar(): array
    {
        return \Cache::getInstance()->remember(
            self::CACHE_KEY_MASALAR,
            fn () => $this->masaRepository->all(['*'], 'masa_no', 'ASC'),
            self::CACHE_TTL_MASALAR
        );
    }

    /**
     * QR token ile masa bul
     *
     * @param string $qrToken QR token
     * @return array|null
     */
    public function getMasaByQrToken(string $qrToken): ?array
    {
        return $this->masaRepository->findByQrToken($qrToken);
    }

    /**
     * QR token'i yenile
     *
     * Yeni benzersiz token uretir ve gunceller.
     *
     * @param int $id Masa ID
     * @return array Guncellenmis masa
     * @throws HttpException Masa bulunamazsa
     */
    public function qrTokenYenile(int $id): array
    {
        $this->masaRepository->findOrFail($id);

        $yeniToken = bin2hex(random_bytes(32));
        $this->masaRepository->update($id, ['qr_token' => $yeniToken]);

        return $this->masaRepository->findOrFail($id);
    }
}
