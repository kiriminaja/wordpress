<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Public advisory coverage, without quoting or exposing pickup address details. */
class InstantMapCoverageService {
	private ShipmentLocationService $locations;

	public function __construct( ?ShipmentLocationService $locations = null ) {
		$this->locations = $locations ?? new ShipmentLocationService();
	}

	/** Normalize only coordinates; missing values must never become zero. */
	public static function fromOrigin( array $origin ): ?array {
		try {
			$latitude  = BuyerDestination::coordinate( $origin['origin_latitude'] ?? $origin['latitude'] ?? null, 90 );
			$longitude = BuyerDestination::coordinate( $origin['origin_longitude'] ?? $origin['longitude'] ?? null, 180 );
		} catch ( \InvalidArgumentException $error ) {
			return null;
		}
		if ( '' === $latitude || '' === $longitude ) {
			return null;
		}
		return array(
			'origin' => array( 'latitude' => $latitude, 'longitude' => $longitude ),
			'radiusMeters' => InstantDeliveryCoverage::MAX_METERS,
		);
	}

	/** Resolve the same local default pickup used by shipping, without an API call. */
	public function defaultCoverage(): ?array {
		return self::fromOrigin( $this->locations->locationToOrigin( $this->locations->getDefaultLocation() ) );
	}

	/** An explicit package origin is authoritative, including an invalid origin. */
	public function checkoutCoverage(): ?array {
		$wc = function_exists( 'WC' ) ? WC() : null;
		$shipping = $wc && method_exists( $wc, 'shipping' ) ? $wc->shipping() : null;
		$packages = $shipping && method_exists( $shipping, 'get_packages' ) ? $shipping->get_packages() : array();
		if ( ! is_array( $packages ) ) {
			return null;
		}
		if ( empty( $packages ) ) {
			return $this->defaultCoverage();
		}
		$coverage = null;
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) ) {
				return null;
			}
			if ( ! array_key_exists( 'origin', $package ) ) {
				return 1 === count( $packages ) ? $this->defaultCoverage() : null;
			}
			$current = is_array( $package['origin'] ) ? self::fromOrigin( $package['origin'] ) : null;
			if ( null === $current || ( null !== $coverage && $current !== $coverage ) ) {
				return null;
			}
			$coverage = $current;
		}
		return $coverage;
	}
}
