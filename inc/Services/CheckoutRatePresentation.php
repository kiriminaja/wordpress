<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Display capability separately from whether insurance was requested. */
final class CheckoutRatePresentation {
	public static function insuranceLabel( $option, bool $requested ): string {
		$row = (array) $option;
		$settings = (array) ( $row['setting'] ?? array() );
		$supported = false;
		foreach ( array( $row, $settings ) as $fields ) {
			foreach ( array( 'insurance_supported', 'allow_insurance', 'insurance_available' ) as $key ) {
				if ( ! array_key_exists( $key, $fields ) ) { continue; }
				if ( in_array( $fields[$key], array( false, 0, '0', 'no', 'false' ), true ) ) { return __( 'No Insurance Support', 'kiriminaja-official' ); }
				if ( in_array( $fields[$key], array( true, 1, '1', 'yes', 'true' ), true ) ) { $supported = true; }
			}
		}
		if ( in_array( $row['force_insurance'] ?? false, array( true, 1, '1', 'yes', 'true' ), true ) || ( isset( $row['insurance'] ) && is_numeric( $row['insurance'] ) && is_finite( (float) $row['insurance'] ) && (float) $row['insurance'] > 0 ) ) {
			return __( 'With Insurance', 'kiriminaja-official' );
		}
		if ( $supported ) { return self::supported( $requested ); }
		// An insured pricing request is not proof of optional capability when it is off.
		return $requested ? __( 'With Insurance', 'kiriminaja-official' ) : '';
	}

	private static function supported( bool $requested ): string {
		return $requested ? __( 'With Insurance', 'kiriminaja-official' ) : __( 'Additional Insurance Supported', 'kiriminaja-official' );
	}
}
