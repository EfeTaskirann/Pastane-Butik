<?php
/**
 * Validator Tests
 *
 * BaseValidator, SiparisValidator, KategoriValidator, AuthValidator testleri.
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Validators\SiparisValidator;
use Pastane\Validators\KategoriValidator;
use Pastane\Validators\AuthValidator;
use Pastane\Exceptions\ValidationException;

class ValidatorTest extends TestCase
{
    // ========================================
    // SiparisValidator — Create Scenario
    // ========================================

    /**
     * @test
     */
    public function siparis_create_requires_ad_soyad(): void
    {
        $validator = new SiparisValidator('create');

        $this->expectException(ValidationException::class);
        $validator->validate([
            'telefon' => '05551234567',
            'tarih' => date('Y-m-d', strtotime('+7 days')),
            'kategori' => 'pasta',
        ]);
    }

    /**
     * @test
     */
    public function siparis_create_requires_telefon(): void
    {
        $validator = new SiparisValidator('create');

        $this->expectException(ValidationException::class);
        $validator->validate([
            'ad_soyad' => 'Ali Veli',
            'tarih' => date('Y-m-d', strtotime('+7 days')),
            'kategori' => 'pasta',
        ]);
    }

    /**
     * @test
     */
    public function siparis_create_accepts_valid_data(): void
    {
        $validator = new SiparisValidator('create');

        $result = $validator->validate([
            'ad_soyad' => 'Ali Veli',
            'telefon' => '05551234567',
            'tarih' => date('Y-m-d', strtotime('+7 days')),
            'kategori' => 'pasta',
        ]);

        $this->assertEquals('Ali Veli', $result['ad_soyad']);
        $this->assertEquals('pasta', $result['kategori']);
    }

    /**
     * @test
     */
    public function siparis_create_rejects_past_date(): void
    {
        $validator = new SiparisValidator('create');

        $this->expectException(ValidationException::class);
        $validator->validate([
            'ad_soyad' => 'Ali Veli',
            'telefon' => '05551234567',
            'tarih' => '2020-01-01',
            'kategori' => 'pasta',
        ]);
    }

    /**
     * @test
     */
    public function siparis_create_whitelists_fields(): void
    {
        $validator = new SiparisValidator('create');

        $result = $validator->validate([
            'ad_soyad' => 'Ali Veli',
            'telefon' => '05551234567',
            'tarih' => date('Y-m-d', strtotime('+7 days')),
            'kategori' => 'pasta',
            'birim_fiyat' => 999999, // Client'tan gelmemeli
            'toplam_tutar' => 999999, // Client'tan gelmemeli
        ]);

        $this->assertArrayNotHasKey('birim_fiyat', $result);
        $this->assertArrayNotHasKey('toplam_tutar', $result);
    }

    // ========================================
    // SiparisValidator — Status Scenario
    // ========================================

    /**
     * @test
     */
    public function siparis_status_requires_durum(): void
    {
        $validator = new SiparisValidator('status');

        $this->expectException(ValidationException::class);
        $validator->validate([]);
    }

    /**
     * @test
     */
    public function siparis_status_rejects_invalid_durum(): void
    {
        $validator = new SiparisValidator('status');

        $this->expectException(ValidationException::class);
        $validator->validate(['durum' => 'gecersiz_durum']);
    }

    /**
     * @test
     */
    public function siparis_status_accepts_valid_durum(): void
    {
        $validStatuses = ['beklemede', 'onaylandi', 'hazirlaniyor', 'teslim_edildi', 'iptal'];

        foreach ($validStatuses as $status) {
            $validator = new SiparisValidator('status');
            $result = $validator->validate(['durum' => $status]);
            $this->assertEquals($status, $result['durum']);
        }
    }

    // ========================================
    // AuthValidator
    // ========================================

    /**
     * @test
     */
    public function auth_login_requires_credentials(): void
    {
        $validator = new AuthValidator('login');

        $this->expectException(ValidationException::class);
        $validator->validate([]);
    }

    /**
     * @test
     */
    public function auth_login_accepts_valid_credentials(): void
    {
        $validator = new AuthValidator('login');

        $result = $validator->validate([
            'kullanici_adi' => 'admin',
            'sifre' => 'test1234',
        ]);

        $this->assertEquals('admin', $result['kullanici_adi']);
        $this->assertEquals('test1234', $result['sifre']);
    }

    // ========================================
    // KategoriValidator
    // ========================================

    /**
     * @test
     */
    public function kategori_create_requires_ad(): void
    {
        $validator = new KategoriValidator('create');

        $this->expectException(ValidationException::class);
        $validator->validate([]);
    }

    /**
     * @test
     */
    public function kategori_create_accepts_valid_data(): void
    {
        $validator = new KategoriValidator('create');

        $result = $validator->validate([
            'ad' => 'Pastalar',
        ]);

        $this->assertEquals('Pastalar', $result['ad']);
    }
}
