<?php
/** Historical, read-only coordinates for the Instant transaction detail map. */
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InstantDetailMapData {
    /** Public tile configuration only; never publishes private pickup data. */
    public static function mapConfig(): array {
        $tiles = function_exists( 'apply_filters' ) ? (string) apply_filters( 'kiriof_map_checkout_tiles_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' ) : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
        if ( 0 !== strpos( $tiles, 'https://' ) ) { $tiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'; }
        $attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
        return array_merge( ( new GoogleMapsSettings() )->config(), array( 'enabled' => true, 'tiles' => $tiles, 'attribution' => $attribution ) );
    }

	/** No live location, WooCommerce geolocation, or remote routing is consulted. */
	public static function prepare( object $row ): ?array {
		if ( 'instant' !== TransactionDeliveryType::resolve( $row ) ) {
			return null;
		}
		$shipping = self::snapshot( $row->shipping_info ?? null );
		$request  = self::snapshot( $row->instant_request_snapshot ?? $shipping['instant_request_snapshot'] ?? null );
		// A present snapshot is authoritative, including malformed/incomplete data.
		$raw_origin = $row->shipment_location_snapshot ?? null;
		$origin = null !== $raw_origin && '' !== $raw_origin
			? self::pair( self::snapshot( $raw_origin ), 'origin_latitude', 'origin_longitude' )
			: self::pair( self::snapshot( $request['origin'] ?? null ), 'origin_latitude', 'origin_longitude' );

		$fields = get_object_vars( $row );
		if ( array_key_exists( 'destination_latitude', $fields ) || array_key_exists( 'destination_longitude', $fields ) ) {
			$destination = self::pair( $fields, 'destination_latitude', 'destination_longitude' );
		} elseif ( array_key_exists( 'destination_latitude', $shipping ) || array_key_exists( 'destination_longitude', $shipping ) ) {
			$destination = self::pair( $shipping, 'destination_latitude', 'destination_longitude' );
		} elseif ( array_key_exists( 'instant_destination', $shipping ) ) {
			$destination = self::pair( self::snapshot( $shipping['instant_destination'] ), 'destination_latitude', 'destination_longitude' );
		} else {
			$destination = self::pair( self::snapshot( $request['destination'] ?? null ), 'destination_latitude', 'destination_longitude' );
		}

		$points = InstantTrackingPresentation::routePoints( $row );
		$mode   = count( $points ) >= 2 ? 'recorded' : ( null !== $origin && null !== $destination ? 'illustration' : 'unavailable' );
		// Illustration endpoints are not presented as courier route evidence.
		if ( 'illustration' === $mode ) {
			$points = array( array( $origin['latitude'], $origin['longitude'] ), array( $destination['latitude'], $destination['longitude'] ) );
		}
		return array( 'origin' => $origin, 'destination' => $destination, 'points' => $points, 'mode' => $mode );
	}

	private static function snapshot( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}
		return is_object( $raw ) ? get_object_vars( $raw ) : ( is_array( $raw ) ? $raw : array() );
	}

	/** Select a complete pair from one schema; never mix aliases or sources. */
	private static function pair( array $data, string $lat_key, string $lng_key ): ?array {
		if ( array_key_exists( $lat_key, $data ) || array_key_exists( $lng_key, $data ) ) {
			$lat = $data[ $lat_key ] ?? null;
			$lng = $data[ $lng_key ] ?? null;
		} elseif ( array_key_exists( 'latitude', $data ) || array_key_exists( 'longitude', $data ) ) {
			$lat = $data['latitude'] ?? null;
			$lng = $data['longitude'] ?? null;
		} else {
			$lat = $data['lat'] ?? null;
			$lng = $data['lng'] ?? $data['long'] ?? null;
		}
		$lat = self::coordinate( $lat, 90 );
		$lng = self::coordinate( $lng, 180 );
		return null === $lat || null === $lng ? null : array( 'latitude' => $lat, 'longitude' => $lng );
	}

	private static function coordinate( $value, int $limit ): ?float {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( ! preg_match( '/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)\z/', $value ) ) {
				return null;
			}
		} elseif ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}
		$value = (float) $value;
		return is_finite( $value ) && abs( $value ) <= $limit ? $value : null;
	}
}
