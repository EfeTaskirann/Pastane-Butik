<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Musteri Repository
 *
 * Müşteri veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class MusteriRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'musteriler';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'telefon',
        'isim',
        'adres',
        'siparis_sayisi',
        'toplam_harcama',
        'son_siparis_tarihi',
        'hediye_hak_edildi'
    ];

    /**
     * Telefon numarasına göre müşteri bul
     *
     * @param string $telefon
     * @return array|null
     */
    public function findByPhone(string $telefon): ?array
    {
        return $this->findBy('telefon', $telefon);
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
        // Geçerli sıralama alanları
        $gecerliSiralama = ['siparis_sayisi', 'son_siparis_tarihi', 'isim', 'telefon', 'hediye_hak_edildi', 'toplam_harcama'];
        if (!in_array($siralama, $gecerliSiralama)) {
            $siralama = 'siparis_sayisi';
        }
        $yon = strtoupper($yon) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if ($arama) {
            $sql .= " WHERE (telefon LIKE ? OR isim LIKE ? OR adres LIKE ?)";
            $searchTerm = "%{$arama}%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= " ORDER BY {$siralama} {$yon}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Toplam istatistikleri getir
     *
     * @return array
     */
    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) as toplam_musteri,
                    COALESCE(SUM(siparis_sayisi), 0) as toplam_siparis,
                    COALESCE(SUM(hediye_hak_edildi), 0) as toplam_hediye,
                    COALESCE(SUM(toplam_harcama), 0) as toplam_ciro
                FROM {$this->table}";

        $stmt = $this->db->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'toplam_musteri' => 0,
            'toplam_siparis' => 0,
            'toplam_hediye' => 0,
            'toplam_ciro' => 0
        ];
    }

    /**
     * Sipariş sayısını artır
     *
     * @param int $id
     * @param float $tutar Sipariş tutarı
     * @param string|null $isim
     * @param string|null $adres
     * @return bool
     */
    public function incrementOrderCount(int $id, float $tutar = 0, ?string $isim = null, ?string $adres = null): bool
    {
        $sql = "UPDATE {$this->table} SET
                    siparis_sayisi = siparis_sayisi + 1,
                    toplam_harcama = toplam_harcama + ?,
                    son_siparis_tarihi = CURDATE(),
                    updated_at = NOW()";

        $params = [$tutar];

        if ($isim !== null) {
            $sql .= ", isim = COALESCE(NULLIF(?, ''), isim)";
            $params[] = $isim;
        }

        if ($adres !== null) {
            $sql .= ", adres = COALESCE(NULLIF(?, ''), adres)";
            $params[] = $adres;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Sipariş sayısını azalt
     *
     * @param int $id
     * @param float $tutar Sipariş tutarı
     * @return bool
     */
    public function decrementOrderCount(int $id, float $tutar = 0): bool
    {
        $sql = "UPDATE {$this->table} SET
                    siparis_sayisi = GREATEST(0, siparis_sayisi - 1),
                    toplam_harcama = GREATEST(0, toplam_harcama - ?),
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$tutar, $id]);
    }

    /**
     * Hediye sayısını artır
     *
     * @param int $id
     * @return bool
     */
    public function incrementGiftCount(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET
                    hediye_hak_edildi = hediye_hak_edildi + 1,
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Hediye sayısını azalt
     *
     * @param int $id
     * @return bool
     */
    public function decrementGiftCount(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET
                    hediye_hak_edildi = GREATEST(0, hediye_hak_edildi - 1),
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * En çok sipariş veren müşterileri getir
     *
     * @param int $limit
     * @return array
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE siparis_sayisi > 0
                ORDER BY siparis_sayisi DESC, toplam_harcama DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hediye hak eden müşterileri getir (5'in katına ulaşanlar)
     *
     * @return array
     */
    public function getCustomersEligibleForGift(): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE siparis_sayisi > 0
                AND siparis_sayisi % 5 = 0
                ORDER BY son_siparis_tarihi DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
