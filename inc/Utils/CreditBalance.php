<?php
namespace KiriminAjaOfficial\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Strict shared balance normalization. Unknown is never a zero balance. */
final class CreditBalance {
	public static function parse( $payload, int $depth = 0 ): ?float {
		if ( $depth > 8 ) {
			return null;
		}
		if ( is_object( $payload ) ) {
			$payload = get_object_vars( $payload );
		}
		if ( is_array( $payload ) ) {
			if ( array_key_exists( 'status', $payload ) && true !== $payload['status'] ) {
				return null;
			}
			if ( array_key_exists( 'balance', $payload ) ) {
				return self::number( $payload['balance'] );
			}
			foreach ( array( 'results', 'data', 'payload', 'result' ) as $wrapper ) {
				if ( array_key_exists( $wrapper, $payload ) ) {
					return self::parse( $payload[ $wrapper ], $depth + 1 );
				}
			}
			return null;
		}
		return self::number( $payload );
	}

	private static function number( $value ): ?float {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/\A[0-9]+(?:\.[0-9]+)?\z/', $value ) ) ) {
			return null;
		}
		$value = (float) $value;
		// JSON consumers must be able to represent the amount safely, including balances above 32 bits.
		return is_finite( $value ) && $value >= 0 && $value <= 9007199254740991 ? $value : null;
	}
}
