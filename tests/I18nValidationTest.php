<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Validates internationalization requirements:
 * - Text domain consistency
 * - Translation function usage
 */
final class I18nValidationTest extends TestCase
{
    private const TEXT_DOMAIN = 'kiriminaja-official';

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
    public function translation_functions_use_correct_text_domain(): void
    {
        $violations = [];
        // Match __(), _e(), esc_html__(), esc_html_e(), esc_attr__(), esc_attr_e(), _n(), _x()
        $pattern = '/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_n|_x)\s*\(.+?[\'"]([a-zA-Z0-9_-]+)[\'"]\s*\)/';

        foreach (self::$phpFiles as $filePath) {
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $rel = str_replace(PLUGIN_DIR . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches[1] as $domain) {
                        if ($domain !== self::TEXT_DOMAIN && $domain !== 'woocommerce') {
                            $violations[] = sprintf(
                                '%s:%d → text domain "%s" should be "%s"',
                                $rel,
                                $lineNum + 1,
                                $domain,
                                self::TEXT_DOMAIN
                            );
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Wrong text domain in translation functions:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }

    #[Test]
    public function lang_directory_exists(): void
    {
        $this->assertDirectoryExists(
            PLUGIN_DIR . '/lang',
            'lang/ directory must exist for translations'
        );
    }

    #[Test]
    public function domain_path_header_matches(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/kiriminaja.php');
        $this->assertMatchesRegularExpression(
            '/Domain Path:\s+\/lang/',
            $content,
            'Domain Path header should point to /lang'
        );
    }
}
