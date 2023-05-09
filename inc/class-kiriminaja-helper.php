<?php

/**
 * KiriminAja Helper Class
 */
class KiriminAja_Helper {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setting = new KiriminAja_Setting();
	}

	/**
	 * Get country on session
	 *
	 * @param  string $context    Context.
	 * @return string Country id.
	 */
	public function get_country_session( $context = 'billing' ) {
		$country      = '';

		if( 'billing' === $context ) {
			$country = WC()->customer->get_billing_country();
		} else {
			$country = WC()->customer->get_shipping_country();
		}

		if( empty( $country ) ) {
			$country = 'ID';
		}

		// add filter hook to force change country ability.
		return apply_filters( 'kiriminaja_session_country', $country, $context );
	}

	/**
	 * Is plugin active
	 *
	 * @return boolean Status.
	 */
	public function is_plugin_active() {
		// for front.
		if ( false === $this->setting->get( 'enable' ) ) {
			return false;
		}

		// curl must active.
		if ( ! function_exists( 'curl_version' ) ) {
			return false;
		}

		// token.
		$token = $this->setting->get( 'token' );
		if ( empty( $token ) ) {
			return false;
		}

		// base city.
		$base_city = $this->setting->get( 'store_district' );
		if ( empty( $base_city ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is admin active
	 *
	 * @return boolean Status.
	 */
	public function is_token_set() {
		$token = $this->setting->get( 'token' );
		return ! empty( $token );
	}

	/**
	 * Is store set
	 *
	 * @return boolean Status.
	 */
	public function is_store_set() {
		$store_name     = $this->setting->get( 'store_name' );
		$store_address  = $this->setting->get( 'store_address' );
		$store_province = $this->setting->get( 'store_province' );
		$store_city     = $this->setting->get( 'store_city' );
		$store_district = $this->setting->get( 'store_district' );
		$store_zipcode  = $this->setting->get( 'store_zipcode' );
		$store_phone    = $this->setting->get( 'store_phone' );
		$couriers       = $this->setting->get( 'couriers' );
		if ( empty( $store_name ) || empty( $store_address ) || empty( $store_province ) || empty( $store_city ) || empty( $store_district ) || empty( $store_zipcode ) || empty( $store_phone ) || empty( $couriers ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Compare current WC version
	 *
	 * @param  string $operator Comparing operator.
	 * @param  string $version  Version to check.
	 * @return boolean           Version satatus.
	 */
	public function compare_wc_version( $operator = '>=', $version = '3.0' ) {
		global $woocommerce;
		return version_compare( $woocommerce->version, $version, $operator );
	}

	/**
	 * Check if woocommerce is active
	 *
	 * @return boolean Is active.
	 */
	public static function is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Clear all cached WC's shipping costs
	 */
	public function clear_cached_costs() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%wc_ship%'" );
	}

	/**
	 * Get product weight.
	 * If volume calculation enabled, the weight is calculated from product dimensions
	 * But if product weight is higher than weight from calculated dimensions, use the product weight instead.
	 *
	 * @param  object $product Product object.
	 * @return float           Product weight on kg.
	 */
	public function get_product_weight( $product ) {
		if ( ! $product || $product->is_virtual() ) {
			return 0;
		}
		if ( ! empty( $product->get_length() ) && ! empty( $product->get_width() ) && ! empty( $product->get_height() ) ) {
			$product_weight = ( $this->dimension_convert( $product->get_length() ) * $this->dimension_convert( $product->get_width() ) * $this->dimension_convert( $product->get_height() ) ) / 6000;
			if ( $product->has_weight() ) {
				$product_weight = max( $product_weight, $this->weight_convert( $product->get_weight() ) ); // get highest value between volumetric or weight.
			}
		} else {
			$product_weight = $product->has_weight() ? $this->weight_convert( $product->get_weight() ) : $this->setting->get( 'default_weight' );
		}
		return apply_filters( 'kiriminaja_get_product_weight', $product_weight, $product, $this->setting->get_all() );
	}

	/**
	 * Get order total weight
	 *
	 * @param  object $order Order object.
	 * @return float         Total weight.
	 */
	public function get_order_weight( $order ) {
		$weight = 0;
		foreach ( $order->get_items() as $item ) {
			$weight += ( $this->get_product_weight( $item->get_product() ) * $item->get_quantity() );
		}
		return apply_filters( 'kiriminaja_get_order_weight', $weight, $order );
	}

	/**
	 * Get total weight of cart contents
	 *
	 * @param  array $contents Cart contents.
	 * @return float            Cart weight.
	 */
	public function get_cart_weight( $contents ) {
		$weight = 0;
		foreach ( $contents as $content ) {
			$weight += ( $this->get_product_weight( $content['data'] ) * $content['quantity'] );
		}
		return apply_filters( 'kiriminaja_get_cart_weight', $weight, $contents );
	}

	/**
	 * Get estimated dimension of the order
	 *
	 * @param  object $order Order object.
	 * @return array         Order dimensions.
	 */
	public function get_order_dimension( $order ) {
		$widths = $lengths = $heights = array();
		foreach ( $order->get_items() as $item ) {
			$product   = $item->get_product();
			$widths[]  = $this->dimension_convert( $product->get_width() );
			$lengths[] = $this->dimension_convert( $product->get_length() );
			$heights[] = $this->dimension_convert( $product->get_height() );
		}
		if (count($widths) >0 ) {
			$dimension = array(
				'width'  => max( 1, max( $widths ) ), // get longest width.
				'length' => max( 1, max( $lengths ) ), // get longest length.
				'height' => max( 1, array_sum( $heights ) ), // sums the heights of the products.
			);
		}else{
			$dimension = array(
				'width'  => 1, // get longest width.
				'length' => 1, // get longest length.
				'height' =>1, // sums the heights of the products.
			);
		}
		return apply_filters( 'kiriminaja_get_order_dimension', $dimension, $order );
	}

	/**
	 * Get estimated dimension of cart contents
	 *
	 * @param  array $contents Cart contents.
	 * @return array           Estimated cart dimension.
	 */
	public function get_cart_dimension( $contents ) {
		$widths = $lengths = $heights = array();
		foreach ( $contents as $content ) {
			$product   = wc_get_product( $content['data']->get_id() );
			$widths[]  = $this->dimension_convert( $product->get_width() );
			$lengths[] = $this->dimension_convert( $product->get_length() );
			$heights[] = $this->dimension_convert( $product->get_height() );
		}
		$dimension = array(
			'width'  => max( 1, max( $widths ) ), // get longest width.
			'length' => max( 1, max( $lengths ) ), // get longest length.
			'height' => max( 1, array_sum( $heights ) ), // sums the heights of the products.
		);
		return apply_filters( 'kiriminaja_get_cart_dimension', $dimension, $contents );
	}

	/**
	 * Convert current weight to kilo
	 *
	 * @param  float $weight Current weight.
	 * @return float         Converted weight.
	 */
	public function weight_convert( $weight = 0 ) {
		$wc_unit = strtolower( get_option( 'woocommerce_weight_unit', 'kg' ) );
		if ( 'kg' !== $wc_unit ) {
			switch ( $wc_unit ) {
				case 'g':
					$weight *= 0.001;
					break;
				case 'lbs':
					$weight *= 0.4535;
					break;
				case 'oz':
					$weight *= 0.0283495;
					break;
			}
		}
		return apply_filters( 'kiriminaja_weight_convert', $weight );
	}

	/**
	 * Convert current dimension to cm
	 *
	 * @param  float $dimension Current dimension.
	 * @return float            Converted dimension.
	 */
	public function dimension_convert( $dimension = 0 ) {
		$dimension = floatval( $dimension );
		$wc_unit = strtolower( get_option( 'woocommerce_dimension_unit', 'cm' ) );
		if ( 'cm' !== $wc_unit ) {
			switch ( $wc_unit ) {
				case 'm':
					$dimension *= 100;
					break;
				case 'mm':
					$dimension *= 0.1;
					break;
				case 'in':
					$dimension *= 2.54;
					break;
				case 'yd':
					$dimension *= 91.44;
					break;
			}
		}
		return apply_filters( 'kiriminaja_dimension_convert', $dimension );
	}

	/**
	 * Is insurance enable on given products
	 *
	 * @param  array $contents Cart contents.
	 * @return boolean           Enabled or not.
	 */
	public function is_enable_insurance( $contents ) {
		$enable = ( 'yes' === $this->setting->get( 'enable_insurance' ) ? true : false );
		if ( $contents instanceof WC_Order ) {
			foreach ( $contents->get_items() as $item ) {
				if ( 'set' === $this->setting->get( 'enable_insurance' ) && ( 'yes' === get_post_meta( $item->get_product_id(), 'enable_insurance', true ) || 'yes' === get_post_meta( $item->get_product()->get_parent_id(), 'enable_insurance', true ) ) ) {
					$enable = true;
				}
			}
		} elseif ( is_array( $contents ) ) {
			foreach ( $contents as $content ) {
				if ( 'set' === $this->setting->get( 'enable_insurance' ) && ( 'yes' === get_post_meta( $content['data']->get_id(), 'enable_insurance', true ) || 'yes' === get_post_meta( $content['data']->get_parent_id(), 'enable_insurance', true ) ) ) {
					$enable = true;
				}
			}
		}
		return apply_filters( 'kiriminaja_is_enable_insurance', $enable, $contents, $this->setting->get_all() );
	}

	/**
	 * Get address ids from order
	 *
	 * @param  integer $order_id Order ID.
	 * @param  string  $type     Address type.
	 * @return integer           Address id.
	 */
	public function get_address_id_from_order( $order_id = 0, $type = 'billing_state' ) {
		if ( ! in_array( $type, array( 'billing_country', 'billing_state', 'billing_city', 'billing_district', 'shipping_country', 'shipping_state', 'shipping_city', 'shipping_district' ), true ) ) {
			return 0;
		}
		$id = get_post_meta( $order_id, '_' . $type . '_id', true );
		if ( '' === $id ) {
			$id = get_post_meta( $order_id, '_' . $type, true );
		}
		return ! in_array( $type, array( 'billing_country', 'shipping_country' ) ) ? intval( $id ) : $id;
	}

	/**
	 * Get address ids from user
	 *
	 * @param  integer $user_id  User ID.
	 * @param  string  $type     Address type.
	 * @return integer           Address id.
	 */
	public function get_address_id_from_user( $user_id = 0, $type = 'billing_state' ) {
		if ( ! in_array( $type, array( 'billing_state', 'billing_city', 'billing_district', 'shipping_state', 'shipping_city', 'shipping_district' ), true ) ) {
			return 0;
		}
		$id = get_user_meta( $user_id, $type . '_id', true );
		if ( '' === $id ) {
			$id = get_user_meta( $user_id, $type, true );
		}
		return intval( $id );
	}

	/**
	 * Check if store only sell to Indonesia
	 *
	 * @return boolean Is sell to Indonesia
	 */
	public function is_store_only_sell_to_indonesia() {
		$allowed_countries = WC()->countries->get_allowed_countries();
		if ( 1 === count( $allowed_countries ) && isset( $allowed_countries['ID'] ) ) {
			$allowed_shipping = WC()->countries->get_shipping_countries();
			if ( 1 === count( $allowed_shipping ) && isset( $allowed_shipping['ID'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse estimated time
	 * @param  string $etd Etd.
	 * @return string      Formatted Etd.
	 */
	public function format_etd( $etd ) {
		if ( '' === $etd || '-' === $etd ) {
			return '';
		}
		$explode = explode( '-', $etd );
		$from = ltrim( $explode[0], '0' );
		if ( ! isset( $explode[1] ) ) {
			return $from . ' ' . _n( 'day', 'days', intval( $from ), 'kiriminaja' );
		} else {
			$to = ltrim( $explode[1], '0' );
			if ( intval( $from ) === intval( $to ) ) {
				return $from . ' ' . _n( 'day', 'days', intval( $from ), 'kiriminaja' );
			} elseif ( empty( $from ) ) {
				return $to . ' ' . _n( 'day', 'days', intval( $to ), 'kiriminaja' );
			} else {
				return $from . '-' . $to . ' ' . __( 'days', 'kiriminaja' );
			}
		}
	}

	/**
	 * Check if override to indonesia
	 * @return boolean
	 */
	public function is_override_to_indonesia() {
		return true;
		// $override = $this->setting->get( 'override_default_location_to_indonesia' );
		// if( empty( $override ) || 'yes' === $override ) {
		// 	return true;
		// }
		// return false;
	}

	/**
	 * Map woocommerce state to ongkir state format
	 * @param $state string
	 * @return $states array
	 */
	public function map_ongkir_states( $state = '' ) {
		$states = array(
			'BA' => '1',
			'BB' => '2',
			'BT' => '3',
			'BE' => '4',
			'YO' => '5',
			'JK' => '6',
			'GO' => '7',
			'JA' => '8',
			'JB' => '9',
			'JT' => '10',
			'JI' => '11',
			'KB' => '12',
			'KS' => '13',
			'KT' => '14',
			'KI' => '15',
			'KU' => '16',
			'KR' => '17',
			'LA' => '18',
			'MA' => '19',
			'MU' => '20',
			'AC' => '21',
			'NB' => '22',
			'NT' => '23',
			'PA' => '24',
			'PB' => '25',
			'RI' => '26',
			'SR' => '27',
			'SN' => '28',
			'ST' => '29',
			'SG' => '30',
			'SA' => '31',
			'SB' => '32',
			'SS' => '33',
			'SU' => '34',
		);
		if( ! empty( $state ) ) {
			$states = isset( $states[$state] ) ? $states[$state] : false;
		}
		return $states;
	}

	/**
	 * Get KiriminAja's order ID from order
	 *
	 * @param  integer $order_id Order ID.
	 * @return string            KiriminAja's order ID.
	 */
	public function get_order_ref_id( $order_id ) {
		if ( '' === ( $ref_id = get_post_meta( $order_id, '_ka_order_id', true ) ) ) {
			$prefix = $this->setting->get('ref_prefix');
			$ref_id = $prefix . sprintf( '%010d', intval( $order_id ) );
			update_post_meta( $order_id, '_ka_order_id', $ref_id );
		}
		return $ref_id;
	}

	/**
	 * Get order ID from KiriminAja's order ID
	 *
	 * @param  string  $ref_id KiriminAja's order ID.
	 * @return integer         Order ID.
	 */
	public function get_order_by_ref_id( $ref_id ) {
		global $wpdb;
		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT o.ID FROM {$wpdb->posts} o LEFT JOIN {$wpdb->postmeta} r ON ( r.post_id = o.ID ) WHERE o.post_type = 'shop_order' AND r.meta_key = '_ka_order_id' AND r.meta_value LIKE %s", $ref_id ) );
	    return ! is_null( $order_id ) ? intval( $order_id ) : false;
	}

	/**
	 * Get pickup ID from KiriminAja's pickup number
	 *
	 * @param  string  $ref_id KiriminAja's pickup number.
	 * @return integer         Pickup ID.
	 */
	public function get_pickup_by_ref_id( $ref_id ) {
		global $wpdb;
		$pickup_id = $wpdb->get_var( $wpdb->prepare( "SELECT o.ID FROM {$wpdb->posts} o WHERE o.post_type = 'pickup_request' AND o.post_title LIKE %s", $ref_id ) );
	    return ! is_null( $pickup_id ) ? intval( $pickup_id ) : false;
	}

	/**
	 * Check if an order is available for pickup request
	 *
	 * @param  WC_Order|integer  $order Order.
	 * @return boolean                  Order is available for pickup.
	 */
	public function is_order_available_for_pickup( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}
		$shipping_methods = $order->get_shipping_methods();

		// make sure order status is available for pickup.
		if ( ! in_array( $order->get_status(), apply_filters( 'kiriminaja_pickup_order_status' , array( 'on-hold', 'pending', 'processing' ) ) ) ) {
			return false;
		}

		// make sure the shipping method is created by kiriminaja.
		if ( false === $this->get_order_shipping( $order ) ) {
			return false;
		}

		// make sure destination is set
		if ( '' === get_post_meta( $order->get_id(), '_shipping_district_id', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Format phone number
	 *
	 * @param  string $number Raw phone number.
	 * @return string         Formatted phone number.
	 */
	public function format_phone_number( $number ) {
		$number = trim( str_replace( array(' ', '-', '(', ')', '+'), '', $number ) );
		if ( 0 === stripos( $number, '62') ) {
			$number = substr_replace( $number, '0', 0, 2 );
		}
		return $number;
	}

	/**
	 * Get order items name
	 *
	 * @param  WC_Order|integer $order Order.
	 * @return string           Order items name.
	 */
	public function get_order_items_name( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}
		$names = array();
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
		}
		return implode( ', ', $names );
	}

	/**
	 * Get order shipping method
	 *
	 * @param  WC_Order|integer       $order Order.
	 * @return WC_Order_Item_Shipping        Shipping item.
	 */
	public function get_order_shipping( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}
		$shipping_methods = $order->get_shipping_methods();
		if ( ! empty( $shipping_methods ) ) {
			$shipping_method = $shipping_methods[ array_keys( $shipping_methods )[0] ];
			if ( true == boolval( $shipping_method->get_meta('created_by_kiriminaja') ) ) {
				return $shipping_method;
			}
		}
		return false;
	}

	/**
	 * Get active transactional order statuses for current order status
	 *
	 * @param  string $statues Current order status.
	 * @return array           Transactional order statuses.
	 */
	public function get_transactional_order_statuses( $status ) {
		$order_statuses  = wc_get_order_statuses();
		$active_statuses = array( $status );
		switch ( $status ) {
			case 'wc-pending':
			case 'wc-processing':
			case 'wc-on-hold':
				$active_statuses[] = 'wc-pickup-request';
				$active_statuses[] = 'wc-cancelled';
				break;

			case 'wc-pickup-request':
				$active_statuses[] = 'wc-shipped';
				$active_statuses[] = 'wc-cancelled';
				break;

			case 'wc-shipped':
				$active_statuses[] = 'wc-return';
				$active_statuses[] = 'wc-returned';
				$active_statuses[] = 'wc-completed';
				break;

			case 'wc-return':
				$active_statuses[] = 'wc-returned';
				break;

			case 'wc-missing':
				$active_statuses[] = 'wc-missing-finished';
				break;

			case 'wc-damaged':
				$active_statuses[] = 'wc-damaged-finished';
				break;

			case 'wc-cancelled':
				$active_statuses[] = 'wc-processing';
				$active_statuses[] = 'wc-on-hold';
				break;

			case 'wc-completed':
				break;

			default:
				$active_statuses = array_keys( $order_statuses );
				break;
		}
		return apply_filters( 'kiriminaja_transactional_order_statuses', $active_statuses, $status );
	}

	/**
	 * Get pickup status label
	 *
	 * @param  integer $pickup_id Pickup ID.
	 * @return string             Pickup status Label.
	 */
	public function get_pickup_status_label( $pickup_id ) {
		$status         = get_post_meta( $pickup_id, 'status', true );
		$payment_status = get_post_meta( $pickup_id, 'payment_status', true );
		if ( 'paid' === $payment_status && 'new' === $status ) { // backward compatibilty.
			$status = 'paid';
		}
		switch ( $status ) {
			case 'new':
			case 'pending':
				$label = __( 'Waiting for Payment', 'kiriminaja' );
				break;
			case 'paid':
				$label = __( 'Paid', 'kiriminaja' );
				break;
			case 'picked':
				$label = __( 'Picked', 'kiriminaja' );
				break;
			default:
				$label = '-';
				break;
		}
		return $label;
	}

	/**
	 * Is order has awb
	 *
	 * @param  integer  $order_id Order ID.
	 * @return boolean            AWB status.
	 */
	public function has_awb( $order_id ) {
		$shipping = $this->get_order_shipping( $order_id );
		$awb      = $shipping->get_meta('awb');
		return ! empty( $awb );
	}

}
