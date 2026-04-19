<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\TemaRepository;

/**
 * Tema Service
 *
 * Tema business logic — aktivasyon, deaktivasyon, cache yönetimi.
 *
 * @package Pastane\Services
 * @since 1.1.0
 */
class TemaService extends BaseService
{
    private const CACHE_KEY = 'active_theme';
    private const CACHE_TTL = 300; // 5 dakika

    /**
     * @var TemaRepository
     */
    protected TemaRepository $temaRepository;

    /**
     * Constructor
     *
     * @param TemaRepository|null $repository
     */
    public function __construct(?TemaRepository $repository = null)
    {
        $this->temaRepository = $repository ?? new TemaRepository();
        $this->repository = $this->temaRepository;
    }

    /**
     * Aktif temayı getir (cache destekli)
     *
     * @return array|null
     */
    public function getActive(): ?array
    {
        try {
            $cache = \Cache::getInstance();
            return $cache->remember(self::CACHE_KEY, function () {
                return $this->temaRepository->findActive();
            }, self::CACHE_TTL);
        } catch (\Throwable) {
            return $this->temaRepository->findActive();
        }
    }

    /**
     * Temayı aktif et
     *
     * Transaction içinde: önce hepsini kapat, sonra hedefi aç.
     *
     * @param int $id
     * @return array Aktif edilen tema
     */
    public function activate(int $id): array
    {
        $this->temaRepository->findOrFail($id);

        $this->temaRepository->transaction(function () use ($id) {
            $this->temaRepository->deactivateAll();
            $this->temaRepository->update($id, ['aktif' => 1]);
        });

        $this->flushThemeCache();

        return $this->temaRepository->find($id);
    }

    /**
     * Temayı deaktif et
     *
     * @param int $id
     * @return array Deaktif edilen tema
     */
    public function deactivate(int $id): array
    {
        $this->temaRepository->findOrFail($id);

        $this->temaRepository->update($id, ['aktif' => 0]);
        $this->flushThemeCache();

        return $this->temaRepository->find($id);
    }

    /**
     * Tüm temaları deaktif et (varsayılan temaya dön)
     *
     * @return void
     */
    public function deactivateAll(): void
    {
        $this->temaRepository->deactivateAll();
        $this->flushThemeCache();
    }

    /**
     * Tema cache'ini agresif temizle
     *
     * Cache dosyasını doğrudan silerek garanti temizlik.
     *
     * @return void
     */
    private function flushThemeCache(): void
    {
        // Yöntem 1: Cache API ile
        try {
            \Cache::getInstance()->forget(self::CACHE_KEY);
        } catch (\Throwable) {}

        // Yöntem 2: Dosyayı doğrudan sil (garanti)
        $cacheFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
            . '/storage/cache/' . md5('pastane_' . self::CACHE_KEY) . '.cache';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * Tüm temaları listele
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->temaRepository->all(['*'], 'isim', 'ASC');
    }
}
