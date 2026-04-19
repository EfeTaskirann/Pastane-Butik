<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MasaRepository;
use Pastane\Exceptions\HttpException;

/**
 * QR Kod Service
 *
 * QR Menu sistemi icin QR kod uretimi ve yonetimi.
 * Google Charts API kullanarak QR kod gorseli uretir.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class QrKodService extends BaseService
{
    /**
     * Google Charts API QR uretim URL sablonu
     */
    private const GOOGLE_QR_API = 'https://chart.googleapis.com/chart?cht=qr&chs=%dx%d&chl=%s';

    /**
     * Varsayilan QR kod boyutu (piksel)
     */
    private const VARSAYILAN_BOYUT = 300;

    /**
     * @var MasaRepository
     */
    protected MasaRepository $masaRepository;

    /**
     * Constructor
     *
     * @param MasaRepository|null $repository
     */
    public function __construct(?MasaRepository $repository = null)
    {
        $this->masaRepository = $repository ?? new MasaRepository();
        $this->repository = $this->masaRepository;
    }

    /**
     * QR kod gorseli uret
     *
     * Google Charts API kullanarak QR kod PNG verisi doner.
     * Not: Ileride endroid/qr-code kutuphanesi ile degistirilebilir.
     *
     * @param string $qrToken Masanin QR token'i
     * @param int $boyut QR kod boyutu (piksel)
     * @return array ['url' => string, 'menu_url' => string, 'boyut' => int]
     * @throws HttpException QR token gecersizse
     */
    public function qrKodUret(string $qrToken, int $boyut = self::VARSAYILAN_BOYUT): array
    {
        // Token gecerliligini kontrol et
        $masa = $this->masaRepository->findByQrToken($qrToken);
        if ($masa === null) {
            throw HttpException::notFound('Gecersiz QR token.');
        }

        $menuUrl = $this->qrKodUrl($qrToken);
        $qrImageUrl = sprintf(
            self::GOOGLE_QR_API,
            $boyut,
            $boyut,
            urlencode($menuUrl)
        );

        return [
            'url'      => $qrImageUrl,
            'menu_url' => $menuUrl,
            'boyut'    => $boyut,
            'masa_no'  => $masa['masa_no'],
        ];
    }

    /**
     * QR kodu PNG olarak indir
     *
     * Google Charts API'den QR kod gorselini ceker ve PNG data olarak doner.
     * Not: Ileride endroid/qr-code ile lokal uretim yapilabilir.
     *
     * @param string $qrToken Masanin QR token'i
     * @param int $boyut QR kod boyutu (piksel)
     * @return array ['data' => string, 'content_type' => string, 'dosya_adi' => string]
     * @throws HttpException QR token gecersizse veya gorsel indirilemezse
     */
    public function qrKodIndir(string $qrToken, int $boyut = self::VARSAYILAN_BOYUT): array
    {
        $qrBilgi = $this->qrKodUret($qrToken, $boyut);

        // Google Charts API'den gorseli cek
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Pastane QR Menu/1.0',
            ],
        ]);

        $imageData = @file_get_contents($qrBilgi['url'], false, $context);

        if ($imageData === false) {
            throw HttpException::serverError('QR kod gorseli indirilemedi. Lutfen daha sonra tekrar deneyin.');
        }

        return [
            'data'         => $imageData,
            'content_type' => 'image/png',
            'dosya_adi'    => 'masa-' . $qrBilgi['masa_no'] . '-qr.png',
        ];
    }

    /**
     * Menu URL'ini doner
     *
     * QR kod tarandiginda yonlendirilecek menu sayfasi URL'i.
     *
     * @param string $qrToken Masanin QR token'i
     * @return string Menu URL'i: {APP_URL}/menu?t={qr_token}
     */
    public function qrKodUrl(string $qrToken): string
    {
        $baseUrl = config('app.url', 'http://localhost/pastane');
        return rtrim($baseUrl, '/') . '/menu?t=' . urlencode($qrToken);
    }
}
