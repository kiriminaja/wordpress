<?php

use PHPUnit\Framework\TestCase;

final class OnboardingBackendLoadingRuntimeTest extends TestCase {
	private function comparable_bootstrap( array $bootstrap ): array {
		unset(
			$bootstrap['address']['i18n']['loading'],
			$bootstrap['address']['i18n']['actionError'],
			$bootstrap['address']['i18n']['retry'],
			$bootstrap['couriers']['i18n']['actionError']
		);

		return $bootstrap;
	}

	private function run_fixture( string $version, string $scenario = 'complete', string $operation = 'page' ): array {
		$output = array();
		$status = 0;
		exec(
			escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/onboarding-backend-runtime.php' ) . ' ' . escapeshellarg( $version ) . ' ' . escapeshellarg( $scenario ) . ' ' . escapeshellarg( $operation ) . ' 2>&1',
			$output,
			$status
		);
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_production_onboarding_page_omits_optional_readers_without_changing_bootstrap_contract(): void {
		$baseline = $this->run_fixture( 'baseline' );
		$current  = $this->run_fixture( 'current' );

		$this->assertSame( $this->comparable_bootstrap( $baseline['bootstrap'] ), $this->comparable_bootstrap( $current['bootstrap'] ) );
		$this->assertSame( 2, $baseline['product_queries'] );
		$this->assertSame( 8, $baseline['sql_queries'] );
		$this->assertSame( 6, $current['sql_queries'] );
		$this->assertGreaterThan( 0, $baseline['tracking_reads'] );
		$this->assertSame( 0, $current['product_queries'] );
		$this->assertSame( 0, $current['tracking_reads'] );
		$this->assertSame( $baseline['profile_reads'], $current['profile_reads'] );
	}

	public function test_required_state_is_complete_without_loading_optional_steps(): void {
		$result = $this->run_fixture( 'current', 'complete', 'required' );

		$this->assertSame( array( 'account', 'address', 'couriers', 'shipping' ), array_keys( $result['steps'] ) );
		$this->assertSame( $result['steps'], $result['repeated'] );
		$this->assertTrue( $result['complete'] );
		$this->assertSame( 'complete', $result['first'] );
		$this->assertSame( 0, $result['product_queries'] );
		$this->assertSame( 0, $result['tracking_reads'] );
	}

	public function test_optional_notices_keep_the_full_steps_contract_and_are_lazy(): void {
		$result = $this->run_fixture( 'current', 'complete', 'steps' );

		$this->assertSame( array( 'account', 'address', 'couriers', 'shipping', 'products', 'tracking' ), array_keys( $result['steps'] ) );
		$this->assertSame( $result['steps'], $result['repeated'] );
		$this->assertSame( 2, $result['product_queries'] );
		$this->assertSame( 1, $result['tracking_reads'] );
	}

	public function test_each_required_stage_and_profile_outcome_matches_the_baseline_payload(): void {
		$cases = array(
			'complete' => 'complete', 'missing-setup' => 'account', 'missing-api' => 'account',
			'missing-address' => 'address', 'empty-address' => 'address', 'no-couriers' => 'couriers',
			'deny-policy' => 'couriers', 'no-method' => 'shipping', 'disabled-locations' => 'shipping',
			'no-countries' => 'shipping', 'profile-error' => 'complete', 'empty-profile' => 'complete',
			'exception' => 'complete',
		);
		foreach ( $cases as $scenario => $first ) {
			$baseline = $this->run_fixture( 'baseline', $scenario );
			$current  = $this->run_fixture( 'current', $scenario );
			$this->assertSame( $this->comparable_bootstrap( $baseline['bootstrap'] ), $this->comparable_bootstrap( $current['bootstrap'] ), $scenario );
			$this->assertSame( $first, $current['bootstrap']['initialStep'], $scenario );
			$this->assertSame( 0, $current['product_queries'], $scenario );
			$this->assertSame( 0, $current['tracking_reads'], $scenario );
			$this->assertSame( in_array( $scenario, array( 'missing-api', 'missing-setup' ), true ) ? 0 : 1, $current['profile_reads'], $scenario );
			$this->assertStringNotContainsString( 'private-api', json_encode( $current['bootstrap'] ) );
			$this->assertStringNotContainsString( 'private-setup', json_encode( $current['bootstrap'] ) );
		}
	}

	public function test_optional_notice_payload_and_default_first_incomplete_behavior_match_baseline(): void {
		foreach ( array( 'complete' => 'products', 'products-ready' => 'tracking', 'optional-complete' => 'complete' ) as $scenario => $first ) {
			$baseline = $this->run_fixture( 'baseline', $scenario, 'steps' );
			$current  = $this->run_fixture( 'current', $scenario, 'steps' );
			$this->assertSame( $baseline, $current );
			$this->assertSame( $first, $current['first'] );
		}
	}

	public function test_redirect_targets_flags_and_exclusions_are_unchanged(): void {
		foreach ( array( 'complete', 'missing-api', 'forbidden', 'onboarding-page', 'shipping-settings', 'plugins-page' ) as $scenario ) {
			$baseline = $this->run_fixture( 'baseline', $scenario, 'redirect' );
			$current  = $this->run_fixture( 'current', $scenario, 'redirect' );
			foreach ( array( 'redirect', 'deleted', 'options' ) as $key ) {
				$this->assertSame( $baseline[ $key ] ?? null, $current[ $key ] ?? null, $scenario . ':' . $key );
			}
			$this->assertSame( 0, $current['product_queries'] );
			$this->assertSame( 0, $current['tracking_reads'] );
		}
		$this->assertSame( array( 'kiriof_onboarding_activation_redirect', 'kiriof_onboarding_update_redirect' ), $this->run_fixture( 'current', 'complete', 'redirect' )['deleted'] );
		$this->assertSame( 'https://fixture.test/shop/wp-admin/admin.php?page=kiriminaja-onboarding', $this->run_fixture( 'current', 'missing-api', 'redirect' )['redirect'] );
	}

	public function test_unauthorized_render_does_not_read_state(): void {
		$result = $this->run_fixture( 'current', 'forbidden' );
		$this->assertArrayNotHasKey( 'bootstrap', $result );
		$this->assertSame( 'You do not have sufficient permissions to access this page.', $result['error'] );
		$this->assertSame( 0, $result['sql_queries'] );
		$this->assertSame( 0, $result['profile_reads'] );
	}
}
