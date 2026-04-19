<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\OdemeRepository;
use Pastane\Repositories\MasaSiparisRepository;
use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

/**
 * Odeme Service
 *
 * Odeme gateway entegrasyonu ve odeme is mantigi.
 * Gateway abstraction sayesinde farkli odeme saglayicilarina
 * gecis kolayca yapilabilir (test, iyzico, paytr vb.).
 *
 * SDK bagimliligi olmadan, kendi cURL wrapper'imiz ile calisir.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class OdemeService extends BaseService
{
    /**
     * Desteklenen gateway'ler
     */
    public const DESTEKLENEN_GATEWAY_LER = ['test', 'iyzico'];

    /**
     * @var OdemeRepository
     */
    private OdemeRepository $odemeRepository;

    /**
     * @var MasaSiparisRepository
     */
    private MasaSiparisRepository $siparisRepository;

    /**
     * @var string Aktif gateway (test, iyzico, paytr)
     */
    private string $gateway;

    /**
     * @var array Odeme konfigurasyonu
     */
    private array $config;

    /**
     * Constructor
     *
     * @param OdemeRepository|null $odemeRepository
     * @param MasaSiparisRepository|null $siparisRepository
     */
    public function __construct(
        ?OdemeRepository $odemeRepository = null,
        ?MasaSiparisRepository $siparisRepository = null
    ) {
        $this->odemeRepository = $odemeRepository ?? new OdemeRepository();
        $this->siparisRepository = $siparisRepository ?? new MasaSiparisRepository();
        $this->repository = $this->odemeRepository;

        $this->config = $this->loadConfig();
        $this->gateway = $this->config['gateway'] ?? 'test';

        if (!in_array($this->gateway, self::DESTEKLENEN_GATEWAY_LER, true)) {
            $this->gateway = 'test';
        }
    }

    /**
     * Odeme baslatma
     *
     * 1. Siparis bilgisini getirir (masa_siparisleri + kalemler)
     * 2. Siparis zaten odenmisse hata firlatir
     * 3. odeme_islemleri tablosuna 'baslatildi' kaydi olusturur
     * 4. Gateway'e istek gonderir (test/iyzico)
     * 5. Gateway response'ini kaydeder
     * 6. Odeme formu/URL'si dondurur
     *
     * @param int $siparisId Siparis ID
     * @param array $musteri Musteri bilgileri [ad, soyad, email, telefon, ip, adres]
     * @param string $callbackUrl Odeme sonucu callback URL'si
     * @return array ['basarili' => bool, 'odeme_id' => int, 'odeme_formu' => ?string, 'redirect_url' => ?string, 'token' => ?string]
     * @throws HttpException Siparis bulunamazsa
     * @throws ValidationException Siparis zaten odenmisse veya musteri bilgileri eksikse
     */
    public function odemeBaslat(int $siparisId, array $musteri, string $callbackUrl): array
    {
        // Musteri bilgisi validasyonu
        $this->musteriDogrula($musteri);

        // Siparis bilgisini getir
        $siparis = $this->siparisRepository->findOrFail($siparisId);

        // Siparis zaten odenmis mi?
        if (($siparis['odeme_durumu'] ?? '') === 'odendi') {
            throw new ValidationException('Bu siparis zaten odenmis.', [
                'siparis_id' => ['Siparis #' . $siparisId . ' icin odeme zaten tamamlanmis.'],
            ]);
        }

        // Siparis iptal mi?
        if (($siparis['durum'] ?? '') === 'iptal') {
            throw new ValidationException('Iptal edilmis siparis icin odeme yapilamaz.', [
                'siparis_id' => ['Siparis #' . $siparisId . ' iptal edilmis.'],
            ]);
        }

        // Siparis kalemlerini getir
        $kalemler = $this->siparisRepository->getSiparisKalemleri($siparisId);

        // odeme_islemleri tablosuna baslatildi kaydi olustur
        $islemId = $this->islemIdOlustur();
        $odemeId = $this->odemeRepository->create([
            'siparis_id'  => $siparisId,
            'gateway'     => $this->gateway,
            'islem_id'    => $islemId,
            'tutar'       => (float) $siparis['toplam_tutar'],
            'para_birimi' => $this->config['para_birimi'] ?? 'TRY',
            'durum'       => 'baslatildi',
        ]);

        $this->logOdemeIslem('odeme_baslatildi', [
            'odeme_id'   => $odemeId,
            'siparis_id' => $siparisId,
            'gateway'    => $this->gateway,
            'tutar'      => $siparis['toplam_tutar'],
        ]);

        // Gateway'e gore odeme baslat
        try {
            $gatewayYanit = match ($this->gateway) {
                'iyzico' => $this->iyzicoOdemeBaslat($siparis, $kalemler, $musteri, $callbackUrl),
                default  => $this->testOdemeBaslat($siparis, $callbackUrl, (int) $odemeId),
            };
        } catch (\Throwable $e) {
            // Gateway hatasi durumunda odeme durumunu guncelle
            $this->odemeRepository->updateDurum((int) $odemeId, 'basarisiz', [
                'hata' => $e->getMessage(),
            ]);

            $this->logOdemeIslem('gateway_hatasi', [
                'odeme_id'   => $odemeId,
                'siparis_id' => $siparisId,
                'gateway'    => $this->gateway,
                'hata'       => $e->getMessage(),
            ]);

            throw HttpException::serverError('Odeme baslatilamadi: ' . $e->getMessage());
        }

        // Gateway yanitini kaydet
        $this->odemeRepository->updateDurum((int) $odemeId, 'baslatildi', [
            'gateway_yanit' => $gatewayYanit,
        ]);

        return [
            'basarili'     => true,
            'odeme_id'     => (int) $odemeId,
            'odeme_formu'  => $gatewayYanit['odeme_formu'] ?? null,
            'redirect_url' => $gatewayYanit['redirect_url'] ?? null,
            'token'        => $gatewayYanit['token'] ?? null,
        ];
    }

    /**
     * Odeme dogrulama (callback'ten gelen veriyi dogrula)
     *
     * 1. Gateway'den gelen callback token'ini dogrular
     * 2. Basariliysa: odeme_islemleri.durum = 'basarili', siparis guncelle
     * 3. Basarisizsa: odeme_islemleri.durum = 'basarisiz'
     * 4. Gateway yanitini JSON olarak kaydeder
     *
     * @param string $token Gateway'den gelen callback token
     * @return array ['basarili' => bool, 'siparis_id' => int, 'mesaj' => string]
     * @throws HttpException Token gecersizse veya islem bulunamazsa
     */
    public function odemeDogrula(string $token): array
    {
        if (empty($token)) {
            throw HttpException::badRequest('Odeme token bilgisi eksik.');
        }

        // Gateway'e gore dogrulama yap
        try {
            $dogrulamaYanit = match ($this->gateway) {
                'iyzico' => $this->iyzicoOdemeDogrula($token),
                default  => $this->testOdemeDogrula($token),
            };
        } catch (\Throwable $e) {
            $this->logOdemeIslem('dogrulama_hatasi', [
                'token'   => substr($token, 0, 8) . '...',
                'gateway' => $this->gateway,
                'hata'    => $e->getMessage(),
            ]);

            throw HttpException::serverError('Odeme dogrulama hatasi: ' . $e->getMessage());
        }

        $basarili = $dogrulamaYanit['basarili'] ?? false;
        $islemId = $dogrulamaYanit['islem_id'] ?? '';

        // Odeme kaydini bul
        $odeme = $this->odemeRepository->findByIslemId($islemId);
        if ($odeme === null) {
            // Test modunda islem_id TEST_ ile baslar, token ile eslestirmeye calis
            $odeme = $this->tokenIleOdemeBul($token);
        }

        if ($odeme === null) {
            throw HttpException::notFound('Odeme islemi bulunamadi.');
        }

        // Idempotency: zaten islenmis mi?
        if ($odeme['durum'] === 'basarili') {
            return [
                'basarili'   => true,
                'siparis_id' => (int) $odeme['siparis_id'],
                'odeme_id'   => (int) $odeme['id'],
                'mesaj'      => 'Odeme zaten onaylanmis.',
            ];
        }

        $siparisId = (int) $odeme['siparis_id'];
        $odemeId = (int) $odeme['id'];

        // Tutar dogrulama — callback'ten gelen tutar orijinal odeme tutariyla eslesmeli
        if ($basarili && isset($dogrulamaYanit['tutar']) && $dogrulamaYanit['tutar'] > 0
            && abs((float) $dogrulamaYanit['tutar'] - (float) $odeme['tutar']) > 0.01
        ) {
            $this->odemeRepository->updateDurum($odemeId, 'basarisiz', [
                'hata'     => 'Tutar uyusmazligi',
                'beklenen' => (float) $odeme['tutar'],
                'gelen'    => (float) $dogrulamaYanit['tutar'],
            ]);

            $this->logOdemeIslem('tutar_uyusmazligi', [
                'odeme_id'   => $odemeId,
                'siparis_id' => $siparisId,
                'beklenen'   => (float) $odeme['tutar'],
                'gelen'      => (float) $dogrulamaYanit['tutar'],
            ]);

            throw HttpException::badRequest('Odeme tutari dogrulanamadi.');
        }

        // Transaction ile atomik guncelleme
        return $this->odemeRepository->transaction(function () use ($basarili, $odemeId, $siparisId, $dogrulamaYanit) {
            if ($basarili) {
                // Odeme basarili — durumlari guncelle
                $this->odemeRepository->updateDurum($odemeId, 'basarili', $dogrulamaYanit);

                $this->siparisRepository->updateOdemeDurumu(
                    $siparisId,
                    'odendi',
                    $this->gateway,
                    $dogrulamaYanit['islem_id'] ?? null
                );

                // Siparis durumunu otomatik onayla
                $siparis = $this->siparisRepository->find($siparisId);
                if ($siparis !== null && ($siparis['durum'] ?? '') === 'beklemede') {
                    $this->siparisRepository->updateDurum($siparisId, 'onaylandi');
                }

                $this->logOdemeIslem('odeme_basarili', [
                    'odeme_id'   => $odemeId,
                    'siparis_id' => $siparisId,
                    'tutar'      => $dogrulamaYanit['tutar'] ?? null,
                ]);

                return [
                    'basarili'   => true,
                    'siparis_id' => $siparisId,
                    'odeme_id'   => $odemeId,
                    'mesaj'      => 'Odeme basariyla tamamlandi.',
                ];
            }

            // Odeme basarisiz
            $this->odemeRepository->updateDurum($odemeId, 'basarisiz', $dogrulamaYanit);

            $this->logOdemeIslem('odeme_basarisiz', [
                'odeme_id'   => $odemeId,
                'siparis_id' => $siparisId,
                'hata'       => $dogrulamaYanit['mesaj'] ?? 'Bilinmeyen hata',
            ]);

            return [
                'basarili'   => false,
                'siparis_id' => $siparisId,
                'odeme_id'   => $odemeId,
                'mesaj'      => $dogrulamaYanit['mesaj'] ?? 'Odeme basarisiz oldu.',
            ];
        });
    }

    /**
     * Odeme durumu sorgulama
     *
     * Siparis icin en son odeme isleminin durumunu dondurur.
     *
     * @param int $siparisId Siparis ID
     * @return array ['durum' => string, 'odeme' => ?array, 'siparis_odeme_durumu' => string]
     * @throws HttpException Siparis bulunamazsa
     */
    public function odemeDurumuSorgula(int $siparisId): array
    {
        $siparis = $this->siparisRepository->findOrFail($siparisId);
        $odemeler = $this->odemeRepository->findBySiparisId($siparisId);

        $sonOdeme = !empty($odemeler) ? $odemeler[0] : null;

        return [
            'siparis_id'           => $siparisId,
            'siparis_odeme_durumu' => $siparis['odeme_durumu'] ?? 'odenmedi',
            'durum'                => $sonOdeme['durum'] ?? 'odeme_yok',
            'odeme'                => $sonOdeme,
            'toplam_odeme_sayisi'  => count($odemeler),
        ];
    }

    /**
     * Iade islemi baslat
     *
     * Basarili odemeyi iade eder. Tam veya kismi iade desteklenir.
     *
     * @param int $siparisId Siparis ID
     * @param float|null $tutar Iade tutari (null ise tam iade)
     * @return array ['basarili' => bool, 'mesaj' => string, 'iade_tutar' => float]
     * @throws HttpException Siparis veya odeme bulunamazsa
     * @throws ValidationException Odeme iade edilemez durumdaysa
     */
    public function iadeBaslat(int $siparisId, ?float $tutar = null): array
    {
        $siparis = $this->siparisRepository->findOrFail($siparisId);

        // Siparis odenmis mi?
        if (($siparis['odeme_durumu'] ?? '') !== 'odendi') {
            throw new ValidationException('Sadece odenmis siparisler iade edilebilir.', [
                'odeme_durumu' => ['Mevcut odeme durumu: ' . ($siparis['odeme_durumu'] ?? 'odenmedi')],
            ]);
        }

        // Basarili odemeyi bul
        $odemeler = $this->odemeRepository->findBySiparisId($siparisId);
        $basariliOdeme = null;
        foreach ($odemeler as $odeme) {
            if ($odeme['durum'] === 'basarili') {
                $basariliOdeme = $odeme;
                break;
            }
        }

        if ($basariliOdeme === null) {
            throw HttpException::notFound('Bu siparis icin basarili odeme kaydina ulasilamadi.');
        }

        // Iade tutari kontrolu
        $odemeTutar = (float) $basariliOdeme['tutar'];
        $iadeTutar = $tutar ?? $odemeTutar;

        if ($iadeTutar <= 0) {
            throw new ValidationException('Iade tutari sifirdan buyuk olmalidir.', [
                'tutar' => ['Gecersiz iade tutari: ' . $iadeTutar],
            ]);
        }

        if ($iadeTutar > $odemeTutar) {
            throw new ValidationException('Iade tutari odeme tutarini asamaz.', [
                'tutar' => ['Odeme tutari: ' . $odemeTutar . ', istenilen iade: ' . $iadeTutar],
            ]);
        }

        // Transaction ile atomik iade
        return $this->odemeRepository->transaction(function () use ($basariliOdeme, $siparisId, $iadeTutar) {
            $odemeId = (int) $basariliOdeme['id'];

            // Gateway'e gore iade istegi
            $iadeYanit = match ($this->gateway) {
                'iyzico' => $this->iyzicoIadeBaslat($basariliOdeme, $iadeTutar),
                default  => $this->testIadeBaslat($basariliOdeme, $iadeTutar),
            };

            if ($iadeYanit['basarili']) {
                // Odeme durumunu iade olarak guncelle
                $this->odemeRepository->updateDurum($odemeId, 'iade', $iadeYanit);

                // Siparis odeme durumunu guncelle
                $this->siparisRepository->updateOdemeDurumu($siparisId, 'iade', $this->gateway);

                $this->logOdemeIslem('iade_basarili', [
                    'odeme_id'   => $odemeId,
                    'siparis_id' => $siparisId,
                    'iade_tutar' => $iadeTutar,
                ]);

                return [
                    'basarili'   => true,
                    'mesaj'      => 'Iade islemi basariyla tamamlandi.',
                    'iade_tutar' => $iadeTutar,
                    'odeme_id'   => $odemeId,
                ];
            }

            $this->logOdemeIslem('iade_basarisiz', [
                'odeme_id'   => $odemeId,
                'siparis_id' => $siparisId,
                'hata'       => $iadeYanit['mesaj'] ?? 'Bilinmeyen hata',
            ]);

            return [
                'basarili'   => false,
                'mesaj'      => $iadeYanit['mesaj'] ?? 'Iade islemi basarisiz oldu.',
                'iade_tutar' => $iadeTutar,
                'odeme_id'   => $odemeId,
            ];
        });
    }

    // ================================================================
    // TEST GATEWAY
    // ================================================================

    /**
     * Test modu: Odeme baslatma (gercek API cagrisi yapmaz)
     *
     * @param array $siparis Siparis bilgileri
     * @param string $callbackUrl Callback URL
     * @return array Gateway yaniti
     */
    private function testOdemeBaslat(array $siparis, string $callbackUrl, int $odemeId = 0): array
    {
        $testToken = bin2hex(random_bytes(16));
        $islemId = 'TEST_' . $testToken;

        // odeme kaydindaki islem_id'yi guncelle — dogrulama adiminda eslessin
        if ($odemeId > 0) {
            $this->odemeRepository->update($odemeId, ['islem_id' => $islemId]);
        }

        $testConfig = $this->config['test'] ?? [];
        $gecikme = (int) ($testConfig['gecikme_saniye'] ?? 0);
        if ($gecikme > 0) {
            sleep($gecikme);
        }

        return [
            'basarili'     => true,
            'odeme_formu'  => null,
            'redirect_url' => $callbackUrl . '?token=' . $testToken . '&test=1',
            'token'        => $testToken,
        ];
    }

    /**
     * Test modu: Odeme dogrulama
     *
     * @param string $token Test token
     * @return array Dogrulama yaniti
     */
    private function testOdemeDogrula(string $token): array
    {
        $testConfig = $this->config['test'] ?? [];
        $otomatikOnay = (bool) ($testConfig['otomatik_onay'] ?? true);

        // Token'dan odeme kaydini bul ve gercek tutari dondur
        $islemId = 'TEST_' . $token;
        $odeme = $this->odemeRepository->findByIslemId($islemId);
        $tutar = $odeme ? (float) $odeme['tutar'] : 0;

        if (!$otomatikOnay) {
            return [
                'basarili' => false,
                'islem_id' => $islemId,
                'tutar'    => $tutar,
                'mesaj'    => 'Test modu: Otomatik onay kapali.',
            ];
        }

        return [
            'basarili' => true,
            'islem_id' => $islemId,
            'tutar'    => $tutar,
            'mesaj'    => 'Test odeme basarili',
        ];
    }

    /**
     * Test modu: Iade islemi
     *
     * @param array $odeme Odeme bilgileri
     * @param float $tutar Iade tutari
     * @return array Iade yaniti
     */
    private function testIadeBaslat(array $odeme, float $tutar): array
    {
        return [
            'basarili'    => true,
            'islem_id'    => 'TEST_IADE_' . bin2hex(random_bytes(8)),
            'iade_tutar'  => $tutar,
            'mesaj'       => 'Test iade basarili',
        ];
    }

    // ================================================================
    // IYZICO GATEWAY (cURL ile, SDK olmadan)
    // ================================================================

    /**
     * iyzico: Odeme baslatma (Checkout Form Initialize)
     *
     * @param array $siparis Siparis bilgileri
     * @param array $kalemler Siparis kalemleri
     * @param array $musteri Musteri bilgileri
     * @param string $callbackUrl Callback URL
     * @return array Gateway yaniti
     * @throws \RuntimeException iyzico API hatasi
     */
    private function iyzicoOdemeBaslat(array $siparis, array $kalemler, array $musteri, string $callbackUrl): array
    {
        $iyzicoConfig = $this->config['iyzico'] ?? [];

        if (empty($iyzicoConfig['api_key']) || empty($iyzicoConfig['secret_key'])) {
            throw new \RuntimeException('iyzico API anahtarlari yapilandirilmamis.');
        }

        // Sepet kalemlerini hazirla
        $basketItems = [];
        foreach ($kalemler as $kalem) {
            $basketItems[] = [
                'id'        => (string) ($kalem['urun_id'] ?? $kalem['id'] ?? '0'),
                'name'      => $kalem['urun_adi'] ?? 'Urun',
                'category1' => 'Yiyecek',
                'itemType'  => 'PHYSICAL',
                'price'     => number_format((float) ($kalem['toplam_fiyat'] ?? 0), 2, '.', ''),
            ];
        }

        $data = [
            'locale'          => 'tr',
            'conversationId'  => (string) $siparis['id'],
            'price'           => number_format((float) $siparis['toplam_tutar'], 2, '.', ''),
            'paidPrice'       => number_format((float) $siparis['toplam_tutar'], 2, '.', ''),
            'currency'        => 'TRY',
            'basketId'        => 'SIP_' . $siparis['id'],
            'paymentGroup'    => 'PRODUCT',
            'callbackUrl'     => $callbackUrl,
            'enabledInstallments' => [1],
            'buyer'           => [
                'id'                  => 'MUSTERI_' . ($siparis['oturum_id'] ?? '0'),
                'name'                => $musteri['ad'] ?? 'Misafir',
                'surname'             => $musteri['soyad'] ?? 'Musteri',
                'gsmNumber'           => $musteri['telefon'] ?? '+905000000000',
                'email'               => $musteri['email'] ?? 'misafir@example.com',
                'identityNumber'      => '11111111111',
                'registrationAddress' => $musteri['adres'] ?? 'Adres belirtilmemis',
                'ip'                  => $musteri['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'city'                => 'Istanbul',
                'country'             => 'Turkey',
            ],
            'shippingAddress' => [
                'contactName' => ($musteri['ad'] ?? 'Misafir') . ' ' . ($musteri['soyad'] ?? 'Musteri'),
                'city'        => 'Istanbul',
                'country'     => 'Turkey',
                'address'     => $musteri['adres'] ?? 'Adres belirtilmemis',
            ],
            'billingAddress'  => [
                'contactName' => ($musteri['ad'] ?? 'Misafir') . ' ' . ($musteri['soyad'] ?? 'Musteri'),
                'city'        => 'Istanbul',
                'country'     => 'Turkey',
                'address'     => $musteri['adres'] ?? 'Adres belirtilmemis',
            ],
            'basketItems'     => $basketItems,
        ];

        $yanit = $this->iyzicoApiCagri(
            '/payment/iyzipos/checkoutform/initialize/auth/ecom',
            $data
        );

        if (($yanit['status'] ?? '') !== 'success') {
            throw new \RuntimeException(
                'iyzico odeme baslatma hatasi: ' . ($yanit['errorMessage'] ?? 'Bilinmeyen hata')
            );
        }

        return [
            'basarili'     => true,
            'odeme_formu'  => $yanit['checkoutFormContent'] ?? null,
            'redirect_url' => null,
            'token'        => $yanit['token'] ?? null,
        ];
    }

    /**
     * iyzico bilinen IP araliklari (production whitelist)
     */
    private const IYZICO_IP_PATTERNS = [
        '85.111.51.*',
        '213.226.127.*',
    ];

    /**
     * iyzico callback IP adresini dogrula
     *
     * Production ortaminda iyzico IP whitelist'i kontrol eder.
     * Development ortaminda (localhost / 127.0.0.1) atlaniir.
     *
     * @return bool
     */
    private function iyzicoIpDogrula(): bool
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Development ortaminda IP kontrolu atla
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        if ($serverName === 'localhost' || $remoteIp === '127.0.0.1' || $remoteIp === '::1') {
            return true;
        }

        foreach (self::IYZICO_IP_PATTERNS as $pattern) {
            // Wildcard pattern eslestirme (ornegin: 85.111.51.*)
            $regex = '/^' . str_replace(['.', '*'], ['\\.', '\\d+'], $pattern) . '$/';
            if (preg_match($regex, $remoteIp)) {
                return true;
            }
        }

        $this->logOdemeIslem('iyzico_ip_reddedildi', [
            'remote_ip' => $remoteIp,
        ]);

        return false;
    }

    /**
     * iyzico: Odeme dogrulama (Checkout Form Retrieve)
     *
     * @param string $token iyzico callback token
     * @return array Dogrulama yaniti
     * @throws \RuntimeException iyzico API hatasi veya IP reddedildiyse
     */
    private function iyzicoOdemeDogrula(string $token): array
    {
        // IP whitelist kontrolu
        if (!$this->iyzicoIpDogrula()) {
            throw new \RuntimeException('iyzico callback: IP adresi yetkili degil.');
        }

        $yanit = $this->iyzicoApiCagri(
            '/payment/iyzipos/checkoutform/auth/ecom/detail',
            ['token' => $token]
        );

        $basarili = ($yanit['paymentStatus'] ?? '') === 'SUCCESS'
            && ($yanit['status'] ?? '') === 'success';

        return [
            'basarili' => $basarili,
            'islem_id' => $yanit['paymentId'] ?? '',
            'tutar'    => (float) ($yanit['paidPrice'] ?? 0),
            'mesaj'    => $basarili
                ? 'iyzico odeme basarili'
                : ($yanit['errorMessage'] ?? 'iyzico odeme basarisiz'),
        ];
    }

    /**
     * iyzico: Iade islemi
     *
     * @param array $odeme Odeme bilgileri
     * @param float $tutar Iade tutari
     * @return array Iade yaniti
     * @throws \RuntimeException iyzico API hatasi
     */
    private function iyzicoIadeBaslat(array $odeme, float $tutar): array
    {
        $gatewayYaniti = [];
        if (!empty($odeme['gateway_yaniti'])) {
            $gatewayYaniti = is_string($odeme['gateway_yaniti'])
                ? (json_decode($odeme['gateway_yaniti'], true) ?? [])
                : $odeme['gateway_yaniti'];
        }

        $paymentId = $gatewayYaniti['islem_id']
            ?? $gatewayYaniti['gateway_yanit']['token']
            ?? $odeme['islem_id']
            ?? '';

        $data = [
            'locale'         => 'tr',
            'conversationId' => (string) $odeme['siparis_id'],
            'paymentTransactionId' => $paymentId,
            'price'          => number_format($tutar, 2, '.', ''),
            'currency'       => 'TRY',
        ];

        $yanit = $this->iyzicoApiCagri('/payment/refund', $data);

        $basarili = ($yanit['status'] ?? '') === 'success';

        return [
            'basarili'   => $basarili,
            'islem_id'   => $yanit['paymentId'] ?? '',
            'iade_tutar' => $tutar,
            'mesaj'      => $basarili
                ? 'iyzico iade basarili'
                : ($yanit['errorMessage'] ?? 'iyzico iade basarisiz'),
        ];
    }

    /**
     * iyzico HMAC-SHA256 imza olusturma
     *
     * iyzico API'nin bekledigi Authorization header icin
     * imza hesaplar.
     *
     * @param string $uri API endpoint URI
     * @param string $jsonBody JSON formatinda request body
     * @return string Authorization header degeri
     */
    private function iyzicoImzaOlustur(string $uri, string $jsonBody): string
    {
        $iyzicoConfig = $this->config['iyzico'] ?? [];
        $apiKey = $iyzicoConfig['api_key'] ?? '';
        $secretKey = $iyzicoConfig['secret_key'] ?? '';

        $randomKey = bin2hex(random_bytes(8));

        // iyzico v2 imza algoritmasi
        $hashStr = $randomKey . $uri . $jsonBody;
        $signature = hash_hmac('sha256', $hashStr, $secretKey);

        $authorizationParams = [
            'apiKey:'     . $apiKey,
            'randomKey:'  . $randomKey,
            'signature:'  . $signature,
        ];

        return 'IYZWSv2 ' . base64_encode(implode('&', $authorizationParams));
    }

    /**
     * iyzico API cagrisi (cURL wrapper)
     *
     * @param string $endpoint API endpoint (ornegin: /payment/iyzipos/checkoutform/initialize/auth/ecom)
     * @param array $data Gonderilecek veri
     * @return array API yaniti (decoded JSON)
     * @throws \RuntimeException cURL veya API hatasi
     */
    private function iyzicoApiCagri(string $endpoint, array $data): array
    {
        $iyzicoConfig = $this->config['iyzico'] ?? [];
        $baseUrl = rtrim($iyzicoConfig['base_url'] ?? 'https://sandbox-api.iyzipay.com', '/');
        $url = $baseUrl . $endpoint;

        $jsonBody = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            throw new \RuntimeException('iyzico API istegi JSON encode hatasi.');
        }

        $authorization = $this->iyzicoImzaOlustur($endpoint, $jsonBody);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: ' . $authorization,
                'x-iyzi-rnd: ' . bin2hex(random_bytes(8)),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('iyzico API baglanti hatasi: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \RuntimeException('iyzico API yaniti JSON decode hatasi. HTTP: ' . $httpCode);
        }

        // Hassas veri loglamadan sadece status ve hata bilgisi kaydet
        $this->logOdemeIslem('iyzico_api_yanit', [
            'endpoint'  => $endpoint,
            'http_code' => $httpCode,
            'status'    => $decoded['status'] ?? 'unknown',
        ]);

        return $decoded;
    }

    // ================================================================
    // YARDIMCI METODLAR
    // ================================================================

    /**
     * Musteri bilgisi validasyonu
     *
     * @param array $musteri
     * @return void
     * @throws ValidationException Zorunlu alanlar eksikse
     */
    private function musteriDogrula(array $musteri): void
    {
        $hatalar = [];

        if (empty($musteri['ad'])) {
            $hatalar['ad'] = ['Musteri adi zorunludur.'];
        }

        if (empty($musteri['soyad'])) {
            $hatalar['soyad'] = ['Musteri soyadi zorunludur.'];
        }

        if (empty($musteri['email'])) {
            $hatalar['email'] = ['Email adresi zorunludur.'];
        } elseif (!filter_var($musteri['email'], FILTER_VALIDATE_EMAIL)) {
            $hatalar['email'] = ['Gecerli bir email adresi giriniz.'];
        }

        if (!empty($hatalar)) {
            throw new ValidationException('Musteri bilgileri eksik veya hatali.', $hatalar);
        }
    }

    /**
     * Benzersiz islem ID'si olustur
     *
     * @return string
     */
    private function islemIdOlustur(): string
    {
        return strtoupper($this->gateway) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Token ile odeme kaydi bul
     *
     * Test modunda islem_id "TEST_<token>" formatindadir.
     * iyzico modunda token dogrudan aranir.
     *
     * @param string $token
     * @return array|null
     */
    private function tokenIleOdemeBul(string $token): ?array
    {
        // Test modunda: islem_id = "TEST_<token>"
        $testIslemId = 'TEST_' . $token;
        $odeme = $this->odemeRepository->findByIslemId($testIslemId);
        if ($odeme !== null) {
            return $odeme;
        }

        return null;
    }

    /**
     * Odeme konfigurasyonunu yukle
     *
     * config/odeme.php dosyasindan yukler.
     *
     * @return array
     */
    private function loadConfig(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/odeme.php';

        if (file_exists($configPath)) {
            return require $configPath;
        }

        // Varsayilan config (dosya yoksa)
        return [
            'gateway'      => 'test',
            'iyzico'       => [
                'api_key'    => '',
                'secret_key' => '',
                'base_url'   => 'https://sandbox-api.iyzipay.com',
            ],
            'test'         => [
                'otomatik_onay'  => true,
                'gecikme_saniye' => 0,
            ],
            'callback_url' => 'http://localhost/menu/odeme-callback.php',
            'basari_url'   => 'http://localhost/menu/odeme-sonuc.php',
            'para_birimi'  => 'TRY',
        ];
    }

    /**
     * Odeme islem loglama
     *
     * Hassas veri (kart numarasi, CVV vb.) LOGLANMAZ.
     * Sadece islem akisi takibi icin gerekli bilgiler kaydedilir.
     *
     * @param string $olay Islem tipi (odeme_baslatildi, odeme_basarili, vb.)
     * @param array $baglamaVerisi Ek bilgiler
     * @return void
     */
    private function logOdemeIslem(string $olay, array $baglamaVerisi = []): void
    {
        try {
            if (function_exists('logger')) {
                logger('[Odeme] ' . $olay, $baglamaVerisi, 'info');
            }
        } catch (\Throwable) {
            // Loglama hatasi odeme akisini engellememeli
        }
    }

    /**
     * Aktif gateway'i dondur
     *
     * @return string
     */
    public function getAktifGateway(): string
    {
        return $this->gateway;
    }

    /**
     * Gateway test modunda mi?
     *
     * @return bool
     */
    public function isTestModu(): bool
    {
        return $this->gateway === 'test';
    }
}
