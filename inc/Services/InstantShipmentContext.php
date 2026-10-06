<?php
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\TransactionProcessServices\RecipientDataResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validates a persisted Instant order without geocoding or booking a shipment. */
class InstantShipmentContext {
	private ShipmentLocationService $locations;
	private RecipientDataResolver $recipients;
	private SettingRepository $settings;

	public function __construct( ?ShipmentLocationService $locations = null, ?RecipientDataResolver $recipients = null, ?SettingRepository $settings = null ) {
		$this->locations  = $locations ?? new ShipmentLocationService();
		$this->recipients = $recipients ?? new RecipientDataResolver();
		$this->settings   = $settings ?? new SettingRepository();
	}

	/** Cheap UI guard only; build() performs all order and address validation. */
	public static function canProcess( $row ): bool {
		if ( is_array( $row ) ) {
			$row = (object) $row;
		}
		if ( ! is_object( $row ) || 'instant' !== TransactionDeliveryType::resolve( $row )
			|| ! in_array( strtolower( trim( (string) ( $row->service ?? '' ) ) ), array( 'gosend', 'grab_express' ), true )
			|| 'new' !== ( $row->status ?? null ) ) {
			return false;
		}
		foreach ( array( 'awb', 'payment_id', 'instant_payment_id', 'instant_status_code' ) as $field ) {
			// Zero is an existing remote status code too, not an empty value.
			if ( isset( $row->$field ) && '' !== (string) $row->$field ) {
				return false;
			}
		}
		return true;
	}

	/** @return array{origin:array,package:array,pricing:array,fingerprint:string} */
	public function build( object $transaction ): array {
		if ( ! self::canProcess( $transaction ) ) {
			throw new InvalidArgumentException( esc_html__( 'This Instant shipment cannot be processed or has already been booked.', 'kiriminaja-official' ) );
		}
		$order_id = $transaction->order_id ?? null;
		if ( ! is_string( $order_id ) || '' === trim( $order_id ) || trim( $order_id ) !== $order_id || preg_match( '/[\x00-\x20\x7f]/', $order_id ) ) {
			throw new InvalidArgumentException( esc_html__( 'The shipment order ID is invalid.', 'kiriminaja-official' ) );
		}
		$wc_id = $transaction->wp_wc_order_stat_order_id ?? null;
		if ( ! is_scalar( $wc_id ) || ! ctype_digit( (string) $wc_id ) || (int) $wc_id < 1 ) {
			throw new InvalidArgumentException( esc_html__( 'The WooCommerce order is unavailable.', 'kiriminaja-official' ) );
		}
		$order = wc_get_order( (int) $wc_id );
		if ( ! $order ) {
			throw new InvalidArgumentException( esc_html__( 'The WooCommerce order is unavailable.', 'kiriminaja-official' ) );
		}
		if ( in_array( $order->get_status(), array( 'cancelled', 'completed', 'refunded', 'failed', 'trash' ), true ) || ( ! $order->is_paid() && 'processing' !== $order->get_status() ) ) {
			throw new InvalidArgumentException( esc_html__( 'The WooCommerce order must be paid or processing and not closed.', 'kiriminaja-official' ) );
		}
		if ( 'cod' === strtolower( (string) $order->get_payment_method() ) || (float) ( $transaction->cod ?? 0 ) > 0 || (float) ( $transaction->cod_fee ?? 0 ) > 0 || in_array( $transaction->is_cod ?? null, array( true, 1, '1', 'yes' ), true ) || in_array( strtolower( (string) ( $transaction->payment_method ?? '' ) ), array( 'cod' ), true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Cash on delivery is not supported for Instant shipments.', 'kiriminaja-official' ) );
		}
		if ( (float) ( $transaction->insurance_cost ?? 0 ) > 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Insurance is not supported for Instant shipments.', 'kiriminaja-official' ) );
		}
		$courier = strtolower( trim( (string) $transaction->service ) );
		$service = $transaction->service_name ?? null;
		if ( ! is_string( $service ) || '' === trim( $service ) || ! $this->settings->isCourierServiceEnabled( $courier, $service ) ) {
			throw new InvalidArgumentException( esc_html__( 'The selected Instant courier service is unavailable.', 'kiriminaja-official' ) );
		}
		if ( 'motor' !== TransactionDeliveryType::normalizeVehicle( $transaction->vehicle ?? null ) ) {
			throw new InvalidArgumentException( esc_html__( 'Only motor delivery is supported for Instant shipments.', 'kiriminaja-official' ) );
		}

		// A present snapshot is authoritative, including missing/invalid fields. Never
		// repair a historical pin by overlaying the current default location.
		$raw_snapshot = $transaction->shipment_location_snapshot ?? null;
		if ( null !== $raw_snapshot && '' !== $raw_snapshot ) {
			$source = is_string( $raw_snapshot ) ? json_decode( $raw_snapshot, true ) : null;
			if ( ! is_array( $source ) || empty( $source ) ) {
				throw new InvalidArgumentException( esc_html__( 'The saved shipment origin is incomplete. Configure its address and coordinates before processing.', 'kiriminaja-official' ) );
			}
		} else {
			$source = $this->locations->originForLocation( (int) ( $transaction->shipment_location_id ?? 0 ) );
			if ( empty( $source ) ) {
				$source = array();
				foreach ( (array) $this->settings->getOriginData() as $setting ) {
					$source[ $setting->key ] = $setting->value;
				}
			}
		}
		$note_keys = array( 'origin_address_note', 'address_note', 'origin_address_2', 'address_2' );
		foreach ( $note_keys as $key ) {
			if ( isset( $source[ $key ] ) && ! is_scalar( $source[ $key ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'The shipment origin name, phone, address or postcode is invalid.', 'kiriminaja-official' ) );
			}
		}
		$origin = array(
			'name'      => $this->value( $source, array( 'origin_name', 'location_name', 'name' ) ),
			'phone'     => $this->value( $source, array( 'origin_phone', 'phone' ) ),
			'address'   => $this->value( $source, array( 'origin_address', 'address', 'address_1' ) ),
			'zipcode'   => $this->value( $source, array( 'origin_zip_code', 'zip_code', 'postcode', 'zipcode' ) ),
			'latitude'  => $this->coordinate( $this->value( $source, array( 'origin_latitude', 'latitude', 'lat' ) ), 90 ),
			'longitude' => $this->coordinate( $this->value( $source, array( 'origin_longitude', 'longitude', 'long' ) ), 180 ),
		);
		$address_parts = array( $origin['address'] );
		foreach ( array( array( 'origin_address_2', 'address_2' ), array( 'origin_city', 'city' ), array( 'origin_state', 'state' ) ) as $keys ) {
			$part = $this->value( $source, $keys );
			if ( '' !== $part ) {
				$address_parts[] = $part;
			}
		}
		$origin['address'] = implode( ', ', $address_parts );
		$origin['address_note'] = $this->value( $source, $note_keys );
		if ( '' === $origin['address_note'] ) {
			$origin['address_note'] = $origin['address'];
		}
		// Bounds from the official v6.2 Instant OpenAPI, not Express limits.
		if ( ! $this->lengthBetween( $origin['name'], 10, 40 ) || ! $this->validPhone( $origin['phone'] ) || ! $this->lengthBetween( $origin['address'], 20, 250 ) || '' === $origin['zipcode'] ) {
			throw new InvalidArgumentException( esc_html__( 'The shipment origin name, phone, address or postcode is invalid.', 'kiriminaja-official' ) );
		}
		$timezone = $this->value( $source, array( 'timezone', 'origin_timezone' ) );
		if ( '' === $timezone ) {
			$zones    = array( 'Asia/Jakarta' => 'WIB', 'Asia/Pontianak' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT' );
			$timezone = $zones[ wp_timezone_string() ] ?? '';
		}
		if ( ! in_array( $timezone, array( 'WIB', 'WITA', 'WIT' ), true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Configure a supported Indonesian timezone for Instant delivery.', 'kiriminaja-official' ) );
		}

		$raw_shipping_info = $transaction->shipping_info ?? null;
		$shipping_info = is_string( $raw_shipping_info ) ? json_decode( $raw_shipping_info ) : null;
		if ( ! is_object( $shipping_info ) ) {
			throw new InvalidArgumentException( esc_html__( 'The saved recipient address is incomplete. Update the destination coordinates before processing.', 'kiriminaja-official' ) );
		}
		// Reject malformed address values before the legacy resolver casts them.
		foreach ( array( 'first_name', 'last_name', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
			foreach ( array( '_shipping_' . $field, 'shipping_' . $field, '_billing_' . $field, 'billing_' . $field, $field ) as $key ) {
				if ( property_exists( $shipping_info, $key ) && ! is_scalar( $shipping_info->$key ) ) {
					throw new InvalidArgumentException( esc_html__( 'The saved recipient address is incomplete. Update the destination coordinates before processing.', 'kiriminaja-official' ) );
				}
			}
		}
		$current       = $this->recipients->resolve( $order, null, $transaction );
		// Resolve the checkout snapshot separately: don't let resolver fallback hide
		// an intentionally cleared current address_2 and reuse an obsolete pin.
		if ( is_object( $shipping_info ) ) {
			foreach ( array( 'latitude', 'longitude' ) as $axis ) {
				$field = 'destination_' . $axis;
				foreach ( array( $field, '_kiriof_' . $field, 'kiriof_' . $field ) as $key ) {
					if ( property_exists( $shipping_info, $key ) && $this->coordinate( $shipping_info->$key, 'latitude' === $axis ? 90 : 180 ) !== $this->coordinate( $transaction->$field ?? null, 'latitude' === $axis ? 90 : 180 ) ) {
						throw new InvalidArgumentException( esc_html__( 'The saved destination coordinates do not match this shipment.', 'kiriminaja-official' ) );
					}
				}
			}
			$saved = $this->recipients->resolve( null, $shipping_info, $transaction );
			if ( '' === $saved['address_1'] ) {
				throw new InvalidArgumentException( esc_html__( 'The saved recipient address is incomplete. Update the destination coordinates before processing.', 'kiriminaja-official' ) );
			}
			$address = (array) $order->get_address( 'shipping' );
			if ( '' === trim( (string) ( $address['address_1'] ?? '' ) ) ) {
				$address = (array) $order->get_address( 'billing' );
			}
			if ( 'ID' !== trim( (string) ( $address['country'] ?? '' ) ) ) {
				throw new InvalidArgumentException( esc_html__( 'Instant delivery requires a current recipient address in Indonesia.', 'kiriminaja-official' ) );
			}
			foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
				$has_snapshot = false;
				foreach ( array( '_shipping_' . $field, 'shipping_' . $field, '_billing_' . $field, 'billing_' . $field, $field ) as $key ) {
					$has_snapshot = $has_snapshot || property_exists( $shipping_info, $key );
				}
				if ( $has_snapshot && trim( (string) ( $address[ $field ] ?? $current[ $field ] ) ) !== $saved[ $field ] ) {
					throw new InvalidArgumentException( esc_html__( 'The recipient address has changed. Update the destination coordinates before processing.', 'kiriminaja-official' ) );
				}
			}
		}
		$name = trim( $current['first_name'] . ' ' . $current['last_name'] );
		if ( '' === $name || ! $this->validPhone( $current['phone'] ) || '' === $current['address_1'] || '' === $current['city'] || '' === $current['postcode'] ) {
			throw new InvalidArgumentException( esc_html__( 'The current recipient name, phone, street, city and postcode are required.', 'kiriminaja-official' ) );
		}
		$destination = array(
			'name'      => $name,
			'phone'     => $current['phone'],
			'latitude'  => $this->coordinate( $transaction->destination_latitude ?? null, 90 ),
			'longitude' => $this->coordinate( $transaction->destination_longitude ?? null, 180 ),
			'address'   => implode( ', ', array_filter( array( $current['address_1'], $current['address_2'], $current['city'], $current['state'], $current['postcode'] ), static fn( $value ) => '' !== $value ) ),
		);
		// Use the current address line, not an order comment or a saved fallback.
		$destination['address_note'] = $this->value( $address, array( 'address_2' ) );
		if ( '' === $destination['address_note'] ) {
			$destination['address_note'] = $destination['address'];
		}

		if ( ! InstantDeliveryCoverage::covers( $origin['latitude'], $origin['longitude'], $destination['latitude'], $destination['longitude'] ) ) {
			throw new InvalidArgumentException( esc_html__( 'Instant delivery is available only within 40 km of the pickup origin. You can use Express delivery for this address.', 'kiriminaja-official' ) );
		}

		$items = array();
		$weight = 0;
		$value = 0;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				throw new InvalidArgumentException( esc_html__( 'An order product is unavailable.', 'kiriminaja-official' ) );
			}
			if ( $product->is_virtual() ) {
				continue;
			}
			$qty   = $this->integer( $item->get_quantity(), 1 );
			$grams = $this->positiveUnit( wc_get_weight( $product->get_weight(), 'g' ) );
			$total = $item->get_total();
			if ( ! is_numeric( $total ) || ! is_finite( (float) $total ) ) {
				throw new InvalidArgumentException( esc_html__( 'An order item value is invalid.', 'kiriminaja-official' ) );
			}
			$price = $this->integer( round( max( 0, (float) $total ) / $qty ), 0 );
			$item_name = trim( (string) $item->get_name() );
			if ( '' === $item_name ) {
				throw new InvalidArgumentException( esc_html__( 'An order item name is required.', 'kiriminaja-official' ) );
			}
			$items[] = array(
				'name'        => $item_name,
				'description' => $item_name,
				'price'       => $price,
				'weight'      => $grams,
				'qty'         => $qty,
				'width'       => $this->positiveUnit( wc_get_dimension( $product->get_width(), 'cm' ) ),
				'height'      => $this->positiveUnit( wc_get_dimension( $product->get_height(), 'cm' ) ),
				'length'      => $this->positiveUnit( wc_get_dimension( $product->get_length(), 'cm' ) ),
				'metadata'    => array( 'sku' => (string) $product->get_sku() ),
			);
			$weight += $grams * $qty;
			$value  += $price * $qty;
		}
		if ( empty( $items ) || $weight > 40000 || $value > PHP_INT_MAX ) {
			throw new InvalidArgumentException( esc_html__( 'Instant shipments require physical items with a total weight of at most 40000 grams.', 'kiriminaja-official' ) );
		}
		// Resolve package category based on products, defaulting to Lain-lain (7).
		$default_type_id = PackageTypeService::resolveForOrder( $order );
		$type_id         = $this->integer( apply_filters( 'kiriof_instant_package_type_id', $default_type_id, $transaction, $order ), 1 );
		$package = array(
			'order_id'        => $order_id,
			'destination'     => $destination,
			'shipping_cost'   => $this->integer( $transaction->shipping_cost ?? null, 0 ),
			'service'         => $courier,
			'service_type'    => $service,
			'package_type_id' => $type_id,
			'vehicle'         => 'motor',
			'description'     => 'Order ' . $order_id,
			'items'           => $items,
		);
		$pricing = array(
			'origin'      => array( 'lat' => $origin['latitude'], 'long' => $origin['longitude'], 'address' => $origin['address'] ),
			'destination' => array( 'lat' => $destination['latitude'], 'long' => $destination['longitude'], 'address' => $destination['address'] ),
			'service'     => array( $courier ),
			'item_price'  => $value,
			'weight'      => $weight,
			'vehicle'     => 'motor',
			'timezone'    => $timezone,
		);
		$context = array( 'coverage_version' => 1, 'origin' => $origin, 'package' => $package, 'pricing' => $pricing );
		$encoded = wp_json_encode( $this->canonical( $context ) );
		if ( false === $encoded ) {
			throw new InvalidArgumentException( esc_html__( 'The Instant shipment context could not be encoded.', 'kiriminaja-official' ) );
		}
		$context['fingerprint'] = hash( 'sha256', $encoded );
		return $context;
	}

	private function value( array $source, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== trim( (string) $source[ $key ] ) ) {
				return trim( (string) $source[ $key ] );
			}
		}
		return '';
	}

	private function coordinate( $value, int $limit ): float {
		try {
			$coordinate = BuyerDestination::coordinate( $value, $limit );
			if ( '' === $coordinate ) {
				throw new InvalidArgumentException();
			}
			return (float) $coordinate;
		} catch ( InvalidArgumentException $error ) {
			throw new InvalidArgumentException( esc_html__( 'Valid origin and destination coordinates are required for Instant delivery.', 'kiriminaja-official' ) );
		}
	}

	private function integer( $value, int $minimum ): int {
		if ( is_bool( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < $minimum || (float) $value >= PHP_INT_MAX || floor( (float) $value ) !== (float) $value ) {
			throw new InvalidArgumentException( esc_html__( 'An Instant shipment quantity, price or package type is invalid.', 'kiriminaja-official' ) );
		}
		return (int) $value;
	}

	private function positiveUnit( $value ): int {
		if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value <= 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Physical products require positive weight and dimensions for Instant delivery.', 'kiriminaja-official' ) );
		}
		// Integer grams/centimeters, rounded up so a sub-unit item is not lost.
		return $this->integer( ceil( (float) $value ), 1 );
	}

	private function lengthBetween( string $value, int $minimum, int $maximum ): bool {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		return $length >= $minimum && $length <= $maximum;
	}

	private function validPhone( string $phone ): bool {
		return $this->lengthBetween( $phone, 8, 14 ) && 1 === preg_match( '/^(?:08|62)\d+$/', $phone );
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
}
