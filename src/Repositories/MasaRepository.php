<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Masa Repository
 *
 * Masa veritabani islemleri.
 * QR Menu sistemi icin masa yonetimi.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class MasaRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'masalar';

    /**
     * @var bool Timestamps devre disi — masalar tablosunda olusturma_tarihi/guncelleme_tarihi var
     */
    protected bool $timestamps = false;

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'masa_no',
        'qr_token',
        'durum',
        'kapasite',
        'konum',
        'aktif_oturum_id',
    ];

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'masa_no', 'durum', 'kapasite', 'konum', 'olusturma_tarihi', 'guncelleme_tarihi',
    ];

    /**
     * Gecerli masa durumlari (migration ENUM ile eslesir)
     */
    public const VALID_DURUMLAR = ['bos', 'aktif', 'kapali'];

    /**
     * Masa numarasina gore masa bul
     *
     * @param int $masaNo
     * @return array|null
     */
    public function findByMasaNo(int $masaNo): ?array
    {
        return $this->findBy('masa_no', $masaNo);
    }

    /**
     * QR token'a gore masa bul
     *
     * @param string $qrToken
     * @return array|null
     */
    public function findByQrToken(string $qrToken): ?array
    {
        return $this->findBy('qr_token', $qrToken);
    }

    /**
     * Aktif (kullanilabilir) masalari getir
     *
     * @return array
     */
    public function getAktifMasalar(): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE durum = 'aktif'
                ORDER BY masa_no ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bos durumdaki masalari getir
     *
     * @return array
     */
    public function getBosMasalar(): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE durum = 'bos'
                ORDER BY masa_no ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Masa durumunu guncelle
     *
     * @param int $id
     * @param string $durum
     * @return bool
     */
    public function updateDurum(int $id, string $durum): bool
    {
        if (!in_array($durum, self::VALID_DURUMLAR, true)) {
            $durum = 'bos';
        }

        $sql = "UPDATE {$this->table} SET durum = ?, guncelleme_tarihi = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$durum, $id]);
    }

    /**
     * Masanin aktif oturum ID'sini guncelle
     *
     * @param int $id Masa ID
     * @param int|null $oturumId Oturum ID (null ise oturum kapatilir)
     * @return bool
     */
    public function updateAktifOturum(int $id, ?int $oturumId): bool
    {
        $sql = "UPDATE {$this->table} SET aktif_oturum_id = ?, guncelleme_tarihi = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$oturumId, $id]);
    }
}
