<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ParaTestRunnerPolicyTest extends TestCase {
    public function test_composer_and_make_use_paratest_without_direct_phpunit_dependency(): void {
        $composer = json_decode( file_get_contents( PLUGIN_DIR . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
        $this->assertArrayHasKey( 'brianium/paratest', $composer['require-dev'] );
        $this->assertArrayNotHasKey( 'phpunit/phpunit', $composer['require-dev'] );
        $this->assertSame( 'paratest --configuration paratest.xml --testdox', $composer['scripts']['test'] );
        $make = file_get_contents( PLUGIN_DIR . '/Makefile' );
        $this->assertStringContainsString( "test:\n\tvendor/bin/paratest --configuration paratest.xml --testdox", $make );
        foreach ( array( 'phpunit.xml', 'phpunit.xml.dist' ) as $file ) {
            $this->assertFileDoesNotExist( PLUGIN_DIR . '/' . $file );
        }
    }

    public function test_ci_and_project_scripts_do_not_run_the_underlying_binary(): void {
        $files = array_merge( glob( PLUGIN_DIR . '/.github/workflows/*.yml' ), glob( PLUGIN_DIR . '/scripts/*' ) );
        foreach ( $files as $file ) {
            if ( ! is_file( $file ) ) { continue; }
            $content = file_get_contents( $file );
            $this->assertDoesNotMatchRegularExpression( '~(?:vendor/bin/|(?:^|\s)(?:php\s+)?)(?:phpunit)(?:\s|$)~m', $content, basename( $file ) );
        }
        $ci = file_get_contents( PLUGIN_DIR . '/.github/workflows/test.yml' );
        $this->assertStringContainsString( 'run: make test', $ci );
    }
}
