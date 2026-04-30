<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates that no legacy "kj_" prefixed identifiers remain in the codebase.
 * WordPress.org requires consistent, unique prefixing.
 */
final class PrefixValidationTest extends TestCase
{
    private static array $sourceFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$sourceFiles = self::findSourceFiles();
    }

    private static function findSourceFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(PLUGIN_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $ext = $file->getExtension();
            if (
                in_array($ext, ['php', 'js'], true)
                && !str_contains($path, '/build/')
                && !str_contains($path, '/vendor/')
                && !str_contains($path, '/tests/')
                && !str_contains($path, '/node_modules/')
                && !str_contains($path, '.zip')
            ) {
                $files[] = $path;
            }
        }
        sort($files);
        return $files;
    }

    #[Test]
    public function no_kj_underscore_prefix_in_source_files(): void
    {
        $violations = [];
        // Patterns that indicate legacy kj_ prefix usage (not inside kiriof_)
        $patterns = [
            '/(?<![a-zA-Z0-9])kj_[a-zA-Z]/',  // kj_ followed by letter (not part of kiriof_)
            '/[\'"]_kj_/',                       // '_kj_' post meta keys
            '/\$kj_/',                           // $kj_ variables
        ];

        foreach (self::$sourceFiles as $filePath) {
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $line, $matches)) {
                        // Exclude if it's part of "kiriof_" (false positive)
                        if (str_contains($line, 'kiriof_')) {
                            // Check if there's a standalone kj_ NOT inside kiriof_
                            $cleaned = str_replace('kiriof_', '', $line);
                            if (!preg_match($pattern, $cleaned)) {
                                continue;
                            }
                        }
                        $violations[] = sprintf(
                            '%s:%d → %s',
                            $rel,
                            $lineNum + 1,
                            trim($line)
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found legacy kj_ prefix in source files:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }

    #[Test]
    public function no_kj_camelcase_methods_in_source_files(): void
    {
        $violations = [];
        // Match method declarations and references like addKjSomething, getKjData
        $pattern = '/(?:function\s+|->|\$this->)[a-z]+Kj[A-Z]/';

        foreach (self::$sourceFiles as $filePath) {
            if (!str_ends_with($filePath, '.php')) {
                continue;
            }
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                if (preg_match($pattern, $line)) {
                    $violations[] = sprintf('%s:%d → %s', $rel, $lineNum + 1, trim($line));
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found camelCase Kj methods in source files:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }

    #[Test]
    public function all_defines_use_kiriof_prefix(): void
    {
        $violations = [];

        foreach (self::$sourceFiles as $filePath) {
            if (!str_ends_with($filePath, '.php')) {
                continue;
            }
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                if (preg_match('/\bdefine\s*\(\s*[\'"]([A-Z_]+)/', $line, $matches)) {
                    $constant = $matches[1];
                    // Skip WordPress constants
                    if (in_array($constant, ['ABSPATH', 'WP_UNINSTALL_PLUGIN'], true)) {
                        continue;
                    }
                    if (!str_starts_with($constant, PLUGIN_DEFINE_PREFIX)) {
                        $violations[] = sprintf(
                            '%s:%d → define("%s") should start with %s',
                            $rel,
                            $lineNum + 1,
                            $constant,
                            PLUGIN_DEFINE_PREFIX
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found non-prefixed constants:\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function all_global_functions_use_kiriof_prefix(): void
    {
        $violations = [];

        foreach (self::$sourceFiles as $filePath) {
            if (!str_ends_with($filePath, '.php')) {
                continue;
            }
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);
            $inClass = false;
            $braceDepth = 0;

            foreach ($lines as $lineNum => $line) {
                // Track class/interface/trait scope
                if (preg_match('/^\s*(class|interface|trait|enum)\s+/', $line)) {
                    $inClass = true;
                }
                if ($inClass) {
                    $braceDepth += substr_count($line, '{');
                    $braceDepth -= substr_count($line, '}');
                    if ($braceDepth <= 0) {
                        $inClass = false;
                        $braceDepth = 0;
                    }
                    continue;
                }

                if (preg_match('/^function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $matches)) {
                    $funcName = $matches[1];
                    if (!str_starts_with($funcName, PLUGIN_PREFIX)) {
                        // Skip template files (contain JS functions, not PHP globals)
                        if (str_contains($rel, 'templates/')) {
                            continue;
                        }
                        $violations[] = sprintf(
                            '%s:%d → function %s() should start with %s',
                            $rel,
                            $lineNum + 1,
                            $funcName,
                            PLUGIN_PREFIX
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found non-prefixed global functions:\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function all_ajax_actions_use_kiriof_prefix(): void
    {
        $violations = [];

        foreach (self::$sourceFiles as $filePath) {
            if (!str_ends_with($filePath, '.php')) {
                continue;
            }
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                if (preg_match('/wp_ajax(?:_nopriv)?_([a-zA-Z0-9_-]+)/', $line, $matches)) {
                    $actionName = $matches[1];
                    if (
                        !str_starts_with($actionName, 'kiriof_')
                        && !str_starts_with($actionName, 'kiriof-')
                        && !str_starts_with($actionName, 'kiriminaja')
                    ) {
                        $violations[] = sprintf(
                            '%s:%d → AJAX action "%s" should start with kiriof_ or kiriminaja',
                            $rel,
                            $lineNum + 1,
                            $actionName
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found non-prefixed AJAX actions:\n" . implode("\n", $violations)
        );
    }
}
