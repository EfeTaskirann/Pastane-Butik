<?php

declare(strict_types=1);

namespace Pastane\Repositories;

/**
 * Urun Repository
 *
 * Ürün veritabanı işlemleri.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
class UrunRepository extends BaseRepository
{
    /**
     * @var string Table name
     */
    protected string $table = 'urunler';

    /**
     * @var string Primary key
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [
        'ad',
        'aciklama',
        'fiyat',
        'fiyat_4kisi',
        'fiyat_6kisi',
        'fiyat_8kisi',
        'fiyat_10kisi',
        'kategori_id',
        'gorsel',
        'aktif',
        'sira',
    ];

    /**
     * @var array Sortable columns whitelist
     */
    protected array $sortableColumns = [
        'id', 'ad', 'fiyat', 'kategori_id', 'aktif', 'sira', 'created_at', 'updated_at',
    ];

    /**
     * @var bool Timestamps
     */
    protected bool $timestamps = true;

    /**
     * Get active products
     *
     * @param int|null $kategoriId
     * @param int|null $limit
     * @return array
     */
    public function getActive(?int $kategoriId = null, ?int $limit = null): array
    {
        $sql = "SELECT u.*, k.ad as kategori_adi
                FROM {$this->table} u
                LEFT JOIN kategoriler k ON u.kategori_id = k.id
                WHERE u.aktif = 1";

        $params = [];

        if ($kategoriId !== null) {
            $sql .= " AND u.kategori_id = ?";
            $params[] = $kategoriId;
        }

        $sql .= " ORDER BY u.sira ASC, u.created_at DESC";

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $sql .= " LIMIT {$limit}";
        }

        return $this->raw($sql, $params);
    }

    /**
     * Get all products with category info (admin panel)
     *
     * @return array
     */
    public function getAllWithCategory(): array
    {
        $sql = "SELECT u.*, k.ad as kategori_ad
                FROM {$this->table} u
                LEFT JOIN kategoriler k ON u.kategori_id = k.id
                ORDER BY u.sira ASC, u.created_at DESC";

        return $this->raw($sql);
    }

    /**
     * Get by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Get with category
     *
     * @param int $id
     * @return array|null
     */
    public function findWithCategory(int $id): ?array
    {
        $sql = "SELECT u.*, k.ad as kategori_adi, k.slug as kategori_slug
                FROM {$this->table} u
                LEFT JOIN kategoriler k ON u.kategori_id = k.id
                WHERE u.id = ?";

        $result = $this->raw($sql, [$id]);
        return $result[0] ?? null;
    }

    /**
     * Get featured products
     *
     * @param int $limit
     * @return array
     */
    public function getFeatured(int $limit = 6): array
    {
        // Veritabanında öne çıkan flag'i yok —
        // en düşük sıralı aktif ürünleri "öne çıkan" olarak kabul et
        $sql = "SELECT u.*, k.ad as kategori_adi
                FROM {$this->table} u
                LEFT JOIN kategoriler k ON u.kategori_id = k.id
                WHERE u.aktif = 1
                ORDER BY u.sira ASC, u.created_at DESC
                LIMIT " . max(1, (int)$limit);

        return $this->raw($sql);
    }

    /**
     * Search products
     *
     * @param string $query
     * @param int|null $kategoriId
     * @param int $limit
     * @return array
     */
    public function search(string $query, ?int $kategoriId = null, int $limit = 20): array
    {
        $sql = "SELECT u.*, k.ad as kategori_adi
                FROM {$this->table} u
                LEFT JOIN kategoriler k ON u.kategori_id = k.id
                WHERE u.aktif = 1 AND (u.ad LIKE ? OR u.aciklama LIKE ?)";

        $params = ["%{$query}%", "%{$query}%"];

        if ($kategoriId !== null) {
            $sql .= " AND u.kategori_id = ?";
            $params[] = $kategoriId;
        }

        $sql .= " ORDER BY
                  CASE WHEN u.ad LIKE ? THEN 1 ELSE 2 END,
                  u.sira ASC
                  LIMIT " . max(1, (int)$limit);

        $params[] = "{$query}%";

        return $this->raw($sql, $params);
    }

    /**
     * Get products by category
     *
     * @param int $kategoriId
     * @param bool $activeOnly
     * @return array
     */
    public function getByCategory(int $kategoriId, bool $activeOnly = true): array
    {
        $conditions = ['kategori_id' => $kategoriId];

        if ($activeOnly) {
            $conditions['aktif'] = 1;
        }

        return $this->where($conditions, ['*'], 'sira', 'ASC');
    }

    /**
     * Update product order
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

    /**
     * Toggle active status
     *
     * @param int $id
     * @return bool New status
     */
    public function toggleActive(int $id): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        $newStatus = !$product['aktif'];
        $this->update($id, ['aktif' => $newStatus ? 1 : 0]);

        return $newStatus;
    }

    /**
     * Get price range
     *
     * @param int|null $kategoriId
     * @return array ['min' => float, 'max' => float]
     */
    public function getPriceRange(?int $kategoriId = null): array
    {
        $sql = "SELECT MIN(fiyat) as min_fiyat, MAX(fiyat) as max_fiyat FROM {$this->table} WHERE aktif = 1";
        $params = [];

        if ($kategoriId !== null) {
            $sql .= " AND kategori_id = ?";
            $params[] = $kategoriId;
        }

        $result = $this->raw($sql, $params);
        return [
            'min' => (float)($result[0]['min_fiyat'] ?? 0),
            'max' => (float)($result[0]['max_fiyat'] ?? 0),
        ];
    }
}
