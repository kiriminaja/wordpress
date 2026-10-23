<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure identity checks against authoritative postcode-search rows.
 *
 * Whitespace and ASCII case are insignificant within hierarchy components;
 * punctuation, words and component boundaries remain significant. No fuzzy
 * matching, abbreviations, external lookup, persistence or object coercion.
 */
final class OriginAddressMatcher {
    private const MAX_TEXT = 1000;
    private const MAX_ROWS = 500;

    /**
     * Resolve a verified ID, or repair a stale ID using the complete hierarchy.
     * Any malformed row or duplicate ID (including exact duplicates) rejects the
     * entire response: a partial response must not manufacture uniqueness.
     * Public object properties and arrays are supported, never magic accessors.
     *
     * @param int|string $saved_id Historical ID; invalid scalar IDs may be repaired.
     * @param string $saved_label Historical full label, partial name, or empty string.
     * @param int|string $saved_zip Five digits, or empty string to derive from label.
     * @param array<int,array|object> $rows Validated authoritative address rows.
     * @return array{sub_district_id:int,sub_district_name:string,zip_code:string}|null
     */
    public static function match( $saved_id, $saved_label, $saved_zip, array $rows ): ?array {
        if ( ( ! is_int( $saved_id ) && ! is_string( $saved_id ) )
            || ( is_string( $saved_id ) && strlen( $saved_id ) > self::MAX_TEXT )
            || count( $rows ) > self::MAX_ROWS ) {
            return null;
        }
        $postcode = self::postcode( $saved_label, $saved_zip );
        if ( null === $postcode ) {
            return null;
        }
        $label = self::hierarchy( $saved_label );
        // Comma-delimited labels purport to describe a hierarchy, not a short name.
        if ( false !== strpos( $saved_label, ',' ) && null === $label ) {
            return null;
        }

        $identities = array();
        foreach ( $rows as $row ) {
            if ( is_object( $row ) ) {
                $row = get_object_vars( $row );
            }
            if ( ! is_array( $row ) ) {
                return null;
            }
            $id = self::id( $row['subdistrict_id'] ?? $row['id'] ?? null );
            if ( null === $id || isset( $identities[ $id ] ) ) {
                return null;
            }
            foreach ( array( 'id', 'subdistrict_id' ) as $key ) {
                if ( array_key_exists( $key, $row ) && self::id( $row[ $key ] ) !== $id ) {
                    return null;
                }
            }
            foreach ( array( 'district_id', 'city_id', 'province_id' ) as $key ) {
                if ( null === self::id( $row[ $key ] ?? null ) ) {
                    return null;
                }
            }
            $text = $row['text'] ?? null;
            $hierarchy = self::hierarchy( $text );
            $zip = self::zip( $row['zip_code'] ?? null );
            if ( null === $hierarchy || null === $zip || $hierarchy[4] !== $zip ) {
                return null;
            }
            $identities[ $id ] = array(
                'hierarchy' => $hierarchy,
                'canonical' => array(
                    'sub_district_id' => $id,
                    'sub_district_name' => $text,
                    'zip_code' => $zip,
                ),
            );
        }

        $id = self::id( $saved_id );
        if ( null !== $id && isset( $identities[ $id ] ) ) {
            $identity = $identities[ $id ];
            if ( $identity['canonical']['zip_code'] !== $postcode
                || ( null !== $label && $label !== $identity['hierarchy'] ) ) {
                return null;
            }
            return $identity['canonical'];
        }
        if ( null === $label ) {
            return null;
        }
        $matches = array();
        foreach ( $identities as $identity ) {
            if ( $label === $identity['hierarchy'] && $identity['canonical']['zip_code'] === $postcode ) {
                $matches[] = $identity['canonical'];
            }
        }
        return count( $matches ) === 1 ? $matches[0] : null;
    }

    /**
     * Select a strict five-digit postcode; an explicit invalid or conflicting
     * value never falls back. A comma-delimited label must end in a postcode.
     *
     * @param string $saved_label Label of at most 1000 bytes.
     * @param int|string $saved_zip Five digits or the empty string (not null).
     * @return string|null
     */
    public static function postcode( $saved_label, $saved_zip ): ?string {
        if ( ! is_string( $saved_label ) || strlen( $saved_label ) > self::MAX_TEXT
            || 1 !== preg_match( '//u', $saved_label ) ) {
            return null;
        }
        $label_zip = null;
        if ( false !== strpos( $saved_label, ',' ) ) {
            $parts = explode( ',', $saved_label );
            $label_zip = self::zip( trim( end( $parts ) ) );
            if ( null === $label_zip ) {
                return null;
            }
        }
        if ( '' === $saved_zip ) {
            return $label_zip;
        }
        $zip = self::zip( $saved_zip );
        return null !== $zip && ( null === $label_zip || $zip === $label_zip ) ? $zip : null;
    }

    /** @param mixed $value @return int|null Strict positive integer without overflow. */
    private static function id( $value ): ?int {
        if ( ( ! is_int( $value ) && ! is_string( $value ) )
            || ( is_string( $value ) && ( strlen( $value ) > 20 || 1 !== preg_match( '/\A[1-9][0-9]*\z/', $value ) ) ) ) {
            return null;
        }
        $id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
        return false === $id ? null : $id;
    }

    /** @param mixed $value @return string|null Exact five ASCII digits. */
    private static function zip( $value ): ?string {
        if ( ! is_int( $value ) && ! is_string( $value ) ) {
            return null;
        }
        $value = (string) $value;
        return 1 === preg_match( '/\A[0-9]{5}\z/', $value ) ? $value : null;
    }

    /** @param mixed $text @return array<int,string>|null Four names and a postcode. */
    private static function hierarchy( $text ): ?array {
        if ( ! is_string( $text ) || strlen( $text ) > self::MAX_TEXT ) {
            return null;
        }
        $parts = explode( ',', $text );
        if ( count( $parts ) !== 5 ) {
            return null;
        }
        $zip = self::zip( trim( $parts[4] ) );
        if ( null === $zip ) {
            return null;
        }
        for ( $i = 0; $i < 4; ++$i ) {
            $part = preg_replace( '/\s+/u', '', $parts[ $i ] );
            if ( null === $part || 1 !== preg_match( '/[\p{L}\p{N}]/u', $part ) ) {
                return null;
            }
            $parts[ $i ] = strtolower( $part );
        }
        $parts[4] = $zip;
        return $parts;
    }
}
