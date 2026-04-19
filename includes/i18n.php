<?php
declare(strict_types=1);

/**
 * I18n - Translation & Locale Management
 *
 * Provides a lightweight TR/EN translation layer.
 *
 * Kullanim:
 *   echo e(t('nav.home'));                     // "Ana Sayfa" | "Home"
 *   echo e(t('common.welcome', ['name' => 'Efe'])); // Placeholder replace
 *   I18n::setLocale('en');                     // Session'a yaz
 *   I18n::getLocale();                         // Aktif dil ('tr' | 'en')
 *
 * @package Pastane
 * @since 1.0.0
 */

final class I18n
{
    /**
     * Allowed locale codes (session/cookie poisoning korumasi icin whitelist)
     */
    public const ALLOWED_LOCALES = ['tr', 'en'];

    /**
     * Default locale — locale tespit edilemedigi durumda
     */
    public const DEFAULT_LOCALE = 'tr';

    /**
     * Session anahtari
     */
    public const SESSION_KEY = 'locale';

    /**
     * In-memory translation cache: ['tr' => [...], 'en' => [...]]
     *
     * @var array<string, array>
     */
    private static array $translations = [];

    /**
     * Loaded flag for boot-time init
     */
    private static bool $booted = false;

    /**
     * Aktif locale'i dondur. Once session, sonra cookie, sonra default.
     *
     * @return string
     */
    public static function getLocale(): string
    {
        // Session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionLocale = $_SESSION[self::SESSION_KEY] ?? null;
            if (is_string($sessionLocale) && self::isAllowed($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // Cookie (session baslatilmamis durumlar icin)
        if (isset($_COOKIE['locale']) && is_string($_COOKIE['locale']) && self::isAllowed($_COOKIE['locale'])) {
            return $_COOKIE['locale'];
        }

        return self::DEFAULT_LOCALE;
    }

    /**
     * Locale'i set et (session + cookie). Whitelist disi ise sessizce reddeder.
     *
     * @param string $locale
     * @return bool
     */
    public static function setLocale(string $locale): bool
    {
        if (!self::isAllowed($locale)) {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $locale;
        }

        // Cookie: 1 yil, HttpOnly degil (JS erisebilsin — hassas degil), SameSite=Lax
        if (!headers_sent()) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('locale', $locale, [
                'expires'  => time() + 31_536_000,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
                'secure'   => $isSecure,
            ]);
        }

        return true;
    }

    /**
     * Locale whitelist kontrolu
     *
     * @param string $locale
     * @return bool
     */
    public static function isAllowed(string $locale): bool
    {
        return in_array($locale, self::ALLOWED_LOCALES, true);
    }

    /**
     * Aktif locale dosyasini yukle (lazy, cache'li). Bootstrap'tan cagrilir.
     *
     * Ayrica ?lang=xx query param'ini yakalar ve redirect yapar.
     *
     * @return void
     */
    public static function load(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // ?lang=xx GET param — session'a yaz ve redirect
        if (isset($_GET['lang']) && is_string($_GET['lang'])) {
            $lang = strtolower(trim($_GET['lang']));
            if (self::isAllowed($lang)) {
                self::setLocale($lang);
                self::redirectWithoutLangParam();
            }
        }

        $locale = self::getLocale();
        self::loadLocale($locale);
    }

    /**
     * Belirli bir locale'i belleke yukle (idempotent)
     *
     * @param string $locale
     * @return array<string, mixed>
     */
    public static function loadLocale(string $locale): array
    {
        if (!self::isAllowed($locale)) {
            $locale = self::DEFAULT_LOCALE;
        }

        if (isset(self::$translations[$locale])) {
            return self::$translations[$locale];
        }

        $path = self::localeFilePath($locale);
        if (is_file($path)) {
            /** @var mixed $data */
            $data = include $path;
            if (is_array($data)) {
                self::$translations[$locale] = $data;
                return self::$translations[$locale];
            }
        }

        self::$translations[$locale] = [];
        return self::$translations[$locale];
    }

    /**
     * Locale dosya yolu
     *
     * @param string $locale
     * @return string
     */
    private static function localeFilePath(string $locale): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        return $base . '/lang/' . $locale . '.php';
    }

    /**
     * Key'i cevir. Once aktif locale, yoksa default locale, son olarak key dondurur.
     *
     * @param string $key  Dot-notation ('nav.home') veya flat key ('welcome')
     * @param array<string, int|float|string> $vars  Placeholder map
     * @param string|null $locale  Override locale (test icin)
     * @return string
     */
    public static function translate(string $key, array $vars = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::getLocale();
        $translations = self::loadLocale($locale);

        $value = self::dotGet($translations, $key);

        // Fallback 1: default locale
        if ($value === null && $locale !== self::DEFAULT_LOCALE) {
            $defaults = self::loadLocale(self::DEFAULT_LOCALE);
            $value = self::dotGet($defaults, $key);
        }

        // Fallback 2: key literal + log warning (yalnizca bulunamayan gercek stringler)
        if ($value === null || !is_string($value)) {
            self::logMissingKey($key, $locale);
            return self::interpolate($key, $vars);
        }

        return self::interpolate($value, $vars);
    }

    /**
     * Dot-notation lookup: 'nav.home' -> $array['nav']['home'].
     * Ayni zamanda flat key olarak da bakar (user esnek dot kullanabilir).
     *
     * @param array<mixed> $array
     * @param string $key
     * @return mixed
     */
    private static function dotGet(array $array, string $key): mixed
    {
        // Flat lookup
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Nested lookup
        $segments = explode('.', $key);
        $current = $array;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * `{{name}}` placeholder'larini degistir.
     *
     * @param string $value
     * @param array<string, int|float|string> $vars
     * @return string
     */
    private static function interpolate(string $value, array $vars): string
    {
        if (empty($vars)) {
            return $value;
        }

        foreach ($vars as $name => $replacement) {
            if (!is_string($name)) {
                continue;
            }
            $value = str_replace('{{' . $name . '}}', (string)$replacement, $value);
        }

        return $value;
    }

    /**
     * Bulunamayan key'leri logger'a yaz (throttle: aynisini her isteyiste yazmaz).
     *
     * @param string $key
     * @param string $locale
     * @return void
     */
    private static function logMissingKey(string $key, string $locale): void
    {
        static $seen = [];
        $cacheKey = $locale . '|' . $key;
        if (isset($seen[$cacheKey])) {
            return;
        }
        $seen[$cacheKey] = true;

        // Logger sinifi ve func_exists guard (bootstrap tamamlanmamis olabilir)
        if (class_exists('Logger', false)) {
            try {
                Logger::getInstance()->warning('i18n: missing translation key', [
                    'key'    => $key,
                    'locale' => $locale,
                ]);
            } catch (\Throwable) {
                // sessiz — eksik cevirinin kendi logu hata olmamali
            }
        }
    }

    /**
     * `?lang=xx` GET'ini yakaladiktan sonra ayni URL'ye lang param'i cikarilmis halde redirect.
     *
     * @return void
     */
    private static function redirectWithoutLangParam(): void
    {
        if (headers_sent()) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';

        parse_str($parts['query'] ?? '', $query);
        unset($query['lang']);

        $redirectUrl = $path;
        if (!empty($query)) {
            $redirectUrl .= '?' . http_build_query($query);
        }

        header('Location: ' . $redirectUrl, true, 302);
        exit;
    }

    /**
     * Test/reset amacli — cache'i temizler.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$translations = [];
        self::$booted = false;
    }
}

if (!function_exists('t')) {
    /**
     * Global translation helper.
     *
     * @param string $key
     * @param array<string, int|float|string> $vars
     * @return string
     */
    function t(string $key, array $vars = []): string
    {
        return I18n::translate($key, $vars);
    }
}

if (!function_exists('locale')) {
    /**
     * Aktif locale'i dondurur.
     *
     * @return string
     */
    function locale(): string
    {
        return I18n::getLocale();
    }
}
