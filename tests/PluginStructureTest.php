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
    public function main_plugin_file_has_required_headers(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $requiredHeaders = [
            'Plugin Name',
            'Version',
            'Author',
            'License',
            'Text Domain',
            'Description',
        ];

        foreach ($requiredHeaders as $header) {
            $this->assertStringContainsString(
                $header,
                $content,
                "Missing required plugin header: {$header}"
            );
        }
    }

    #[Test]
    public function text_domain_matches_slug(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $this->assertMatchesRegularExpression(
            '/Text Domain:\s+kiriminaja-official/',
            $content,
            'Text Domain should be "kiriminaja-official"'
        );
    }

    #[Test]
    public function version_defined_in_main_file(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $this->assertMatchesRegularExpression(
            '/define\s*\(\s*[\'"]KIRIOF_VERSION[\'"]\s*,\s*[\'"](\d+\.\d+\.\d+)[\'"]/',
            $content,
            'KIRIOF_VERSION constant must be defined with semver format'
        );
    }

    #[Test]
    public function version_header_matches_constant(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');

        preg_match('/\*\s*Version:\s+(\d+\.\d+\.\d+)/', $content, $headerMatch);
        preg_match('/KIRIOF_VERSION[\'"],\s*[\'"](\d+\.\d+\.\d+)/', $content, $constantMatch);

        $this->assertNotEmpty($headerMatch, 'Version header not found');
        $this->assertNotEmpty($constantMatch, 'KIRIOF_VERSION constant not found');
        $this->assertSame(
            $headerMatch[1],
            $constantMatch[1],
            'Version header and KIRIOF_VERSION constant must match'
        );
    }

    #[Test]
    public function readme_txt_exists(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/readme.txt');
    }

    #[Test]
    public function readme_stable_tag_matches_version(): void
    {
        $mainContent = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        preg_match('/KIRIOF_VERSION[\'"],\s*[\'"](\d+\.\d+\.\d+)/', $mainContent, $versionMatch);

        $readmeContent = file_get_contents(PLUGIN_DIR . '/readme.txt');
        preg_match('/Stable tag:\s*(\S+)/', $readmeContent, $stableMatch);

        $this->assertNotEmpty($versionMatch, 'KIRIOF_VERSION not found');
        $this->assertNotEmpty($stableMatch, 'Stable tag not found in readme.txt');
        $this->assertSame(
            $versionMatch[1],
            $stableMatch[1],
            'readme.txt Stable tag must match KIRIOF_VERSION'
        );
    }

    #[Test]
    public function readme_has_required_sections(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/readme.txt');
        $requiredSections = [
            '== Description ==',
            '== Installation ==',
            '== Changelog ==',
        ];

        foreach ($requiredSections as $section) {
            $this->assertStringContainsString(
                $section,
                $content,
                "readme.txt missing required section: {$section}"
            );
        }
    }

    #[Test]
    public function uninstall_php_exists(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/uninstall.php');
    }

    #[Test]
    public function uninstall_checks_wp_uninstall_plugin(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/uninstall.php');
        $this->assertStringContainsString(
            'WP_UNINSTALL_PLUGIN',
            $content,
            'uninstall.php must check WP_UNINSTALL_PLUGIN constant'
        );
    }

    #[Test]
    public function index_php_exists_in_root(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/index.php');
    }

    #[Test]
    public function composer_json_has_correct_autoload(): void
    {
        $this->assertFileExists(PLUGIN_DIR . '/composer.json');
        $composer = json_decode(file_get_contents(PLUGIN_DIR . '/composer.json'), true);

        $this->assertArrayHasKey('autoload', $composer);
        $this->assertArrayHasKey('psr-4', $composer['autoload']);
        $this->assertArrayHasKey('KiriminAjaOfficial\\', $composer['autoload']['psr-4']);
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
}
