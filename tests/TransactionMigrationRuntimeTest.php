<?php

use PHPUnit\Framework\TestCase;

final class TransactionMigrationRuntimeTest extends TestCase {
	private function run_scenario( string $scenario ): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/transaction-migration-runtime.php' ) . ' ' . escapeshellarg( $scenario ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, implode( "\n", $output ) );
		return $result;
	}

	public function test_current_schema_never_alters_status_on_repeated_requests(): void {
		$result = $this->run_scenario( 'current' );
		$this->assertCount( 100, $result['alter_counts'] );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['alter_counts'] );
		$this->assertStringNotContainsString( 'ALTER TABLE', implode( "\n", $result['queries'] ) );
		$this->assertSame( array_fill( 0, 100, $result['database_query_counts'][0] ), $result['database_query_counts'] );
		$this->assertSame( array_fill( 0, 100, '1' ), $result['option_history'] );
	}

	public function test_legacy_enum_is_upgraded_only_once(): void {
		$result = $this->run_scenario( 'legacy' );
		$this->assertSame( array( 1, 1, 1 ), $result['alter_counts'] );
		$this->assertStringContainsString( "'canceled'", $result['status']['Type'] );
		$this->assertSame( 'NO', $result['status']['Null'] );
		$this->assertSame( 'new', $result['status']['Default'] );
	}

	public function test_nullable_status_is_corrected_only_once(): void {
		$result = $this->run_scenario( 'nullable' );
		$this->assertSame( array( 1, 1, 1 ), $result['alter_counts'] );
		$this->assertSame( 'NO', $result['status']['Null'] );
	}

	public function test_incorrect_status_default_is_corrected_only_once(): void {
		$result = $this->run_scenario( 'default' );
		$this->assertSame( array( 1, 1, 1 ), $result['alter_counts'] );
		$this->assertSame( 'new', $result['status']['Default'] );
	}

	public function test_failed_metadata_read_does_not_issue_a_blind_alter(): void {
		$result = $this->run_scenario( 'read_failure' );
		$this->assertSame( array( 0, 1, 1 ), $result['alter_counts'] );
		$this->assertSame( array( null, '1', '1' ), $result['option_history'] );
	}

	public function test_failed_alter_is_retried_then_stops_after_success(): void {
		$result = $this->run_scenario( 'alter_failure' );
		$this->assertSame( array( 1, 2, 2 ), $result['alter_counts'] );
		$this->assertSame( array( null, '1', '1' ), $result['option_history'] );
		$this->assertStringContainsString( "'canceled'", $result['status']['Type'] );
	}

	public function test_missing_status_does_not_trigger_modify(): void {
		$result = $this->run_scenario( 'missing_status' );
		$this->assertSame( array( 0, 0, 0 ), $result['alter_counts'] );
		$this->assertStringNotContainsString( 'MODIFY COLUMN', implode( "\n", $result['queries'] ) );
		$this->assertSame( array( null, null, null ), $result['option_history'] );
	}

	public function test_other_missing_columns_are_still_added_once(): void {
		$result = $this->run_scenario( 'missing_canceled_at' );
		$this->assertSame( array( 0, 0, 0 ), $result['alter_counts'] );
		$alters = array_values( array_filter( $result['queries'], static function ( $sql ) { return str_starts_with( $sql, 'ALTER TABLE' ); } ) );
		$this->assertSame( array( 'ALTER TABLE `traffic_kiriminaja_transactions` ADD canceled_at timestamp NULL DEFAULT NULL' ), $alters );
		$this->assertContains( 'canceled_at', $result['columns'] );
	}

	public function test_custom_prefix_and_sanitized_suffix_target_the_correct_table(): void {
		$result = $this->run_scenario( 'suffix' );
		$this->assertSame( array( 1, 1, 1 ), $result['alter_counts'] );
		$this->assertStringContainsString( 'ALTER TABLE `traffic_kiriminaja_transactions_archive`', $result['queries'][0] );
		$this->assertStringContainsString( "SHOW COLUMNS FROM `traffic_kiriminaja_transactions_archive` LIKE 'status'", $result['metadata_queries'][0] );
	}

	public function test_fresh_install_uses_current_enum_without_a_followup_alter(): void {
		$result = $this->run_scenario( 'fresh' );
		$this->assertSame( array( 0, 0, 0 ), $result['alter_counts'] );
		$this->assertCount( 1, $result['schemas'] );
		$this->assertSame( array( '1', '1', '1' ), $result['option_history'] );
		$this->assertStringContainsString( "`status` " . $result['status']['Type'] . " NOT NULL DEFAULT 'new'", $result['schemas'][0] );
		$this->assertSame( array(), $result['queries'] );
	}

	public function test_completed_version_skips_all_database_queries(): void {
		$result = $this->run_scenario( 'complete' );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['database_query_counts'] );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['alter_counts'] );
	}

	public function test_cleared_version_never_alters_a_correct_status_column(): void {
		$result = $this->run_scenario( 'version_reset' );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['alter_counts'] );
		$this->assertSame( array_fill( 0, 100, '1' ), $result['option_history'] );
	}

	public function test_failed_backfill_retries_without_altering_correct_status(): void {
		$result = $this->run_scenario( 'backfill_failure' );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['alter_counts'] );
		$this->assertSame( array_fill( 0, 100, null ), $result['option_history'] );
		$this->assertGreaterThan( $result['database_query_counts'][0], $result['database_query_counts'][99] );
	}

	public function test_failed_index_retries_without_altering_correct_status(): void {
		$result = $this->run_scenario( 'index_failure' );
		$this->assertSame( array_fill( 0, 100, 0 ), $result['alter_counts'] );
		$this->assertSame( array_fill( 0, 100, null ), $result['option_history'] );
		$this->assertGreaterThan( $result['database_query_counts'][0], $result['database_query_counts'][99] );
	}

	public function test_failed_column_inspection_leaves_legacy_upgrade_retryable(): void {
		$result = $this->run_scenario( 'describe_failure' );
		$this->assertSame( array( 0, 1, 1 ), $result['alter_counts'] );
		$this->assertSame( array( null, '1', '1' ), $result['option_history'] );
		$this->assertSame( 2, $result['database_query_counts'][0] );
	}

	public function test_successful_status_upgrade_is_not_repeated_during_backfill_retries(): void {
		$result = $this->run_scenario( 'legacy_backfill_failure' );
		$this->assertSame( array_fill( 0, 100, 1 ), $result['alter_counts'] );
		$this->assertSame( array_fill( 0, 100, null ), $result['option_history'] );
	}
}
