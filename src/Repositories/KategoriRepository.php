<?php

declare(strict_types=1);

namespace Pastane\Repositories;

/**
 * Kategori Repository
 *
 * Kategori veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class KategoriRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'kategoriler';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'ad',
        'slug',
        'sira',
    ];

    /**
     * @var bool Timestamps
     */
    protected bool $timestamps = true;

    /**
     * Tüm kategorileri ürün sayısıyla birlikte getir
     *
     * @return array
     */
    public function getAllWithProductCount(): array
    {
        $sql = "SELECT k.*, COUNT(u.id) as urun_sayisi
                FROM {$this->table} k
                LEFT JOIN urunler u ON k.id = u.kategori_id
                GROUP BY k.id
                ORDER BY k.sira ASC, k.ad ASC";

        return $this->raw($sql);
    }

    /**
     * Slug'a göre kategori bul
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Kategorinin ürünü var mı kontrol et
     *
     * @param int $id
     * @return bool
     */
    public function hasProducts(int $id): bool
    {
        $sql = "SELECT COUNT(*) as count FROM urunler WHERE kategori_id = ?";
        $result = $this->raw($sql, [$id]);
        return (int)($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Kategorideki ürün sayısını getir
     *
     * @param int $id
     * @return int
     */
    public function getProductCount(int $id): int
    {
        $sql = "SELECT COUNT(*) as count FROM urunler WHERE kategori_id = ?";
        $result = $this->raw($sql, [$id]);
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * Slug'ın benzersiz olup olmadığını kontrol et
     *
     * @param string $slug
     * @param int|null $exceptId Hariç tutulacak ID (güncelleme için)
     * @return bool Slug mevcut mu
     */
    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE slug = ?";
        $params = [$slug];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $result = $this->raw($sql, $params);
        return !empty($result);
    }

    /**
     * Kategori sırasını güncelle
     *
     * @param array $orders [id => sira]
     * @return void
     */
    public function updateOrder(array $orders): void
    {
        $this->transaction(function () use ($orders) {
            foreach ($orders as $id => $sira) {
                $this->update($id, ['sira' => $sira]);
            }
        });
    }
}
