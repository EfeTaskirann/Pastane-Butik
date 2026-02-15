<?php

declare(strict_types=1);

namespace Pastane\Exceptions;

/**
 * Validation Exception
 *
 * Doğrulama hataları için exception sınıfı.
 *
 * @package Pastane\Exceptions
 * @since 1.0.0
 */
class ValidationException extends HttpException
{
    /**
     * @var array Field errors
     */
    protected array $errors;

    /**
     * Constructor
     *
     * @param string $message
     * @param array $errors Field errors ['field' => ['error1', 'error2']]
     */
    public function __construct(string $message = 'Doğrulama hatası', array $errors = [])
    {
        parent::__construct($message, 422, ['errors' => $errors]);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     *
     * @param string $field
     * @return string|null
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if field has error
     *
     * @param string $field
     * @return bool
     */
    public function has(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get all errors as flat array
     *
     * @return array
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                $all[] = $error;
            }
        }
        return $all;
    }

    /**
     * Convert to array for JSON response
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => false,
            'error' => $this->getMessage(),
            'code' => 422,
            'errors' => $this->errors,
        ];
    }
}
