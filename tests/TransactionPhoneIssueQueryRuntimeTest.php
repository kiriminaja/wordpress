<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TransactionPhoneIssueQueryRuntimeTest extends TestCase {
	public function test_unavailable_order_uses_the_same_snapshot_as_row_display(): void {
		foreach ( array( 'legacy', 'hpos' ) as $storage ) {
			$result = $this->scenario( $storage, 'order-unavailable' );
			$this->assertSame( 3, $result['page']['total'] );
			$this->assertNotContains( 1, array_column( $result['regular_all']['results'], 'wc_order_id' ) );
			$this->assertSame( 3, $result['badges']['issue'] );
		}
	}
	public function test_overlong_local_phone_is_exclusively_an_issue_but_allowed_international_phone_stays_regular(): void {
		foreach ( array( 'legacy', 'hpos' ) as $storage ) {
			$result = $this->scenario( $storage, 'phone-length' );
			$this->assertSame( 3, $result['page']['total'] );
			$this->assertNotContains( 1, array_column( $result['regular']['results'], 'wc_order_id' ) );
			$this->assertContains( 2, array_column( $result['regular']['results'], 'wc_order_id' ) );
			$this->assertNotContains( 1, array_column( $result['regular_all']['results'], 'wc_order_id' ) );
			$this->assertContains( 2, array_column( $result['regular_all']['results'], 'wc_order_id' ) );
			$this->assertSame( 3, $result['badges']['issue'] );
		}
	}
	private function scenario( string $storage, string $scenario ): array {
		$command = kiriof_test_fixture_php_shell( array( PLUGIN_DIR . '/tests/fixtures/transaction-phone-issue-query.php', $storage, $scenario ) );
		exec( $command . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_duplicate_snapshots_and_whitespace_booking_fields_keep_order_issue_priority(): void {
		foreach ( array( 'legacy', 'hpos' ) as $storage ) {
			$result = $this->scenario( $storage, 'duplicates' );
			$this->assertSame( 3, $result['badges']['issue'] );
			$this->assertSame( 3, $result['page']['total'] );
			$this->assertNotContains( 1, array_column( $result['regular']['results'], 'wc_order_id' ) );
			$this->assertSame( array( 8, 4 ), array_column( $result['corrected']['results'], 'wc_order_id' ) );
			$this->assertTrue( $result['page']['results'][0]['kiriof_phone_issue'] );
		}
	}

	public function test_phone_issues_beyond_first_candidate_batch_are_included_once(): void {
		foreach ( array( 'legacy', 'hpos' ) as $storage ) {
			$result = $this->scenario( $storage, 'large' );
			$this->assertSame( 4, $result['page']['total'] );
			$this->assertSame( 4, $result['badges']['issue'] );
			$this->assertSame( 204, $result['badges']['regular'] );
			$this->assertSame( 206, $result['reads'] );
			$this->assertSame( 2, $result['scans'] );
			$this->assertNotContains( 212, array_column( $result['regular']['results'], 'wc_order_id' ) );
		}
	}

	#[Test]
	public function effective_phone_issues_share_sql_counts_pagination_and_live_correction_in_both_storages(): void {
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			$this->markTestSkipped( 'Requires PDO SQLite.' );
		}
		foreach ( array( 'legacy', 'hpos' ) as $storage ) {
			$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/transaction-phone-issue-query.php' ) . ' ' . escapeshellarg( $storage );
			$result = json_decode( (string) shell_exec( $command ), true, 512, JSON_THROW_ON_ERROR );
			$this->assertSame( 3, $result['page']['total'], $storage );
			$this->assertSame( 2, $result['page']['page'] );
			$this->assertSame( array( 1 ), array_column( $result['page']['results'], 'wc_order_id' ) );
			$this->assertSame( 3, $result['counts']['order-issue'] );
			$this->assertSame( array( 'regular' => 4, 'instant' => 1, 'issue' => 3 ), $result['badges'] );
			$this->assertSame( 4, $result['counts']['wc-processing'] );
			$this->assertSame( array( 10, 5, 3, 2 ), array_column( $result['regular']['results'], 'wc_order_id' ) );
			$this->assertSame( array( 6, 5 ), array_column( $result['processed']['results'], 'wc_order_id' ) );
			$this->assertSame( array( 8, 4 ), array_column( $result['corrected']['results'], 'wc_order_id' ) );
			$this->assertContains( 1, array_column( $result['corrected_regular']['results'], 'wc_order_id' ) );
			$this->assertSame( 2, $result['corrected_badges']['issue'] );
			$this->assertSame( 5, $result['corrected_badges']['regular'] );
			$this->assertSame( 5, $result['reads'], 'Only five eligible unbooked rows hydrate, once across list and status counts.' );
			$this->assertSame( 1, $result['scans'] );
		}
	}
}
