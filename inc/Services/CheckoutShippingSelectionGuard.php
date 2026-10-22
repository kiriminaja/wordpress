<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read-only final review gate, before either route's validation or snapshot writes. */
final class CheckoutShippingSelectionGuard {
	public static function schema(): array {
		return array(
			'type' => 'object', 'readonly' => false,
			'properties' => array(
				'version' => array( 'type' => 'integer', 'enum' => array( 1 ) ),
				'packages' => array( 'type' => 'array', 'maxItems' => 50, 'items' => array(
					'type' => 'object', 'properties' => array(
						'package_id' => array( 'type' => array( 'string', 'integer' ) ),
						'rate_id' => array( 'type' => 'string', 'maxLength' => 256 ),
					), 'required' => array( 'package_id', 'rate_id' ),
				) ),
			), 'required' => array( 'version', 'packages' ),
		);
	}

	public function register(): void {
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'storeApi' ), 5, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'classic' ), 5, 2 );
	}

	public function storeApi( $order, $request ): void {
		// Woo also fires this hook on draft/address PATCH requests. Only the
		// place-order POST is an approval boundary; never block ordinary editing.
		if ( is_callable( array( $request, 'get_method' ) ) && 'POST' !== strtoupper( $request->get_method() ) ) { return; }
		$extensions = $request->get_param( 'extensions' );
		$raw = is_array( $extensions ) ? ( $extensions['kiriminaja-official']['shipping_selection'] ?? null ) : null;
		$this->validate( $order, $raw, true );
	}

	public function classic( $order, $data ): void {
		// WooCommerce verifies the checkout nonce before create_order. Never reuse a session review.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing -- Native checkout nonce verified; JSON is unslashed below then review() strictly validates version, list shape and bounded exact identifiers against server rates. Text sanitization would mutate opaque rate IDs.
		$posted = $_POST['kiriof_shipping_selection'] ?? null;
		$raw = is_string( $posted ) ? json_decode( wp_unslash( $posted ), true ) : null;
		$this->validate( $order, $raw, false );
	}

	private function owned( string $id ): bool {
		return 'kiriminaja-official' === $id || 'kiriminaja-instant' === $id
			|| 0 === strpos( $id, 'kiriminaja-official_' ) || 0 === strpos( $id, 'kiriminaja-official:' )
			|| 0 === strpos( $id, 'kiriminaja-instant:' );
	}

	/** Version 1 packages list; native amount/tax/currency fields are deliberately not trusted here. */
	private function identifier( $value, bool $package ): ?string {
		if ( $package && is_int( $value ) ) { return $value >= 0 ? (string) $value : null; }
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 256 || preg_match( '/[\x00-\x1f\x7f-\x9f]/', $value ) ) { return null; }
		return $value;
	}

	private function review( $raw ): ?array {
		if ( ! is_array( $raw ) || 1 !== ( $raw['version'] ?? null ) || ! is_array( $raw['packages'] ?? null ) || ! array_is_list( $raw['packages'] ) || count( $raw['packages'] ) > 50 ) {
			return null;
		}
		$result = array();
		foreach ( $raw['packages'] as $package ) {
			$id = is_array( $package ) ? $this->identifier( $package['package_id'] ?? null, true ) : null;
			$rate = is_array( $package ) ? $this->identifier( $package['rate_id'] ?? null, false ) : null;
			if ( null === $id || null === $rate || array_key_exists( $id, $result ) ) {
				return null;
			}
			$result[ $id ] = $rate;
		}
		return $result;
	}

	private function validate( $order, $raw, bool $store_api ): void {
		$review = $this->review( $raw );
		if ( null !== $raw && null === $review ) { $this->fail( $store_api, 'review_invalid' ); }
		$wc = function_exists( 'WC' ) ? WC() : null;
		$packages = $wc ? $wc->shipping()->get_packages() : array();
		$chosen = $wc && $wc->session ? $wc->session->get( 'chosen_shipping_methods', array() ) : array();
		$lines = $order->get_items( 'shipping' );
		$in_scope = false;
		// Include the incoming intent even when its version/schema is invalid: switching away must not evade the gate.
		foreach ( is_array( $raw ) && is_array( $raw['packages'] ?? null ) ? $raw['packages'] : array() as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['rate_id'] ?? null ) && $this->owned( $entry['rate_id'] ) ) { $in_scope = true; }
		}
		// Woo updates selections by current package key and does not always prune
		// removed package keys. They are not shipments in this checkout request.
		$current_chosen = is_array( $chosen ) && is_array( $packages ) ? array_intersect_key( $chosen, $packages ) : array();
		foreach ( $current_chosen as $id ) {
			if ( is_string( $id ) && $this->owned( $id ) ) { $in_scope = true; }
		}
		foreach ( is_array( $packages ) ? $packages : array() as $key => $package ) {
			$id = is_array( $chosen ) ? ( $chosen[ $key ] ?? null ) : null;
			$rate = is_string( $id ) ? ( $package['rates'][ $id ] ?? null ) : null;
			if ( $rate && $this->owned( (string) $rate->get_method_id() ) ) { $in_scope = true; }
		}
		foreach ( $lines as $line ) {
			if ( $this->owned( (string) $line->get_method_id() ) ) { $in_scope = true; }
		}
		if ( ! $in_scope ) { return; }

		if ( null === $review ) { $this->fail( $store_api, 'review_missing' ); }
		if ( ! is_array( $packages ) || ! is_array( $chosen ) || count( $review ) !== count( $packages ) || count( $current_chosen ) !== count( $packages ) || count( $lines ) !== count( $packages ) ) {
			$this->fail( $store_api, 'current_package_set_mismatch' );
		}
		$remaining = array_values( $lines );
		foreach ( $packages as $key => $package ) {
			$id = $review[ $key ] ?? null;
			$rate = is_string( $id ) ? ( $package['rates'][ $id ] ?? null ) : null;
			if ( ! $rate || $rate->get_id() !== $id ) { $this->fail( $store_api, 'reviewed_rate_unavailable' ); }
			if ( ( $chosen[ $key ] ?? null ) !== $id ) {
				$selected = is_string( $chosen[ $key ] ?? null ) ? $chosen[ $key ] : '';
				$this->fail( $store_api, 'selected_rate_mismatch', array(
					'reviewed_kind' => $this->rateKind( $id ),
					'selected_kind' => $this->rateKind( $selected ),
					'reviewed_fingerprint' => substr( hash( 'sha256', $id ), 0, 16 ),
					'selected_fingerprint' => substr( hash( 'sha256', $selected ), 0, 16 ),
					'order_matches_review' => (bool) array_filter( $remaining, fn( $line ) => $this->matches( $line, $rate ) ),
				) );
			}
			// Match a multiset, not order item IDs or presumed numeric package positions.
			$matched = false;
			foreach ( $remaining as $index => $line ) {
				if ( $this->matches( $line, $rate ) ) {
					unset( $remaining[ $index ] );
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) { $this->fail( $store_api, 'order_shipping_identity_mismatch' ); }
		}
	}

	private function matches( $line, $rate ): bool {
		$method = $rate->get_method_id();
		// Legacy Express lines may hold the full rate ID; derive identity from the actual native rate only.
		if ( $line->get_method_id() !== $method && ! ( 'kiriminaja-official' === $method && $line->get_method_id() === $rate->get_id() ) ) { return false; }
		if ( (string) $line->get_instance_id() !== (string) $rate->get_instance_id() ) { return false; }
		$keys = 'kiriminaja-instant' === $method ? array( 'kiriof_instant_courier', 'kiriof_instant_service' )
			: ( 'kiriminaja-official' === $method ? array( 'kiriof_rate_service', 'kiriof_rate_service_type' ) : array() );
		$meta = $rate->get_meta_data();
		foreach ( $keys as $key ) {
			if ( ! is_scalar( $meta[ $key ] ?? null ) || '' === (string) $meta[ $key ] || (string) $line->get_meta( $key, true ) !== (string) $meta[ $key ] ) { return false; }
		}
		return true;
	}

	private function rateKind( string $id ): string {
		if ( 0 === strpos( $id, 'kiriminaja-instant:' ) ) { return 'instant'; }
		if ( $this->owned( $id ) ) { return 'express'; }
		return '' === $id ? 'missing' : 'external';
	}

	private function fail( bool $store_api, string $reason, array $identity_diagnostics = array() ): void {
		// Fixed internal reasons only: no posted identifiers, address, coordinates,
		// payment values, quote tokens or exception trace enter this diagnostic.
		if ( function_exists( 'kiriof_log' ) ) {
			try {
				kiriof_log( 'warning', 'Checkout shipping review rejected.', array( 'reason' => $reason, 'route' => $store_api ? 'blocks' : 'classic', 'backtrace' => false ) + $identity_diagnostics, 'kiriminaja_checkout' );
			} catch ( \Throwable $error ) {
				// Logging must not alter the read-only checkout gate.
			}
		}
		$message = __( 'Shipping options changed. Please review and select your courier again before placing the order.', 'kiriminaja-official' );
		$exception = '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';
		if ( $store_api && class_exists( $exception ) ) {
			throw new $exception( 'kiriof_shipping_selection_changed', esc_html( $message ), 409 );
		}
		throw new \RuntimeException( esc_html( $message ) );
	}
}
