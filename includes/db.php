<?php
/**
 * Veritabanı Bağlantısı (PDO)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $queryCount = 0;
    private array $queryLog = [];
    private bool $logging = false;

    /**
     * İzin verilen tablo adları - SQL Injection koruması
     * Codex + Antigravity analizi ile eksik tablolar eklendi
     */
    private const ALLOWED_TABLES = [
        'urunler',
        'kategoriler',
        'siparisler',
        'mesajlar',
        'kullanicilar',
        'ayarlar',
        'login_attempts',
        'login_log',
        'rate_limits',
        'cache',
        'iletisim_mesajlari',
        'admin_kullanicilar',
        'siparis_arsiv',
        'odemeler',
        'jwt_blacklist',
        'migrations',
        'musteriler',
        'musteri_sadakat',
        'password_history',
        'siparis_puan_ayarlari',
        'kategori_fiyatlari',
    ];

    /**
     * İzin verilen kolon adları - SQL Injection koruması
     */
    private const ALLOWED_COLUMNS_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->logging = defined('DEBUG_MODE') && DEBUG_MODE;
        } catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new RuntimeException("Veritabanı bağlantı hatası: " . $e->getMessage());
            }
            throw new RuntimeException("Veritabanı bağlantısı kurulamadı.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * PDO bağlantısını döndür
     * @return PDO
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $start = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $this->queryCount++;

        if ($this->logging) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Tablo adını doğrula - SQL Injection koruması
     */
    private function validateTable(string $table): void {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new InvalidArgumentException("Geçersiz tablo adı: {$table}");
        }
    }

    /**
     * Kolon adlarını doğrula - SQL Injection koruması
     */
    private function validateColumns(array $columns): void {
        foreach ($columns as $column) {
            if (!preg_match(self::ALLOWED_COLUMNS_PATTERN, $column)) {
                throw new InvalidArgumentException("Geçersiz kolon adı: {$column}");
            }
        }
    }

    public function insert($table, $data) {
        $this->validateTable($table);
        $this->validateColumns(array_keys($data));

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    /**
     * WHERE clause'u doğrula - SQL Injection koruması
     * Sadece basit kolon karşılaştırmaları ve parametreli sorgular kabul edilir
     */
    private function validateWhereClause(string $where): void {
        // Boş where clause kabul edilmez
        if (empty(trim($where))) {
            throw new InvalidArgumentException("WHERE clause boş olamaz");
        }

        // Tehlikeli SQL keyword'leri kontrol et
        $dangerousKeywords = [
            'DROP', 'TRUNCATE', 'DELETE FROM', 'INSERT INTO', 'UPDATE SET',
            'ALTER', 'CREATE', 'GRANT', 'REVOKE', '--', '/*', '*/', 'UNION',
            'EXEC', 'EXECUTE', 'xp_', 'sp_', 'INFORMATION_SCHEMA'
        ];

        $upperWhere = strtoupper($where);
        foreach ($dangerousKeywords as $keyword) {
            if (strpos($upperWhere, $keyword) !== false) {
                throw new InvalidArgumentException("WHERE clause'da izin verilmeyen keyword: {$keyword}");
            }
        }

        // WHERE clause'daki kolon adlarını doğrula (basit pattern matching)
        // Örnek: "id = :id" veya "id = ?" formatında olmalı
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*(=|!=|<|>|<=|>=|LIKE|IS NULL|IS NOT NULL)\s*(:?[a-zA-Z0-9_]*|\?)(\s+(AND|OR)\s+[a-zA-Z_][a-zA-Z0-9_]*\s*(=|!=|<|>|<=|>=|LIKE|IS NULL|IS NOT NULL)\s*(:?[a-zA-Z0-9_]*|\?))*$/i', $where)) {
            // Daha esnek pattern - en azından parametre kullanıldığından emin ol
            if (strpos($where, ':') === false && strpos($where, '?') === false) {
                throw new InvalidArgumentException("WHERE clause parametresiz değer içeremez. Prepared statement kullanın.");
            }
        }
    }

    public function update($table, $data, $where, $whereParams = []) {
        $this->validateTable($table);
        $this->validateColumns(array_keys($data));
        $this->validateWhereClause($where);

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $this->query($sql, array_merge($data, $whereParams));
    }

    public function delete($table, $where, $params = []): int {
        $this->validateTable($table);
        $this->validateWhereClause($where);

        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    // ========================================
    // TRANSACTION MANAGEMENT
    // ========================================

    /**
     * Transaction başlat
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Transaction onayla
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Transaction geri al
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Transaction içinde callback çalıştır
     * Hata olursa otomatik rollback yapar
     *
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Transaction aktif mi kontrol et
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    // ========================================
    // QUERY STATISTICS
    // ========================================

    /**
     * Toplam sorgu sayısını al
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Sorgu log'unu al (debug mode'da)
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Bağlantı sağlıklı mı kontrol et
     */
    public function isHealthy(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

}

// Kısa erişim fonksiyonu
function db() {
    return Database::getInstance();
}
