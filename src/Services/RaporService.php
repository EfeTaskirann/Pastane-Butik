<?php

declare(strict_types=1);

namespace Pastane\Services;

use PDO;

/**
 * Rapor Service
 *
 * Satış raporları ve analizler için business logic.
 * Repository kullanmaz, doğrudan PDO ile çalışır (cross-table analytics).
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class RaporService
{
    /**
     * @var PDO Database connection
     */
    protected PDO $db;

    /**
     * Constructor
     *
     * @param PDO|null $db
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db()->getPdo();
    }

    /**
     * Aylık satış özeti
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getSatisOzeti(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    COUNT(*) as siparis_sayisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_satis,
                    COALESCE(AVG(toplam_tutar), 0) as ortalama_sepet,
                    COALESCE(SUM(kisi_sayisi), 0) as toplam_kisi
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        $ozet = $stmt->fetch(PDO::FETCH_ASSOC);

        return $ozet ?: [
            'siparis_sayisi' => 0,
            'toplam_satis' => 0,
            'ortalama_sepet' => 0,
            'toplam_kisi' => 0
        ];
    }

    /**
     * Geçen ay ile karşılaştırma (% değişim)
     *
     * @param string $baslangic
     * @param string $bitis
     * @return float
     */
    public function getGecenAyKarsilastirma(string $baslangic, string $bitis): float
    {
        // Bu dönemin satışı
        $sql = "SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        $buDonem = $stmt->fetch(PDO::FETCH_ASSOC);

        // Geçen ayın aynı dönemindeki satış
        $gecenBaslangic = date('Y-m-d', strtotime($baslangic . ' -1 month'));
        $gecenBitis = date('Y-m-d', strtotime($bitis . ' -1 month'));

        $stmt->execute([$gecenBaslangic, $gecenBitis]);
        $gecenDonem = $stmt->fetch(PDO::FETCH_ASSOC);

        $buToplam = (float)($buDonem['toplam'] ?? 0);
        $gecenToplam = (float)($gecenDonem['toplam'] ?? 0);

        if ($gecenToplam == 0) {
            return $buToplam > 0 ? 100.0 : 0.0;
        }

        return round((($buToplam - $gecenToplam) / $gecenToplam) * 100, 1);
    }

    /**
     * Günlük satış verileri (grafik için)
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getGunlukSatislar(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    tarih,
                    COUNT(*) as siparis_sayisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_tutar,
                    COALESCE(SUM(kisi_sayisi), 0) as toplam_kisi
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'
                GROUP BY tarih
                ORDER BY tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tüm günleri doldur (boş günler için 0)
        $gunler = [];
        $current = strtotime($baslangic);
        $end = strtotime($bitis);

        while ($current <= $end) {
            $tarih = date('Y-m-d', $current);
            $gunler[$tarih] = [
                'tarih' => $tarih,
                'siparis_sayisi' => 0,
                'toplam_tutar' => 0,
                'toplam_kisi' => 0
            ];
            $current = strtotime('+1 day', $current);
        }

        foreach ($sonuclar as $row) {
            $gunler[$row['tarih']] = $row;
        }

        return array_values($gunler);
    }

    /**
     * Kategori dağılımı
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getKategoriDagilimi(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    kategori,
                    COUNT(*) as siparis_sayisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_tutar,
                    COALESCE(SUM(kisi_sayisi), 0) as toplam_kisi
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'
                GROUP BY kategori
                ORDER BY toplam_tutar DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * En çok satan kategoriler
     *
     * @param string $baslangic
     * @param string $bitis
     * @param int $limit
     * @return array
     */
    public function getEnCokSatanlar(string $baslangic, string $bitis, int $limit = 5): array
    {
        $sql = "SELECT
                    kategori,
                    COALESCE(SUM(kisi_sayisi), 0) as toplam_kisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_tutar,
                    COUNT(*) as siparis_sayisi
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'
                GROUP BY kategori
                ORDER BY toplam_kisi DESC
                LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Müşteri analizi
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getMusteriAnalizi(string $baslangic, string $bitis): array
    {
        // Toplam benzersiz müşteri (telefon bazlı)
        $sql1 = "SELECT COUNT(DISTINCT telefon) as sayi
                 FROM siparisler
                 WHERE tarih BETWEEN ? AND ?
                   AND durum = 'teslim_edildi'
                   AND telefon IS NOT NULL
                   AND telefon != ''";

        $stmt = $this->db->prepare($sql1);
        $stmt->execute([$baslangic, $bitis]);
        $toplamMusteri = $stmt->fetch(PDO::FETCH_ASSOC);

        // Tekrar eden müşteriler
        $sql2 = "SELECT COUNT(*) as sayi FROM (
                    SELECT telefon
                    FROM siparisler
                    WHERE tarih BETWEEN ? AND ?
                      AND durum = 'teslim_edildi'
                      AND telefon IS NOT NULL
                      AND telefon != ''
                    GROUP BY telefon
                    HAVING COUNT(*) > 1
                ) as tekrar";

        $stmt = $this->db->prepare($sql2);
        $stmt->execute([$baslangic, $bitis]);
        $tekrarEden = $stmt->fetch(PDO::FETCH_ASSOC);

        // Yeni müşteriler (bu dönemde ilk siparişi veren)
        $sql3 = "SELECT COUNT(DISTINCT s.telefon) as sayi
                 FROM siparisler s
                 WHERE s.tarih BETWEEN ? AND ?
                   AND s.durum = 'teslim_edildi'
                   AND s.telefon IS NOT NULL
                   AND s.telefon != ''
                   AND NOT EXISTS (
                       SELECT 1 FROM siparisler s2
                       WHERE s2.telefon = s.telefon
                         AND s2.tarih < ?
                         AND s2.durum = 'teslim_edildi'
                   )";

        $stmt = $this->db->prepare($sql3);
        $stmt->execute([$baslangic, $bitis, $baslangic]);
        $yeniMusteri = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'toplam_musteri' => (int)($toplamMusteri['sayi'] ?? 0),
            'tekrar_eden' => (int)($tekrarEden['sayi'] ?? 0),
            'yeni_musteri' => (int)($yeniMusteri['sayi'] ?? 0)
        ];
    }

    /**
     * Ödeme tipi dağılımı
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getOdemeTipiDagilimi(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    COALESCE(odeme_tipi, 'online') as odeme_tipi,
                    COUNT(*) as siparis_sayisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'
                GROUP BY odeme_tipi";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Kanal dağılımı
     *
     * @param string $baslangic
     * @param string $bitis
     * @return array
     */
    public function getKanalDagilimi(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    COALESCE(kanal, 'site') as kanal,
                    COUNT(*) as siparis_sayisi,
                    COALESCE(SUM(toplam_tutar), 0) as toplam_tutar
                FROM siparisler
                WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'
                GROUP BY kanal";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Haftalık karşılaştırma (son 4 hafta)
     *
     * @return array
     */
    public function getHaftalikKarsilastirma(): array
    {
        $sonuclar = [];

        for ($i = 0; $i < 4; $i++) {
            $haftaBitis = date('Y-m-d', strtotime("-{$i} weeks"));
            $haftaBaslangic = date('Y-m-d', strtotime("-{$i} weeks -6 days"));

            $sql = "SELECT
                        COALESCE(SUM(toplam_tutar), 0) as toplam_tutar,
                        COUNT(*) as siparis_sayisi
                    FROM siparisler
                    WHERE tarih BETWEEN ? AND ? AND durum = 'teslim_edildi'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$haftaBaslangic, $haftaBitis]);
            $hafta = $stmt->fetch(PDO::FETCH_ASSOC);

            $sonuclar[] = [
                'hafta' => $i == 0 ? 'Bu Hafta' : ($i == 1 ? 'Geçen Hafta' : ($i + 1) . '. Hafta Önce'),
                'baslangic' => $haftaBaslangic,
                'bitis' => $haftaBitis,
                'toplam_tutar' => (float)($hafta['toplam_tutar'] ?? 0),
                'siparis_sayisi' => (int)($hafta['siparis_sayisi'] ?? 0)
            ];
        }

        return array_reverse($sonuclar);
    }
}
