<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Validates PHP syntax across all plugin source files.
 */
final class SyntaxValidationTest extends TestCase
{
    private static array $phpFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$phpFiles = self::findPhpFiles();
    }

    private static function findPhpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PLUGIN_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (
                $file->getExtension() === 'php'
                && !str_contains($path, '/build/')
                && !str_contains($path, '/vendor/')
                && !str_contains($path, '/tests/')
                && !str_contains($path, '.zip')
            ) {
                $files[] = $path;
            }
        }
        sort($files);
        return $files;
    }

    public static function phpFileProvider(): array
    {
        // Provider runs before setUpBeforeClass; discover inline.
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PLUGIN_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (
                $file->getExtension() === 'php'
                && !str_contains($path, '/build/')
                && !str_contains($path, '/vendor/')
                && !str_contains($path, '/tests/')
                && !str_contains($path, '.zip')
            ) {
                $rel = str_replace(PLUGIN_DIR . '/', '', $path);
                $files[$rel] = [$path];
            }
        }
        ksort($files);
        return $files;
    }

    #[Test]
    #[DataProvider('phpFileProvider')]
    public function every_php_file_has_valid_syntax(string $filePath): void
    {
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "Syntax error in {$filePath}:\n" . implode("\n", $output)
        );
    }

    #[Test]
    public function at_least_one_php_file_found(): void
    {
        $files = self::phpFileProvider();
        $this->assertNotEmpty($files, 'No PHP source files found in plugin directory');
    }
}
