<?php

declare(strict_types=1);

namespace Pastane\Exceptions;

use Exception;

/**
 * HTTP Exception
 *
 * HTTP hata kodları için exception sınıfı.
 *
 * @package Pastane\Exceptions
 * @since 1.0.0
 */
class HttpException extends Exception
{
    /**
     * @var int HTTP status code
     */
    protected int $statusCode;

    /**
     * @var array Additional error details
     */
    protected array $details;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $details Additional details
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Bir hata oluştu',
        int $statusCode = 500,
        array $details = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->details = $details;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get error details
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Convert to array for JSON response
     *
     * @return array
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error' => $this->getMessage(),
            'code' => $this->statusCode,
        ];

        if (!empty($this->details)) {
            $response['details'] = $this->details;
        }

        return $response;
    }

    /**
     * Create Not Found exception
     *
     * @param string $message
     * @return static
     */
    public static function notFound(string $message = 'Kayıt bulunamadı'): static
    {
        return new static($message, 404);
    }

    /**
     * Create Unauthorized exception
     *
     * @param string $message
     * @return static
     */
    public static function unauthorized(string $message = 'Yetkilendirme gerekli'): static
    {
        return new static($message, 401);
    }

    /**
     * Create Forbidden exception
     *
     * @param string $message
     * @return static
     */
    public static function forbidden(string $message = 'Bu işlem için yetkiniz yok'): static
    {
        return new static($message, 403);
    }

    /**
     * Create Bad Request exception
     *
     * @param string $message
     * @param array $details
     * @return static
     */
    public static function badRequest(string $message = 'Geçersiz istek', array $details = []): static
    {
        return new static($message, 400, $details);
    }

    /**
     * Create Internal Server Error exception
     *
     * @param string $message
     * @return static
     */
    public static function serverError(string $message = 'Sunucu hatası'): static
    {
        return new static($message, 500);
    }

    /**
     * Create Too Many Requests exception
     *
     * @param string $message
     * @param int|null $retryAfter
     * @return static
     */
    public static function tooManyRequests(string $message = 'Çok fazla istek', ?int $retryAfter = null): static
    {
        $details = $retryAfter ? ['retry_after' => $retryAfter] : [];
        return new static($message, 429, $details);
    }
}
