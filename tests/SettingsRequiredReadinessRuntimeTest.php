<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingsRequiredReadinessRuntimeTest extends TestCase {
	public static function required_states(): array {
		return array(
			'complete'          => array( '', true, 'products' ),
			'account missing'   => array( 'account', false, 'account' ),
			'address missing'   => array( 'address', false, 'address' ),
			'couriers missing'  => array( 'couriers', false, 'couriers' ),
			'method disabled'   => array( 'shipping', false, 'shipping' ),
			'locations disabled' => array( 'locations', false, 'shipping' ),
		);
	}

	#[DataProvider( 'required_states' )]
	public function test_redirect_gate_skips_optional_queries_but_full_checklist_still_loads_them( string $missing, bool $complete, string $first ): void {
		$output = array();
		$status = 0;
		exec(
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/settings-required-readiness-runtime.php' ) . ' ' . escapeshellarg( $missing ) . ' 2>&1',
			$output,
			$status
		);
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( $complete, $result['complete'] );
		$this->assertSame( 0, $result['gate_queries'] );
		$this->assertSame( 0, $result['gate_tracking_calls'] );
		$this->assertSame( 2, $result['full_queries'] );
		$this->assertSame( 1, $result['full_tracking_calls'] );
		$this->assertSame( array( 'account', 'address', 'couriers', 'shipping', 'products', 'tracking' ), array_keys( $result['steps'] ) );
		$this->assertFalse( $result['steps']['products']['done'] );
		$this->assertFalse( $result['steps']['tracking']['done'] );
		$this->assertSame( $first, $result['first'] );
	}
}
