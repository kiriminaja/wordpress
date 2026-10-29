<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Restores only explicitly marked, empty Classic address fields from verified districts. */
final class ClassicDistrictAddressSync {
	private $lookup;
	private array $lookups = array();
	private static array $default_lookups = array();
	private bool $checkout_verified = false;

	public function __construct( ?callable $lookup = null ) {
		$this->lookup = $lookup;
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync' ), 5, 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'process' ), 5 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'posted' ), PHP_INT_MAX, 1 );
	}

	/** The input is unslashed; client labels supply lookup postcodes, never address values. */
	public function normalize( array $data ): array {
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			$marker = $data[ 'kiriof_' . $scope . '_derived_address' ] ?? null;
			if ( ! is_string( $marker ) || ! preg_match( '/^(city|state|postcode)(,(city|state|postcode))*$/D', $marker ) || 'ID' !== ( $data[ $scope . '_country' ] ?? null ) ) {
				continue;
			}
			$fields = explode( ',', $marker );
			if ( count( $fields ) !== count( array_unique( $fields ) ) ) {
				continue;
			}
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
		return $data;
	}

	/** WooCommerce's nonce check precedes this hook; its customer setters follow it. */
	public function sync( $post_data ): void {
		if ( ! $this->classicHook( 'woocommerce_checkout_update_order_review' ) || ! is_string( $post_data ) ) {
			return;
		}
		parse_str( $post_data, $data );
		$normalized = $this->normalize( $data );
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
		if ( $data !== $normalized ) {
			$_POST['post_data'] = wp_slash( http_build_query( $normalized, '', '&' ) );
		}
	}

	private function repairReviewAliases( array $data, bool $separate ): void {
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
		$repairs = $this->repairs( $candidate, $this->normalize( $candidate ) );
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
		foreach ( $this->repairs( $data, $this->normalize( $data ) ) as $key => $value ) {
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
		$context = $data;
		foreach ( array( 'billing', 'shipping' ) as $scope ) {
			$district = 'shipping' === $scope ? 'kiriof_shipping_destination_area' : 'kiriof_destination_area';
			foreach ( array( 'kiriof_' . $scope . '_derived_address', $district, $district . '_name', $scope . '_country' ) as $key ) {
				$context[ $key ] = $raw[ $key ] ?? null;
			}
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$key = $scope . '_' . $field;
				if ( array_key_exists( $key, $raw ) && ! is_string( $raw[ $key ] ) ) {
					$context[ $key ] = $raw[ $key ];
				}
			}
		}
		if ( ! self::separate( $data ) ) {
			unset( $context['kiriof_shipping_derived_address'] );
		}
		foreach ( $this->repairs( $context, $this->normalize( $context ) ) as $key => $value ) {
			$data[ $key ] = $value;
			// WooCommerce copies billing to shipping before this filter runs.
			if ( ! self::separate( $data ) && 0 === strpos( $key, 'billing_' ) ) {
				$shipping_key = 'shipping_' . substr( $key, 8 );
				if ( ! array_key_exists( $shipping_key, $data ) || ( is_string( $data[ $shipping_key ] ) && '' === trim( $data[ $shipping_key ] ) ) ) {
					$data[ $shipping_key ] = $value;
				}
			}
		}
		return $data;
	}

	public static function separate( array $data ): bool {
		return ! ( function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only() )
			&& ( is_string( $data['ship_to_different_address'] ?? null ) || is_bool( $data['ship_to_different_address'] ?? null ) )
			&& ! empty( $data['ship_to_different_address'] );
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
