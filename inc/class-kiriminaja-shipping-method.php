<?php

/**
 * KiriminAja Shipping Method
 */
class KiriminAja_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $kiriminaja_helper;
		global $kiriminaja_core;
		$this->id                   = 'kiriminaja';
		$this->method_title         = __( 'KiriminAja', 'kiriminaja' );
		$this->method_description   = __( 'KiriminAja', 'kiriminaja' );
		$this->enabled              = 'yes';
		$this->title                = __( 'KiriminAja', 'kiriminaja' );
		$this->core                 = $kiriminaja_core;
		$this->setting              = new KiriminAja_Setting();
		$this->helper               = $kiriminaja_helper;
		// $this->supports             = array( 'shipping-zones', 'settings' );
		add_action( 'kiriminaja_calculate_shipping', array( $this, 'kiriminaja_calculate_shipping' ), 30 );
	}

	/**
	 * Display admin options
	 */
	public function admin_options() {
		global $kiriminaja_helper;
		$settings  = $this->setting->get_all();
		$provinces = $this->core->get_province();
		$couriers  = $this->core->get_all_couriers();
		include_once KIRIMINAJA_PLUGIN_PATH . 'views/admin/setting.php';
	}

	/**
	 * Calculate shipping cost
	 *
	 * @param  array $package Packages.
	 */
	public function calculate_shipping( $package = array() ) {
		global $woocommerce;

		if ( ! $this->helper->is_plugin_active() ) {
			return false;
		}

		if ( empty( $package ) ) {
			return false;
		}

		// clear all cached WC's shipping costs.
		$this->helper->clear_cached_costs();

		do_action( 'kiriminaja_calculate_shipping', $package, $this );

	}

	/**
	 * KiriminAja's Calculate Shipping cost
	 *
	 * @param  array $package Packages.
	 */
	public function kiriminaja_calculate_shipping( $package ) {
		$destination = $package['destination'];

		$user_insurance = 0;
		if ( isset( $_POST['post_data'] ) ) { // checkout page.
			$user_insurance = $this->get_checkout_post_data( 'billing_insurance' );
		} elseif ( isset( $_POST['billing_insurance'] ) ) { // order detail (after checkout).
			$user_insurance = sanitize_text_field( wp_unslash( $_POST['billing_insurance'] ) );
		}
		$enable_insurance = ( 1 === intval( $user_insurance ) );

		if ( ! isset( $package['weight'] ) ) {
			$weight = $this->helper->get_cart_weight( $package['contents'] );
		} else {
			$weight = $package['weight'];
		}

		if ( 'ID' === $destination['country'] ) {
			// get destination.
			if ( isset( $_POST['post_data'] ) ) { // checkout page.
				if ( '1' === $this->get_checkout_post_data( 'ship_to_different_address' ) ) {
					$district = $this->get_checkout_post_data( 'shipping_district' );
				} else {
					$district = $this->get_checkout_post_data( 'billing_district' );
				}
			} else { // order detail (after checkout).
				if ( isset( $_POST['shipping_district'] ) && ! empty( $_POST['shipping_district'] ) ) {
					$district = sanitize_text_field( wp_unslash( $_POST['shipping_district'] ) );
				} elseif ( isset( $_POST['billing_district'] ) && ! empty( $_POST['billing_district'] ) ) {
					$district = sanitize_text_field( wp_unslash( $_POST['billing_district'] ) );
				}
			}
			if ( ! empty( $district ) ) {
				$destination['district'] = intval( $district );
				$destination_id = intval( $district );
			}

			// get costs.
			if ( ! empty( $destination_id ) ) {
				$rates = $this->core->get_cost( $destination_id, $weight, array_sum( wp_list_pluck( $package['contents'], 'line_total' ) ), $enable_insurance );
			}
		}

		if ( ! empty( $rates ) ) {

			// allow 3rd parties to filter result form api.
			$rates = apply_filters( 'kiriminaja_rates', $rates, $package );

			foreach ( $rates as $i => $rate ) {
				$final_rate = array();
				$meta = array(
					'created_by_kiriminaja' => true,
					'courier'   => $rate['courier'],
					'service'   => $rate['service'],
					'cod'       => $rate['cod'],
					'base_cost' => $rate['cost'],
					'insurance' => $rate['insurance'],
					'cod_fee'   => $rate['cod_fee'],
					'discount_amount' => $rate['discount_amount'],
					'discount_percentage' => $rate['discount_percentage'],
					'raw'       => json_encode( $rate )
				);
				if ( ! empty( $rate['discount_amount'] ) ) {
					$rate['cost'] = floatval( $rate['cost'] ) - floatval( $rate['discount_amount'] ); 
				}

				$final_rate = apply_filters(
					'kiriminaja_rate', array(
						'id'        => 'kiriminaja-' . $rate['courier'] . '-' . $i,
						'label'     => $rate['name'],
						'cost'      => $rate['cost'],
						'meta_data' => $meta,
					), $rate, $package
				);
				$this->add_rate( $final_rate );
			}
		}
	}

	/**
	 * Get checkout post data.
	 *
	 * @param  string $field Checkout field.
	 * @return mixed         Checkout field data.
	 */
	private function get_checkout_post_data( $field ) {
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $return );
			if ( isset( $return[ $field ] ) ) {
				$return[ $field ] = str_replace( '+',' ',$return[ $field ] );
				return $return[ $field ];
			}
		}
		return false;
	}
}
