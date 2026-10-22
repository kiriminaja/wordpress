<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Instant-only contact syntax. Never rewrite native order/customer snapshots. */
final class InstantPhoneNumber {
	/** Keep local 08 or international 62 digits; reject rather than salvage unknown input. */
	public static function normalize( $value ): string {
		if ( ! is_string( $value ) || ! preg_match( '/\A[0-9+ ()-]+\z/', $value ) ) {
			return '';
		}
		$value = trim( $value, ' ' );
		if ( 1 !== preg_match( '/\A(?:(?:\+?62|08)(?:[0-9]|[ -]|\([0-9]+\))*|\(08[0-9]+\)(?:[0-9]|[ -]|\([0-9]+\))*)\z/', $value ) ) {
			return '';
		}
		// A plus belongs only to the Indonesian international prefix, never to 08.
		if ( str_starts_with( $value, '+' ) ) {
			if ( ! str_starts_with( $value, '+62' ) ) {
				return '';
			}
			$value = substr( $value, 1 );
		}
		if ( false !== strpos( $value, '+' ) ) {
			return '';
		}
		$digits = str_replace( array( ' ', '-', '(', ')' ), '', $value );
		return 1 === preg_match( '/\A(?:08|62)[0-9]+\z/', $digits ) ? $digits : '';
	}
}
