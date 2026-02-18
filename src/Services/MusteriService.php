<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\MusteriRepository;
use Pastane\Exceptions\ValidationException;

/**
 * Musteri Service
 *
 * Müşteri iş mantığı ve sadakat programı.
 * Her 5 siparişte 1 hediye hak edilir.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class MusteriService extends BaseService
{
    /**
     * Her kaç siparişte hediye verileceği
     */
    public const ORDERS_PER_GIFT = 5;

    /**
     * @var MusteriRepository
     */
    protected MusteriRepository $musteriRepository;

    /**
     * Constructor
     *
     * @param MusteriRepository|null $repository
     */
    public function __construct(?MusteriRepository $repository = null)
    {
        $this->musteriRepository = $repository ?? new MusteriRepository();
        $this->repository = $this->musteriRepository;
    }

    /**
     * Telefon numarasına göre müşteri bul
     *
     * @param string $telefon
     * @return array|null
     */
    public function findByPhone(string $telefon): ?array
    {
        return $this->musteriRepository->findByPhone($telefon);
    }

    /**
     * Telefon numarasına göre bul veya yeni oluştur
     *
     * @param string $telefon
     * @param string|null $isim
     * @param string|null $adres
     * @return array
     */
    public function findOrCreate(string $telefon, ?string $isim = null, ?string $adres = null): array
    {
        $musteri = $this->findByPhone($telefon);

        if ($musteri) {
            return $musteri;
        }

        $id = $this->musteriRepository->create([
            'telefon' => $telefon,
            'isim' => $isim,
            'adres' => $adres,
            'siparis_sayisi' => 0,
            'toplam_harcama' => 0,
            'hediye_hak_edildi' => 0
        ]);

        return $this->musteriRepository->find($id);
    }

    /**
     * Arama ve sıralama ile müşterileri getir
     *
     * @param string|null $arama
     * @param string $siralama
     * @param string $yon
     * @return array
     */
    public function getAllWithSearch(?string $arama = null, string $siralama = 'siparis_sayisi', string $yon = 'DESC'): array
    {
        return $this->musteriRepository->getAllWithSearch($arama, $siralama, $yon);
    }

    /**
     * Toplam istatistikleri getir
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->musteriRepository->getStats();
    }

    /**
     * Teslim edilen sipariş kaydı
     * Müşterinin sipariş sayısını artırır ve hediye kontrolü yapar
     *
     * @param string $telefon Müşteri telefon numarası
     * @param float $tutar Sipariş tutarı
     * @param string|null $isim Müşteri ismi (güncelleme için)
     * @param string|null $adres Müşteri adresi (güncelleme için)
     * @return array ['musteri' => array, 'hediye_kazanildi' => bool]
     */
    public function recordDeliveredOrder(string $telefon, float $tutar = 0, ?string $isim = null, ?string $adres = null): array
    {
        $musteri = $this->findByPhone($telefon);
        $hediyeKazanildi = false;

        if ($musteri) {
            // Mevcut müşteri - sipariş sayısını artır
            $yeniSiparisSayisi = $musteri['siparis_sayisi'] + 1;

            $this->musteriRepository->incrementOrderCount(
                (int)$musteri['id'],
                $tutar,
                $isim,
                $adres
            );

            // Her 5 siparişte hediye
            if ($yeniSiparisSayisi % self::ORDERS_PER_GIFT === 0) {
                $this->musteriRepository->incrementGiftCount((int)$musteri['id']);
                $hediyeKazanildi = true;
            }

            // Güncel müşteri bilgisini al
            $musteri = $this->musteriRepository->find((int)$musteri['id']);
        } else {
            // Yeni müşteri oluştur
            $id = $this->musteriRepository->create([
                'telefon' => $telefon,
                'isim' => $isim,
                'adres' => $adres,
                'siparis_sayisi' => 1,
                'toplam_harcama' => $tutar,
                'son_siparis_tarihi' => date('Y-m-d'),
                'hediye_hak_edildi' => 0
            ]);

            $musteri = $this->musteriRepository->find($id);
        }

        return [
            'musteri' => $musteri,
            'hediye_kazanildi' => $hediyeKazanildi
        ];
    }

    /**
     * Teslim edilen sipariş iptali/geri alımı
     * Müşterinin sipariş sayısını azaltır ve hediye kontrolü yapar
     *
     * @param string $telefon Müşteri telefon numarası
     * @param float $tutar Sipariş tutarı
     * @return array ['musteri' => array|null, 'hediye_geri_alindi' => bool]
     */
    public function reverseDeliveredOrder(string $telefon, float $tutar = 0): array
    {
        $musteri = $this->findByPhone($telefon);
        $hediyeGeriAlindi = false;

        if (!$musteri || $musteri['siparis_sayisi'] <= 0) {
            return [
                'musteri' => $musteri,
                'hediye_geri_alindi' => false
            ];
        }

        $mevcutSiparisSayisi = $musteri['siparis_sayisi'];

        // Eğer mevcut sipariş sayısı 5'in katı ise ve hediye varsa, hediyeyi geri al
        if ($mevcutSiparisSayisi % self::ORDERS_PER_GIFT === 0 && $musteri['hediye_hak_edildi'] > 0) {
            $this->musteriRepository->decrementGiftCount((int)$musteri['id']);
            $hediyeGeriAlindi = true;
        }

        // Sipariş sayısını azalt
        $this->musteriRepository->decrementOrderCount((int)$musteri['id'], $tutar);

        // Güncel müşteri bilgisini al
        $musteri = $this->musteriRepository->find((int)$musteri['id']);

        return [
            'musteri' => $musteri,
            'hediye_geri_alindi' => $hediyeGeriAlindi
        ];
    }

    /**
     * Müşterinin bir sonraki hediyeye kaç sipariş kaldığını hesapla
     *
     * @param array $musteri
     * @return int
     */
    public function getOrdersUntilNextGift(array $musteri): int
    {
        $siparisKalan = $musteri['siparis_sayisi'] % self::ORDERS_PER_GIFT;
        return self::ORDERS_PER_GIFT - $siparisKalan;
    }

    /**
     * Müşterinin hediye için progress yüzdesini hesapla
     *
     * @param array $musteri
     * @return float 0-100 arası
     */
    public function getGiftProgress(array $musteri): float
    {
        $siparisKalan = $musteri['siparis_sayisi'] % self::ORDERS_PER_GIFT;
        return ($siparisKalan / self::ORDERS_PER_GIFT) * 100;
    }

    /**
     * En çok sipariş veren müşterileri getir
     *
     * @param int $limit
     * @return array
     */
    public function getTopCustomers(int $limit = 10): array
    {
        return $this->musteriRepository->getTopCustomers($limit);
    }

    /**
     * Hediye hak eden müşterileri getir
     *
     * @return array
     */
    public function getCustomersEligibleForGift(): array
    {
        return $this->musteriRepository->getCustomersEligibleForGift();
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
            'telefon' => 'required|string|min:10|max:20',
        ]);

        // Telefon benzersiz olmalı
        if (!empty($data['telefon'])) {
            $existing = $this->findByPhone($data['telefon']);
            if ($existing) {
                throw new ValidationException('Bu telefon numarası zaten kayıtlı.', [
                    'telefon' => ['Bu telefon numarası zaten kayıtlı.']
                ]);
            }
        }
    }

    /**
     * Validate before update
     *
     * @param int|string $id
     * @param array $data
     * @throws ValidationException
     */
    protected function validateUpdate(int|string $id, array $data): void
    {
        parent::validateUpdate($id, $data);

        // Telefon değiştiriliyorsa benzersiz olmalı
        if (!empty($data['telefon'])) {
            $existing = $this->findByPhone($data['telefon']);
            if ($existing && $existing['id'] != $id) {
                throw new ValidationException('Bu telefon numarası zaten kayıtlı.', [
                    'telefon' => ['Bu telefon numarası zaten kayıtlı.']
                ]);
            }
        }
    }
}
