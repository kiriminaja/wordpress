<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckoutPhoneValidation {
	public function register(): void {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'classicFields' ), 5, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'classicOrder' ), 4, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'storeApi' ), 4, 2 );
	}

	/** Check syntax without repairing input or claiming the number is reachable. */
	public static function valid( $value ): bool {
		if ( ! is_string( $value ) || strlen( $value ) > 64 ) {
			return false;
		}
		$value = trim( $value, ' ' );
		if ( 1 !== preg_match( '/\A\+?(?:[0-9]|\([0-9]+\))(?:[0-9]|[ -](?=[0-9(])|\([0-9]+\))*\z/', $value ) ) {
			return false;
		}
		$digits = preg_replace( '/[^0-9]/', '', $value );
		return strlen( $digits ) >= 8 && strlen( $digits ) <= 15;
	}

	public static function message(): string {
		return __( 'Enter a valid phone number with 8–15 digits. Spaces, hyphens, parentheses and a leading + are allowed.', 'kiriminaja-official' );
	}

	private function owned( $method ): bool {
		return is_string( $method ) && 1 === preg_match( '/\Akiriminaja-(?:official|instant)(?:\z|[_:])/', $method );
	}

	private function classicScope( array $data ): bool {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! $wc->cart || ! $wc->cart->needs_shipping() ) {
			return false;
		}
		$methods = array_key_exists( 'shipping_method', $data ) ? $data['shipping_method'] : ( $wc->session ? $wc->session->get( 'chosen_shipping_methods', array() ) : array() );
		return is_array( $methods ) && (bool) array_filter( $methods, array( $this, 'owned' ) );
	}

	private function invalidFields( array $phones ): array {
		$invalid = array();
		foreach ( $phones as $field => $phone ) {
			// A missing separate recipient phone may inherit the required billing phone.
			if ( 'shipping_phone' === $field && '' === $phone ) {
				continue;
			}
			if ( ! self::valid( $phone ) ) {
				$invalid[] = $field;
			}
		}
		return $invalid;
	}

	public function classicFields( $data, $errors ): void {
		if ( ! is_array( $data ) || ! $this->classicScope( $data ) ) {
			return;
		}
		$phones = array( 'billing_phone' => $data['billing_phone'] ?? '' );
		if ( ! empty( $data['ship_to_different_address'] ) ) {
			$phones['shipping_phone'] = $data['shipping_phone'] ?? '';
		}
		foreach ( $phones as $field => $phone ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies the checkout nonce. Validate raw syntax without sanitizing malformed input into an acceptable phone.
			if ( array_key_exists( $field, $_POST ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict syntax validation below; never persisted or echoed.
				$phones[ $field ] = wp_unslash( $_POST[ $field ] );
			}
		}
		foreach ( $this->invalidFields( $phones ) as $field ) {
			if ( ! $errors->get_error_message( $field . '_validation' ) ) {
				$errors->add( $field . '_validation', self::message(), array( 'id' => $field ) );
			}
		}
	}

	private function orderScope( $order ): bool {
		foreach ( $order->get_items( 'shipping' ) as $line ) {
			if ( $this->owned( $line->get_method_id() ) ) {
				return true;
			}
		}
		return false;
	}

	public function classicOrder( $order, $data ): void {
		$this->validateOrder( $order, null, false, ! empty( $data['ship_to_different_address'] ) );
	}

	public function storeApi( $order, $request ): void {
		// Draft PATCH requests must remain editable while a number is incomplete.
		if ( 'POST' !== strtoupper( $request->get_method() ) ) {
			return;
		}
		$this->validateOrder( $order, $request, true );
	}

	private function validateOrder( $order, $request, bool $store_api, bool $separate = true ): void {
		if ( ! $this->orderScope( $order ) ) {
			return;
		}
		$phones = array();
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			if ( 'shipping' === $scope && ! $separate ) {
				continue;
			}
			$address = $request ? $request->get_param( $scope . '_address' ) : null;
			$getter = 'get_' . $scope . '_phone';
			$phones[ $scope . '_phone' ] = is_array( $address ) && array_key_exists( 'phone', $address )
				? $address['phone'] : $order->$getter();
		}
		$invalid = $this->invalidFields( $phones );
		if ( empty( $invalid ) ) {
			return;
		}
		$message = self::message();
		if ( $store_api ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'kiriof_invalid_phone', esc_html( $message ), 400, array( 'invalid_fields' => array_map( 'esc_html', $invalid ) ) );
		}
		throw new \InvalidArgumentException( esc_html( $message ) );
	}
}
