<?php
namespace KiriminAjaOfficial\Services\CheckoutServices;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PricingCacheService {
    private const SESSION_KEY = 'kiriof_shipping_price_cache';
    private const TRANSIENT_PREFIX = 'kiriof_ship_price_';
    private const MAX_ENTRIES = 8;
    private const TTL_SECONDS = 300;
    private const STALE_TTL_SECONDS = 900;
    private static array $runtime_cache = array();

    public static function get( array $payload, bool $allow_stale = false ) {
        $key   = self::cacheKey( $payload );
        if ( isset( self::$runtime_cache[ $key ] ) && self::isUsable( self::$runtime_cache[ $key ], $allow_stale ) ) {
            return self::$runtime_cache[ $key ]['data'] ?? null;
        }

        $cache = self::getCache();
        if ( isset( $cache[ $key ] ) && self::isUsable( $cache[ $key ], $allow_stale ) ) {
            self::$runtime_cache[ $key ] = $cache[ $key ];
            return $cache[ $key ]['data'] ?? null;
        }

        $transient_entry = get_transient( self::transientKey( $key ) );
        if ( self::isUsable( $transient_entry, $allow_stale ) ) {
            self::storeEntry( $key, $transient_entry );
            return $transient_entry['data'] ?? null;
        }

        $base_key = self::baseKey( $payload );
        $couriers = self::normalizeCouriers( $payload['courier'] ?? null );
        foreach ( $cache as $entry ) {
            if ( ! self::isUsable( $entry, $allow_stale ) || ( $entry['base_key'] ?? '' ) !== $base_key ) {
                continue;
            }

            $entry_couriers = isset( $entry['couriers'] ) && is_array( $entry['couriers'] )
                ? $entry['couriers']
                : array();
            if ( self::couriersAreCompatible( $couriers, $entry_couriers ) ) {
                return $entry['data'] ?? null;
            }
        }

        return null;
    }

    public static function put( array $payload, $pricing_data ): void {
        if ( empty( $pricing_data ) ) {
            return;
        }

        $key   = self::cacheKey( $payload );
        $entry = array(
            'base_key'  => self::baseKey( $payload ),
            'couriers'  => self::normalizeCouriers( $payload['courier'] ?? null ),
            'data'      => $pricing_data,
            'stored_at' => time(),
        );

        self::storeEntry( $key, $entry );
    }

    private static function storeEntry( string $key, array $entry ): void {
        self::$runtime_cache[ $key ] = $entry;
        set_transient( self::transientKey( $key ), $entry, self::TTL_SECONDS );

        if ( ! self::hasSession() ) {
            return;
        }

        $cache = self::getCache();
        $cache[ $key ] = $entry;
        $cache = array_filter( $cache, array( self::class, 'isFresh' ) );
        if ( count( $cache ) > self::MAX_ENTRIES ) {
            $cache = array_slice( $cache, -self::MAX_ENTRIES, null, true );
        }

        WC()->session->set( self::SESSION_KEY, $cache );
    }

    private static function transientKey( string $key ): string {
        return self::TRANSIENT_PREFIX . $key;
    }

    private static function getCache(): array {
        if ( ! self::hasSession() ) {
            return array();
        }

        $cache = WC()->session->get( self::SESSION_KEY, array() );
        return is_array( $cache ) ? $cache : array();
    }

    private static function hasSession(): bool {
        return function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session;
    }

    private static function isFresh( $entry ): bool {
        if ( ! is_array( $entry ) || empty( $entry['stored_at'] ) ) {
            return false;
        }

        return ( time() - (int) $entry['stored_at'] ) <= self::TTL_SECONDS;
    }

    private static function isUsable( $entry, bool $allow_stale ): bool {
        if ( self::isFresh( $entry ) ) {
            return true;
        }

        if ( ! $allow_stale || ! is_array( $entry ) || empty( $entry['stored_at'] ) ) {
            return false;
        }

        return ( time() - (int) $entry['stored_at'] ) <= self::STALE_TTL_SECONDS;
    }

    private static function cacheKey( array $payload ): string {
        return md5(
            wp_json_encode(
                array(
                    'base_key' => self::baseKey( $payload ),
                    'couriers' => self::normalizeCouriers( $payload['courier'] ?? null ),
                )
            )
        );
    }

    private static function baseKey( array $payload ): string {
        return md5(
            wp_json_encode(
                array(
                    'subdistrict_origin'      => (int) ( $payload['subdistrict_origin'] ?? 0 ),
                    'subdistrict_destination' => (int) ( $payload['subdistrict_destination'] ?? 0 ),
                    'weight'                  => (float) ( $payload['weight'] ?? 0 ),
                    'length'                  => (float) ( $payload['length'] ?? 0 ),
                    'width'                   => (float) ( $payload['width'] ?? 0 ),
                    'height'                  => (float) ( $payload['height'] ?? 0 ),
                    'insurance'               => (int) ( $payload['insurance'] ?? 0 ),
                    'item_value'              => (int) ( $payload['item_value'] ?? 0 ),
                    'pickup_option'           => isset( $payload['pickup_option'] ) ? (array) $payload['pickup_option'] : array( 'PICKUP' ),
                )
            )
        );
    }

    private static function normalizeCouriers( $courier ): array {
        if ( empty( $courier ) ) {
            return array();
        }

        $couriers = is_array( $courier ) ? $courier : array( $courier );
        $couriers = array_filter(
            array_map(
                static function ( $value ) {
                    return strtolower( trim( sanitize_text_field( (string) $value ) ) );
                },
                $couriers
            )
        );
        sort( $couriers );

        return array_values( array_unique( $couriers ) );
    }

    private static function couriersAreCompatible( array $requested_couriers, array $cached_couriers ): bool {
        if ( empty( $requested_couriers ) ) {
            return empty( $cached_couriers );
        }

        if ( empty( $cached_couriers ) ) {
            return true;
        }

        return empty( array_diff( $requested_couriers, $cached_couriers ) );
    }
}
