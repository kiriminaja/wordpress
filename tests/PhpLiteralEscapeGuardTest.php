<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpLiteralEscapeGuardTest extends TestCase
{
    #[Test]
    public function package_guard_rejects_literal_php_escape_tokens(): void
    {
        $script = file_get_contents( PLUGIN_DIR . '/scripts/check-php-literal-escapes.php' );
        $makefile = file_get_contents( PLUGIN_DIR . '/Makefile' );

        $this->assertStringContainsString( "token_get_all", $script );
        $this->assertStringContainsString( "array( '\\\\t', '\\\\n', '\\\\r' )", $script );
        $this->assertStringContainsString( 'scripts/check-php-literal-escapes.php', $makefile );
    }
}
