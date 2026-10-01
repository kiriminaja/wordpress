<?php
namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Services\BuyerDestination;
use KiriminAjaOfficial\Services\InstantCheckoutQuoteService;
use KiriminAjaOfficial\Services\ShipmentLocationService;
use KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Final checkout validation only: shipment booking remains an explicit admin action. */
class InstantCheckoutController {
	public const SNAPSHOT_META_KEY = '_kiriof_instant_checkout_snapshot';
	private SettingRepository $settings;
	private TransactionRepository $transactions;
	private InstantCheckoutQuoteService $quotes;
	private GenerateOrderId $generator;

	public function __construct( SettingRepository $settings, TransactionRepository $transactions, ?InstantCheckoutQuoteService $quotes = null, ?GenerateOrderId $generator = null ) {
		$this->settings = $settings;
		$this->transactions = $transactions;
		$this->quotes = $quotes ?? new InstantCheckoutQuoteService( $settings, new ShipmentLocationService( null, $settings ) );
		$this->generator = $generator ?? new GenerateOrderId( $settings, $transactions );
	}

	public function register(): void {
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'afterStoreApiCheckoutUpdateOrderFromRequest' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'afterStoreApiCheckoutOrderProcessed' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'afterCheckoutBeforeCreated' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'afterCheckoutAfterCreated' ), 20, 3 );
	}

	/** Exact method identity, never a prefix match on an Express method. */
	private function shipping( $order ) {
		$lines = $order->get_items( 'shipping' );
		$instant = array_filter( $lines, static fn( $line ) => 'kiriminaja-instant' === $line->get_method_id() );
		if ( empty( $instant ) ) {
			return null;
		}
		if ( 1 !== count( $instant ) || 1 !== count( $lines ) ) {
			throw new \InvalidArgumentException( 'shipping_invalid' );
		}
		return reset( $instant );
	}

	public function afterStoreApiCheckoutUpdateOrderFromRequest( $order, $request ): void {
		$this->validateOrder( $order, $request, false );
	}

	public function afterCheckoutBeforeCreated( $order, $data ): void {
		$this->validateOrder( $order, null, true );
	}

	private function validateOrder( $order, $request, bool $classic ): void {
		try {
			$line = $this->shipping( $order );
			if ( null === $line ) {
				return;
			}
			$wc = WC();
			$packages = $wc->shipping()->get_packages();
			if ( ! is_array( $packages ) || 1 !== count( $packages ) ) {
				throw new \InvalidArgumentException( 'packages_invalid' );
			}
			$package = reset( $packages );
			$package['destination'] = is_array( $package['destination'] ?? null ) ? $package['destination'] : array();
			$fields = array_merge( BuyerDestination::ADDRESS_FIELDS, array( 'first_name', 'last_name', 'phone' ) );
			foreach ( $fields as $field ) {
				$getter = 'get_shipping_' . $field;
				if ( ! array_key_exists( $field, $package['destination'] ) && is_callable( array( $wc->customer ?? null, $getter ) ) ) {
					$package['destination'][ $field ] = $wc->customer->$getter();
				}
			}
			if ( $classic ) {
				$raw = $wc->session->get( 'kiriof_buyer_destination', null );
			} else {
				$extensions = $request->get_param( 'extensions' );
				$raw = $extensions['kiriminaja-official']['destination'] ?? null;
				$address = $request->get_param( 'shipping_address' );
				if ( ! is_array( $address ) ) {
					throw new \InvalidArgumentException( 'address_invalid' );
				}
				foreach ( $fields as $field ) {
					if ( array_key_exists( $field, $address ) ) {
						if ( ! is_string( $address[ $field ] ) ) {
							throw new \InvalidArgumentException( 'address_invalid' );
						}
						$setter = 'set_shipping_' . $field;
						$order->$setter( $address[ $field ] );
					}
				}
				$payment = $request->get_param( 'payment_method' );
				if ( is_string( $payment ) && '' !== $payment ) {
					$order->set_payment_method( $payment );
				}
			}
			$destination = BuyerDestination::normalize( $raw );
			$current = $this->orderAddress( $order );
			if ( 2 !== $destination['version'] || $destination['shipping_address'] !== BuyerDestination::address( $current ) || BuyerDestination::address( $package['destination'] ) !== BuyerDestination::address( $current ) ) {
				throw new \InvalidArgumentException( 'address_changed' );
			}
			foreach ( array( 'first_name', 'last_name', 'phone' ) as $field ) {
				if ( trim( (string) ( $package['destination'][ $field ] ?? '' ) ) !== trim( $current[ $field ] ) ) {
					throw new \InvalidArgumentException( 'recipient_changed' );
				}
			}
			$this->checkZone( $line, $package );
			$snapshot = $this->quotes->validate( (string) $line->get_meta( 'kiriof_instant_quote_token' ), (string) $line->get_meta( 'kiriof_instant_courier' ), (string) $line->get_meta( 'kiriof_instant_service' ), $package, $destination, $order->get_payment_method(), false );
			$this->checkSnapshot( $order, $line, $snapshot );
			$order->update_meta_data( self::SNAPSHOT_META_KEY, $snapshot );
			$order->update_meta_data( BuyerDestination::META_KEY, $destination );
			$order->update_meta_data( BuyerDestination::COORDINATE_META_KEY, array( 'destination_latitude' => $destination['destination_latitude'], 'destination_longitude' => $destination['destination_longitude'] ) );
		} catch ( \Throwable $error ) {
			$this->fail( 'checkout_validation_failed' );
		}
	}

	private function orderAddress( $order ): array {
		$address = array();
		foreach ( array_merge( BuyerDestination::ADDRESS_FIELDS, array( 'first_name', 'last_name', 'phone' ) ) as $field ) {
			$getter = 'get_shipping_' . $field;
			$address[ $field ] = (string) $order->$getter();
		}
		return $address;
	}

	private function checkZone( $line, array $package ): void {
		$instance = (int) $line->get_instance_id();
		if ( $instance < 1 ) {
			throw new \InvalidArgumentException( 'method_invalid' );
		}
		if ( class_exists( '\WC_Shipping_Zones' ) ) {
			$zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
			$methods = $zone->get_shipping_methods( true );
			$method = $methods[ $instance ] ?? null;
			if ( ! $method || 'kiriminaja-instant' !== $method->id || 'yes' !== $method->enabled ) {
				throw new \InvalidArgumentException( 'method_disabled' );
			}
		}
		if ( ! empty( $package['rates'] ) ) {
			$found = false;
			foreach ( $package['rates'] as $rate ) {
				if ( 'kiriminaja-instant' !== $rate->get_method_id() || $instance !== (int) $rate->get_instance_id() ) {
					continue;
				}
				$meta = $rate->get_meta_data();
				if ( ( $meta['kiriof_instant_quote_token'] ?? null ) === $line->get_meta( 'kiriof_instant_quote_token' ) && ( $meta['kiriof_instant_courier'] ?? null ) === $line->get_meta( 'kiriof_instant_courier' ) && ( $meta['kiriof_instant_service'] ?? null ) === $line->get_meta( 'kiriof_instant_service' ) && (float) $rate->get_cost() === (float) $line->get_total() ) {
					$found = true;
				}
			}
			if ( ! $found ) {
				throw new \InvalidArgumentException( 'rate_invalid' );
			}
		}
	}

	/** Snapshot retries do not depend on a destructively consumed session quote. */
	private function checkSnapshot( $order, $line, array $snapshot ): void {
		$rate = $snapshot['rate'] ?? array();
		$context = $snapshot['context'] ?? array();
		if ( 'cod' === strtolower( $order->get_payment_method() ) || ! is_int( $rate['cost'] ?? null ) || $rate['cost'] < 0 || (float) $line->get_total() !== (float) $rate['cost'] || 'motor' !== ( $rate['vehicle'] ?? null ) || 'motor' !== $line->get_meta( 'kiriof_instant_vehicle' ) || (string) ( $rate['expires'] ?? '' ) !== (string) $line->get_meta( 'kiriof_instant_quote_expires' ) || empty( $context['items'] ) || empty( $context['origin'] ) || ( $context['insurance'] ?? null ) !== false || ( $context['payment_method'] ?? null ) !== $order->get_payment_method() ) {
			throw new \InvalidArgumentException( 'snapshot_invalid' );
		}
		foreach ( array( 'courier', 'service', 'quote_token' ) as $field ) {
			if ( ( $rate[ $field ] ?? null ) !== $line->get_meta( 'kiriof_instant_' . $field ) ) {
				throw new \InvalidArgumentException( 'selection_changed' );
			}
		}
		if ( ! $this->settings->isCourierServiceEnabled( $rate['courier'], $rate['service'] ) || BuyerDestination::address( $this->orderAddress( $order ) ) !== ( $context['destination']['shipping_address'] ?? null ) ) {
			throw new \InvalidArgumentException( 'snapshot_changed' );
		}
		$current = $this->orderAddress( $order );
		foreach ( array( 'first_name', 'last_name', 'phone' ) as $field ) {
			if ( trim( $current[ $field ] ) !== ( $context['recipient'][ $field ] ?? null ) ) {
				throw new \InvalidArgumentException( 'recipient_changed' );
			}
		}
	}

	public function afterCheckoutAfterCreated( $order_id, $data, $order ): void {
		$this->afterStoreApiCheckoutOrderProcessed( $order );
	}

	public function afterStoreApiCheckoutOrderProcessed( $order ): void {
		$locked = false;
		$key = '_kiriof_instant_checkout_lock_' . (int) $order->get_id();
		try {
			$line = $this->shipping( $order );
			if ( null === $line ) {
				return;
			}
			if ( $order->get_id() < 1 ) {
				throw new \RuntimeException( 'order_invalid' );
			}
			$locked = add_option( $key, '1', '', false );
			if ( ! $locked ) {
				throw new \RuntimeException( 'checkout_busy' );
			}
			$existing = $this->transactions->getTransactionByWCOrderId( $order->get_id() );
			if ( $existing ) {
				if ( 'instant' !== ( $existing->delivery_type ?? null ) ) {
					throw new \RuntimeException( 'transaction_conflict' );
				}
				return;
			}
			$snapshot = $order->get_meta( self::SNAPSHOT_META_KEY );
			if ( ! is_array( $snapshot ) ) {
				throw new \RuntimeException( 'snapshot_missing' );
			}
			$this->checkSnapshot( $order, $line, $snapshot );
			$context = $snapshot['context'];
			$rate = $snapshot['rate'];
			$origin = array( 'location_id' => $context['origin']['location_id'], 'timezone' => $context['origin']['timezone'] );
			foreach ( array( 'name' => 'name', 'phone' => 'phone', 'address' => 'address', 'zipcode' => 'zip_code', 'latitude' => 'latitude', 'longitude' => 'longitude', 'country' => 'country' ) as $from => $to ) {
				$origin[ 'origin_' . $to ] = $context['origin'][ $from ];
			}
			$shipping = array();
			foreach ( $this->orderAddress( $order ) as $field => $value ) {
				$shipping[ '_shipping_' . $field ] = $value;
			}
			$payload = array( 'order_id' => $this->generator->call(), 'delivery_type' => 'instant', 'vehicle' => 'motor', 'service' => $rate['courier'], 'service_name' => $rate['service'], 'status' => 'new', 'shipping_info' => wp_json_encode( $shipping ), 'weight' => $context['weight'], 'shipping_cost' => $rate['cost'], 'insurance_cost' => 0, 'cod_fee' => 0, 'transaction_value' => $context['item_value'], 'wp_wc_order_stat_order_id' => $order->get_id(), 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'destination_sub_district_id' => $context['destination']['district_id'], 'destination_sub_district' => $context['destination']['district_label'], 'destination_latitude' => $context['destination']['destination_latitude'], 'destination_longitude' => $context['destination']['destination_longitude'], 'shipment_location_id' => $origin['location_id'], 'shipment_location_snapshot' => wp_json_encode( $origin ), 'discount_amount' => 0, 'discount_percentage' => 0, 'woocommerce_discount_amount' => (float) $order->get_discount_total(), 'woocommerce_discount_description' => '', 'is_deficit' => 0, 'cod_minimum' => null );
			foreach ( array( 'length', 'width', 'height' ) as $dimension ) {
				$payload[ $dimension ] = max( array_column( $context['items'], $dimension ) );
			}
			if ( ! $this->transactions->createTransaction( $payload ) ) {
				throw new \RuntimeException( 'insert_failed' );
			}
		} catch ( \Throwable $error ) {
			$this->fail( 'checkout_transaction_failed' );
		} finally {
			if ( $locked ) {
				delete_option( $key );
			}
		}
	}

	private function fail( string $code ): void {
		if ( function_exists( 'kiriof_log' ) ) {
			try {
				kiriof_log( 'error', 'Instant checkout failed.', array( 'code' => $code, 'backtrace' => false ), 'kiriminaja_instant' );
			} catch ( \Throwable $error ) {
				// Logging must not leak upstream details or change checkout failure semantics.
			}
		}
		throw new \RuntimeException( __( 'Instant delivery could not be confirmed. Please refresh your shipping quote and try again.', 'kiriminaja-official' ) );
	}
}
