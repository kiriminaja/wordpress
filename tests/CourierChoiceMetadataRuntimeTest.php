<?php

use PHPUnit\Framework\TestCase;

final class CourierChoiceMetadataRuntimeTest extends TestCase {
	private function render(): array {
		$output = array();
		$status = 0;
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/courier-choice-metadata-runtime.php' ) . ' 2>&1', $output, $status );
		$this->assertSame( 0, $status, implode( "\n", $output ) );
		return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
	}

	public function test_actual_template_uses_only_verified_rate_metadata_and_explicit_plugin_ids(): void {
		$result = $this->render();
		$this->assertSame( array( 'jne', 'grab_express', 'jnt_cargo', 'idx', '', '', '', '', 'jne', 'grab_express', 'grab_express', '', '', '', 'pos', 'jnt_cargo', '', '' ), $result['codes'] );
		preg_match_all( '/<option value="([^"]+)" data-courier="([^"]*)"/', $result['html'], $options );
		$this->assertSame( $result['codes'], $options[2] );
		$this->assertCount( 18, $options[1] );
		$this->assertSame( 18, substr_count( $result['html'], 'name="shipping_method[0]"' ) );
		$this->assertCount( 18, $result['hooks'] );
		$this->assertStringContainsString( 'JNE &lt;third-party&gt; &amp; service', $result['html'] );
		$this->assertStringContainsString( 'Rp 15,000', $result['html'] );
		$this->assertStringNotContainsString( 'onerror=', $result['html'] );
		// Options carry a safe identity, never an arbitrary URL or guessed label asset.
		$this->assertStringNotContainsString( '<img', $result['html'] );
		$this->assertStringNotContainsString( 'assets/buyer/img/couriers/', $result['html'] );
		foreach ( array( 'unknown', 'flat_rate', 'thirdparty', 'jneevil' ) as $code ) {
			$this->assertArrayNotHasKey( $code, $result['urls'] );
		}
	}

	public function test_logo_map_contains_twenty_one_shared_unchanged_png_assets_without_rebranding(): void {
		$urls = $this->render()['urls'];
		$this->assertCount( 21, $urls );
		$hashes = json_decode( file_get_contents( __DIR__ . '/fixtures/courier-artwork-sha256.json' ), true, 512, JSON_THROW_ON_ERROR );
		$total = 0;
		foreach ( $urls as $code => $url ) {
			$this->assertStringStartsWith( 'https://shop.example/wp-content/plugins/kiriminaja/assets/buyer/img/couriers/', $url );
			$file = basename( $url );
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+\.png$/', $file );
			$asset = dirname( __DIR__ ) . '/assets/buyer/img/couriers/' . $file;
			$this->assertFileExists( $asset );
			$bytes = file_get_contents( $asset );
			$this->assertSame( "\x89PNG\r\n\x1a\n", substr( $bytes, 0, 8 ) );
			$this->assertSame( $hashes[ $file ], hash( 'sha256', $bytes ), 'Original courier artwork must remain byte-identical.' );
			$total += strlen( $bytes );
		}
		$this->assertLessThan( 60000, $total );
		$this->assertDirectoryDoesNotExist( dirname( __DIR__ ) . '/src/assets/images/kiriminaja-kurir' );
		$config = file_get_contents( dirname( __DIR__ ) . '/templates/front/partials/form-billing-address-config.php' );
		$this->assertStringContainsString( "'courierLogos'", $config );
		$this->assertStringContainsString( 'CourierLogoAssets::urls()', $config );
	}
}
