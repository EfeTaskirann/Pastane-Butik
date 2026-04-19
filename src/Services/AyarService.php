<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\AyarRepository;

/**
 * Ayar Service
 *
 * Key-value ayar tablosu için tip-aware get/set ve cache katmanı.
 *
 * Cache:
 *   - Tüm ayarlar tek anahtarda (`ayarlar:all`) cache'lenir, 5 dk TTL.
 *   - set/delete sonrası cache invalidate edilir.
 *
 * Tip dönüşümleri:
 *   - string → string (raw)
 *   - int    → (int) cast
 *   - bool   → '1'/'true'/'on' = true, diğerleri false
 *   - json   → json_decode($_, true) (array döner; hatalıysa default)
 *
 * @package Pastane\Services
 * @since 2.0.0-sprint2
 */
class AyarService extends BaseService
{
    /**
     * @var AyarRepository
     */
    protected AyarRepository $ayarRepository;

    /**
     * @var string Cache key — tüm ayarların indexli map'i
     */
    private const CACHE_KEY_ALL = 'ayarlar:all';

    /**
     * @var int Cache TTL (saniye) — 5 dk
     */
    private const CACHE_TTL = 300;

    /**
     * @var array<string, array>|null Memory cache (request ömrü)
     */
    private ?array $memoryCache = null;

    /**
     * Constructor
     *
     * @param AyarRepository|null $repository
     */
    public function __construct(?AyarRepository $repository = null)
    {
        $this->ayarRepository = $repository ?? new AyarRepository();
        $this->repository = $this->ayarRepository;
    }

    /**
     * Ayar değerini tip-aware olarak getir
     *
     * @param string $anahtar
     * @param mixed  $default Kayıt yoksa veya dönüştürülemezse dönecek değer
     * @return mixed
     */
    public function get(string $anahtar, mixed $default = null): mixed
    {
        $all = $this->loadAll();

        if (!isset($all[$anahtar])) {
            return $default;
        }

        $row = $all[$anahtar];
        return $this->castValue(
            $row['deger'] ?? null,
            (string) ($row['tip'] ?? 'string'),
            $default
        );
    }

    /**
     * Ayarı set et (tip-aware serialize)
     *
     * @param string      $anahtar
     * @param mixed       $deger
     * @param string      $tip      string|int|bool|json
     * @param string|null $grup
     * @param string|null $aciklama
     * @return bool
     */
    public function set(string $anahtar, mixed $deger, string $tip = 'string', ?string $grup = null, ?string $aciklama = null): bool
    {
        $serialized = $this->serializeValue($deger, $tip);
        $result = $this->ayarRepository->setKey($anahtar, $serialized, $tip, $grup, $aciklama);
        $this->invalidateCache();
        return $result;
    }

    /**
     * Grup bazlı ayarları tip-aware map olarak getir
     *
     * @param string $grup
     * @return array<string, mixed>
     */
    public function getGroup(string $grup): array
    {
        $out = [];
        foreach ($this->loadAll() as $anahtar => $row) {
            if (($row['grup'] ?? '') === $grup) {
                $out[$anahtar] = $this->castValue(
                    $row['deger'] ?? null,
                    (string) ($row['tip'] ?? 'string'),
                    null
                );
            }
        }
        return $out;
    }

    /**
     * Tüm ayarları tip-aware map olarak getir
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $out = [];
        foreach ($this->loadAll() as $anahtar => $row) {
            $out[$anahtar] = $this->castValue(
                $row['deger'] ?? null,
                (string) ($row['tip'] ?? 'string'),
                null
            );
        }
        return $out;
    }

    /**
     * Tüm ayarları raw (DB satır) formatında döndür — admin UI için
     *
     * @return array<string, array>
     */
    public function getAllRaw(): array
    {
        return $this->loadAll();
    }

    /**
     * Ayarı sil
     *
     * @param string $anahtar
     * @return bool
     */
    public function deleteByKey(string $anahtar): bool
    {
        $result = $this->ayarRepository->deleteByKey($anahtar);
        $this->invalidateCache();
        return $result;
    }

    /**
     * Cache'i temizle (manuel invalidate)
     */
    public function invalidateCache(): void
    {
        $this->memoryCache = null;
        $this->clearCacheKeys(self::CACHE_KEY_ALL);
    }

    /**
     * Tüm ayarları yükle (cache veya DB'den)
     *
     * @return array<string, array>
     */
    private function loadAll(): array
    {
        if ($this->memoryCache !== null) {
            return $this->memoryCache;
        }

        try {
            $cache = \Cache::getInstance();
            $cached = $cache->remember(self::CACHE_KEY_ALL, function () {
                return $this->ayarRepository->getAllIndexed();
            }, self::CACHE_TTL);

            $this->memoryCache = is_array($cached) ? $cached : [];
            return $this->memoryCache;
        } catch (\Throwable) {
            // Cache başarısız olursa direkt DB
            $this->memoryCache = $this->ayarRepository->getAllIndexed();
            return $this->memoryCache;
        }
    }

    /**
     * Raw string değeri tip'e göre cast et
     *
     * @param mixed  $raw
     * @param string $tip
     * @param mixed  $default
     * @return mixed
     */
    private function castValue(mixed $raw, string $tip, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        $str = (string) $raw;

        return match ($tip) {
            'int'    => is_numeric($str) ? (int) $str : $default,
            'bool'   => in_array(strtolower(trim($str)), ['1', 'true', 'on', 'yes', 'evet'], true),
            'json'   => $this->decodeJson($str, $default),
            default  => $str, // string
        };
    }

    /**
     * PHP değerini DB string'ine serialize et
     *
     * @param mixed  $value
     * @param string $tip
     * @return string
     */
    private function serializeValue(mixed $value, string $tip): string
    {
        if (!in_array($tip, AyarRepository::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Geçersiz ayar tipi: {$tip}");
        }

        return match ($tip) {
            'int'  => (string) (int) $value,
            'bool' => (is_bool($value) ? ($value ? '1' : '0') : (
                in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'evet'], true) ? '1' : '0'
            )),
            'json' => is_string($value) && $this->isJsonString($value)
                ? $value
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    /**
     * JSON decode — hatalıysa default
     *
     * @param string $json
     * @param mixed  $default
     * @return mixed
     */
    private function decodeJson(string $json, mixed $default): mixed
    {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }
        return $decoded;
    }

    /**
     * String geçerli JSON mu?
     *
     * @param string $str
     * @return bool
     */
    private function isJsonString(string $str): bool
    {
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
