<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\UrunRepository;
use Pastane\Exceptions\ValidationException;

/**
 * Urun Service
 *
 * Ürün business logic.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class UrunService extends BaseService
{
    /**
     * @var UrunRepository
     */
    protected UrunRepository $urunRepository;

    /**
     * Constructor
     *
     * @param UrunRepository|null $repository
     */
    public function __construct(?UrunRepository $repository = null)
    {
        $this->urunRepository = $repository ?? new UrunRepository();
        $this->repository = $this->urunRepository;
    }

    /**
     * Get active products
     *
     * @param int|null $kategoriId
     * @param int|null $limit
     * @return array
     */
    public function getActive(?int $kategoriId = null, ?int $limit = null): array
    {
        return $this->urunRepository->getActive($kategoriId, $limit);
    }

    /**
     * Get featured products
     *
     * @param int $limit
     * @return array
     */
    public function getFeatured(int $limit = 6): array
    {
        return $this->urunRepository->getFeatured($limit);
    }

    /**
     * Get product by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->urunRepository->findBySlug($slug);
    }

    /**
     * Get product with category info
     *
     * @param int $id
     * @return array|null
     */
    public function findWithCategory(int $id): ?array
    {
        return $this->urunRepository->findWithCategory($id);
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
        if (strlen($query) < 2) {
            return [];
        }

        return $this->urunRepository->search($query, $kategoriId, $limit);
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
        return $this->urunRepository->getByCategory($kategoriId, $activeOnly);
    }

    /**
     * Create product
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        // Generate slug if not provided
        if (empty($data['slug']) && !empty($data['ad'])) {
            $data['slug'] = $this->generateSlug($data['ad']);
        }

        // Set default values
        $data['aktif'] = $data['aktif'] ?? 1;
        $data['sira'] = $data['sira'] ?? 0;

        return parent::create($data);
    }

    /**
     * Update product
     *
     * @param int|string $id
     * @param array $data
     * @return array
     */
    public function update(int|string $id, array $data): array
    {
        // Regenerate slug if name changed
        if (!empty($data['ad']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['ad'], (int)$id);
        }

        return parent::update($id, $data);
    }

    /**
     * Toggle product active status
     *
     * @param int $id
     * @return bool New status
     */
    public function toggleActive(int $id): bool
    {
        return $this->urunRepository->toggleActive($id);
    }

    /**
     * Update product order
     *
     * @param array $orders [id => sira]
     * @return void
     */
    public function updateOrder(array $orders): void
    {
        $this->urunRepository->updateOrder($orders);
    }

    /**
     * Get price range
     *
     * @param int|null $kategoriId
     * @return array
     */
    public function getPriceRange(?int $kategoriId = null): array
    {
        return $this->urunRepository->getPriceRange($kategoriId);
    }

    /**
     * Generate unique slug
     *
     * @param string $name
     * @param int|null $exceptId
     * @return string
     */
    protected function generateSlug(string $name, ?int $exceptId = null): string
    {
        $slug = $this->slugify($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $exceptId)) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Convert string to slug
     * helpers.php str_slug() fonksiyonunu kullanır — tek bir kaynak.
     *
     * @param string $text
     * @return string
     */
    protected function slugify(string $text): string
    {
        return str_slug($text);
    }

    /**
     * Check if slug exists
     *
     * @param string $slug
     * @param int|null $exceptId
     * @return bool
     */
    protected function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $existing = $this->urunRepository->findBySlug($slug);

        if (!$existing) {
            return false;
        }

        if ($exceptId && $existing['id'] == $exceptId) {
            return false;
        }

        return true;
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
            'ad' => 'required|string|min:2|max:255',
            'fiyat' => 'required|numeric',
            'kategori_id' => 'required|integer',
        ]);
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

        $rules = [];

        if (isset($data['ad'])) {
            $rules['ad'] = 'string|min:2|max:255';
        }

        if (isset($data['fiyat'])) {
            $rules['fiyat'] = 'numeric';
        }

        if (!empty($rules)) {
            $this->validate($data, $rules);
        }
    }
}
