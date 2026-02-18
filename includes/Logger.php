<?php
/**
 * Logger - PSR-3 Compatible Logging System
 *
 * Structured logging with multiple channels, log levels, and rotation.
 *
 * @package Pastane
 * @since 1.0.0
 */

class Logger
{
    /**
     * @var Logger|null Singleton instance
     */
    private static ?Logger $instance = null;

    /**
     * @var array Log level priorities
     */
    private const LEVELS = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /**
     * @var string Current log level
     */
    private string $level;

    /**
     * @var string Log directory
     */
    private string $logPath;

    /**
     * @var array Sensitive fields to mask
     */
    private array $sensitiveFields = [
        'password', 'sifre', 'token', 'api_key', 'secret',
        'credit_card', 'cvv', 'authorization'
    ];

    /**
     * Private constructor (Singleton)
     */
    private function __construct()
    {
        $this->level = env('LOG_LEVEL', 'debug');
        $this->logPath = BASE_PATH . '/' . env('LOG_PATH', 'storage/logs');

        // Log dizini yoksa oluştur
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Get singleton instance
     *
     * @return Logger
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Emergency: System is unusable
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * Alert: Action must be taken immediately
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * Critical: Critical conditions
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * Error: Runtime errors
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Warning: Exceptional occurrences that are not errors
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Notice: Normal but significant events
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Info: Interesting events
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Debug: Detailed debug information
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Log to specific channel
     *
     * @param string $channel Channel name (security, audit, api, etc.)
     * @param string $level Log level
     * @param string $message
     * @param array $context
     */
    public function channel(string $channel, string $level, string $message, array $context = []): void
    {
        $this->log($level, $message, $context, $channel);
    }

    /**
     * Log security events
     */
    public function security(string $message, array $context = []): void
    {
        $this->log('info', $message, $context, 'security');
    }

    /**
     * Log audit events
     */
    public function audit(string $message, array $context = []): void
    {
        $context['user_id'] = $_SESSION['user_id'] ?? null;
        $context['ip'] = $this->getClientIp();
        $this->log('info', $message, $context, 'audit');
    }

    /**
     * Log API requests
     */
    public function api(string $message, array $context = []): void
    {
        $context['method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $context['uri'] = $_SERVER['REQUEST_URI'] ?? '';
        $this->log('info', $message, $context, 'api');
    }

    /**
     * Main log method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @param string $channel
     */
    private function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        // Level kontrolü
        if (!$this->shouldLog($level)) {
            return;
        }

        // Context'i maskele
        $context = $this->maskSensitiveData($context);

        // Log entry oluştur
        $entry = $this->formatEntry($level, $message, $context);

        // Dosyaya yaz
        $this->writeToFile($channel, $entry);
    }

    /**
     * Check if should log based on level
     */
    private function shouldLog(string $level): bool
    {
        $currentPriority = self::LEVELS[$this->level] ?? 7;
        $messagePriority = self::LEVELS[$level] ?? 7;

        return $messagePriority <= $currentPriority;
    }

    /**
     * Mask sensitive data
     */
    private function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif ($this->isSensitive($key)) {
                $data[$key] = '********';
            }
        }
        return $data;
    }

    /**
     * Check if field is sensitive
     */
    private function isSensitive(string $field): bool
    {
        $field = strtolower($field);
        foreach ($this->sensitiveFields as $sensitive) {
            if (str_contains($field, $sensitive)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format log entry
     */
    private function formatEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        // Context'i JSON'a çevir
        $contextJson = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        return "[{$timestamp}] [{$levelUpper}] {$message}{$contextJson}" . PHP_EOL;
    }

    /**
     * Write to log file
     */
    private function writeToFile(string $channel, string $entry): void
    {
        $filename = $this->logPath . '/' . $channel . '.log';

        // Dosya boyutu kontrolü (10MB'dan büyükse rotate)
        if (file_exists($filename) && filesize($filename) > 10485760) {
            $this->rotateLog($filename);
        }

        file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Rotate log file
     */
    private function rotateLog(string $filename): void
    {
        $rotatedName = $filename . '.' . date('Y-m-d-His');
        rename($filename, $rotatedName);

        // Eski log dosyalarını temizle (30 günden eski)
        $this->cleanOldLogs($this->logPath, 30);
    }

    /**
     * Clean old log files
     */
    private function cleanOldLogs(string $path, int $days): void
    {
        $files = glob($path . '/*.log.*');
        $cutoff = time() - ($days * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    /**
     * Get client IP
     */
    private function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get recent logs
     *
     * @param string $channel
     * @param int $lines
     * @return array
     */
    public function getRecentLogs(string $channel = 'app', int $lines = 100): array
    {
        $filename = $this->logPath . '/' . $channel . '.log';

        if (!file_exists($filename)) {
            return [];
        }

        $file = new SplFileObject($filename, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $logs = [];
        $start = max(0, $lastLine - $lines);

        $file->seek($start);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }

        return $logs;
    }

    /**
     * Clear log file
     *
     * @param string $channel
     */
    public function clearLog(string $channel = 'app'): void
    {
        $filename = $this->logPath . '/' . $channel . '.log';
        if (file_exists($filename)) {
            file_put_contents($filename, '');
        }
    }
}
