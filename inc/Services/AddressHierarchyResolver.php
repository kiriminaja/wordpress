<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Only validated unified-address rows may establish village/parent identities. */
final class AddressHierarchyResolver {
    private const CACHE_KEY = 'kiriof_address_identity_v1';
    private const TTL = 86400;
    private const MAX_ENTRIES = 500;
    private static array $identities = array();

    /** Called only after the repository validates the complete official response. */
    public static function remember( array $rows ): void {
        $cache = self::cache();
        foreach ( $rows as $row ) {
            $cache[ self::key( $row['subdistrict_id'] ) ] = array(
                'district_id' => $row['district_id'],
                'city_id' => $row['city_id'],
                'province_id' => $row['province_id'],
                'postcode' => $row['zip_code'],
                'expires' => time() + self::TTL,
            );
        }
        self::$identities = array_slice( $cache, -self::MAX_ENTRIES, null, true );
        if ( function_exists( 'set_transient' ) ) {
            set_transient( self::CACHE_KEY, self::$identities, self::TTL );
        }
    }

    /** Never infer a parent from an ID, label, or an undocumented endpoint. */
    public static function resolve( $village, $postcode, callable $search ): ?array {
        if ( ( ! is_int( $village ) && ! is_string( $village ) ) || false === filter_var( $village, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
            return null;
        }
        if ( ! is_string( $postcode ) || ( '' !== $postcode && 1 !== preg_match( '/\A[0-9]{5}\z/', $postcode ) ) ) {
            return null;
        }
        $key = self::key( (int) $village );
        $cache = self::cache();
        if ( isset( $cache[ $key ] ) ) {
            return '' === $postcode || $postcode === $cache[ $key ]['postcode'] ? $cache[ $key ] : null;
        }
        // Historical addresses without a verified mapping or postcode fail closed.
        if ( '' === $postcode ) {
            return null;
        }
        $response = $search( $postcode );
        if ( empty( $response['status'] ) ) {
            return null;
        }
        $cache = self::cache();
        return isset( $cache[ $key ] ) && $postcode === $cache[ $key ]['postcode'] ? $cache[ $key ] : null;
    }

    private static function key( $village ): string {
        return hash( 'sha256', 'unified-village:' . (int) $village );
    }

    private static function cache(): array {
        $stored = function_exists( 'get_transient' ) ? get_transient( self::CACHE_KEY ) : false;
        $cache = is_array( $stored ) ? $stored : self::$identities;
        return array_filter( $cache, static function ( $row ) {
            return is_array( $row ) && ( $row['expires'] ?? 0 ) > time()
                && is_int( $row['district_id'] ?? null ) && $row['district_id'] > 0
                && is_int( $row['city_id'] ?? null ) && $row['city_id'] > 0
                && is_int( $row['province_id'] ?? null ) && $row['province_id'] > 0
                && is_string( $row['postcode'] ?? null ) && 1 === preg_match( '/\A[0-9]{5}\z/', $row['postcode'] );
        } );
    }
}
