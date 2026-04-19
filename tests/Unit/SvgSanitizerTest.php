<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SvgSanitizer;

/**
 * @covers \SvgSanitizer
 */
final class SvgSanitizerTest extends TestCase
{
    public function test_simple_valid_svg_passes(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="red"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringContainsString('<circle', $clean);
        $this->assertStringContainsString('fill="red"', $clean);
    }

    public function test_script_tag_is_removed(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="5" cy="5" r="4"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringContainsString('<circle', $clean);
    }

    public function test_foreign_object_is_removed(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>xss</div></foreignObject></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('<foreignObject', $clean);
        $this->assertStringNotContainsString('<div', $clean);
    }

    public function test_onload_event_handler_is_stripped(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle cx="5" cy="5" r="4"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_onclick_event_handler_is_stripped(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4" onclick="evil()"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function test_javascript_href_is_stripped(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><text>Click</text></a></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_data_uri_href_is_stripped(): void
    {
        // XML-legal data URI (script literal would break XML parsing)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjwvc3ZnPg=="/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('data:', $clean);
    }

    public function test_style_with_javascript_is_stripped(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4" style="fill:url(javascript:alert(1))"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_strict_mode_rejects_malicious(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $result = SvgSanitizer::sanitize($svg, ['strict' => true]);
        $this->assertNull($result);
    }

    public function test_strict_mode_passes_clean_input(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="1" y="1" width="8" height="8" fill="#FFF"/></svg>';
        $result = SvgSanitizer::sanitize($svg, ['strict' => true]);
        $this->assertIsString($result);
    }

    public function test_xxe_entity_is_blocked(): void
    {
        $svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        // Entity expansion suppressed — either null or without file content
        if ($clean !== null) {
            $this->assertStringNotContainsString('root:', $clean);
            $this->assertStringNotContainsString('/etc/passwd', $clean);
        }
        $this->assertTrue(true); // test reaches here without exception
    }

    public function test_non_svg_root_rejected(): void
    {
        $xml = '<?xml version="1.0"?><root><child/></root>';
        $this->assertNull(SvgSanitizer::sanitize($xml));
    }

    public function test_empty_input_rejected(): void
    {
        $this->assertNull(SvgSanitizer::sanitize(''));
        $this->assertNull(SvgSanitizer::sanitize('   '));
    }

    public function test_invalid_xml_rejected(): void
    {
        $this->assertNull(SvgSanitizer::sanitize('<svg><unclosed'));
    }

    public function test_oversize_rejected(): void
    {
        $big = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat('<circle r="1"/>', 500000) . '</svg>';
        $this->assertNull(SvgSanitizer::sanitize($big, ['maxSize' => 1024]));
    }

    public function test_aria_and_data_attributes_pass(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" aria-label="icon" data-custom="x"><circle cx="5" cy="5" r="4"/></svg>';
        $clean = SvgSanitizer::sanitize($svg);
        $this->assertIsString($clean);
        $this->assertStringContainsString('aria-label="icon"', $clean);
        $this->assertStringContainsString('data-custom="x"', $clean);
    }

    public function test_sanitize_file_roundtrip(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'svg_');
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"><script>bad</script><circle cx="5" cy="5" r="4" fill="blue"/></svg>');
        $this->assertTrue(SvgSanitizer::sanitizeFile($tmp));
        $content = file_get_contents($tmp);
        $this->assertIsString($content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringContainsString('<circle', $content);
        @unlink($tmp);
    }
}
