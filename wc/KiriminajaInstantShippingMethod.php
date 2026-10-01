<?php
/** Standalone, zone-managed Instant checkout shipping method. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_shipping_init', 'kiriof_instant_shipping_method', 99 );
add_filter( 'woocommerce_shipping_methods', 'kiriof_register_instant_shipping_method' );

/** Register safely even when WooCommerce is not loaded yet. */
function kiriof_register_instant_shipping_method( $methods ) {
	kiriof_instant_shipping_method();
	if ( class_exists( 'Kiriof_Instant_Shipping_Method_Controller', false ) ) {
		$methods['kiriminaja-instant'] = 'Kiriof_Instant_Shipping_Method_Controller';
	}
	return $methods;
}

/** Delay the WooCommerce subclass declaration until its parent is available. */
function kiriof_instant_shipping_method() {
	if ( ! class_exists( 'WC_Shipping_Method' ) || class_exists( 'Kiriof_Instant_Shipping_Method_Controller', false ) ) {
		return;
	}

	class Kiriof_Instant_Shipping_Method_Controller extends WC_Shipping_Method {
		/** @var \KiriminAjaOfficial\Services\InstantCheckoutQuoteService|null */
		private $quotes;

		public function __construct( $instance_id = 0, ?\KiriminAjaOfficial\Services\InstantCheckoutQuoteService $quotes = null ) {
			$this->id                 = 'kiriminaja-instant';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'KiriminAja Instant', 'kiriminaja-official' );
			$this->method_description = __( 'Add this method to a shipping zone. Instant requires a saved origin map pin and a buyer shipping map pin. COD and shipping insurance are not supported.', 'kiriminaja-official' );
			$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
			$this->instance_form_fields = array(
				'enabled' => array( 'title' => __( 'Enable', 'kiriminaja-official' ), 'type' => 'checkbox', 'default' => 'yes' ),
				'title'   => array( 'title' => __( 'Title', 'kiriminaja-official' ), 'type' => 'text', 'default' => __( 'KiriminAja Instant', 'kiriminaja-official' ) ),
			);
			$this->init_settings();
			if ( method_exists( $this, 'init_instance_settings' ) ) { $this->init_instance_settings(); }
			$this->enabled = $this->get_option( 'enabled', 'yes' );
			$this->title   = $this->get_option( 'title', __( 'KiriminAja Instant', 'kiriminaja-official' ) );
			$this->quotes  = $quotes;
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/** Availability must never make a remote quote request. */
		public function is_available( $package ) {
			if ( 'yes' !== $this->enabled || ! $this->has_physical_contents( $package ) ) {
				return false;
			}
			return parent::is_available( $package );
		}

		private function has_physical_contents( $package ) {
			foreach ( $package['contents'] ?? array() as $item ) {
				if ( ! empty( $item['quantity'] ) && isset( $item['data'] ) && is_object( $item['data'] ) && $item['data']->needs_shipping() ) {
					return true;
				}
			}
			return false;
		}

		/** A typed dependency is injectable without a production bypass filter. */
		protected function quote_service() {
			if ( null === $this->quotes ) {
				$settings = new \KiriminAjaOfficial\Repositories\SettingRepository();
				$this->quotes = new \KiriminAjaOfficial\Services\InstantCheckoutQuoteService(
					$settings,
					new \KiriminAjaOfficial\Services\ShipmentLocationService( null, $settings )
				);
			}
			return $this->quotes;
		}

		public function calculate_shipping( $package = array() ) {
			if ( 'yes' !== $this->enabled || ! $this->has_physical_contents( $package ) ) {
				return;
			}
			$wc      = function_exists( 'WC' ) ? WC() : null;
			$session = $wc && isset( $wc->session ) ? $wc->session : null;
			if ( ! $session ) {
				return;
			}
			$payment = $session->get( 'chosen_payment_method', '' );
			if ( ! is_string( $payment ) || '' === $payment ) {
				$payment = $session->get( 'payment_method', $session->get( 'kiriof_payment_method', '' ) );
			}
			$payment = is_string( $payment ) ? $payment : '';
			// Instant has no insurance allowance; Express insurance settings stay unchanged.
			$insurance = false;
			if ( 'cod' === strtolower( trim( $payment ) ) ) {
				$this->store_status( $session, $package, 'cod_not_supported', false, 0 );
				return;
			}
			try {
				$destination = \KiriminAjaOfficial\Services\BuyerDestination::normalize( $session->get( 'kiriof_buyer_destination', null ) );
				if ( 2 !== $destination['version'] ) {
					throw new \InvalidArgumentException();
				}
			} catch ( \InvalidArgumentException $exception ) {
				$this->store_status( $session, $package, 'destination_required', false, 0 );
				return;
			}

			// Only fill missing fields: the actual package always wins over the customer.
			$package['destination'] = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
			$customer = $wc->customer ?? null;
			foreach ( array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'first_name', 'last_name', 'phone' ) as $field ) {
				$getter = 'get_shipping_' . $field;
				if ( ! array_key_exists( $field, $package['destination'] ) && $customer && is_callable( array( $customer, $getter ) ) ) {
					$package['destination'][ $field ] = $customer->$getter();
				}
			}
			try {
				$result = $this->quote_service()->quote( $package, $destination, $payment, $insurance );
				$count  = 0;
				if ( ! empty( $result['eligible'] ) ) {
					foreach ( $result['rates'] ?? array() as $rate ) {
						if ( ! $this->valid_rate( $rate ) ) {
							continue;
						}
						$this->add_rate( array(
							'id' => $this->id . ':' . $this->instance_id . ':' . $rate['courier'] . ':' . $rate['service'],
							'label' => sanitize_text_field( $rate['label'] ) . ' — ' . __( 'Insurance not supported', 'kiriminaja-official' ),
							'cost' => (float) $rate['cost'],
							'meta_data' => array(
								'kiriof_delivery_type' => 'instant',
								'kiriof_instant_quote_token' => $rate['quote_token'],
								'kiriof_instant_courier' => $rate['courier'],
								'kiriof_instant_service' => $rate['service'],
								'kiriof_instant_vehicle' => 'motor',
								'kiriof_instant_quote_expires' => (int) $rate['expires'],
							),
						) );
						++$count;
					}
				}
				$code = $count ? 'available' : ( $result['code'] ?? 'unavailable' );
				if ( ! $count && 'available' === $code ) {
					$code = 'unavailable';
				}
				$this->store_status( $session, $package, $code, $count > 0, $count, $result['context'] ?? array() );
			} catch ( \Throwable $exception ) {
				$this->store_status( $session, $package, 'quote_failed', false, 0 );
			}
		}

		/** Never rewrite unsupported identifiers into a different courier/service pair. */
		private function valid_rate( $rate ) {
			if ( ! is_array( $rate ) ) {
				return false;
			}
			foreach ( array( 'courier', 'service' ) as $field ) {
				if ( ! isset( $rate[ $field ] ) || ! is_string( $rate[ $field ] ) || ! preg_match( '/^[A-Za-z0-9_-]+$/D', $rate[ $field ] ) ) {
					return false;
				}
			}
			return isset( $rate['cost'], $rate['label'], $rate['quote_token'], $rate['expires'], $rate['vehicle'] )
				&& in_array( $rate['courier'], array( 'gosend', 'grab_express' ), true )
				&& is_numeric( $rate['cost'] ) && is_finite( (float) $rate['cost'] ) && (float) $rate['cost'] >= 0
				&& is_string( $rate['label'] ) && '' !== trim( $rate['label'] )
				&& is_string( $rate['quote_token'] ) && preg_match( '/^[a-zA-Z0-9_-]{1,255}$/D', $rate['quote_token'] )
				&& is_numeric( $rate['expires'] ) && (int) $rate['expires'] > time() && 'motor' === $rate['vehicle'];
		}

		/** Keep only fixed diagnostics and a one-way context hash, never address data. */
		private function store_status( $session, $package, $code, $eligible, $count, $context = array() ) {
			$messages = array(
				'available' => __( 'Instant delivery is available.', 'kiriminaja-official' ),
				'cod_not_supported' => __( 'Instant delivery does not support COD.', 'kiriminaja-official' ),
				'insurance_not_supported' => __( 'Instant delivery does not support shipping insurance.', 'kiriminaja-official' ),
				'destination_required' => __( 'Choose a shipping address and map pin for Instant delivery.', 'kiriminaja-official' ),
				'quote_failed' => __( 'Instant delivery rates are temporarily unavailable. Please try again.', 'kiriminaja-official' ),
				'unavailable' => __( 'Instant delivery is unavailable for this shipment.', 'kiriminaja-official' ),
			);
			// Service reason codes are machine-only; never relay upstream error messages.
			$code = is_string( $code ) && preg_match( '/^[a-z0-9_-]{1,80}$/D', $code ) ? $code : 'unavailable';
			$key = $this->instance_id . ':' . hash( 'sha256', wp_json_encode( array_keys( $package['contents'] ?? array() ) ) );
			$status = $session->get( 'kiriof_instant_checkout_status', array() );
			$status = is_array( $status ) ? $status : array();
			$status[ $key ] = array( 'code' => $code, 'message' => $messages[ $code ] ?? $messages['unavailable'], 'eligible' => (bool) $eligible, 'count' => (int) $count, 'updated' => time(), 'fingerprint' => hash( 'sha256', wp_json_encode( $context ) ) );
			$session->set( 'kiriof_instant_checkout_status', $status );
			if ( function_exists( 'kiriof_log' ) ) {
				kiriof_log( $eligible ? 'info' : 'warning', 'Instant checkout rate calculation completed.', array(
					'code' => $code, 'eligible' => (bool) $eligible, 'rate_count' => (int) $count,
					'instance_id' => (int) $this->instance_id, 'backtrace' => false,
				), 'kiriminaja_instant' );
			}
		}
	}
}
