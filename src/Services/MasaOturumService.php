<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MasaRepository;
use Pastane\Repositories\MasaOturumRepository;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

/**
 * Masa Oturum Service
 *
 * QR Menu sistemi oturum yonetimi is mantigi.
 * Oturum olusturma, dogrulama, kapatma islemleri.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class MasaOturumService extends BaseService
{
    /**
     * @var MasaOturumRepository
     */
    protected MasaOturumRepository $oturumRepository;

    /**
     * @var MasaRepository
     */
    protected MasaRepository $masaRepository;

    /**
     * Constructor
     *
     * @param MasaOturumRepository|null $repository
     * @param MasaRepository|null $masaRepository
     */
    public function __construct(
        ?MasaOturumRepository $repository = null,
        ?MasaRepository $masaRepository = null
    ) {
        $this->oturumRepository = $repository ?? new MasaOturumRepository();
        $this->masaRepository = $masaRepository ?? new MasaRepository();
        $this->repository = $this->oturumRepository;
    }

    /**
     * Yeni oturum olustur
     *
     * Masa icin benzersiz oturum token'i uretir.
     *
     * @param int $masaId Masa ID
     * @param int $musteriSayisi Musteri sayisi
     * @return array Olusturulan oturum
     * @throws HttpException Masa bulunamazsa
     * @throws ValidationException Zaten aktif oturum varsa
     */
    public function oturumOlustur(int $masaId, int $musteriSayisi = 1): array
    {
        // Masa var mi?
        $this->masaRepository->findOrFail($masaId);

        // Zaten aktif oturum var mi?
        $aktifOturum = $this->oturumRepository->getAktifOturum($masaId);
        if ($aktifOturum !== null) {
            throw new ValidationException('Bu masada zaten aktif bir oturum bulunuyor.', [
                'masa_id' => ['Masa ' . $masaId . ' icin aktif oturum mevcut.'],
            ]);
        }

        $oturumToken = bin2hex(random_bytes(32));

        $data = [
            'masa_id'          => $masaId,
            'oturum_token'     => $oturumToken,
            'musteri_sayisi'   => max(1, $musteriSayisi),
            'durum'            => 'aktif',
            'baslangic_zamani' => date('Y-m-d H:i:s'),
        ];

        $id = $this->oturumRepository->create($data);

        return $this->oturumRepository->findOrFail($id);
    }

    /**
     * QR token ile oturum dogrula
     *
     * QR kodu tarayan musteri icin masanin aktif olup olmadigini kontrol eder.
     * Aktif oturum varsa oturum token'ini doner.
     *
     * @param string $qrToken Masanin QR token'i
     * @return array ['gecerli' => bool, 'masa' => array|null, 'oturum_token' => string|null, 'mesaj' => string]
     */
    public function oturumDogrula(string $qrToken): array
    {
        // QR token ile masayi bul
        $masa = $this->masaRepository->findByQrToken($qrToken);

        if ($masa === null) {
            return [
                'gecerli'      => false,
                'masa'         => null,
                'oturum_token' => null,
                'mesaj'        => 'Gecersiz QR kod. Lutfen tekrar deneyin.',
            ];
        }

        // Aktif oturumu kontrol et
        $aktifOturum = $this->oturumRepository->getAktifOturum((int)$masa['id']);

        if ($aktifOturum === null) {
            return [
                'gecerli'      => false,
                'masa'         => $masa,
                'oturum_token' => null,
                'mesaj'        => 'Bu masa simdilik aktif degil. Lutfen garsondan yardim isteyin.',
            ];
        }

        return [
            'gecerli'      => true,
            'masa'         => $masa,
            'oturum_token' => $aktifOturum['oturum_token'],
            'mesaj'        => 'Hosgeldiniz! Masa ' . $masa['masa_no'] . ' icin siparis verebilirsiniz.',
        ];
    }

    /**
     * Oturum bilgilerini getir
     *
     * Oturum detaylari ve o oturuma ait siparisleri doner.
     *
     * @param string $oturumToken Oturum token'i
     * @return array ['oturum' => array, 'masa' => array, 'siparisler' => array]
     * @throws HttpException Oturum bulunamazsa
     */
    public function oturumBilgisi(string $oturumToken): array
    {
        $oturum = $this->oturumRepository->findByOturumToken($oturumToken);

        if ($oturum === null) {
            throw HttpException::notFound('Oturum bulunamadi.');
        }

        $masa = $this->masaRepository->findOrFail((int)$oturum['masa_id']);
        $siparisler = $this->oturumRepository->getOturumSiparisleri((int)$oturum['id']);

        return [
            'oturum'     => $oturum,
            'masa'       => $masa,
            'siparisler' => $siparisler,
        ];
    }

    /**
     * Oturumu kapat
     *
     * @param int $oturumId Oturum ID
     * @param string $durum Kapanma durumu (tamamlandi veya iptal)
     * @return array Guncellenmis oturum
     * @throws HttpException Oturum bulunamazsa veya zaten kapanmissa
     */
    public function oturumKapat(int $oturumId, string $durum = 'tamamlandi'): array
    {
        $oturum = $this->oturumRepository->findOrFail($oturumId);

        if ($oturum['durum'] !== 'aktif') {
            throw HttpException::badRequest('Bu oturum zaten kapatilmis.');
        }

        $this->oturumRepository->oturumuKapat($oturumId, $durum);

        return $this->oturumRepository->findOrFail($oturumId);
    }

    /**
     * Masada aktif oturum var mi?
     *
     * @param int $masaId Masa ID
     * @return bool
     */
    public function aktifOturumVar(int $masaId): bool
    {
        $aktifOturum = $this->oturumRepository->getAktifOturum($masaId);
        return $aktifOturum !== null;
    }
}
