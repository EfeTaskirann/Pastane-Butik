<?php
/**
 * ValidationException Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Exceptions\ValidationException;

class ValidationExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_stores_validation_errors(): void
    {
        $errors = [
            'ad' => ['Ad alanı zorunludur.'],
            'fiyat' => ['Fiyat sayısal olmalıdır.'],
        ];

        $exception = new ValidationException('Doğrulama hatası', $errors);

        $this->assertEquals('Doğrulama hatası', $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
    }

    /**
     * @test
     */
    public function it_converts_to_array(): void
    {
        $errors = ['email' => ['Geçerli email olmalıdır.']];
        $exception = new ValidationException('Hata', $errors);

        $array = $exception->toArray();

        $this->assertFalse($array['success']);
        $this->assertEquals('Hata', $array['error']);
        $this->assertEquals(422, $array['code']);
        $this->assertEquals($errors, $array['errors']);
    }

    /**
     * @test
     */
    public function it_has_422_status_code(): void
    {
        $exception = new ValidationException('Test');

        $this->assertEquals(422, $exception->getCode());
    }
}
