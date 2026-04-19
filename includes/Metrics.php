<?php
/**
 * Metrics — Basit, dosya tabanlı Prometheus-uyumlu metrik toplama sınıfı.
 *
 * Depolama:
 *   storage/cache/prom-counters.json   (counter + gauge)
 *   storage/cache/prom-histograms.json (histogram buckets + last-hour window)
 *
 * Tip desteği:
 *   - counter : monotonik artan sayaç (Metrics::inc)
 *   - gauge   : anlık değer (Metrics::set)
 *   - histogram: bucket + sum + count (Metrics::observe)
 *
 * Label desteği:
 *   Metrics::inc('pastane_http_requests_total', ['method' => 'GET', 'status' => '200'])
 *   → JSON key: "pastane_http_requests_total|method=GET,status=200"
 *
 * Prometheus format üretimi için Metrics::render() kullanılır.
 *
 * Dikkat:
 *   - Dosyalar LOCK_EX ile kilitlenir (concurrency safe)
 *   - PHP process lifecycle dışında persist eder (file-backed)
 *   - Histogram sadece son 1 saatlik pencere saklanır (auto-trim)
 *
 * @package Pastane
 * @since   1.2.0-sprint2
 */

declare(strict_types=1);

final class Metrics
{
    /** Histogram bucket sınırları (saniye, DB query için) */
    public const HISTOGRAM_BUCKETS_SECONDS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /** Son-bir-saat histogram penceresi (saniye) */
    public const HISTOGRAM_WINDOW_SECONDS = 3600;

    /** @var string|null Counter/gauge dosyası */
    private static ?string $countersFile = null;

    /** @var string|null Histogram dosyası */
    private static ?string $histogramsFile = null;

    /** @var int Process başlatma zamanı (uptime gauge için) */
    private static int $startTime = 0;

    /**
     * Depolama dosyası yolu belirle.
     */
    private static function init(): void
    {
        if (self::$countersFile !== null) {
            return;
        }
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $cacheDir = $base . DIRECTORY_SEPARATOR . (function_exists('env') ? (string) env('CACHE_PATH', 'storage/cache') : 'storage/cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        self::$countersFile = $cacheDir . DIRECTORY_SEPARATOR . 'prom-counters.json';
        self::$histogramsFile = $cacheDir . DIRECTORY_SEPARATOR . 'prom-histograms.json';
        if (self::$startTime === 0) {
            self::$startTime = (int) ($_SERVER['REQUEST_TIME'] ?? time());
        }
    }

    /**
     * Label dizisini kanonik "key|k1=v1,k2=v2" string'ine dönüştür.
     *
     * @param array<string, string|int|float> $labels
     */
    private static function canonicalKey(string $name, array $labels): string
    {
        if (!$labels) {
            return $name;
        }
        ksort($labels);
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = (string) $k . '=' . self::sanitizeLabel((string) $v);
        }
        return $name . '|' . implode(',', $parts);
    }

    /**
     * Label değerinden tehlikeli karakterleri temizle.
     */
    private static function sanitizeLabel(string $v): string
    {
        return str_replace(['"', '\\', "\n", ',', '|'], ['', '', ' ', ';', ';'], $v);
    }

    /**
     * Counter'ı artır (atomic).
     *
     * @param array<string, string|int|float> $labels
     */
    public static function inc(string $name, array $labels = [], int|float $by = 1): void
    {
        self::init();
        try {
            $data = self::readJson(self::$countersFile);
            $key = self::canonicalKey($name, $labels);
            $data['counters'][$key] = ($data['counters'][$key] ?? 0) + $by;
            self::writeJson(self::$countersFile, $data);
        } catch (Throwable) {
            // Metrics kaydı ana akışı engellememeli
        }
    }

    /**
     * Gauge değerini setle.
     *
     * @param array<string, string|int|float> $labels
     */
    public static function set(string $name, int|float $value, array $labels = []): void
    {
        self::init();
        try {
            $data = self::readJson(self::$countersFile);
            $key = self::canonicalKey($name, $labels);
            $data['gauges'][$key] = $value;
            self::writeJson(self::$countersFile, $data);
        } catch (Throwable) {
            // sessiz
        }
    }

    /**
     * Histogram gözlemi (son 1 saat penceresi).
     *
     * @param array<string, string|int|float> $labels
     */
    public static function observe(string $name, int|float $value, array $labels = []): void
    {
        self::init();
        try {
            $data = self::readJson(self::$histogramsFile);
            $key = self::canonicalKey($name, $labels);
            $bucket = &$data['histograms'][$key];
            if (!is_array($bucket)) {
                $bucket = ['observations' => []];
            }
            $bucket['observations'][] = ['t' => time(), 'v' => (float) $value];

            // Pencere dışındaki gözlemleri at
            $cutoff = time() - self::HISTOGRAM_WINDOW_SECONDS;
            $bucket['observations'] = array_values(array_filter(
                $bucket['observations'],
                static fn(array $o): bool => $o['t'] >= $cutoff
            ));
            self::writeJson(self::$histogramsFile, $data);
        } catch (Throwable) {
            // sessiz
        }
    }

    /**
     * Counter değerini oku (test amaçlı).
     *
     * @param array<string, string|int|float> $labels
     */
    public static function getCounter(string $name, array $labels = []): float
    {
        self::init();
        $data = self::readJson(self::$countersFile);
        $key = self::canonicalKey($name, $labels);
        return (float) ($data['counters'][$key] ?? 0);
    }

    /**
     * Prometheus text exposition format üret.
     *
     * @return string text/plain; version=0.0.4
     */
    public static function render(): string
    {
        self::init();
        $out = '';
        $counters = self::readJson(self::$countersFile);
        $hists    = self::readJson(self::$histogramsFile);

        // ----- Counters -----
        $byName = [];
        foreach (($counters['counters'] ?? []) as $key => $value) {
            [$name, $labelStr] = self::splitKey($key);
            $byName[$name][] = ['labels' => $labelStr, 'value' => $value];
        }
        foreach ($byName as $name => $rows) {
            $out .= "# TYPE {$name} counter\n";
            foreach ($rows as $r) {
                $out .= $name . self::promLabels($r['labels']) . ' ' . self::formatValue($r['value']) . "\n";
            }
        }

        // ----- Gauges -----
        $byName = [];
        foreach (($counters['gauges'] ?? []) as $key => $value) {
            [$name, $labelStr] = self::splitKey($key);
            $byName[$name][] = ['labels' => $labelStr, 'value' => $value];
        }
        foreach ($byName as $name => $rows) {
            $out .= "# TYPE {$name} gauge\n";
            foreach ($rows as $r) {
                $out .= $name . self::promLabels($r['labels']) . ' ' . self::formatValue($r['value']) . "\n";
            }
        }

        // ----- Process uptime -----
        $out .= "# TYPE pastane_uptime_seconds gauge\n";
        $out .= 'pastane_uptime_seconds ' . max(0, time() - self::$startTime) . "\n";

        // ----- Histograms -----
        foreach (($hists['histograms'] ?? []) as $key => $bucketData) {
            [$name, $labelStr] = self::splitKey($key);
            $obs = $bucketData['observations'] ?? [];
            if (!is_array($obs)) {
                continue;
            }
            $sum = 0.0;
            $count = 0;
            $bucketCounts = array_fill(0, count(self::HISTOGRAM_BUCKETS_SECONDS), 0);
            foreach ($obs as $o) {
                $v = (float) ($o['v'] ?? 0);
                $sum += $v;
                $count++;
                foreach (self::HISTOGRAM_BUCKETS_SECONDS as $i => $le) {
                    if ($v <= $le) {
                        $bucketCounts[$i]++;
                    }
                }
            }
            $out .= "# TYPE {$name} histogram\n";
            foreach (self::HISTOGRAM_BUCKETS_SECONDS as $i => $le) {
                $extra = $labelStr ? $labelStr . ',' : '';
                $extra .= sprintf('le="%s"', self::formatValue($le));
                $out .= $name . '_bucket{' . $extra . '} ' . $bucketCounts[$i] . "\n";
            }
            $extra = $labelStr ? $labelStr . ',' : '';
            $extra .= 'le="+Inf"';
            $out .= $name . '_bucket{' . $extra . '} ' . $count . "\n";
            $out .= $name . '_sum' . self::promLabels($labelStr) . ' ' . self::formatValue($sum) . "\n";
            $out .= $name . '_count' . self::promLabels($labelStr) . ' ' . $count . "\n";
        }

        return $out;
    }

    /**
     * "name|k=v,k2=v2" → ["name", "k=\"v\",k2=\"v2\""]
     *
     * @return array{0:string,1:string}
     */
    private static function splitKey(string $key): array
    {
        $pos = strpos($key, '|');
        if ($pos === false) {
            return [$key, ''];
        }
        $name = substr($key, 0, $pos);
        $labelPart = substr($key, $pos + 1);
        $pairs = explode(',', $labelPart);
        $formatted = [];
        foreach ($pairs as $p) {
            if (!$p) {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
            $formatted[] = $k . '="' . $v . '"';
        }
        return [$name, implode(',', $formatted)];
    }

    private static function promLabels(string $labelStr): string
    {
        return $labelStr === '' ? '' : '{' . $labelStr . '}';
    }

    private static function formatValue(int|float $v): string
    {
        if (is_int($v) || $v === floor($v)) {
            return (string) (int) $v;
        }
        return rtrim(rtrim(sprintf('%.6f', $v), '0'), '.');
    }

    /**
     * Sayaç verisini sıfırla (test / bakım amaçlı).
     */
    public static function reset(): void
    {
        self::init();
        @unlink(self::$countersFile);
        @unlink(self::$histogramsFile);
    }

    /**
     * JSON dosyasını locked-read ile oku.
     *
     * @return array<string, mixed>
     */
    private static function readJson(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        $fp = @fopen($file, 'rb');
        if ($fp === false) {
            return [];
        }
        try {
            if (!flock($fp, LOCK_SH)) {
                return [];
            }
            $raw = stream_get_contents($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * JSON dosyasını locked-write ile yaz (atomic temp + rename).
     *
     * @param array<string, mixed> $data
     */
    private static function writeJson(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $file . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $fp = @fopen($tmp, 'wb');
        if ($fp === false) {
            return;
        }
        try {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $json);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
        // Atomic swap (Windows'ta hedef var ise rename başarısız — önce sil)
        if (PHP_OS_FAMILY === 'Windows' && file_exists($file)) {
            @unlink($file);
        }
        @rename($tmp, $file);
    }
}
