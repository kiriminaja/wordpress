<?php
use PHPUnit\Framework\TestCase;

final class PluginLogDownloadRuntimeTest extends TestCase {
	private string $directory;

	public function test_invalid_utf8_has_visible_replacement_without_losing_safe_suffix(): void {
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', "broken \xC3 tail SAFE_SUFFIX\nNEXT_LINE\n" );
		$result = $this->download();
		$this->assertStringContainsString( 'Invalid UTF-8 bytes replaced.', $result['body'] );
		$this->assertStringContainsString( 'SAFE_SUFFIX', $result['body'] );
		$this->assertStringContainsString( 'NEXT_LINE', $result['body'] );
		$this->assertSame( 1, preg_match( '//u', $result['body'] ) );
	}

	public function test_export_preserves_all_escaped_content_above_previous_size_cap(): void {
		$line = str_repeat( '<&', 100 ) . " SAFE_LINE\n";
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', "OLDEST_MARKER\n" . str_repeat( $line, 4000 ) . "NEWEST_MARKER\n" );
		$result = $this->download();
		$this->assertGreaterThan( 512 * 1024, strlen( $result['body'] ) );
		$this->assertStringContainsString( 'NEWEST_MARKER', $result['body'] );
		$this->assertStringContainsString( 'OLDEST_MARKER', $result['body'] );
		$this->assertStringNotContainsString( 'Export limited', $result['body'] );
		$this->assertStringContainsString( '&lt;&amp;', $result['body'] );
	}

	public function test_export_has_no_directory_lookup_metadata(): void {
		$result = $this->download();
		$this->assertStringNotContainsString( 'WordPress.org', $result['body'] );
		$this->assertStringNotContainsString( 'lookup budget', $result['body'] );
		$this->assertStringNotContainsString( 'Size limit:', $result['body'] );
	}

	public function test_export_includes_safe_environment_metadata_before_log_contents(): void {
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', "LOG_CONTENT_MARKER\n" );
		$result = $this->download();
		$body = $result['body'];
		$this->assertStringStartsWith( "KiriminAja Plugin v3.0.11 Logs\nGenerated at: 2026-10-28 12:00:00\nRange: 2026-10-24 00:00:00 to 2026-10-28 12:00:00 (UTC)\nSources: kiriminaja*, shipping_discount_coupon\n", $body );
		foreach ( array( 'PHP version: ' . PHP_VERSION, 'WP: 6.9.1', 'Active theme: Shop Child &amp; Theme v1.2', 'Parent theme: Shop Parent v5.6', '- a-active (v2.3.4) [active]', '- n-network (v3.4.5) [network-active]', '- z-inactive (v1.2.3) [inactive]', '- mu (v4.5.6) [must-use]' ) as $line ) {
			$this->assertStringContainsString( $line, $body );
		}
		$this->assertLessThan( strpos( $body, 'LOG_CONTENT_MARKER' ), strpos( $body, 'Plugins:' ) );
		$this->assertLessThan( strpos( $body, '- n-network' ), strpos( $body, '- a-active' ) );
		foreach ( array( 'PRIVATE_DESCRIPTION', 'PRIVATE_URL', 'a-active/main.php', '<b>', "\nForged header" ) as $excluded ) {
			$this->assertStringNotContainsString( $excluded, $body );
		}
	}

	public function test_environment_omits_absent_parent_and_must_use_sections(): void {
		$result = $this->download( array( 'no_parent' => true, 'no_mu' => true ) );
		$this->assertStringContainsString( 'Active theme:', $result['body'] );
		$this->assertStringNotContainsString( 'Parent theme:', $result['body'] );
		$this->assertStringNotContainsString( 'Must-use plugins:', $result['body'] );
	}

	public function test_file_replaced_after_discovery_is_never_exported(): void {
		foreach ( array( 'regular', 'symlink' ) as $swap ) {
			$path = $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log';
			if ( file_exists( $path ) || is_link( $path ) ) { unlink( $path ); }
			file_put_contents( $path, 'ORIGINAL_CONTENT' );
			file_put_contents( $this->directory . '/private.log', 'OUTSIDE_PRIVATE_DATA' );
			$result = $this->download( compact( 'swap' ) );
			$this->assertStringContainsString( 'Log file changed during export; skipped.', $result['body'] );
			$this->assertStringNotContainsString( 'PRIVATE_DATA', $result['body'] );
		}
	}

	public function test_prefixed_and_hyphenated_credentials_are_redacted_even_inside_json_strings(): void {
		$secrets = array( 'access_token' => 'PRIVATE_ACCESS', 'refresh_token' => 'PRIVATE_REFRESH', 'client_secret' => 'PRIVATE_CLIENT', 'api_secret' => 'PRIVATE_API', 'x-api-key' => 'PRIVATE_HEADER', 'setup-key' => 'PRIVATE_SETUP' );
		$content = '';
		foreach ( $secrets as $key => $secret ) { $content .= $key . '=' . $secret . "\n"; }
		$content .= json_encode( array( 'context' => json_encode( array( 'access_token' => 'PRIVATE_NESTED' ) ) ) ) . "\n";
		$content .= str_repeat( '-', 100000 ) . " SAFE_SUFFIX\n";
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', $content );
		$result = $this->download();
		$this->assertStringNotContainsString( 'PRIVATE_', $result['body'] );
		$this->assertStringContainsString( 'SAFE_SUFFIX', $result['body'] );
		$this->assertStringNotContainsString( 'redaction failed', $result['body'] );
	}

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/kiriof-log-download-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory );
		mkdir( $this->directory . '/logs' );
	}

	public function test_only_today_and_previous_four_utc_log_dates_are_exported(): void {
		foreach ( array( '2026-10-23', '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29' ) as $date ) {
			file_put_contents( $this->directory . '/logs/kiriminaja_api-' . $date . '-hash.log', 'LOG_DATE_' . $date . "\n" );
			file_put_contents( $this->directory . '/logs/kiriminaja_api.0-' . $date . '-hash.log', 'ROTATED_DATE_' . $date . "\n" );
		}
		$result = $this->download();
		foreach ( array( '2026-10-24', '2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28' ) as $date ) {
			$this->assertStringContainsString( 'LOG_DATE_' . $date, $result['body'] );
			$this->assertStringContainsString( 'ROTATED_DATE_' . $date, $result['body'] );
		}
		foreach ( array( '2026-10-23', '2026-10-29' ) as $date ) {
			$this->assertStringNotContainsString( $date, $result['body'] );
		}
	}

	public function test_quoted_secrets_with_spaces_are_redacted_in_full(): void {
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', '{"token":"private token tail","pin":"123456","api_key":"secret key tail"}' . "\nSAFE_MESSAGE\n" );
		$result = $this->download();
		foreach ( array( 'private', 'tail', '123456', 'secret key' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $result['body'] );
		}
		$this->assertStringContainsString( 'SAFE_MESSAGE', $result['body'] );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->directory . '/logs/*' ) ?: array() as $path ) { unlink( $path ); }
		rmdir( $this->directory . '/logs' );
		foreach ( glob( $this->directory . '/*' ) ?: array() as $path ) { unlink( $path ); }
		rmdir( $this->directory );
	}

	private function download( array $scenario = array(), int $expected_status = 0, bool $sample = false ): array {
		$output = $this->directory . '/download.log';
		$command = kiriof_test_fixture_php_shell( array( '-d', 'memory_limit=32M', '-d', 'display_errors=0', __DIR__ . '/fixtures/plugin-log-download-runtime.php', json_encode( $scenario + array( 'directory' => $this->directory ), JSON_THROW_ON_ERROR ) ) );
		exec( $command . ' > ' . escapeshellarg( $output ) . ' 2> ' . escapeshellarg( $this->directory . '/stderr.log' ), $lines, $status );
		$this->assertSame( $expected_status, $status, file_get_contents( $this->directory . '/stderr.log' ) );
		if ( $sample ) {
			$stream = fopen( $output, 'rb' );
			$body = fread( $stream, 2048 );
			fseek( $stream, -min( 2048, filesize( $output ) ), SEEK_END );
			$body .= fread( $stream, 2048 );
			fclose( $stream );
		} else {
			$body = file_get_contents( $output );
		}
		return array( 'body' => $body, 'meta' => json_decode( file_get_contents( $this->directory . '/result.json' ), true, 512, JSON_THROW_ON_ERROR ) );
	}

	public function test_large_log_completes_with_bounded_memory(): void {
		$file = fopen( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', 'wb' );
		$line = str_repeat( 'diagnostic ', 800 ) . "\n";
		for ( $i = 0; $i < 4096; ++$i ) { fwrite( $file, $line ); }
		fwrite( $file, "END_OF_LOG\n" );
		fclose( $file );
		$result = $this->download();
		$this->assertStringContainsString( 'END_OF_LOG', $result['body'] );
		$this->assertLessThan( 24 * 1024 * 1024, $result['meta']['peak_memory'] );
	}

	public function test_clean_attachment_keeps_plugin_scope_rotations_and_redacts_secrets(): void {
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', 'token=secret-token api_key="private-key" pin=123456 Authorization: Bearer private-bearer' . "\nEND_OF_LOG\n" );
		file_put_contents( $this->directory . '/logs/kiriminaja_api.0-2026-10-28-hash.log', "ROTATED_PLUGIN_LOG\n" );
		file_put_contents( $this->directory . '/logs/other-plugin-2026-10-28-hash.log', 'OTHER_PLUGIN_PRIVATE_DATA' );
		file_put_contents( $this->directory . '/logs/kiriminaja-other-plugin-2026-10-28-hash.log', 'PREFIX_COLLISION_PRIVATE_DATA' );
		$result = $this->download( array( 'buffered' => true ) );
		foreach ( array( 'secret-token', 'private-key', '123456', 'private-bearer', 'UNRELATED_BUFFERED_OUTPUT', 'OTHER_PLUGIN_PRIVATE_DATA', 'PREFIX_COLLISION_PRIVATE_DATA' ) as $private ) {
			$this->assertStringNotContainsString( $private, $result['body'] );
		}
		$this->assertStringContainsString( 'ROTATED_PLUGIN_LOG', $result['body'] );
		$this->assertStringContainsString( 'END_OF_LOG', $result['body'] );
		$this->assertStringStartsWith( 'KiriminAja Plugin v3.0.11 Logs', $result['body'] );
		$this->assertContains( 'Content-Type: text/plain; charset=utf-8', $result['meta']['headers'] );
		$this->assertContains( 'Content-Length: ' . strlen( $result['body'] ), $result['meta']['headers'] );
		$this->assertContains( 'X-Content-Type-Options: nosniff', $result['meta']['headers'] );
		$this->assertContains( 'Content-Length', $result['meta']['removed_headers'] );
	}

	public function test_empty_export_is_a_complete_attachment(): void {
		$result = $this->download();
		$this->assertStringContainsString( 'No KiriminAja plugin logs found.', $result['body'] );
		$this->assertContains( 'Content-Length: ' . strlen( $result['body'] ), $result['meta']['headers'] );
	}

	public function test_permission_and_nonce_failure_never_export_logs(): void {
		file_put_contents( $this->directory . '/logs/kiriminaja-2026-10-28-hash.log', 'PRIVATE_LOG_DATA' );
		foreach ( array( array( 'permission' => false ), array( 'nonce' => false ) ) as $scenario ) {
			$result = $this->download( $scenario, 3 );
			$this->assertSame( 'DOWNLOAD_DENIED', $result['body'] );
			$this->assertSame( array(), $result['meta']['headers'] );
		}
	}

	public function test_oversized_line_is_omitted_without_leaking_split_secret_and_export_continues(): void {
		$content = str_repeat( 'x', 1048570 ) . ' token=SECRET_ACROSS_READ_BOUNDARY' . str_repeat( 'y', 65536 ) . "\nAFTER_OVERSIZED_LINE\n";
		file_put_contents( $this->directory . '/logs/kiriminaja-2026-10-28-hash.log', $content );
		$result = $this->download();
		$this->assertStringNotContainsString( 'SECRET', $result['body'] );
		$this->assertStringContainsString( '[Oversized or incomplete log line omitted for safe export.]', $result['body'] );
		$this->assertStringContainsString( 'AFTER_OVERSIZED_LINE', $result['body'] );
	}

	public function test_escaped_quotes_and_embedded_json_never_leak_secret_suffixes(): void {
		$payload = array( 'token' => 'prefix"SECRET_TAIL', 'pin' => '123456', 'api_key' => 'key\\PRIVATE_TAIL' );
		$content = json_encode( $payload ) . "\n" . json_encode( array( 'request_payload_json' => json_encode( $payload ) ) ) . "\nSAFE_MESSAGE\n";
		file_put_contents( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', $content );
		$result = $this->download();
		foreach ( array( 'SECRET_TAIL', 'PRIVATE_TAIL', '123456' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $result['body'] );
		}
		$this->assertStringContainsString( 'SAFE_MESSAGE', $result['body'] );
	}

	public function test_large_pickup_json_line_is_preserved_but_pin_is_redacted(): void {
		$payload = array( 'packages' => array_fill( 0, 500, array( 'order_id' => 'ORDER-FIXTURE', 'destination_address' => str_repeat( 'a', 150 ), 'qty' => 2 ) ), 'pin' => '123456' );
		file_put_contents( $this->directory . '/logs/kiriminaja_request_pickup-2026-10-28-hash.log', json_encode( array( 'request_payload_json' => json_encode( $payload ) ) ) . "\n" );
		$result = $this->download();
		$this->assertStringNotContainsString( '123456', $result['body'] );
		$this->assertStringNotContainsString( 'omitted', $result['body'] );
		$this->assertSame( 500, substr_count( $result['body'], 'ORDER-FIXTURE' ) );
	}

	public function test_large_export_preserves_oldest_and_latest_entries_without_total_size_limit(): void {
		$file = fopen( $this->directory . '/logs/kiriminaja_api-2026-10-28-hash.log', 'wb' );
		fwrite( $file, "OLDEST_ENTRY\n" );
		$line = str_repeat( 'diagnostic ', 800 ) . "\n";
		for ( $i = 0; $i < 8000; ++$i ) { fwrite( $file, $line ); }
		fwrite( $file, "LATEST_ENTRY\n" );
		fclose( $file );
		$result = $this->download( array(), 0, true );
		$this->assertStringContainsString( 'OLDEST_ENTRY', $result['body'] );
		$this->assertStringContainsString( 'LATEST_ENTRY', $result['body'] );
		$this->assertStringNotContainsString( 'Export limited', $result['body'] );
	}

	public function test_plugin_named_symlink_cannot_export_a_file_outside_log_directory(): void {
		file_put_contents( $this->directory . '/private.log', 'PRIVATE_OUTSIDE_FILE' );
		symlink( $this->directory . '/private.log', $this->directory . '/logs/kiriminaja-2026-10-28-link.log' );
		$result = $this->download();
		$this->assertStringNotContainsString( 'PRIVATE_OUTSIDE_FILE', $result['body'] );
		$this->assertStringContainsString( 'No KiriminAja plugin logs found.', $result['body'] );
	}
}
