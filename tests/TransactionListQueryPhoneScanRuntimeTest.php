<?php
use PHPUnit\Framework\TestCase;

final class TransactionListQueryPhoneScanRuntimeTest extends TestCase {
	public function test_query_fake_is_consistent_when_woocommerce_phone_scanning_is_available(): void {
		$command = kiriof_test_fixture_php_shell( array( __DIR__ . '/fixtures/transaction-list-query-phone-scan-runtime.php' ) );
		exec( $command . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( array( 'regular' => 3, 'instant' => 2, 'issue' => 1 ), $result['counts'] );
		$this->assertSame( 6, $result['total'] );
		$this->assertSame( 3, $result['page'] );
		$this->assertSame( 1, $result['phone_scans'] );
		$this->assertTrue( $result['badge_queries_match'] );
		$this->assertStringContainsString( 'LIMIT 25 OFFSET 50', $result['clamped_sql'] );
		$this->assertSame( 55, $result['issue_count'] );
	}
}
