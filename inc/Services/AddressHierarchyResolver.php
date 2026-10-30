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
    public const REFRESH_HOOK = 'kiriof_address_hierarchy_refresh';
    private const REFRESH_QUEUE = 'kiriof_address_hierarchy_refresh_queue_v1';
    private const REFRESH_INTERVAL = 300;
    private const MAX_REFRESHES = 20;
    private static array $identities = array();

    /** Re-admit only complete, internally consistent official option rows. */
    public static function rememberCachedRows( $rows, string $postcode ): bool {
        if ( 1 !== preg_match( '/\A[0-9]{5}\z/', $postcode ) || ! is_array( $rows ) || ! array_is_list( $rows ) || empty( $rows ) || count( $rows ) > self::MAX_ENTRIES ) {
            return false;
        }
        $validated = array();
        $hierarchies = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) && ! is_object( $row ) ) {
                return false;
            }
            $row = (array) $row;
            foreach ( array( 'id', 'subdistrict_id', 'district_id', 'city_id', 'province_id' ) as $field ) {
                if ( ! is_int( $row[ $field ] ?? null ) || $row[ $field ] < 1 ) {
                    return false;
                }
            }
            if ( $row['id'] !== $row['subdistrict_id'] || $postcode !== ( $row['zip_code'] ?? null ) ) {
                return false;
            }
            $parts = array();
            foreach ( array( 'subdistrict_name', 'district_name', 'city_name', 'province_name' ) as $field ) {
                if ( ! is_string( $row[ $field ] ?? null ) || '' === $row[ $field ] || trim( $row[ $field ] ) !== $row[ $field ] || false !== strpos( $row[ $field ], ',' ) ) {
                    return false;
                }
                $parts[] = $row[ $field ];
            }
            $parts[] = $postcode;
            if ( ! is_string( $row['text'] ?? null ) || strlen( $row['text'] ) > 1000 || implode( ', ', $parts ) !== $row['text'] ) {
                return false;
            }
            foreach ( array( 'kecamatan_id' => 'district_id', 'kabupaten_id' => 'city_id', 'provinsi_id' => 'province_id', 'kelurahan_name' => 'subdistrict_name' ) as $alias => $field ) {
                if ( array_key_exists( $alias, $row ) && $row[ $alias ] !== $row[ $field ] ) {
                    return false;
                }
            }
            foreach ( array(
                'province' => array( $row['province_id'], $row['province_name'] ),
                'city' => array( $row['city_id'], $row['province_id'], $row['city_name'] ),
                'district' => array( $row['district_id'], $row['city_id'], $row['province_id'], $row['district_name'] ),
                'village' => array( $row['subdistrict_id'], $row['district_id'], $row['city_id'], $row['province_id'], $row['subdistrict_name'] ),
            ) as $level => $hierarchy ) {
                $key = $level . ':' . $hierarchy[0];
                if ( isset( $hierarchies[ $key ] ) && $hierarchies[ $key ] !== $hierarchy ) {
                    return false;
                }
                $hierarchies[ $key ] = $hierarchy;
            }
            $validated[] = $row;
        }
        self::remember( $validated );
        return true;
    }

    /** Demand-driven refreshes carry only a postcode, never checkout state. */
    public static function scheduleRefresh( string $postcode ): void {
        if ( 1 !== preg_match( '/\A[0-9]{5}\z/', $postcode ) || ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
            return;
        }
        $stored = get_transient( self::REFRESH_QUEUE );
        $queue = is_array( $stored ) ? $stored : array();
        foreach ( $queue as $queued_postcode => $expires ) {
            if ( ! is_int( $expires ) || ( $expires <= time() && ! wp_next_scheduled( self::REFRESH_HOOK, array( (string) $queued_postcode ) ) ) ) {
                unset( $queue[ $queued_postcode ] );
            }
        }
        $key = $postcode;
        if ( isset( $queue[ $key ] ) || count( $queue ) >= self::MAX_REFRESHES || wp_next_scheduled( self::REFRESH_HOOK, array( $postcode ) ) ) {
            return;
        }
        if ( true === wp_schedule_single_event( time() + 60, self::REFRESH_HOOK, array( $postcode ) ) ) {
            $queue[ $key ] = time() + self::REFRESH_INTERVAL;
            set_transient( self::REFRESH_QUEUE, $queue, self::TTL );
        }
    }

    /** Called only after the repository validates the complete official response. */
    public static function remember( array $rows ): void {
        $cache = self::cache();
        foreach ( $rows as $row ) {
            unset( $cache[ self::key( $row['subdistrict_id'] ) ] );
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
        if ( isset( $cache[ $key ] ) && ( '' === $postcode || $postcode === $cache[ $key ]['postcode'] ) ) {
            self::scheduleRefresh( $cache[ $key ]['postcode'] );
            return $cache[ $key ];
        }
        // Historical addresses without a verified mapping or postcode fail closed.
        if ( '' === $postcode ) {
            return null;
        }
        $response = $search( $postcode );
        if ( empty( $response['status'] ) ) {
            self::scheduleRefresh( $postcode );
            return null;
        }
        $cache = self::cache();
        if ( ! isset( $cache[ $key ] ) || $postcode !== $cache[ $key ]['postcode'] ) {
            self::scheduleRefresh( $postcode );
        }
        return isset( $cache[ $key ] ) && $postcode === $cache[ $key ]['postcode'] ? $cache[ $key ] : null;
    }

    private static function key( $village ): string {
        return hash( 'sha256', 'unified-village:' . (int) $village );
    }

    private static function cache(): array {
        $stored = function_exists( 'get_transient' ) ? get_transient( self::CACHE_KEY ) : false;
        $valid = static function ( $row ) {
            return is_array( $row ) && ( $row['expires'] ?? 0 ) > time()
                && is_int( $row['district_id'] ?? null ) && $row['district_id'] > 0
                && is_int( $row['city_id'] ?? null ) && $row['city_id'] > 0
                && is_int( $row['province_id'] ?? null ) && $row['province_id'] > 0
                && is_string( $row['postcode'] ?? null ) && 1 === preg_match( '/\A[0-9]{5}\z/', $row['postcode'] );
        };
        return array_replace( array_filter( self::$identities, $valid ), is_array( $stored ) ? array_filter( $stored, $valid ) : array() );
    }
}
