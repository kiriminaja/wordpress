<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Display capability separately from whether insurance was requested. */
final class CheckoutRatePresentation {
	/** Sort both KiriminAja methods together by displayed delivery cost, then name. */
	public static function sortPackageRates( array $rates ): array {
		$slots = array();
		$sorted = array();
		foreach ( $rates as $key => $rate ) {
			if ( ! $rate instanceof \WC_Shipping_Rate || ! in_array( $rate->get_method_id(), array( 'kiriminaja-official', 'kiriminaja-instant' ), true ) ) {
				continue;
			}
			$cost = $rate->get_cost();
			if ( ! is_numeric( $cost ) || ! is_finite( (float) $cost ) || (float) $cost < 0 ) {
				continue;
			}
			$slots[ $key ] = true;
			$sorted[] = array( 'key' => $key, 'rate' => $rate, 'cost' => (float) $cost, 'label' => (string) $rate->get_label(), 'position' => count( $sorted ) );
		}
		if ( count( $sorted ) < 2 ) {
			return $rates;
		}
		usort( $sorted, static function( $a, $b ) {
			$cost = $a['cost'] <=> $b['cost'];
			if ( 0 !== $cost ) {
				return $cost;
			}
			$name = strcasecmp( $a['label'], $b['label'] );
			return 0 !== $name ? $name : $a['position'] <=> $b['position'];
		} );
		$ordered = array();
		$index = 0;
		foreach ( $rates as $key => $rate ) {
			if ( isset( $slots[ $key ] ) ) {
				$entry = $sorted[ $index++ ];
				$ordered[ $entry['key'] ] = $entry['rate'];
			} else {
				// Other providers and invalid entries retain their position and identity.
				$ordered[ $key ] = $rate;
			}
		}
		return $ordered;
	}

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
		if ( in_array( $row['force_insurance'] ?? false, array( true, 1, '1', 'yes', 'true' ), true ) ) {
			return __( 'With Insurance', 'kiriminaja-official' );
		}
		if ( isset( $row['insurance'] ) && is_numeric( $row['insurance'] ) && is_finite( (float) $row['insurance'] ) && (float) $row['insurance'] > 0 ) { $supported = true; }
		if ( $supported ) { return self::supported( $requested ); }
		// An insured pricing request is not proof of optional capability when it is off.
		return $requested ? __( 'With Insurance', 'kiriminaja-official' ) : '';
	}

	private static function supported( bool $requested ): string {
		return $requested ? __( 'With Insurance', 'kiriminaja-official' ) : __( 'Additional Insurance Supported', 'kiriminaja-official' );
	}
}
