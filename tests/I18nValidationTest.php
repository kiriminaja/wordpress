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
        // Map each i18n function to the 1-based argument index that holds the text domain.
        // See: https://developer.wordpress.org/reference/functions/__/ and friends.
        $functions = [
            '__'             => 2,
            '_e'             => 2,
            'esc_html__'     => 2,
            'esc_html_e'     => 2,
            'esc_attr__'     => 2,
            'esc_attr_e'     => 2,
            'esc_xml__'      => 2,
            '_x'             => 3, // ($text, $context, $domain)
            '_ex'            => 3,
            'esc_html_x'     => 3,
            'esc_attr_x'     => 3,
            '_n'             => 4, // ($single, $plural, $number, $domain)
            '_nx'            => 5, // ($single, $plural, $number, $context, $domain)
            '_n_noop'        => 3, // ($singular, $plural, $domain)
            '_nx_noop'       => 4, // ($singular, $plural, $context, $domain)
        ];

        $violations = [];

        foreach (self::$phpFiles as $filePath) {
            $rel  = str_replace(PLUGIN_DIR . '/', '', $filePath);
            $code = file_get_contents($filePath);
            foreach ($this->extractI18nCalls($code, array_keys($functions)) as $call) {
                $expected_index = $functions[$call['name']];
                $args           = $call['args'];

                // If the call has fewer args than the domain index, the default
                // text domain (i.e. WP core 'default') is used; ignore here.
                if (count($args) < $expected_index) {
                    continue;
                }

                $domain = $this->stringLiteral($args[$expected_index - 1]);
                if ($domain === null) {
                    // Non-literal (variable, concat, etc.) — out of scope for this static check.
                    continue;
                }

                if ($domain !== self::TEXT_DOMAIN && $domain !== 'woocommerce' && $domain !== 'default') {
                    $violations[] = sprintf(
                        '%s:%d → %s() text domain "%s" should be "%s"',
                        $rel,
                        $call['line'],
                        $call['name'],
                        $domain,
                        self::TEXT_DOMAIN
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Wrong text domain in translation functions:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }

    /**
     * Tokenize PHP source and return all calls to the given i18n functions.
     *
     * @param string   $code      PHP source.
     * @param string[] $functions Function names to look for.
     * @return array<int, array{name:string, line:int, args: array<int, array<int, array{0:int|string,1?:string,2?:int}|string>>}>
     */
    private function extractI18nCalls(string $code, array $functions): array
    {
        $tokens = token_get_all($code);
        $calls  = [];
        $count  = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $tok = $tokens[$i];
            if (!is_array($tok) || $tok[0] !== T_STRING || !in_array($tok[1], $functions, true)) {
                continue;
            }

            // Skip if this is a method/property/static access or a function definition.
            $prev = $this->prevSignificant($tokens, $i);
            if ($prev !== null) {
                $p = $tokens[$prev];
                if (is_array($p) && in_array($p[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION, T_NEW], true)) {
                    continue;
                }
            }

            $next = $this->nextSignificant($tokens, $i);
            if ($next === null || $tokens[$next] !== '(') {
                continue;
            }

            // Walk the argument list, splitting on top-level commas.
            $depth   = 0;
            $args    = [];
            $current = [];
            $j       = $next + 1;
            for (; $j < $count; $j++) {
                $t = $tokens[$j];
                if ($t === '(' || $t === '[' || $t === '{') {
                    $depth++;
                    $current[] = $t;
                    continue;
                }
                if ($t === ')' || $t === ']' || $t === '}') {
                    if ($t === ')' && $depth === 0) {
                        if ($current !== []) {
                            $args[] = $current;
                        }
                        break;
                    }
                    $depth--;
                    $current[] = $t;
                    continue;
                }
                if ($t === ',' && $depth === 0) {
                    $args[]  = $current;
                    $current = [];
                    continue;
                }
                $current[] = $t;
            }

            $calls[] = [
                'name' => $tok[1],
                'line' => $tok[2],
                'args' => $args,
            ];
        }

        return $calls;
    }

    /**
     * If the argument's token stream is a single string literal, return its value.
     */
    private function stringLiteral(array $argTokens): ?string
    {
        $significant = array_values(array_filter($argTokens, function ($t): bool {
            if (is_array($t)) {
                return !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }
            return true;
        }));

        if (count($significant) !== 1) {
            return null;
        }
        $only = $significant[0];
        if (!is_array($only) || $only[0] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        // Strip surrounding quotes and unescape simple escapes.
        $raw   = $only[1];
        $quote = $raw[0];
        $body  = substr($raw, 1, -1);
        if ($quote === '"') {
            $body = str_replace(['\\"', '\\\\'], ['"', '\\'], $body);
        } else {
            $body = str_replace(["\\'", '\\\\'], ["'", '\\'], $body);
        }

        return $body;
    }

    private function prevSignificant(array $tokens, int $i): ?int
    {
        for ($k = $i - 1; $k >= 0; $k--) {
            if (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $k;
        }
        return null;
    }

    private function nextSignificant(array $tokens, int $i): ?int
    {
        $n = count($tokens);
        for ($k = $i + 1; $k < $n; $k++) {
            if (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $k;
        }
        return null;
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
