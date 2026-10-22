<?php
use PHPUnit\Framework\TestCase;

final class OnboardingNavigationRuntimeTest extends TestCase {
	private function assert_completion_destination( string $admin_base ): void {
		$output = array();
		$status = 0;
		exec(
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/onboarding-navigation-runtime.php' ) . ' ' . escapeshellarg( $admin_base ) . ' 2>&1',
			$output,
			$status
		);
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$bootstrap = json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
		$this->assertSame( 'complete', $bootstrap['initialStep'] );
		$this->assertSame( $admin_base . 'admin.php?page=kiriminaja-transaction', $bootstrap['dashboardUrl'] );
		$this->assertSame( 'page=kiriminaja-transaction', parse_url( $bootstrap['dashboardUrl'], PHP_URL_QUERY ) );
	}

	public function test_completion_destination_uses_transactions_at_root_installation(): void {
		$this->assert_completion_destination( 'https://fixture.test/wp-admin/' );
	}

	public function test_completion_destination_preserves_subdirectory_admin_path_and_query(): void {
		$this->assert_completion_destination( 'https://fixture.test/shop/wp-admin/' );
	}
}
