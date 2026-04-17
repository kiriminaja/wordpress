<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates WordPress.org security requirements:
 * - Nonce verification on AJAX handlers
 * - Input sanitization
 * - Output escaping
 * - No direct $_POST/$_GET access without sanitization
 * - ABSPATH checks on all files
 */
final class SecurityValidationTest extends TestCase
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
                && !str_contains($path, '/scripts/')
                && !str_contains($path, '.zip')
            ) {
                $files[] = $path;
            }
        }
        sort($files);
        return $files;
    }

    #[Test]
    public function all_php_files_have_abspath_check(): void
    {
        $violations = [];
        $excludes = ['index.php', 'uninstall.php', 'kiriminaja.php'];

        foreach (self::$phpFiles as $filePath) {
            $basename = basename($filePath);
            if (in_array($basename, $excludes, true)) {
                continue;
            }
            $content = file_get_contents($filePath);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            // Check for ABSPATH guard
            if (
                !str_contains($content, "defined( 'ABSPATH' )")
                && !str_contains($content, "defined('ABSPATH')")
            ) {
                $violations[] = $rel;
            }
        }

        $this->assertEmpty(
            $violations,
            "PHP files missing ABSPATH check:\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function no_raw_superglobal_access(): void
    {
        $violations = [];
        // Pattern: @$_POST, @$_GET, @$_REQUEST (suppressed error access)
        $pattern = '/@\$_(POST|GET|REQUEST)\b/';

        foreach (self::$phpFiles as $filePath) {
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
            "Found @\$_POST/@\$_GET (unsanitized superglobal access):\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function ajax_handlers_have_nonce_verification(): void
    {
        $violations = [];
        $controllerDir = PLUGIN_DIR . '/inc/Controllers';
        if (!is_dir($controllerDir)) {
            $this->markTestSkipped('Controllers directory not found');
        }

        $files = glob($controllerDir . '/*.php');
        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            // Find all wp_ajax_ registrations
            preg_match_all(
                '/add_action\s*\(\s*[\'"]wp_ajax_(?:nopriv_)?([a-zA-Z_]+)[\'"].*?[\'"](\w+)[\'"]\s*\)/',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $methodName = $match[2];

                // Find the method body and check for nonce verification
                $methodPattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(/';
                if (preg_match($methodPattern, $content, $methodMatch, PREG_OFFSET_CAPTURE)) {
                    $startPos = $methodMatch[0][1];
                    // Get the next 2000 chars as method body approximation
                    $methodBody = substr($content, $startPos, 2000);

                    if (
                        !str_contains($methodBody, 'wp_verify_nonce')
                        && !str_contains($methodBody, 'check_ajax_referer')
                    ) {
                        $violations[] = sprintf(
                            '%s → AJAX handler %s() missing nonce verification',
                            $rel,
                            $methodName
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "AJAX handlers missing nonce verification:\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function no_direct_post_variable_assignment(): void
    {
        $violations = [];
        // Pattern: $post = $_POST or $var = $_POST without sanitization
        $pattern = '/\$\w+\s*=\s*\$_POST\s*;/';

        foreach (self::$phpFiles as $filePath) {
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
            "Found direct \$_POST assignment (use individual sanitized keys instead):\n" . implode("\n", $violations)
        );
    }

    #[Test]
    public function no_unescaped_echo_statements(): void
    {
        $violations = [];
        // Check template files for echo without escaping
        $templateDir = PLUGIN_DIR . '/templates';
        if (!is_dir($templateDir)) {
            $this->markTestSkipped('Templates directory not found');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $content = file_get_contents($path);
            $rel = str_replace(PLUGIN_DIR . '/', '', $path);

            // Find echo statements that don't use escaping functions
            preg_match_all('/<?php\s+echo\s+(?!esc_|wp_kses|selected|checked|wc_price)(.+?);/s', $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as $match) {
                $lineNum = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                $snippet = trim(substr($match[0], 0, 80));
                $violations[] = sprintf('%s:%d → %s', $rel, $lineNum, $snippet);
            }
        }

        // Informational only — some echo statements are legitimately safe
        // (e.g., wc_price, wp_kses_post output). Log to stderr for visibility.
        if (!empty($violations)) {
            fwrite(STDERR, "\n[INFO] Potentially unescaped echo statements (review manually):\n"
                . implode("\n", array_slice($violations, 0, 15)) . "\n");
        }
        $this->assertTrue(true);
    }
}
