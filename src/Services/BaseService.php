<?php

declare(strict_types=1);

namespace Pastane\Services;

use Pastane\Repositories\BaseRepository;
use Pastane\Exceptions\HttpException;
use Pastane\Exceptions\ValidationException;

/**
 * Base Service
 *
 * Tüm service'ler için temel sınıf.
 * Business logic ve repository entegrasyonu.
 *
 * @package Pastane\Services
 * @since 1.0.0
 */
abstract class BaseService
{
    /**
     * @var BaseRepository Repository instance
     */
    protected BaseRepository $repository;

    /**
     * Get all records
     *
     * @param array $columns
     * @param string|null $orderBy
     * @param string $direction
     * @return array
     */
    public function all(array $columns = ['*'], ?string $orderBy = null, string $direction = 'ASC'): array
    {
        return $this->repository->all($columns, $orderBy, $direction);
    }

    /**
     * Find by ID
     *
     * @param int|string $id
     * @param array $columns
     * @return array|null
     */
    public function find(int|string $id, array $columns = ['*']): ?array
    {
        return $this->repository->find($id, $columns);
    }

    /**
     * Find or fail
     *
     * @param int|string $id
     * @param array $columns
     * @return array
     * @throws HttpException
     */
    public function findOrFail(int|string $id, array $columns = ['*']): array
    {
        return $this->repository->findOrFail($id, $columns);
    }

    /**
     * Create a new record
     *
     * @param array $data
     * @return array Created record
     */
    public function create(array $data): array
    {
        $this->validateCreate($data);
        $id = $this->repository->create($data);

        return $this->repository->find($id);
    }

    /**
     * Update a record
     *
     * @param int|string $id
     * @param array $data
     * @return array Updated record
     */
    public function update(int|string $id, array $data): array
    {
        $this->validateUpdate($id, $data);
        $this->repository->update($id, $data);

        return $this->repository->find($id);
    }

    /**
     * Delete a record
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        $this->validateDelete($id);
        return $this->repository->delete($id);
    }

    /**
     * Get paginated results
     *
     * @param int $page
     * @param int $perPage
     * @param array $conditions
     * @param array $columns
     * @param string|null $orderBy
     * @param string $direction
     * @return array
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        array $columns = ['*'],
        ?string $orderBy = null,
        string $direction = 'ASC'
    ): array {
        return $this->repository->paginate($page, $perPage, $conditions, $columns, $orderBy, $direction);
    }

    /**
     * Count records
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        return $this->repository->count($conditions);
    }

    /**
     * Check if record exists
     *
     * @param array $conditions
     * @return bool
     */
    public function exists(array $conditions): bool
    {
        return $this->repository->exists($conditions);
    }

    /**
     * Execute in transaction
     *
     * @param callable $callback
     * @return mixed
     */
    public function transaction(callable $callback): mixed
    {
        return $this->repository->transaction($callback);
    }

    /**
     * Validate data before create
     * Override in child classes
     *
     * @param array $data
     * @return void
     * @throws ValidationException
     */
    protected function validateCreate(array $data): void
    {
        // Override in child classes
    }

    /**
     * Validate data before update
     * Override in child classes
     *
     * @param int|string $id
     * @param array $data
     * @return void
     * @throws ValidationException|HttpException
     */
    protected function validateUpdate(int|string $id, array $data): void
    {
        // Ensure record exists
        $this->repository->findOrFail($id);
    }

    /**
     * Validate before delete
     * Override in child classes
     *
     * @param int|string $id
     * @return void
     * @throws ValidationException|HttpException
     */
    protected function validateDelete(int|string $id): void
    {
        // Ensure record exists
        $this->repository->findOrFail($id);
    }

    /**
     * Validate data against rules
     *
     * @param array $data
     * @param array $rules
     * @return array Validated data
     * @throws ValidationException
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $error = $this->applyRule($field, $value, $rule, $data);
                if ($error !== null) {
                    $errors[$field][] = $error;
                }
            }

            if (!isset($errors[$field]) && $value !== null) {
                $validated[$field] = $value;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Doğrulama hatası', $errors);
        }

        return $validated;
    }

    /**
     * Apply a single validation rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param array $data
     * @return string|null
     */
    protected function applyRule(string $field, mixed $value, string $rule, array $data): ?string
    {
        $ruleName = $rule;
        $ruleParam = null;

        if (str_contains($rule, ':')) {
            [$ruleName, $ruleParam] = explode(':', $rule, 2);
        }

        return match ($ruleName) {
            'required' => ($value === null || $value === '') ? "{$field} alanı zorunludur." : null,
            'string' => (!is_string($value) && $value !== null) ? "{$field} metin olmalıdır." : null,
            'integer' => (!is_numeric($value) && $value !== null) ? "{$field} sayı olmalıdır." : null,
            'numeric' => (!is_numeric($value) && $value !== null) ? "{$field} sayısal olmalıdır." : null,
            'email' => (!filter_var($value, FILTER_VALIDATE_EMAIL) && $value !== null) ? "{$field} geçerli email olmalıdır." : null,
            'min' => (strlen((string)$value) < (int)$ruleParam && $value !== null) ? "{$field} en az {$ruleParam} karakter olmalıdır." : null,
            'max' => (strlen((string)$value) > (int)$ruleParam && $value !== null) ? "{$field} en fazla {$ruleParam} karakter olabilir." : null,
            'unique' => $this->validateUnique($field, $value, $ruleParam, $data),
            default => null,
        };
    }

    /**
     * Validate unique rule
     *
     * @param string $field
     * @param mixed $value
     * @param string|null $param table,column,except_id
     * @param array $data
     * @return string|null
     */
    protected function validateUnique(string $field, mixed $value, ?string $param, array $data): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = explode(',', $param ?? '');
        $column = $parts[0] ?? $field;
        $exceptId = $parts[1] ?? null;

        $conditions = [$column => $value];

        if ($this->repository->exists($conditions)) {
            // Check if it's the same record (for updates)
            if ($exceptId) {
                $existing = $this->repository->findBy($column, $value);
                if ($existing && $existing[$this->repository->getPrimaryKey()] == $exceptId) {
                    return null;
                }
            }
            return "{$field} zaten kullanılıyor.";
        }

        return null;
    }

    /**
     * Get repository instance
     *
     * @return BaseRepository
     */
    public function getRepository(): BaseRepository
    {
        return $this->repository;
    }

    /**
     * Cache key'lerini temizle
     *
     * Service'lerde CRUD sonrası çağrılır.
     * Alt sınıflar $cacheKeys property'si ile temizlenecek key'leri tanımlar.
     *
     * @param string ...$keys Temizlenecek cache key'leri
     * @return void
     */
    protected function clearCacheKeys(string ...$keys): void
    {
        try {
            $cache = \Cache::getInstance();
            foreach ($keys as $key) {
                $cache->forget($key);
            }
        } catch (\Throwable) {
            // Cache temizleme hatası business logic'i engellememeli
        }
    }
}
