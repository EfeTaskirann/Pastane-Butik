<?php
/**
 * AyarService Unit Tests
 *
 * Ayar (settings key-value) servisinin tip-aware get/set davranışını,
 * serialize/deserialize akışını ve cache invalidation'ı test eder.
 *
 * Repository katmanı `InMemoryAyarRepository` ile mock'lanır — DB'ye
 * dokunulmaz, testler tamamen izole çalışır.
 *
 * @package Pastane\Tests\Unit
 * @since 2.1.0-sprint3
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Repositories\AyarRepository;
use Pastane\Services\AyarService;
use Pastane\Tests\TestCase;

class AyarServiceTest extends TestCase
{
    /**
     * @var InMemoryAyarRepository
     */
    private InMemoryAyarRepository $repo;

    /**
     * @var AyarService
     */
    private AyarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new InMemoryAyarRepository();
        $this->service = new AyarService($this->repo);
    }

    /**
     * @test
     */
    public function test_olmayan_anahtar_default_doner(): void
    {
        $this->assertSame('varsayilan', $this->service->get('yok', 'varsayilan'));
        $this->assertNull($this->service->get('yok_da_yok'));
    }

    /**
     * @test
     */
    public function test_string_tipi_set_get_aynen_doner(): void
    {
        $this->service->set('site_baslik', 'Tatlı Düşler', 'string', 'genel');
        $this->assertSame('Tatlı Düşler', $this->service->get('site_baslik'));
    }

    /**
     * @test
     */
    public function test_int_tipi_cast_edilir(): void
    {
        $this->service->set('minimum_tutar', '250', 'int', 'siparis');
        $value = $this->service->get('minimum_tutar');
        $this->assertIsInt($value);
        $this->assertSame(250, $value);
    }

    /**
     * @test
     */
    public function test_int_tipi_gecersiz_deger_default_doner(): void
    {
        // Repository'e doğrudan geçersiz değer yerleştir (serializer'ı bypass eder)
        $this->repo->seed('bozuk', 'abc', 'int');
        $this->service->invalidateCache();
        $this->assertSame(-1, $this->service->get('bozuk', -1));
    }

    /**
     * @test
     */
    public function test_bool_tipi_cast_eder(): void
    {
        $this->service->set('kapida_odeme_aktif', true, 'bool', 'odeme');
        $this->service->set('havale_aktif', false, 'bool', 'odeme');

        $this->assertTrue($this->service->get('kapida_odeme_aktif'));
        $this->assertFalse($this->service->get('havale_aktif'));
    }

    /**
     * @test
     */
    public function test_bool_string_on_evet_true_kabul_edilir(): void
    {
        $this->repo->seed('on_flag', 'on', 'bool');
        $this->repo->seed('evet_flag', 'evet', 'bool');
        $this->repo->seed('yes_flag', 'yes', 'bool');
        $this->service->invalidateCache();

        $this->assertTrue($this->service->get('on_flag'));
        $this->assertTrue($this->service->get('evet_flag'));
        $this->assertTrue($this->service->get('yes_flag'));
    }

    /**
     * @test
     */
    public function test_json_tipi_array_olarak_doner(): void
    {
        $payload = ['gateway' => 'iyzico', 'currency' => 'TRY', 'fee' => 2.5];
        $this->service->set('odeme_konfig', $payload, 'json', 'odeme');

        $got = $this->service->get('odeme_konfig');
        $this->assertIsArray($got);
        $this->assertSame($payload, $got);
    }

    /**
     * @test
     */
    public function test_json_tipi_bozuk_default_doner(): void
    {
        $this->repo->seed('bozuk_json', 'bu json degil {{', 'json');
        $this->service->invalidateCache();
        $this->assertSame(['fallback'], $this->service->get('bozuk_json', ['fallback']));
    }

    /**
     * @test
     */
    public function test_gecersiz_tip_exception_firlatir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->set('x', 'y', 'gercekdisi_tip');
    }

    /**
     * @test
     */
    public function test_getGroup_sadece_ilgili_grubu_doner(): void
    {
        $this->service->set('site_baslik', 'Baslik', 'string', 'genel');
        $this->service->set('iletisim_email', 'a@b.com', 'string', 'iletisim');
        $this->service->set('kapida_odeme_aktif', true, 'bool', 'odeme');

        $iletisim = $this->service->getGroup('iletisim');
        $this->assertArrayHasKey('iletisim_email', $iletisim);
        $this->assertArrayNotHasKey('site_baslik', $iletisim);
        $this->assertArrayNotHasKey('kapida_odeme_aktif', $iletisim);
    }

    /**
     * @test
     */
    public function test_getAll_tip_aware_cast_eder(): void
    {
        $this->service->set('s_key', 'hello', 'string');
        $this->service->set('i_key', '42', 'int');
        $this->service->set('b_key', true, 'bool');

        $all = $this->service->getAll();
        $this->assertSame('hello', $all['s_key']);
        $this->assertSame(42, $all['i_key']);
        $this->assertTrue($all['b_key']);
    }

    /**
     * @test
     */
    public function test_deleteByKey_kaydi_siler_ve_cache_invalidate_eder(): void
    {
        $this->service->set('silinecek', 'evet', 'string');
        $this->assertSame('evet', $this->service->get('silinecek'));

        $ok = $this->service->deleteByKey('silinecek');
        $this->assertTrue($ok);
        $this->assertNull($this->service->get('silinecek'));
    }

    /**
     * @test
     */
    public function test_set_sonrasi_cache_invalidate_olur(): void
    {
        $this->service->set('x', '1', 'int');
        $this->assertSame(1, $this->service->get('x'));

        // Repository'e direkt yeni değer enjekte et — cache hâlâ eskiyi dönerse test fail
        $this->service->set('x', '99', 'int');
        $this->assertSame(99, $this->service->get('x'), 'set() çağrısı cache\'i invalidate etmeli');
    }
}

/**
 * In-memory AyarRepository — testler için mock (DB'siz).
 *
 * Gerçek AyarRepository ile aynı API'i sağlar (getAllIndexed, setKey, deleteByKey, getByKey).
 * BaseRepository'den extend eder ama tüm DB çağrılarını bellek diziye yönlendirir.
 */
class InMemoryAyarRepository extends AyarRepository
{
    /** @var array<string, array> */
    private array $data = [];

    /** @var int */
    private int $nextId = 1;

    public function __construct()
    {
        // Parent constructor'ı BYPASS et — DB bağlantısına ihtiyaç yok
    }

    public function getByKey(string $anahtar): ?array
    {
        return $this->data[$anahtar] ?? null;
    }

    public function getAllIndexed(): array
    {
        return $this->data;
    }

    public function setKey(string $anahtar, string $deger, string $tip = 'string', ?string $grup = null, ?string $aciklama = null): bool
    {
        if (!in_array($tip, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Geçersiz ayar tipi: {$tip}");
        }

        if (isset($this->data[$anahtar])) {
            $this->data[$anahtar]['deger'] = $deger;
            $this->data[$anahtar]['tip'] = $tip;
            if ($grup !== null) {
                $this->data[$anahtar]['grup'] = $grup;
            }
            if ($aciklama !== null) {
                $this->data[$anahtar]['aciklama'] = $aciklama;
            }
            return true;
        }

        $this->data[$anahtar] = [
            'id'       => $this->nextId++,
            'anahtar'  => $anahtar,
            'deger'    => $deger,
            'tip'      => $tip,
            'grup'     => $grup ?? 'genel',
            'aciklama' => $aciklama,
        ];
        return true;
    }

    public function deleteByKey(string $anahtar): bool
    {
        if (!isset($this->data[$anahtar])) {
            return false;
        }
        unset($this->data[$anahtar]);
        return true;
    }

    /**
     * Test helper — serializer'ı bypass edip doğrudan raw değer yerleştir.
     */
    public function seed(string $anahtar, string $rawDeger, string $tip, string $grup = 'genel'): void
    {
        $this->data[$anahtar] = [
            'id'       => $this->nextId++,
            'anahtar'  => $anahtar,
            'deger'    => $rawDeger,
            'tip'      => $tip,
            'grup'     => $grup,
            'aciklama' => null,
        ];
    }
}
