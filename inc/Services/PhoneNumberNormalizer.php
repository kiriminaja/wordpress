<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Normalize valid Indonesian phone numbers for provider payloads. */
final class PhoneNumberNormalizer {
    /** Keep local 08 or international 62 digits; reject rather than salvage unknown input. */
    public static function normalize( $value ): string {
        if ( ! is_string( $value ) || ! preg_match( '/\A[0-9+ ()-]+\z/', $value ) ) {
            return '';
        }
        $value = trim( $value, ' ' );
        if ( 1 !== preg_match( '/\A(?:(?:\+?62|0[1-9])(?:[0-9]|[ -]|\([0-9]+\))*|\(0[1-9][0-9]+\)(?:[0-9]|[ -]|\([0-9]+\))*)\z/', $value ) ) {
            return '';
        }
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
        if ( str_starts_with( $digits, '0' ) && ! str_starts_with( $digits, '08' ) ) {
            $digits = '62' . substr( $digits, 1 );
        }
        return 1 === preg_match( '/\A(?:08|62)[0-9]+\z/', $digits ) ? $digits : '';
    }
}
