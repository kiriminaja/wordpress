<?php
namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\SettingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Local configuration diagnostics, not a live quote or package availability check. */
class InstantCheckoutDiagnosticsService {
	private ?SettingRepository $settings;
	private ?ShipmentLocationService $locations;

	/** Keep composition at plugins_loaded free of database initialization. */
	public function __construct( ?SettingRepository $settings = null, ?ShipmentLocationService $locations = null ) {
		$this->settings  = $settings;
		$this->locations = $locations;
	}

	/** Diagnostics are requested explicitly by callers; checkout hooks never log readiness. */
	public function register(): void {}

	/** Only booleans, numeric IDs and bounded configuration/reason codes leave this method. */
	public function snapshot(): array {
		$this->settings  = $this->settings ?? new SettingRepository();
		$this->locations = $this->locations ?? new ShipmentLocationService( null, $this->settings );
		$selection = $this->settings->getCourierServiceSelection();
		$services = array();
		foreach ( array( 'gosend', 'grab_express' ) as $courier ) {
			foreach ( (array) ( $selection[ $courier ] ?? array() ) as $service ) {
				if ( is_string( $service ) && preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/', $service ) ) {
					$services[ $courier ][] = $service;
				}
			}
			if ( isset( $services[ $courier ] ) ) {
				$services[ $courier ] = array_values( array_unique( $services[ $courier ] ) );
				sort( $services[ $courier ], SORT_STRING );
			}
		}
		$credential = $this->settings->getSettingByKey( 'api_key' );
		$insurance = $this->settings->getSettingByKey( 'enable_insurance' );
		$wc = function_exists( 'WC' ) ? WC() : null;
		$session = $wc->session ?? null;
		$raw_pin = $session && method_exists( $session, 'get' ) ? $session->get( 'kiriof_buyer_destination', null ) : null;
		$pin_valid = false;
		$address_matches = false;
		$current = array();
		$address_complete = false;
		try {
			foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
				$getter = 'get_shipping_' . $field;
				$current[ $field ] = $wc->customer->$getter();
			}
			$current = BuyerDestination::address( $current );
			$address_complete = 'ID' === $current['country'] && '' !== $current['address_1'] && '' !== $current['city'] && '' !== $current['state']
				&& 1 === preg_match( '/\A[0-9]{5}\z/', $current['postcode'] );
		} catch ( \Throwable $error ) {
			$current = array();
		}
		try {
			$pin = BuyerDestination::normalize( $raw_pin );
			$pin_valid = 2 === $pin['version'] && '' !== $pin['district_id'];
			$address_matches = $pin_valid && $address_complete && $current === $pin['shipping_address'];
		} catch ( \Throwable $error ) {
			// Malformed or legacy destinations are expected; never log their contents.
		}
		$zone_known = false;
		$zone_enabled = false;
		$zone_id = null;
		$zone_methods = array();
		// Local zone lookup only. Loading method instances does not calculate rates or fetch quotes.
		if ( $address_complete && class_exists( 'WC_Shipping_Zones' ) ) {
			try {
				$zone = \WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => $current ) );
				if ( is_object( $zone ) && is_callable( array( $zone, 'get_shipping_methods' ) ) ) {
					$id = is_callable( array( $zone, 'get_id' ) ) ? $zone->get_id() : null;
					$methods = array();
					$enabled = false;
					// Include disabled rows: WooCommerce applies the admin row's is_enabled to
					// each instance's enabled property, independently of instance settings.
					foreach ( $zone->get_shipping_methods( false ) as $key => $method ) {
						if ( ! is_object( $method ) ) {
							continue;
						}
						$method_id = $method->id ?? null;
						if ( ! is_string( $method_id ) || 1 !== preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/', $method_id ) ) {
							continue;
						}
						$instance_id = is_callable( array( $method, 'get_instance_id' ) ) ? $method->get_instance_id() : ( $method->instance_id ?? $key );
						$row_enabled = 'yes' === ( $method->enabled ?? null );
						$stored = $method->instance_settings['enabled'] ?? null;
						$stored_enabled = in_array( $stored, array( 'yes', 'no' ), true ) ? 'yes' === $stored : null;
						$methods[] = array(
							'method_id' => $method_id,
							'instance_id' => $this->numericId( $instance_id ) ?? 0,
							'enabled' => $row_enabled,
							'stored_enabled' => $stored_enabled,
							'enabled_settings_conflict' => null !== $stored_enabled && $stored_enabled !== $row_enabled,
						);
						if ( 'kiriminaja-instant' === $method_id && $row_enabled ) {
							$enabled = true;
						}
					}
					$zone_id = $this->numericId( $id );
					$zone_methods = $methods;
					$zone_enabled = $enabled;
					$zone_known = true;
				}
			} catch ( \Throwable $error ) {
				// An unknown zone is not proof that the method is disabled.
			}
		}
		// Inspect the stored default only; getDefaultLocation() can seed/repair rows.
		$origin = $this->locations->locationToOrigin( $this->locations->repository()->getDefault() );
		$coordinates = false;
		try {
			$coordinates = '' !== BuyerDestination::coordinate( $origin['origin_latitude'] ?? null, 90 )
				&& '' !== BuyerDestination::coordinate( $origin['origin_longitude'] ?? null, 180 );
		} catch ( \Throwable $error ) {
			// Includes the literal string "null", but deliberately accepts zero.
		}
		$address = $this->text( $origin, 'origin_address' );
		foreach ( array( 'origin_address_2', 'origin_city', 'origin_state' ) as $field ) {
			$part = $this->text( $origin, $field );
			if ( '' !== $part ) {
				$address .= ', ' . $part;
			}
		}
		$phone = $this->text( $origin, 'origin_phone' );
		$country = $this->text( $origin, 'origin_country' );
		$timezone = '';
		$timezone_valid = false;
		try {
			$timezone = InstantCheckoutQuoteService::normalizeTimezone( $origin );
			$timezone_valid = true;
		} catch ( \Throwable $error ) {
			// Explicit invalid source timezones fail closed; WordPress timezone is unrelated.
		}
		$result = array(
			'code' => 'configured_store_readiness',
			'plugin_version' => defined( 'KIRIOF_VERSION' ) ? KIRIOF_VERSION : 'unknown',
			'live_quote_checked' => false,
			'service_keys' => $services,
			'services_enabled' => ! empty( $services ),
			'credential_present' => is_object( $credential ) && is_string( $credential->value ?? null ) && '' !== trim( $credential->value ),
			'insurance_enabled' => 'yes' === ( $insurance->value ?? null ),
			'instant_insurance_enabled' => false,
			'instant_insurance_supported' => false,
			'payment_is_cod' => $session && 'cod' === strtolower( trim( (string) $session->get( 'chosen_payment_method', '' ) ) ),
			'destination_version' => is_int( $raw_pin['version'] ?? null ) && in_array( $raw_pin['version'], array( 1, 2 ), true ) ? $raw_pin['version'] : 0,
			'coordinate_session_present' => is_array( $raw_pin ) && isset( $raw_pin['destination_latitude'], $raw_pin['destination_longitude'] ),
			'destination_district_recorded' => is_array( $raw_pin ) && ( is_string( $raw_pin['district_id'] ?? null ) || is_int( $raw_pin['district_id'] ?? null ) ) && 1 === preg_match( '/\A[1-9][0-9]*\z/', (string) ( $raw_pin['district_id'] ?? '' ) ),
			'destination_address_complete' => $address_complete,
			'destination_pin_valid' => $pin_valid,
			'destination_address_matches' => $address_matches,
			'default_origin_present' => ! empty( $origin ),
			'default_origin_coordinates_valid' => $coordinates,
			'default_origin_address_valid' => $this->length( $address, 20, 250 ) && '' !== $this->text( $origin, 'origin_address' ),
			'default_origin_name_valid' => $this->length( $this->text( $origin, 'origin_name' ), 10, 40 ),
			'default_origin_phone_valid' => $this->length( $phone, 8, 14 ) && 1 === preg_match( '/\A(?:08|62)[0-9]+\z/', $phone ),
			'default_origin_postcode_valid' => 1 === preg_match( '/\A[0-9]{5}\z/', $this->text( $origin, 'origin_zip_code' ) ),
			'default_origin_country_valid' => '' === $country || 'ID' === strtoupper( $country ),
			'timezone_supported' => $timezone_valid,
			'instant_timezone' => $timezone,
			'matching_zone_known' => $zone_known,
			'matching_zone_id' => $zone_id,
			'matching_zone_methods' => $zone_methods,
			'method_zone_enabled' => $zone_enabled,
			'method_registered' => function_exists( 'has_filter' ) && false !== has_filter( 'woocommerce_shipping_methods', 'kiriof_register_instant_shipping_method' ) && function_exists( 'kiriof_register_instant_shipping_method' ),
			'checkout_integration_ready' => class_exists( 'KiriminAjaOfficial\\Controllers\\InstantCheckoutController', false ),
		);
		$checks = array(
			'services_enabled' => 'services_disabled', 'credential_present' => 'account_unavailable',
			'destination_pin_valid' => 'destination_pin_missing_or_invalid', 'destination_address_matches' => 'destination_address_changed_or_invalid',
			'default_origin_present' => 'default_origin_missing', 'default_origin_coordinates_valid' => 'default_origin_coordinates_invalid',
			'default_origin_address_valid' => 'default_origin_address_invalid', 'default_origin_name_valid' => 'default_origin_name_invalid',
			'default_origin_phone_valid' => 'default_origin_phone_invalid', 'default_origin_postcode_valid' => 'default_origin_postcode_invalid',
			'default_origin_country_valid' => 'default_origin_country_invalid', 'timezone_supported' => 'timezone_unsupported',
			'method_registered' => 'method_not_registered', 'checkout_integration_ready' => 'checkout_integration_not_ready',
		);
		$result['reasons'] = array();
		foreach ( $checks as $check => $reason ) {
			if ( ! $result[ $check ] && ( 'destination_address_matches' !== $check || $pin_valid ) ) {
				$result['reasons'][] = $reason;
			}
		}
		if ( $zone_known && ! $zone_enabled ) {
			$result['reasons'][] = 'method_not_enabled_in_matching_zone';
		}
		foreach ( array( 'payment_is_cod' => 'cod_unsupported' ) as $check => $reason ) {
			if ( $result[ $check ] ) {
				$result['reasons'][] = $reason;
			}
		}
		$result['ready'] = empty( $result['reasons'] );
		return $result;
	}

	/** Reject arbitrary strings rather than coercing private data into identifiers. */
	private function numericId( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}
		if ( is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/', $value ) ) {
			$id = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
			return false === $id ? null : $id;
		}
		return null;
	}

	private function text( array $origin, string $key ): string {
		return is_string( $origin[ $key ] ?? null ) ? trim( $origin[ $key ] ) : '';
	}

	private function length( string $value, int $min, int $max ): bool {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		return $length >= $min && $length <= $max;
	}
}
