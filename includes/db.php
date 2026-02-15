<?php
/**
 * Veritabanı Bağlantısı (PDO)
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

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
        'rate_limits',
        'cache',
        // Codex + Antigravity tarafından tespit edilen eksik tablolar
        'iletisim_mesajlari',
        'admin_kullanicilar',
        'siparis_arsiv',
        'odemeler'
    ];

    /**
     * İzin verilen kolon adları - SQL Injection koruması
     */
    private const ALLOWED_COLUMNS_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * WHERE clause için izin verilen operatörler
     */
    private const ALLOWED_WHERE_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Veritabanı bağlantı hatası: " . $e->getMessage());
            } else {
                die("Bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * PDO bağlantısını döndür (alias)
     * @return PDO
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
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

    /**
     * Güvenli update - array based where clause
     * Örnek: updateSafe('users', ['name' => 'John'], ['id' => 5])
     */
    public function updateSafe(string $table, array $data, array $conditions): void {
        $this->validateTable($table);
        $this->validateColumns(array_keys($data));
        $this->validateColumns(array_keys($conditions));

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :set_{$column}";
        }

        $where = [];
        foreach (array_keys($conditions) as $column) {
            $where[] = "{$column} = :where_{$column}";
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);

        $params = [];
        foreach ($data as $key => $value) {
            $params["set_{$key}"] = $value;
        }
        foreach ($conditions as $key => $value) {
            $params["where_{$key}"] = $value;
        }

        $this->query($sql, $params);
    }

    public function delete($table, $where, $params = []) {
        $this->validateTable($table);
        $this->validateWhereClause($where);

        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->query($sql, $params);
    }

    /**
     * Güvenli delete - array based where clause
     * Örnek: deleteSafe('users', ['id' => 5])
     */
    public function deleteSafe(string $table, array $conditions): void {
        $this->validateTable($table);
        $this->validateColumns(array_keys($conditions));

        $where = [];
        foreach (array_keys($conditions) as $column) {
            $where[] = "{$column} = :{$column}";
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where);
        $this->query($sql, $conditions);
    }
}

// Kısa erişim fonksiyonu
function db() {
    return Database::getInstance();
}
