<?php
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Live buyer quotes only. Never books, writes transactions or shares Express caches. */
class InstantCheckoutQuoteService {
	public const SESSION_KEY = 'kiriof_instant_checkout_quotes';
	private const TTL = 120;
	private const AMOUNT_VERSION = 3;
	private const MAX_QUOTES = 8;
	private SettingRepository $settings;
	private ShipmentLocationService $locations;
	private ?InstantDeliveryApiRepository $api;

	public function __construct( SettingRepository $settings, ShipmentLocationService $locations, ?InstantDeliveryApiRepository $api = null ) {
		$this->settings = $settings;
		$this->locations = $locations;
		$this->api = $api;
	}

	/** Instant has its own Indonesian timezone; never mutate or infer WordPress timezone. */
	public static function normalizeTimezone( array $origin ): string {
		foreach ( array( 'timezone', 'origin_timezone' ) as $key ) {
			if ( ! array_key_exists( $key, $origin ) ) {
				continue;
			}
			if ( ! is_string( $origin[ $key ] ) ) {
				throw new InvalidArgumentException( 'timezone_unsupported' );
			}
			$timezone = strtoupper( trim( $origin[ $key ] ) );
			if ( '' === $timezone ) {
				continue;
			}
			if ( ! in_array( $timezone, array( 'WIB', 'WITA', 'WIT' ), true ) ) {
				throw new InvalidArgumentException( 'timezone_unsupported' );
			}
			return $timezone;
		}
		return 'WIB';
	}

	/** @return array{eligible:bool,code:string,message:string,rates:array,context:?array} */
	public function quote( array $package, array $destination, string $payment_method, bool $insurance ): array {
		try {
			$context = $this->build( $package, $destination, $payment_method, $insurance );
		} catch ( InvalidArgumentException $error ) {
			return $this->failure( $error->getMessage() );
		} catch ( \Throwable $error ) {
			return $this->failure( 'context_unavailable' );
		}
		try {
			$session = $this->session();
			if ( null === $session ) {
				return $this->failure( 'session_unavailable' );
			}
			$cache = $this->cache( $session );
			$key = $context['fingerprint'];
			if ( isset( $cache[ $key ] ) && $this->compatible( $cache[ $key ], $context ) ) {
				return $this->success( $cache[ $key ]['rates'], $context );
			}
			$this->api = $this->api ?? new InstantDeliveryApiRepository();
			try {
				$response = $this->api->price( $context['pricing'] );
			} catch ( \Throwable $error ) {
				$this->trace( 'pricing_api_exception' );
				throw $error;
			}
			$rates = $this->rates( $response, $context['policy'] );
			if ( empty( $rates ) ) {
				return $this->failure( 'no_rates' );
			}
			$expires = time() + self::TTL;
			foreach ( $rates as &$rate ) {
				$rate['quote_token'] = bin2hex( random_bytes( 32 ) );
				$rate['expires'] = $expires;
			}
			unset( $rate );
			unset( $cache[ $key ] );
			$cache[ $key ] = array( 'amount_version' => self::AMOUNT_VERSION, 'context' => $context, 'rates' => $rates, 'expires' => $expires );
			while ( count( $cache ) > self::MAX_QUOTES ) {
				array_shift( $cache );
			}
			$session->set( self::SESSION_KEY, $cache );
			return $this->success( $rates, $context );
		} catch ( \Throwable $error ) {
			// Upstream exceptions may contain addresses, tokens or credentials.
			return $this->failure( 'quote_unavailable' );
		}
	}

	/** Rebuild current cart/address/policy without an API call; never echo a supplied token. */
	public function validate( string $token, string $courier, string $service, array $package, array $destination, string $payment, bool $insurance ): array {
		if ( ! preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) {
			throw new InvalidArgumentException( 'quote_invalid' );
		}
		try {
			$context = $this->build( $package, $destination, $payment, $insurance );
			$session = $this->session();
			$cache = null === $session ? array() : $this->cache( $session );
			$entry = $cache[ $context['fingerprint'] ] ?? null;
			if ( ! is_array( $entry ) || ! $this->compatible( $entry, $context ) ) {
				throw new InvalidArgumentException( 'quote_expired_or_changed' );
			}
			foreach ( $entry['rates'] as $rate ) {
				if ( hash_equals( $rate['quote_token'], $token ) && $courier === $rate['courier'] && $service === $rate['service']
					&& $this->settings->isCourierServiceEnabled( $courier, $service ) ) {
					return array( 'rate' => $rate, 'context' => $context );
				}
			}
			throw new InvalidArgumentException( 'quote_invalid' );
		} catch ( InvalidArgumentException $error ) {
			$reason = $this->reason( $error->getMessage() );
			throw new InvalidArgumentException( $reason );
		} catch ( \Throwable $error ) {
			throw new InvalidArgumentException( 'quote_invalid' );
		}
	}

	/** Explicit stored Instant services only. No legacy whitelist or guessed wildcard grants. */
	public function enabledInstant(): array {
		$selection = $this->settings->getCourierServiceSelection();
		$policy = array();
		foreach ( array( 'gosend', 'grab_express' ) as $courier ) {
			$services = $selection[ $courier ] ?? array();
			if ( ! is_array( $services ) ) {
				continue;
			}
			foreach ( $services as $service ) {
				if ( is_string( $service ) && preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/', $service ) && $this->settings->isCourierServiceEnabled( $courier, $service ) ) {
					$policy[ $courier ][] = $service;
				}
			}
			if ( isset( $policy[ $courier ] ) ) {
				$policy[ $courier ] = array_values( array_unique( $policy[ $courier ] ) );
				sort( $policy[ $courier ], SORT_STRING );
			}
		}
		return $policy;
	}

	private function build( array $package, array $destination, string $payment, bool $insurance ): array {
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IDR';
		if ( 'IDR' !== $currency ) {
			throw new InvalidArgumentException( 'currency_unsupported' );
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		$cart = $wc->cart ?? null;
		if ( is_object( $cart ) && is_callable( array( $cart, 'get_shipping_packages' ) ) ) {
			$packages = $cart->get_shipping_packages();
			if ( ! is_array( $packages ) || count( $packages ) > 1 ) {
				throw new InvalidArgumentException( 'packages_invalid' );
			}
		}
		if ( 'cod' === strtolower( trim( $payment ) ) ) {
			throw new InvalidArgumentException( 'cod_unsupported' );
		}
		// Express/global insurance preferences do not apply to Instant.
		$policy = $this->enabledInstant();
		if ( empty( $policy ) ) {
			throw new InvalidArgumentException( 'services_disabled' );
		}
		$credential = $this->settings->getSettingByKey( 'api_key' );
		if ( ! is_object( $credential ) || ! is_string( $credential->value ?? null ) || '' === trim( $credential->value ) ) {
			throw new InvalidArgumentException( 'account_unavailable' );
		}
		try {
			$destination = BuyerDestination::normalize( $destination );
			$current = BuyerDestination::address( $package['destination'] ?? null );
			if ( 2 !== $destination['version'] || '' === $destination['district_id'] || 'ID' !== $current['country']
				|| $current !== ( $destination['shipping_address'] ?? null ) || ! preg_match( '/\A[0-9]{5}\z/', $current['postcode'] )
				|| '' === $current['address_1'] || '' === $current['city'] || '' === $current['state'] ) {
				throw new InvalidArgumentException();
			}
		} catch ( \Throwable $error ) {
			throw new InvalidArgumentException( 'destination_invalid' );
		}
		$buyer = $package['destination'];
		$name = trim( $this->text( $buyer, array( 'first_name' ) ) . ' ' . $this->text( $buyer, array( 'last_name' ) ) );
		$phone = $this->text( $buyer, array( 'phone', 'shipping_phone' ) );
		$address = implode( ', ', array_filter( array( $current['address_1'], $current['address_2'], $current['city'], $current['state'], $current['postcode'] ), static fn( $value ) => '' !== $value ) );
		if ( ! $this->length( $name, 1, 255 ) || ! $this->phone( $phone ) || ! $this->length( $address, 20, 250 ) ) {
			throw new InvalidArgumentException( 'recipient_invalid' );
		}
		$recipient = array( 'name' => $name, 'first_name' => $this->text( $buyer, array( 'first_name' ) ), 'last_name' => $this->text( $buyer, array( 'last_name' ) ), 'phone' => $phone, 'address' => $address,
			'latitude' => (float) $destination['destination_latitude'], 'longitude' => (float) $destination['destination_longitude'], 'zipcode' => $current['postcode'] );

		// A supplied snapshot is authoritative, even when empty or malformed.
		$source = array_key_exists( 'origin', $package ) ? $package['origin'] : $this->locations->locationToOrigin( $this->locations->getDefaultLocation() );
		if ( ! is_array( $source ) || empty( $source ) ) {
			throw new InvalidArgumentException( 'origin_invalid' );
		}
		$country = $this->text( $source, array( 'origin_country', 'country' ) );
		if ( '' !== $country && 'ID' !== strtoupper( $country ) ) {
			throw new InvalidArgumentException( 'origin_invalid' );
		}
		$origin = array(
			'location_id' => $this->integer( $source['location_id'] ?? $source['id'] ?? 0, 0, 'origin_invalid' ),
			'name' => $this->text( $source, array( 'origin_name', 'location_name', 'name' ) ),
			'phone' => $this->text( $source, array( 'origin_phone', 'phone' ) ),
			'address' => $this->text( $source, array( 'origin_address', 'address', 'address_1' ) ),
			'zipcode' => $this->text( $source, array( 'origin_zip_code', 'zip_code', 'postcode', 'zipcode' ) ),
		);
		foreach ( array( array( 'origin_address_2', 'address_2' ), array( 'origin_city', 'city' ), array( 'origin_state', 'state' ) ) as $keys ) {
			$part = $this->text( $source, $keys );
			if ( '' !== $part ) {
				$origin['address'] .= ', ' . $part;
			}
		}
		try {
			$lat = BuyerDestination::coordinate( $source['origin_latitude'] ?? $source['latitude'] ?? null, 90 );
			$long = BuyerDestination::coordinate( $source['origin_longitude'] ?? $source['longitude'] ?? null, 180 );
			if ( '' === $lat || '' === $long ) {
				throw new InvalidArgumentException();
			}
			$origin['latitude'] = (float) $lat;
			$origin['longitude'] = (float) $long;
		} catch ( \Throwable $error ) {
			throw new InvalidArgumentException( 'origin_invalid' );
		}
		if ( ! $this->length( $origin['name'], 10, 40 ) || ! $this->phone( $origin['phone'] ) || ! $this->length( $origin['address'], 20, 250 ) || ! preg_match( '/\A[0-9]{5}\z/', $origin['zipcode'] ) ) {
			throw new InvalidArgumentException( 'origin_invalid' );
		}
		if ( ! InstantDeliveryCoverage::covers( $origin['latitude'], $origin['longitude'], $recipient['latitude'], $recipient['longitude'] ) ) {
			throw new InvalidArgumentException( 'outside_instant_radius' );
		}
		$timezone = self::normalizeTimezone( $source );
		$origin['timezone'] = $timezone;
		$origin['country'] = $country;
		$items = array();
		$weight = 0;
		$value = 0;
		$contents = $package['contents'] ?? null;
		if ( ! is_array( $contents ) ) {
			throw new InvalidArgumentException( 'items_invalid' );
		}
		foreach ( $contents as $key => $row ) {
			if ( ! is_array( $row ) || ! is_object( $row['data'] ?? null ) || ! method_exists( $row['data'], 'needs_shipping' ) ) {
				throw new InvalidArgumentException( 'items_invalid' );
			}
			$product = $row['data'];
			if ( ! $product->needs_shipping() ) {
				continue;
			}
			$qty = $this->integer( $row['quantity'] ?? null, 1 );
			$grams = $this->unit( wc_get_weight( $product->get_weight(), 'g' ) );
			$total = $row['line_total'] ?? null;
			if ( is_bool( $total ) || ! is_numeric( $total ) || ! is_finite( (float) $total ) || (float) $total < 0 || (float) $total >= PHP_INT_MAX ) {
				throw new InvalidArgumentException( 'items_invalid' );
			}
			$raw_total = (float) $total;
			$total = $this->integer( round( $raw_total ), 0 );
			$price = $this->integer( round( $total / $qty ), 0 );
			$item_name = $this->text( array( 'name' => $product->get_name() ), array( 'name' ) );
			if ( '' === $item_name || $grams > 40000 || $qty > intdiv( 40000, $grams ) || $weight > 40000 - $grams * $qty || $value > PHP_INT_MAX - $total ) {
				throw new InvalidArgumentException( 'items_invalid' );
			}
			$items[] = array( 'cart_key' => (string) $key, 'product_id' => $this->integer( $row['product_id'] ?? $product->get_id(), 1 ), 'variation_id' => $this->integer( $row['variation_id'] ?? 0, 0 ),
				'name' => $item_name, 'description' => $item_name, 'qty' => $qty, 'price' => $price, 'line_total' => $total, 'weight' => $grams,
				'width' => $this->unit( wc_get_dimension( $product->get_width(), 'cm' ) ), 'length' => $this->unit( wc_get_dimension( $product->get_length(), 'cm' ) ), 'height' => $this->unit( wc_get_dimension( $product->get_height(), 'cm' ) ),
				'measurements' => array( 'weight' => (float) wc_get_weight( $product->get_weight(), 'g' ), 'width' => (float) wc_get_dimension( $product->get_width(), 'cm' ), 'length' => (float) wc_get_dimension( $product->get_length(), 'cm' ), 'height' => (float) wc_get_dimension( $product->get_height(), 'cm' ), 'line_total' => $raw_total ),
				'metadata' => array( 'sku' => (string) $product->get_sku() ) );
			$weight += $grams * $qty;
			$value += $total;
		}
		if ( empty( $items ) ) {
			throw new InvalidArgumentException( 'items_invalid' );
		}
		usort( $items, static fn( $a, $b ) => strcmp( $a['cart_key'], $b['cart_key'] ) );
		$pricing = array( 'origin' => array( 'lat' => $origin['latitude'], 'long' => $origin['longitude'], 'address' => $origin['address'] ),
			'destination' => array( 'lat' => $recipient['latitude'], 'long' => $recipient['longitude'], 'address' => $recipient['address'] ),
			'service' => array_keys( $policy ), 'item_price' => $value, 'weight' => $weight, 'vehicle' => 'motor', 'timezone' => $timezone );
		$package_id = $package['package_id'] ?? 0;
		if ( ! is_string( $package_id ) && ! is_int( $package_id ) ) {
			throw new InvalidArgumentException( 'items_invalid' );
		}
		$context = array( 'amount_version' => self::AMOUNT_VERSION, 'currency' => $currency, 'coverage_version' => 1, 'package_id' => (string) $package_id, 'origin' => $origin, 'destination' => $destination, 'recipient' => $recipient, 'items' => $items,
			'weight' => $weight, 'item_value' => $value, 'package_type_id' => $this->integer( $package['package_type_id'] ?? 7, 1 ), 'pricing' => $pricing, 'policy' => $policy, 'payment_method' => $payment, 'insurance' => false );
		$encoded = wp_json_encode( $this->canonical( $context ) );
		if ( false === $encoded ) {
			throw new InvalidArgumentException( 'context_unavailable' );
		}
		// Credential changes invalidate quotes, without retaining or exposing the key.
		$context['fingerprint'] = hash( 'sha256', $encoded . hash( 'sha256', $credential->value ) );
		return $context;
	}

	private function rates( array $response, array $policy ): array {
		if ( true !== ( $response['status'] ?? false ) ) {
			$this->trace( 'pricing_unavailable' );
			return array();
		}
		$data = (array) ( $response['data'] ?? array() );
		if ( array_key_exists( 'status', $data ) && true !== $data['status'] ) {
			$this->trace( 'pricing_unavailable' );
			return array();
		}
		$rows = $data['result'] ?? $data['results'] ?? null;
		if ( is_object( $rows ) || is_array( $rows ) ) {
			$envelope = (array) $rows;
			$rows = $envelope['result'] ?? $envelope['results'] ?? $rows;
		}
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
			$this->trace( 'pricing_unavailable' );
			return array();
		}
		$rates = array();
		$seen = array();
		$duplicates = array();
		$invalid = false;
		foreach ( $rows as $row ) {
			$row = (array) $row;
			$courier = $row['name'] ?? null;
			if ( ! is_string( $courier ) || ! isset( $policy[ $courier ] ) || ( isset( $row['vehicle'] ) && 'motor' !== $row['vehicle'] ) || ( isset( $row['type'] ) && 'instant' !== $row['type'] ) ) {
				continue;
			}
			$costs = $row['costs'] ?? null;
			if ( ! is_array( $costs ) || ! array_is_list( $costs ) ) {
				$invalid = true;
				continue;
			}
			foreach ( $costs as $cost ) {
				$cost = (array) $cost;
				$service = $cost['service_type'] ?? null;
				if ( ! is_string( $service ) || ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/', $service ) || ! $this->settings->isCourierServiceEnabled( $courier, $service ) ) {
					continue;
				}
				$key = $courier . ':' . strtolower( $service );
				if ( isset( $seen[ $key ] ) ) {
					$invalid = true;
				$duplicates[ $key ] = true;
					unset( $rates[ $key ] );
					continue;
				}
				$seen[ $key ] = true;
				if ( isset( $cost['vehicle'] ) && 'motor' !== $cost['vehicle'] ) {
					continue;
				}
				$price = (array) ( $cost['price'] ?? array() );
				$rate = array( 'courier' => $courier, 'service' => $service, 'label' => $this->label( $courier, $service ),
					'cost' => $price['total_price'] ?? null, 'shipping_costs' => $price['shipping_costs'] ?? null,
					'admin_fee' => $price['admin_fee'] ?? null, 'total_price' => $price['total_price'] ?? null,
					'estimation' => $cost['estimation'] ?? null, 'vehicle' => 'motor' );
				if ( ! self::validRateAmounts( $rate ) ) {
					$invalid = true;
					continue;
				}
				// Preserve the upstream hour estimate; never reinterpret it as days.
				$rate['estimation'] = sanitize_text_field( $rate['estimation'] );
				$rates[ $key ] = $rate;
			}
		}
		$rates = array_values( array_diff_key( $rates, $duplicates ) );
		if ( $invalid && empty( $rates ) ) {
			$this->trace( 'pricing_unavailable' );
		}
		return $rates;
	}

	/** Strict API amounts, also required for cached quotes and durable receipts. */
	public static function validRateAmounts( array $rate ): bool {
		foreach ( array( 'cost', 'shipping_costs', 'admin_fee', 'total_price' ) as $field ) {
			if ( ! is_int( $rate[ $field ] ?? null ) || $rate[ $field ] < 0 ) {
				return false;
			}
		}
		if ( $rate['shipping_costs'] > PHP_INT_MAX - $rate['admin_fee']
			|| $rate['total_price'] !== $rate['shipping_costs'] + $rate['admin_fee'] || $rate['cost'] !== $rate['total_price'] ) {
			return false;
		}
		$eta = $rate['estimation'] ?? null;
		if ( ! is_string( $eta ) || '' === trim( $eta ) || preg_match( '/[<>\\x00-\\x1f\\x7f]/', $eta ) ) {
			return false;
		}
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $eta ) : strlen( $eta );
		return $length <= 80 && '' !== sanitize_text_field( $eta );
	}

	private function label( string $courier, string $service ): string {
		// Display aliases only: entitlement is always checked with the actual API code.
		$labels = array( 'instant' => 'Instant', 'sameday' => 'Same Day', 'go-instant' => 'Instant', 'go-sameday' => 'Same Day', 'grab-bike' => 'Instant', 'grab-instant' => 'Instant', 'grab-sameday' => 'Same Day' );
		return ( 'gosend' === $courier ? 'GoSend' : 'GrabExpress' ) . ' ' . ( $labels[ strtolower( $service ) ] ?? $service );
	}

	private function session() {
		$wc = function_exists( 'WC' ) ? WC() : null;
		return is_object( $wc ) && is_object( $wc->session ?? null ) && method_exists( $wc->session, 'get' ) && method_exists( $wc->session, 'set' ) ? $wc->session : null;
	}

	private function cache( $session ): array {
		$cache = $session->get( self::SESSION_KEY, array() );
		if ( ! is_array( $cache ) ) {
			return array();
		}
		return array_filter( $cache, static fn( $entry ) => is_array( $entry ) && is_int( $entry['expires'] ?? null ) && $entry['expires'] > time() && $entry['expires'] <= time() + self::TTL );
	}

	private function compatible( array $entry, array $context ): bool {
		if ( ( $entry['amount_version'] ?? null ) !== self::AMOUNT_VERSION || ( $entry['context'] ?? null ) !== $context || ! is_int( $entry['expires'] ?? null ) || $entry['expires'] <= time() || ! is_array( $entry['rates'] ?? null ) || empty( $entry['rates'] ) ) {
			return false;
		}
		foreach ( $entry['rates'] as $rate ) {
			if ( ! is_array( $rate ) || ! is_string( $rate['quote_token'] ?? null ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $rate['quote_token'] )
				|| ! self::validRateAmounts( $rate ) || 'motor' !== ( $rate['vehicle'] ?? null ) || ( $rate['expires'] ?? null ) !== $entry['expires']
				|| ! is_string( $rate['courier'] ?? null ) || ! isset( $context['policy'][ $rate['courier'] ] ) || ! is_string( $rate['service'] ?? null ) || ! $this->settings->isCourierServiceEnabled( $rate['courier'], $rate['service'] ) ) {
				return false;
			}
		}
		return true;
	}

	private function text( array $source, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $source ) ) {
				if ( ! is_string( $source[ $key ] ) || preg_match( '/[<>\x00-\x1f\x7f]/', $source[ $key ] ) ) {
					throw new InvalidArgumentException( 'context_invalid' );
				}
				return trim( $source[ $key ] );
			}
		}
		return '';
	}

	private function integer( $value, int $minimum, string $reason = 'items_invalid' ): int {
		if ( is_bool( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < $minimum || (float) $value >= PHP_INT_MAX || floor( (float) $value ) !== (float) $value ) {
			throw new InvalidArgumentException( $reason );
		}
		return (int) $value;
	}

	private function unit( $value ): int {
		if ( is_bool( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value <= 0 ) {
			throw new InvalidArgumentException( 'items_invalid' );
		}
		return $this->integer( ceil( (float) $value ), 1 );
	}

	private function phone( string $value ): bool {
		return $this->length( $value, 8, 14 ) && 1 === preg_match( '/\A(?:08|62)[0-9]+\z/', $value );
	}

	private function length( string $value, int $min, int $max ): bool {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		return $length >= $min && $length <= $max;
	}

	private function canonical( array $data ): array {
		if ( ! array_is_list( $data ) ) {
			ksort( $data );
		}
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->canonical( $value );
			}
		}
		return $data;
	}

	private function success( array $rates, array $context ): array {
		return array( 'eligible' => true, 'code' => 'ok', 'message' => '', 'rates' => $rates, 'context' => $context );
	}

	private function failure( string $reason ): array {
		$reason = $this->reason( $reason );
		$messages = array(
			'currency_unsupported' => __( 'Instant delivery requires Indonesian rupiah (IDR).', 'kiriminaja-official' ),
			'packages_invalid' => __( 'Instant delivery supports only one shipping package.', 'kiriminaja-official' ),
			'outside_instant_radius' => __( 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.', 'kiriminaja-official' ),
			'cod_unsupported' => __( 'Cash on delivery is not supported for Instant delivery.', 'kiriminaja-official' ),
			'insurance_unsupported' => __( 'Insurance is not supported for Instant delivery.', 'kiriminaja-official' ),
			'services_disabled' => __( 'No Instant courier services are enabled.', 'kiriminaja-official' ),
			'account_unavailable' => __( 'Instant delivery is not available for this store.', 'kiriminaja-official' ),
			'destination_invalid' => __( 'Complete your Indonesian shipping address and update its map pin for Instant delivery.', 'kiriminaja-official' ),
			'recipient_invalid' => __( 'A valid recipient name, Indonesian phone number and full address are required for Instant delivery.', 'kiriminaja-official' ),
			'origin_invalid' => __( 'The store pickup address is not ready for Instant delivery.', 'kiriminaja-official' ),
			'timezone_unsupported' => __( 'A supported Indonesian timezone is required for Instant delivery.', 'kiriminaja-official' ),
			'items_invalid' => __( 'Instant delivery requires physical items with valid quantities, weights and dimensions, weighing at most 40000 grams.', 'kiriminaja-official' ),
			'no_rates' => __( 'No Instant delivery rates are available for this address and cart.', 'kiriminaja-official' ),
		);
		return array( 'eligible' => false, 'code' => $reason, 'message' => $messages[ $reason ] ?? __( 'Instant delivery could not be quoted. Please try again.', 'kiriminaja-official' ), 'rates' => array(), 'context' => null );
	}

	/** Log only actionable pricing failures, without payloads, identifiers or traces. */
	private function trace( string $code ): void {
		if ( ! in_array( $code, array( 'pricing_api_exception', 'pricing_unavailable' ), true ) || ! function_exists( 'kiriof_log' ) ) {
			return;
		}
		try {
			kiriof_log( 'warning', 'Instant checkout pricing failed.', array( 'code' => $code, 'backtrace' => false ), 'kiriminaja_instant' );
		} catch ( \Throwable $error ) {
			// Observability must not interrupt checkout.
		}
	}

	/** Only internal reason codes may cross the buyer boundary, even for repository exceptions. */
	private function reason( string $reason ): string {
		$allowed = array( 'currency_unsupported', 'packages_invalid', 'outside_instant_radius', 'cod_unsupported', 'insurance_unsupported', 'services_disabled', 'account_unavailable', 'destination_invalid', 'recipient_invalid', 'origin_invalid', 'timezone_unsupported', 'items_invalid', 'no_rates', 'context_invalid', 'context_unavailable', 'session_unavailable', 'quote_unavailable', 'quote_invalid', 'quote_expired_or_changed' );
		return in_array( $reason, $allowed, true ) ? $reason : 'context_unavailable';
	}
}
