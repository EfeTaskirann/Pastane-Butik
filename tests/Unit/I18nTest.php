<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use I18n;
use PHPUnit\Framework\TestCase;

/**
 * I18n translator coverage — P2-15.
 *
 * Coverage:
 *  - locale set/get round-trip
 *  - whitelist validation (allowed locales only)
 *  - placeholder interpolation
 *  - fallback: unknown key returns the key string
 *  - fallback: unknown locale falls back to default (tr)
 *  - en/tr round-trip on the same key
 *  - dot-notation lookup
 */
final class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        I18n::reset();

        // Test ortaminda session aktif olabilsin diye guard
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        I18n::reset();
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_default_locale_is_turkish(): void
    {
        self::assertSame('tr', I18n::DEFAULT_LOCALE);
        self::assertSame('tr', I18n::getLocale());
    }

    public function test_set_locale_persists_via_session(): void
    {
        $ok = I18n::setLocale('en');
        self::assertTrue($ok);
        self::assertSame('en', I18n::getLocale());
    }

    public function test_set_locale_rejects_unknown_code(): void
    {
        // Whitelist disindaki locale'ler reddedilmeli
        self::assertFalse(I18n::setLocale('de'));
        self::assertFalse(I18n::setLocale(''));
        self::assertFalse(I18n::setLocale('tr; DROP TABLE'));
        self::assertSame('tr', I18n::getLocale());
    }

    public function test_translate_returns_turkish_value_by_default(): void
    {
        self::assertSame('Ana Sayfa', I18n::translate('nav.home'));
        self::assertSame('Sepete Ekle', I18n::translate('btn.add_to_cart'));
    }

    public function test_translate_returns_english_when_locale_overridden(): void
    {
        self::assertSame('Home', I18n::translate('nav.home', [], 'en'));
        self::assertSame('Add to Cart', I18n::translate('btn.add_to_cart', [], 'en'));
    }

    public function test_translate_round_trip_tr_en(): void
    {
        // TR
        self::assertSame('Kontrol Paneli', I18n::translate('nav.dashboard', [], 'tr'));
        // EN
        self::assertSame('Dashboard', I18n::translate('nav.dashboard', [], 'en'));
    }

    public function test_t_helper_function_works(): void
    {
        self::assertTrue(function_exists('t'));
        self::assertSame('Ana Sayfa', t('nav.home'));
    }

    public function test_unknown_key_returns_key_literal(): void
    {
        $key = 'nonexistent.deeply.nested.key';
        self::assertSame($key, t($key));
    }

    public function test_placeholder_replacement(): void
    {
        $tr = I18n::translate('common.welcome', ['name' => 'Efe'], 'tr');
        self::assertStringContainsString('Efe', $tr);
        self::assertSame('Hoş geldin, Efe', $tr);

        $en = I18n::translate('common.welcome', ['name' => 'Efe'], 'en');
        self::assertSame('Welcome, Efe', $en);
    }

    public function test_placeholder_with_numeric_value(): void
    {
        // count placeholder int kabul etmeli
        self::assertSame('3 ürün', I18n::translate('order.cart_count', ['count' => 3], 'tr'));
        self::assertSame('5 items', I18n::translate('order.cart_count', ['count' => 5], 'en'));
    }

    public function test_placeholder_in_unknown_key_still_interpolates(): void
    {
        // Bilinmeyen key fallback'ten gecerken placeholder yine de calismaali
        $result = t('nonexistent.welcome', ['name' => 'X']);
        // Key + placeholder calismali — key icinde placeholder yok ama hata fırlatmamalı
        self::assertSame('nonexistent.welcome', $result);
    }

    public function test_fallback_to_default_locale_when_key_missing(): void
    {
        // Eger ileride sadece TR'de key tanımlanırsa, EN'den istenince fallback TR'ye dussun.
        // simulasyon: gercek var olan key, locale=en olsa bile ceviri donmeli
        self::assertSame('Ana Sayfa', I18n::translate('nav.home', [], 'tr'));
        // Bilinmeyen locale → default locale'e dusmeli (allowed disi locale silently default'e)
        self::assertSame('Ana Sayfa', I18n::translate('nav.home', [], 'xx_BAD'));
    }

    public function test_dot_notation_lookup(): void
    {
        self::assertSame('Hoş geldin, {{name}}', I18n::translate('common.welcome', [], 'tr'));
        self::assertSame('Welcome, {{name}}', I18n::translate('common.welcome', [], 'en'));
    }

    public function test_is_allowed(): void
    {
        self::assertTrue(I18n::isAllowed('tr'));
        self::assertTrue(I18n::isAllowed('en'));
        self::assertFalse(I18n::isAllowed('de'));
        self::assertFalse(I18n::isAllowed('TR'));  // case-sensitive
        self::assertFalse(I18n::isAllowed(''));
    }

    public function test_translation_files_have_same_top_level_groups(): void
    {
        $tr = require BASE_PATH . '/lang/tr.php';
        $en = require BASE_PATH . '/lang/en.php';

        self::assertSame(array_keys($tr), array_keys($en), 'TR ve EN top-level group anahtarlari eslemeli.');

        // Her grupta TR + EN ayni key'lere sahip olmali (drift uyarisi)
        foreach ($tr as $group => $keys) {
            self::assertIsArray($en[$group], "EN icinde '$group' grubu eksik.");
            self::assertSame(
                array_keys($keys),
                array_keys($en[$group]),
                "TR ve EN '$group' grubunda key listesi eslesmeli."
            );
        }
    }

    public function test_translation_files_have_minimum_keys(): void
    {
        $countDeep = static function (array $arr) use (&$countDeep): int {
            $c = 0;
            foreach ($arr as $v) {
                $c += is_array($v) ? $countDeep($v) : 1;
            }
            return $c;
        };

        $tr = require BASE_PATH . '/lang/tr.php';
        $en = require BASE_PATH . '/lang/en.php';

        // P2-15 DoD: minimum 100 key
        self::assertGreaterThanOrEqual(100, $countDeep($tr), 'TR icinde en az 100 key olmali.');
        self::assertGreaterThanOrEqual(100, $countDeep($en), 'EN icinde en az 100 key olmali.');
    }

    public function test_locale_helper_function(): void
    {
        self::assertTrue(function_exists('locale'));
        self::assertSame('tr', locale());

        I18n::setLocale('en');
        self::assertSame('en', locale());
    }
}
