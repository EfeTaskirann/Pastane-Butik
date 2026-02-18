<?php

declare(strict_types=1);

namespace Pastane\Exceptions;

use Exception;
use Throwable;

/**
 * Application-Level Exception Handler
 *
 * Global exception handler, error handler ve shutdown handler.
 * Bootstrap'ta register edilir, tüm yakalanmamış hataları yakalar.
 *
 * @package Pastane\Exceptions
 * @since 1.1.0
 */
class AppException
{
    /**
     * @var bool Debug mode active?
     */
    private static bool $debug = false;

    /**
     * @var bool Is API request?
     */
    private static bool $isApi = false;

    /**
     * @var bool Already registered?
     */
    private static bool $registered = false;

    /**
     * Register global error/exception handlers
     *
     * @param bool $debug
     * @return void
     */
    public static function register(bool $debug = false): void
    {
        if (self::$registered) {
            return;
        }

        self::$debug = $debug;
        self::$isApi = self::detectApiRequest();
        self::$registered = true;

        // Global exception handler
        set_exception_handler([self::class, 'handleException']);

        // PHP error → ErrorException dönüştürücü
        set_error_handler([self::class, 'handleError']);

        // Fatal error handler (parse error, out of memory vb.)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $e
     * @return void
     */
    public static function handleException(Throwable $e): void
    {
        // Log the error
        self::logException($e);

        // Determine status code
        $statusCode = 500;
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
        }

        // API request → JSON response
        if (self::$isApi) {
            self::sendJsonError($e, $statusCode);
            return;
        }

        // Admin/Frontend request → HTML error page
        self::sendHtmlError($e, $statusCode);
    }

    /**
     * Convert PHP errors to ErrorException
     *
     * @param int $level Error level (E_WARNING, E_NOTICE, etc.)
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @throws \ErrorException
     */
    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        // error_reporting() seviyesine göre filtrele
        // '@' operatörü kullanıldığında error_reporting 0 döner
        if (!(error_reporting() & $level)) {
            return false;
        }

        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Handle fatal errors on shutdown
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }

    /**
     * Log exception using Logger class (or error_log as fallback)
     *
     * @param Throwable $e
     * @return void
     */
    private static function logException(Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        // HttpException'lar 4xx ise warning, 5xx ise error
        $level = 'error';
        if ($e instanceof HttpException) {
            $level = $e->getStatusCode() < 500 ? 'warning' : 'error';
        }

        try {
            if (class_exists('Logger', false)) {
                $logger = \Logger::getInstance();
                $logger->$level($e->getMessage(), $context);
            } else {
                error_log(sprintf(
                    "[%s] %s in %s:%d\n%s",
                    strtoupper($level),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                ));
            }
        } catch (Throwable) {
            // Logger da fail ederse, en azından error_log'a yaz
            error_log("FATAL: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Send JSON error response (API context)
     *
     * @param Throwable $e
     * @param int $statusCode
     * @return void
     */
    private static function sendJsonError(Throwable $e, int $statusCode): void
    {
        // Headers zaten gönderilmişse tekrar gönderme
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => false,
            'error' => self::$debug ? $e->getMessage() : self::getPublicMessage($statusCode),
        ];

        // Validation errors
        if ($e instanceof ValidationException) {
            $response['error'] = $e->getMessage(); // Validation mesajları her zaman gösterilir
            $response['errors'] = $e->getErrors();
        }

        // Debug modda ek bilgi
        if (self::$debug && $statusCode >= 500) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Send HTML error response (admin/frontend context)
     *
     * @param Throwable $e
     * @param int $statusCode
     * @return void
     */
    private static function sendHtmlError(Throwable $e, int $statusCode): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $message = self::$debug ? e_safe($e->getMessage()) : self::getPublicMessage($statusCode);

        // Debug modda detaylı bilgi
        $debugInfo = '';
        if (self::$debug) {
            $debugInfo = sprintf(
                '<div style="margin-top:20px;padding:15px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;font-family:monospace;font-size:13px;overflow-x:auto;">'
                . '<strong>%s</strong> in %s:%d<br><pre style="margin-top:10px;">%s</pre></div>',
                e_safe(get_class($e)),
                e_safe($e->getFile()),
                $e->getLine(),
                e_safe($e->getTraceAsString())
            );
        }

        echo sprintf(
            '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata %d</title>'
            . '<style>body{font-family:system-ui,-apple-system,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5;}'
            . '.error-box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.1);max-width:600px;text-align:center;}'
            . 'h1{color:#e74c3c;margin:0 0 10px;font-size:48px;}p{color:#666;font-size:16px;}'
            . 'a{color:#3498db;text-decoration:none;}a:hover{text-decoration:underline;}</style></head>'
            . '<body><div class="error-box"><h1>%d</h1><p>%s</p>%s<p style="margin-top:20px;"><a href="/">Ana Sayfaya Dön</a></p></div></body></html>',
            $statusCode,
            $statusCode,
            $message,
            $debugInfo
        );
    }

    /**
     * Get public-facing error message (production-safe)
     *
     * @param int $statusCode
     * @return string
     */
    private static function getPublicMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Geçersiz istek.',
            401 => 'Yetkilendirme gerekli.',
            403 => 'Bu işlem için yetkiniz yok.',
            404 => 'Aradığınız sayfa bulunamadı.',
            405 => 'Bu metod desteklenmiyor.',
            422 => 'Doğrulama hatası.',
            429 => 'Çok fazla istek. Lütfen bekleyiniz.',
            500 => 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
            503 => 'Sistem bakımda. Lütfen daha sonra tekrar deneyin.',
            default => 'Bir hata oluştu.',
        };
    }

    /**
     * Detect if current request is API context
     *
     * @return bool
     */
    private static function detectApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        // URL /api/ ile başlıyorsa
        if (str_contains($uri, '/api/')) {
            return true;
        }

        // Accept header JSON istiyorsa
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // XHR isteği ise
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }
}

/**
 * HTML escape helper (AppException içinde kullanılır)
 * helpers.php yüklenmemiş olabilir, bu yüzden ayrı tanımlıyoruz.
 */
if (!function_exists('e_safe')) {
    function e_safe(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8', false);
    }
}
