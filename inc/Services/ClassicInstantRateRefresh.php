<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Refresh Classic's cached quotes without changing a reviewed destination or courier. */
final class ClassicInstantRateRefresh {
	private const RECOVERY_KEY = 'kiriof_classic_instant_rate_recovery';
	private ClassicDistrictAddressSync $address_sync;

	public function __construct( ?ClassicDistrictAddressSync $address_sync = null ) {
		$this->address_sync = $address_sync ?? new ClassicDistrictAddressSync();
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'refresh' ), 20, 1 );
	}

	/** WooCommerce verifies its update-order-review nonce before invoking this hook. */
	public function refresh( $post_data ): void {
		if ( 'woocommerce_checkout_update_order_review' !== current_filter() || ! is_string( $post_data ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! isset( $wc->session, $wc->cart ) || ! $wc->session || ! $wc->cart || ! $wc->cart->needs_shipping() || ! is_callable( array( $wc->cart, 'get_shipping_packages' ) ) ) {
			return;
		}
		parse_str( $post_data, $data );
		$data = $this->address_sync->normalize( $data );
		$drafts = $wc->session->get( ClassicPinDraftService::SESSION_KEY, null );
		$scope = ClassicDistrictAddressSync::separate( $data ) ? 'shipping' : 'billing';
		$draft_bound = is_array( $drafts ) && is_array( $drafts[ $scope ] ?? null );
		if ( ! $draft_bound && ! is_string( $data['kiriof_buyer_destination_snapshot'] ?? null ) ) {
			return;
		}
		try {
			$destination = BuyerDestination::normalize( $wc->session->get( 'kiriof_buyer_destination', null ) );
			$posted = $draft_bound ? $destination : BuyerDestination::normalize( json_decode( $data['kiriof_buyer_destination_snapshot'], true ) );
			$district_key = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			if ( 2 !== $destination['version'] || '' === $destination['district_id'] || $destination !== $posted
				|| ( $draft_bound && ( $destination['district_id'] !== ( $data[ $district_key ] ?? null ) || $drafts[ $scope ] !== $wc->session->get( 'kiriof_buyer_destination_coordinates', null ) ) ) ) {
				return;
			}
			$scope = ClassicDistrictAddressSync::separate( $data ) ? 'shipping' : 'billing';
			$address = array();
			foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
				$address[ $field ] = $data[ $scope . '_' . $field ] ?? '';
			}
			if ( BuyerDestination::address( $address ) !== $destination['shipping_address'] ) {
				return;
			}
		} catch ( \InvalidArgumentException $error ) {
			return;
		}
		$payment = $data['payment_method'] ?? '';
		if ( ! is_string( $payment ) || 'cod' === strtolower( trim( $payment ) ) ) {
			return;
		}
		$now = time();
		$packages = $wc->cart->get_shipping_packages();
		if ( ! is_array( $packages ) || 1 !== count( $packages ) ) {
			return;
		}
		$package_key = array_key_first( $packages );
		$package = $packages[ $package_key ];
		$cached = $wc->session->get( 'shipping_for_package_' . $package_key, false );
		if ( ! is_array( $cached ) ) {
			return; // No native cache to bypass: WooCommerce will calculate normally.
		}
		$quotes = $wc->session->get( InstantCheckoutQuoteService::SESSION_KEY, array() );
		foreach ( $cached['rates'] ?? array() as $rate ) {
			if ( ! is_object( $rate ) || ! is_callable( array( $rate, 'get_method_id' ) ) || 'kiriminaja-instant' !== $rate->get_method_id() ) {
				continue;
			}
			$meta = $rate->get_meta_data();
			if ( $this->hasCurrentReceipt( $meta, $quotes, $now ) ) {
				return;
			}
		}
		$contact = InstantCheckoutRecipient::resolve( array( 'destination' => $destination['shipping_address'] ), $wc->customer ?? null );
		$cart_hash = is_callable( array( $wc->cart, 'get_cart_hash' ) ) ? $wc->cart->get_cart_hash() : '';
		$key = hash( 'sha256', wp_json_encode( array( $destination, array_intersect_key( $contact, array_flip( InstantCheckoutRecipient::FIELDS ) ), $payment, $cart_hash ) ) );
		$previous = $wc->session->get( self::RECOVERY_KEY, array() );
		if ( is_array( $previous ) && ( $previous['key'] ?? '' ) === $key && $now - (int) ( $previous['at'] ?? 0 ) < 30 ) {
			return;
		}
		// Fresh provider/eligibility failures must not cause a quote loop.
		$contents_key = hash( 'sha256', wp_json_encode( array_keys( $package['contents'] ?? array() ) ) );
		foreach ( (array) $wc->session->get( 'kiriof_instant_checkout_status', array() ) as $status_key => $status ) {
			$parts = explode( ':', (string) $status_key, 2 );
			if ( ( $parts[1] ?? '' ) === $contents_key && is_array( $status ) && empty( $status['eligible'] ) && $now - (int) ( $status['updated'] ?? 0 ) < 30 && in_array( $status['code'] ?? '', array( 'no_rates', 'quote_empty_or_unavailable', 'outside_instant_radius', 'quote_unavailable', 'quote_failed' ), true ) ) {
				return;
			}
		}
		$wc->session->set( self::RECOVERY_KEY, array( 'key' => $key, 'at' => $now ) );
		ClassicShippingRateCache::invalidate();
	}

	/** This checks cache availability only; final checkout still validates the full quote. */
	private function hasCurrentReceipt( array $meta, $quotes, int $now ): bool {
		$expires = $meta['kiriof_instant_quote_expires'] ?? null;
		$token = $meta['kiriof_instant_quote_token'] ?? null;
		if ( ! is_numeric( $expires ) || (int) $expires <= $now || ! is_string( $token ) || '' === $token ) {
			return false;
		}
		foreach ( is_array( $quotes ) ? $quotes : array() as $quote ) {
			if ( ! is_array( $quote ) || ( $quote['expires'] ?? null ) !== (int) $expires || ! is_array( $quote['rates'] ?? null ) ) {
				continue;
			}
			foreach ( $quote['rates'] as $rate ) {
				if ( is_array( $rate ) && ( $rate['quote_token'] ?? null ) === $token ) {
					return true;
				}
			}
		}
		return false;
	}
}
