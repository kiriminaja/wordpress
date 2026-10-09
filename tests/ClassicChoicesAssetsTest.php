<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Distribution, enqueue, label and read-only transport guards; interaction belongs to the browser suite. */
final class ClassicChoicesAssetsTest extends TestCase {
	#[Test]
	public function classic_enqueue_executes_with_local_assets_and_preserves_the_legacy_dependency_chain(): void {
		$result = $this->runtime( 'fixtures/classic-choices-assets-runtime.php', array( 'page' => 'classic' ) );
		$this->assertArrayNotHasKey( 'kiriof-choices', $result['scripts'] );
		$this->assertArrayNotHasKey( 'kiriof-choices', $result['styles'] );
		$scripts = $result['scripts'];
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-buyer-state', 'wc-country-select' ), $scripts['kiriof-classic-choices']['deps'] );
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-classic-choices' ), $scripts['kiriof-classic-shipping-options']['deps'] );
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-classic-choices', 'kiriof-classic-shipping-options' ), $scripts['kiriof-form-billing-address']['deps'] );
		$previous = 'kiriof-script';
		foreach ( array( 'state', 'blocks-compatibility', 'classic-district', 'shipping-payment' ) as $module ) {
			$handle = 'kiriof-checkout-' . $module;
			$this->assertSame( 'state' === $module ? array( $previous, 'kiriof-shipping-selection' ) : array( $previous ), $scripts[ $handle ]['deps'] );
			$previous = $handle;
		}
		$this->assertSame( array( 'in_footer' => true ), $scripts['kiriof-classic-choices']['args'] );
		$this->assertSame( array( 'kiriof-style' ), $result['styles']['kiriof-classic-choices']['deps'] );
		$this->assertContains( 'kiriof-classic-shipping-layout', $result['enqueued_styles'] );
		$this->assertSame( array( 'kiriof-classic-choices' ), $result['styles']['kiriof-classic-shipping-layout']['deps'] );
		$this->assertStringEndsWith( 'assets/buyer/dist/kiriminaja-buyer-classic.js', $scripts['kiriof-classic-choices']['src'] );
		$this->assertStringEndsWith( 'assets/buyer/dist/kiriminaja-buyer-classic.css', $result['styles']['kiriof-classic-choices']['src'] );
		$this->assertSame( array(), $scripts['kiriof-buyer-state']['deps'] );
		$this->assertSame( false, $scripts['kiriof-shipping-selection']['src'] );
		$this->assertSame( array( 'kiriof-buyer-state' ), $scripts['kiriof-shipping-selection']['deps'] );
		foreach ( array( 'scripts', 'styles' ) as $type ) {
			foreach ( $result[ $type ] as $asset ) {
				if ( false === $asset['src'] ) { continue; }
				$this->assertStringStartsWith( 'https://shop.example/', $asset['src'], 'Frontend assets must not resolve to a CDN.' );
			}
		}
	}

	#[Test]
	#[DataProvider( 'other_pages' )]
	public function choices_is_not_registered_outside_editable_classic_checkout( string $page ): void {
		$result = $this->runtime( 'fixtures/classic-choices-assets-runtime.php', array( 'page' => $page ) );
		foreach ( array( 'scripts', 'styles' ) as $type ) {
			$this->assertArrayNotHasKey( 'kiriof-choices', $result[ $type ] );
			$this->assertArrayNotHasKey( 'kiriof-classic-choices', $result[ $type ] );
		}
	}

	public static function other_pages(): array {
		return array( 'Blocks' => array( 'blocks' ), 'cart' => array( 'cart' ), 'account' => array( 'account' ), 'receipt' => array( 'received' ), 'ordinary page' => array( 'ordinary' ) );
	}

	#[Test]
	public function rendered_config_uses_subdistrict_and_keeps_nonce_and_field_contracts(): void {
		$result = $this->runtime( 'fixtures/classic-choices-assets-runtime.php', array( 'page' => 'classic' ) );
		$config = $result['config'];
		$this->assertSame( 'Subdistrict', $config['i18n']['district'] );
		$this->assertSame( 'Select Subdistrict', $config['i18n']['selectDistrict'] );
		$this->assertSame( 'kiriof_destination_area', $config['fieldKey'] );
		$this->assertSame( array( 'id' => '202', 'name' => 'Shipping village' ), $config['shippingDistrict'] );
		$this->assertSame( 'nonce:choices-assets-test', $config['nonce'] );
		$this->assertSame( 'nonce:choices-assets-test', $result['localized']['kiriofAjax']['nonce'] );
		$this->assertSame( 'https://shop.example/wp-admin/admin-ajax.php', $config['ajaxUrl'] );
		$fields = $this->runtime( 'fixtures/checkout-country-runtime.php', array( 'action' => 'fields', 'post' => array( 'billing_country' => 'ID', 'shipping_country' => 'ID' ) ) );
		foreach ( array( 'billing' => 'kiriof_destination_area', 'shipping' => 'kiriof_shipping_destination_area' ) as $group => $key ) {
			$expected_keys = array_keys( $fields['original_fields'][ $group ] );
			$expected_keys[] = $key;
			$this->assertSame( $expected_keys, array_keys( $fields['fields'][ $group ] ), 'Relabeling must not rename native address fields or introduce a new district schema.' );
			$this->assertSame( $fields['original_fields'][ $group ][ $group . '_country' ], $fields['fields'][ $group ][ $group . '_country' ] );
			$this->assertArrayHasKey( $key, $fields['fields'][ $group ] );
			$this->assertSame( 'select', $fields['fields'][ $group ][ $key ]['type'] );
			$this->assertSame( 'Subdistrict', $fields['fields'][ $group ][ $key ]['label'] );
			$this->assertTrue( $fields['fields'][ $group ][ $key ]['required'] );
		}
	}

	private function source( string $path ): string {
		$content = file_get_contents( PLUGIN_DIR . '/' . $path );
		$this->assertIsString( $content, $path );
		return $content;
	}

	private function runtime( string $fixture, array $input ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/' . $fixture ) . ' ' . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
		exec( $command, $output, $exit_code );
		$text = implode( "\n", $output );
		$this->assertSame( 0, $exit_code, $text );
		return json_decode( $text, true, 512, JSON_THROW_ON_ERROR );
	}
}
