<?php

/**
 * Customized checkout fields
 */
class KiriminAja_Hooks_Addresses {

	/**
	 * KiriminAja Core
	 *
	 * @var object
	 */
	protected $core;

	/**
	 * KiriminAja Setting
	 *
	 * @var object
	 */
	protected $setting;

	/**
	 * KiriminAja Helper
	 *
	 * @var object
	 */
	protected $helper;

	/**
	 * Field order
	 *
	 * @var array
	 */
	protected $field_order;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $kiriminaja_helper;
		global $kiriminaja_core;
		$this->core     = $kiriminaja_core;
		$this->setting  = new KiriminAja_Setting();
		$this->helper   = $kiriminaja_helper;
		$this->field_order = apply_filters(
			'kiriminaja_fields_priority', array(
				'first_name'    => 10,
				'last_name'     => 20,
				'company'       => 30,
				'country'       => 40,
				'state'         => 50,
				'city'          => 60,
				'district'      => 70,
				'address_1'     => 80,
				'address_2'     => 90,
				'postcode'      => 100,
				'phone'         => 110,
				'email'         => 120,
				'insurance'		=> 9999
			)
		);

		if ( $this->setting->get('enable') && $this->helper->is_token_set() ) {
			// checkout.
			add_filter( 'woocommerce_states', array( $this, 'set_provinces' ) );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_checkout_fields' ) );
			add_filter( 'woocommerce_billing_fields', array( $this, 'custom_billing_fields' ) );
			add_filter( 'woocommerce_shipping_fields', array( $this, 'custom_shipping_fields' ) );
			add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale' ) );
			add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'locale_field_selectors' ) );
			add_filter( 'woocommerce_get_country_locale_default', array( $this, 'country_locale_default' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ), 10, 2 );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'delete_wc_cache' ) );
			add_filter( 'woocommerce_cart_ready_to_calc_shipping', array( $this, 'remove_shipping_on_cart' ) );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'set_available_payments' ) );
			add_action( 'woocommerce_review_order_after_cart_contents', array( $this, 'show_additional_info_on_checkout' ) );
			add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'custom_shipping_label' ), 10, 2 );
			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'custom_fees_on_checkout' ) );

			// order.
			add_filter( 'woocommerce_localisation_address_formats', array( $this, 'custom_address_format' ) );
			add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'custom_address_replacement' ), 30, 2 );
			add_action( 'woocommerce_get_order_address', array( $this, 'set_order_address_data' ), 10, 3 );

			// my account.
			add_filter( 'woocommerce_my_account_edit_address_field_value', array( $this, 'set_my_account_address_value' ), 10, 3 );
			add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'format_myaccount_address' ), 10, 3 );
			add_action( 'woocommerce_customer_save_address', array( $this, 'update_customer_address' ), 10, 2 );

			// other.
			add_filter( 'woocommerce_shipping_settings', array( $this, 'modify_shipping_settings' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// Let 3rd parties unhook the above via this hook.
			do_action( 'kiriminaja_hooks_addresses', $this );
		}
	}

	/**
	 * Set custom provinces
	 *
	 * @param array $states WC States.
	 */
	public function set_provinces( $states ) {
		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && 'woocommerce_page_wc-settings' === $screen->id && ( ! isset( $_GET['tab'] ) || 'general' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ) {
				return $states;
			}
		}
		$provinces = $this->core->get_province();
		if ( ! empty( $provinces ) ) {
			$states['ID'] = $provinces;
		} else {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Failed to load data. Please refresh the page.', 'kiriminaja' ), 'error' );
			}
		}
		return $states;
	}

	/**
	 * Custom checkout fields
	 *
	 * @param  array $fields Checkout fields.
	 * @return array         Checkout fields
	 */
	public function custom_checkout_fields( $fields ) {
		$fields['billing']  = $this->alter_fields( $fields['billing'], 'billing' );
		$fields['shipping'] = $this->alter_fields( $fields['shipping'], 'shipping' );
		return $fields;
	}

	/**
	 * Custom billing fields
	 *
	 * @param  array $fields Billing fields.
	 * @return array         Billing fields
	 */
	public function custom_billing_fields( $fields ) {
		return $this->alter_fields( $fields, 'billing' );
	}

	/**
	 * Custom shipping fields
	 *
	 * @param  array $fields Billing fields.
	 * @return array         Billing fields
	 */
	public function custom_shipping_fields( $fields ) {
		return $this->alter_fields( $fields, 'shipping' );
	}

	/**
	 * Alter checkout fields
	 *
	 * @param  array  $fields Checkout fields.
	 * @param  string $type   Billing/Shipping.
	 * @return array          Customized fields.
	 */
	private function alter_fields( $fields = array(), $type = 'billing' ) {
		$fields[ $type . '_district' ] = array(
			'label'        		=> __( 'District', 'kiriminaja' ),
			'placeholder'  		=> __( 'Search District', 'kiriminaja' ),
			'type'         		=> 'select',
			'options'      		=> array( '' => __( 'Search District', 'kiriminaja' ) ),
			'class'        		=> array( 'update_totals_on_change', 'address-field', 'select2-ajax' ),
			'custom_attributes'	=> array(
				'data-action'	=> 'kiriminaja_search_district',
				'data-nonce'	=> wp_create_nonce( 'get_list_district' )
			),
		);

		$fields[ $type . '_district_text' ] = array(
			'label'        		=> __( 'District Text', 'kiriminaja' ),
			'placeholder'  		=> __( 'Search District Text', 'kiriminaja' ),
			'type'         		=> 'text',
			'class'        		=> array(),
		);

		if ( 'billing' === $type && is_checkout() ){
			$fields[ $type . '_insurance' ]['label']	= __( 'Add shipping insurance to this order', 'kiriminaja' );
			$fields[ $type . '_insurance' ]['type']		= 'checkbox';
			$fields[ $type . '_insurance' ]['class']	= array( 'update_totals_on_change', 'form-row-wide' );
			$fields[ $type . '_insurance' ]['priority'] = $this->field_order['insurance'];
		}

		return $fields;
	}

	/**
	 * Get country locale
	 *
	 * @param  array $fields Fields.
	 * @return array         Fields.
	 */
	public function country_locale( $fields ) {
		foreach ( $fields as $country => $locale ) {
			if ( 'ID' === $country ) {
				$fields['ID'] = array(
					'address_1' => array(
						'label'    => __( 'Address', 'kiriminaja' ),
						'priority' => $this->field_order['address_1']
					),
					'address_2' => array(
						'priority' => $this->field_order['address_2']
					),
					'state' => array(
						'label'       => __( 'Province', 'kiriminaja' ),
						'placeholder' => __( 'Select Province', 'kiriminaja' ),
						'priority'    => $this->field_order['state'],
						'hidden'      => true,
						'required'    => false,
					),
					'city' => array(
						'hidden' => true,
						'required' => false,
						'priority'    => $this->field_order['city'],
					),
					'postcode'  => array(
						'label'    => __( 'Postcode / ZIP', 'kiriminaja' ),
						'priority' => $this->field_order['postcode']
					),
					'district' => array(
						'hidden'   => false,
						'required' => true,
						'priority'    => $this->field_order['city'],
					),
					'district_text' => array(
						'required' => false,
						'hidden'   => true,
					)
				);
			} else {
				$fields[ $country ]['district'] = array(
					'required' => false,
					'hidden'   => true,
				);
				$fields[ $country ]['district_text'] = array(
					'required' => false,
					'hidden'   => true,
				);
				$fields[ $country ]['insurance'] = array(
					'required' => false,
					'hidden'   => true,
				);
			}
		}
		return $fields;
	}

	/**
	 * Get country locale default
	 *
	 * @param  array $fields Fields.
	 * @return array         Fields.
	 */
	public function country_locale_default( $fields ) {
		$fields['district'] = array(
			'required' => false,
			'hidden'   => true,
		);
		$fields['district_text'] = array(
			'required' => false,
			'hidden'   => true,
		);
		$fields['insurance'] = array(
			'required' => false,
			'hidden'   => false,
		);
		return $fields;
	}

	/**
	 * Additional locale address fields
	 * 
	 * @param  array $fields Fields.
	 * @return array         Fields.
	 */
	public function locale_field_selectors( $fields ) {
		$fields['district']      = '#billing_district_field, #shipping_district_field';
		$fields['district_text'] = '#billing_district_text_field, #shipping_district_text_field';
		$fields['insurance']     = '#billing_insurance_field';
		return $fields;
	}

	/**
	 * Additional fees (COD fee & Insurance)
	 */
	public function custom_fees_on_checkout() {
		if ( null === WC()->session ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
		$cod_fee       = false;
		$insurance_fee = false;

		if ( WC()->session->__isset('chosen_shipping_methods') && WC()->session->__isset('shipping_for_package_0') ) {
			$chosen_shipping  = WC()->session->get('chosen_shipping_methods');
			$chosen_payment   = WC()->session->get('chosen_payment_method');
			$shipping_options = WC()->session->get('shipping_for_package_0');
			if ( isset( $shipping_options['rates'] ) && isset( $chosen_shipping[0] ) && isset( $shipping_options['rates'][ $chosen_shipping[0] ] ) ) {
				$shipping_meta = $shipping_options['rates'][ $chosen_shipping[0] ]->get_meta_data();
				if ( 'cod' === $chosen_payment && isset( $shipping_meta['cod'] ) ) {
					$is_cod = boolval( $shipping_meta['cod'] );
					if ( $is_cod && isset( $shipping_meta['cod_fee'] ) ) {
						$cod_fee = $shipping_meta['cod_fee'];
					}
				}
				if ( isset( $shipping_meta['insurance'] ) && ! empty( $shipping_meta['insurance'] ) ) {
					$insurance_fee = $shipping_meta['insurance'];
				}
			}
		}

		if ( false !== $cod_fee ) {
			WC()->cart->add_fee( __( 'COD Fee', 'kiriminaja' ), floatval( $cod_fee ) );
		}
		if ( false !== $insurance_fee ) {
			WC()->cart->add_fee( __( 'Insurance', 'kiriminaja' ), floatval( $insurance_fee ) );
		}

		$fees = WC()->cart->get_fees();
		foreach ( $fees as $key => $fee ) {
			if ( __( 'COD Fee', 'kiriminaja' ) == $fee->name && false === $cod_fee ) {
				unset( $fees[ $key ] );
			}
			if ( __( 'Insurance', 'kiriminaja' ) == $fee->name && false === $insurance_fee ) {
				unset( $fees[ $key ] );
			}
		}
		WC()->cart->fees_api()->set_fees( $fees );
	}

	/**
	 * Load scripts
	 */
	public function enqueue_scripts() {
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			if ( is_account_page() ) {
				wp_enqueue_style( 'kiriminaja-myaccount', KIRIMINAJA_PLUGIN_URL . '/assets/css/myaccount.css', array(), KIRIMINAJA_VERSION );
			}
			if ( is_checkout() || is_account_page() || apply_filters( 'kiriminaja_is_checkout', false ) ) {
				wp_enqueue_style( 'kiriminaja-checkout', KIRIMINAJA_PLUGIN_URL . '/assets/css/checkout.css', array( 'select2' ), KIRIMINAJA_VERSION );
				wp_enqueue_script( 'kiriminaja-checkout', KIRIMINAJA_PLUGIN_URL . '/assets/js/checkout.js', array( 'jquery', 'select2' ), KIRIMINAJA_VERSION, true );
				$localize = array(
					'ajaxurl'               => admin_url( 'admin-ajax.php' ),
					'labelFailedCity'       => __( 'Failed to load city list. Try again?', 'kiriminaja' ),
					'labelFailedDistrict'   => __( 'Failed to load district list. Try again?', 'kiriminaja' ),
					'labelSelectCity'       => __( 'Select City', 'kiriminaja' ),
					'labelLoadingCity'      => __( 'Loading city options...', 'kiriminaja' ),
					'labelSelectDistrict'   => __( 'Select District', 'kiriminaja' ),
					'labelLoadingDistrict'  => __( 'Loading district options...', 'kiriminaja' ),
					'only_sell_to_indonesia' => $this->helper->is_store_only_sell_to_indonesia(),
					'billing_country'       => $this->helper->get_country_session( 'billing' ),
					'shipping_country'      => $this->helper->get_country_session( 'shipping' ),
					'is_my_account'			=> is_account_page(),
					'is_checkout'			=> is_checkout(),
					'billing_state'         => 0,
					'shipping_state'        => 0,
					'billing_city'          => 0,
					'shipping_city'         => 0,
					'billing_district'      => 0,
					'shipping_district'     => 0,
					'nonce_change_country'  => wp_create_nonce( 'change_country' ),
					'nonce_get_list_city'   => wp_create_nonce( 'get_list_city' ),
					'nonce_get_list_district' => wp_create_nonce( 'get_list_district' )
				);
				// get returning user data.
				if ( is_user_logged_in() ) {
					$user_id = get_current_user_id();
					$localize['billing_state']      = $this->helper->get_address_id_from_user( $user_id, 'billing_state' );
					$localize['billing_city']       = $this->helper->get_address_id_from_user( $user_id, 'billing_city' );
					$localize['billing_district']   = $this->helper->get_address_id_from_user( $user_id, 'billing_district' );
					$localize['shipping_state']     = $this->helper->get_address_id_from_user( $user_id, 'shipping_state' );
					$localize['shipping_city']      = $this->helper->get_address_id_from_user( $user_id, 'shipping_city' );
					$localize['shipping_district']  = $this->helper->get_address_id_from_user( $user_id, 'shipping_district' );
				}
				wp_localize_script( 'kiriminaja-checkout', 'kiriminaja_checkout_data', $localize );
			}
		}
	}

	/**
	 * Custom address format
	 *
	 * @param  array $formats Address formats.
	 * @return array          Address formats.
	 */
	public function custom_address_format( $formats ) {
		$formats['ID'] = "{name}\n{company}\n{address_1}\n{address_2}\n{kiriminaja_district}{kiriminaja_city}\n{kiriminaja_state}\n{country}\n{postcode}";
		return $formats;
	}

	/**
	 * Custom address format replacements
	 *
	 * @param  array $replacements Replacement fields.
	 * @param  array $args         Address args.
	 * @return array               Replacement fields.
	 */
	public function custom_address_replacement( $replacements, $args ) {
		// set state name.
		$province = isset( $args['state'] ) ? $args['state'] : '';
		if ( isset( $args['state'] ) ) {
			if ( 0 !== intval( $args['state'] ) ) {
				$provinces = $this->core->get_province();
				if ( isset( $provinces[ intval( $args['state'] ) ] ) ) {
					$province = $provinces[ intval( $args['state'] ) ];
				}
			}
		}

		// set city name.
		$city = isset( $args['city'] ) ? $args['city'] : '';
		if ( isset( $args['city'] ) ) {
			if ( 0 !== intval( $args['city'] ) ) {
				if ( 0 !== intval( $args['state'] ) ) {
					$cities = $this->core->get_city( intval( $args['state'] ) );
					if ( isset( $cities[ intval( $args['city'] ) ] ) ) {
						$city = $cities[ intval( $args['city'] ) ];
					}
				}
			}
		}

		// set district name.
		$district = '';
		if ( isset( $args['district'] ) ) {
			if ( 0 !== intval( $args['city'] ) && 0 !== intval( $args['city'] ) && 0 !== intval( $args['district'] ) ) {
				$districts = $this->core->get_district( intval( $args['city'] ) );
				if ( isset( $districts[ intval( $args['district'] ) ] ) ) {
					$district = 'Kec. ' . $districts[ intval( $args['district'] ) ] . "\n";
				}
			} elseif ( ! empty( $args['district'] ) ) {
				$district = 'Kec. ' . $args['district'] . "\n";
			} else {
				$district = "";
			}
		}

		$replacements['{kiriminaja_district}'] = $district;
		$replacements['{kiriminaja_city}']     = $city;
		$replacements['{kiriminaja_state}']    = $province;

		return $replacements;
	}

	/**
	 * Set address value on my account page
	 * 
	 * @param string $value        Address value.
	 * @param string $key          Field key.
	 * @param string $load_address Address type.
	 */
	public function set_my_account_address_value( $value, $key, $load_address ) {
		if ( in_array( $key, array( 'billing_state', 'billing_city', 'billing_district', 'shipping_state', 'shipping_city', 'shipping_district' ) ) ) {
			$user_id = get_current_user_id();
			$address_value = $this->helper->get_address_id_from_user( $user_id, $key );
			if ( ! empty( $address_value ) ) {
				$value = $address_value;
			}
		}
		return $value;
	}

	/**
	 * Fix name formatting on myaccount page
	 *
	 * @param  array  $address     Address data.
	 * @param  int    $customer_id Customer ID.
	 * @param  string $name        Billing/Shipping.
	 * @return array               Address data.
	 */
	public function format_myaccount_address( $address, $customer_id, $name ) {
		$address['district'] = get_user_meta( $customer_id, $name . '_district', true );
		return $address;
	}

	/**
	 * Validate district on checkout
	 */
	public function validate_district() {
		if ( isset( $_POST['billing_country'] ) && 'ID' === $_POST['billing_country'] && ( ! isset( $_POST['billing_district'] ) || empty( $_POST['billing_district'] ) ) ) { // WPCS: Input var okay. CSRF okay.
			wc_add_notice( __( '<b>Billing district</b> is required', 'kiriminaja' ), 'error' );
		}

		if ( isset( $_POST['ship_to_different_address'] ) && ! empty( $_POST['ship_to_different_address'] ) && isset( $_POST['shipping_country'] ) && 'ID' === $_POST['shipping_country'] ) { // WPCS: Input var okay. CSRF okay.
			if ( ! isset( $_POST['shipping_district'] ) || empty( $_POST['shipping_district'] ) ) { // WPCS: Input var okay. CSRF okay.
				wc_add_notice( __( '<b>Shipping district</b> is required', 'kiriminaja' ), 'error' );
			}
		}
	}

	/**
	 * Update user meta on checkout
	 *
	 * @param  integer $order_id Order ID.
	 * @param  array   $data     Input data.
	 */
	public function update_order_meta( $order_id, $data ) {
		$order = wc_get_order( $order_id );
		$user_id = version_compare( WC()->version, '3.0', '>=' ) ? $order->get_user_id() : $order->user_id;

		// update order meta & user meta.
		if ( 'ID' === $data['billing_country'] ) {
			if ( isset( $data['billing_district'] ) ) {
				update_post_meta( $order_id, '_billing_district_id', $data['billing_district'] );
			}
			if ( isset( $data['billing_district_text'] ) ) {
				update_post_meta( $order_id, '_billing_district', $data['billing_district_text'] );
				update_user_meta( $user_id, 'billing_district', $data['billing_district_text'] );
			}
		}
		if ( 'ID' === $data['shipping_country'] ) {
			if ( isset( $data['shipping_district'] ) ) {
				update_post_meta( $order_id, '_shipping_district_id', $data['shipping_district'] );
			}
			if ( isset( $data['shipping_district_text'] ) ) {
				update_post_meta( $order_id, '_shipping_district', $data['shipping_district_text'] );
				update_user_meta( $user_id, 'shipping_district', $data['shipping_district_text'] );
			}
		}
	}

	/**
	 * Add district to order address
	 *
	 * @param array  $address Address data.
	 * @param string $type    Billing/shipping.
	 * @param object $order   Order object.
	 */
	public function set_order_address_data( $address, $type, $order ) {
		$order_id = version_compare( WC()->version, '3.0', '>=' ) ? $order->get_id() : $order->id;

		$state = get_post_meta( $order_id, '_' . $type . '_state_id', true );
		if ( ! empty( $state ) ) {
			$address['state'] = $state;
		}

		// $city = get_post_meta( $order_id, '_' . $type . '_city_id', true );
		// if ( ! empty( $city ) ) {
		// 	$address['city'] = $city;
		// }

		$district = get_post_meta( $order_id, '_' . $type . '_district', true );
		if ( ! empty( $district ) ) {
			$address['district'] = $district;
		}
		return $address;
	}

	/**
	 * Delete shipping cache
	 */
	public function delete_wc_cache() {
		$packages = WC()->cart->get_shipping_packages();
		foreach ( $packages as $key => $value ) {
			$shipping_session = "shipping_for_package_$key";
			WC()->session->__unset( $shipping_session );
		}
	}

	/**
	 * Modify woocommerce setting page
	 *
	 * @param  array $fields Setting fields.
	 * @return array         Setting fields.
	 */
	public function modify_shipping_settings( $fields ) {
		if ( function_exists( 'array_column' ) ) {
			$key = array_search( 'woocommerce_enable_shipping_calc', array_column( $fields, 'id' ), true );
			if ( false !== $key ) {
				update_option( 'woocommerce_enable_shipping_calc', 'no' );
				$fields[ $key ]['custom_attributes']['disabled'] = 'disabled';
				$fields[ $key ]['desc'] .= ' (' . esc_html__( 'disabled by KiriminAja', 'kiriminaja' ) . ')';
			}
		}
		return $fields;
	}

	/**
	 * Remove shipping from cart page
	 *
	 * @param  boolean $show_shipping Show shipping or not.
	 * @return boolean                Show shipping or not.
	 */
	public function remove_shipping_on_cart( $show_shipping ) {
		if ( is_cart() ) {
			return false;
		}
		return $show_shipping;
	}

	/**
	 * Handle save customer address
	 *
	 * @param  integer $user_id      User ID.
	 * @param  string  $load_address Billing/shipping.
	 */
	public function update_customer_address( $user_id, $load_address ) {
		if ( isset( $_POST['billing_country'] ) && 'ID' === sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) ) { // WPCS: Input var okay. CSRF okay.
			if ( isset( $_POST['billing_state'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['billing_state'] ) ) { // WPCS: Input var okay. CSRF okay.
					$provinces = $this->core->get_province();
					if ( isset( $provinces[ intval( $_POST['billing_state'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
						$province = $provinces[ intval( $_POST['billing_state'] ) ]; // WPCS: Input var okay. CSRF okay.
					}
					update_user_meta( $user_id, 'billing_state', ( isset( $province ) && ! empty( $province ) ? $province : $_POST['billing_state'] ) );
					update_user_meta( $user_id, 'billing_state_id', $_POST['billing_state'] );
				}
			}
			if ( isset( $_POST['billing_city'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['billing_city'] ) ) { // WPCS: Input var okay. CSRF okay.
					if ( 0 !== intval( $_POST['billing_state'] ) ) { // WPCS: Input var okay. CSRF okay.
						$cities = $this->core->get_city( intval( $_POST['billing_state'] ) ); // WPCS: Input var okay. CSRF okay.
						if ( isset( $cities[ intval( $_POST['billing_city'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
							$city = $cities[ intval( $_POST['billing_city'] ) ]; // WPCS: Input var okay. CSRF okay.
						}
					}
					update_user_meta( $user_id, 'billing_city', ( isset( $city ) && ! empty( $city ) ? $city : $_POST['billing_city'] ) );
					update_user_meta( $user_id, 'billing_city_id', $_POST['billing_city'] );
				}
			}
			if ( isset( $_POST['billing_district'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['billing_district'] ) ) { // WPCS: Input var okay. CSRF okay.
					if ( 0 !== intval( $_POST['billing_city'] ) ) { // WPCS: Input var okay. CSRF okay.
						$districts = $this->core->get_district( intval( $_POST['billing_city'] ) ); // WPCS: Input var okay. CSRF okay.
						if ( isset( $districts[ intval( $_POST['billing_district'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
							$district = $districts[ intval( $_POST['billing_district'] ) ]; // WPCS: Input var okay. CSRF okay.
						}
					}
					update_user_meta( $user_id, 'billing_district', ( isset( $district ) && ! empty( $district ) ? $district : $_POST['billing_district'] ) );
					update_user_meta( $user_id, 'billing_district_id', $_POST['billing_district'] );
				}
			}
		}
		if ( isset( $_POST['shipping_country'] ) && 'ID' === sanitize_text_field( wp_unslash( $_POST['shipping_country'] ) ) ) { // WPCS: Input var okay. CSRF okay.
			if ( isset( $_POST['shipping_state'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['shipping_state'] ) ) { // WPCS: Input var okay. CSRF okay.
					$provinces = $this->core->get_province();
					if ( isset( $provinces[ intval( $_POST['shipping_state'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
						$province = $provinces[ intval( $_POST['shipping_state'] ) ]; // WPCS: Input var okay. CSRF okay.
					}
					update_user_meta( $user_id, 'shipping_state', ( isset( $province ) && ! empty( $province ) ? $province : $_POST['shipping_state'] ) );
					update_user_meta( $user_id, 'shipping_state_id', $_POST['shipping_state'] );
				}
			}
			if ( isset( $_POST['shipping_city'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['shipping_city'] ) ) { // WPCS: Input var okay. CSRF okay.
					if ( 0 !== intval( $_POST['shipping_state'] ) ) { // WPCS: Input var okay. CSRF okay.
						$cities = $this->core->get_city( intval( $_POST['shipping_state'] ) ); // WPCS: Input var okay. CSRF okay.
						if ( isset( $cities[ intval( $_POST['shipping_city'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
							$city = $cities[ intval( $_POST['shipping_city'] ) ]; // WPCS: Input var okay. CSRF okay.
						}
					}
					update_user_meta( $user_id, 'shipping_city', ( isset( $city ) && ! empty( $city ) ? $city : $_POST['shipping_city'] ) );
					update_user_meta( $user_id, 'shipping_city_id', $_POST['shipping_city'] );
				}
			}
			if ( isset( $_POST['shipping_district'] ) ) { // WPCS: Input var okay. CSRF okay.
				if ( 0 !== intval( $_POST['shipping_district'] ) ) { // WPCS: Input var okay. CSRF okay.
					if ( 0 !== intval( $_POST['shipping_city'] ) ) { // WPCS: Input var okay. CSRF okay.
						$districts = $this->core->get_district( intval( $_POST['shipping_city'] ) ); // WPCS: Input var okay. CSRF okay.
						if ( isset( $districts[ intval( $_POST['shipping_district'] ) ] ) ) { // WPCS: Input var okay. CSRF okay.
							$district = $districts[ intval( $_POST['shipping_district'] ) ]; // WPCS: Input var okay. CSRF okay.
						}
					}
					update_user_meta( $user_id, 'shipping_district', ( isset( $district ) && ! empty( $district ) ? $district : $_POST['shipping_district'] ) );
					update_user_meta( $user_id, 'shipping_district_id', $_POST['shipping_district'] );
				}
			}
		}
	}

	/**
	 * Dynamically enable COD payment for spesific shipping option
	 * 
	 * @param array $gateways Payment gateways.
	 */
	public function set_available_payments( $gateways ) {
		$is_cod = false;
		if ( null === WC()->session ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
		if ( WC()->session->__isset('chosen_shipping_methods') && WC()->session->__isset('shipping_for_package_0') ) {
			$chosen_shipping  = WC()->session->get('chosen_shipping_methods');
			$shipping_options = WC()->session->get('shipping_for_package_0');
			if ( isset( $shipping_options['rates'] ) && isset( $chosen_shipping[0] ) && isset( $shipping_options['rates'][ $chosen_shipping[0] ] ) ) {
				$shipping_meta = $shipping_options['rates'][ $chosen_shipping[0] ]->get_meta_data();
				if ( isset( $shipping_meta['cod'] ) ) {
					$is_cod = boolval( $shipping_meta['cod'] );
				}
			}
			if ( false === $is_cod && isset( $gateways['cod'] ) ) {
				unset( $gateways['cod'] );
			}
		}
		return $gateways;
	}

	/**
	 * Show shipping weight on checkout
	 */
	public function show_additional_info_on_checkout() {
		if ( count( WC()->cart->get_cart() ) > 0 ) {
			$weight = $this->helper->get_cart_weight( WC()->cart->get_cart() );
			if ( floor( $weight ) < $weight ) {
				$weight = number_format( $weight, 1 );
			}
			if( $weight > 0 ):
			?>
			<tr>
				<td class="product-name">
					<?php echo esc_html( apply_filters( 'kiriminaja_show_weight_label', __( 'Total shipping weight', 'pok' ) ) ); ?>
				</td>
				<td class="product-total">
					<?php echo esc_html( $weight ); ?>
					Kg
				</td>
			</tr>
			<?php
			endif;

			$dimension = $this->helper->get_cart_dimension( WC()->cart->get_cart() );
			?>
			<tr>
				<td class="product-name">
					<?php echo esc_html( apply_filters( 'kiriminaja_show_dimension_label', __( 'Estimated shipping dimension', 'pok' ) ) ); ?>
				</td>
				<td class="product-total">
					<?php echo esc_html( $dimension['length'] . ' x ' . $dimension['width'] . ' x ' . $dimension['height'] ); ?>
					cm
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Change shipping method label if it has discount
	 * 
	 * @param  string $label  Shipping label.
	 * @param  object $method Method object.
	 * @return string         New shipping label.
	 */
	public function custom_shipping_label( $label, $method ) {
		if ( 'kiriminaja' === $method->get_method_id() ) {
			$shipping_meta   = $method->get_meta_data();
			$discount_amount = isset( $shipping_meta['discount_amount'] ) ? floatval( $shipping_meta['discount_amount'] ) : 0;

			if ( ! empty( $discount_amount ) ) {
				$label = "<span class='pok-label'>" . $method->get_label() . ':</span> <span class="pok-price"><del>' . wc_price( $method->get_cost() + $discount_amount ) . '</del> <ins>' . wc_price( $method->get_cost() ) . '</ins></span>';
			} else {
				$label = "<span class='kiriminaja-label'>" . $method->get_label() . ':</span> <span class="kiriminaja-price">' . wc_price( $method->get_cost() ) . "</span>";
			}
		}
		return $label;
	}

}
