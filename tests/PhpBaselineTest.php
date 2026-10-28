<?php

use PHPUnit\Framework\TestCase;

final class PhpBaselineTest extends TestCase {
	public function test_plugin_dependency_and_tooling_baselines_are_php_82(): void {
		$composer = json_decode( file_get_contents( PLUGIN_DIR . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
		$lock = json_decode( file_get_contents( PLUGIN_DIR . '/composer.lock' ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( '>=8.2.35', $composer['require']['php'] ?? null );
		$this->assertSame( '8.2.35', $composer['config']['platform']['php'] );
		$this->assertSame( '>=8.2.35', $lock['platform']['php'] ?? null );
		$this->assertSame( '8.2.35', $lock['platform-overrides']['php'] );
		foreach ( array( 'kiriminaja.php', 'readme.txt' ) as $file ) {
			$this->assertMatchesRegularExpression( '/^\s*(?:\*\s*)?Requires PHP:\s*8\.2\.35\s*$/m', file_get_contents( PLUGIN_DIR . '/' . $file ), $file );
		}
		$this->assertSame( '8.2.35', trim( file_get_contents( PLUGIN_DIR . '/.php-version' ) ) );
		$this->assertSame( '8.2', json_decode( file_get_contents( PLUGIN_DIR . '/.vscode/settings.json' ), true, 512, JSON_THROW_ON_ERROR )['php.version'] );
		$this->assertStringContainsString( 'phpVersion: 80235', file_get_contents( PLUGIN_DIR . '/phpstan.neon' ) );
	}

	public function test_all_ci_php_runtimes_use_only_php_82(): void {
		$setups = 0;
		foreach ( glob( PLUGIN_DIR . '/.github/workflows/*.yml' ) as $path ) {
			$workflow = file_get_contents( $path );
			$count = preg_match_all( '/php-version:\s*["\x27]?([^"\x27\s]+)["\x27]?/', $workflow, $versions );
			$this->assertSame( substr_count( $workflow, 'shivammathur/setup-php@' ), $count, basename( $path ) );
			foreach ( $versions[1] as $version ) {
				++$setups;
				$this->assertSame( '8.2.35', $version, basename( $path ) );
			}
		}
		$this->assertGreaterThanOrEqual( 6, $setups, 'Check every build, smoke, test, deployment and E2E PHP setup.' );
		$tests = file_get_contents( PLUGIN_DIR . '/.github/workflows/test.yml' );
		$this->assertStringNotContainsString( 'matrix.php', $tests );
		$this->assertStringContainsString( 'name: "PHP tests (8.2.35)"', $tests );
	}
}
