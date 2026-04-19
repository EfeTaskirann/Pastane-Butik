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
     * Arama icin minimum karakter sayisi
     */
    private const MIN_SEARCH_LENGTH = 2;

    /**
     * Cache TTL sabitleri (saniye)
     */
    private const CACHE_TTL_CAFE_MENU = 300;
    private const CACHE_TTL_ACTIVE    = 300;  // aktif ürün listesi (menü)
    private const CACHE_TTL_FEATURED  = 600;  // öne çıkan ürünler

    /**
     * Cache key sabitleri
     */
    private const CACHE_KEY_CAFE_MENU = 'cafe_menu_urunleri';
    private const CACHE_KEY_ACTIVE_ALL = 'urunler:aktif:all';
    private const CACHE_KEY_FEATURED   = 'urunler:one-cikan';

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
     * Get all products with category info (admin panel)
     *
     * @return array
     */
    public function getAllWithCategory(): array
    {
        return $this->urunRepository->getAllWithCategory();
    }

    /**
     * Get active products
     *
     * Hot path: menü listesi — filtresiz (kategori_id=null, limit=null) çağrı
     * 5 dk cache'lenir. Parametreli çağrılar cache edilmez (DB'ye direkt).
     *
     * @param int|null $kategoriId
     * @param int|null $limit
     * @return array
     */
    public function getActive(?int $kategoriId = null, ?int $limit = null): array
    {
        // Sadece filtresiz varsayılan sorguyu cache'le (parametrik cache key şişkinliğini önler)
        if ($kategoriId === null && $limit === null) {
            return $this->cacheRemember(
                self::CACHE_KEY_ACTIVE_ALL,
                fn () => $this->urunRepository->getActive(null, null),
                self::CACHE_TTL_ACTIVE
            );
        }

        return $this->urunRepository->getActive($kategoriId, $limit);
    }

    /**
     * Get featured products
     *
     * Hot path: homepage için — varsayılan limit (6) cache'lenir.
     *
     * @param int $limit
     * @return array
     */
    public function getFeatured(int $limit = 6): array
    {
        if ($limit === 6) {
            return $this->cacheRemember(
                self::CACHE_KEY_FEATURED,
                fn () => $this->urunRepository->getFeatured(6),
                self::CACHE_TTL_FEATURED
            );
        }

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
        if (strlen($query) < self::MIN_SEARCH_LENGTH) {
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
     * Cafe menusu urunlerini getir (QR Menu icin)
     *
     * Sadece cafe_menusu=1 ve aktif=1 olan urunleri doner.
     * Sonuc 300 saniye cache'lenir. Urun guncelleme/silme/toggle
     * islemlerinde cache otomatik olarak temizlenir.
     *
     * @return array Cafe menusundeki aktif urunler (kategori bilgisi ile)
     */
    public function getCafeMenuUrunleri(): array
    {
        return $this->cacheRemember(
            self::CACHE_KEY_CAFE_MENU,
            fn () => $this->urunRepository->getCafeMenuUrunleri(),
            self::CACHE_TTL_CAFE_MENU
        );
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
        if (empty($data['slug']) && !empty($data['isim'])) {
            $data['slug'] = $this->generateSlug($data['isim']);
        }

        // Set default values
        $data['aktif'] = $data['aktif'] ?? 1;
        $data['sira'] = $data['sira'] ?? 0;

        $result = parent::create($data);
        $this->clearCache();
        return $result;
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
        if (!empty($data['isim']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['isim'], (int)$id);
        }

        $result = parent::update($id, $data);
        $this->clearCache();
        return $result;
    }

    /**
     * Delete product (override for cache invalidation)
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        $result = parent::delete($id);
        $this->clearCache();
        return $result;
    }

    /**
     * Toggle product active status
     *
     * @param int $id
     * @return bool New status
     */
    public function toggleActive(int $id): bool
    {
        $result = $this->urunRepository->toggleActive($id);
        $this->clearCache();
        return $result;
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
        $this->clearCache();
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
            'isim' => 'required|string|min:2|max:255',
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

        if (isset($data['isim'])) {
            $rules['isim'] = 'string|min:2|max:255';
        }

        if (isset($data['fiyat'])) {
            $rules['fiyat'] = 'numeric';
        }

        if (!empty($rules)) {
            $this->validate($data, $rules);
        }
    }

    /**
     * Urun cache'ini temizle
     *
     * Menü listesi, cafe menüsü ve öne çıkan ürün cache'lerini invalidate eder.
     * Create / update / delete / toggleActive / updateOrder sonrası çağrılır.
     *
     * @return void
     */
    protected function clearCache(): void
    {
        $this->clearCacheKeys(
            'products_active',
            self::CACHE_KEY_CAFE_MENU,
            self::CACHE_KEY_ACTIVE_ALL,
            self::CACHE_KEY_FEATURED
        );

        try {
            $kategoriler = db()->fetchAll('SELECT id FROM kategoriler');
            $cache = \Cache::getInstance();
            foreach ($kategoriler as $k) {
                $cache->forget('products_active_' . $k['id']);
            }
        } catch (\Throwable) {
        }
    }
}
