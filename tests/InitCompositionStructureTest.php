<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InitCompositionStructureTest extends TestCase
{
    #[Test]
    public function controller_composition_only_occurs_inside_instantiate(): void
    {
        $source            = file_get_contents( PLUGIN_DIR . '/inc/Init.php' );
        $register_start    = strpos( $source, 'public static function register_services' );
        $instantiate_start = strpos( $source, 'private static function instantiate' );

        $this->assertNotFalse( $register_start );
        $this->assertNotFalse( $instantiate_start );

        $register_body    = substr( $source, $register_start, $instantiate_start - $register_start );
        $instantiate_body = substr( $source, $instantiate_start );

        $this->assertStringNotContainsString( '=== $class', $register_body );
        $this->assertStringContainsString( 'Controllers\\EditOrderController::class === $class', $instantiate_body );
    }
}
