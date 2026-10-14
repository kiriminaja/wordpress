<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates PHP syntax across all plugin source files.
 */
final class SyntaxValidationTest extends TestCase
{
    private static function findPhpFiles(): array
    {
        $files = [];
        // Prune before descent, including dependencies nested in owned directories.
        // Scan the root so development scripts and new source directories stay covered.
        $excludedDirectories = ['vendor', 'node_modules', 'build', 'tests', '.git', 'docs', '.paratest.cache'];
        $source = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(PLUGIN_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($excludedDirectories): bool {
                if (str_contains($file->getPathname(), '.zip')) {
                    return false;
                }

                if ($file->isDir()) {
                    return !$file->isLink() && !in_array($file->getFilename(), $excludedDirectories, true);
                }

                return $file->isFile() && $file->getExtension() === 'php';
            }
        );
        $iterator = new RecursiveIteratorIterator(
            $source
        );
        foreach ($iterator as $file) {
            $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    #[Test]
    public function every_php_file_has_valid_syntax(): void
    {
        $files = self::findPhpFiles();
        $this->assertNotEmpty($files, 'No PHP source files found in plugin directory');

        $failures = [];
        foreach ($files as $filePath) {
            $output = [];
            $exitCode = 0;
            // Use the runner's PHP version and retain compiler checks beyond TOKEN_PARSE.
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);
            if ($exitCode !== 0) {
                $failures[] = "{$filePath}:\n" . implode("\n", $output);
            }
        }

        $this->assertSame(
            0,
            count($failures),
            "PHP lint failures:\n" . implode("\n\n", $failures)
        );
    }
}
