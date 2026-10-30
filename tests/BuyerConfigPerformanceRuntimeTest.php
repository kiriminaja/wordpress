<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BuyerConfigPerformanceRuntimeTest extends TestCase {
	/** @dataProvider checkout_modes */
	public function test_registration_reuses_only_the_current_localization_map( string $mode, string $buyer_name ): void {
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/buyer-config-performance.php' ) . ' ' . escapeshellarg( $mode ) );
		$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
		$cases = $result['cases'];

		foreach ( array( 'register_only', 'repeated' ) as $name ) {
			self::assertSame( 0, $cases[ $name ]['map_calls'], $mode . '/' . $name );
			self::assertSame( 0, $cases[ $name ]['buyer_calls'], $mode . '/' . $name );
			self::assertSame( array(), $cases[ $name ]['localized'] );
		}
		foreach ( array( 'initial' => 'origin-a', 'changed_context' => 'origin-d', 'non_string_data' => 'origin-e' ) as $name => $origin ) {
			self::assertSame( 1, $cases[ $name ]['map_calls'], $mode . '/' . $name );
			self::assertSame( 1, $cases[ $name ]['buyer_calls'], $mode . '/' . $name );
			$localized = $cases[ $name ]['localized'];
			self::assertSame( $localized[ $buyer_name ]['map'], $localized['kiriofMapCheckoutConfig'] );
			self::assertSame( $origin, $localized['kiriofMapCheckoutConfig']['coverage']['origin'] );
			self::assertSame( 'https://shop.example/wp-admin/admin-ajax.php', $localized[ $buyer_name ]['ajaxUrl'] );
			if ( 'classic' === $mode ) {
				self::assertTrue( $localized[ $buyer_name ]['enabled'] );
				self::assertFalse( $localized[ $buyer_name ]['ownsDistrict'] );
				self::assertTrue( $localized[ $buyer_name ]['needsShipping'] );
				self::assertArrayHasKey( 'pinSaveFailed', $localized[ $buyer_name ]['i18n'] );
				self::assertArrayHasKey( 'kiriof_pin_invalid_scope', $localized[ $buyer_name ]['pinErrors'] );
			} else {
				self::assertSame( 'Shipping discount "%s" applied to your cart.', $localized['kiriofBlockCheckoutStrings']['couponApplied'] );
			}
		}

		$buyer_missing = $cases['buyer_only_missing'];
		self::assertSame( 1, $buyer_missing['map_calls'] );
		self::assertSame( 1, $buyer_missing['buyer_calls'] );
		self::assertArrayNotHasKey( 'kiriofMapCheckoutConfig', $buyer_missing['localized'] );
		self::assertSame( 'origin-b', $buyer_missing['localized'][ $buyer_name ]['map']['coverage']['origin'] );

		$map_missing = $cases['map_only_missing'];
		self::assertSame( 1, $map_missing['map_calls'] );
		self::assertSame( 0, $map_missing['buyer_calls'] );
		self::assertArrayNotHasKey( $buyer_name, $map_missing['localized'] );
		self::assertSame( 'origin-c', $map_missing['localized']['kiriofMapCheckoutConfig']['coverage']['origin'] );
		if ( 'blocks' === $mode ) {
			self::assertContains( 'kiriof-map-checkout-editor', $result['translations'] );
			self::assertContains( 'kiriof-checkout-district-editor', $result['translations'] );
		}
	}

	public static function checkout_modes(): array {
		return array(
			'blocks' => array( 'blocks', 'kiriofBuyerCheckoutConfig' ),
			'classic' => array( 'classic', 'kiriofClassicCheckoutConfig' ),
		);
	}
}
