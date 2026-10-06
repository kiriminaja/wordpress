<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Distribution, enqueue, label and read-only transport guards; interaction belongs to the browser suite. */
final class ClassicChoicesAssetsTest extends TestCase {
	#[Test]
	public function manual_vendor_assets_are_pinned_and_include_the_complete_mit_license(): void {
		$readme = $this->source( 'assets/lib/choices/README.md' );
		$this->assertStringContainsString( '# Choices.js 11.2.4', $readme );
		$hashes = array(
			'choices.min.js' => 'cb6805adda4eaf3251fe5adbde5f2f62c80ea1c29b7999b158546a77d1e191d6',
			'choices.min.css' => 'a5360f4f5da9db5bcc5bf5c53673e4b91622aadbe3ac5727a605ff9a6b14da0b',
			'LICENSE' => '38feb3fc5fcbca23433ffaf82140ced496e6a1503636392ac7d640faac18ec1b',
		);
		foreach ( $hashes as $file => $hash ) {
			$this->assertSame( $hash, hash_file( 'sha256', PLUGIN_DIR . '/assets/lib/choices/' . $file ), $file );
			$this->assertStringContainsString( $hash, $readme, 'Document the exact shipped file, not only its version.' );
		}
		$this->assertStringContainsString( 'choices.js v11.2.4', $this->source( 'assets/lib/choices/choices.min.js' ) );
		$this->assertStringContainsString( 'The MIT License (MIT)', $this->source( 'assets/lib/choices/LICENSE' ) );
		$this->assertStringContainsString( 'https://cdn.jsdelivr.net/npm/choices.js@11.2.4/public/assets/scripts/choices.min.js', $readme );
		$this->assertStringContainsString( 'https://cdn.jsdelivr.net/npm/choices.js@11.2.4/public/assets/styles/choices.min.css', $readme );
		$this->assertStringContainsString( 'https://raw.githubusercontent.com/Choices-js/Choices/v11.2.4/LICENSE', $readme );
		$package = json_decode( $this->source( 'package.json' ), true, 512, JSON_THROW_ON_ERROR );
		foreach ( array( 'dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies' ) as $section ) {
			foreach ( array_keys( $package[ $section ] ?? array() ) as $dependency ) {
				$this->assertDoesNotMatchRegularExpression( '/choices/i', $dependency, 'Choices is manually vendored, not a Svelte/npm dependency.' );
			}
		}
	}

	#[Test]
	public function classic_enqueue_executes_with_local_assets_and_preserves_the_legacy_dependency_chain(): void {
		$result = $this->runtime( 'fixtures/classic-choices-assets-runtime.php', array( 'page' => 'classic' ) );
		foreach ( array( 'scripts' => 'choices.min.js', 'styles' => 'choices.min.css' ) as $type => $file ) {
			$asset = $result[ $type ]['kiriof-choices'];
			$this->assertSame( 'https://shop.example/wp-content/plugins/kiriminaja/assets/lib/choices/' . $file, $asset['src'] );
			$this->assertSame( '11.2.4', $asset['version'] );
			$this->assertSame( array(), $asset['deps'] );
		}
		$scripts = $result['scripts'];
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-choices', 'wc-country-select' ), $scripts['kiriof-classic-choices']['deps'] );
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-classic-choices' ), $scripts['kiriof-classic-shipping-options']['deps'] );
		$this->assertSame( array( 'kiriof-checkout-shipping-payment', 'kiriof-classic-choices', 'kiriof-classic-shipping-options' ), $scripts['kiriof-form-billing-address']['deps'] );
		$previous = 'kiriof-script';
		foreach ( array( 'state', 'blocks-compatibility', 'classic-district', 'shipping-payment' ) as $module ) {
			$handle = 'kiriof-checkout-' . $module;
			$this->assertSame( array( $previous ), $scripts[ $handle ]['deps'] );
			$previous = $handle;
		}
		$this->assertSame( array( 'in_footer' => true ), $scripts['kiriof-classic-choices']['args'] );
		$this->assertSame( array( 'kiriof-choices', 'kiriof-style' ), $result['styles']['kiriof-classic-choices']['deps'] );
		$this->assertContains( 'kiriof-classic-choices', $result['enqueued_styles'] );
		foreach ( array( 'scripts', 'styles' ) as $type ) {
			foreach ( $result[ $type ] as $asset ) {
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
		$this->assertMatchesRegularExpression( '/^msgid "Subdistrict"\Rmsgstr "Desa \/ Kelurahan"$/m', $this->source( 'lang/kiriminaja-official-id_ID.po' ) );
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

	#[Test]
	public function legacy_control_source_only_calls_read_only_same_origin_lookup_without_secrets(): void {
		$source = $this->source( 'assets/wp/js/checkout/choices-controls.js' );
		$this->assertStringContainsString( "body.set('action', 'kiriminaja_subdistrict_search')", $source );
		$this->assertStringContainsString( "body.set('nonce', root.nonce || config.nonce || '')", $source );
		$this->assertStringContainsString( "window.fetch(root.ajaxurl || config.ajaxUrl || ''", $source );
		$this->assertStringContainsString( "credentials: 'same-origin'", $source );
		$this->assertStringContainsString( 'url.origin === window.location.origin', $source );
		$this->assertStringNotContainsString( 'createElementNS(', $source );
		$this->assertStringContainsString( "img.addEventListener('error'", $source );
		$this->assertDoesNotMatchRegularExpression( '/(?:create[_-]?order|booking|credit|api[_-]?key|api[_-]?token|Authorization|Bearer|svelte)/i', $source );
		preg_match_all( '/body\.set\(\s*[\'"]([^\'"]+)/', $source, $matches );
		$this->assertSame( array( 'action', 'nonce', 'term', 'data[term]', 'data[search]' ), $matches[1], 'Search must never send credentials or mutate booking/order payloads.' );
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
