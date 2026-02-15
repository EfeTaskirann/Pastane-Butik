<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Siparis Repository
 *
 * Sipariş veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class SiparisRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'siparisler';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'tarih',
        'kategori',
        'kisi_sayisi',
        'puan',
        'birim_fiyat',
        'toplam_tutar',
        'odeme_tipi',
        'kanal',
        'ad_soyad',
        'telefon',
        'ozel_istekler',
        'notlar',
        'durum',
        'musteri_kaydedildi',
        'arsivlendi'
    ];

    /**
     * Geçerli sipariş durumları
     */
    public const VALID_STATUSES = ['beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal'];

    /**
     * Belirli tarihteki siparişleri getir (iptal ve arşivlenmiş hariç)
     *
     * @param string $tarih Y-m-d formatında
     * @return array
     */
    public function getByDate(string $tarih): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tarih = ?
                AND durum != 'iptal'
                AND (arsivlendi = 0 OR arsivlendi IS NULL)
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tarih]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tüm siparişleri getir (arsivlenmemişler)
     *
     * @param string|null $durum Belirli durum filtresi
     * @return array
     */
    public function getAllActive(?string $durum = null): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE (arsivlendi = 0 OR arsivlendi IS NULL)
                AND durum != 'iptal'";
        $params = [];

        if ($durum) {
            $sql .= " AND durum = ?";
            $params[] = $durum;
        }

        $sql .= " ORDER BY tarih DESC, created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Takvim verileri için günlük puan toplamlarını getir
     *
     * @param string $baslangic Y-m-d
     * @param string $bitis Y-m-d
     * @return array [tarih => puan]
     */
    public function getCalendarData(string $baslangic, string $bitis): array
    {
        $sql = "SELECT tarih, SUM(COALESCE(puan, 0) * COALESCE(kisi_sayisi, 1)) as toplam_puan
                FROM {$this->table}
                WHERE tarih >= ? AND tarih <= ?
                AND durum NOT IN ('teslim_edildi', 'iptal')
                GROUP BY tarih";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['tarih']] = (int)$row['toplam_puan'];
        }

        return $result;
    }

    /**
     * Günün toplam iş yükünü (puan) hesapla
     *
     * @param string $tarih
     * @param bool $sadeceBekleyenler Sadece teslim edilmemiş siparişler
     * @return int
     */
    public function getDayWorkload(string $tarih, bool $sadeceBekleyenler = true): int
    {
        $sql = "SELECT SUM(COALESCE(puan, 0) * COALESCE(kisi_sayisi, 1)) as toplam
                FROM {$this->table}
                WHERE tarih = ?
                AND durum != 'iptal'
                AND (arsivlendi = 0 OR arsivlendi IS NULL)";

        if ($sadeceBekleyenler) {
            $sql .= " AND durum != 'teslim_edildi'";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tarih]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['toplam'] ?? 0);
    }

    /**
     * Sipariş durumunu güncelle
     *
     * @param int $id
     * @param string $durum
     * @return bool
     */
    public function updateStatus(int $id, string $durum): bool
    {
        if (!in_array($durum, self::VALID_STATUSES)) {
            $durum = 'beklemede';
        }

        $sql = "UPDATE {$this->table} SET durum = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$durum, $id]);
    }

    /**
     * Siparişi arşivle
     *
     * @param int $id
     * @return bool
     */
    public function archive(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET arsivlendi = 1, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$id]);
    }

    /**
     * Müşteri kaydedildi olarak işaretle
     *
     * @param int $id
     * @return bool
     */
    public function markCustomerRecorded(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET musteri_kaydedildi = 1, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$id]);
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
        $sql = "SELECT * FROM {$this->table}
                WHERE tarih >= ? AND tarih <= ?
                AND durum != 'iptal'";

        if ($sadeceTeslimEdilmis) {
            $sql .= " AND durum = 'teslim_edildi'";
        }

        $sql .= " ORDER BY tarih DESC, created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bugünkü siparişleri getir
     *
     * @return array
     */
    public function getToday(): array
    {
        return $this->getByDate(date('Y-m-d'));
    }

    /**
     * Kategoriye göre sipariş sayısı
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getCountByCategory(string $baslangic, string $bitis): array
    {
        $sql = "SELECT kategori, COUNT(*) as adet, SUM(toplam_tutar) as toplam_tutar
                FROM {$this->table}
                WHERE tarih >= ? AND tarih <= ?
                AND durum = 'teslim_edildi'
                GROUP BY kategori
                ORDER BY adet DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Puan ayarlarını getir
     *
     * @return array [kategori => puan]
     */
    public function getPuanAyarlari(): array
    {
        $sql = "SELECT kategori, puan FROM siparis_puan_ayarlari";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['kategori']] = (int)$row['puan'];
        }

        // Varsayılan değerler
        $defaults = ['pasta' => 15, 'cupcake' => 8, 'cheesecake' => 12, 'kurabiye' => 6, 'ozel' => 20];
        return array_merge($defaults, $result);
    }

    /**
     * Kategori fiyatlarını getir
     *
     * @return array [kategori => fiyat]
     */
    public function getKategoriFiyatlari(): array
    {
        $sql = "SELECT kategori, varsayilan_fiyat FROM kategori_fiyatlari";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['kategori']] = (float)$row['varsayilan_fiyat'];
        }

        // Varsayılan değerler
        $defaults = ['pasta' => 450, 'cupcake' => 45, 'cheesecake' => 380, 'kurabiye' => 180, 'ozel' => 0];
        return array_merge($defaults, $result);
    }

    /**
     * Telefon numarasına göre siparişleri getir
     *
     * @param string $telefon
     * @param int $limit
     * @return array
     */
    public function getByPhone(string $telefon, int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE telefon = ?
                ORDER BY tarih DESC, created_at DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$telefon, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
