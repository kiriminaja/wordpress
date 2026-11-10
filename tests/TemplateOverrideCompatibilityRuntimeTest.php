<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class TemplateOverrideCompatibilityRuntimeTest extends TestCase {
	private function runScenario( string $scenario ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/template-override-compatibility-runtime.php' ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_plugin_override_is_used_only_for_core_templates(): void {
		$this->assertSame( 'plugin', $this->runScenario( 'core' )['selected'] );
		$this->assertSame( 'theme', $this->runScenario( 'theme' )['selected'] );
		$this->assertSame( 'third-party', $this->runScenario( 'third-party' )['selected'] );
	}
}
