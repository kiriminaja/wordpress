<?php
namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Services\BuyerDestination;
use KiriminAjaOfficial\Services\InstantCheckoutQuoteService;
use KiriminAjaOfficial\Services\InstantCheckoutRecipient;
use KiriminAjaOfficial\Services\ShipmentLocationService;
use KiriminAjaOfficial\Services\KiriminAja\GenerateOrderId;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Final checkout validation only: shipment booking remains an explicit admin action. */
class InstantCheckoutController {
	public const SNAPSHOT_META_KEY = '_kiriof_instant_checkout_snapshot';
	public const SELECTION_META_KEY = '_kiriof_instant_checkout_selection';
	public const CUSTOMER_TOTAL_META_KEY = '_kiriof_instant_customer_shipping_total';
	public const ADMIN_FEE_META_KEY = '_kiriof_instant_admin_fee';
	public const INVOICE_META_KEY = '_kiriof_instant_checkout_invoice';
	public const CART_FEE_ID = 'kiriof_instant_admin_fee';
	public const FEE_TYPE = 'instant_admin_fee';
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

	/** Use only the exact selected, current quote. validate() never calls the API. */
	public function addAdminFee( $cart ): void {
		try {
			$wc = WC();
			$packages = $wc->shipping()->get_packages();
			$chosen = $wc->session->get( 'chosen_shipping_methods', array() );
			if ( ! is_array( $packages ) || 1 !== count( $packages ) || ! is_array( $chosen ) || 1 !== count( $chosen ) ) {
				return;
			}

			$key = key( $packages );
			$package = $packages[ $key ];
			$id = $chosen[ $key ] ?? null;
			$rate = is_string( $id ) ? ( $package['rates'][ $id ] ?? null ) : null;
			if ( ! $rate || 'kiriminaja-instant' !== $rate->get_method_id() || $id !== $rate->get_id() ) {
				return;
			}
			$meta = $rate->get_meta_data();
			if ( $id !== 'kiriminaja-instant:' . $rate->get_instance_id() . ':' . ( $meta['kiriof_instant_courier'] ?? '' ) . ':' . ( $meta['kiriof_instant_service'] ?? '' ) ) {
				return;
			}
			$package['destination'] = InstantCheckoutRecipient::resolve( $package, $wc->customer ?? null );
			$payment = $wc->session->get( 'chosen_payment_method', '' );
			if ( ! is_string( $payment ) || '' === $payment ) {
				$payment = $wc->session->get( 'payment_method', $wc->session->get( 'kiriof_payment_method', '' ) );
			}
			$destination = BuyerDestination::normalize( $wc->session->get( 'kiriof_buyer_destination', null ) );
			$snapshot = $this->quotes->validate( (string) ( $meta['kiriof_instant_quote_token'] ?? '' ), (string) ( $meta['kiriof_instant_courier'] ?? '' ), (string) ( $meta['kiriof_instant_service'] ?? '' ), $package, $destination, is_string( $payment ) ? $payment : '', false );
			$amounts = $snapshot['rate'];
			if ( ! InstantCheckoutQuoteService::validRateAmounts( $amounts ) || (float) $rate->get_cost() !== (float) $amounts['shipping_costs'] || 'motor' !== ( $meta['kiriof_instant_vehicle'] ?? null ) || (string) ( $meta['kiriof_instant_quote_expires'] ?? '' ) !== (string) $amounts['expires'] || $amounts['admin_fee'] <= 0 ) {
				return;
			}
			// Stable ID prevents duplicate charges within a totals calculation.
			$cart->fees_api()->add_fee( array( 'id' => self::CART_FEE_ID, 'name' => __( 'Admin Fee', 'kiriminaja-official' ), 'amount' => $amounts['admin_fee'], 'taxable' => false ) );
		} catch ( \Throwable $error ) {
			// A stale or unsupported selection must not create an unvalidated fee.
		}
	}

	/** Durable identity is independent of translations or editable fee names. */
	public function tagAdminFee( $item, $fee_key, $order, $cart ): void {
		if ( self::CART_FEE_ID === $fee_key ) {
			$item->add_meta_data( '_kiriof_fee_type', self::FEE_TYPE, true );
		}
	}

	public function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'addAdminFee' ), 20, 1 );
		// Store API also calls WC_Checkout::create_order_fee_lines (same hook).
		add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'tagAdminFee' ), 20, 4 );
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
			$package['destination'] = InstantCheckoutRecipient::resolve( $package, $wc->customer ?? null );
			$fields = array_merge( BuyerDestination::ADDRESS_FIELDS, InstantCheckoutRecipient::FIELDS );
			if ( $classic ) {
				$raw = $wc->session->get( 'kiriof_buyer_destination', null );
				// Native WooCommerce checkout verifies its nonce before this hook.
				// A posted clear/edit must not silently reuse an older session pin.
				if ( isset( $_POST['kiriof_buyer_destination_snapshot'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( ! is_string( $_POST['kiriof_buyer_destination_snapshot'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
						throw new \InvalidArgumentException( 'destination_invalid' );
					}
					$posted = json_decode( wp_unslash( $_POST['kiriof_buyer_destination_snapshot'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated immediately by the strict destination schema.
					if ( BuyerDestination::normalize( $posted ) !== BuyerDestination::normalize( $raw ) ) {
						throw new \InvalidArgumentException( 'destination_changed' );
					}
				}
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
			if ( $classic ) {
				InstantCheckoutRecipient::inheritClassicOrderPhone( $order );
			}
			$current = $this->orderAddress( $order );
			if ( 2 !== $destination['version'] || $destination['shipping_address'] !== BuyerDestination::address( $current ) || BuyerDestination::address( $package['destination'] ) !== BuyerDestination::address( $current ) ) {
				throw new \InvalidArgumentException( 'address_changed' );
			}
			foreach ( array( 'first_name', 'last_name', 'phone' ) as $field ) {
				if ( ! is_string( $package['destination'][ $field ] ?? null ) || trim( $package['destination'][ $field ] ) !== trim( $current[ $field ] ) ) {
					throw new \InvalidArgumentException( 'recipient_changed' );
				}
			}
			$this->checkZone( $line, $package );
			// Express insurance preferences never apply to an Instant quote/order.
			$snapshot = $this->quotes->validate( (string) $line->get_meta( 'kiriof_instant_quote_token' ), (string) $line->get_meta( 'kiriof_instant_courier' ), (string) $line->get_meta( 'kiriof_instant_service' ), $package, $destination, $order->get_payment_method(), false );
			$this->checkSnapshot( $order, $line, $snapshot );
			$order->update_meta_data( self::SNAPSHOT_META_KEY, $snapshot );
			$order->update_meta_data( self::CUSTOMER_TOTAL_META_KEY, $snapshot['rate']['total_price'] );
			$order->update_meta_data( self::ADMIN_FEE_META_KEY, $snapshot['rate']['admin_fee'] );
			$order->update_meta_data( self::SELECTION_META_KEY, hash( 'sha256', wp_json_encode( $snapshot ) ) );
			$order->update_meta_data( BuyerDestination::META_KEY, $destination );
			$order->update_meta_data( BuyerDestination::COORDINATE_META_KEY, array( 'latitude' => $destination['destination_latitude'], 'longitude' => $destination['destination_longitude'] ) );
		} catch ( \Throwable $error ) {
			$this->fail( $this->reason( $error, 'checkout_validation_failed' ), ! $classic );
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
		if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
			throw new \InvalidArgumentException( 'method_invalid' );
		}
		$zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		$methods = $zone->get_shipping_methods( true );
		$method = $methods[ $instance ] ?? null;
		if ( ! $method || 'kiriminaja-instant' !== $method->id || 'yes' !== $method->enabled ) {
			throw new \InvalidArgumentException( 'method_disabled' );
		}
		if ( empty( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
		throw new \InvalidArgumentException( 'rate_invalid' );
		}
		$found = false;
		$chosen = WC()->session->get( 'chosen_shipping_methods', array() );
		$expected = 'kiriminaja-instant:' . $instance . ':' . $line->get_meta( 'kiriof_instant_courier' ) . ':' . $line->get_meta( 'kiriof_instant_service' );
		if ( ! is_array( $chosen ) || 1 !== count( $chosen ) || reset( $chosen ) !== $expected ) {
			throw new \InvalidArgumentException( 'selection_changed' );
		}
		foreach ( $package['rates'] as $rate ) {
			if ( $expected !== $rate->get_id() || 'kiriminaja-instant' !== $rate->get_method_id() || $instance !== (int) $rate->get_instance_id() ) {
				continue;
			}
			$meta = $rate->get_meta_data();
			if ( ( $meta['kiriof_instant_quote_token'] ?? null ) === $line->get_meta( 'kiriof_instant_quote_token' ) && ( $meta['kiriof_instant_courier'] ?? null ) === $line->get_meta( 'kiriof_instant_courier' ) && ( $meta['kiriof_instant_service'] ?? null ) === $line->get_meta( 'kiriof_instant_service' ) && ( $meta['kiriof_instant_vehicle'] ?? null ) === $line->get_meta( 'kiriof_instant_vehicle' ) && (string) ( $meta['kiriof_instant_quote_expires'] ?? '' ) === (string) $line->get_meta( 'kiriof_instant_quote_expires' ) && (float) $rate->get_cost() === (float) $line->get_total() ) {
				$found = true;
			}
		}
		if ( ! $found ) {
			throw new \InvalidArgumentException( 'rate_invalid' );
		}
	}

	/** Snapshot retries do not depend on a destructively consumed session quote. */
	private function checkSnapshot( $order, $line, array $snapshot ): void {
		$rate = $snapshot['rate'] ?? array();
		$context = $snapshot['context'] ?? array();
		// A durable receipt must explicitly record IDR; legacy unbound receipts fail closed.
		$currency = is_callable( array( $order, 'get_currency' ) ) ? $order->get_currency() : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IDR' );
		if ( 'IDR' !== $currency || 'IDR' !== ( $context['currency'] ?? null ) ) {
			throw new \InvalidArgumentException( 'currency_unsupported' );
		}
		if ( (float) $line->get_total_tax() !== 0.0 ) {
			throw new \InvalidArgumentException( 'shipping_tax_invalid' );
		}

		if ( ! \KiriminAjaOfficial\Services\InstantDeliveryCoverage::covers(
			$context['origin']['latitude'] ?? null, $context['origin']['longitude'] ?? null,
			$context['destination']['destination_latitude'] ?? null, $context['destination']['destination_longitude'] ?? null
		) ) {
			throw new \InvalidArgumentException( 'outside_instant_radius' );
		}
		if ( 'cod' === strtolower( $order->get_payment_method() ) || ! InstantCheckoutQuoteService::validRateAmounts( $rate ) || (float) $line->get_total() !== (float) $rate['shipping_costs'] || 'motor' !== ( $rate['vehicle'] ?? null ) || 'motor' !== $line->get_meta( 'kiriof_instant_vehicle' ) || (string) ( $rate['expires'] ?? '' ) !== (string) $line->get_meta( 'kiriof_instant_quote_expires' ) || empty( $context['items'] ) || empty( $context['origin'] ) || ( $context['insurance'] ?? null ) !== false || ( $context['payment_method'] ?? null ) !== $order->get_payment_method() ) {
			throw new \InvalidArgumentException( 'snapshot_invalid' );
		}
		$fees = array();
		foreach ( $order->get_items( 'fee' ) as $fee ) {
			if ( self::FEE_TYPE === $fee->get_meta( '_kiriof_fee_type' ) || __( 'Admin Fee', 'kiriminaja-official' ) === $fee->get_name() ) {
				$fees[] = $fee;
			}
		}
		if ( count( $fees ) !== ( $rate['admin_fee'] > 0 ? 1 : 0 ) ) {
			throw new \InvalidArgumentException( 'snapshot_invalid' );
		}
		if ( $fees ) {
			$fee = reset( $fees );
			if ( self::FEE_TYPE !== $fee->get_meta( '_kiriof_fee_type' ) || (float) $fee->get_total() !== (float) $rate['admin_fee'] || (float) $fee->get_total_tax() !== 0.0 || (float) $line->get_total() + (float) $fee->get_total() !== (float) $rate['total_price'] ) {
				throw new \InvalidArgumentException( 'snapshot_invalid' );
			}
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
		$this->processOrder( $order, true );
	}

	public function afterStoreApiCheckoutOrderProcessed( $order ): void {
		$this->processOrder( $order, false );
	}

	private function processOrder( $order, bool $classic ): void {
		$locked = false;
		$owner = ( time() + 300 ) . ':' . bin2hex( random_bytes( 16 ) );
		$key = '_kiriof_instant_checkout_lock_' . (int) $order->get_id();
		try {
			$line = $this->shipping( $order );
			if ( null === $line ) {
				return;
			}
			if ( $order->get_id() < 1 ) {
				throw new \RuntimeException( 'order_invalid' );
			}
			$locked = $this->acquireLock( $key, $owner );
			if ( ! $locked ) {
				throw new \RuntimeException( 'checkout_busy' );
			}
			$snapshot = $order->get_meta( self::SNAPSHOT_META_KEY );
			if ( ! is_array( $snapshot ) ) {
				throw new \RuntimeException( 'snapshot_missing' );
			}
			$this->checkSnapshot( $order, $line, $snapshot );
			if ( (string) $order->get_meta( self::CUSTOMER_TOTAL_META_KEY ) !== (string) $snapshot['rate']['total_price'] || (string) $order->get_meta( self::ADMIN_FEE_META_KEY ) !== (string) $snapshot['rate']['admin_fee'] ) {
				throw new \RuntimeException( 'snapshot_changed' );
			}
			if ( ! hash_equals( (string) $order->get_meta( self::SELECTION_META_KEY ), hash( 'sha256', wp_json_encode( $snapshot ) ) ) || $order->get_meta( BuyerDestination::META_KEY ) !== $snapshot['context']['destination'] ) {
				throw new \RuntimeException( 'snapshot_changed' );
			}
			$coordinates = $order->get_meta( BuyerDestination::COORDINATE_META_KEY );
			foreach ( array( 'latitude', 'longitude' ) as $axis ) {
				if ( ! is_array( $coordinates ) || ( $coordinates[ $axis ] ?? null ) !== $snapshot['context']['destination'][ 'destination_' . $axis ] ) {
					throw new \RuntimeException( 'snapshot_changed' );
				}
			}
			$context = $snapshot['context'];
			$rate = $snapshot['rate'];
			$origin = array( 'location_id' => $context['origin']['location_id'], 'timezone' => $context['origin']['timezone'] );
			foreach ( array( 'name' => 'name', 'phone' => 'phone', 'address' => 'address', 'zipcode' => 'zip_code', 'latitude' => 'latitude', 'longitude' => 'longitude', 'country' => 'country' ) as $from => $to ) {
				$origin[ 'origin_' . $to ] = $context['origin'][ $from ];
			}
			// Keep booking/admin repricing on the raw carrier cost; the buyer paid total once.
			$shipping = array( '_kiriof_instant_admin_fee' => $rate['admin_fee'], '_kiriof_instant_shipping_total' => $rate['total_price'], '_kiriof_instant_shipping_cost' => $rate['shipping_costs'] );
			foreach ( $this->orderAddress( $order ) as $field => $value ) {
				$shipping[ '_shipping_' . $field ] = $value;
			}
			$invoice = $order->get_meta( self::INVOICE_META_KEY );
			if ( ! is_string( $invoice ) || '' === $invoice ) {
				$invoice = $this->generator->call();
				$order->update_meta_data( self::INVOICE_META_KEY, $invoice );
				$order->save_meta_data();
			}
			$payload = array( 'order_id' => $invoice, 'delivery_type' => 'instant', 'vehicle' => 'motor', 'service' => $rate['courier'], 'service_name' => $rate['service'], 'status' => 'new', 'shipping_info' => wp_json_encode( $shipping ), 'weight' => $context['weight'], 'shipping_cost' => $rate['shipping_costs'], 'insurance_cost' => 0, 'cod_fee' => 0, 'transaction_value' => $context['item_value'], 'wp_wc_order_stat_order_id' => $order->get_id(), 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'destination_sub_district_id' => $context['destination']['district_id'], 'destination_sub_district' => $context['destination']['district_label'], 'destination_latitude' => $context['destination']['destination_latitude'], 'destination_longitude' => $context['destination']['destination_longitude'], 'shipment_location_id' => $origin['location_id'], 'shipment_location_snapshot' => wp_json_encode( $origin ), 'discount_amount' => 0, 'discount_percentage' => 0, 'woocommerce_discount_amount' => (float) $order->get_discount_total(), 'woocommerce_discount_description' => '', 'is_deficit' => 0, 'cod_minimum' => null );
			foreach ( array( 'length', 'width', 'height' ) as $dimension ) {
				$payload[ $dimension ] = max( array_column( $context['items'], $dimension ) );
			}
			$existing = $this->transactions->getTransactionByWCOrderId( $order->get_id() );
			if ( $existing ) {
				$this->checkExisting( $existing, $payload );
				return;
			}
			if ( ! $this->transactions->createTransaction( $payload ) ) {
				throw new \RuntimeException( 'insert_failed' );
			}
		} catch ( \Throwable $error ) {
			$this->fail( $this->reason( $error, 'checkout_transaction_failed' ), ! $classic );
		} finally {
			if ( $locked ) {
				$this->releaseLock( $key, $owner );
			}
		}
	}


	/** Only fixed codes can reach logs; never upstream details or selected tokens. */
	private function reason( \Throwable $error, string $fallback ): string {
		$allowed = array( 'currency_unsupported', 'shipping_tax_invalid', 'outside_instant_radius', 'shipping_invalid', 'packages_invalid', 'address_invalid', 'address_changed', 'recipient_changed', 'method_invalid', 'method_disabled', 'rate_invalid', 'snapshot_invalid', 'selection_changed', 'snapshot_changed', 'order_invalid', 'checkout_busy', 'transaction_conflict', 'snapshot_missing', 'insert_failed', 'quote_invalid', 'quote_expired_or_changed', 'cod_unsupported', 'insurance_unsupported', 'services_disabled', 'account_unavailable', 'destination_invalid', 'recipient_invalid', 'origin_invalid', 'items_invalid', 'timezone_unsupported', 'context_unavailable' );
		return in_array( $error->getMessage(), $allowed, true ) ? $error->getMessage() : $fallback;
	}

	private function checkExisting( object $existing, array $payload ): void {
		foreach ( array( 'order_id', 'delivery_type', 'vehicle', 'service', 'service_name', 'wp_wc_order_stat_order_id', 'shipping_cost', 'weight', 'transaction_value', 'destination_sub_district_id', 'shipment_location_id' ) as $field ) {
			if ( (string) ( $existing->$field ?? '' ) !== (string) $payload[ $field ] ) {
				throw new \RuntimeException( 'transaction_conflict' );
			}
		}
		foreach ( array( 'destination_latitude', 'destination_longitude' ) as $field ) {
			if ( ! is_numeric( $existing->$field ?? null ) || (float) $existing->$field !== (float) $payload[ $field ] ) {
				throw new \RuntimeException( 'transaction_conflict' );
			}
		}
		foreach ( array( 'shipping_info', 'shipment_location_snapshot' ) as $field ) {
			if ( json_decode( (string) ( $existing->$field ?? '' ), true ) !== json_decode( $payload[ $field ], true ) ) {
				throw new \RuntimeException( 'transaction_conflict' );
			}
		}
	}

	/** Atomic compare-and-swap permits recovery after a crashed checkout worker. */
	private function acquireLock( string $key, string $owner ): bool {
		if ( add_option( $key, $owner, '', false ) ) {
			return true;
		}
		$previous = get_option( $key, '' );
		if ( ! is_string( $previous ) || ! preg_match( '/\A([0-9]+):[a-f0-9]{32}\z/', $previous, $match ) || (int) $match[1] >= time() ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-swap prevents a stale checkout worker from acquiring another owner's lease; the option API cannot provide this operation.
		$claimed = 1 === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $owner, $key, $previous ) );
		wp_cache_delete( $key, 'options' );
		return $claimed;
	}

	private function releaseLock( string $key, string $owner ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Compare-and-delete releases only this worker's lease; delete_option() could remove a newer owner's lock. The option cache is invalidated below.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $owner ) );
		wp_cache_delete( $key, 'options' );
	}

	private function fail( string $code, bool $store_api ): void {
		if ( function_exists( 'kiriof_log' ) ) {
			try {
				kiriof_log( 'error', 'Instant checkout failed.', array( 'code' => $code, 'backtrace' => false ), 'kiriminaja_instant' );
			} catch ( \Throwable $error ) {
				// Logging must not leak upstream details or change checkout failure semantics.
			}
		}
		$message = __( 'Instant delivery could not be confirmed. Please refresh your shipping quote and try again.', 'kiriminaja-official' );
		if ( $store_api && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'kiriof_instant_checkout_failed', esc_html( $message ), 400 );
		}
		throw new \RuntimeException( esc_html( $message ) );
	}
}
