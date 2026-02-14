<?php
/**
 * Environment Variable Loader
 *
 * .env dosyalarını okur ve $_ENV, $_SERVER ve getenv() ile erişilebilir yapar.
 * Production ortamında .env dosyası yerine gerçek environment variables kullanılmalı.
 *
 * @package Pastane
 * @since 1.0.0
 */

class Env
{
    /**
     * @var array Yüklenen environment değişkenleri
     */
    private static array $variables = [];

    /**
     * @var bool Env dosyası yüklenip yüklenmediği
     */
    private static bool $loaded = false;

    /**
     * @var string Proje kök dizini
     */
    private static string $basePath = '';

    /**
     * .env dosyasını yükle
     *
     * @param string $path .env dosyasının bulunduğu dizin
     * @param string $file Dosya adı (varsayılan: .env)
     * @return void
     */
    public static function load(string $path, string $file = '.env'): void
    {
        self::$basePath = rtrim($path, DIRECTORY_SEPARATOR);
        $filePath = self::$basePath . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($filePath)) {
            // Production'da .env dosyası olmayabilir, sistem env vars kullanılır
            self::$loaded = true;
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Yorum satırlarını atla
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // KEY=VALUE formatını parse et
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = self::parseValue(trim($value));

                // Değişken referanslarını çöz (${VAR} formatı)
                $value = self::resolveVariables($value);

                self::$variables[$name] = $value;

                // Eğer zaten sistem env var olarak tanımlı değilse, tanımla
                if (!array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                    putenv("{$name}={$value}");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Environment değişkeni al
     *
     * @param string $key Değişken adı
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Önce yüklenen değişkenlerde ara
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        // Sonra $_ENV'de ara
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Son olarak getenv() ile dene
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Environment değişkeninin varlığını kontrol et
     *
     * @param string $key Değişken adı
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset(self::$variables[$key])
            || isset($_ENV[$key])
            || getenv($key) !== false;
    }

    /**
     * Tüm yüklenen değişkenleri al
     *
     * @return array
     */
    public static function all(): array
    {
        return self::$variables;
    }

    /**
     * Environment değişkeni ayarla (runtime)
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    /**
     * Zorunlu environment değişkeni al
     *
     * @param string $key
     * @return mixed
     * @throws RuntimeException Değişken tanımlı değilse
     */
    public static function getRequired(string $key): mixed
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException(
                "Required environment variable [{$key}] is not defined."
            );
        }

        return $value;
    }

    /**
     * Proje kök dizinini al
     *
     * @return string
     */
    public static function basePath(): string
    {
        return self::$basePath;
    }

    /**
     * Value parsing - tırnak işaretlerini ve özel değerleri işle
     *
     * @param string $value
     * @return mixed
     */
    private static function parseValue(string $value): mixed
    {
        // Tırnak işaretlerini kaldır
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Özel değerleri dönüştür
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value
        };
    }

    /**
     * Değişken referanslarını çöz: ${VAR_NAME} veya $VAR_NAME
     *
     * @param mixed $value
     * @return mixed
     */
    private static function resolveVariables(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // ${VAR} formatını çöz
        if (preg_match_all('/\${([a-zA-Z_][a-zA-Z0-9_]*)}/', $value, $matches)) {
            foreach ($matches[1] as $index => $varName) {
                $replacement = self::get($varName, '');
                $value = str_replace($matches[0][$index], $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * Environment yüklenip yüklenmediğini kontrol et
     *
     * @return bool
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}

/**
 * Global env() helper fonksiyonu
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}
