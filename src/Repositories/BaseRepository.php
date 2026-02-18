<?php

declare(strict_types=1);

namespace Pastane\Repositories;

use PDO;
use Exception;
use Pastane\Exceptions\HttpException;

/**
 * Base Repository
 *
 * Tüm repository'ler için temel sınıf.
 * CRUD işlemleri ve query builder.
 *
 * @package Pastane\Repositories
 * @since 1.0.0
 */
abstract class BaseRepository
{
    /**
     * @var PDO Database connection
     */
    protected PDO $db;

    /**
     * @var string Table name
     */
    protected string $table;

    /**
     * @var string Primary key column
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Fillable columns
     */
    protected array $fillable = [];

    /**
     * @var array Hidden columns (excluded from select)
     */
    protected array $hidden = [];

    /**
     * @var array Sortable columns whitelist (override in child classes)
     * If empty, all valid column names are allowed for ORDER BY.
     */
    protected array $sortableColumns = [];

    /**
     * @var array Allowed SQL operators for WHERE conditions
     */
    protected array $allowedOperators = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
        'IS NULL', 'IS NOT NULL', 'BETWEEN',
    ];

    /**
     * @var bool Use soft deletes
     */
    protected bool $softDeletes = false;

    /**
     * @var string Soft delete column
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * @var bool Use timestamps
     */
    protected bool $timestamps = true;

    /**
     * @var string Created at column
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * @var string Updated at column
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * Constructor
     *
     * @param PDO|null $db
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db()->getPdo();
    }

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
        $cols = $this->buildColumns($columns);
        $sql = "SELECT {$cols} FROM {$this->table}";

        if ($this->softDeletes) {
            $sql .= " WHERE {$this->deletedAtColumn} IS NULL";
        }

        if ($orderBy) {
            $orderBy = $this->validateOrderBy($orderBy);
            $direction = $this->validateDirection($direction);
            $sql .= " ORDER BY {$orderBy} {$direction}";
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $cols = $this->buildColumns($columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$this->primaryKey} = ?";

        if ($this->softDeletes) {
            $sql .= " AND {$this->deletedAtColumn} IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
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
        $result = $this->find($id, $columns);

        if ($result === null) {
            throw HttpException::notFound('Kayıt bulunamadı.');
        }

        return $result;
    }

    /**
     * Find by column
     *
     * @param string $column
     * @param mixed $value
     * @param array $columns
     * @return array|null
     */
    public function findBy(string $column, mixed $value, array $columns = ['*']): ?array
    {
        $column = $this->validateColumnName($column);
        $cols = $this->buildColumns($columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$column} = ?";

        if ($this->softDeletes) {
            $sql .= " AND {$this->deletedAtColumn} IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find all by column
     *
     * @param string $column
     * @param mixed $value
     * @param array $columns
     * @return array
     */
    public function findAllBy(string $column, mixed $value, array $columns = ['*']): array
    {
        $column = $this->validateColumnName($column);
        $cols = $this->buildColumns($columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$column} = ?";

        if ($this->softDeletes) {
            $sql .= " AND {$this->deletedAtColumn} IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get records with conditions
     *
     * @param array $conditions ['column' => value] or ['column' => ['operator', value]]
     * @param array $columns
     * @param string|null $orderBy
     * @param string $direction
     * @param int|null $limit
     * @param int $offset
     * @return array
     */
    public function where(
        array $conditions,
        array $columns = ['*'],
        ?string $orderBy = null,
        string $direction = 'ASC',
        ?int $limit = null,
        int $offset = 0
    ): array {
        $cols = $this->buildColumns($columns);
        $sql = "SELECT {$cols} FROM {$this->table}";

        [$whereSql, $params] = $this->buildWhere($conditions);
        $sql .= $whereSql;

        if ($this->softDeletes) {
            $sql .= " AND {$this->deletedAtColumn} IS NULL";
        }

        if ($orderBy) {
            $orderBy = $this->validateOrderBy($orderBy);
            $direction = $this->validateDirection($direction);
            $sql .= " ORDER BY {$orderBy} {$direction}";
        }

        if ($limit !== null) {
            $limit = max(1, (int)$limit);
            $offset = max(0, (int)$offset);
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count records
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";

        if (!empty($conditions)) {
            [$whereSql, $params] = $this->buildWhere($conditions);
            $sql .= $whereSql;
        } else {
            $params = [];
        }

        if ($this->softDeletes) {
            $sql .= empty($conditions) ? " WHERE " : " AND ";
            $sql .= "{$this->deletedAtColumn} IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Check if record exists
     *
     * @param array $conditions
     * @return bool
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Create a new record
     *
     * @param array $data
     * @return int|string Last insert ID
     */
    public function create(array $data): int|string
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data[$this->createdAtColumn] = $now;
            $data[$this->updatedAtColumn] = $now;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->db->lastInsertId();
    }

    /**
     * Update a record
     *
     * @param int|string $id
     * @param array $data
     * @return bool
     */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data[$this->updatedAtColumn] = date('Y-m-d H:i:s');
        }

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = ?";
        }
        $setsSql = implode(', ', $sets);

        $sql = "UPDATE {$this->table} SET {$setsSql} WHERE {$this->primaryKey} = ?";
        $params = array_values($data);
        $params[] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update with conditions
     *
     * @param array $conditions
     * @param array $data
     * @return int Affected rows
     */
    public function updateWhere(array $conditions, array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data[$this->updatedAtColumn] = date('Y-m-d H:i:s');
        }

        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        $setsSql = implode(', ', $sets);

        [$whereSql, $whereParams] = $this->buildWhere($conditions);
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE {$this->table} SET {$setsSql} {$whereSql}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete a record
     *
     * @param int|string $id
     * @return bool
     */
    public function delete(int|string $id): bool
    {
        if ($this->softDeletes) {
            return $this->update($id, [$this->deletedAtColumn => date('Y-m-d H:i:s')]);
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$id]);
    }

    /**
     * Force delete (even with soft deletes)
     *
     * @param int|string $id
     * @return bool
     */
    public function forceDelete(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$id]);
    }

    /**
     * Restore soft deleted record
     *
     * @param int|string $id
     * @return bool
     */
    public function restore(int|string $id): bool
    {
        if (!$this->softDeletes) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET {$this->deletedAtColumn} = NULL WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([$id]);
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
     * @return array ['data' => [], 'meta' => []]
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        array $columns = ['*'],
        ?string $orderBy = null,
        string $direction = 'ASC'
    ): array {
        $total = $this->count($conditions);
        $offset = ($page - 1) * $perPage;

        $data = $this->where($conditions, $columns, $orderBy, $direction, $perPage, $offset);

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Execute raw query
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function raw(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * Execute in transaction
     *
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Build columns string
     *
     * @param array $columns
     * @return string
     */
    protected function buildColumns(array $columns): string
    {
        if ($columns === ['*']) {
            if (empty($this->hidden)) {
                return '*';
            }
            // Select all except hidden columns - would need table schema
            return '*';
        }

        // Filter out hidden columns and validate each column name
        $columns = array_diff($columns, $this->hidden);
        $validatedColumns = array_map([$this, 'validateColumnName'], $columns);

        return implode(', ', $validatedColumns);
    }

    /**
     * Build WHERE clause
     *
     * @param array $conditions
     * @return array [sql, params]
     */
    protected function buildWhere(array $conditions): array
    {
        if (empty($conditions)) {
            return ['', []];
        }

        $clauses = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $column = $this->validateColumnName($column);

            if (is_array($value)) {
                // ['column' => ['operator', 'value']]
                [$operator, $val] = $value;
                $operator = $this->validateOperator($operator);
                $clauses[] = "{$column} {$operator} ?";
                $params[] = $val;
            } elseif ($value === null) {
                $clauses[] = "{$column} IS NULL";
            } else {
                $clauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $sql = ' WHERE ' . implode(' AND ', $clauses);

        return [$sql, $params];
    }

    /**
     * Filter data to only fillable columns
     *
     * @param array $data
     * @return array
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get primary key
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    // ========================================
    // SQL INJECTION PREVENTION METHODS
    // ========================================

    /**
     * Validate column name to prevent SQL injection
     *
     * Only allows alphanumeric characters, underscores, dots (table.column)
     *
     * @param string $column
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function validateColumnName(string $column): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        return $column;
    }

    /**
     * Validate ORDER BY column against sortable whitelist
     *
     * If $sortableColumns is defined in child class, only those columns are allowed.
     * Otherwise falls back to basic column name validation.
     *
     * @param string $column
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function validateOrderBy(string $column): string
    {
        $this->validateColumnName($column);

        if (!empty($this->sortableColumns) && !in_array($column, $this->sortableColumns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' is not allowed for sorting. Allowed: " . implode(', ', $this->sortableColumns)
            );
        }

        return $column;
    }

    /**
     * Validate sort direction (only ASC or DESC allowed)
     *
     * @param string $direction
     * @return string
     */
    protected function validateDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            return 'ASC';
        }
        return $direction;
    }

    /**
     * Validate SQL operator against whitelist
     *
     * @param string $operator
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function validateOperator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $this->allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid SQL operator: {$operator}");
        }
        return $operator;
    }
}
