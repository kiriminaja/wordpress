<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies contact fields WooCommerce omits from its Classic address refresh. */
final class ClassicCheckoutRecipientSync {
	private bool $checkout_verified = false;

	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync' ), 10, 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process' ), 20 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'posted' ), PHP_INT_MAX, 1 );
	}

	public function process(): void {
		$this->checkout_verified = 'woocommerce_checkout_process' === current_filter() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	public function posted( array $data ): array {
		if ( ! $this->checkout_verified || 'woocommerce_checkout_posted_data' !== current_filter() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $data;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified checkout; contact validates the current form and phone metadata.
		$raw = wp_unslash( $_POST );
		if ( ClassicDistrictAddressSync::separate( $raw ) && ClassicDistrictAddressSync::shippingFirst( $raw ) ) {
			foreach ( $this->contact( $raw ) as $field => $value ) {
				$data[ 'shipping_' . $field ] = $value;
			}
		}
		return $data;
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
		$contact = $separate && ClassicDistrictAddressSync::shippingFirst( $data ) ? $this->contact( $data ) : null;
		InstantCheckoutRecipient::classicPhone( $wc->customer, $separate && ClassicDistrictAddressSync::shippingFirst( $data ) ? $this->value( $data, 'shipping_phone' ) : null );
		InstantCheckoutRecipient::classicContact( $wc->customer, $contact );
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
			if ( null !== $contact ) {
				$shipping = $contact[ $field ];
			}
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

	private function contact( array $data ): array {
		$shared = in_array( $data['wfacp_billing_same_as_shipping'] ?? $data['billing_same_as_shipping'] ?? null, array( '0', 0 ), true );
		$contact = array();
		foreach ( InstantCheckoutRecipient::FIELDS as $field ) {
			$key = 'shipping_' . $field;
			$value = $this->value( $data, $key );
			if ( ( 'phone' === $field && null === $value ) || ( 'phone' !== $field && $shared && ( null === $value || ( is_string( $data[ $key ] ?? null ) && '' === $value ) ) ) ) {
				$key = 'billing_' . $field;
				$value = $this->value( $data, $key );
			}
			$contact[ $field ] = 'phone' === $field ? $this->phone( $data, $key, is_string( $data[ $key ] ?? null ) ? $data[ $key ] : null ) : ( $value ?? '' );
		}
		return $contact;
	}

	private function phone( array $data, string $key, ?string $value ): string {
		if ( null === $value || '' === $value || 'ID' !== ( $data['shipping_country'] ?? null ) ) {
			return '';
		}
		$scope = 'shipping_phone' === $key ? 'shipping' : 'billing';
		if ( array_key_exists( $scope . '_country', $data ) && 'ID' !== $data[ $scope . '_country' ] ) {
			return '';
		}
		if ( ! array_key_exists( 'wfacp_input_phone_field', $data ) ) {
			return InstantPhoneNumber::normalize( $value );
		}
		$json = $data['wfacp_input_phone_field'];
		if ( ! is_string( $json ) || strlen( $json ) > 4096 ) {
			return '';
		}
		$metadata = json_decode( $json, true, 4 );
		if ( ! is_array( $metadata ) || JSON_ERROR_NONE !== json_last_error() || ! is_array( $metadata[ $scope ] ?? null ) ) {
			return '';
		}
		$phone = $metadata[ $scope ];
		if ( array_diff( array_keys( $phone ), array( 'code', 'number', 'hidden' ) ) || '62' !== ( $phone['code'] ?? null ) || ! is_string( $phone['number'] ?? null ) || ! preg_match( '/\A8[0-9]{6,12}\z/', $phone['number'] ) || ! in_array( $phone['hidden'] ?? null, array( 'yes', 'no' ), true ) ) {
			return '';
		}
		$canonical = '62' . $phone['number'];
		$normalized = InstantPhoneNumber::normalize( $value );
		if ( $value === $phone['number'] || $normalized === $canonical || $normalized === '0' . $phone['number'] ) {
			return $canonical;
		}
		return '';
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
