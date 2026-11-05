<?php

use PHPUnit\Framework\TestCase;

final class LogDirectoryStatusRuntimeTest extends TestCase {
	private function run_fixture( string $scenario ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/log-directory-status-runtime.php' ) . ' ' . escapeshellarg( $scenario );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result );
		return $result;
	}

	public function test_paths_and_child_parent_themes_have_independent_slug_observations(): void {
		$result = $this->run_fixture( 'paths' );
		foreach ( array( 'folder', 'single', 'child-theme', 'parent-theme' ) as $index => $slug ) {
			$this->assertStringContainsString( 'listed for slug ' . $slug . '; basis:', $result['statuses'][ $index ] );
			parse_str( parse_url( $result['requests'][ $index ]['url'], PHP_URL_QUERY ), $query );
			$this->assertSame( array( 'slug' => $slug ), $query['request'] );
			$this->assertSame( $index < 2 ? 'plugin_information' : 'theme_information', $query['action'] );
		}
		$this->assertStringContainsString( 'plugin folder', $result['statuses'][0] );
		$this->assertStringContainsString( 'single-file basename', $result['statuses'][1] );
	}

	public function test_fixed_https_transport_is_bounded_and_sends_no_site_or_version(): void {
		$result = $this->run_fixture( 'paths' );
		foreach ( $result['requests'] as $request ) {
			$this->assertMatchesRegularExpression( '~^https://api\.wordpress\.org/(plugins|themes)/info/1\.2/\?~', $request['url'] );
			$this->assertSame( 1, $request['args']['timeout'] );
			$this->assertSame( 0, $request['args']['redirection'] );
			$this->assertSame( 262144, $request['args']['limit_response_size'] );
			$this->assertSame( 'DirectoryMetadata/1.0', $request['args']['user-agent'] );
			$this->assertTrue( $request['args']['sslverify'] );
			$this->assertSame( array(), $request['args']['cookies'] );
		}
	}

	public function test_external_sources_mu_and_invalid_paths_remain_unknown_without_requests(): void {
		$result = $this->run_fixture( 'skip' );
		$this->assertSame( array(), $result['requests'] );
		foreach ( $result['statuses'] as $status ) { $this->assertStringContainsString( 'WordPress.org: unknown', $status ); }
		$this->assertStringContainsString( 'external update source', $result['statuses'][0] );
		$this->assertStringNotContainsString( 'SECRET', implode( '', $result['statuses'] ) );
		$this->assertStringContainsString( 'must-use', $result['statuses'][2] );
	}

	public function test_definitive_presence_is_reused_locally_and_from_day_cache(): void {
		$result = $this->run_fixture( 'cache' );
		$this->assertCount( 1, $result['requests'] );
		$this->assertSame( array( 86400 ), $result['writes'] );
		$this->assertCount( 1, array_unique( $result['statuses'] ) );
	}

	public function test_only_recognized_official_not_found_is_definitive_for_both_directories(): void {
		$result = $this->run_fixture( 'absent' );
		$this->assertCount( 2, $result['requests'] );
		$this->assertSame( array( 86400, 86400 ), $result['writes'] );
		foreach ( $result['statuses'] as $status ) { $this->assertStringContainsString( 'not found for slug', $status ); }
	}

	public function test_timeouts_rate_limits_server_errors_and_malformed_responses_are_unknown(): void {
		$result = $this->run_fixture( 'errors' );
		$this->assertCount( 7, $result['requests'] );
		$this->assertSame( array_fill( 0, 7, 300 ), $result['writes'] );
		foreach ( $result['statuses'] as $status ) { $this->assertStringContainsString( 'WordPress.org: unknown', $status ); }
		$this->assertStringNotContainsString( 'private', implode( '', $result['statuses'] ) );
	}

	public function test_cold_requests_are_capped_and_remaining_entries_are_unknown(): void {
		$result = $this->run_fixture( 'budget' );
		$this->assertCount( 8, $result['requests'] );
		$this->assertStringContainsString( 'lookup budget exhausted', $result['statuses'][8] );
		$this->assertCount( 8, $result['writes'] );
	}

	public function test_shared_deadline_stops_requests_before_export_budget_is_spent(): void {
		$started = microtime( true );
		$result = $this->run_fixture( 'deadline' );
		$this->assertCount( 3, $result['requests'] );
		$this->assertStringContainsString( 'lookup budget exhausted', $result['statuses'][3] );
		$this->assertLessThan( 5.0, microtime( true ) - $started );
	}
}
