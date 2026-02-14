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
        'cache'
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

    public function update($table, $data, $where, $whereParams = []) {
        $this->validateTable($table);
        $this->validateColumns(array_keys($data));

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $this->query($sql, array_merge($data, $whereParams));
    }

    public function delete($table, $where, $params = []) {
        $this->validateTable($table);

        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->query($sql, $params);
    }
}

// Kısa erişim fonksiyonu
function db() {
    return Database::getInstance();
}
