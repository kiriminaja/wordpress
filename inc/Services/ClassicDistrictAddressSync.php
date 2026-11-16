<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Restores Classic address fields and shared billing districts from verified selections. */
final class ClassicDistrictAddressSync {
	private $lookup;
	private array $lookups = array();
	private static array $default_lookups = array();
	private bool $checkout_verified = false;
	private array $checkout_input = array();
	private bool $forced_billing_conflict = false;
	private bool $forced_billing_conflict_notice_added = false;

	public function __construct( ?callable $lookup = null ) {
		$this->lookup = $lookup;
	}

	public static function sharedBilling( array $data ): bool {
		if ( array_key_exists( 'wfacp_billing_same_as_shipping', $data ) ) {
			return in_array( $data['wfacp_billing_same_as_shipping'], array( '0', 0 ), true );
		}
		if ( array_key_exists( 'billing_same_as_shipping', $data ) ) {
			return in_array( $data['billing_same_as_shipping'], array( '1', 1 ), true );
		}
		// A single-address FunnelKit form can omit both billing-toggle flags.
		if ( ! self::shippingFirst( $data ) || 'ID' !== ( $data['shipping_country'] ?? null ) || 'ID' !== ( $data['billing_country'] ?? null ) ) {
			return false;
		}
		$street = $data['shipping_address_1'] ?? null;
		if ( ! is_string( $street ) || '' === trim( $street ) || $street !== ( $data['billing_address_1'] ?? null ) ) {
			return false;
		}
		foreach ( array( 'address_2', 'city', 'state', 'postcode' ) as $field ) {
			$billing = $data[ 'billing_' . $field ] ?? '';
			$shipping = $data[ 'shipping_' . $field ] ?? '';
			if ( ! is_string( $billing ) || ! is_string( $shipping ) || ( '' !== $billing && $billing !== $shipping ) ) {
				return false;
			}
		}
		return true;
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync' ), 5, 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process' ), 5 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'posted' ), PHP_INT_MAX, 1 );
	}

	/** The input is unslashed; client labels supply lookup postcodes, never address values. */
	public function normalize( array $data, bool $complete_billing = true ): array {
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			if ( 'ID' !== ( $data[ $scope . '_country' ] ?? null ) ) {
				continue;
			}
			$fields = $this->derivedFields( $data, $scope );
			$missing = array();
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$key = $scope . '_' . $field;
				if ( array_key_exists( $key, $data ) && ! is_string( $data[ $key ] ) ) {
					continue 2;
				}
				if ( in_array( $field, $fields, true ) && ( ! array_key_exists( $key, $data ) || '' === trim( $data[ $key ] ) ) ) {
					$missing[] = $field;
				}
			}
			$district_key = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			$id = $this->districtId( $data[ $district_key ] ?? null );
			$label = $this->parts( $data[ $district_key . '_name' ] ?? null );
			if ( empty( $missing ) || null === $id || null === $label ) {
				continue;
			}
			$matches = array();
			foreach ( $this->rows( $label[4] ) as $row ) {
				if ( ! is_array( $row ) && ! is_object( $row ) ) {
					continue;
				}
				$row = (array) $row;
				if ( $id === $this->districtId( $row['id'] ?? null ) ) {
					$matches[] = $row;
				}
			}
			if ( 1 !== count( $matches ) ) {
				continue;
			}
			$canonical = $this->parts( $matches[0]['text'] ?? null );
			if ( null === $canonical || $canonical[4] !== $label[4] || ( isset( $matches[0]['zip_code'] ) && $matches[0]['zip_code'] !== $label[4] ) ) {
				continue;
			}
			$values = array( 'city' => $canonical[2], 'postcode' => $canonical[4], 'state' => $this->state( $canonical[3] ) );
			foreach ( $missing as $field ) {
				if ( null !== $values[ $field ] ) {
					$data[ $scope . '_' . $field ] = $values[ $field ];
				}
			}
		}
		if ( $complete_billing && function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only() ) {
			$data = array_replace( $data, $this->forcedBillingFields( $data ) );
		}
		return $data;
	}

	private function forcedBillingShippingConflict( array $data, array $verified ): bool {
		foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
			$key = 'shipping_' . $field;
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( ! is_string( $data[ $key ] ) || ( '' !== trim( $data[ $key ] ) && $data[ $key ] !== $verified[ $field ] ) ) {
				return true;
			}
		}
		return false;
	}

	private function addForcedBillingConflictNotice(): void {
		if ( $this->forced_billing_conflict_notice_added ) {
			return;
		}
		$this->forced_billing_conflict_notice_added = true;
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Please verify your shipping address before placing the order.', 'kiriminaja-official' ), 'error' );
		}
	}

	/** Forced billing forms can omit FunnelKit's optional billing-toggle flags. */
	private function forcedBillingForm( array $data ): bool {
		return function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only()
			&& null !== $this->districtId( $data['_wfacp_post_id'] ?? null )
			&& array_key_exists( 'shipping_country', $data );
	}

	/** Complete only missing billing fields from a verified single-address context. */
	private function forcedBillingFields( array $data ): array {
		$verified = $this->forcedBillingContext( $data );
		if ( null === $verified ) {
			return array();
		}
		$updates = array();
		foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
			if ( ! array_key_exists( 'billing_' . $field, $data ) || '' === trim( $data[ 'billing_' . $field ] ) ) {
				$updates[ 'billing_' . $field ] = $verified[ $field ];
			}
		}
		if ( ! array_key_exists( 'kiriof_destination_area', $data ) || '' === trim( (string) $data['kiriof_destination_area'] ) ) {
			$updates['kiriof_destination_area'] = $verified['id'];
		}
		if ( ! array_key_exists( 'kiriof_destination_area_name', $data ) || '' === trim( (string) $data['kiriof_destination_area_name'] ) ) {
			$updates['kiriof_destination_area_name'] = $verified['label'];
		}
		return $updates;
	}

	/** Return canonical fields independently of whether the form needs repairs. */
	private function forcedBillingContext( array $data ): ?array {
		if ( ! $this->forcedBillingForm( $data ) || 'ID' !== ( $data['shipping_country'] ?? null ) || 'ID' !== ( $data['billing_country'] ?? null ) ) {
			return null;
		}
		if ( array_key_exists( 'shipping_method', $data ) ) {
			if ( ! is_array( $data['shipping_method'] ) ) {
				return null;
			}
			foreach ( $data['shipping_method'] as $method ) {
				if ( ! is_string( $method ) || in_array( explode( ':', $method, 2 )[0], array( 'local_pickup', 'pickup_location' ), true ) ) {
					return null;
				}
			}
		}
		foreach ( array( 'address_1', 'address_2' ) as $field ) {
			$billing = array_key_exists( 'billing_' . $field, $data ) ? $data[ 'billing_' . $field ] : '';
			$shipping = array_key_exists( 'shipping_' . $field, $data ) ? $data[ 'shipping_' . $field ] : '';
			if ( ! is_string( $billing ) || ! is_string( $shipping ) || $billing !== $shipping || ( 'address_1' === $field && '' === trim( $billing ) ) ) {
				return null;
			}
		}
		foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
			$billing = array_key_exists( 'billing_' . $field, $data ) ? $data[ 'billing_' . $field ] : '';
			$shipping = array_key_exists( 'shipping_' . $field, $data ) ? $data[ 'shipping_' . $field ] : '';
			if ( ! is_string( $billing ) || ! is_string( $shipping ) || ( '' !== trim( $billing ) && $billing !== $shipping ) ) {
				return null;
			}
		}
		foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name' ) as $key ) {
			if ( array_key_exists( $key, $data ) && ! is_string( $data[ $key ] ) && ! is_int( $data[ $key ] ) ) {
				return null;
			}
		}
		if ( ! $this->reviewAliasesMatch( $data ) ) {
			return null;
		}
		$verified = $this->verifiedShippingDistrict( $data );
		if ( null === $verified ) {
			return null;
		}
		foreach ( array( 'kiriof_destination_area' => $verified['id'], 'kiriof_destination_area_name' => $verified['label'] ) as $key => $value ) {
			if ( array_key_exists( $key, $data ) && '' !== trim( (string) $data[ $key ] ) && (string) $data[ $key ] !== $value ) {
				return null;
			}
		}
		if ( 'woocommerce_checkout_update_order_review' === current_filter() ) {
			foreach ( array( '', 's_' ) as $prefix ) {
				foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
					$key = $prefix . $field;
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified the nonce; compare original unslashed aliases only.
					if ( array_key_exists( $key, $_POST ) && ( ! is_string( $_POST[ $key ] ) || ( '' !== trim( wp_unslash( $_POST[ $key ] ) ) && wp_unslash( $_POST[ $key ] ) !== $verified[ $field ] ) ) ) {
						return null;
					}
				}
			}
		}
		return $verified;
	}

	private function reviewAliasesMatch( array $data ): bool {
		if ( 'woocommerce_checkout_update_order_review' !== current_filter() || ! $this->forcedBillingForm( $data ) ) {
			return true;
		}
		foreach ( array( '' => 'billing', 's_' => 'shipping' ) as $prefix => $scope ) {
			foreach ( array( 'country', 'address', 'address_2' ) as $field ) {
				$key = $prefix . $field;
				if ( ! array_key_exists( $key, $_POST ) ) {
					continue;
				}
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified the review nonce; compare without accepting address values.
				$value = wp_unslash( $_POST[ $key ] );
				$expected_field = 'address' === $field ? 'address_1' : $field;
				$expected = $data[ $scope . '_' . $expected_field ] ?? '';
				if ( ! is_string( $value ) || ! is_string( $expected ) || $value !== $expected ) {
					return false;
				}
			}
		}
		return true;
	}

	private function verifiedShippingDistrict( array $data, bool $strict = true ): ?array {
		$id = $this->districtId( $data['kiriof_shipping_destination_area'] ?? null );
		$postcode = $data['shipping_postcode'] ?? null;
		if ( array_key_exists( 'kiriof_shipping_destination_area_name', $data ) && ! is_string( $data['kiriof_shipping_destination_area_name'] ) ) {
			return null;
		}
		if ( null === $id || ! is_string( $postcode ) || ! preg_match( '/\A[0-9]{5}\z/', $postcode ) ) {
			return null;
		}
		$matches = array();
		foreach ( $this->rows( $postcode ) as $row ) {
			if ( ( is_array( $row ) || is_object( $row ) ) && $id === $this->districtId( ( (array) $row )['id'] ?? null ) ) {
				$matches[] = (array) $row;
			}
		}
		if ( 1 !== count( $matches ) ) {
			return null;
		}
		$canonical = $this->parts( $matches[0]['text'] ?? null );
		if ( null === $canonical || $canonical[4] !== $postcode || ( isset( $matches[0]['zip_code'] ) && $matches[0]['zip_code'] !== $postcode ) ) {
			return null;
		}
		$state = $strict ? $this->state( $canonical[3] ) : null;
		if ( $strict && null === $state ) {
			return null;
		}
		foreach ( $strict ? array( 'city' => $canonical[2], 'state' => $state, 'postcode' => $postcode ) : array() as $field => $value ) {
			if ( '' !== trim( (string) ( $data[ 'shipping_' . $field ] ?? '' ) ) && $data[ 'shipping_' . $field ] !== $value ) {
				return null;
			}
		}
		return array( 'id' => (string) $id, 'label' => implode( ', ', $canonical ), 'city' => $canonical[2], 'state' => $state, 'postcode' => $postcode );
	}

	private function derivedFields( array $data, string $scope ): array {
		$key = 'kiriof_' . $scope . '_derived_address';
		if ( array_key_exists( $key, $data ) ) {
			$marker = $data[ $key ];
			if ( ! is_string( $marker ) || ! preg_match( '/^(city|state|postcode)(,(city|state|postcode))*$/D', $marker ) ) {
				return array();
			}
			$fields = explode( ',', $marker );
			return count( $fields ) === count( array_unique( $fields ) ) ? $fields : array();
		}
		if ( null === $this->districtId( $data['_wfacp_post_id'] ?? null ) ) {
			return array();
		}
		// Removed FunnelKit fields are distinct from a buyer clearing a present field.
		return array_values( array_filter( array( 'city', 'state', 'postcode' ), static fn( $field ) => ! array_key_exists( $scope . '_' . $field, $data ) ) );
	}

	private function sharedBillingDistrict( array $data ): array {
		if ( function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only() ) {
			return $this->forcedBillingFields( $this->normalize( $data, false ) );
		}
		if ( ! self::shippingFirst( $data ) || ! self::sharedBilling( $data ) || 'ID' !== ( $data['shipping_country'] ?? null ) ) {
			return array();
		}
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( $wc && isset( $wc->cart ) && is_callable( array( $wc->cart, 'get_cart' ) ) ) {
			$physical = false;
			foreach ( $wc->cart->get_cart() as $item ) {
				$product = $item['data'] ?? null;
				if ( ( ! isset( $item['quantity'] ) || 0 < (float) $item['quantity'] ) && ( ! is_object( $product ) || ! is_callable( array( $product, 'needs_shipping' ) ) || $product->needs_shipping() ) ) {
					$physical = true;
					break;
				}
			}
			if ( ! $physical ) {
				return array();
			}
		}
		if ( array_key_exists( 'shipping_method', $data ) ) {
			if ( ! is_array( $data['shipping_method'] ) ) {
				return array();
			}
			foreach ( $data['shipping_method'] as $method ) {
				if ( ! is_string( $method ) || in_array( explode( ':', $method, 2 )[0], array( 'local_pickup', 'pickup_location' ), true ) ) {
					return array();
				}
			}
		}
		foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name' ) as $key ) {
			if ( array_key_exists( $key, $data ) && ! is_string( $data[ $key ] ) && ! is_int( $data[ $key ] ) ) {
				return array();
			}
		}
		$data = $this->normalize( $data );
		if ( array_key_exists( 'kiriof_shipping_destination_area_name', $data ) && ! is_string( $data['kiriof_shipping_destination_area_name'] ) ) {
			return array();
		}
		$verified = $this->verifiedShippingDistrict( $data, false );
		if ( null === $verified ) {
			return array();
		}
		$updates = array();
		foreach ( array( 'kiriof_destination_area', 'kiriof_shipping_destination_area' ) as $key ) {
			$updates[ $key ] = $verified['id'];
			$updates[ $key . '_name' ] = $verified['label'];
		}
		return $updates;
	}

	/** WooCommerce's nonce check precedes this hook; its customer setters follow it. */
	public function sync( $post_data ): void {
		if ( ! $this->classicHook( 'woocommerce_checkout_update_order_review' ) || ! is_string( $post_data ) ) {
			return;
		}
		parse_str( $post_data, $data );
		$normalized = $this->normalize( $data );
		foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name' ) as $key ) {
			if ( array_key_exists( $key, $normalized ) && ( ! array_key_exists( $key, $data ) || $data[ $key ] !== $normalized[ $key ] ) ) {
				$_POST[ $key ] = wp_slash( $normalized[ $key ] );
			}
		}
		$separate = self::separate( $data );
		foreach ( $this->repairs( $data, $normalized ) as $key => $value ) {
			$_POST[ $key ] = wp_slash( $value );
			list( $scope, $field ) = explode( '_', $key, 2 );
			if ( 'billing' === $scope ) {
				$_POST[ $field ] = wp_slash( $value );
				if ( ! $separate ) {
					$_POST[ 's_' . $field ] = wp_slash( $value );
				}
			} elseif ( $separate ) {
				$_POST[ 's_' . $field ] = wp_slash( $value );
			}
		}
		$this->repairReviewAliases( $data, $separate );
		if ( $separate && self::shippingFirst( $data ) ) {
			// WC_AJAX applies these aliases after all review hooks, not the form fields.
			foreach ( BuyerDestination::ADDRESS_FIELDS as $field ) {
				$value = $normalized[ 'shipping_' . $field ] ?? '';
				$alias = 'address_1' === $field ? 'address' : $field;
				$_POST[ 's_' . $alias ] = is_string( $value ) ? wp_slash( $value ) : '';
			}
		}
		if ( $data !== $normalized ) {
			$_POST['post_data'] = wp_slash( http_build_query( $normalized, '', '&' ) );
		}
	}

	private function repairReviewAliases( array $data, bool $separate ): void {
		if ( $this->forcedBillingForm( $data ) ) {
			$verified = $this->forcedBillingContext( $this->normalize( $data, false ) );
			if ( null === $verified ) {
				return;
			}
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				foreach ( array( $field, 's_' . $field ) as $alias ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified the review nonce; preserve nonblank or malformed aliases.
					if ( ! array_key_exists( $alias, $_POST ) || ( is_string( $_POST[ $alias ] ) && '' === trim( wp_unslash( $_POST[ $alias ] ) ) ) ) {
						$_POST[ $alias ] = wp_slash( $verified[ $field ] );
					}
				}
			}
			return;
		}
		$candidate = $data;
		$targets = array();
		foreach ( array( '' => 'billing', 's_' => $separate ? 'shipping' : 'billing' ) as $prefix => $scope ) {
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$alias = $prefix . $field;
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verified the review nonce; only absence or blank strings are inspected.
				$value = $_POST[ $alias ] ?? null;
				if ( array_key_exists( $alias, $_POST ) && ( ! is_string( $value ) || '' !== trim( $value ) ) ) {
					continue;
				}
				$key = $scope . '_' . $field;
				if ( array_key_exists( $key, $data ) && ! is_string( $data[ $key ] ) ) {
					continue;
				}
				$targets[ $alias ] = $key;
				// Force verification instead of trusting a populated serialized native value.
				$candidate[ $key ] = '';
			}
		}
		if ( empty( $targets ) ) {
			return;
		}
		$repairs = $this->repairs( $candidate, $this->normalize( $candidate, false ) );
		foreach ( $targets as $alias => $key ) {
			if ( array_key_exists( $key, $repairs ) ) {
				$_POST[ $alias ] = wp_slash( $repairs[ $key ] );
			}
		}
	}

	/** Runs after the native checkout nonce check, before get_posted_data and validation. */
	public function process(): void {
		if ( ! $this->classicHook( 'woocommerce_checkout_process' ) ) {
			return;
		}
		$this->checkout_verified = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WC_Checkout; normalize validates the address evidence.
		$data = wp_unslash( $_POST );
		$this->checkout_input = $data;
		$this->forced_billing_conflict = false;
		$this->forced_billing_conflict_notice_added = false;
		if ( $this->forcedBillingForm( $data ) ) {
			$verified = $this->forcedBillingContext( $this->normalize( $data, false ) );
			if ( null !== $verified && $this->forcedBillingShippingConflict( $data, $verified ) ) {
				$this->forced_billing_conflict = true;
				$this->addForcedBillingConflictNotice();
				return;
			}
		}
		foreach ( $this->repairs( $data, $this->normalize( $data ) ) as $key => $value ) {
			$_POST[ $key ] = wp_slash( $value );
		}
		foreach ( $this->sharedBillingDistrict( $data ) as $key => $value ) {
			$_POST[ $key ] = wp_slash( $value );
		}
	}

	public function posted( array $data ): array {
		if ( ! $this->checkout_verified || ! $this->classicHook( 'woocommerce_checkout_posted_data' ) ) {
			return $data;
		}
		// Custom marker fields need not be registered in WooCommerce's checkout fieldset.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The process hook verified the lifecycle; normalize validates this evidence.
		$raw = wp_unslash( $_POST );
		if ( $this->forcedBillingForm( $this->checkout_input ) ) {
			// Validate original evidence before WooCommerce's copies or synthetic blanks can hide conflicts.
			$original = $this->normalize( $this->checkout_input, false );
			if ( null === $this->forcedBillingContext( $original ) ) {
				return $data;
			}
			$updates = $this->forcedBillingFields( $original );
			$effective = $original;
			foreach ( array( 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $field ) {
				$key = 'billing_' . $field;
				if ( array_key_exists( $key, $data ) && ( ! is_string( $data[ $key ] ) || '' !== trim( $data[ $key ] ) ) ) {
					$effective[ $key ] = $data[ $key ];
				}
			}
			foreach ( array( 'kiriof_destination_area', 'kiriof_destination_area_name' ) as $key ) {
				if ( array_key_exists( $key, $data ) && '' !== $data[ $key ] ) {
					$effective[ $key ] = $data[ $key ];
				}
			}
			$verified = $this->forcedBillingContext( $effective );
			if ( null === $verified ) {
				return $data;
			}
			if ( $this->forced_billing_conflict || $this->forcedBillingShippingConflict( $data, $verified ) ) {
				$this->addForcedBillingConflictNotice();
				return $data;
			}
			$data = array_replace( $data, $updates );
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				foreach ( array( 'billing_', 'shipping_' ) as $prefix ) {
					$key = $prefix . $field;
					if ( ! array_key_exists( $key, $data ) || ( is_string( $data[ $key ] ) && '' === trim( $data[ $key ] ) ) ) {
						$data[ $key ] = $verified[ $field ];
					}
				}
			}
			foreach ( array( 'kiriof_destination_area' => 'id', 'kiriof_destination_area_name' => 'label' ) as $key => $field ) {
				if ( ! array_key_exists( $key, $data ) || ( is_string( $data[ $key ] ) && '' === trim( $data[ $key ] ) ) ) {
					$data[ $key ] = $verified[ $field ];
				}
			}
			return $data;
		}
		if ( self::separate( $raw ) && self::shippingFirst( $raw ) ) {
			foreach ( array_merge( BuyerDestination::ADDRESS_FIELDS, InstantCheckoutRecipient::FIELDS ) as $field ) {
				$key = 'shipping_' . $field;
				if ( array_key_exists( $key, $raw ) ) {
					$data[ $key ] = is_string( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
				}
			}
		}
		$context = $data;
		foreach ( array( '_wfacp_post_id', 'wfacp_billing_same_as_shipping', 'billing_same_as_shipping' ) as $key ) {
			if ( array_key_exists( $key, $raw ) ) {
				$context[ $key ] = $raw[ $key ];
			}
		}
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			$district = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			foreach ( array( $district, $district . '_name', $scope . '_country' ) as $key ) {
				unset( $context[ $key ] );
				if ( array_key_exists( $key, $raw ) ) {
					$context[ $key ] = $raw[ $key ];
				}
			}
			$marker = 'kiriof_' . $scope . '_derived_address';
			unset( $context[ $marker ] );
			if ( array_key_exists( $marker, $raw ) ) {
				$context[ $marker ] = $raw[ $marker ];
			}
			$fields = $this->derivedFields( $this->checkout_input, $scope );
			if ( ! array_key_exists( $marker, $raw ) ) {
				$context[ $marker ] = implode( ',', $fields );
			}
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$key = $scope . '_' . $field;
				if ( array_key_exists( $key, $raw ) && ! is_string( $raw[ $key ] ) ) {
					$context[ $key ] = $raw[ $key ];
				} elseif ( in_array( $field, $fields, true ) && ( ! array_key_exists( $key, $data ) || ( is_string( $data[ $key ] ) && '' === trim( $data[ $key ] ) ) ) ) {
					// Revalidate the current selection when Woo omits an unregistered field.
					unset( $context[ $key ] );
				}
			}
		}
		if ( ! self::separate( $context ) ) {
			unset( $context['kiriof_shipping_derived_address'] );
		}
		foreach ( $this->repairs( $context, $this->normalize( $context ) ) as $key => $value ) {
			if ( ! self::separate( $context ) && 0 === strpos( $key, 'shipping_' ) ) {
				continue;
			}
			$data[ $key ] = $value;
			// WooCommerce copies billing to shipping before this filter runs.
			if ( ! self::separate( $context ) && 0 === strpos( $key, 'billing_' ) ) {
				$shipping_key = 'shipping_' . substr( $key, 8 );
				if ( ! array_key_exists( $shipping_key, $data ) || ( is_string( $data[ $shipping_key ] ) && '' === trim( $data[ $shipping_key ] ) ) ) {
					$data[ $shipping_key ] = $value;
				}
			}
		}
		// FunnelKit can replace custom fields after WooCommerce has sanitized them.
		foreach ( $this->sharedBillingDistrict( $raw ) as $key => $value ) {
			$data[ $key ] = $value;
		}
		return $data;
	}

	public static function separate( array $data ): bool {
		if ( function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only() ) {
			return false;
		}
		return self::shippingFirst( $data ) || ( ( is_string( $data['ship_to_different_address'] ?? null ) || is_bool( $data['ship_to_different_address'] ?? null ) )
			&& ! empty( $data['ship_to_different_address'] ) );
	}

	/** FunnelKit form.php and its billing toggle keep shipping as the physical address. */
	public static function shippingFirst( array $data ): bool {
		$id = $data['_wfacp_post_id'] ?? null;
		if ( ( ! is_string( $id ) && ! is_int( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || ! array_key_exists( 'shipping_country', $data ) ) {
			return false;
		}
		// The hidden wfacp flag is 0 for shared billing and 1 for different billing.
		// The official xlplugins compatibility gist also supports the visible flag.
		foreach ( array( 'wfacp_billing_same_as_shipping', 'billing_same_as_shipping' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				return in_array( $data[ $key ], array( '0', '1', 0, 1 ), true );
			}
		}
		$separate = $data['ship_to_different_address'] ?? null;
		if ( ( is_string( $separate ) || is_bool( $separate ) ) && ! empty( $separate ) ) {
			return true;
		}
		return ! array_key_exists( 'billing_address_1', $data );
	}

	private function classicHook( string $hook ): bool {
		return $hook === current_filter() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	private function repairs( array $before, array $after ): array {
		$repairs = array();
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$key = $scope . '_' . $field;
				if ( array_key_exists( $key, $after ) && ( ! array_key_exists( $key, $before ) || $before[ $key ] !== $after[ $key ] ) ) {
					$repairs[ $key ] = $after[ $key ];
				}
			}
		}
		return $repairs;
	}

	private function districtId( $id ): ?int {
		if ( ( ! is_string( $id ) && ! is_int( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) {
			return null;
		}
		$value = filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return false === $value ? null : $value;
	}

	private function parts( $text ): ?array {
		if ( ! is_string( $text ) || strlen( $text ) > 1000 ) {
			return null;
		}
		$parts = array_map( 'trim', explode( ',', $text ) );
		if ( 5 !== count( $parts ) || in_array( '', $parts, true ) || ! preg_match( '/^[0-9]{5}$/D', $parts[4] ) ) {
			return null;
		}
		foreach ( $parts as $part ) {
			if ( sanitize_text_field( $part ) !== $part ) {
				return null;
			}
		}
		return $parts;
	}

	private function rows( string $postcode ): array {
		$cache = null === $this->lookup ? self::$default_lookups : $this->lookups;
		if ( array_key_exists( $postcode, $cache ) ) {
			return $cache[ $postcode ];
		}
		$rows = array();
		try {
			$response = null === $this->lookup ? kiriof_checkout_service_factory()->districtSearch( $postcode ) : call_user_func( $this->lookup, $postcode );
			if ( is_object( $response ) && 200 === ( $response->status ?? null ) && is_array( $response->data ?? null ) ) {
				$rows = $response->data;
			}
		} catch ( \Throwable $error ) {
			// An unavailable lookup leaves WooCommerce's ordinary validation in charge.
			$rows = array();
		}
		if ( null === $this->lookup ) {
			self::$default_lookups[ $postcode ] = $rows;
		} else {
			$this->lookups[ $postcode ] = $rows;
		}
		return $rows;
	}

	private function state( string $province ): ?string {
		$wc = function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || ! isset( $wc->countries ) || ! is_callable( array( $wc->countries, 'get_states' ) ) ) {
			return null;
		}
		$matches = array();
		foreach ( (array) $wc->countries->get_states( 'ID' ) as $code => $name ) {
			if ( is_string( $name ) && $this->provinceName( $name ) === $this->provinceName( $province ) ) {
				$matches[] = (string) $code;
			}
		}
		return 1 === count( $matches ) ? $matches[0] : null;
	}

	private function provinceName( string $name ): string {
		$name = strtolower( trim( html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$aliases = array(
			'di yogyakarta' => 'daerah istimewa yogyakarta',
			'd.i. yogyakarta' => 'daerah istimewa yogyakarta',
			'dki jakarta' => 'daerah khusus ibukota jakarta',
		);
		return $aliases[ $name ] ?? $name;
	}
}
