<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Classic district/pin fields are not included in WooCommerce's native package hash. */
final class ClassicShippingRateCache {
	public static function invalidate(): void {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! isset( $wc->session ) || ! $wc->session ) {
			return;
		}
		$keys = array();
		if ( method_exists( $wc->session, 'get_session_data' ) ) {
			foreach ( array_keys( (array) $wc->session->get_session_data() ) as $key ) {
				if ( is_string( $key ) && 1 === preg_match( '/\Ashipping_for_package_[0-9]+\z/', $key ) ) {
					$keys[ $key ] = true;
				}
			}
		}
		if ( isset( $wc->cart ) && $wc->cart && method_exists( $wc->cart, 'get_shipping_packages' ) ) {
			foreach ( array_keys( (array) $wc->cart->get_shipping_packages() ) as $index ) {
				if ( ( is_int( $index ) && $index >= 0 ) || ( is_string( $index ) && ctype_digit( $index ) ) ) {
					$keys[ 'shipping_for_package_' . $index ] = true;
				}
			}
		}
		foreach ( array_keys( $keys ) as $key ) {
			$wc->session->set( $key, false );
		}
	}
}
