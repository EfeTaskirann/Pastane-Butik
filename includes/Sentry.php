<?php
declare(strict_types=1);

/**
 * Sentry — Native (library-free) Sentry client
 *
 * Kütüphane (sentry/sentry) bulunmadığı durumda, Sentry'nin public
 * "envelope" endpoint'ine HTTP POST ile event gönderir. DSN boşsa no-op.
 *
 * DSN formatı (resmî):
 *   https://<PUBLIC_KEY>@<HOST>[:<PORT>]/<PROJECT_ID>
 *
 * Endpoint:
 *   POST https://<HOST>/api/<PROJECT_ID>/envelope/
 *   Header: X-Sentry-Auth: Sentry sentry_version=7,
 *                          sentry_client=pastane-native/1.0,
 *                          sentry_timestamp=<unix>,
 *                          sentry_key=<PUBLIC_KEY>
 *
 * Özellikler:
 *  - set_error_handler + set_exception_handler + register_shutdown_function
 *  - PII scrubber: password, sifre, credit_card, csrf_token, token, secret,
 *                  api_key, cvv, authorization, cookie
 *  - Tag / user / extra context support
 *  - Non-blocking best-effort delivery (HTTP timeout 2 sn, suppress errors)
 *
 * @package Pastane
 * @since 1.1.0-sprint1
 */

class Sentry
{
    /** @var string|null Parse edilmiş public key */
    private static ?string $publicKey = null;

    /** @var string|null Parse edilmiş project id */
    private static ?string $projectId = null;

    /** @var string|null Parse edilmiş host (port dâhil) */
    private static ?string $host = null;

    /** @var string|null Envelope endpoint */
    private static ?string $endpoint = null;

    /** @var string Environment (development/staging/production) */
    private static string $environment = 'development';

    /** @var float Traces sample rate (0.0 .. 1.0) — şu an sadece event rate'i için */
    private static float $sampleRate = 1.0;

    /** @var float Traces sample rate — isteğe bağlı */
    private static float $tracesSampleRate = 0.0;

    /** @var bool Init edildi mi? */
    private static bool $initialized = false;

    /** @var bool Aktif mi? (DSN varsa true) */
    private static bool $enabled = false;

    /** @var array<string,string> Etiketler */
    private static array $tags = [];

    /** @var array<string,mixed> Kullanıcı bilgisi (scrub edilecek) */
    private static array $user = [];

    /** @var array<string,mixed> Extra context */
    private static array $extra = [];

    /** @var array<int,string> PII alan isimleri (case-insensitive substring) */
    private static array $piiFields = [
        'password', 'sifre', 'parola',
        'credit_card', 'kredi_karti', 'card_number', 'card_no',
        'cvv', 'cvc',
        'csrf_token', 'csrf',
        'token', 'api_key', 'apikey', 'secret',
        'authorization', 'auth',
        'cookie', 'session_id',
    ];

    /**
     * Bootstrap. DSN boşsa no-op.
     *
     * @param array{dsn?:string,environment?:string,sample_rate?:float,traces_sample_rate?:float,release?:string} $config
     */
    public static function init(array $config = []): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $dsn = $config['dsn'] ?? (function_exists('env') ? env('SENTRY_DSN', '') : '');
        $env = $config['environment']
            ?? (function_exists('env') ? env('SENTRY_ENVIRONMENT', 'development') : 'development');
        $sampleRate = (float) ($config['sample_rate'] ?? 1.0);
        $tracesSampleRate = (float) (
            $config['traces_sample_rate']
            ?? (function_exists('env') ? env('SENTRY_TRACES_SAMPLE_RATE', 0.0) : 0.0)
        );

        self::$environment = is_string($env) && $env !== '' ? $env : 'development';
        self::$sampleRate = max(0.0, min(1.0, $sampleRate));
        self::$tracesSampleRate = max(0.0, min(1.0, $tracesSampleRate));

        if (!is_string($dsn) || $dsn === '') {
            self::$enabled = false;
            return;
        }

        if (!self::parseDsn($dsn)) {
            self::$enabled = false;
            return;
        }

        self::$enabled = true;

        // Global handler'ları bağla (önceki handler'ları override etmeden önce chain et)
        $previousException = set_exception_handler(null);
        set_exception_handler(static function (\Throwable $e) use ($previousException): void {
            try {
                self::captureException($e);
            } catch (\Throwable) {
                // swallow — Sentry kendisi hata atmamalı
            }
            if (is_callable($previousException)) {
                $previousException($e);
            }
        });

        $previousError = set_error_handler(null);
        set_error_handler(
            static function (int $severity, string $message, string $file = '', int $line = 0) use ($previousError): bool {
                // Sadece fatal seviyedekileri Sentry'ye gönder
                if (in_array($severity, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    try {
                        self::captureMessage($message, 'error', [
                            'file' => $file,
                            'line' => $line,
                            'severity' => $severity,
                        ]);
                    } catch (\Throwable) {
                        // noop
                    }
                }
                if (is_callable($previousError)) {
                    return (bool) $previousError($severity, $message, $file, $line);
                }
                return false; // PHP'nin default error handler'ına devret
            }
        );

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err === null) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (in_array($err['type'], $fatal, true)) {
                try {
                    self::captureMessage(
                        '[FATAL] ' . $err['message'],
                        'fatal',
                        ['file' => $err['file'] ?? '', 'line' => $err['line'] ?? 0]
                    );
                } catch (\Throwable) {
                    // noop
                }
            }
        });
    }

    /**
     * DSN'yi parse eder. Başarılıysa true.
     */
    private static function parseDsn(string $dsn): bool
    {
        $parts = parse_url($dsn);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $user = $parts['user'] ?? '';
        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if ($scheme === '' || $host === '' || $user === '' || $path === '') {
            return false;
        }
        // Path: /<project_id>  — başka segment olmamalı
        $projectId = ltrim($path, '/');
        if ($projectId === '' || !preg_match('/^\d+$/', $projectId)) {
            return false;
        }

        self::$publicKey = $user;
        self::$projectId = $projectId;
        self::$host = $host . $port;
        self::$endpoint = sprintf('%s://%s/api/%s/envelope/', $scheme, self::$host, $projectId);

        return true;
    }

    /**
     * Exception'ı Sentry'ye gönder.
     *
     * @param array<string,mixed> $context extra payload
     */
    public static function captureException(\Throwable $e, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }
        if (!self::shouldSample()) {
            return;
        }

        $frames = [];
        foreach ($e->getTrace() as $t) {
            $frames[] = [
                'function' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
                'filename' => $t['file'] ?? '[internal]',
                'lineno'   => $t['line'] ?? 0,
            ];
        }
        // Sentry stacktrace en eski frame başta olacak şekilde bekler
        $frames = array_reverse($frames);

        $event = self::baseEvent('error');
        $event['exception'] = [
            'values' => [[
                'type'  => get_class($e),
                'value' => $e->getMessage(),
                'stacktrace' => ['frames' => $frames],
            ]],
        ];
        $event['extra'] = self::scrub(array_merge(self::$extra, $context));

        self::send($event);
    }

    /**
     * Mesaj gönder.
     *
     * @param array<string,mixed> $context
     */
    public static function captureMessage(string $msg, string $level = 'info', array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }
        if (!self::shouldSample()) {
            return;
        }

        $valid = ['debug', 'info', 'warning', 'error', 'fatal'];
        if (!in_array($level, $valid, true)) {
            $level = 'info';
        }

        $event = self::baseEvent($level);
        $event['message'] = ['formatted' => $msg];
        $event['extra'] = self::scrub(array_merge(self::$extra, $context));

        self::send($event);
    }

    /**
     * Kullanıcıyı ayarla (scope). PII scrub edilir.
     *
     * @param array<string,mixed> $user
     */
    public static function setUser(array $user): void
    {
        self::$user = self::scrub($user);
    }

    /**
     * Tag ekle.
     */
    public static function setTag(string $key, string $value): void
    {
        self::$tags[$key] = $value;
    }

    /**
     * Extra context'i set et.
     *
     * @param array<string,mixed> $extra
     */
    public static function setExtra(array $extra): void
    {
        self::$extra = array_merge(self::$extra, self::scrub($extra));
    }

    /**
     * Aktif durum (DSN parse başarılı mı?).
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Test amaçlı: init sonrası parse edilen endpoint'i döner (yoksa null).
     */
    public static function debugEndpoint(): ?string
    {
        return self::$endpoint;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Event payload iskeleti.
     *
     * @return array<string,mixed>
     */
    private static function baseEvent(string $level): array
    {
        return [
            'event_id' => self::uuid4(),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'platform' => 'php',
            'level'    => $level,
            'logger'   => 'pastane',
            'server_name' => gethostname() ?: 'unknown',
            'environment' => self::$environment,
            'release'  => function_exists('env') ? (string) env('APP_VERSION', '1.0.0-sprint1') : '1.0.0-sprint1',
            'sdk' => [
                'name'    => 'pastane.sentry-native',
                'version' => '1.0.0',
            ],
            'tags' => self::$tags,
            'user' => !empty(self::$user) ? self::$user : null,
            'request' => self::requestContext(),
        ];
    }

    /**
     * Request context (URL, method, headers — scrubbed).
     *
     * @return array<string,mixed>|null
     */
    private static function requestContext(): ?array
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return [
            'url'     => $scheme . '://' . $host . $uri,
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'headers' => self::scrub(self::collectHeaders()),
        ];
    }

    /**
     * HTTP header'ları topla.
     *
     * @return array<string,string>
     */
    private static function collectHeaders(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $out[$name] = is_string($v) ? $v : json_encode($v);
            }
        }
        return $out;
    }

    /**
     * PII scrubber — password/token/csrf/credit-card değerlerini `[Filtered]` yapar.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::scrub($v);
                continue;
            }
            if (self::isPii((string) $k)) {
                $data[$k] = '[Filtered]';
                continue;
            }
            if (is_string($v) && self::looksLikeCardNumber($v)) {
                $data[$k] = '[Filtered]';
            }
        }
        return $data;
    }

    private static function isPii(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::$piiFields as $needle) {
            if (strpos($k, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function looksLikeCardNumber(string $v): bool
    {
        $digits = preg_replace('/\D+/', '', $v);
        $len = strlen((string) $digits);
        return $len >= 13 && $len <= 19;
    }

    /**
     * Sample rate kontrolü.
     */
    private static function shouldSample(): bool
    {
        if (self::$sampleRate >= 1.0) {
            return true;
        }
        if (self::$sampleRate <= 0.0) {
            return false;
        }
        return (mt_rand() / mt_getrandmax()) < self::$sampleRate;
    }

    /**
     * Envelope formatında POST et. Best-effort.
     *
     * @param array<string,mixed> $event
     */
    private static function send(array $event): bool
    {
        if (!self::$enabled || self::$endpoint === null || self::$publicKey === null) {
            return false;
        }

        $eventJson = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($eventJson === false) {
            return false;
        }

        // Envelope format: header \n item_header \n item_payload
        $envelopeHeader = json_encode([
            'event_id' => $event['event_id'],
            'sent_at'  => gmdate('Y-m-d\TH:i:s\Z'),
            'dsn'      => sprintf('https://%s@%s/%s', self::$publicKey, self::$host, self::$projectId),
        ], JSON_UNESCAPED_SLASHES);

        $itemHeader = json_encode([
            'type' => 'event',
            'length' => strlen($eventJson),
            'content_type' => 'application/json',
        ]);

        $body = $envelopeHeader . "\n" . $itemHeader . "\n" . $eventJson;

        $authHeader = sprintf(
            'Sentry sentry_version=7,sentry_client=pastane-native/1.0,sentry_timestamp=%d,sentry_key=%s',
            time(),
            self::$publicKey
        );

        // cURL varsa onu kullan, yoksa stream context
        if (function_exists('curl_init')) {
            $ch = curl_init(self::$endpoint);
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-sentry-envelope',
                    'X-Sentry-Auth: ' . $authHeader,
                ],
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/x-sentry-envelope',
                    'X-Sentry-Auth: ' . $authHeader,
                ]),
                'content' => $body,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents(self::$endpoint, false, $ctx);
        return $resp !== false;
    }

    /**
     * UUID v4 (lowercase, dash'sız — Sentry event_id formatı).
     */
    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        return bin2hex($bytes);
    }
}
