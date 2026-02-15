<?php

declare(strict_types=1);

namespace Pastane\Controllers;

use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

/**
 * Base Controller
 *
 * Tüm controller'lar için temel sınıf.
 * Request/Response yönetimi, validation, JSON response helpers.
 *
 * @package Pastane\Controllers
 * @since 1.0.0
 */
abstract class BaseController
{
    /**
     * @var array Request data
     */
    protected array $request = [];

    /**
     * @var array Query parameters
     */
    protected array $query = [];

    /**
     * @var array Route parameters
     */
    protected array $params = [];

    /**
     * @var array Current user (if authenticated)
     */
    protected ?array $user = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->request = $this->parseRequest();
        $this->query = $_GET;
    }

    /**
     * Parse request body
     *
     * @return array
     */
    protected function parseRequest(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }

        return array_merge($_GET, $_POST);
    }

    /**
     * Get request input
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->request[$key] ?? $default;
    }

    /**
     * Get query parameter
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Set route parameters
     *
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get route parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set authenticated user
     *
     * @param array|null $user
     * @return void
     */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /**
     * Validate request data
     *
     * @param array $rules Validation rules
     * @param array|null $data Data to validate (defaults to request)
     * @return array Validated data
     * @throws ValidationException
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data = $data ?? $this->request;
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $ruleName = $rule;
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule, 2);
                }

                $error = $this->applyRule($field, $value, $ruleName, $ruleParam, $data);

                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }

            if (!isset($errors[$field]) && $value !== null) {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $validated;
    }

    /**
     * Apply validation rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param string|null $param
     * @param array $data
     * @return string|null Error message or null if valid
     */
    protected function applyRule(string $field, mixed $value, string $rule, ?string $param, array $data): ?string
    {
        return match ($rule) {
            'required' => ($value === null || $value === '') ? "{$field} alanı zorunludur." : null,
            'string' => (!is_string($value) && $value !== null) ? "{$field} metin olmalıdır." : null,
            'integer' => (!is_numeric($value) && $value !== null) ? "{$field} sayı olmalıdır." : null,
            'numeric' => (!is_numeric($value) && $value !== null) ? "{$field} sayısal olmalıdır." : null,
            'email' => (!filter_var($value, FILTER_VALIDATE_EMAIL) && $value !== null) ? "{$field} geçerli email olmalıdır." : null,
            'min' => (strlen((string)$value) < (int)$param && $value !== null) ? "{$field} en az {$param} karakter olmalıdır." : null,
            'max' => (strlen((string)$value) > (int)$param && $value !== null) ? "{$field} en fazla {$param} karakter olabilir." : null,
            'in' => (!in_array($value, explode(',', $param ?? '')) && $value !== null) ? "{$field} geçersiz değer." : null,
            'confirmed' => ($value !== ($data["{$field}_confirmation"] ?? null)) ? "{$field} eşleşmiyor." : null,
            'date' => (!strtotime($value ?? '') && $value !== null) ? "{$field} geçerli tarih olmalıdır." : null,
            'array' => (!is_array($value) && $value !== null) ? "{$field} dizi olmalıdır." : null,
            default => null,
        };
    }

    /**
     * Send JSON response
     *
     * @param mixed $data
     * @param int $status
     * @return never
     */
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send success response
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $status
     * @return never
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): never
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $status);
    }

    /**
     * Send error response
     *
     * @param string $message
     * @param int $status
     * @param array|null $errors
     * @return never
     */
    protected function error(string $message, int $status = 400, ?array $errors = null): never
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    /**
     * Send created response
     *
     * @param mixed $data
     * @param string|null $message
     * @return never
     */
    protected function created(mixed $data = null, ?string $message = null): never
    {
        $this->success($data, $message ?? 'Başarıyla oluşturuldu.', 201);
    }

    /**
     * Send no content response
     *
     * @return never
     */
    protected function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /**
     * Send not found response
     *
     * @param string $message
     * @return never
     */
    protected function notFound(string $message = 'Kayıt bulunamadı.'): never
    {
        $this->error($message, 404);
    }

    /**
     * Send unauthorized response
     *
     * @param string $message
     * @return never
     */
    protected function unauthorized(string $message = 'Yetkilendirme gerekli.'): never
    {
        $this->error($message, 401);
    }

    /**
     * Send forbidden response
     *
     * @param string $message
     * @return never
     */
    protected function forbidden(string $message = 'Bu işlem için yetkiniz yok.'): never
    {
        $this->error($message, 403);
    }

    /**
     * Redirect to URL
     *
     * @param string $url
     * @param int $status
     * @return never
     */
    protected function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    /**
     * Render view (for traditional pages)
     *
     * @param string $view View path (relative to views/)
     * @param array $data Data to pass to view
     * @return void
     */
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = dirname(__DIR__, 2) . '/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new HttpException("View not found: {$view}", 500);
        }

        include $viewPath;
    }

    /**
     * Get pagination parameters
     *
     * @param int $defaultLimit
     * @param int $maxLimit
     * @return array ['page' => int, 'limit' => int, 'offset' => int]
     */
    protected function pagination(int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(1, (int)$this->query('page', 1));
        $limit = min($maxLimit, max(1, (int)$this->query('limit', $defaultLimit)));
        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Create paginated response
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $limit
     * @return array
     */
    protected function paginated(array $items, int $total, int $page, int $limit): array
    {
        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ];
    }
}
