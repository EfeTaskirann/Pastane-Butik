<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pastane\Services\PdfService;

/**
 * @covers \Pastane\Services\PdfService
 */
final class PdfServiceTest extends TestCase
{
    private PdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PdfService();
    }

    public function test_render_invoice_html_contains_key_fields(): void
    {
        $html = $this->service->renderInvoiceHtml([
            'siparis' => [
                'id' => 1234,
                'musteri_adi' => 'Ayşe Yılmaz',
                'telefon' => '0555 123 4567',
                'adres' => 'İstanbul',
                'created_at' => '2026-04-20 14:30:00',
            ],
            'items' => [
                ['isim' => 'Tiramisu', 'adet' => 2, 'birim_fiyat' => 85.00],
                ['isim' => 'Cheesecake', 'adet' => 1, 'birim_fiyat' => 92.50],
            ],
            'total' => 262.50,
            'site_adi' => 'Tatlı Düşler',
        ]);

        $this->assertStringContainsString('#1234', $html);
        $this->assertStringContainsString('Ayşe Yılmaz', $html);
        $this->assertStringContainsString('Tiramisu', $html);
        $this->assertStringContainsString('262,50', $html);
        $this->assertStringContainsString('Tatlı Düşler', $html);
    }

    public function test_render_invoice_escapes_html(): void
    {
        $html = $this->service->renderInvoiceHtml([
            'siparis' => ['musteri_adi' => '<script>alert(1)</script>'],
            'items' => [],
            'total' => 0,
        ]);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_from_html_dompdf_fallback_to_browser_print(): void
    {
        $result = $this->service->fromHtml('<p>Hello</p>', ['title' => 'Test']);
        if (class_exists('\\Dompdf\\Dompdf')) {
            $this->assertSame('application/pdf', $result['mime']);
            $this->assertSame('pdf', $result['ext']);
            $this->assertStringStartsWith('%PDF', $result['content']);
        } else {
            $this->assertSame('text/html; charset=utf-8', $result['mime']);
            $this->assertSame('html', $result['ext']);
            $this->assertStringContainsString('<p>Hello</p>', $result['content']);
            $this->assertStringContainsString('window.print', $result['content']);
            $this->assertSame('browser-print', $result['method']);
        }
    }

    public function test_from_html_preserves_existing_doctype(): void
    {
        $html = '<!DOCTYPE html><html><head><title>X</title></head><body><p>Already full doc</p></body></html>';
        $result = $this->service->fromHtml($html);
        if (class_exists('\\Dompdf\\Dompdf')) {
            $this->assertSame('application/pdf', $result['mime']);
        } else {
            $this->assertStringStartsWith('<!DOCTYPE', $result['content']);
            $this->assertStringContainsString('Already full doc', $result['content']);
        }
    }
}
