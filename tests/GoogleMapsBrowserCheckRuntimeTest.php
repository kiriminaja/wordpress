<?php

use PHPUnit\Framework\TestCase;

final class GoogleMapsBrowserCheckRuntimeTest extends TestCase {
	private function runScenario( array $scenario = array() ): array {
		$output = array();
		$status = 1;
		exec( kiriof_test_fixture_php_shell( array( PLUGIN_DIR . '/tests/fixtures/google-maps-browser-check-runtime.php', json_encode( $scenario, JSON_THROW_ON_ERROR ) ) ) . ' 2>&1', $output, $status );
		self::assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function testConfiguredOriginsNormalizeDefaultPortsAndSupportIpv6(): void {
		foreach ( array( 'https://Store.example:443/shop/' => 'https://store.example', 'http://localhost:80/shop/' => 'http://localhost', 'https://[::1]:9443/shop/' => 'https://[::1]:9443' ) as $url => $origin ) {
			$result = $this->runScenario( array( 'home_url' => $url, 'admin_url' => $url ) );
			preg_match( '/<script id="kiriof-google-maps-check-config" type="application\/json">(.*?)<\/script>/s', $result['html'], $matches );
			$config = json_decode( $matches[1], true, 512, JSON_THROW_ON_ERROR );
			self::assertSame( $origin, $config['probeOrigin'] );
			self::assertSame( $origin, $config['targetOrigin'] );
		}
	}

	public function testStorefrontFrameAllowsOnlyConfiguredAdminEvenWhenHostsDiffer(): void {
		$result = $this->runScenario( array( 'home_url' => 'https://store.example:9443/shop/', 'admin_url' => 'https://admin.example/wordpress/wp-admin/' ) );
		preg_match( '/<script id="kiriof-google-maps-check-config" type="application\/json">(.*?)<\/script>/s', $result['html'], $matches );
		$config = json_decode( $matches[1], true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 'https://store.example:9443', $config['probeOrigin'] );
		self::assertSame( 'https://admin.example', $config['targetOrigin'] );
		self::assertContains( 'Content-Security-Policy: frame-ancestors https://admin.example', $result['headers'] );
		self::assertContains( 'X-Frame-Options', $result['removed_headers'] );
		self::assertNotContains( 'X-Frame-Options: SAMEORIGIN', $result['headers'] );
	}

	public function testStorefrontRouteIgnoresUnrelatedRequestsAndRequiresAuthorization(): void {
		foreach ( array( array(), array( 'kiriof_google_maps_browser_check' => array( '1' ) ), array( 'kiriof_google_maps_browser_check' => 'other' ) ) as $query ) {
			$result = $this->runScenario( array( 'dispatch' => true, 'query' => $query ) );
			self::assertSame( 0, $result['key_reads'] );
			self::assertSame( '', $result['html'] );
			self::assertFalse( $result['nocache'] );
		}
		$query = array( 'kiriof_google_maps_browser_check' => '1', '_wpnonce' => 'dedicated', 'channel' => 'random_channel_123456' );
		$result = $this->runScenario( array( 'dispatch' => true, 'query' => $query ) );
		self::assertSame( 200, $result['status'] );
		self::assertSame( 1, $result['key_reads'] );
		$result = $this->runScenario( array( 'dispatch' => true, 'query' => $query, 'authorized' => false ) );
		self::assertSame( 403, $result['status'] );
		self::assertSame( 0, $result['key_reads'] );
	}

	public function testInvalidConfiguredOriginsFailBeforeReadingKey(): void {
		foreach ( array( 'home_url', 'admin_url' ) as $setting ) {
			foreach ( array( 'javascript:alert(1)', 'https://user:password@store.example/', 'https://store.example;unsafe/' ) as $url ) {
				$result = $this->runScenario( array( $setting => $url ) );
				self::assertSame( 500, $result['status'] );
				self::assertSame( 0, $result['key_reads'] );
			}
		}
	}

	public function testAuthorizationAndRequestValidationPrecedeKeyRead(): void {
		$cases = array(
			array( array( 'method' => 'POST' ), 405 ),
			array( array( 'authorized' => false ), 403 ),
			array( array( 'query' => array( '_wpnonce' => 'submitted-secret', 'channel' => 'random_channel_123456' ) ), 403 ),
			array( array( 'query' => array( '_wpnonce' => array( 'dedicated' ), 'channel' => 'random_channel_123456' ) ), 403 ),
			array( array( 'query' => array( '_wpnonce' => 'dedicated' ) ), 400 ),
			array( array( 'query' => array( '_wpnonce' => 'dedicated', 'channel' => array( 'submitted-secret' ) ) ), 400 ),
		);
		foreach ( array( 'short', str_repeat( 'a', 81 ), "random_channel_123456\n", '<submitted-secret>' ) as $token ) {
			$cases[] = array( array( 'query' => array( '_wpnonce' => 'dedicated', 'channel' => $token ) ), 400 );
		}
		foreach ( $cases as list( $scenario, $status ) ) {
			$result = $this->runScenario( $scenario );
			self::assertSame( $status, $result['status'] );
			self::assertSame( 0, $result['key_reads'] );
			self::assertTrue( $result['nocache'] );
			self::assertSame( 'Unable to check Google Maps in this browser.', $result['error'] );
			self::assertSame( '', $result['html'] );
		}
	}

	public function testProtectedFrameUsesSavedKeyJsonAndFixedOriginWithSecurityHeaders(): void {
		$result = $this->runScenario( array( 'query' => array( 'nonce' => 'dedicated', 'channel' => 'random_channel_123456', 'key' => 'submitted-secret', 'targetOrigin' => 'https://evil.example' ) ) );
		self::assertSame( 'manage_woocommerce', $result['capability'] );
		self::assertSame( array( 'wp_ajax_kiriof_save_google_maps_settings', 'template_redirect' ), $result['hooks'] );
		self::assertSame( 1, $result['key_reads'] );
		self::assertSame( 200, $result['status'] );
		self::assertTrue( $result['nocache'] );
		foreach ( array( 'Content-Type: text/html; charset=UTF-8', 'Referrer-Policy: strict-origin-when-cross-origin', 'X-Robots-Tag: noindex, nofollow', 'Content-Security-Policy: frame-ancestors https://shop.example:8443' ) as $header ) {
			self::assertContains( $header, $result['headers'] );
		}
		preg_match( '/<script id="kiriof-google-maps-check-config" type="application\/json">(.*?)<\/script>/s', $result['html'], $matches );
		$config = json_decode( $matches[1], true, 512, JSON_THROW_ON_ERROR );
		self::assertSame( 'AIza-browser_saved123', $config['apiKey'] );
		self::assertSame( 'https://shop.example:8443', $config['targetOrigin'] );
		self::assertSame( 'https://shop.example:8443', $config['probeOrigin'] );
		self::assertSame( 'random_channel_123456', $config['channel'] );
		self::assertSame( 1, substr_count( $result['html'], 'AIza-browser_saved123' ) );
		self::assertStringNotContainsString( 'submitted-secret', $result['html'] );
		self::assertStringNotContainsString( 'evil.example', $result['html'] );
		self::assertStringNotContainsString( 'maps.googleapis.com', $result['html'] );
		self::assertStringContainsString( 'assets/admin/js/google-maps-browser-check.js?ver=test', $result['html'] );
		self::assertTrue( $result['summary']['configured'] );
		self::assertStringNotContainsString( $config['apiKey'], json_encode( $result['summary'] ) );
	}

	public function testEmptyKeyAndJsonEscapingRemainSafe(): void {
		$result = $this->runScenario( array( 'key' => '' ) );
		self::assertStringContainsString( '"apiKey":""', $result['html'] );
		self::assertFalse( $result['summary']['configured'] );
		$escaped = $this->runScenario( array( 'unsafe_config' => true ) );
		self::assertStringNotContainsString( '<img', $escaped['html'] );
		self::assertStringContainsString( '\\u003C', $escaped['html'] );
		self::assertStringContainsString( '\\u0026', $escaped['html'] );
		self::assertStringContainsString( '\\u0022', $escaped['html'] );
		self::assertStringContainsString( '\\u0027', $escaped['html'] );
	}
}
