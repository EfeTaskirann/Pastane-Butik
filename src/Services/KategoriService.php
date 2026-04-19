<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\KategoriRepository;
use Pastane\Exceptions\ValidationException;
use Pastane\Exceptions\HttpException;

/**
 * Kategori Service
 *
 * Kategori business logic.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
class KategoriService extends BaseService
{
    /**
     * Cache key / TTL sabitleri
     */
    private const CACHE_KEY_ALL_WITH_COUNT = 'kategoriler:urun-sayisi';
    private const CACHE_TTL_ALL_WITH_COUNT = 300; // 5 dk

    /**
     * @var KategoriRepository
     */
    protected KategoriRepository $kategoriRepository;

    /**
     * Constructor
     *
     * @param KategoriRepository|null $repository
     */
    public function __construct(?KategoriRepository $repository = null)
    {
        $this->kategoriRepository = $repository ?? new KategoriRepository();
        $this->repository = $this->kategoriRepository;
    }

    /**
     * Tüm kategorileri ürün sayısıyla birlikte getir
     *
     * Hot path: hem menü hem admin panel bu metodu çağırır.
     * 5 dk cache'lenir. Create/update/delete/updateOrder sonrası invalidate.
     *
     * @return array
     */
    public function getAllWithProductCount(): array
    {
        return $this->cacheRemember(
            self::CACHE_KEY_ALL_WITH_COUNT,
            fn () => $this->kategoriRepository->getAllWithProductCount(),
            self::CACHE_TTL_ALL_WITH_COUNT
        );
    }

    /**
     * Slug'a göre kategori bul
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->kategoriRepository->findBySlug($slug);
    }

    /**
     * Kategorinin ürünü var mı kontrol et
     *
     * @param int $id
     * @return bool
     */
    public function hasProducts(int $id): bool
    {
        return $this->kategoriRepository->hasProducts($id);
    }

    /**
     * Kategorideki ürün sayısını getir
     *
     * @param int $id
     * @return int
     */
    public function getProductCount(int $id): int
    {
        return $this->kategoriRepository->getProductCount($id);
    }

    /**
     * Kategori oluştur
     *
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        // Slug oluştur
        if (empty($data['slug']) && !empty($data['isim'])) {
            $data['slug'] = $this->generateSlug($data['isim']);
        }

        // Varsayılan sıra
        $data['sira'] = $data['sira'] ?? 0;

        $result = parent::create($data);
        $this->clearCache();
        return $result;
    }

    /**
     * Kategori güncelle
     *
     * @param int|string $id
     * @param array $data
     * @return array
     */
    public function update(int|string $id, array $data): array
    {
        // İsim değiştiyse slug'ı yeniden oluştur
        if (!empty($data['isim']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['isim'], (int)$id);
        }

        $result = parent::update($id, $data);
        $this->clearCache();
        return $result;
    }

    /**
     * Kategori sil
     *
     * @param int|string $id
     * @return bool
     * @throws HttpException Kategoride ürün varsa
     */
    public function delete(int|string $id): bool
    {
        // Kategoride ürün var mı kontrol et
        $productCount = $this->getProductCount((int)$id);
        if ($productCount > 0) {
            throw HttpException::badRequest(
                "Bu kategoriye ait {$productCount} ürün var. Önce ürünleri başka kategoriye taşıyın veya silin."
            );
        }

        $result = parent::delete($id);
        $this->clearCache();
        return $result;
    }

    /**
     * Kategori sırasını güncelle
     *
     * @param array $orders [id => sira]
     * @return void
     */
    public function updateOrder(array $orders): void
    {
        $this->kategoriRepository->updateOrder($orders);
        $this->clearCache();
    }

    /**
     * Benzersiz slug oluştur
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

        while ($this->kategoriRepository->slugExists($slug, $exceptId)) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Metni slug'a çevir
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
     * Kategori cache'ini temizle
     *
     * Kategori create/update/delete/updateOrder sonrası çağrılır.
     * Ayrıca kategori değişimi menü listesini de etkilediği için
     * ilgili ürün cache key'lerini de temizler.
     *
     * @return void
     */
    protected function clearCache(): void
    {
        $this->clearCacheKeys(
            'categories_all',
            self::CACHE_KEY_ALL_WITH_COUNT,
            'urunler:aktif:all',    // UrunService::CACHE_KEY_ACTIVE_ALL — kategori değişti → menü bayat
            'cafe_menu_urunleri',   // UrunService::CACHE_KEY_CAFE_MENU
            'urunler:one-cikan'     // UrunService::CACHE_KEY_FEATURED
        );
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
            'isim' => 'required|string|min:2|max:100',
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

        if (isset($data['isim'])) {
            $this->validate($data, [
                'isim' => 'string|min:2|max:100',
            ]);
        }
    }
}
