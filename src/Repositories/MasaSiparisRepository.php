<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Masa Siparis Repository
 *
 * Masa siparisi veritabani islemleri.
 * QR Menu sistemi icin siparis yonetimi.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class MasaSiparisRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'masa_siparisleri';

    /**
     * @var bool Timestamps devre disi — masa_siparisleri tablosunda siparis_zamani var
     */
    protected bool $timestamps = false;

    /**
     * @var array Fillable columns (migration ile eslesen)
     */
    protected array $fillable = [
        'oturum_id',
        'masa_id',
        'durum',
        'toplam_tutar',
        'odeme_durumu',
        'odeme_yontemi',
        'odeme_referans',
        'siparis_notu',
        'takip_token',
    ];

    /**
     * Takip token regex — 32 karakter hexadecimal.
     *
     * `bin2hex(random_bytes(16))` formatinin tamamiyle eslesir.
     * Public takip URL'lerinde ve api track endpoint'inde zorunlu validasyon.
     */
    public const TAKIP_TOKEN_REGEX = '/^[a-f0-9]{32}$/';

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'oturum_id', 'masa_id', 'durum', 'toplam_tutar',
        'odeme_durumu', 'siparis_zamani',
    ];

    /**
     * Gecerli siparis durumlari (migration ENUM ile eslesir)
     */
    public const VALID_DURUMLAR = ['beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal'];

    /**
     * Gecerli odeme durumlari (migration ENUM ile eslesir)
     */
    public const VALID_ODEME_DURUMLARI = ['odenmedi', 'odendi', 'iade'];

    /**
     * Kalem ekleme icin izin verilen alanlar (SQL injection koruması)
     */
    public const KALEM_FILLABLE = [
        'siparis_id', 'urun_id', 'urun_adi', 'porsiyon',
        'adet', 'birim_fiyat', 'toplam_fiyat', 'ozel_not',
    ];

    /**
     * Yeni sipariş oluştur.
     *
     * BaseRepository::create() uzerine yazar; `takip_token` alani
     * explicit olarak set edilmemisse `bin2hex(random_bytes(16))` ile
     * otomatik uretilir. Unique çakismasi durumunda bir kez daha dener.
     *
     * @param array $data Sipariş verisi
     * @return int|string Last insert ID
     */
    public function create(array $data): int|string
    {
        if (empty($data['takip_token'])) {
            $data['takip_token'] = $this->generateTrackingToken();
        }

        try {
            return parent::create($data);
        } catch (\PDOException $e) {
            // Unique index çakismasi — kolon adimiz `takip_token`
            if (str_contains($e->getMessage(), 'takip_token') && (int)$e->getCode() === 23000) {
                $data['takip_token'] = $this->generateTrackingToken();
                return parent::create($data);
            }
            throw $e;
        }
    }

    /**
     * Kriptografik olarak güvenli takip tokeni uret.
     *
     * @return string 32 karakter hexadecimal (bin2hex(random_bytes(16)))
     */
    public function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Takip tokeni ile siparis getir.
     *
     * Token regex validasyonu (32 hex) basarisiz olursa sorgu çalismaz,
     * direkt null doner. Prepared statement + unique index ile guvenli.
     *
     * @param string $token 32 karakter hex
     * @return array|null Siparis kaydi veya null
     */
    public function findByToken(string $token): ?array
    {
        if (!preg_match(self::TAKIP_TOKEN_REGEX, $token)) {
            return null;
        }

        $sql = "SELECT * FROM {$this->table} WHERE takip_token = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Aktif siparisleri getir (beklemede, onaylandi, hazirlaniyor, hazir)
     *
     * @return array
     */
    public function getAktifSiparisler(): array
    {
        $sql = "SELECT ms.id, ms.oturum_id, ms.masa_id, ms.durum,
                       ms.toplam_tutar, ms.odeme_durumu, ms.siparis_notu,
                       ms.siparis_zamani, ms.hazir_zamani,
                       m.masa_no, CONCAT('Masa ', m.masa_no) AS masa_adi
                FROM {$this->table} ms
                LEFT JOIN masalar m ON ms.masa_id = m.id
                WHERE ms.durum IN ('beklemede', 'onaylandi', 'hazirlaniyor', 'hazir')
                ORDER BY ms.siparis_zamani ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Belirli bir oturuma ait siparisleri getir
     *
     * idx_siparis_oturum indeksinden faydalanir.
     *
     * @param int $oturumId
     * @return array
     */
    public function getSiparisByOturum(int $oturumId): array
    {
        $sql = "SELECT id, oturum_id, masa_id, durum, toplam_tutar,
                       odeme_durumu, odeme_yontemi, siparis_notu,
                       siparis_zamani, hazir_zamani, teslim_zamani
                FROM {$this->table}
                WHERE oturum_id = ?
                ORDER BY siparis_zamani DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$oturumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Siparis durumunu guncelle
     *
     * @param int $id
     * @param string $durum
     * @return bool
     */
    public function updateDurum(int $id, string $durum): bool
    {
        if (!in_array($durum, self::VALID_DURUMLAR, true)) {
            $durum = 'beklemede';
        }

        // Duruma gore zaman kolonlarini da guncelle
        $zamanKolonu = match ($durum) {
            'hazirlaniyor'  => ', hazirlama_baslangic = NOW()',
            'hazir'         => ', hazir_zamani = NOW()',
            'teslim_edildi' => ', teslim_zamani = NOW()',
            default         => '',
        };

        $sql = "UPDATE {$this->table} SET durum = ?{$zamanKolonu} WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$durum, $id]);
    }

    /**
     * Odeme durumunu guncelle
     *
     * @param int $id
     * @param string $durum Odeme durumu
     * @param string|null $yontem Odeme yontemi (nakit, kredi_karti, online vb.)
     * @param string|null $referans Odeme referans numarasi
     * @return bool
     */
    public function updateOdemeDurumu(int $id, string $durum, ?string $yontem = null, ?string $referans = null): bool
    {
        if (!in_array($durum, self::VALID_ODEME_DURUMLARI, true)) {
            $durum = 'odenmedi';
        }

        $sql = "UPDATE {$this->table}
                SET odeme_durumu = ?, odeme_yontemi = ?, odeme_referans = ?
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$durum, $yontem, $referans, $id]);
    }

    /**
     * Mutfak siparislerini getir (onaylandi ve hazirlaniyor durumundakiler)
     *
     * Sadece mutfak ekraninda gereken alanlari secer.
     *
     * @return array
     */
    public function getMutfakSiparisleri(): array
    {
        $sql = "SELECT ms.id, ms.oturum_id, ms.masa_id, ms.durum,
                       ms.toplam_tutar, ms.siparis_notu, ms.siparis_zamani,
                       ms.hazirlama_baslangic,
                       m.masa_no, CONCAT('Masa ', m.masa_no) AS masa_adi
                FROM {$this->table} ms
                LEFT JOIN masalar m ON ms.masa_id = m.id
                WHERE ms.durum IN ('onaylandi', 'hazirlaniyor')
                ORDER BY
                    CASE ms.durum
                        WHEN 'onaylandi' THEN 1
                        WHEN 'hazirlaniyor' THEN 2
                    END,
                    ms.siparis_zamani ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hazir siparisleri getir (garson icin - teslim edilmeyi bekleyenler)
     *
     * @return array
     */
    public function getHazirSiparisler(): array
    {
        $sql = "SELECT ms.id, ms.oturum_id, ms.masa_id, ms.durum,
                       ms.toplam_tutar, ms.odeme_yontemi, ms.odeme_durumu,
                       ms.siparis_notu, ms.siparis_zamani,
                       ms.hazir_zamani,
                       m.masa_no, CONCAT('Masa ', m.masa_no) AS masa_adi
                FROM {$this->table} ms
                LEFT JOIN masalar m ON ms.masa_id = m.id
                WHERE ms.durum = 'hazir'
                ORDER BY ms.hazir_zamani ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Siparis kalemlerini getir
     *
     * @param int $siparisId
     * @return array
     */
    public function getSiparisKalemleri(int $siparisId): array
    {
        $sql = "SELECT sk.id, sk.siparis_id, sk.urun_id, sk.urun_adi,
                       sk.porsiyon, sk.adet, sk.birim_fiyat, sk.toplam_fiyat,
                       sk.ozel_not, u.isim AS urun_adi_guncel, u.gorsel AS urun_gorsel,
                       u.kategori_id
                FROM masa_siparis_kalemleri sk
                LEFT JOIN urunler u ON sk.urun_id = u.id
                WHERE sk.siparis_id = ?
                ORDER BY sk.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siparisId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Birden fazla siparise ait kalemleri toplu getir (N+1 sorgu onleme)
     *
     * Tek sorguda tum kalemleri alir ve siparis_id bazinda gruplar.
     * getMutfakSiparisleri ve getBugununSiparisleri gibi liste metodlarinda
     * her siparis icin ayri sorgu yerine bu metod kullanilmalidir.
     *
     * @param array<int> $siparisIds Siparis ID listesi
     * @return array<int, array> Siparis ID'ye gore gruplenmis kalemler
     */
    public function getKalemlerBySiparisIds(array $siparisIds): array
    {
        if (empty($siparisIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($siparisIds), '?'));

        $sql = "SELECT sk.id, sk.siparis_id, sk.urun_id, sk.urun_adi,
                       sk.porsiyon, sk.adet, sk.birim_fiyat, sk.toplam_fiyat,
                       sk.ozel_not, u.isim AS urun_adi_guncel, u.gorsel AS urun_gorsel,
                       u.kategori_id
                FROM masa_siparis_kalemleri sk
                LEFT JOIN urunler u ON sk.urun_id = u.id
                WHERE sk.siparis_id IN ({$placeholders})
                ORDER BY sk.siparis_id ASC, sk.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($siparisIds));
        $tumKalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Siparis ID bazinda grupla
        $gruplu = [];
        foreach ($tumKalemler as $kalem) {
            $gruplu[(int)$kalem['siparis_id']][] = $kalem;
        }

        return $gruplu;
    }

    /**
     * Oturuma ait tum siparisler kapandi mi?
     *
     * Teslim edildi veya iptal haricinde kalan siparis var mi kontrol eder.
     *
     * @param int $oturumId
     * @return bool
     */
    public function tumSiparislerKapandiMi(int $oturumId): bool
    {
        $sql = "SELECT COUNT(*) as bekleyen
                FROM {$this->table}
                WHERE oturum_id = ? AND durum NOT IN ('teslim_edildi', 'iptal')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$oturumId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['bekleyen'] ?? 0) === 0;
    }

    /**
     * Siparise kalem ekle
     *
     * @param int $siparisId
     * @param array $kalemData Kalem verileri (urun_id, adet, birim_fiyat, toplam_fiyat, notlar vb.)
     * @return int|string Eklenen kalem ID
     */
    public function addKalem(int $siparisId, array $kalemData): int|string
    {
        $kalemData['siparis_id'] = $siparisId;

        // Guvenlik: sadece izin verilen alanlari kabul et
        $kalemData = array_intersect_key($kalemData, array_flip(self::KALEM_FILLABLE));

        $columns = implode(', ', array_keys($kalemData));
        $placeholders = implode(', ', array_fill(0, count($kalemData), '?'));

        $sql = "INSERT INTO masa_siparis_kalemleri ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($kalemData));

        return $this->db->lastInsertId();
    }

    /**
     * Bugunun tum siparislerini getir (filtreleme destekli)
     *
     * DATE() fonksiyonu yerine aralik karsilastirmasi kullanir (sargable).
     * Bu sayede idx_siparis_tarih_durum indeksi kullanilabilir.
     *
     * @param string|null $durumFiltre Belirli bir duruma gore filtrele (null = tumu)
     * @return array
     */
    public function getBugununSiparisleri(?string $durumFiltre = null): array
    {
        $sql = "SELECT ms.id, ms.oturum_id, ms.masa_id, ms.durum,
                       ms.toplam_tutar, ms.odeme_durumu, ms.odeme_yontemi,
                       ms.siparis_notu, ms.siparis_zamani, ms.hazir_zamani,
                       ms.teslim_zamani,
                       m.masa_no, CONCAT('Masa ', m.masa_no) AS masa_adi
                FROM {$this->table} ms
                LEFT JOIN masalar m ON ms.masa_id = m.id
                WHERE ms.siparis_zamani >= CURDATE()
                  AND ms.siparis_zamani < CURDATE() + INTERVAL 1 DAY";

        $params = [];

        if ($durumFiltre !== null && in_array($durumFiltre, self::VALID_DURUMLAR, true)) {
            $sql .= " AND ms.durum = ?";
            $params[] = $durumFiltre;
        }

        $sql .= " ORDER BY ms.siparis_zamani DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bugunun siparis istatistiklerini getir
     *
     * DATE() fonksiyonu yerine aralik karsilastirmasi kullanir (sargable).
     * Bu sayede idx_siparis_tarih_durum indeksi kullanilabilir.
     *
     * @return array ['toplam', 'beklemede', 'onaylandi', 'hazirlaniyor', 'hazir', 'teslim_edildi', 'iptal', 'toplam_ciro']
     */
    public function getBugununIstatistikleri(): array
    {
        $sql = "SELECT
                    COUNT(*) as toplam,
                    SUM(CASE WHEN durum = 'beklemede' THEN 1 ELSE 0 END) as beklemede,
                    SUM(CASE WHEN durum = 'onaylandi' THEN 1 ELSE 0 END) as onaylandi,
                    SUM(CASE WHEN durum = 'hazirlaniyor' THEN 1 ELSE 0 END) as hazirlaniyor,
                    SUM(CASE WHEN durum = 'hazir' THEN 1 ELSE 0 END) as hazir,
                    SUM(CASE WHEN durum = 'teslim_edildi' THEN 1 ELSE 0 END) as teslim_edildi,
                    SUM(CASE WHEN durum = 'iptal' THEN 1 ELSE 0 END) as iptal,
                    COALESCE(SUM(CASE WHEN durum != 'iptal' THEN toplam_tutar ELSE 0 END), 0) as toplam_ciro
                FROM {$this->table}
                WHERE siparis_zamani >= CURDATE()
                  AND siparis_zamani < CURDATE() + INTERVAL 1 DAY";

        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'toplam'        => (int)($result['toplam'] ?? 0),
            'beklemede'     => (int)($result['beklemede'] ?? 0),
            'onaylandi'     => (int)($result['onaylandi'] ?? 0),
            'hazirlaniyor'  => (int)($result['hazirlaniyor'] ?? 0),
            'hazir'         => (int)($result['hazir'] ?? 0),
            'teslim_edildi' => (int)($result['teslim_edildi'] ?? 0),
            'iptal'         => (int)($result['iptal'] ?? 0),
            'toplam_ciro'   => round((float)($result['toplam_ciro'] ?? 0), 2),
        ];
    }

    // =========================================================================
    // Raporlama Metodlari
    // =========================================================================

    /**
     * Masa bazli ciro raporu
     *
     * Her masanin siparis sayisi, toplam ciro, ortalama siparis tutari ve
     * son siparis zamanini hesaplar. Sadece iptal olmayan siparisler dahil edilir.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @return array [{masa_no, siparis_sayisi, toplam_ciro, ortalama_siparis, son_siparis}]
     */
    public function getMasaBazliCiro(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    m.masa_no,
                    COUNT(ms.id) AS siparis_sayisi,
                    COALESCE(SUM(ms.toplam_tutar), 0) AS toplam_ciro,
                    COALESCE(AVG(ms.toplam_tutar), 0) AS ortalama_siparis,
                    MAX(ms.siparis_zamani) AS son_siparis
                FROM {$this->table} ms
                INNER JOIN masalar m ON ms.masa_id = m.id
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                  AND ms.durum != 'iptal'
                GROUP BY m.id, m.masa_no
                ORDER BY toplam_ciro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic . ' 00:00:00', $bitis . ' 23:59:59']);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'masa_no'          => (int)$row['masa_no'],
                'siparis_sayisi'   => (int)$row['siparis_sayisi'],
                'toplam_ciro'      => round((float)$row['toplam_ciro'], 2),
                'ortalama_siparis' => round((float)$row['ortalama_siparis'], 2),
                'son_siparis'      => $row['son_siparis'],
            ];
        }, $sonuclar);
    }

    /**
     * Saatlik yogunluk raporu
     *
     * Hangi saatlerde en cok siparis geldigini gosterir.
     * idx_masa_siparisleri_zaman indeksinden faydalanir.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @return array [{saat, siparis_sayisi, toplam_tutar}]
     */
    public function getSaatlikYogunluk(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    HOUR(ms.siparis_zamani) AS saat,
                    COUNT(ms.id) AS siparis_sayisi,
                    COALESCE(SUM(ms.toplam_tutar), 0) AS toplam_tutar
                FROM {$this->table} ms
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                  AND ms.durum != 'iptal'
                GROUP BY HOUR(ms.siparis_zamani)
                ORDER BY saat ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic . ' 00:00:00', $bitis . ' 23:59:59']);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sonuclari saat bazinda indeksle
        $saatMap = [];
        foreach ($sonuclar as $row) {
            $saatMap[(int)$row['saat']] = $row;
        }

        // Tum saatleri doldur (0-23), bos saatler icin sifir
        $tamSonuc = [];
        for ($s = 0; $s < 24; $s++) {
            if (isset($saatMap[$s])) {
                $tamSonuc[] = [
                    'saat'            => $s,
                    'siparis_sayisi'  => (int)$saatMap[$s]['siparis_sayisi'],
                    'toplam_tutar'    => round((float)$saatMap[$s]['toplam_tutar'], 2),
                ];
            } else {
                $tamSonuc[] = [
                    'saat'            => $s,
                    'siparis_sayisi'  => 0,
                    'toplam_tutar'    => 0.0,
                ];
            }
        }

        return $tamSonuc;
    }

    /**
     * Ortalama siparis suresi raporu (siparis → teslim)
     *
     * siparis_zamani ile teslim_zamani arasindaki farki dakika cinsinden hesaplar.
     * Sadece teslim edilen siparisler dahil edilir.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @return array [{tarih, ortalama_sure_dakika, min_sure, max_sure, siparis_sayisi}]
     */
    public function getOrtalamaSiparisSuresi(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    DATE(ms.siparis_zamani) AS tarih,
                    ROUND(AVG(TIMESTAMPDIFF(MINUTE, ms.siparis_zamani, ms.teslim_zamani)), 1) AS ortalama_sure_dakika,
                    MIN(TIMESTAMPDIFF(MINUTE, ms.siparis_zamani, ms.teslim_zamani)) AS min_sure,
                    MAX(TIMESTAMPDIFF(MINUTE, ms.siparis_zamani, ms.teslim_zamani)) AS max_sure,
                    COUNT(ms.id) AS siparis_sayisi
                FROM {$this->table} ms
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                  AND ms.durum = 'teslim_edildi'
                  AND ms.teslim_zamani IS NOT NULL
                GROUP BY DATE(ms.siparis_zamani)
                ORDER BY tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic . ' 00:00:00', $bitis . ' 23:59:59']);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'tarih'                => $row['tarih'],
                'ortalama_sure_dakika' => (float)($row['ortalama_sure_dakika'] ?? 0),
                'min_sure'             => (int)($row['min_sure'] ?? 0),
                'max_sure'             => (int)($row['max_sure'] ?? 0),
                'siparis_sayisi'       => (int)$row['siparis_sayisi'],
            ];
        }, $sonuclar);
    }

    /**
     * En cok siparis edilen urunler (QR menuden)
     *
     * masa_siparis_kalemleri tablosundan JOIN ile urun bazinda
     * toplam adet, tutar ve siparis sayisini hesaplar.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @param int $limit Dondurulecek urun sayisi (varsayilan: 10)
     * @return array [{urun_adi, toplam_adet, toplam_tutar, siparis_sayisi}]
     */
    public function getEnCokSiparisDen(string $baslangic, string $bitis, int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "SELECT
                    sk.urun_adi,
                    SUM(sk.adet) AS toplam_adet,
                    COALESCE(SUM(sk.toplam_fiyat), 0) AS toplam_tutar,
                    COUNT(DISTINCT sk.siparis_id) AS siparis_sayisi
                FROM masa_siparis_kalemleri sk
                INNER JOIN {$this->table} ms ON sk.siparis_id = ms.id
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                  AND ms.durum != 'iptal'
                GROUP BY sk.urun_adi
                ORDER BY toplam_adet DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $baslangic . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(2, $bitis . ' 23:59:59', PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'urun_adi'        => $row['urun_adi'],
                'toplam_adet'     => (int)$row['toplam_adet'],
                'toplam_tutar'    => round((float)$row['toplam_tutar'], 2),
                'siparis_sayisi'  => (int)$row['siparis_sayisi'],
            ];
        }, $sonuclar);
    }

    /**
     * Gunluk siparis ozeti
     *
     * Her gun icin siparis sayisi, toplam tutar, ortalama tutar,
     * teslim edilen ve iptal edilen siparis sayilarini hesaplar.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @return array [{tarih, siparis_sayisi, toplam_tutar, ortalama_tutar, teslim_edilen, iptal_edilen}]
     */
    public function getGunlukOzet(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    DATE(ms.siparis_zamani) AS tarih,
                    COUNT(ms.id) AS siparis_sayisi,
                    COALESCE(SUM(ms.toplam_tutar), 0) AS toplam_tutar,
                    COALESCE(AVG(ms.toplam_tutar), 0) AS ortalama_tutar,
                    SUM(CASE WHEN ms.durum = 'teslim_edildi' THEN 1 ELSE 0 END) AS teslim_edilen,
                    SUM(CASE WHEN ms.durum = 'iptal' THEN 1 ELSE 0 END) AS iptal_edilen
                FROM {$this->table} ms
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                GROUP BY DATE(ms.siparis_zamani)
                ORDER BY tarih ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic . ' 00:00:00', $bitis . ' 23:59:59']);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tum gunleri doldur (bos gunler icin sifir)
        $gunMap = [];
        foreach ($sonuclar as $row) {
            $gunMap[$row['tarih']] = $row;
        }

        $tamSonuc = [];
        $current = strtotime($baslangic);
        $end = strtotime($bitis);

        while ($current <= $end) {
            $tarih = date('Y-m-d', $current);
            if (isset($gunMap[$tarih])) {
                $tamSonuc[] = [
                    'tarih'          => $tarih,
                    'siparis_sayisi' => (int)$gunMap[$tarih]['siparis_sayisi'],
                    'toplam_tutar'   => round((float)$gunMap[$tarih]['toplam_tutar'], 2),
                    'ortalama_tutar' => round((float)$gunMap[$tarih]['ortalama_tutar'], 2),
                    'teslim_edilen'  => (int)$gunMap[$tarih]['teslim_edilen'],
                    'iptal_edilen'   => (int)$gunMap[$tarih]['iptal_edilen'],
                ];
            } else {
                $tamSonuc[] = [
                    'tarih'          => $tarih,
                    'siparis_sayisi' => 0,
                    'toplam_tutar'   => 0.0,
                    'ortalama_tutar' => 0.0,
                    'teslim_edilen'  => 0,
                    'iptal_edilen'   => 0,
                ];
            }
            $current = strtotime('+1 day', $current);
        }

        return $tamSonuc;
    }

    /**
     * Odeme yontemi dagilimi
     *
     * odeme_islemleri tablosu ile JOIN yaparak her odeme yontemi icin
     * siparis sayisi ve toplam tutari hesaplar.
     * Sadece basarili odemeler dahil edilir.
     *
     * @param string $baslangic Baslangic tarihi (Y-m-d)
     * @param string $bitis Bitis tarihi (Y-m-d)
     * @return array [{yontem, siparis_sayisi, toplam_tutar}]
     */
    public function getOdemeYontemiDagilimi(string $baslangic, string $bitis): array
    {
        $sql = "SELECT
                    COALESCE(oi.gateway, 'belirtilmemis') AS yontem,
                    COUNT(DISTINCT ms.id) AS siparis_sayisi,
                    COALESCE(SUM(oi.tutar), 0) AS toplam_tutar
                FROM odeme_islemleri oi
                INNER JOIN {$this->table} ms ON oi.siparis_id = ms.id
                WHERE ms.siparis_zamani BETWEEN ? AND ?
                  AND oi.durum = 'basarili'
                GROUP BY oi.gateway
                ORDER BY toplam_tutar DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$baslangic . ' 00:00:00', $bitis . ' 23:59:59']);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'yontem'          => $row['yontem'],
                'siparis_sayisi'  => (int)$row['siparis_sayisi'],
                'toplam_tutar'    => round((float)$row['toplam_tutar'], 2),
            ];
        }, $sonuclar);
    }
}
