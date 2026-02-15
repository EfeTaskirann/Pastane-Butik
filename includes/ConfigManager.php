<?php
/**
 * Configuration Manager
 *
 * Tüm config dosyalarını yükler ve erişim sağlar.
 * Dot notation destekler: config('app.name')
 *
 * @package Pastane
 * @since 1.0.0
 */

class Config
{
    /**
     * @var array Yüklenen konfigürasyonlar
     */
    private static array $config = [];

    /**
     * @var bool Config yüklenip yüklenmediği
     */
    private static bool $loaded = false;

    /**
     * @var string Config dizini
     */
    private static string $configPath = '';

    /**
     * Config dosyalarını yükle
     *
     * @param string $path Config dizini
     * @return void
     */
    public static function load(string $path): void
    {
        self::$configPath = rtrim($path, DIRECTORY_SEPARATOR);

        $files = glob(self::$configPath . DIRECTORY_SEPARATOR . '*.php');

        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, '.php');
                self::$config[$name] = require $file;
            }
        }

        self::$loaded = true;
    }

    /**
     * Config değeri al
     *
     * @param string $key Dot notation key (örn: 'app.name', 'database.connections.mysql.host')
     * @param mixed $default Varsayılan değer
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Config değeri ayarla (runtime)
     *
     * @param string $key Dot notation key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $config = &self::$config;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $config[$segment] = $value;
            } else {
                if (!isset($config[$segment]) || !is_array($config[$segment])) {
                    $config[$segment] = [];
                }
                $config = &$config[$segment];
            }
        }
    }

    /**
     * Config değerinin varlığını kontrol et
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Tüm config'i veya bir bölümü al
     *
     * @param string|null $section
     * @return array
     */
    public static function all(?string $section = null): array
    {
        if ($section !== null) {
            return self::$config[$section] ?? [];
        }
        return self::$config;
    }

    /**
     * Config yüklenip yüklenmediğini kontrol et
     *
     * @return bool
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /**
     * Config'i yeniden yükle
     *
     * @return void
     */
    public static function reload(): void
    {
        self::$config = [];
        self::$loaded = false;
        self::load(self::$configPath);
    }

    /**
     * Environment'a göre config dosyası yükle
     *
     * @param string $environment
     * @return void
     */
    public static function loadEnvironmentConfig(string $environment): void
    {
        $envConfigPath = self::$configPath . DIRECTORY_SEPARATOR . $environment;

        if (is_dir($envConfigPath)) {
            $files = glob($envConfigPath . DIRECTORY_SEPARATOR . '*.php');

            if ($files) {
                foreach ($files as $file) {
                    $name = basename($file, '.php');
                    $envConfig = require $file;

                    if (isset(self::$config[$name])) {
                        self::$config[$name] = array_replace_recursive(
                            self::$config[$name],
                            $envConfig
                        );
                    } else {
                        self::$config[$name] = $envConfig;
                    }
                }
            }
        }
    }
}

/**
 * Global config() helper fonksiyonu
 *
 * @param string|null $key
 * @param mixed $default
 * @return mixed
 */
if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Config::all();
        }

        return Config::get($key, $default);
    }
}
