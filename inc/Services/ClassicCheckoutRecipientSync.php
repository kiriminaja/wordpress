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
		if ( array_key_exists( 'wfacp_billing_same_as_shipping', $data ) ) {
			$shared = in_array( $data['wfacp_billing_same_as_shipping'], array( '0', 0 ), true );
		} else {
			$shared = array_key_exists( 'billing_same_as_shipping', $data )
				? in_array( $data['billing_same_as_shipping'], array( '1', 1 ), true )
				: ! array_key_exists( 'shipping_first_name', $data ) && ! array_key_exists( 'shipping_last_name', $data );
		}
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
		$normalized = InstantPhoneNumber::normalize( $value );
		if ( array_key_exists( 'kiriof_funnelkit_phone_context', $data ) ) {
			$raw_context = $data['kiriof_funnelkit_phone_context'];
			if ( ! is_string( $raw_context ) || strlen( $raw_context ) > 2048 ) {
				return '';
			}
			$context = json_decode( $raw_context, false, 4 );
			if ( ! $context instanceof \stdClass || JSON_ERROR_NONE !== json_last_error() || array_diff( array_keys( (array) $context ), array( 'billing', 'shipping' ) ) ) {
				return '';
			}
			if ( property_exists( $context, $scope ) ) {
				return $this->widgetPhone( $context->$scope, $value );
			}
		}
		$prefixed = (bool) preg_match( '/\A(?:08|628)[0-9]{6,12}\z/', $normalized );
		if ( ! array_key_exists( 'wfacp_input_phone_field', $data ) ) {
			return $prefixed ? $normalized : '';
		}
		$json = $data['wfacp_input_phone_field'];
		if ( ! is_string( $json ) || strlen( $json ) > 4096 ) {
			return '';
		}
		$metadata = json_decode( $json, false, 4 );
		if ( ! $metadata instanceof \stdClass || JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}
		$metadata = (array) $metadata;
		if ( array_diff( array_keys( $metadata ), array( 'billing', 'shipping' ) ) ) {
			return '';
		}
		// FunnelKit emits an empty object, or an all-empty scope, while its
		// asynchronous phone widget is still initializing. In that window only
		// an explicitly Indonesian-prefixed value is safe to accept.
		if ( ! array_key_exists( $scope, $metadata ) ) {
			return $prefixed ? $normalized : '';
		}
		if ( ! $metadata[ $scope ] instanceof \stdClass ) {
			return '';
		}
		$phone = (array) $metadata[ $scope ];
		if ( array_diff( array_keys( $phone ), array( 'code', 'number', 'hidden' ) ) ) {
			return '';
		}
		if ( '' === ( $phone['code'] ?? null ) && '' === ( $phone['number'] ?? null ) && '' === ( $phone['hidden'] ?? null ) ) {
			return $prefixed ? $normalized : '';
		}
		if ( '62' !== ( $phone['code'] ?? null ) || ! is_string( $phone['number'] ?? null ) || ! preg_match( '/\A8[0-9]{6,12}\z/', $phone['number'] ) || ! in_array( $phone['hidden'] ?? null, array( 'yes', 'no' ), true ) ) {
			return '';
		}
		$canonical = '62' . $phone['number'];
		if ( $value === $phone['number'] || $normalized === $canonical || $normalized === '0' . $phone['number'] ) {
			return $canonical;
		}
		return '';
	}

	/** The current widget selection must be bound to the exact submitted input. */
	private function widgetPhone( $context, string $value ): string {
		if ( ! $context instanceof \stdClass ) {
			return '';
		}
		$row = (array) $context;
		if ( array_diff( array_keys( $row ), array( 'value', 'iso2', 'dialCode' ) ) || ! is_string( $row['value'] ?? null ) || strlen( $row['value'] ) > 64 || 'id' !== ( $row['iso2'] ?? null ) || '62' !== ( $row['dialCode'] ?? null ) ) {
			return '';
		}
		$widget_value = $row['value'];
		if ( preg_match( '/\A8[0-9]{6,12}\z/', $widget_value ) ) {
			$canonical = '62' . $widget_value;
		} else {
			$canonical = InstantPhoneNumber::normalize( $widget_value );
			if ( str_starts_with( $canonical, '08' ) ) {
				$canonical = '62' . substr( $canonical, 1 );
			}
		}
		if ( ! preg_match( '/\A628[0-9]{6,12}\z/', $canonical ) ) {
			return '';
		}
		// FunnelKit may add +62 during final checkout after this context was captured.
		$normalized = InstantPhoneNumber::normalize( $value );
		if ( str_starts_with( $normalized, '08' ) ) {
			$normalized = '62' . substr( $normalized, 1 );
		}
		return $widget_value === $value || $normalized === $canonical ? $canonical : '';
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
