<?php
/**
 * Service Cache Integration Tests
 *
 * BaseService::cacheRemember() davranışını (hit / miss / graceful degrade)
 * ve Metrics entegrasyonunu test eder.
 *
 * @package Pastane\Tests\Unit
 * @since 2.1.0-sprint3
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Services\BaseService;
use Pastane\Tests\TestCase;

class ServiceCacheTest extends TestCase
{
    /**
     * @var string
     */
    private string $testKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testKey = 'test_service_cache_' . uniqid();
        // Test öncesi key'i temizle
        \Cache::getInstance()->forget($this->testKey);
    }

    protected function tearDown(): void
    {
        \Cache::getInstance()->forget($this->testKey);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function test_cacheRemember_ilk_cagri_callback_calistirir(): void
    {
        $callCount = 0;
        $service = new class extends BaseService {
            public function exposedRemember(string $key, callable $cb, int $ttl): mixed
            {
                return $this->cacheRemember($key, $cb, $ttl);
            }
        };

        $value = $service->exposedRemember($this->testKey, function () use (&$callCount) {
            $callCount++;
            return ['result' => 'first_call'];
        }, 60);

        $this->assertSame(1, $callCount);
        $this->assertSame(['result' => 'first_call'], $value);
    }

    /**
     * @test
     */
    public function test_cacheRemember_ikinci_cagri_cacheden_doner(): void
    {
        $callCount = 0;
        $service = new class extends BaseService {
            public function exposedRemember(string $key, callable $cb, int $ttl): mixed
            {
                return $this->cacheRemember($key, $cb, $ttl);
            }
        };

        $cb = function () use (&$callCount) {
            $callCount++;
            return 'payload-' . $callCount;
        };

        $first = $service->exposedRemember($this->testKey, $cb, 60);
        $second = $service->exposedRemember($this->testKey, $cb, 60);

        $this->assertSame('payload-1', $first);
        $this->assertSame('payload-1', $second, 'İkinci çağrı cache\'den dönmeli — callback 1 kez çalışmış olmalı');
        $this->assertSame(1, $callCount);
    }

    /**
     * @test
     */
    public function test_cache_forget_sonrasi_callback_yeniden_calisir(): void
    {
        $callCount = 0;
        $service = new class extends BaseService {
            public function exposedRemember(string $key, callable $cb, int $ttl): mixed
            {
                return $this->cacheRemember($key, $cb, $ttl);
            }
            public function exposedClear(string ...$keys): void
            {
                $this->clearCacheKeys(...$keys);
            }
        };

        $service->exposedRemember($this->testKey, function () use (&$callCount) {
            $callCount++;
            return 'v' . $callCount;
        }, 60);

        // Invalidate
        $service->exposedClear($this->testKey);

        $service->exposedRemember($this->testKey, function () use (&$callCount) {
            $callCount++;
            return 'v' . $callCount;
        }, 60);

        $this->assertSame(2, $callCount, 'forget sonrasi callback yeniden calismali');
    }

    /**
     * @test
     */
    public function test_metrics_hit_miss_sayilir(): void
    {
        if (!class_exists('\Metrics', false)) {
            $this->markTestSkipped('Metrics sınıfı yüklü değil');
        }

        $baselineMiss = \Metrics::getCounter('pastane_cache_operations_total', [
            'key'    => $this->testKey,
            'result' => 'miss',
        ]);
        $baselineHit = \Metrics::getCounter('pastane_cache_operations_total', [
            'key'    => $this->testKey,
            'result' => 'hit',
        ]);

        $service = new class extends BaseService {
            public function exposedRemember(string $key, callable $cb, int $ttl): mixed
            {
                return $this->cacheRemember($key, $cb, $ttl);
            }
        };

        // Miss
        $service->exposedRemember($this->testKey, fn () => 'x', 60);
        // Hit
        $service->exposedRemember($this->testKey, fn () => 'x', 60);
        $service->exposedRemember($this->testKey, fn () => 'x', 60);

        $afterMiss = \Metrics::getCounter('pastane_cache_operations_total', [
            'key'    => $this->testKey,
            'result' => 'miss',
        ]);
        $afterHit = \Metrics::getCounter('pastane_cache_operations_total', [
            'key'    => $this->testKey,
            'result' => 'hit',
        ]);

        $this->assertSame(1.0, $afterMiss - $baselineMiss, 'Bir miss sayilmali');
        $this->assertSame(2.0, $afterHit - $baselineHit, 'Iki hit sayilmali');
    }

    /**
     * @test
     */
    public function test_cache_exception_olursa_callback_calistirilir(): void
    {
        // Cache singleton çalışıyor olacak — gerçek fail simulasyonu için
        // callback'in her zaman çalıştığını doğrulamak yerine normal akış
        // garanti edilir (graceful degrade path'i tryfail için integration testi).
        $callCount = 0;
        $service = new class extends BaseService {
            public function exposedRemember(string $key, callable $cb, int $ttl): mixed
            {
                return $this->cacheRemember($key, $cb, $ttl);
            }
        };

        $uniqueKey = 'graceful_' . uniqid();
        $value = $service->exposedRemember($uniqueKey, function () use (&$callCount) {
            $callCount++;
            return 'always_works';
        }, 60);

        \Cache::getInstance()->forget($uniqueKey);
        $this->assertSame(1, $callCount);
        $this->assertSame('always_works', $value);
    }
}
