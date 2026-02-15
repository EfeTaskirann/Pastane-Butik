<?php
/**
 * Base Test Case
 *
 * Tüm testler için temel sınıf.
 *
 * @package Pastane\Tests
 */

declare(strict_types=1);

namespace Pastane\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var PDO|null Database connection
     */
    protected static ?PDO $db = null;

    /**
     * @var bool Transaction started flag
     */
    protected bool $transactionStarted = false;

    /**
     * Set up before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Start transaction for database isolation
        if ($this->usesDatabaseTransactions()) {
            $this->beginTransaction();
        }
    }

    /**
     * Tear down after each test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Rollback transaction
        if ($this->transactionStarted) {
            $this->rollbackTransaction();
        }

        parent::tearDown();
    }

    /**
     * Check if test uses database transactions
     *
     * @return bool
     */
    protected function usesDatabaseTransactions(): bool
    {
        return false;
    }

    /**
     * Get database connection
     *
     * @return PDO
     */
    protected function getDb(): PDO
    {
        if (self::$db === null) {
            self::$db = db()->getPdo();
        }
        return self::$db;
    }

    /**
     * Begin database transaction
     *
     * @return void
     */
    protected function beginTransaction(): void
    {
        $this->getDb()->beginTransaction();
        $this->transactionStarted = true;
    }

    /**
     * Rollback database transaction
     *
     * @return void
     */
    protected function rollbackTransaction(): void
    {
        if ($this->getDb()->inTransaction()) {
            $this->getDb()->rollBack();
        }
        $this->transactionStarted = false;
    }

    /**
     * Assert JSON structure
     *
     * @param array $structure
     * @param array $data
     * @return void
     */
    protected function assertJsonStructure(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey(is_int($key) ? 0 : $key, $data);
                if (is_int($key)) {
                    foreach ($data as $item) {
                        $this->assertJsonStructure($value, $item);
                    }
                } else {
                    $this->assertJsonStructure($value, $data[$key]);
                }
            } else {
                $this->assertArrayHasKey($value, $data);
            }
        }
    }

    /**
     * Create a mock request
     *
     * @param string $method
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @return void
     */
    protected function mockRequest(
        string $method,
        string $uri,
        array $data = [],
        array $headers = []
    ): void {
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;

        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $_POST = $data;
        } else {
            $_GET = $data;
        }
    }

    /**
     * Generate random string
     *
     * @param int $length
     * @return string
     */
    protected function randomString(int $length = 10): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate random email
     *
     * @return string
     */
    protected function randomEmail(): string
    {
        return 'test_' . $this->randomString(8) . '@example.com';
    }
}
