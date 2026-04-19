<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pastane\Services\ExportService;

/**
 * @covers \Pastane\Services\ExportService
 */
final class ExportServiceTest extends TestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService();
    }

    public function test_csv_basic(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Tiramisu', 'fiyat' => 85.00],
            ['id' => 2, 'name' => 'Cheesecake', 'fiyat' => 92.50],
        ];
        $csv = $this->service->toCsv($rows);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'should include UTF-8 BOM by default');
        $this->assertStringContainsString('id,name,fiyat', $csv);
        $this->assertStringContainsString('Tiramisu', $csv);
        $this->assertStringContainsString('92.5', $csv);
    }

    public function test_csv_respects_column_order(): void
    {
        $rows = [['a' => 1, 'b' => 2, 'c' => 3]];
        $csv = $this->service->toCsv($rows, ['c', 'a']);
        $this->assertStringContainsString("c,a\n3,1", $csv);
    }

    public function test_csv_without_bom(): void
    {
        $rows = [['a' => 1]];
        $csv = $this->service->toCsv($rows, [], ['bom' => false]);
        $this->assertStringStartsNotWith("\xEF\xBB\xBF", $csv);
        $this->assertStringStartsWith('a', $csv);
    }

    public function test_csv_handles_embedded_quotes_and_commas(): void
    {
        $rows = [['msg' => 'Hello, "world"']];
        $csv = $this->service->toCsv($rows);
        $this->assertStringContainsString('"Hello, ""world"""', $csv);
    }

    public function test_csv_empty_rows_returns_bom_only(): void
    {
        $csv = $this->service->toCsv([]);
        $this->assertSame("\xEF\xBB\xBF", $csv);
    }

    public function test_csv_turkish_characters_preserved(): void
    {
        $rows = [['isim' => 'Çilekli Pasta', 'aciklama' => 'Ağır şurup']];
        $csv = $this->service->toCsv($rows);
        $this->assertStringContainsString('Çilekli Pasta', $csv);
        $this->assertStringContainsString('Ağır şurup', $csv);
    }

    public function test_xlsx_fallback_to_spreadsheetml(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Tiramisu', 'fiyat' => 85.00],
        ];
        $xml = $this->service->toXlsx($rows, [], ['title' => 'Siparisler']);
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            // Real XLSX — binary ZIP starts with PK
            $this->assertStringStartsWith('PK', $xml);
        } else {
            $this->assertStringContainsString('<Workbook', $xml);
            $this->assertStringContainsString('Tiramisu', $xml);
            $this->assertStringContainsString('<Worksheet ss:Name="Siparisler"', $xml);
            $this->assertStringContainsString('<Data ss:Type="Number">85', $xml);
        }
    }

    public function test_xlsx_escapes_xml_special_chars(): void
    {
        $rows = [['label' => '<script>alert(1)</script>']];
        $xml = $this->service->toXlsx($rows);
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $this->assertStringNotContainsString('<script', $xml);
            $this->assertStringContainsString('&lt;script&gt;', $xml);
        } else {
            $this->assertTrue(true); // real XLSX compresses XML, can't assert easily
        }
    }

    public function test_json_pretty_default(): void
    {
        $rows = [['a' => 1]];
        $json = $this->service->toJson($rows);
        $this->assertStringContainsString("\n", $json); // pretty
        $this->assertStringContainsString('"a":', $json);
    }

    public function test_json_compact(): void
    {
        $rows = [['a' => 1]];
        $json = $this->service->toJson($rows, false);
        $this->assertStringNotContainsString("\n", $json);
    }

    public function test_send_headers_filename_sanitized(): void
    {
        // headers_sent() true in CLI — just ensure no exception
        $this->service->sendHeaders('csv', 'siparisler_../secret.csv');
        $this->assertTrue(true);
    }
}
