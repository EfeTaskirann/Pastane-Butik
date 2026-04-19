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
        'adet',
        'puan',
        'birim_fiyat',
        'toplam_tutar',
        'odeme_tipi',
        'kanal',
        'musteri_adi',
        'telefon',
        'adres',
        'notlar',
        'tamamlandi',
        'musteri_kaydedildi',
        'arsivlendi',
        'takip_token',
    ];

    /**
     * Takip token regex — 32 karakter hexadecimal.
     *
     * `bin2hex(random_bytes(16))` tam olarak bu formati uretir.
     * Validation public endpoint'lerde bypass denemelerine karsi zorunlu.
     */
    public const TAKIP_TOKEN_REGEX = '/^[a-f0-9]{32}$/';

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'tarih', 'tamamlandi', 'toplam_tutar', 'musteri_adi', 'telefon',
        'kategori', 'kanal', 'odeme_tipi', 'created_at',
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
                WHERE (arsivlendi = 0 OR arsivlendi IS NULL)";
        $params = [];

        if ($durum === 'tamamlandi') {
            $sql .= " AND tamamlandi = 1";
        } elseif ($durum === 'beklemede') {
            $sql .= " AND tamamlandi = 0";
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
        $sql = "SELECT tarih, SUM(COALESCE(puan, 0) * COALESCE(adet, 1)) as toplam_puan
                FROM {$this->table}
                WHERE tarih >= ? AND tarih <= ?
                AND tamamlandi = 0
                AND (arsivlendi = 0 OR arsivlendi IS NULL)
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
        $sql = "SELECT SUM(COALESCE(puan, 0) * COALESCE(adet, 1)) as toplam
                FROM {$this->table}
                WHERE tarih = ?
                AND (arsivlendi = 0 OR arsivlendi IS NULL)";

        if ($sadeceBekleyenler) {
            $sql .= " AND tamamlandi = 0";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tarih]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['toplam'] ?? 0);
    }

    /**
     * Sipariş tamamlanma bayrağını güncelle
     *
     * NOT: `siparisler` tablosunda ENUM bazlı `durum` kolonu YOKTUR;
     * yalnızca `tamamlandi` (TINYINT 0/1) vardır. Bu metot o bayrağı
     * günceller. Çoklu durumlu (onaylandi/hazirlaniyor/teslim_edildi...)
     * akış için {@see \Pastane\Repositories\MasaSiparisRepository::updateDurum()}
     * kullanın.
     *
     * @param int  $id           Sipariş ID
     * @param bool $tamamlandi   true → tamamlandı, false → bekliyor
     * @return bool              UPDATE başarılıysa true
     */
    public function updateStatus(int $id, bool $tamamlandi): bool
    {
        $sql = "UPDATE {$this->table} SET tamamlandi = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$tamamlandi ? 1 : 0, $id]);
    }

    /**
     * Siparişi arşivle
     *
     * @param int $id
     * @return bool
     */
    public function archive(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET arsivlendi = 1 WHERE id = ?";
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
        $sql = "UPDATE {$this->table} SET musteri_kaydedildi = 1 WHERE id = ?";
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
                AND (arsivlendi = 0 OR arsivlendi IS NULL)";

        if ($sadeceTeslimEdilmis) {
            $sql .= " AND tamamlandi = 1";
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
                AND tamamlandi = 1
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

    /**
     * Yeni sipariş oluştur.
     *
     * BaseRepository::create() metodunun üzerine yazar; `takip_token` alani
     * explicit olarak set edilmemisse otomatik üretilir (`bin2hex(random_bytes(16))`).
     * Token unique constraint'e carparsa (astronomik olasilikla) bir kez daha dener.
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
            // Unique index çakışması — kolon adımız `takip_token`
            if (str_contains($e->getMessage(), 'takip_token') && (int)$e->getCode() === 23000) {
                $data['takip_token'] = $this->generateTrackingToken();
                return parent::create($data);
            }
            throw $e;
        }
    }

    /**
     * Kriptografik olarak güvenli takip tokeni üret.
     *
     * 16 byte random → 32 karakter hex. Enumeration, brute-force ve çakışma
     * riskini ihmal edilebilir kilar (2^128 alan).
     *
     * @return string 32 karakter hexadecimal
     */
    public function generateTrackingToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Takip tokeni ile sipariş getir.
     *
     * Token format validasyonu (32 hex karakter) basarisiz olursa
     * sorgu çalistirilmadan null doner (zaman saldırısı fail-safe).
     * Prepared statement ile SQL injection korumasi.
     *
     * @param string $token 32 karakter hex takip tokeni
     * @return array|null Sipariş kaydi veya null
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
}
