<?php

use PHPUnit\Framework\TestCase;

/** Exercise the real settings migration against strict, length-limited storage. */
final class CourierServiceSchemaRuntimeTest extends TestCase {
	private function run_scenario( string $scenario ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/courier-service-schema-runtime.php' ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, implode( "\n", $output ) );
		return $result;
	}

	public function test_existing_varchar_migration_unblocks_enable_all_onboarding(): void {
		$result = $this->run_scenario( 'existing' );
		$this->assertGreaterThan( 255, $result['policy_length'] );
		$this->assertGreaterThan( 255, $result['names_length'] );
		$this->assertFalse( $result['before_success'] );
		$this->assertSame( 'Unable to save courier settings.', $result['before_message'] );
		$this->assertSame( array( 'START TRANSACTION', 'ROLLBACK' ), $result['before_transactions'] );
		$this->assertTrue( $result['rollback_preserved_rows'] );
		$this->assertTrue( $result['migration_preserved_rows'] );
		$this->assertSame( 'longtext', $result['column_type'] );
		$this->assertSame( 1, $result['value_alters'] );
		$this->assertTrue( $result['after_success'] );
		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $result['after_transactions'] );
		$this->assertTrue( $result['reload_matches'] );
		$this->assertTrue( $result['mirrors_match'] );
		$this->assertTrue( $result['every_service_enabled'] );
		$this->assertTrue( $result['couriers_done'] );
		$this->assertSame( 'unrelated merchant setting', $result['unrelated'] );
	}

	public function test_existing_longtext_migration_is_idempotent(): void {
		$result = $this->run_scenario( 'longtext' );
		$this->assertSame( 'longtext', $result['column_type'] );
		$this->assertSame( 0, $result['value_alters'] );
		$this->assertTrue( $result['rows_preserved'] );
	}

	public function test_fresh_settings_table_uses_longtext_and_saves_enable_all(): void {
		$result = $this->run_scenario( 'fresh' );
		$this->assertMatchesRegularExpression( '/`value`\s+longtext\s+NULL/i', $result['schema'] );
		$this->assertSame( 'longtext', $result['column_type'] );
		$this->assertTrue( $result['after_success'] );
		$this->assertTrue( $result['reload_matches'] );
		$this->assertTrue( $result['couriers_done'] );
		$this->assertSame( 'no', $result['is_top'] );
	}
}
