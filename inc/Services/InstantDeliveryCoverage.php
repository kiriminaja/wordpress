<?php
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixed straight-line Instant coverage, independent of courier routing or settings. */
final class InstantDeliveryCoverage {
	public const MAX_METERS = 40000;
	private const EARTH_RADIUS_METERS = 6371000;

	/** Validates both pins using the shared destination coordinate contract. */
	public static function distanceMeters( $origin_latitude, $origin_longitude, $destination_latitude, $destination_longitude ): float {
		$coordinates = array( $origin_latitude, $origin_longitude, $destination_latitude, $destination_longitude );
		foreach ( $coordinates as $index => $value ) {
			if ( '' === BuyerDestination::coordinate( $value, 0 === $index % 2 ? 90 : 180 ) ) {
				throw new InvalidArgumentException( 'Valid origin and destination coordinates are required for Instant delivery.' );
			}
		}
		// Preserve validated input precision here so an exact 40 km boundary is
		// not shifted by the seven-decimal storage normalization.
		$origin_latitude = deg2rad( (float) $origin_latitude );
		$destination_latitude = deg2rad( (float) $destination_latitude );
		$latitude_delta = $destination_latitude - $origin_latitude;
		$longitude_delta = deg2rad( (float) $destination_longitude - (float) $origin_longitude );
		$haversine = sin( $latitude_delta / 2 ) ** 2 + cos( $origin_latitude ) * cos( $destination_latitude ) * sin( $longitude_delta / 2 ) ** 2;
		// Floating point drift at antipodal pins must not make asin return NaN.
		return 2 * self::EARTH_RADIUS_METERS * asin( sqrt( max( 0.0, min( 1.0, $haversine ) ) ) );
	}

	/** Inclusive limit with only a sub-millimetre floating point tolerance. */
	public static function covers( $origin_latitude, $origin_longitude, $destination_latitude, $destination_longitude ): bool {
		return self::distanceMeters( $origin_latitude, $origin_longitude, $destination_latitude, $destination_longitude ) <= self::MAX_METERS + 0.001;
	}
}
