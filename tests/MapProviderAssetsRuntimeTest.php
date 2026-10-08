<?php

use PHPUnit\Framework\TestCase;

final class MapProviderAssetsRuntimeTest extends TestCase {
	public function test_provider_selection_controls_actual_registrations_in_every_map_context(): void {
		$contexts = array(
			array( 'context' => 'blocks' ), array( 'context' => 'classic' ), array( 'context' => 'account' ),
			array( 'context' => 'admin', 'page' => 'kiriminaja-setting' ),
			array( 'context' => 'admin', 'page' => 'kiriminaja-transaction' ),
			array( 'context' => 'admin', 'page' => 'kiriminaja-transaction-detail' ),
			array( 'context' => 'admin', 'page' => 'kiriminaja-onboarding' ),
			array( 'context' => 'admin', 'screen' => 'woocommerce_page_wc-settings', 'tab' => 'kiriminaja_warehouses' ),
			array( 'context' => 'admin', 'screen' => 'woocommerce_page_wc-settings', 'tab' => 'general' ),
		);
		foreach ( array( '', 'AIza-browser_public123' ) as $key ) {
			foreach ( $contexts as $context ) {
				$context['key'] = $key;
				$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/map-provider-assets-runtime.php' ) . ' ' . escapeshellarg( json_encode( $context, JSON_THROW_ON_ERROR ) ) );
				self::assertNotNull( $output );
				$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
				$leaflet = array();
				foreach ( array( 'scripts', 'styles' ) as $type ) {
					foreach ( $result[$type] as $asset ) {
						if ( is_string( $asset['src'] ) && str_contains( $asset['src'], '/leaflet/' ) ) { $leaflet[] = $asset['src']; }
						if ( '' !== $key ) { self::assertNotContains( 'kiriof-leaflet', $asset['deps'] ); self::assertNotContains( 'kiriof-leaflet-script', $asset['deps'] ); }
					}
				}
				self::assertCount( '' === $key ? 2 : 0, $leaflet, json_encode( $context ) );
				foreach ( array( 'map', 'detailMap' ) as $config ) {
					self::assertSame( '' === $key ? 'leaflet' : 'google', $result[$config]['provider'] );
					self::assertSame( $key, $result[$config]['apiKey'] );
				}
				if ( 'blocks' === $context['context'] ) {
					self::assertSame( array( 'kiriof-buyer-state', 'wp-element', 'wp-data', 'wp-plugins', 'wc-blocks-checkout', 'wc-settings', 'kiriof-map-provider' ), $result['scripts']['kiriof-buyer-blocks']['deps'] );
				}
			}
		}
	}
}
