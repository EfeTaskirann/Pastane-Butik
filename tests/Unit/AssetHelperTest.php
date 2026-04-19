<?php

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * asset() helper coverage — P1-11.
 */
final class AssetHelperTest extends TestCase
{
    private string $tmpRoot;
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpRoot = sys_get_temp_dir() . '/pastane-asset-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot . '/dist', 0777, true);
        @mkdir($this->tmpRoot . '/assets/js', 0777, true);

        $this->manifestPath = $this->tmpRoot . '/dist/manifest.json';
        file_put_contents($this->manifestPath, json_encode([
            'assets/js/main.js' => [
                'file' => 'js/main.abc123.js',
                'css' => ['css/main.def456.css'],
            ],
            'assets/js/admin.js' => [
                'file' => 'js/admin.xyz789.js',
            ],
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->tmpRoot . '/assets/js/main.js', "// raw source\n");

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $this->tmpRoot);
        }
        if (!defined('APP_URL')) {
            define('APP_URL', 'http://localhost/pastane');
        }
    }

    protected function tearDown(): void
    {
        // Best-effort cleanup
        foreach (['dist/manifest.json', 'assets/js/main.js'] as $f) {
            @unlink($this->tmpRoot . '/' . $f);
        }
        @rmdir($this->tmpRoot . '/dist');
        @rmdir($this->tmpRoot . '/assets/js');
        @rmdir($this->tmpRoot . '/assets');
        @rmdir($this->tmpRoot);
        parent::tearDown();
    }

    public function test_dev_mode_returns_raw_with_mtime(): void
    {
        if (!defined('APP_ENV')) {
            define('APP_ENV', 'development');
        }
        if (APP_ENV !== 'development') {
            $this->markTestSkipped('APP_ENV locked as ' . APP_ENV);
        }

        $url = asset('assets/js/main.js');
        $this->assertStringContainsString('assets/js/main.js', $url);
        $this->assertStringContainsString('?v=', $url);
    }

    public function test_production_manifest_returns_hashed_file(): void
    {
        // APP_ENV might already be defined by previous test — we skip if so.
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            $this->markTestSkipped('Production manifest test requires APP_ENV=production');
        }
        if (!defined('APP_ENV')) {
            define('APP_ENV', 'production');
        }

        $url = asset('assets/js/main.js');
        $this->assertStringContainsString('dist/js/main.abc123.js', $url);
    }

    public function test_asset_css_returns_related_stylesheets_in_prod(): void
    {
        if (defined('APP_ENV') && APP_ENV !== 'production') {
            $this->markTestSkipped('assetCss test requires production mode');
        }

        $urls = assetCss('assets/js/main.js');
        $this->assertCount(1, $urls);
        $this->assertStringContainsString('dist/css/main.def456.css', $urls[0]);
    }

    public function test_asset_css_empty_for_unknown_entry(): void
    {
        $urls = assetCss('assets/js/does-not-exist.js');
        $this->assertSame([], $urls);
    }
}
