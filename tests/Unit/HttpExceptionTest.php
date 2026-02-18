<?php
/**
 * HttpException Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Exceptions\HttpException;

class HttpExceptionTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_exception_with_status_code(): void
    {
        $exception = new HttpException('Test error', 404);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
    }

    /**
     * @test
     */
    public function it_creates_not_found_exception(): void
    {
        $exception = HttpException::notFound('Ürün bulunamadı');

        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertEquals('Ürün bulunamadı', $exception->getMessage());
    }

    /**
     * @test
     */
    public function it_creates_unauthorized_exception(): void
    {
        $exception = HttpException::unauthorized();

        $this->assertEquals(401, $exception->getStatusCode());
    }

    /**
     * @test
     */
    public function it_creates_forbidden_exception(): void
    {
        $exception = HttpException::forbidden();

        $this->assertEquals(403, $exception->getStatusCode());
    }

    /**
     * @test
     */
    public function it_creates_bad_request_with_details(): void
    {
        $details = ['field' => 'name', 'error' => 'required'];
        $exception = HttpException::badRequest('Geçersiz istek', $details);

        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals($details, $exception->getDetails());
    }

    /**
     * @test
     */
    public function it_creates_too_many_requests_with_retry_after(): void
    {
        $exception = HttpException::tooManyRequests('Çok fazla istek', 60);

        $this->assertEquals(429, $exception->getStatusCode());
        $this->assertEquals(['retry_after' => 60], $exception->getDetails());
    }

    /**
     * @test
     */
    public function it_creates_validation_error(): void
    {
        $errors = ['email' => 'Geçerli email olmalıdır'];
        $exception = HttpException::validationError('Doğrulama hatası', $errors);

        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertEquals($errors, $exception->getDetails());
    }

    /**
     * @test
     */
    public function it_converts_to_array(): void
    {
        $exception = HttpException::badRequest('Test', ['key' => 'value']);
        $array = $exception->toArray();

        $this->assertFalse($array['success']);
        $this->assertEquals('Test', $array['error']);
        $this->assertEquals(400, $array['code']);
        $this->assertEquals(['key' => 'value'], $array['details']);
    }

    /**
     * @test
     */
    public function it_excludes_empty_details_from_array(): void
    {
        $exception = HttpException::notFound();
        $array = $exception->toArray();

        $this->assertArrayNotHasKey('details', $array);
    }

    /**
     * @test
     */
    public function it_creates_server_error(): void
    {
        $exception = HttpException::serverError();

        $this->assertEquals(500, $exception->getStatusCode());
    }

    /**
     * @test
     */
    public function it_creates_method_not_allowed_with_methods(): void
    {
        $exception = HttpException::methodNotAllowed('Metod desteklenmiyor', ['GET', 'POST']);

        $this->assertEquals(405, $exception->getStatusCode());
        $this->assertEquals(['allowed_methods' => ['GET', 'POST']], $exception->getDetails());
    }
}
