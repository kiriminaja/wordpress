<?php

use PHPUnit\Framework\TestCase;

final class MapProviderAssetsRuntimeTest extends TestCase {
	public function test_provider_selection_controls_actual_registrations_in_every_map_context(): void {
		$contexts = array(
			'blocks' => array( 'context' => 'blocks' ),
			'classic' => array( 'context' => 'classic' ),
			'account' => array( 'context' => 'account' ),
			'settings' => array( 'context' => 'admin', 'page' => 'kiriminaja-setting' ),
			'transactions' => array( 'context' => 'admin', 'page' => 'kiriminaja-transaction' ),
			'transaction detail' => array( 'context' => 'admin', 'page' => 'kiriminaja-transaction-detail' ),
			'onboarding' => array( 'context' => 'admin', 'page' => 'kiriminaja-onboarding' ),
			'warehouses' => array( 'context' => 'admin', 'screen' => 'woocommerce_page_wc-settings', 'tab' => 'kiriminaja_warehouses' ),
			'general' => array( 'context' => 'admin', 'screen' => 'woocommerce_page_wc-settings', 'tab' => 'general' ),
		);
		$expected = $actual = array();
		foreach ( array( 'leaflet' => '', 'google' => 'AIza-browser_public123' ) as $provider => $key ) {
			foreach ( $contexts as $name => $context ) {
				$context['key'] = $key;
				$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/map-provider-assets-runtime.php' ) . ' ' . escapeshellarg( json_encode( $context, JSON_THROW_ON_ERROR ) ) );
				self::assertNotNull( $output );
				$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
				$leaflet = $forbiddenDependencies = array();
				foreach ( array( 'scripts', 'styles' ) as $type ) {
					foreach ( $result[$type] as $handle => $asset ) {
						if ( is_string( $asset['src'] ) && str_contains( $asset['src'], '/leaflet/' ) ) { $leaflet[] = $asset['src']; }
						if ( '' !== $key ) {
							$forbidden = array_values( array_intersect( $asset['deps'], array( 'kiriof-leaflet', 'kiriof-leaflet-script' ) ) );
							if ( $forbidden ) { $forbiddenDependencies[$type . '/' . $handle] = $forbidden; }
						}
					}
				}
				$case = $provider . '/' . $name;
				$expected[$case] = array( 'leaflet assets' => '' === $key ? 2 : 0, 'forbidden dependencies' => array() );
				$actual[$case] = array( 'leaflet assets' => count( $leaflet ), 'forbidden dependencies' => $forbiddenDependencies );
				foreach ( array( 'map', 'detailMap' ) as $config ) {
					$expected[$case][$config] = array( 'provider' => $provider, 'apiKey' => $key );
					$actual[$case][$config] = array( 'provider' => $result[$config]['provider'], 'apiKey' => $result[$config]['apiKey'] );
				}
				if ( 'blocks' === $context['context'] ) {
					$expected[$case]['blocks dependencies'] = array( 'kiriof-buyer-state', 'wp-element', 'wp-data', 'wp-plugins', 'wc-blocks-checkout', 'wc-settings', 'kiriof-map-provider' );
					$actual[$case]['blocks dependencies'] = $result['scripts']['kiriof-buyer-blocks']['deps'];
				}
			}
		}
		self::assertSame( $expected, $actual );
	}
}
