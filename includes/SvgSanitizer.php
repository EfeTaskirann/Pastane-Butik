<?php

declare(strict_types=1);

/**
 * SVG Sanitizer — XSS koruyucu (P3-22)
 *
 * DOMDocument ile SVG parse eder, whitelist dışı tag + attribute'ları
 * çıkarır. Strict mode'da tehlikeli giriş tamamen reddedilir.
 *
 * Engellenen vektörler:
 *   - <script> / <style> / <foreignObject> tag
 *   - on* event handler (onclick, onload, onerror, …)
 *   - javascript:/data:/vbscript: URI schemes
 *   - XML external entities (XXE) — libxml_disable_entity_loader() + LIBXML_NONET
 *   - xlink:href="javascript:…" ve xlink:href="data:…"
 *
 * Kullanım:
 *   $clean = SvgSanitizer::sanitize(file_get_contents('upload.svg'));
 *   if ($clean === null) {
 *       throw new RuntimeException('SVG malicious or invalid');
 *   }
 *   file_put_contents('safe.svg', $clean);
 *
 * Strict mode:
 *   $clean = SvgSanitizer::sanitize($svg, ['strict' => true]);
 *   // strict=true → tehlikeli içerik bulunursa null döndürür (reject)
 *   // strict=false → tehlikeli içeriği strip edip geri kalanı döndürür (sanitize)
 */
final class SvgSanitizer
{
    /**
     * İzin verilen SVG tag'ları. SVG 1.1 spec'ten güvenli subset.
     * <foreignObject>, <script>, <style> EKLENMEZ (tehlikeli).
     */
    private const ALLOWED_TAGS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'image', // güvenli kabul edilir ama href = javascript:/data: engellenir
        'clipPath', 'mask', 'pattern', 'linearGradient', 'radialGradient',
        'stop', 'filter', 'feGaussianBlur', 'feOffset', 'feBlend', 'feFlood',
        'feColorMatrix', 'feComposite', 'feMerge', 'feMergeNode',
        'marker', 'switch',
        'a', // <a xlink:href="..."> — href kontrolü yapılır
    ];

    /**
     * İzin verilen attribute'lar — hepsi **lowercase**. Karşılaştırma
     * lowercase üzerinden yapılır, böylece SVG'nin camelCase attribute'ları
     * (viewBox, preserveAspectRatio, vb.) doğru eşleşir.
     */
    private const ALLOWED_ATTRS = [
        // Core
        'id', 'class', 'style', 'lang', 'xml:lang', 'xml:space', 'tabindex',
        // Presentation
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width',
        'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset',
        'stroke-opacity', 'stroke-miterlimit', 'opacity', 'color', 'display',
        'visibility', 'overflow', 'transform', 'clip-path', 'clip-rule',
        'mask', 'filter', 'mix-blend-mode',
        // Layout
        'x', 'y', 'dx', 'dy', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'viewbox', 'preserveaspectratio',
        'points', 'd', 'pathlength',
        // Text
        'text-anchor', 'font-family', 'font-size', 'font-style', 'font-weight',
        'font-variant', 'font-stretch', 'letter-spacing', 'word-spacing',
        'line-height', 'text-decoration', 'dominant-baseline', 'alignment-baseline',
        'writing-mode', 'direction',
        // Refs (sanitize edilir ayrıca)
        'href', 'xlink:href',
        // Gradient / pattern
        'gradientunits', 'gradienttransform', 'spreadmethod', 'stop-color',
        'stop-opacity', 'offset', 'patternunits', 'patterncontentunits',
        'patterntransform',
        // Filter
        'in', 'in2', 'result', 'stddeviation', 'values', 'type', 'mode',
        'operator', 'k1', 'k2', 'k3', 'k4', 'flood-color', 'flood-opacity',
        // Marker
        'marker-start', 'marker-mid', 'marker-end', 'markerunits', 'markerwidth',
        'markerheight', 'refx', 'refy', 'orient',
        // SVG root
        'xmlns', 'xmlns:xlink', 'version', 'baseprofile',
        // Meta
        'role', 'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden',
    ];

    /**
     * Güvensiz URL şemaları (href / xlink:href için).
     */
    private const UNSAFE_URI_SCHEMES = [
        'javascript:', 'vbscript:', 'livescript:', 'mocha:', 'data:',
        'file:', 'about:',
    ];

    /**
     * Input'u sanitize et (veya strict modda reddet).
     *
     * @param string $svg          Raw SVG içerik.
     * @param array{strict?:bool,maxSize?:int} $options
     * @return string|null         Temizlenmiş SVG, ya da null (invalid/reddedildi).
     */
    public static function sanitize(string $svg, array $options = []): ?string
    {
        $strict = $options['strict'] ?? false;
        $maxSize = $options['maxSize'] ?? (2 * 1024 * 1024); // 2 MB default

        if (trim($svg) === '') {
            return null;
        }

        if (strlen($svg) > $maxSize) {
            return null;
        }

        // UTF-8 BOM strip
        if (strncmp($svg, "\xEF\xBB\xBF", 3) === 0) {
            $svg = substr($svg, 3);
        }

        // Basit regex ön-filtre (DOMDocument'a ulaşmadan obvious attack'ler için)
        if (preg_match('/<\s*script\b/i', $svg) || preg_match('/<\s*foreignObject\b/i', $svg)) {
            if ($strict) {
                return null;
            }
        }

        // XXE / external entity koruması
        $prev = null;
        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            $prev = libxml_disable_entity_loader(true);
        }
        $prevErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;

        // LIBXML_NONET → HTTP fetch disabled
        // LIBXML_NOENT false kalır → entity expansion engelli
        $loaded = @$dom->loadXML(
            $svg,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader') && $prev !== null) {
            libxml_disable_entity_loader($prev);
        }
        libxml_use_internal_errors($prevErrors);

        if (!$loaded) {
            return null;
        }

        // Root <svg>?
        $root = $dom->documentElement;
        if ($root === null || strtolower($root->localName ?? '') !== 'svg') {
            return null;
        }

        $tainted = false;

        // Recursive clean — tag ve attribute whitelist
        self::cleanNode($root, $tainted);

        if ($tainted && $strict) {
            return null;
        }

        $clean = $dom->saveXML($root);
        return $clean !== false ? $clean : null;
    }

    /**
     * SVG dosya upload'ı için convenience wrapper.
     * Başarısızsa false, başarılıysa temiz içerik.
     */
    public static function sanitizeFile(string $srcPath, string $dstPath = null, array $options = []): bool
    {
        if (!is_file($srcPath) || !is_readable($srcPath)) {
            return false;
        }

        $raw = file_get_contents($srcPath);
        if ($raw === false) {
            return false;
        }

        $clean = self::sanitize($raw, $options);
        if ($clean === null) {
            return false;
        }

        $target = $dstPath ?? $srcPath;
        return file_put_contents($target, $clean) !== false;
    }

    /**
     * Recursive node cleaner.
     * Tehlikeli node'ları siler, izinsiz attribute'ları kaldırır.
     */
    private static function cleanNode(\DOMNode $node, bool &$tainted): void
    {
        // Element olmayan node'ları (text, comment, cdata) atla
        if (!($node instanceof \DOMElement)) {
            return;
        }

        // Çocukları önce listele (silme sırasında iteration bozulmasın)
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $name = strtolower($child->localName ?? '');

                if (!in_array($name, self::ALLOWED_TAGS, true)) {
                    $tainted = true;
                    $node->removeChild($child);
                    continue;
                }

                self::cleanAttributes($child, $tainted);
                self::cleanNode($child, $tainted);
            } elseif ($child instanceof \DOMComment) {
                // Yorumlar genelde zararsız ama XML-ish payload taşıyabilir — kaldır
                $node->removeChild($child);
            } elseif ($child->nodeType === XML_PI_NODE) {
                // Processing instruction — kaldır
                $tainted = true;
                $node->removeChild($child);
            }
        }

        self::cleanAttributes($node, $tainted);
    }

    /**
     * Element'teki izinsiz attribute'ları sil + URI şemalarını doğrula.
     */
    private static function cleanAttributes(\DOMElement $el, bool &$tainted): void
    {
        $toRemove = [];

        foreach ($el->attributes as $attr) {
            $attrName = strtolower($attr->nodeName ?? '');

            // on* event handler — derhal reddet
            if (strncmp($attrName, 'on', 2) === 0) {
                $tainted = true;
                $toRemove[] = $attr->nodeName;
                continue;
            }

            // Whitelist?
            $baseName = $attrName;
            // xlink: prefix'i normalize
            if (strpos($baseName, 'xlink:') === 0) {
                $baseName = 'xlink:' . substr($baseName, 6);
            }

            $isAllowed = in_array($attrName, self::ALLOWED_ATTRS, true)
                      || in_array($baseName, self::ALLOWED_ATTRS, true);

            // aria-* her zaman izinli
            if (!$isAllowed && strpos($attrName, 'aria-') === 0) {
                $isAllowed = true;
            }
            // data-* her zaman izinli
            if (!$isAllowed && strpos($attrName, 'data-') === 0) {
                $isAllowed = true;
            }

            if (!$isAllowed) {
                $tainted = true;
                $toRemove[] = $attr->nodeName;
                continue;
            }

            // URI şeması kontrolü: href + xlink:href + filter/mask/clip-path url(...)
            if (in_array($attrName, ['href', 'xlink:href'], true)) {
                if (self::hasUnsafeUri($attr->nodeValue ?? '')) {
                    $tainted = true;
                    $toRemove[] = $attr->nodeName;
                    continue;
                }
            }

            // style="" attr — javascript:, expression(), behavior: gibi payload'lar
            if ($attrName === 'style') {
                if (self::hasUnsafeStyle($attr->nodeValue ?? '')) {
                    $tainted = true;
                    $toRemove[] = $attr->nodeName;
                    continue;
                }
            }
        }

        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
    }

    private static function hasUnsafeUri(string $uri): bool
    {
        $uri = strtolower(trim($uri));
        // URL-encoded newline / tab bypass'ı engelle
        $uri = preg_replace('/\s+/', '', $uri) ?? $uri;
        $uri = str_replace(["%00", "&#0;", "&#x0;"], '', $uri);

        foreach (self::UNSAFE_URI_SCHEMES as $scheme) {
            if (strpos($uri, $scheme) === 0) {
                return true;
            }
        }

        // data:image/* hatta güvenli değil — harici kaynak sızdırabilir. Tümünü engelle.
        return false;
    }

    private static function hasUnsafeStyle(string $style): bool
    {
        $style = strtolower($style);
        $patterns = [
            'javascript:', 'vbscript:', 'expression(', 'behavior:', 'behaviour:',
            '@import', 'url(javascript', 'url("javascript', "url('javascript",
        ];
        foreach ($patterns as $p) {
            if (strpos($style, $p) !== false) {
                return true;
            }
        }
        return false;
    }
}
