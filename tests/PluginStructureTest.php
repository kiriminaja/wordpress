<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates plugin structure and metadata meet WordPress.org requirements:
 * - Plugin headers
 * - Required files
 * - readme.txt format
 * - Build output consistency
 */
final class PluginStructureTest extends TestCase
{
    #[Test]
    public function main_plugin_file_exists(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/kiriminaja.php');
    }

    #[Test]
    public function readme_txt_exists(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/readme.txt');
    }

    #[Test]
    public function uninstall_php_exists(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/uninstall.php');
    }

    #[Test]
    public function license_file_exists(): void
    {
        $this->assertTrue(
            file_exists(PLUGIN_DIR . '/LICENSE') || file_exists(PLUGIN_DIR . '/license.txt'),
            'LICENSE or license.txt file must exist'
        );
    }

    #[Test]
    public function no_php_files_in_build_differ_from_source(): void
    {
        $buildDir = PLUGIN_DIR . '/build/' . PLUGIN_SLUG;
        if (!is_dir($buildDir)) {
            $this->markTestSkipped('Build directory not found — run `make zip` first');
        }

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $buildPath = $file->getPathname();
            $relativePath = str_replace($buildDir . '/', '', $buildPath);
            $sourcePath = PLUGIN_DIR . '/' . $relativePath;

            // Skip vendor (build uses --no-dev so files will differ)
            if (str_starts_with($relativePath, 'vendor/')) {
                continue;
            }

            if (!file_exists($sourcePath)) {
                continue; // Build-only files are OK (e.g., vendor)
            }

            $buildHash = md5_file($buildPath);
            $sourceHash = md5_file($sourcePath);

            if ($buildHash !== $sourceHash) {
                $violations[] = $relativePath;
            }
        }

        $this->assertEmpty(
            $violations,
            "Build files differ from source (run `make zip` to sync):\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function packaged_vendor_keeps_composer_manifest(): void
    {
        $buildDir = PLUGIN_DIR . '/build/kiriminaja-official';
        if (!is_dir($buildDir)) {
            $this->markTestSkipped('Build directory does not exist; run `make zip` first.');
        }

        $this->assertDirectoryExists($buildDir . '/vendor');
        $this->assertFileExists(
            $buildDir . '/composer.json',
            'Packaged Composer vendor files need composer.json for Plugin Check attribution'
        );
        $this->assertFileDoesNotExist($buildDir . '/composer.lock');
		$this->assertFileDoesNotExist($buildDir . '/phpstan.neon');
		$this->assertFileDoesNotExist($buildDir . '/phpstan.neon.dist');
		$this->assertFileDoesNotExist($buildDir . '/phpstan-baseline.neon');
		$this->assertFileDoesNotExist($buildDir . '/phpstan-baseline.neon.dist');
        $this->assertFileDoesNotExist($buildDir . '/vendor/bin/.phpunit.result.cache');
        $this->assertFileExists(
            $buildDir . '/vendor/kiriminaja/kiriminaja-php/src/Base/Api/Api.php',
            'The packaged KiriminAja SDK must include its API client source.'
        );
    }

    #[Test]
    public function packaged_plugin_excludes_internal_documentation(): void
    {
        $buildDir = PLUGIN_DIR . '/build/' . PLUGIN_SLUG;
        if (!is_dir($buildDir)) {
            $this->markTestSkipped('Build directory does not exist; run `make zip` first.');
        }

        $this->assertDirectoryDoesNotExist(
            $buildDir . '/docs',
            'Internal documentation must not be included in the release package'
        );
    }
}
