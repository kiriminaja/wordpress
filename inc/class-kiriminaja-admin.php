<?php

/**
 * KiriminAja Admin Class
 */
class KiriminAja_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		global $kiriminaja_helper;

		// add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// add_filter( 'parent_file', array( $this, 'highlight_submenu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_print_scripts-post-new.php', array( $this, 'early_enqueue_scripts' ) );
		add_action( 'admin_print_scripts-post.php', array( $this, 'early_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_notices' ) );

		$this->helper     = $kiriminaja_helper;
		$this->setting    = new KiriminAja_Setting();
		$this->core       = new KiriminAja_Core();
		
	}

	/**
	 * Validate current admin screen
	 *
	 * @return boolean Screen is KiriminAja or not.
	 */
	private function validate_screen() {
		$screen = get_current_screen();
		if ( is_null( $screen ) ) {
			return false;
		}

		$allowed_screens = array(
			'woocommerce_page_wc-settings'
		);
		if ( in_array( $screen->id, $allowed_screens, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Enqueue Script Inventory Manager
	 */
	public function early_enqueue_scripts() {
		$screen = get_current_screen();
		if ( 'edit-product' === $screen->id || 'product' === $screen->id ) {
			wp_enqueue_script( 'kiriminaja-admin-product', KIRIMINAJA_PLUGIN_URL . '/assets/js/admin-product.js', array( 'jquery' ), KIRIMINAJA_VERSION, true );
			wp_localize_script(
				'kiriminaja-admin-product', 'kiriminaja_translations', array(
					'weight_must_set'     => __( 'Weight must be set in the Shipping tab.', 'kiriminaja' ),
					'dimensions_must_set' => __( 'Dimensions must be set in the Shipping tab.', 'kiriminaja' ),
				)
			);
		}
	}

	/**
	 * Enqueue Script Inventory Manager
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' === $screen->id ) {

			wp_enqueue_style( 'kiriminaja-admin-setting', KIRIMINAJA_PLUGIN_URL . '/assets/css/admin-setting.css', array(), KIRIMINAJA_VERSION );
			wp_enqueue_script( 'kiriminaja-admin-setting', KIRIMINAJA_PLUGIN_URL . '/assets/js/admin-setting.js', array( 'jquery', 'jquery-blockui' ), KIRIMINAJA_VERSION, true );
			wp_localize_script( 'kiriminaja-admin-setting', 'kiriminaja_settings', $this->setting->get_all() );
			wp_localize_script(
				'kiriminaja-admin-setting', 'kiriminaja_translations', array(
					'key_not_valid'       => __( 'Key is not valid', 'kiriminaja' ),
					'api_key_empty'       => __( 'API key is empty', 'kiriminaja' ),
					'cant_connect_server' => __( 'Can not connect server', 'kiriminaja' ),
					'connecting_server'   => __( 'Connecting server...', 'kiriminaja' ),
					'delete'              => __( 'Delete', 'kiriminaja' ),
					'add'                 => __( 'Add', 'kiriminaja' ),
					'select_city'         => __( 'Select city', 'kiriminaja' ),
					'select_district'     => __( 'Select district', 'kiriminaja' ),
				)
			);
			wp_localize_script(
				'kiriminaja-admin-setting', 'kiriminaja_nonces', array(
					'get_list_city'     => wp_create_nonce( 'get_list_city' ),
					'get_list_district' => wp_create_nonce( 'get_list_district' ),
					'get_list_service'  => wp_create_nonce( 'get_list_service' ),
					'set_token'         => wp_create_nonce( 'set_token' ),
				)
			);

		} elseif ( 'edit-shop_order' === $screen->id || 'shop_order' === $screen->id ) {
			add_thickbox();
			wp_enqueue_style( 'kiriminaja-admin-order', KIRIMINAJA_PLUGIN_URL . '/assets/css/admin-order.css', array(), KIRIMINAJA_VERSION );
			wp_enqueue_script( 'qrcode', KIRIMINAJA_PLUGIN_URL . '/assets/js/qrcode.min.js', array( 'jquery' ), KIRIMINAJA_VERSION, true );
			wp_enqueue_script( 'kiriminaja-admin-order', KIRIMINAJA_PLUGIN_URL . '/assets/js/admin-order.js', array( 'jquery', 'jquery-blockui' ), KIRIMINAJA_VERSION, true );
			wp_localize_script(
				'kiriminaja-admin-order', 'kiriminaja_translations', array(
					'confirm_cancel'      => __( 'Are you sure want to cancel the shipping of this order?', 'kiriminaja' ),
					'enter_reason'        => __( 'Please enter the reason', 'kiriminaja' ),
					'orders_ids_empty'    => __( 'Please select at least 1 order before send request', 'kiriminaja' ),
					'no_orders_to_pickup' => __( 'Selected orders is not available for pickup.', 'kiriminaja' ),
					'order_not_pickup'    => __( 'This order is not available for pickup', 'kiriminaja' ),
					'pickup_request'      => __( 'Pickup Request', 'kiriminaja' ),
					'payment'             => __( 'QRIS Payment', 'kiriminaja' ),
				)
			);
			wp_localize_script(
				'kiriminaja-admin-order', 'kiriminaja_nonces', array(
					'get_list_order'      => wp_create_nonce( 'get_list_order' ),
					'get_schedules'       => wp_create_nonce( 'get_schedules' ),
					'pickup_request'      => wp_create_nonce( 'pickup_request' ),
					'load_payment'        => wp_create_nonce( 'load_payment' ),
					'get_shipping_status' => wp_create_nonce( 'get_shipping_status' ),
					'cancel_shipment'     => wp_create_nonce( 'cancel_shipment' )
				)
			);
			if ( 'shop_order' === $screen->id ) {
				global $post;
				$enabled_order_statuses = $this->helper->get_transactional_order_statuses( $post->post_status );
				wp_localize_script( 'kiriminaja-admin-order', 'kiriminaja_order_statuses', $enabled_order_statuses );
			}
		} elseif ( 'edit-pickup_request' === $screen->id ) {
			add_thickbox();
			wp_enqueue_style( 'kiriminaja-admin-pickup', KIRIMINAJA_PLUGIN_URL . '/assets/css/admin-pickup.css', array(), KIRIMINAJA_VERSION );
			wp_enqueue_script( 'qrcode', KIRIMINAJA_PLUGIN_URL . '/assets/js/qrcode.min.js', array( 'jquery' ), KIRIMINAJA_VERSION, true );
			wp_enqueue_script( 'kiriminaja-admin-pickup', KIRIMINAJA_PLUGIN_URL . '/assets/js/admin-pickup.js', array( 'jquery', 'jquery-blockui' ), KIRIMINAJA_VERSION, true );
			wp_localize_script(
				'kiriminaja-admin-pickup', 'kiriminaja_translations', array(
					'details'        => __( 'Pickup Details', 'kiriminaja' ),
					'payment'        => __( 'QRIS Payment', 'kiriminaja' ),
					'confirm_cancel' => __( 'Are you sure want to cancel this pickup request?', 'kiriminaja' ),
					'reschedule_pickup' => __( 'Reschedule Pickup', 'kiriminaja' ),
				)
			);
			wp_localize_script(
				'kiriminaja-admin-pickup', 'kiriminaja_nonces', array(
					'load_detail'    => wp_create_nonce( 'load_detail' ),
					'load_payment'   => wp_create_nonce( 'load_payment' ),
					'cancel_pickup'  => wp_create_nonce( 'cancel_pickup' ),
					'check_schedule' => wp_create_nonce( 'check_schedule' ),
					'get_schedules'  => wp_create_nonce( 'get_schedules' ),
					'pickup_request' => wp_create_nonce( 'pickup_request' )
				)
			);
		}
	}

	/**
	 * Register admin menu
	 */
	public function admin_menu() {
    	global $submenu;
		add_menu_page( 'Ongkos Kirim', 'Ongkos Kirim', 'manage_woocommerce', 'plugin_ongkos_kirim', null, KIRIMINAJA_PLUGIN_URL . '/assets/img/icon.png', 58 );
		foreach ( $this->admin_tabs as $key => $tab ) {
			$sub_url = 'setting' === $key ? 'kiriminaja_setting' : 'kiriminaja_setting&tab=' . $key;
			add_submenu_page( 'plugin_ongkos_kirim', $tab['label'], $tab['label'], 'manage_woocommerce', $sub_url, array( $this, 'render_admin_page' ) );
		}
		remove_submenu_page( 'plugin_ongkos_kirim', 'plugin_ongkos_kirim' );
	}

	/**
	 * Manually highlight the sub menu hack
	 */
	public function highlight_submenu( $parent_file ) {
		global $submenu_file;
		if ( isset( $_GET['tab'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['tab'] ) ), array_keys( $this->admin_tabs ), true ) ) { // WPCS: Input var okay, CSRF ok.
			$submenu_file = 'kiriminaja_setting&tab=' . sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // WPCS: Input var okay, CSRF ok.
		}
		return $parent_file;
	}

	/**
	 * Render setting page
	 */
	public function render_admin_page() {
		if ( $this->license->is_license_active() ) {
			$tabs = $this->admin_tabs;
			if ( isset( $_GET['tab'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['tab'] ) ), array_keys( $tabs ), true ) ) { // WPCS: Input var okay, CSRF ok.
				$tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // WPCS: Input var okay, CSRF ok.
			} else {
				$tab = current( array_keys( $tabs ) );
			}
			include_once KIRIMINAJA_PLUGIN_PATH . 'views/admin.php';
		} else {
			include_once KIRIMINAJA_PLUGIN_PATH . 'views/admin-inactive.php';
		}
	}

	/**
	 * Handle actions
	 */
	public function handle_actions() {
		if ( isset( $_REQUEST['kiriminaja_action'] ) ) { // Input var okay.

			// update setting.
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['kiriminaja_action'] ) ), 'update_setting' ) ) { // Input var okay.
				$new_setting = array();
				if ( isset( $_POST['kiriminaja_setting'] ) && is_array( $_POST['kiriminaja_setting'] ) ) { // Input var okay.
					foreach ( wp_unslash( $_POST['kiriminaja_setting'] ) as $key => $value ) { // WPCS: Input var okay, CSRF ok, sanitization ok.
						if ( ! is_array( $value ) ) {
							$new_setting[ $key ] = sanitize_text_field( wp_unslash( $value ) );
						} else {
							$new_setting[ $key ] = array_map( 'sanitize_text_field', wp_unslash( $value ) );
						}
					}
				}

				$old_setting = $this->setting->get_all();
				$new_setting = wp_parse_args( $new_setting, $old_setting );
				$this->setting->save( $new_setting );
				$this->core->purge_cache( 'cost' );
				$this->add_notice( __( 'Settings saved', 'kiriminaja' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=kiriminaja' ) );
				die;
			}

			do_action( 'kiriminaja_admin_handle_action', sanitize_text_field( wp_unslash( $_REQUEST['kiriminaja_action'] ) ), $this ); // Input var okay.
		}
	}

	/**
	 * Add notice
	 *
	 * @param string  $message Message.
	 * @param string  $type    Type.
	 * @param boolean $p       Using paragraph?.
	 */
	public function add_notice( $message = '', $type = 'success', $p = true ) {
		$old_notice = get_option( 'kiriminaja_notices', array() );
		$old_notice[] = array(
			'type'      => $type,
			'message'   => $p ? '<p>' . $message . '</p>' : $message,
		);
		update_option( 'kiriminaja_notices', $old_notice, false );
	}

	/**
	 * Show all notices
	 */
	public function show_notices() {
		$notices = get_option( 'kiriminaja_notices', array() );
		foreach ( $notices as $notice ) {
			echo '
				<div class="notice is-dismissible notice-' . esc_attr( $notice['type'] ) . '">
					' . wp_kses_post( $notice['message'] ) . '
				</div>';
		}
		update_option( 'kiriminaja_notices', array() );
	}

	/**
	 * Admin notice
	 */
	public function admin_notice() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' === $screen->id && isset( $_GET['section'] ) && 'kiriminaja' === sanitize_text_field( $_GET['section'] ) ) {
			return;
		}

		$errors = array();

		if ( ! $this->helper->is_woocommerce_active() ) {
			$errors[] = __( 'Woocommerce not active', 'kiriminaja' );
		}

		if ( ! function_exists( 'curl_version' ) ) {
			$errors[] = __( 'KiriminAja needs active CURL', 'kiriminaja' );
		}

		if ( ! $this->helper->is_token_set() ) {
			$errors[] = __( 'Token is not set.', 'kiriminaja' );
		}

		if ( ! $this->helper->is_store_set() ) {
			$store_name     = $this->setting->get( 'store_name' );
			$store_address  = $this->setting->get( 'store_address' );
			$store_province = $this->setting->get( 'store_province' );
			$store_city     = $this->setting->get( 'store_city' );
			$store_district = $this->setting->get( 'store_district' );
			$store_zipcode  = $this->setting->get( 'store_zipcode' );
			$store_phone    = $this->setting->get( 'store_phone' );
			$couriers       = $this->setting->get( 'couriers' );
			if ( empty( $store_name ) ) {
				$errors[] = __( 'Store Name is empty.', 'kiriminaja' );
			}
			if ( empty( $store_address ) ) {
				$errors[] = __( 'Store Address is empty.', 'kiriminaja' );
			}
			if ( empty( $store_province ) ) {
				$errors[] = __( 'Store Province is empty.', 'kiriminaja' );
			}
			if ( empty( $store_city ) ) {
				$errors[] = __( 'Store City is empty.', 'kiriminaja' );
			}
			if ( empty( $store_district ) ) {
				$errors[] = __( 'Store District is empty.', 'kiriminaja' );
			}
			if ( empty( $store_zipcode ) ) {
				$errors[] = __( 'Store Zip Code is empty.', 'kiriminaja' );
			}
			if ( empty( $store_phone ) ) {
				$errors[] = __( 'Store Phone is empty.', 'kiriminaja' );
			}
			if ( empty( $couriers ) ) {
				$errors[] = __( 'Couriers is empty.', 'kiriminaja' );
			}
		}

		$errors = apply_filters( 'kiriminaja_admin_errors', $errors );

		if ( ! empty( $errors ) ) {
			?>
			<div class="notice notice-error">
				<p><?php echo wp_kses_post( __( '<strong>KiriminAja</strong> is disabled due to the following errors:', 'kiriminaja' ) ); ?></p>
				<?php foreach ( $errors as $e ) : ?>
					<p style="margin:0;">- <?php echo esc_html( $e ); ?></p>
				<?php endforeach; ?>
				<p style="margin-top: 10px;"><a href="<?php echo esc_url( KIRIMINAJA_SETTING_URL ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'kiriminaja' ); ?></a></p>
			</div>
			<?php
		}
	}

}
