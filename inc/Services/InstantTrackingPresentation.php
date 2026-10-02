<?php
/** Persisted route evidence for Instant admin presentation; never fetches tracking. */
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InstantTrackingPresentation {
	/**
	 * Accept an explicit tracking polyline or validated booking route points.
	 * Origin/destination/driver coordinates and URLs
	 * are not route evidence. Missing persisted evidence deliberately fails closed.
	 */
	public static function hasRoute( object $row ): bool {
		return count( self::routePoints( $row ) ) >= 2;
	}

	/** Return validated persisted points only; never fetch or predict a route. */
	public static function routePoints( object $row ): array {
		// A present tracking payload is authoritative, even when invalid or empty.
		if ( ! property_exists( $row, 'instant_tracking_payload' ) ) {
			$snapshot = $row->shipping_info ?? null;
			if ( is_string( $snapshot ) ) {
				$snapshot = json_decode( $snapshot, true );
			}
			$snapshot = is_object( $snapshot ) ? get_object_vars( $snapshot ) : $snapshot;
			$points = is_array( $snapshot ) ? ( $snapshot['instant_route_points'] ?? null ) : null;
			return is_array( $points ) ? self::normalizePolyline( $points ) : array();
		}
		$payload = $row->instant_tracking_payload ?? null;
		if ( is_string( $payload ) ) {
			$payload = json_decode( $payload, true );
		}
		$payload = is_object( $payload ) ? get_object_vars( $payload ) : $payload;
		if ( ! is_array( $payload ) ) {
			return array();
		}
		// An envelope is authoritative: never fall back to another route on conflict.
		if ( array_key_exists( 'result', $payload ) ) {
			$payload = is_object( $payload['result'] ) ? get_object_vars( $payload['result'] ) : $payload['result'];
		}
		return is_array( $payload ) ? self::normalizePolyline( $payload['polyline'] ?? null ) : array();
	}

	public static function trackingUrl( object $row ): string {
		return self::hasRoute( $row ) ? InstantShipmentState::trackingUrl( $row->live_tracking_url ?? null ) : '';
	}

	private static function normalizePolyline( $polyline ): array {
		if ( is_string( $polyline ) ) {
			return self::decodePolyline( trim( $polyline ) );
		}
		if ( ! is_array( $polyline ) || ! array_is_list( $polyline ) || count( $polyline ) < 2 || count( $polyline ) > 10000 ) {
			return array();
		}
		$points = array();
		foreach ( $polyline as $point ) {
			$point = is_object( $point ) ? get_object_vars( $point ) : $point;
			if ( ! is_array( $point ) ) {
				return array();
			}
			if ( array_is_list( $point ) && 2 === count( $point ) ) {
				[ $lat, $lng ] = $point;
			} else {
				$lat = $point['lat'] ?? null;
				$lng = $point['lng'] ?? $point['long'] ?? null;
			}
			if ( ! self::validPoint( $lat, $lng ) ) {
				return array();
			}
			$points[] = array( (float) $lat, (float) $lng );
		}
		return $points;
	}

	private static function validPoint( $lat, $lng ): bool {
		return ( is_int( $lat ) || is_float( $lat ) ) && ( is_int( $lng ) || is_float( $lng ) )
			&& is_finite( (float) $lat ) && is_finite( (float) $lng ) && abs( $lat ) <= 90 && abs( $lng ) <= 180;
	}

	/** Validate Google encoded-polyline coordinates, not merely a nonempty string. */
	private static function decodePolyline( string $encoded ): array {
		$length = strlen( $encoded );
		if ( $length < 4 || $length > 100000 || ! preg_match( '/\A[\x3f-\x7e]+\z/', $encoded ) ) {
			return array();
		}
		$offset = 0;
		$lat    = 0;
		$lng    = 0;
		$points = array();
		while ( $offset < $length ) {
			if ( count( $points ) >= 10000 ) {
				return array();
			}
			foreach ( array( 'lat', 'lng' ) as $axis ) {
				$value = 0;
				$shift = 0;
				do {
					if ( $offset >= $length || $shift > 30 ) {
						return array();
					}
					$byte   = ord( $encoded[ $offset++ ] ) - 63;
					$value |= ( $byte & 31 ) << $shift;
					$shift += 5;
				} while ( $byte >= 32 );
				$delta = ( $value & 1 ) ? ~( $value >> 1 ) : ( $value >> 1 );
				if ( 'lat' === $axis ) {
					$lat += $delta;
				} else {
					$lng += $delta;
				}
			}
			if ( ! self::validPoint( $lat / 100000, $lng / 100000 ) ) {
				return array();
			}
			$points[] = array( $lat / 100000, $lng / 100000 );
		}
		return count( $points ) >= 2 ? $points : array();
	}
}
