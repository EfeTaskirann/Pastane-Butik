<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\SiparisRepository;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

/**
 * Siparis Service
 *
 * Sipariş iş mantığı ve MusteriService entegrasyonu.
 * Durum değişikliklerinde sadakat programı otomatik güncellenir.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class SiparisService extends BaseService
{
    /**
     * Geçerli sipariş durumları
     */
    public const STATUSES = [
        'beklemede' => 'Beklemede',
        'onaylandi' => 'Onaylandı',
        'hazirlaniyor' => 'Hazırlanıyor',
        'teslim_edildi' => 'Teslim Edildi',
        'iptal' => 'İptal'
    ];

    /**
     * Geçerli kategoriler
     */
    public const CATEGORIES = ['pasta', 'cupcake', 'cheesecake', 'kurabiye', 'ozel'];

    /**
     * İş yükü eşik değerleri (puan)
     */
    public const WORKLOAD_BOS = 10;
    public const WORKLOAD_UYGUN = 40;
    public const WORKLOAD_YOGUN = 80;

    /**
     * Arama için minimum karakter sayısı
     */
    public const MIN_SEARCH_LENGTH = 2;

    /**
     * @var SiparisRepository
     */
    protected SiparisRepository $siparisRepository;

    /**
     * @var MusteriService
     */
    protected MusteriService $musteriService;

    /**
     * Constructor
     *
     * @param SiparisRepository|null $repository
     * @param MusteriService|null $musteriService
     */
    public function __construct(?SiparisRepository $repository = null, ?MusteriService $musteriService = null)
    {
        $this->siparisRepository = $repository ?? new SiparisRepository();
        $this->musteriService = $musteriService ?? musteri_service();
        $this->repository = $this->siparisRepository;
    }

    /**
     * Yeni sipariş oluştur
     *
     * @param array $data
     * @return array Oluşturulan sipariş
     */
    public function create(array $data): array
    {
        $this->validateCreate($data);

        // Puan ve fiyat hesaplama
        $puanAyarlari = $this->siparisRepository->getPuanAyarlari();
        $kategoriFiyatlari = $this->siparisRepository->getKategoriFiyatlari();

        $kategori = $data['kategori'];
        $adet = (int)($data['kisi_sayisi'] ?? $data['adet'] ?? 1);

        // Özel sipariş için puan manuel girilir
        if ($kategori === 'ozel') {
            $puan = (int)($data['ozel_puan'] ?? $data['puan'] ?? $puanAyarlari['ozel']);
        } else {
            $puan = $data['puan'] ?? $puanAyarlari[$kategori] ?? 10;
        }

        // Fiyat hesaplama
        $birimFiyat = (float)($data['birim_fiyat'] ?? 0);
        if ($birimFiyat <= 0 && $kategori !== 'ozel') {
            $birimFiyat = $kategoriFiyatlari[$kategori] ?? 0;
        }
        $toplamTutar = $birimFiyat * $adet;

        // Veriyi hazırla
        $orderData = [
            'tarih' => $data['tarih'],
            'kategori' => $kategori,
            'adet' => $adet,
            'puan' => $puan,
            'birim_fiyat' => $birimFiyat,
            'toplam_tutar' => $toplamTutar,
            'odeme_tipi' => $data['odeme_tipi'] ?? 'online',
            'kanal' => $data['kanal'] ?? 'site',
            'musteri_adi' => $data['musteri_adi'] ?? $data['ad_soyad'] ?? null,
            'telefon' => trim($data['telefon'] ?? ''),
            'adres' => $data['adres'] ?? $data['ozel_istekler'] ?? null,
            'notlar' => $data['notlar'] ?? null,
            'tamamlandi' => 0
        ];

        $id = $this->siparisRepository->create($orderData);
        $siparis = $this->siparisRepository->find($id);

        // Prometheus counter (DB değil — monitoring, sessiz fail)
        try {
            if (class_exists('\\Metrics', false) || class_exists('Metrics', false)) {
                \Metrics::inc('pastane_orders_total', [
                    'durum' => $siparis['tamamlandi'] ?? 0 ? 'tamamlandi' : 'beklemede',
                ]);
            }
        } catch (\Throwable) {
            // monitoring kaydı ana akışı engellememeli
        }

        // Email bildirim (sessizce fail etsin, sipariş akışını engellemesin)
        if ($siparis !== null) {
            $this->siparisOnayEmailiGonder($siparis, $data);
            $this->siparisOnaySmsGonder($siparis);
        }

        return $siparis;
    }

    /**
     * Sipariş onay SMS'i gönder (hook)
     *
     * Müşteri telefonu varsa `siparis-onay` template'i ile SMS yollar.
     * Herhangi bir hata sipariş akışını engellemez (try/catch ile sessizce yutulur).
     *
     * @param array $siparis Oluşturulan sipariş
     * @return void
     */
    protected function siparisOnaySmsGonder(array $siparis): void
    {
        try {
            $telefon = trim((string) ($siparis['telefon'] ?? ''));
            if ($telefon === '') {
                return;
            }

            if (!class_exists('SmsService')) {
                return;
            }

            $smsService = new \SmsService();
            $smsService->sendTemplate($telefon, 'siparis-onay', [
                'musteri_adi'    => $siparis['musteri_adi'] ?? 'Degerli Musterimiz',
                'siparis_no'     => (string) ($siparis['id'] ?? ''),
                'siparis_tarihi' => date('d.m.Y', strtotime((string) ($siparis['tarih'] ?? 'now'))),
                'toplam_tutar'   => number_format((float) ($siparis['toplam_tutar'] ?? 0), 2, ',', '.') . ' TL',
                'site_adi'       => function_exists('env') ? (string) env('APP_NAME', 'Tatli Dusler') : 'Tatli Dusler',
            ]);
        } catch (\Throwable) {
            // SMS hatası sipariş akışını bloklamasın
        }
    }

    /**
     * Sipariş onay email'i gönder (hook)
     *
     * Sipariş oluşturulduğunda müşteriye HTML email gönderir.
     * Herhangi bir hata sipariş akışını engellemez (try/catch ile sessizce yutulur).
     *
     * @param array $siparis Oluşturulan sipariş
     * @param array $input Orijinal input (email alanı için)
     * @return void
     */
    protected function siparisOnayEmailiGonder(array $siparis, array $input): void
    {
        try {
            // Email adresi — önce input'tan, yoksa müşteri kaydından alınabilir
            $aliciEmail = $input['email'] ?? $input['musteri_email'] ?? null;
            if (empty($aliciEmail) || !filter_var($aliciEmail, FILTER_VALIDATE_EMAIL)) {
                return; // Email yoksa sessizce geç
            }

            if (!class_exists('EmailService')) {
                return;
            }

            $emailService = new \EmailService();

            // E-mail HTML body: inline style ZORUNLU — Gmail/Outlook gibi client'lar
            // external stylesheet referanslarını silip atar, inline'ı korur.
            // Bu yüzden `class="u-*"` kullanılmaz; rendering için inline style gerekir.
            $urunListesi = '<tr><td style="padding:10px 12px; border-bottom:1px solid #f3cfc6;">'
                . htmlspecialchars(ucfirst((string)($siparis['kategori'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</td><td align="center" style="padding:10px 12px; border-bottom:1px solid #f3cfc6;">'
                . (int)($siparis['adet'] ?? 1)
                . '</td><td align="right" style="padding:10px 12px; border-bottom:1px solid #f3cfc6;">'
                . number_format((float)($siparis['toplam_tutar'] ?? 0), 2, ',', '.') . ' ₺'
                . '</td></tr>';

            $data = [
                'musteri_adi'    => $siparis['musteri_adi'] ?? 'Değerli Müşterimiz',
                'siparis_no'     => $siparis['id'] ?? '',
                'siparis_tarihi' => date('d.m.Y H:i', strtotime((string)($siparis['tarih'] ?? 'now'))),
                'toplam_tutar'   => number_format((float)($siparis['toplam_tutar'] ?? 0), 2, ',', '.') . ' ₺',
                'urun_listesi'   => $urunListesi,
                'adres'          => $siparis['adres'] ?? '-',
                'telefon'        => $siparis['telefon'] ?? '-',
                'site_adi'       => function_exists('env') ? (string) env('APP_NAME', 'Tatlı Düşler') : 'Tatlı Düşler',
                'yil'            => date('Y'),
            ];

            $emailService->send(
                $aliciEmail,
                'Siparişiniz Alındı #' . ($siparis['id'] ?? ''),
                'siparis-onay',
                $data
            );
        } catch (\Throwable) {
            // Email hatası sipariş akışını bloklamasın
        }
    }

    /**
     * Sipariş durumunu değiştir
     * Sadakat programı entegrasyonu ile
     *
     * @param int $id Sipariş ID
     * @param string $yeniDurum Yeni durum
     * @return array ['siparis' => array, 'mesaj' => string, 'hediye_kazanildi' => bool]
     */
    public function updateStatus(int $id, string $yeniDurum): array
    {
        // Geçerli durum kontrolü
        if (!array_key_exists($yeniDurum, self::STATUSES)) {
            $yeniDurum = 'beklemede';
        }

        // Siparişi al
        $siparis = $this->siparisRepository->findOrFail($id);
        $eskiDurum = ($siparis['tamamlandi'] ?? 0) ? 'teslim_edildi' : 'beklemede';

        // Durum zaten aynıysa işlem yapma
        if ($eskiDurum === $yeniDurum) {
            return [
                'siparis' => $siparis,
                'mesaj' => 'Sipariş durumu zaten ' . self::STATUSES[$yeniDurum],
                'hediye_kazanildi' => false,
                'hediye_geri_alindi' => false
            ];
        }

        // Durumu güncelle
        $this->siparisRepository->updateStatus($id, $yeniDurum === 'teslim_edildi');

        $hediyeKazanildi = false;
        $hediyeGeriAlindi = false;
        $mesaj = self::STATUSES[$yeniDurum] . ' olarak işaretlendi.';

        // Müşteri sadakat programı entegrasyonu
        $telefon = trim($siparis['telefon'] ?? '');

        if (!empty($telefon)) {
            // Teslim edildi'ye geçiş (sipariş tamamlandı)
            if ($yeniDurum === 'teslim_edildi' && $eskiDurum !== 'teslim_edildi') {
                $result = $this->musteriService->recordDeliveredOrder(
                    $telefon,
                    (float)($siparis['toplam_tutar'] ?? 0),
                    $siparis['musteri_adi'] ?? null,
                    $siparis['adres'] ?? null
                );

                // Müşteri kaydedildi olarak işaretle
                $this->siparisRepository->markCustomerRecorded($id);

                $hediyeKazanildi = $result['hediye_kazanildi'];

                if ($hediyeKazanildi) {
                    $mesaj = 'Sipariş teslim edildi! 🎉 Bu müşteri ' . $result['musteri']['siparis_sayisi'] . '. siparişini tamamladı ve HEDİYE kazandı!';
                } else {
                    $mesaj = 'Sipariş teslim edildi. Müşterinin toplam sipariş sayısı: ' . $result['musteri']['siparis_sayisi'];
                }
            }
            // Teslim edildi'den başka duruma geçiş (geri alma)
            elseif ($eskiDurum === 'teslim_edildi' && $yeniDurum !== 'teslim_edildi') {
                $result = $this->musteriService->reverseDeliveredOrder(
                    $telefon,
                    (float)($siparis['toplam_tutar'] ?? 0)
                );

                $hediyeGeriAlindi = $result['hediye_geri_alindi'];

                if ($hediyeGeriAlindi) {
                    $mesaj = 'Sipariş durumu güncellendi. Müşteri sipariş sayısı ve hediye durumu düzeltildi.';
                } else {
                    $mesaj = 'Sipariş durumu güncellendi. Müşteri sipariş sayısı düzeltildi.';
                }
            }
        }

        // Güncel siparişi al
        $siparis = $this->siparisRepository->find($id);

        return [
            'siparis' => $siparis,
            'mesaj' => $mesaj,
            'hediye_kazanildi' => $hediyeKazanildi,
            'hediye_geri_alindi' => $hediyeGeriAlindi
        ];
    }

    /**
     * Siparişi sil veya arşivle
     * Teslim edilmiş siparişler arşivlenir, diğerleri silinir
     *
     * @param int|string $id
     * @return array ['silindi' => bool, 'arsivlendi' => bool, 'mesaj' => string]
     */
    public function deleteOrArchive(int|string $id): array
    {
        $siparis = $this->siparisRepository->findOrFail((int)$id);

        if ($siparis['tamamlandi'] ?? false) {
            // Tamamlanmış sipariş - arşivle (raporlarda kalır)
            $this->siparisRepository->archive((int)$id);

            return [
                'silindi' => false,
                'arsivlendi' => true,
                'mesaj' => 'Sipariş arşivlendi. Takvimde görünmeyecek ama satış raporlarında kalacak.'
            ];
        } else {
            // Tamamlanmamış sipariş - tamamen sil
            $this->siparisRepository->delete((int)$id);

            return [
                'silindi' => true,
                'arsivlendi' => false,
                'mesaj' => 'Sipariş silindi.'
            ];
        }
    }

    /**
     * Belirli tarihteki siparişleri getir
     *
     * @param string $tarih
     * @return array
     */
    public function getByDate(string $tarih): array
    {
        return $this->siparisRepository->getByDate($tarih);
    }

    /**
     * Takvim verileri için günlük puan toplamlarını getir
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array [tarih => puan]
     */
    public function getCalendarData(string $baslangic, string $bitis): array
    {
        return $this->siparisRepository->getCalendarData($baslangic, $bitis);
    }

    /**
     * Günün toplam iş yükünü hesapla
     *
     * @param string $tarih
     * @param bool $sadeceBekleyenler
     * @return int
     */
    public function getDayWorkload(string $tarih, bool $sadeceBekleyenler = true): int
    {
        return $this->siparisRepository->getDayWorkload($tarih, $sadeceBekleyenler);
    }

    /**
     * Yoğunluk kategorisini belirle
     *
     * @param int $puan
     * @return array ['durum' => string, 'label' => string, 'renk' => string]
     */
    public function getWorkloadCategory(int $puan): array
    {
        if ($puan <= self::WORKLOAD_BOS) {
            return ['durum' => 'bos', 'label' => 'Boş', 'renk' => '#B8D4B8'];
        }
        if ($puan <= self::WORKLOAD_UYGUN) {
            return ['durum' => 'uygun', 'label' => 'Uygun', 'renk' => '#B8D4E8'];
        }
        if ($puan <= self::WORKLOAD_YOGUN) {
            return ['durum' => 'yogun', 'label' => 'Yoğun', 'renk' => '#F5D4B0'];
        }

        return ['durum' => 'dolu', 'label' => 'Dolu', 'renk' => '#E8C4C4'];
    }

    /**
     * Bugünkü siparişleri getir
     *
     * @return array
     */
    public function getToday(): array
    {
        return $this->siparisRepository->getToday();
    }

    /**
     * Tarih aralığındaki siparişleri getir
     *
     * @param string $baslangic
     * @param string $bitis
     * @param bool $sadeceTeslimEdilmis
     * @return array
     */
    public function getByDateRange(string $baslangic, string $bitis, bool $sadeceTeslimEdilmis = false): array
    {
        return $this->siparisRepository->getByDateRange($baslangic, $bitis, $sadeceTeslimEdilmis);
    }

    /**
     * Puan ayarlarını getir
     *
     * @return array
     */
    public function getPuanAyarlari(): array
    {
        return $this->siparisRepository->getPuanAyarlari();
    }

    /**
     * Kategori fiyatlarını getir
     *
     * @return array
     */
    public function getKategoriFiyatlari(): array
    {
        return $this->siparisRepository->getKategoriFiyatlari();
    }

    /**
     * Telefon numarasına göre sipariş geçmişi
     *
     * @param string $telefon
     * @param int $limit
     * @return array
     */
    public function getCustomerOrderHistory(string $telefon, int $limit = 10): array
    {
        return $this->siparisRepository->getByPhone($telefon, $limit);
    }

    /**
     * Validate before create
     *
     * @param array $data
     * @throws ValidationException
     */
    protected function validateCreate(array $data): void
    {
        $this->validate($data, [
            'tarih' => 'required',
            'kategori' => 'required',
        ]);

        // Kategori kontrolü
        if (!in_array($data['kategori'], self::CATEGORIES)) {
            throw new ValidationException('Geçersiz kategori.', [
                'kategori' => ['Geçersiz kategori seçildi.']
            ]);
        }

        // Tarih formatı kontrolü
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tarih'])) {
            throw new ValidationException('Geçersiz tarih formatı.', [
                'tarih' => ['Tarih YYYY-MM-DD formatında olmalıdır.']
            ]);
        }
    }
}
