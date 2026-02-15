<?php
/**
 * Helper Functions Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;

class HelpersTest extends TestCase
{
    /**
     * @test
     */
    public function it_escapes_html_entities(): void
    {
        $result = e('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * @test
     */
    public function it_handles_null_values_in_escape(): void
    {
        $result = e(null);

        $this->assertEquals('', $result);
    }

    /**
     * @test
     */
    public function it_generates_slugs(): void
    {
        $result = str_slug('Hello World Test');

        $this->assertEquals('hello-world-test', $result);
    }

    /**
     * @test
     */
    public function it_handles_turkish_characters_in_slugs(): void
    {
        $result = str_slug('Türkçe Karakterler İÖÜŞĞÇ');

        $this->assertStringNotContainsString('İ', $result);
        $this->assertStringNotContainsString('Ö', $result);
        $this->assertStringNotContainsString('Ü', $result);
    }

    /**
     * @test
     */
    public function it_limits_strings(): void
    {
        $long = 'This is a very long string that needs to be truncated';
        $result = str_limit($long, 20);

        $this->assertEquals(23, strlen($result)); // 20 chars + '...'
        $this->assertStringEndsWith('...', $result);
    }

    /**
     * @test
     */
    public function it_does_not_truncate_short_strings(): void
    {
        $short = 'Short';
        $result = str_limit($short, 20);

        $this->assertEquals('Short', $result);
    }

    /**
     * @test
     */
    public function it_generates_random_strings(): void
    {
        $result1 = str_random(16);
        $result2 = str_random(16);

        $this->assertEquals(16, strlen($result1));
        $this->assertNotEquals($result1, $result2);
    }

    /**
     * @test
     */
    public function it_gets_array_values_with_dot_notation(): void
    {
        $array = [
            'level1' => [
                'level2' => [
                    'value' => 'found'
                ]
            ]
        ];

        $result = array_get($array, 'level1.level2.value');

        $this->assertEquals('found', $result);
    }

    /**
     * @test
     */
    public function it_returns_default_for_missing_array_keys(): void
    {
        $array = ['key' => 'value'];

        $result = array_get($array, 'missing', 'default');

        $this->assertEquals('default', $result);
    }

    /**
     * @test
     */
    public function it_formats_money_correctly(): void
    {
        $result = money(1234.56);

        $this->assertEquals('₺1.234,56', $result);
    }

    /**
     * @test
     */
    public function it_formats_prices_correctly(): void
    {
        $result = format_price(999);

        $this->assertStringContainsString('999,00', $result);
        $this->assertStringContainsString('₺', $result);
    }

    /**
     * @test
     */
    public function it_validates_emails(): void
    {
        $this->assertTrue(validate_email('test@example.com'));
        $this->assertTrue(validate_email('user.name+tag@domain.co.uk'));
        $this->assertFalse(validate_email('invalid-email'));
        $this->assertFalse(validate_email('missing@'));
    }

    /**
     * @test
     */
    public function it_validates_turkish_phone_numbers(): void
    {
        $this->assertTrue(validate_phone('5551234567'));
        $this->assertTrue(validate_phone('05551234567'));
        $this->assertTrue(validate_phone('0555 123 4567')); // With spaces (stripped)
        $this->assertFalse(validate_phone('123'));
    }

    /**
     * @test
     */
    public function it_extracts_only_specified_array_keys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];

        $result = array_only($array, ['a', 'c']);

        $this->assertEquals(['a' => 1, 'c' => 3], $result);
    }

    /**
     * @test
     */
    public function it_excludes_specified_array_keys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = array_except($array, ['b']);

        $this->assertEquals(['a' => 1, 'c' => 3], $result);
    }

    /**
     * @test
     */
    public function it_returns_current_datetime(): void
    {
        $result = now();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $result
        );
    }

    /**
     * @test
     */
    public function it_formats_dates_in_turkish(): void
    {
        $result = format_date('2024-01-15');

        $this->assertStringContainsString('Ocak', $result);
        $this->assertStringContainsString('15', $result);
        $this->assertStringContainsString('2024', $result);
    }
}
