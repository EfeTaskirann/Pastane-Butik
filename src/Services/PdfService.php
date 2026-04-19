<?php

declare(strict_types=1);

namespace Pastane\Services;

/**
 * PDF Service (P2-16)
 *
 * Rapor/fatura verilerini PDF'e dönüştürür.
 *
 * Öncelik:
 *   1. dompdf varsa → gerçek PDF döndürür (`Content-Type: application/pdf`)
 *   2. dompdf yoksa → print-optimize HTML döndürür; browser'ın native
 *      "Print → Save as PDF" akışı ile kullanıcı PDF üretir.
 *      (Bu mod için UI tarafında `window.print()` veya "PDF indir"
 *      → yeni tab + print dialog çağrısı kullanılır.)
 *
 * `composer require dompdf/dompdf` ile kurulduğunda otomatik upgrade olur,
 * kod değişikliği gerektirmez.
 */
final class PdfService
{
    /**
     * HTML içeriği PDF binary'sine dönüştür (dompdf varsa) ya da HTML döndür
     * (dompdf yoksa). Döndürülen array: ['content' => string, 'mime' => string, 'ext' => string]
     *
     * @param string $html     Render edilmiş HTML
     * @param array{title?:string,orientation?:string,paper?:string} $opts
     */
    public function fromHtml(string $html, array $opts = []): array
    {
        $title = $opts['title'] ?? 'Rapor';
        $orientation = $opts['orientation'] ?? 'portrait';
        $paper = $opts['paper'] ?? 'a4';

        if (class_exists('\\Dompdf\\Dompdf')) {
            return $this->renderWithDompdf($html, $title, $orientation, $paper);
        }

        return [
            'content' => $this->wrapForBrowserPrint($html, $title),
            'mime' => 'text/html; charset=utf-8',
            'ext' => 'html',
            'method' => 'browser-print',
        ];
    }

    /**
     * Sipariş fatura HTML'ini üret. Tema + renk + placeholder replacement.
     *
     * @param array<string,mixed> $data  ['siparis' => [...], 'items' => [...], 'total' => n, 'site_adi' => '...']
     */
    public function renderInvoiceHtml(array $data): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $siparis = $data['siparis'] ?? [];
        $items = $data['items'] ?? [];
        $total = $data['total'] ?? 0;
        $siteAdi = $data['site_adi'] ?? 'Tatlı Düşler';

        $html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Fatura #' . $e((string)($siparis['id'] ?? '')) . '</title>';
        $html .= '<style>
            body{font-family:Arial,sans-serif;color:#333;margin:40px;font-size:13px;}
            .header{border-bottom:2px solid #7a3d55;padding-bottom:16px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-end;}
            .brand{color:#7a3d55;font-size:24px;font-weight:bold;margin:0;}
            .subtitle{color:#666;margin:4px 0 0;}
            .meta{text-align:right;}
            .meta strong{display:block;color:#333;font-size:16px;}
            .meta span{color:#666;font-size:12px;}
            .customer{margin-bottom:24px;padding:16px;background:#fafafa;border-radius:6px;}
            .customer h3{margin:0 0 8px;color:#7a3d55;font-size:14px;}
            table{width:100%;border-collapse:collapse;margin-bottom:16px;}
            th{background:#7a3d55;color:#fff;text-align:left;padding:10px;font-size:12px;}
            td{padding:8px 10px;border-bottom:1px solid #eee;}
            tr:last-child td{border-bottom:none;}
            .total-row{font-weight:bold;font-size:15px;}
            .total-row td{padding-top:14px;border-top:2px solid #7a3d55;}
            .footer{margin-top:40px;padding-top:16px;border-top:1px solid #ccc;text-align:center;color:#999;font-size:11px;}
            @media print{body{margin:0;}.no-print{display:none;}}
        </style></head><body>';

        $html .= '<div class="header">';
        $html .= '<div><h1 class="brand">' . $e($siteAdi) . '</h1>';
        $html .= '<p class="subtitle">Butik Pasta &amp; Tatlı Sipariş Sistemi</p></div>';
        $html .= '<div class="meta"><strong>Fatura #' . $e((string)($siparis['id'] ?? '—')) . '</strong>';
        $html .= '<span>' . $e((string)($siparis['tarih'] ?? $siparis['created_at'] ?? '')) . '</span></div>';
        $html .= '</div>';

        $html .= '<div class="customer"><h3>Müşteri Bilgileri</h3>';
        $html .= '<div><strong>' . $e((string)($siparis['musteri_adi'] ?? '—')) . '</strong></div>';
        if (!empty($siparis['telefon'])) $html .= '<div>Tel: ' . $e((string)$siparis['telefon']) . '</div>';
        if (!empty($siparis['adres'])) $html .= '<div>Adres: ' . $e((string)$siparis['adres']) . '</div>';
        $html .= '</div>';

        $html .= '<table><thead><tr><th>Ürün</th><th>Adet</th><th>Birim Fiyat</th><th>Tutar</th></tr></thead><tbody>';
        foreach ($items as $item) {
            $birim = (float)($item['birim_fiyat'] ?? 0);
            $adet = (int)($item['adet'] ?? 1);
            $tutar = $birim * $adet;
            $html .= '<tr>';
            $html .= '<td>' . $e((string)($item['isim'] ?? '—')) . '</td>';
            $html .= '<td>' . $adet . '</td>';
            $html .= '<td>' . number_format($birim, 2, ',', '.') . ' TL</td>';
            $html .= '<td>' . number_format($tutar, 2, ',', '.') . ' TL</td>';
            $html .= '</tr>';
        }
        $html .= '<tr class="total-row"><td colspan="3">Toplam</td><td>' . number_format((float)$total, 2, ',', '.') . ' TL</td></tr>';
        $html .= '</tbody></table>';

        $html .= '<div class="footer">Bu bir elektronik fatura örneğidir — Resmî vergi fatura değildir.<br>';
        $html .= '&copy; ' . date('Y') . ' ' . $e($siteAdi) . '</div>';

        $html .= '</body></html>';
        return $html;
    }

    private function renderWithDompdf(string $html, string $title, string $orientation, string $paper): array
    {
        /** @psalm-suppress UndefinedClass */
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return [
            'content' => (string) $dompdf->output(),
            'mime' => 'application/pdf',
            'ext' => 'pdf',
            'method' => 'dompdf',
        ];
    }

    /**
     * Browser print için HTML'e print button + auto-print opsiyonu ekle.
     * Bu modda buyer tarafı tarayıcının "Save as PDF" özelliğini kullanır.
     */
    private function wrapForBrowserPrint(string $html, string $title): string
    {
        // Eğer input zaten full HTML doc ise, onu aynen bırak; aksi halde wrap et
        if (stripos($html, '<!DOCTYPE') === 0 || stripos(ltrim($html), '<html') === 0) {
            // Body sonuna auto-print helper enjekte et
            return preg_replace('#</body>#i', $this->printHelperScript() . '</body>', $html, 1) ?? $html;
        }

        return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>' .
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8') .
            '</title></head><body>' . $html . $this->printHelperScript() . '</body></html>';
    }

    private function printHelperScript(): string
    {
        // PDF print görünümü standalone HTML — ana CSS pipeline (utilities.css)
        // burada yüklenmez. Tarayıcı print preview'inde inline style ZORUNLU.
        return '<div class="no-print" style="position:fixed;bottom:16px;right:16px;">
            <button type="button" onclick="window.print()" style="padding:10px 18px;background:#7a3d55;color:#fff;border:none;border-radius:6px;cursor:pointer;">
                PDF Olarak Kaydet / Yazdır
            </button>
        </div>
        <script>if (location.hash === "#auto") window.addEventListener("load", function(){setTimeout(window.print, 300);});</script>';
    }
}
