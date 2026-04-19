<?php

declare(strict_types=1);

namespace Pastane\Services;

use RuntimeException;

/**
 * Export Service (P2-17)
 *
 * Rapor verilerini CSV, Excel (XLSX), JSON formatlarında üretir.
 *
 * CSV:  Native fputcsv, UTF-8 BOM ile Excel açabilirliği.
 * XLSX: PhpSpreadsheet varsa onu kullanır; yoksa minimal SpreadsheetML
 *        (Excel 2003 XML) fallback — Excel'de açılır.
 * JSON: pretty-print.
 *
 * Kullanım:
 *   $rows = $raporService->getGunlukSiparisler('2026-04-20');
 *   $csv = (new ExportService())->toCsv($rows, ['id', 'musteri_adi', 'tutar']);
 *   header('Content-Type: text/csv; charset=utf-8');
 *   header('Content-Disposition: attachment; filename="siparisler.csv"');
 *   echo $csv;
 */
final class ExportService
{
    /** UTF-8 BOM — Excel'in TR karakter algılaması için. */
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Verileri CSV string'e çevir.
     *
     * @param array<int,array<string,mixed>> $rows   Tek-seviye satır dizisi
     * @param array<int,string>              $columns Başlık sırası (keys); boşsa ilk row'un key'leri
     * @param array{delimiter?:string,enclosure?:string,bom?:bool} $opts
     */
    public function toCsv(array $rows, array $columns = [], array $opts = []): string
    {
        $delimiter = $opts['delimiter'] ?? ',';
        $enclosure = $opts['enclosure'] ?? '"';
        $bom = $opts['bom'] ?? true;

        if (empty($rows)) {
            return $bom ? self::UTF8_BOM : '';
        }

        if (empty($columns)) {
            $columns = array_keys(reset($rows));
        }

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new RuntimeException('Failed to open temp stream');
        }

        // Header
        fputcsv($fh, $columns, $delimiter, $enclosure, '\\');

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $v = $row[$col] ?? '';
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $line[] = (string) $v;
            }
            fputcsv($fh, $line, $delimiter, $enclosure, '\\');
        }

        rewind($fh);
        $content = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $bom ? self::UTF8_BOM . $content : $content;
    }

    /**
     * Tek tablolu XLSX üret.
     *
     * PhpSpreadsheet mevcut ise onu kullanır (zengin formatlama).
     * Aksi halde minimal SpreadsheetML XML (Excel 2003 formatı, `.xml` uzantısı
     * tercih edilir ama `.xlsx` de çoğu Excel sürümü tarafından açılır).
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>              $columns
     * @param array{title?:string}           $opts
     * @return string binary XLSX/XML içerik
     */
    public function toXlsx(array $rows, array $columns = [], array $opts = []): string
    {
        $title = $opts['title'] ?? 'Sheet1';

        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            return $this->toXlsxWithPhpSpreadsheet($rows, $columns, $title);
        }

        return $this->toSpreadsheetMl($rows, $columns, $title);
    }

    /**
     * PhpSpreadsheet varsa gerçek XLSX üret.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>              $columns
     */
    private function toXlsxWithPhpSpreadsheet(array $rows, array $columns, string $title): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($title, 0, 31));

        if (empty($columns) && !empty($rows)) {
            $columns = array_keys(reset($rows));
        }

        // Header
        $colIndex = 1;
        foreach ($columns as $col) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $col);
            $colIndex++;
        }

        // Rows
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($columns as $col) {
                $v = $row[$col] ?? '';
                if (is_array($v) || is_object($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $v);
                $colIndex++;
            }
            $rowIndex++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer->save($tmp);
        $content = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $content;
    }

    /**
     * SpreadsheetML (Excel 2003 XML) fallback — vendor'sız.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>              $columns
     */
    private function toSpreadsheetMl(array $rows, array $columns, string $title): string
    {
        if (empty($columns) && !empty($rows)) {
            $columns = array_keys(reset($rows));
        }

        $xmlEscape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $title = preg_replace('/[^A-Za-z0-9 _-]/', '', $title) ?? 'Sheet1';
        if ($title === '') $title = 'Sheet1';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="Header"><Font ss:Bold="1"/></Style>';
        $xml .= '</Styles>';
        $xml .= '<Worksheet ss:Name="' . $xmlEscape($title) . '"><Table>';

        // Header row
        $xml .= '<Row>';
        foreach ($columns as $col) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . $xmlEscape((string) $col) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        // Data rows
        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($columns as $col) {
                $v = $row[$col] ?? '';
                if (is_array($v) || is_object($v)) {
                    $v = (string) json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $type = (is_numeric($v) && !is_string($v)) ? 'Number' : 'String';
                if ($type === 'Number' && !is_finite((float) $v)) {
                    $type = 'String';
                }
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . $xmlEscape((string) $v) . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }

    /**
     * JSON export (pretty-print).
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function toJson(array $rows, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return (string) json_encode($rows, $flags);
    }

    /**
     * Download header'ları set eder.
     */
    public function sendHeaders(string $format, string $filename): void
    {
        if (headers_sent()) {
            return;
        }
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'export';
        $map = [
            'csv' => 'text/csv; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'application/vnd.ms-excel',
            'json' => 'application/json',
        ];
        $mime = $map[strtolower($format)] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}
