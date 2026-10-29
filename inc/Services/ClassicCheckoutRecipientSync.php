<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies contact fields WooCommerce omits from its Classic address refresh. */
final class ClassicCheckoutRecipientSync {
	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync' ), 10, 1 );
	}

	/** WooCommerce has verified the native nonce before this lifecycle hook runs. */
	public function sync( $post_data ): void {
		if ( 'woocommerce_checkout_update_order_review' !== current_filter() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_string( $post_data ) ) {
			return;
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! isset( $wc->customer, $wc->cart ) || ! $wc->customer || ! $wc->cart || ! $wc->cart->needs_shipping() ) {
			return;
		}

		// The hook receives WooCommerce's already-unslashed serialized checkout payload.
		parse_str( $post_data, $data );
		$separate = ClassicDistrictAddressSync::separate( $data );
		InstantCheckoutRecipient::classicPhone( $wc->customer, $separate && ClassicDistrictAddressSync::shippingFirst( $data ) ? $this->value( $data, 'shipping_phone' ) : null );
		$changed  = false;
		foreach ( InstantCheckoutRecipient::FIELDS as $field ) {
			$billing_key = 'billing_' . $field;
			$shipping_key = 'shipping_' . $field;
			$billing = $this->value( $data, $billing_key );
			if ( null !== $billing ) {
				$changed = $this->set( $wc->customer, 'billing', $field, $billing ) || $changed;
			}

			if ( ! $separate ) {
				if ( null !== $billing ) {
					$changed = $this->set( $wc->customer, 'shipping', $field, $billing ) || $changed;
				}
				continue;
			}

			$shipping = $this->value( $data, $shipping_key );
			// Classic has no shipping phone field. A missing field inherits billing;
			// an explicitly empty field remains empty and is not repaired.
			if ( null === $shipping && 'phone' === $field ) {
				$shipping = $billing;
			}
			if ( null !== $shipping ) {
				$changed = $this->set( $wc->customer, 'shipping', $field, $shipping ) || $changed;
			}
		}
		if ( $changed ) {
			ClassicShippingRateCache::invalidate();
		}
	}

	private function value( array $data, string $key ): ?string {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}
		return is_string( $data[ $key ] ) ? sanitize_text_field( $data[ $key ] ) : '';
	}

	private function set( $customer, string $type, string $field, string $value ): bool {
		$getter = 'get_' . $type . '_' . $field;
		$setter = 'set_' . $type . '_' . $field;
		if ( ! is_callable( array( $customer, $getter ) ) || ! is_callable( array( $customer, $setter ) ) || $value === $customer->$getter() ) {
			return false;
		}
		$customer->$setter( $value );
		return true;
	}
}
