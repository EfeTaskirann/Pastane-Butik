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
     * @return array
     */
    public function getAllWithProductCount(): array
    {
        return $this->kategoriRepository->getAllWithProductCount();
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
        if (empty($data['slug']) && !empty($data['ad'])) {
            $data['slug'] = $this->generateSlug($data['ad']);
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
        // Ad değiştiyse slug'ı yeniden oluştur
        if (!empty($data['ad']) && empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['ad'], (int)$id);
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
     * Kategori oluşturma/güncelleme/silme/sıralama sonrası çağrılır.
     * getCategories() fonksiyonunun kullandığı cache key'lerini invalidate eder.
     *
     * @return void
     */
    protected function clearCache(): void
    {
        try {
            $cache = \Cache::getInstance();
            $cache->forget('categories_all');
        } catch (\Throwable) {
            // Cache temizleme hatası kategori işlemini engellememeli
        }
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
            'ad' => 'required|string|min:2|max:100',
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

        if (isset($data['ad'])) {
            $this->validate($data, [
                'ad' => 'string|min:2|max:100',
            ]);
        }
    }
}
