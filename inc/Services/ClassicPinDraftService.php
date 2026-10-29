<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates stay session-only until a verified Classic request supplies their address. */
final class ClassicPinDraftService {
	public const SESSION_KEY = 'kiriof_classic_pin_drafts';
	private const SCOPE_KEY = 'kiriof_classic_pin_scope';
	private ClassicDistrictAddressSync $address_sync;
	private $lookup;
	private bool $checkout_verified = false;
	private array $checkout_input = array();

	public function __construct( ?ClassicDistrictAddressSync $address_sync = null, ?callable $lookup = null ) {
		$this->address_sync = $address_sync ?? new ClassicDistrictAddressSync();
		$this->lookup = $lookup;
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'review' ), 8, 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process' ), 8 );
		// Registered after address repair at the same last priority.
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'posted' ), PHP_INT_MAX, 1 );
	}

	/** Called only after the session-save AJAX handler verifies its nonce. */
	public function save( array $data ): void {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! $wc->session || ! $wc->cart || ! $wc->cart->needs_shipping() ) {
			if ( $wc && $wc->session && $wc->cart && ! $wc->cart->needs_shipping() ) {
				$wc->session->set( self::SESSION_KEY, null );
				$wc->session->set( self::SCOPE_KEY, null );
				$this->destination( null );
			}
			throw new \InvalidArgumentException( 'Checkout session unavailable.', 10 );
		}
		$scope = $data['address_scope'] ?? null;
		if ( ! in_array( $scope, array( 'billing', 'shipping' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid address scope.', 3 );
		}
		if ( 'ID' !== ( $data['country'] ?? null ) ) {
			$wc->session->set( self::SESSION_KEY, null );
			$wc->session->set( self::SCOPE_KEY, null );
			$this->destination( null );
			if ( is_string( $data['country'] ?? null ) && preg_match( '/^[A-Z]{2}$/D', $data['country'] ) && array_key_exists( 'coordinates', $data ) && null === $data['coordinates'] ) {
				return;
			}
			throw new \InvalidArgumentException( 'Invalid pin country.', 4 );
		}
		if ( ! array_key_exists( 'coordinates', $data ) ) {
			throw new \InvalidArgumentException( 'Invalid coordinates.', 5 );
		}
		$coordinates = null;
		if ( null !== $data['coordinates'] ) {
			$value = $data['coordinates'];
			if ( ! is_array( $value ) || 2 !== count( $value ) || ! array_key_exists( 'latitude', $value ) || ! array_key_exists( 'longitude', $value ) ) {
				throw new \InvalidArgumentException( 'Invalid coordinates.', 5 );
			}
			try {
				$coordinates = array( 'latitude' => BuyerDestination::coordinate( $value['latitude'], 90 ), 'longitude' => BuyerDestination::coordinate( $value['longitude'], 180 ) );
			} catch ( \InvalidArgumentException $error ) {
				throw new \InvalidArgumentException( 'Invalid coordinates.', 5 );
			}
			if ( '' === $coordinates['latitude'] || '' === $coordinates['longitude'] ) {
				throw new \InvalidArgumentException( 'Invalid coordinates.', 5 );
			}
		}
		$drafts = (array) $wc->session->get( self::SESSION_KEY, array() );
		// Once another scope owns a new pin, its older counterpart cannot be revived.
		unset( $drafts[ 'shipping' === $scope ? 'billing' : 'shipping' ] );
		$drafts[ $scope ] = $coordinates;
		$wc->session->set( self::SESSION_KEY, $drafts );
		// A new draft must never leave rates for the previously bound coordinates usable.
		if ( $scope !== $wc->session->get( self::SCOPE_KEY, $scope ) ) {
			$this->destination( null );
		} else {
			$saved = $wc->session->get( 'kiriof_buyer_destination_coordinates', null );
			if ( null === $coordinates || $saved !== $coordinates ) {
				$this->destination( null );
			}
		}
	}

	public function review( $post_data ): void {
		if ( ! $this->hook( 'woocommerce_checkout_update_order_review' ) || ! is_string( $post_data ) ) {
			return;
		}
		parse_str( $post_data, $data );
		$data = $this->address_sync->normalize( $data );
		$wc = function_exists( 'WC' ) ? WC() : null;
		$has_drafts = $wc && $wc->session && is_array( $wc->session->get( self::SESSION_KEY, null ) );
		if ( $this->bind( $data ) ) {
			$data['kiriof_buyer_destination_snapshot'] = wp_json_encode( WC()->session->get( 'kiriof_buyer_destination', null ) );
			$_POST['post_data'] = wp_slash( http_build_query( $data, '', '&' ) );
		} elseif ( $has_drafts ) {
			unset( $data['kiriof_buyer_destination_snapshot'] );
			$_POST['post_data'] = wp_slash( http_build_query( $data, '', '&' ) );
		}
	}

	public function process(): void {
		if ( $this->hook( 'woocommerce_checkout_process' ) ) {
			$this->checkout_verified = true;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified its nonce; retain explicit fields before legacy callbacks can inject session history.
			$this->checkout_input = wp_unslash( $_POST );
		}
	}

	public function posted( array $data ): array {
		if ( ! $this->checkout_verified || ! $this->hook( 'woocommerce_checkout_posted_data' ) ) {
			return $data;
		}
		$raw = $this->checkout_input;
		$context = $data;
		foreach ( array( '_wfacp_post_id', 'wfacp_billing_same_as_shipping', 'billing_same_as_shipping' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$context[ $key ] = $raw[ $key ];
			}
		}
		foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name', 'kiriof_shipping_destination_area', 'kiriof_shipping_destination_area_name' ) as $key ) {
			$context[ $key ] = $raw[ $key ] ?? null;
		}
		// Woo may fill omitted country fields from customer history; require current form evidence.
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			$context[ $scope . '_country' ] = $raw[ $scope . '_country' ] ?? null;
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		$has_drafts = $wc && $wc->session && is_array( $wc->session->get( self::SESSION_KEY, null ) );
		if ( $this->bind( $context ) ) {
			$_POST['kiriof_buyer_destination_snapshot'] = wp_slash( wp_json_encode( WC()->session->get( 'kiriof_buyer_destination', null ) ) );
		} elseif ( $has_drafts ) {
			// Draft clears/incomplete addresses must not resurrect a submitted older snapshot.
			unset( $_POST['kiriof_buyer_destination_snapshot'] );
		}
		return $data;
	}

	private function hook( string $name ): bool {
		return $name === current_filter() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/** No session district or address fallback is allowed here. */
	private function bind( array $data ): bool {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! $wc->session ) {
			return false;
		}
		$drafts = $wc->session->get( self::SESSION_KEY, null );
		if ( ! $wc->cart || ! $wc->cart->needs_shipping() ) {
			$wc->session->set( self::SESSION_KEY, null );
			$wc->session->set( self::SCOPE_KEY, null );
			$this->destination( null );
			return false;
		}
		$scope = ClassicDistrictAddressSync::separate( $data ) ? 'shipping' : 'billing';
		$previous = $wc->session->get( self::SCOPE_KEY, null );
		if ( null !== $previous && $previous !== $scope && is_array( $drafts ) ) {
			unset( $drafts[ $previous ] );
			$wc->session->set( self::SESSION_KEY, $drafts );
			$this->destination( null );
		}
		$wc->session->set( self::SCOPE_KEY, $scope );
		if ( 'ID' !== ( $data[ $scope . '_country' ] ?? null ) ) {
			$wc->session->set( self::SESSION_KEY, null );
			$this->destination( null );
			return false;
		}
		if ( ! is_array( $drafts ) || ! array_key_exists( $scope, $drafts ) ) {
			return false;
		}
		$this->destinationFromDraft( $data, $scope, $drafts[ $scope ] );
		return null !== $wc->session->get( 'kiriof_buyer_destination', null );
	}

	private function destinationFromDraft( array $data, string $scope, $coordinates ): void {
		$destination = null;
		try {
			if ( ! is_array( $coordinates ) ) {
				throw new \InvalidArgumentException();
			}
			$address = array();
			foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
				$address[ $field ] = $data[ $scope . '_' . $field ] ?? ( 'address_2' === $field ? '' : null );
			}
			$address = BuyerDestination::address( $address );
			foreach ( array( 'address_1', 'city', 'state' ) as $field ) {
				if ( '' === trim( $address[ $field ] ) ) {
					throw new \InvalidArgumentException();
				}
			}
			if ( ! preg_match( '/^[0-9]{5}$/D', $address['postcode'] ) ) {
				throw new \InvalidArgumentException();
			}
			$key = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			$id = $data[ $key ] ?? null;
			if ( ( ! is_string( $id ) && ! is_int( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) {
				throw new \InvalidArgumentException();
			}
			$result = null === $this->lookup ? kiriof_checkout_service_factory()->districtSearch( $address['postcode'] ) : call_user_func( $this->lookup, $address['postcode'] );
			$matches = array();
			foreach ( is_object( $result ) && 200 === ( $result->status ?? null ) && is_array( $result->data ?? null ) ? $result->data : array() as $row ) {
				$row = (array) $row;
				if ( (string) $id === (string) ( $row['id'] ?? '' ) ) {
					$matches[] = $row;
				}
			}
			if ( 1 !== count( $matches ) || ! is_string( $matches[0]['text'] ?? null ) ) {
				throw new \InvalidArgumentException();
			}
			$parts = array_map( 'trim', explode( ',', $matches[0]['text'] ) );
			if ( 5 !== count( $parts ) || in_array( '', $parts, true ) || $address['postcode'] !== $parts[4] || ( isset( $matches[0]['zip_code'] ) && (string) $matches[0]['zip_code'] !== $parts[4] ) ) {
				throw new \InvalidArgumentException();
			}
			$destination = BuyerDestination::normalize( array(
				'district_id' => (string) $id, 'district_label' => $matches[0]['text'], 'postcode' => $address['postcode'], 'country' => 'ID',
				'address_type' => 'shipping', 'version' => 2, 'shipping_address' => $address,
				'destination_latitude' => $coordinates['latitude'] ?? null, 'destination_longitude' => $coordinates['longitude'] ?? null,
			) );
		} catch ( \Throwable $error ) {
			// Incomplete or unavailable evidence keeps the draft, never an older bound pin.
			$destination = null;
		}
		$this->destination( $destination );
	}

	private function destination( ?array $destination ): void {
		$session = WC()->session;
		$coordinates = null === $destination ? null : array( 'latitude' => $destination['destination_latitude'], 'longitude' => $destination['destination_longitude'] );
		if ( $session->get( 'kiriof_buyer_destination', null ) === $destination && $session->get( 'kiriof_buyer_destination_coordinates', null ) === $coordinates ) {
			return;
		}
		$session->set( 'kiriof_buyer_destination', $destination );
		$session->set( 'kiriof_buyer_destination_coordinates', $coordinates );
		$session->set( 'kiriof_instant_checkout_quotes', array() );
		$session->set( 'kiriof_instant_checkout_status', array() );
		ClassicShippingRateCache::invalidate();
	}
}
