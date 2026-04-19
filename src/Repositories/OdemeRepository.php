<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;

/**
 * Odeme Repository
 *
 * Odeme islemi veritabani islemleri.
 * QR Menu sistemi icin odeme yonetimi.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class OdemeRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'odeme_islemleri';

    /**
     * @var bool Timestamps devre disi — odeme_islemleri tablosunda islem_zamani var
     */
    protected bool $timestamps = false;

    /**
     * @var array Fillable columns (migration ile eslesen)
     */
    protected array $fillable = [
        'siparis_id',
        'gateway',
        'islem_id',
        'tutar',
        'para_birimi',
        'durum',
        'gateway_yaniti',
    ];

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'siparis_id', 'islem_id', 'tutar', 'durum', 'gateway', 'islem_zamani',
    ];

    /**
     * Gecerli odeme durumlari (migration ENUM ile eslesir)
     */
    public const VALID_DURUMLAR = ['baslatildi', 'basarili', 'basarisiz', 'iade'];

    /**
     * Islem ID'sine gore odeme bul
     *
     * @param string $islemId Benzersiz islem kimlik numarasi
     * @return array|null
     */
    public function findByIslemId(string $islemId): ?array
    {
        return $this->findBy('islem_id', $islemId);
    }

    /**
     * Siparis ID'sine gore odemeleri getir
     *
     * @param int $siparisId
     * @return array
     */
    public function findBySiparisId(int $siparisId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE siparis_id = ?
                ORDER BY islem_zamani DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$siparisId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Odeme durumunu guncelle
     *
     * @param int $id
     * @param string $durum Yeni durum
     * @param array|null $gatewayYaniti Gateway'den gelen yanit verisi
     * @return bool
     */
    public function updateDurum(int $id, string $durum, ?array $gatewayYaniti = null): bool
    {
        if (!in_array($durum, self::VALID_DURUMLAR, true)) {
            $durum = 'baslatildi';
        }

        $sql = "UPDATE {$this->table}
                SET durum = ?, gateway_yaniti = ?
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        $gatewayJson = $gatewayYaniti !== null ? json_encode($gatewayYaniti, JSON_UNESCAPED_UNICODE) : null;

        return $stmt->execute([$durum, $gatewayJson, $id]);
    }
}
