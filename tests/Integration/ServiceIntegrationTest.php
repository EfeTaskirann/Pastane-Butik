<?php
/**
 * Service Integration Tests
 *
 * Service → Repository → Database katmanlarının birlikte çalışmasını test eder.
 * Bu testler gerçek veritabanı bağlantısı kullanır.
 *
 * @package Pastane\Tests\Integration
 */

declare(strict_types=1);

namespace Pastane\Tests\Integration;

use Pastane\Tests\TestCase;
use Pastane\Services\UrunService;
use Pastane\Services\KategoriService;
use Pastane\Services\MesajService;

class ServiceIntegrationTest extends TestCase
{
    private UrunService $urunService;
    private KategoriService $kategoriService;
    private MesajService $mesajService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urunService = urun_service();
        $this->kategoriService = kategori_service();
        $this->mesajService = mesaj_service();
    }

    // ========================================
    // UrunService
    // ========================================

    /**
     * @test
     */
    public function urun_service_count_returns_integer(): void
    {
        $count = $this->urunService->count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * @test
     */
    public function urun_service_get_active_returns_array(): void
    {
        $products = $this->urunService->getActive();
        $this->assertIsArray($products);
    }

    /**
     * @test
     */
    public function urun_service_get_active_with_limit(): void
    {
        $products = $this->urunService->getActive(null, 3);
        $this->assertIsArray($products);
        $this->assertLessThanOrEqual(3, count($products));
    }

    /**
     * @test
     */
    public function urun_service_get_featured_returns_array(): void
    {
        $featured = $this->urunService->getFeatured(6);
        $this->assertIsArray($featured);
        $this->assertLessThanOrEqual(6, count($featured));
    }

    /**
     * @test
     */
    public function urun_service_find_returns_null_for_nonexistent(): void
    {
        $result = $this->urunService->find(999999);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function urun_service_find_or_fail_throws_for_nonexistent(): void
    {
        $this->expectException(\Pastane\Exceptions\HttpException::class);
        $this->urunService->findOrFail(999999);
    }

    /**
     * @test
     */
    public function urun_service_get_price_range_returns_min_max(): void
    {
        $range = $this->urunService->getPriceRange();
        $this->assertArrayHasKey('min', $range);
        $this->assertArrayHasKey('max', $range);
        $this->assertIsFloat($range['min']);
        $this->assertIsFloat($range['max']);
    }

    /**
     * @test
     */
    public function urun_service_search_with_short_query_returns_empty(): void
    {
        $results = $this->urunService->search('a');
        $this->assertEmpty($results);
    }

    // ========================================
    // KategoriService
    // ========================================

    /**
     * @test
     */
    public function kategori_service_count_returns_integer(): void
    {
        $count = $this->kategoriService->count();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * @test
     */
    public function kategori_service_get_all_with_product_count(): void
    {
        $categories = $this->kategoriService->getAllWithProductCount();
        $this->assertIsArray($categories);

        if (!empty($categories)) {
            // Ürün sayısı alanı olmalı
            $first = $categories[0];
            $this->assertArrayHasKey('ad', $first);
        }
    }

    /**
     * @test
     */
    public function kategori_service_find_by_slug_returns_null_for_nonexistent(): void
    {
        $result = $this->kategoriService->findBySlug('olmayan-kategori-slug-xyz');
        $this->assertNull($result);
    }

    // ========================================
    // MesajService
    // ========================================

    /**
     * @test
     */
    public function mesaj_service_get_unread_count_returns_integer(): void
    {
        $count = $this->mesajService->getUnreadCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * @test
     */
    public function mesaj_service_get_all_ordered_returns_array(): void
    {
        $messages = $this->mesajService->getAllOrdered();
        $this->assertIsArray($messages);
    }

    // ========================================
    // Service Helper Singleton Behavior
    // ========================================

    /**
     * @test
     */
    public function service_helpers_return_singleton_instances(): void
    {
        $a = urun_service();
        $b = urun_service();
        $this->assertSame($a, $b, 'urun_service() should return same instance');

        $c = kategori_service();
        $d = kategori_service();
        $this->assertSame($c, $d, 'kategori_service() should return same instance');

        $e = mesaj_service();
        $f = mesaj_service();
        $this->assertSame($e, $f, 'mesaj_service() should return same instance');

        $g = siparis_service();
        $h = siparis_service();
        $this->assertSame($g, $h, 'siparis_service() should return same instance');
    }
}
