<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	/** Courier services mirrored from Shopify app/helpers/expedition.ts. */
class CourierServiceCatalog {
	/**
	 * Return whether a courier belongs to the requested delivery type.
	 *
	 * Express remains the default for backwards compatibility. Instant is
	 * deliberately limited to the couriers documented by the Instant API.
	 *
	 * @param string               $code          Courier code.
	 * @param array<string, mixed> $row           Raw API row.
	 * @param string|null          $delivery_type express, instant, or null for both.
	 */
	public static function isSupportedCourier( string $code, array $row = array(), ?string $delivery_type = 'express' ): bool {
		$code = strtolower( trim( $code ) );
		$type = strtolower( trim( (string) ( $row['type'] ?? '' ) ) );
		$region = strtolower( trim( (string) ( $row['region'] ?? '' ) ) );
		if ( '' === $code || 'international' === $type || 'international' === $region || 'ninja_inter' === $code ) {
			return false;
		}

		$is_instant = in_array( $code, self::instantCodes(), true ) || 'instant' === $type;
		if ( 'instant' === $delivery_type ) {
			return $is_instant && in_array( $code, self::instantCodes(), true );
		}
		if ( 'express' === $delivery_type ) {
			return ! $is_instant;
		}

		return self::isSupportedCourier( $code, $row, 'instant' ) || self::isSupportedCourier( $code, $row, 'express' );
	}

	/** @return string[] */
	public static function instantCodes(): array {
		return array( 'gosend', 'grab_express', 'borzo' );
	}

	/**
	 * Shopify 5a9a2d7 courier children; Borzo is disabled in that reference.
	 *
	 * These definitions enrich account-returned rows; they do not grant entitlement.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function instantServices(): array {
		return array(
			'gosend' => array(
				'code' => 'gosend',
				'name' => 'GoSend',
				'type' => 'instant',
				'services' => array(
					array( 'code' => 'instant', 'name' => 'Instant' ),
					array( 'code' => 'sameday', 'name' => 'Same Day' ),
				),
			),
			'grab_express' => array(
				'code' => 'grab_express',
				'name' => 'GrabExpress',
				'type' => 'instant',
				'services' => array(
					array( 'code' => 'instant', 'name' => 'Instant' ),
					array( 'code' => 'sameday', 'name' => 'Same Day' ),
				),
			),
		);
	}

	/** Filter raw API/cache rows without changing their public shape. */
	public static function filterSupported( array $couriers, ?string $delivery_type = 'express' ): array {
		return array_values( array_filter( $couriers, static function ( $row ) use ( $delivery_type ) {
			$row = (array) $row;
			return self::isSupportedCourier( (string) ( $row['code'] ?? '' ), $row, $delivery_type );
		} ) );
	}

	public static function filterSelection( array $selection, ?string $delivery_type = null ): array {
		return array_filter( $selection, static function ( $code ) use ( $delivery_type ) {
			return self::isSupportedCourier( (string) $code, array(), $delivery_type );
		}, ARRAY_FILTER_USE_KEY );
	}

	public static function known(): array {
		return array(
			'anteraja' => array( 'code' => 'anteraja', 'name' => 'AnterAja', 'type' => 'regular', 'services' => array(
				array( 'code' => 'ND', 'name' => 'Next Day' ),
				array( 'code' => 'SD', 'name' => 'Same Day' ),
				array( 'code' => 'REG', 'name' => 'Regular' ),
				array( 'code' => 'FLSD', 'name' => 'Flat Same Day' ),
				array( 'code' => 'FLREG', 'name' => 'Flat Regular' ),
			) ),
			'jne' => array( 'code' => 'jne', 'name' => 'JNE Express', 'type' => 'regular', 'services' => array(
				array( 'code' => 'CTC', 'name' => 'Reguler', 'aliases' => array( 'CTC19', 'CTC23' ) ),
				array( 'code' => 'REG', 'name' => 'Reguler', 'aliases' => array( 'REG19', 'REG23' ) ),
				array( 'code' => 'OKE', 'name' => 'OKE', 'aliases' => array( 'OKE19', 'OKE23' ) ),
				array( 'code' => 'YES', 'name' => 'YES', 'aliases' => array( 'YES19', 'YES23' ) ),
				array( 'code' => 'JTR', 'name' => 'Trucking', 'aliases' => array( 'JTR18', 'JTR23' ) ),
				array( 'code' => 'CTCJTR', 'name' => 'Trucking', 'aliases' => array( 'CTCJTR23' ) ),
				array( 'code' => 'FLCTC', 'name' => 'Flat Reguler', 'aliases' => array( 'FLCTC19', 'FLCTC23' ) ),
				array( 'code' => 'FLREG', 'name' => 'Flat Reguler', 'aliases' => array( 'FLREG19', 'FLREG23' ) ),
			) ),
			'jnt' => array( 'code' => 'jnt', 'name' => 'J&T Express', 'type' => 'regular', 'services' => array(
				array( 'code' => 'EZ', 'name' => 'Reguler' ),
				array( 'code' => 'JSD', 'name' => 'Same Day' ),
				array( 'code' => 'JND', 'name' => 'Next Day' ),
			) ),
			'ncs' => array( 'code' => 'ncs', 'name' => 'NCS Courier', 'type' => 'regular', 'services' => array(
				array( 'code' => 'NRS', 'name' => 'Regular' ),
				array( 'code' => 'ONS', 'name' => 'One Night' ),
				array( 'code' => 'NFO', 'name' => 'Food-One Night' ),
				array( 'code' => 'NFS', 'name' => 'Food-Same Day' ),
				array( 'code' => 'DRT', 'name' => 'Regular Darat' ),
				array( 'code' => 'NFR', 'name' => 'Food-Reguler' ),
			) ),
			'sap' => array( 'code' => 'sap', 'name' => 'SAP Express', 'type' => 'regular', 'services' => array(
				array( 'code' => 'UDRREG', 'name' => 'Satria Reg' ),
				array( 'code' => 'DRGREG', 'name' => 'Satria Cargo' ),
				array( 'code' => 'UDRONS', 'name' => 'One Day' ),
				array( 'code' => 'UDRSDS', 'name' => 'Same Day' ),
				array( 'code' => 'FLUDRREG', 'name' => 'Flat Satria Reg' ),
			) ),
			'sicepat' => array( 'code' => 'sicepat', 'name' => 'Sicepat', 'type' => 'regular', 'services' => array(
				array( 'code' => 'SIUNT', 'name' => 'Si Untung' ),
				array( 'code' => 'GOKIL', 'name' => 'Cargo Kilat' ),
				array( 'code' => 'BBM', 'name' => 'Berani Bayar Murah' ),
				array( 'code' => 'REG', 'name' => 'Reguler' ),
			) ),
			'ninja' => array( 'code' => 'ninja', 'name' => 'Ninja Xpress', 'type' => 'regular', 'services' => array(
				array( 'code' => 'Standard', 'name' => 'Standard' ),
			) ),
			'sentral' => array( 'code' => 'sentral', 'name' => 'Sentral Cargo', 'type' => 'regular', 'services' => array(
				array( 'code' => 'DARAT', 'name' => 'Darat Non-Ele.' ),
				array( 'code' => 'UDARA', 'name' => 'Udara Non-Ele.' ),
			) ),
			'lion' => array( 'code' => 'lion', 'name' => 'Lion Parcel', 'type' => 'regular', 'services' => array(
				array( 'code' => 'ONEPACK', 'name' => 'Onepack' ),
				array( 'code' => 'REGPACK', 'name' => 'Regpack' ),
				array( 'code' => 'BIGPACK', 'name' => 'Bigpack' ),
				array( 'code' => 'JAGOPACK', 'name' => 'Jagopack' ),
				array( 'code' => 'BOSSPACK', 'name' => 'Bosspack' ),
			) ),
			'idx' => array( 'code' => 'idx', 'name' => 'ID Express', 'type' => 'regular', 'services' => array(
				array( 'code' => '00', 'name' => 'Standard' ),
				array( 'code' => '01', 'name' => 'Same Day' ),
				array( 'code' => '06', 'name' => 'iDtruck' ),
				array( 'code' => '03', 'name' => 'iDlite' ),
				array( 'code' => 'FL00', 'name' => 'Flat Standard' ),
			) ),
			'jtcargo' => array( 'code' => 'jtcargo', 'name' => 'JTCargo', 'type' => 'cargo', 'services' => array(
				array( 'code' => 'REG', 'name' => 'REG' ),
			) ),
			'rpx' => array( 'code' => 'rpx', 'name' => 'RPX Logistics', 'type' => 'regular', 'services' => array(
				array( 'code' => 'RGP', 'name' => 'Regular Package' ),
				array( 'code' => 'NDP', 'name' => 'Next Day Package' ),
				array( 'code' => 'SDP', 'name' => 'SameDay Package' ),
				array( 'code' => 'MDP', 'name' => 'MidDay Package' ),
				array( 'code' => 'HWP', 'name' => 'Heavy Weight Delivery' ),
			) ),
			'paxel' => array( 'code' => 'paxel', 'name' => 'Paxel', 'type' => 'regular', 'services' => array(
				array( 'code' => 'SAMEDAY', 'name' => 'Same Day' ),
				array( 'code' => 'PAXEL BIG', 'name' => 'Next Day Big' ),
				array( 'code' => 'FLSAMEDAY', 'name' => 'Flat Same Day' ),
			) ),
			'tiki' => array( 'code' => 'tiki', 'name' => 'Tiki Indonesia', 'type' => 'express', 'services' => array(
				array( 'code' => 'SDS', 'name' => 'Same Day' ),
				array( 'code' => 'ONS', 'name' => 'Over Night' ),
				array( 'code' => 'REG', 'name' => 'Reguler' ),
				array( 'code' => 'ECO', 'name' => 'Economy' ),
				array( 'code' => 'TRC', 'name' => 'Trucking' ),
			) ),
			'posindonesia' => array( 'code' => 'posindonesia', 'name' => 'POS Indonesia', 'type' => 'regular', 'services' => array(
				array( 'code' => '240', 'name' => 'Pos Kilat Khusus' ),
				array( 'code' => 'PJB', 'name' => 'Pos Kargo' ),
				array( 'code' => '901976', 'name' => 'POS NEXT DAY' ),
				array( 'code' => '901979', 'name' => 'POS REGULER' ),
			) ),
			'spx' => array( 'code' => 'spx', 'name' => 'SPX', 'type' => 'express', 'services' => array(
				array( 'code' => '1', 'name' => 'Standard' ),
				array( 'code' => '3', 'name' => 'Economy' ),
			) ),
		);
	}

	/** Enrich only API-returned couriers; real embedded services remain authoritative. */
	public static function enrich( array $couriers, ?string $delivery_type = 'express' ): array {
		$known = array_merge( self::known(), self::instantServices() );
		$result = array();
		foreach ( $couriers as $row ) {
			$row = (array) $row;
			$code = strtolower( trim( (string) ( $row['code'] ?? '' ) ) );
			$type = strtolower( (string) ( $row['type'] ?? 'regular' ) );
			if ( ! self::isSupportedCourier( $code, $row, $delivery_type ) ) {
				continue;
			}
			$services = array();
			foreach ( (array) ( $row['services'] ?? array() ) as $service ) {
				$service = (array) $service;
				if ( ! isset( $service['code'] ) || ( ! is_string( $service['code'] ) && ! is_int( $service['code'] ) ) || '' === trim( (string) $service['code'] ) ) {
					continue;
				}
				$service['code'] = (string) $service['code'];
				$canonical = self::canonicalService( $code, $service['code'], $known );
				$definition = null;
				foreach ( $known[ $code ]['services'] ?? array() as $candidate ) {
					if ( $candidate['code'] === $canonical ) {
						$definition = $candidate;
						break;
					}
				}
				$definition = $definition ?? array( 'code' => $service['code'], 'name' => (string) ( $service['name'] ?? $service['code'] ) );
				if ( ! empty( $service['name'] ) && $canonical === $service['code'] ) {
					$definition['name'] = (string) $service['name'];
				}
				$services[ $definition['code'] ] = $definition;
			}
			$is_instant = self::isSupportedCourier( $code, $row, 'instant' );
			$result[] = array(
				'code' => $code,
				'name' => (string) ( $row['name'] ?? $known[ $code ]['name'] ?? strtoupper( $code ) ),
				'type' => $type,
				'delivery_type' => $is_instant ? 'instant' : 'express',
				'services' => $services ? array_values( $services ) : ( $known[ $code ]['services'] ?? ( $is_instant ? array() : array( array( 'code' => '*', 'name' => 'All services' ) ) ) ),
			);
		}
		return $result;
	}

	/** Known fallback plus cached API services, without requiring a live request. */
	public static function available( ?string $delivery_type = null ): array {
		$catalog = self::known();
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( 'kiriof_couriers_list_v2' );
			if ( false === $cached ) {
				$cached = get_transient( 'kiriof_couriers_last_success_cache' );
			}
			foreach ( self::enrich( (array) $cached, $delivery_type ) as $courier ) {
				// Save accepts every displayed cached choice as well as known aliases.
				$courier['services'] = array_merge( $courier['services'], $catalog[ $courier['code'] ]['services'] ?? array() );
				$catalog[ $courier['code'] ] = $courier;
			}
			$instant_cached = get_transient( 'kiriof_couriers_all_v1' );
			if ( false === $instant_cached ) {
				$instant_cached = get_transient( 'kiriof_couriers_all_last_success_v1' );
			}
			foreach ( self::enrich( (array) $instant_cached, $delivery_type ) as $courier ) {
				$courier['services'] = array_merge( $courier['services'], $catalog[ $courier['code'] ]['services'] ?? array() );
				$catalog[ $courier['code'] ] = $courier;
			}
		}
		if ( null !== $delivery_type ) {
			$catalog = array_filter(
				$catalog,
				static function ( $courier ) use ( $delivery_type ) {
					return self::isSupportedCourier( (string) ( $courier['code'] ?? '' ), (array) $courier, $delivery_type );
				}
			);
		}
		return $catalog;
	}

	/** Exact case-insensitive matches only: never strip version digits or compare labels. */
	public static function canonicalService( string $courier, string $service, ?array $catalog = null ): ?string {
		if ( null === $catalog ) {
			$catalog = self::available();
			$known = self::known();
			$key = strtolower( trim( $courier ) );
			$catalog[ $key ]['services'] = array_merge( $catalog[ $key ]['services'] ?? array(), $known[ $key ]['services'] ?? array() );
		}
		$courier = strtolower( trim( $courier ) );
		if ( ! self::isSupportedCourier( $courier, array(), null ) ) {
			return null;
		}
		foreach ( $catalog[ $courier ]['services'] ?? array() as $definition ) {
			foreach ( array_merge( array( $definition['code'] ), $definition['aliases'] ?? array() ) as $code ) {
				if ( 0 === strcasecmp( trim( $service ), $code ) ) {
					return $definition['code'];
				}
			}
		}
		return null;
	}

	/** Parse the AJAX JSON object, retaining {} as an explicit deny-all policy. */
	public static function parseSelection( $json ): array {
		if ( ! is_string( $json ) ) {
			throw new \InvalidArgumentException( 'service_selection must be a JSON object string.' );
		}
		$decoded = json_decode( $json );
		if ( JSON_ERROR_NONE !== json_last_error() || ! $decoded instanceof \stdClass ) {
			throw new \InvalidArgumentException( 'service_selection must be a valid JSON object.' );
		}
		$selection = array();
		foreach ( get_object_vars( $decoded ) as $courier => $services ) {
			if ( '' === trim( $courier ) || ! is_array( $services ) ) {
				throw new \InvalidArgumentException( 'Each courier must contain an array of service codes.' );
			}
			$key = strtolower( trim( $courier ) );
			if ( array_key_exists( $key, $selection ) ) {
				throw new \InvalidArgumentException( 'Duplicate courier code.' );
			}
			foreach ( $services as $service ) {
				if ( ! is_string( $service ) || '' === trim( $service ) ) {
					throw new \InvalidArgumentException( 'Service codes must be nonempty strings.' );
				}
			}
			$selection[ $key ] = $services;
		}
		return $selection;
	}
}
