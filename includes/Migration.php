<?php
/**
 * Database Migration Manager
 *
 * Veritabanı migration'larını yöneten sınıf.
 * Laravel benzeri migration sistemi.
 *
 * @package Pastane
 * @since 1.0.0
 */

class Migration
{
    /**
     * @var PDO Database connection
     */
    private PDO $db;

    /**
     * @var string Migrations directory
     */
    private string $migrationsPath;

    /**
     * @var string Migrations table name
     */
    private string $migrationsTable = 'migrations';

    /**
     * @var array Migration log
     */
    private array $log = [];

    /**
     * Constructor
     *
     * @param PDO|null $db
     * @param string|null $migrationsPath
     */
    public function __construct(?PDO $db = null, ?string $migrationsPath = null)
    {
        $this->db = $db ?? db()->getPdo();
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__) . '/database/migrations';

        $this->ensureMigrationsTable();
    }

    /**
     * Ensure migrations table exists
     *
     * @return void
     */
    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->exec($sql);
    }

    /**
     * Run pending migrations
     *
     * @return array Migration log
     */
    public function migrate(): array
    {
        $this->log = [];
        $pendingMigrations = $this->getPendingMigrations();

        if (empty($pendingMigrations)) {
            $this->log[] = ['status' => 'info', 'message' => 'Bekleyen migration yok.'];
            return $this->log;
        }

        $batch = $this->getNextBatchNumber();

        foreach ($pendingMigrations as $migration) {
            try {
                $this->runMigration($migration, $batch);
                $this->log[] = [
                    'status' => 'success',
                    'message' => "Migration çalıştırıldı: {$migration}",
                ];
            } catch (Exception $e) {
                $this->log[] = [
                    'status' => 'error',
                    'message' => "Migration hatası ({$migration}): {$e->getMessage()}",
                ];
                break; // Stop on first error
            }
        }

        return $this->log;
    }

    /**
     * Rollback last batch of migrations
     *
     * @param int $steps Number of batches to rollback
     * @return array Migration log
     */
    public function rollback(int $steps = 1): array
    {
        $this->log = [];

        for ($i = 0; $i < $steps; $i++) {
            $lastBatch = $this->getLastBatch();

            if (empty($lastBatch)) {
                $this->log[] = ['status' => 'info', 'message' => 'Geri alınacak migration yok.'];
                break;
            }

            foreach (array_reverse($lastBatch) as $migration) {
                try {
                    $this->rollbackMigration($migration['migration']);
                    $this->log[] = [
                        'status' => 'success',
                        'message' => "Migration geri alındı: {$migration['migration']}",
                    ];
                } catch (Exception $e) {
                    $this->log[] = [
                        'status' => 'error',
                        'message' => "Rollback hatası ({$migration['migration']}): {$e->getMessage()}",
                    ];
                }
            }
        }

        return $this->log;
    }

    /**
     * Reset all migrations
     *
     * @return array Migration log
     */
    public function reset(): array
    {
        $this->log = [];
        $allMigrations = $this->getExecutedMigrations();

        if (empty($allMigrations)) {
            $this->log[] = ['status' => 'info', 'message' => 'Geri alınacak migration yok.'];
            return $this->log;
        }

        foreach (array_reverse($allMigrations) as $migration) {
            try {
                $this->rollbackMigration($migration);
                $this->log[] = [
                    'status' => 'success',
                    'message' => "Migration geri alındı: {$migration}",
                ];
            } catch (Exception $e) {
                $this->log[] = [
                    'status' => 'error',
                    'message' => "Rollback hatası ({$migration}): {$e->getMessage()}",
                ];
            }
        }

        return $this->log;
    }

    /**
     * Refresh all migrations (reset + migrate)
     *
     * @return array Migration log
     */
    public function refresh(): array
    {
        $this->reset();
        return array_merge($this->log, $this->migrate());
    }

    /**
     * Get migration status
     *
     * @return array
     */
    public function status(): array
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrationFiles();

        $status = [];

        foreach ($all as $migration) {
            $status[] = [
                'migration' => $migration,
                'status' => in_array($migration, $executed) ? 'Executed' : 'Pending',
            ];
        }

        return $status;
    }

    /**
     * Create a new migration file
     *
     * @param string $name Migration name
     * @return string Created file path
     */
    public function create(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . '/' . $filename;

        // Ensure directory exists
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $template = $this->getMigrationTemplate($name);
        file_put_contents($filepath, $template);

        return $filepath;
    }

    /**
     * Get pending migrations
     *
     * @return array
     */
    private function getPendingMigrations(): array
    {
        $executed = $this->getExecutedMigrations();
        $all = $this->getAllMigrationFiles();

        return array_diff($all, $executed);
    }

    /**
     * Get all migration files
     *
     * @return array
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }

        // Also check for SQL files
        $sqlFiles = glob($this->migrationsPath . '/*.sql');
        foreach ($sqlFiles as $file) {
            $migrations[] = basename($file, '.sql');
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Get executed migrations
     *
     * @return array
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY id"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get next batch number
     *
     * @return int
     */
    private function getNextBatchNumber(): int
    {
        $stmt = $this->db->query(
            "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}"
        );
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Get last batch migrations
     *
     * @return array
     */
    private function getLastBatch(): array
    {
        $stmt = $this->db->query(
            "SELECT migration, batch FROM {$this->migrationsTable}
             WHERE batch = (SELECT MAX(batch) FROM {$this->migrationsTable})
             ORDER BY id DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Run a single migration
     *
     * @param string $migration
     * @param int $batch
     * @return void
     */
    private function runMigration(string $migration, int $batch): void
    {
        // Check for PHP migration
        $phpFile = $this->migrationsPath . '/' . $migration . '.php';
        $sqlFile = $this->migrationsPath . '/' . $migration . '.sql';

        // SQL migrations contain DDL which auto-commits, so no transaction for them
        $useTransaction = file_exists($phpFile);

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            if (file_exists($phpFile)) {
                $this->runPhpMigration($phpFile);
            } elseif (file_exists($sqlFile)) {
                $this->runSqlMigration($sqlFile);
            } else {
                throw new Exception("Migration dosyası bulunamadı: {$migration}");
            }

            // Record migration
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
            );
            $stmt->execute([$migration, $batch]);

            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
        } catch (Exception $e) {
            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Run PHP migration file
     *
     * @param string $file
     * @return void
     */
    private function runPhpMigration(string $file): void
    {
        $migration = require $file;

        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up($this->db);
        } elseif (is_array($migration) && isset($migration['up'])) {
            if (is_callable($migration['up'])) {
                $migration['up']($this->db);
            } elseif (is_string($migration['up'])) {
                $this->executeSql($migration['up']);
            }
        }
    }

    /**
     * Run SQL migration file
     *
     * @param string $file
     * @return void
     */
    private function runSqlMigration(string $file): void
    {
        $sql = file_get_contents($file);
        $this->executeSql($sql);
    }

    /**
     * Execute SQL statements
     *
     * @param string $sql
     * @return void
     */
    private function executeSql(string $sql): void
    {
        // Split by semicolon but be careful with strings
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->db->exec($statement);
            }
        }
    }

    /**
     * Split SQL into statements
     *
     * @param string $sql
     * @return array
     */
    private function splitSqlStatements(string $sql): array
    {
        // Simple split - for complex cases, a proper parser would be needed
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }

            if (!$inString && $char === ';') {
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return $statements;
    }

    /**
     * Rollback a single migration
     *
     * @param string $migration
     * @return void
     */
    private function rollbackMigration(string $migration): void
    {
        $phpFile = $this->migrationsPath . '/' . $migration . '.php';

        $this->db->beginTransaction();

        try {
            if (file_exists($phpFile)) {
                $migrationObj = require $phpFile;

                if (is_object($migrationObj) && method_exists($migrationObj, 'down')) {
                    $migrationObj->down($this->db);
                } elseif (is_array($migrationObj) && isset($migrationObj['down'])) {
                    if (is_callable($migrationObj['down'])) {
                        $migrationObj['down']($this->db);
                    } elseif (is_string($migrationObj['down'])) {
                        $this->executeSql($migrationObj['down']);
                    }
                }
            }
            // SQL migrations don't have automatic rollback

            // Remove from migrations table
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
            );
            $stmt->execute([$migration]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get migration template
     *
     * @param string $name
     * @return string
     */
    private function getMigrationTemplate(string $name): string
    {
        $className = $this->getClassName($name);

        return <<<PHP
<?php
/**
 * Migration: {$name}
 *
 * @package Pastane\Database\Migrations
 */

return new class {
    /**
     * Run the migration
     *
     * @param PDO \$db
     * @return void
     */
    public function up(PDO \$db): void
    {
        \$sql = "
            -- Your SQL here
        ";

        \$db->exec(\$sql);
    }

    /**
     * Reverse the migration
     *
     * @param PDO \$db
     * @return void
     */
    public function down(PDO \$db): void
    {
        \$sql = "
            -- Rollback SQL here
        ";

        \$db->exec(\$sql);
    }
};
PHP;
    }

    /**
     * Convert migration name to class name
     *
     * @param string $name
     * @return string
     */
    private function getClassName(string $name): string
    {
        // Remove timestamp prefix if present
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);

        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * Get log messages
     *
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
