<?php
namespace KiriminAjaOfficial\Pages;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Base\BaseInit;
use KiriminAjaOfficial\Services\OnboardingSetupStateService;

class Onboarding extends BaseInit {
	private const PAGE_SLUG = 'kiriminaja-onboarding';
	private const REDIRECT_OPTION = 'kiriof_onboarding_activation_redirect';
	private const UPDATE_REDIRECT_OPTION = 'kiriof_onboarding_update_redirect';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ), 20 );
		// Hide only after WordPress validates the registered page capability.
		add_action( 'admin_head', array( $this, 'hide_page' ) );
		add_action( 'current_screen', array( $this, 'suppress_admin_notices' ), 1 );
		add_action( 'admin_init', array( $this, 'redirect_incomplete_setup' ), 20 );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'kiriminaja-setting',
			__( 'KiriminAja Setup', 'kiriminaja-official' ),
			__( 'Setup', 'kiriminaja-official' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function hide_page(): void {
		remove_submenu_page( 'kiriminaja-setting', self::PAGE_SLUG );
	}

	public function suppress_admin_notices( $screen = null ): void {
		if ( ! $this->is_onboarding_page() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
		}

		$state_service = new OnboardingSetupStateService();
		$steps            = $state_service->get_steps();
		$origin_values    = $state_service->get_origin_values();
		$connection_state = $state_service->get_connection_state();
		$current_step     = $state_service->is_required_complete() ? 'complete' : $state_service->get_first_incomplete_step();

		$kiriof_is_connected = $connection_state['is_connected'];
		$kiriof_profile      = $connection_state['profile'];
		$kiriof_profile_err  = $connection_state['profile_err'];
		$kiriof_onboarding_bootstrap = $this->build_bootstrap(
			$steps,
			$origin_values,
			$current_step,
			(bool) $kiriof_is_connected,
			$kiriof_profile,
			(bool) $kiriof_profile_err
		);

		include KIRIOF_DIR . 'templates/onboarding/index.php';
	}

	private function build_bootstrap( array $steps, array $origin_values, string $current_step, bool $is_connected, $profile, bool $profile_error ): array {
		$required_steps = array_filter(
			$steps,
			static function ( $step ) {
				return $step['required'];
			}
		);
		$progress_steps = array_values(
			array_map(
				static function ( $step ) {
					return array(
						'key'   => $step['key'],
						'label' => $step['nav_title'] ?? $step['title'],
						'done'  => (bool) $step['done'],
					);
				},
				$required_steps
			)
		);

		return array(
			'initialStep'  => $current_step,
			'steps'        => $progress_steps,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( KIRIOF_NONCE ),
			'shippingUrl'  => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			'logoUrl'      => esc_url( KIRIOF_URL . 'assets/admin/img/logo-tagline.svg' ),
			'dashboardUrl' => esc_url( admin_url() ),
			'helpUrl'      => 'https://kiriminaja.com/solusi/plugin-woocommerce',
			'i18n'         => array(
				'back'                      => __( 'Back', 'kiriminaja-official' ),
				'continue'                  => __( 'Continue', 'kiriminaja-official' ),
				'finish'                    => __( 'Finish setup', 'kiriminaja-official' ),
				'accountRequired'           => __( 'Connect your KiriminAja account before continuing.', 'kiriminaja-official' ),
				'saveFailed'                => __( 'Could not save this step.', 'kiriminaja-official' ),
				'networkError'              => __( 'Network error. Please try again.', 'kiriminaja-official' ),
				'disconnectConfirm'         => __( 'Disconnect KiriminAja integration?', 'kiriminaja-official' ),
				'disconnectFailed'          => __( 'Disconnect failed.', 'kiriminaja-official' ),
				'subdistrictLoading'        => __( 'Searching subdistricts...', 'kiriminaja-official' ),
				'subdistrictNoResults'      => __( 'No subdistricts found.', 'kiriminaja-official' ),
				'subdistrictTypeMore'       => __( 'Type at least 3 characters.', 'kiriminaja-official' ),
				'subdistrictSearchFailed'   => __( 'Could not search subdistricts. Check the KiriminAja connection and try again.', 'kiriminaja-official' ),
				'currentLocation'           => __( 'Use current location', 'kiriminaja-official' ),
				'currentLocationUnavailable' => __( 'Current location is not available in this browser.', 'kiriminaja-official' ),
				'currentLocationFailed'     => __( 'Could not detect your current location.', 'kiriminaja-official' ),
				'shippingAddressRequired'   => __( 'Complete all address fields and set the map pin.', 'kiriminaja-official' ),
				'courierRequired'           => __( 'Select at least one courier service.', 'kiriminaja-official' ),
				'shippingPrerequisite'      => __( 'Complete previous required steps before finishing.', 'kiriminaja-official' ),
				'addressSaved'              => __( 'Shipping address saved.', 'kiriminaja-official' ),
				'accountConnected'          => __( 'Account connected.', 'kiriminaja-official' ),
				'couriersSaved'             => __( 'Courier services saved.', 'kiriminaja-official' ),
				'shippingLocationsRequired' => __( 'Enable WooCommerce shipping locations before finishing.', 'kiriminaja-official' ),
			),
			'account'      => array(
				'connected'    => $is_connected,
				'profileError' => $profile_error,
				'profile'      => $profile ? array(
					'name'          => (string) ( $profile->name ?? '' ),
					'email'         => (string) ( $profile->email ?? '' ),
					'status'        => (string) ( $profile->status ?? '' ),
					'paymentMethod' => (string) ( $profile->metadata->payment_method ?? '' ),
				) : null,
				'title'        => $steps['account']['title'],
				'description'  => $steps['account']['description'],
				'helpUrl'      => 'https://help.kiriminaja.com/article/setup-wordpress',
				'i18n'         => array(
					'connection'          => __( 'Connection', 'kiriminaja-official' ),
					'setupKey'            => __( 'Setup key', 'kiriminaja-official' ),
					'setupKeyPlaceholder' => __( 'Paste your setup key', 'kiriminaja-official' ),
					'findKey'             => __( 'Find this key in your KiriminAja application.', 'kiriminaja-official' ),
					'learnHow'            => __( 'Learn how', 'kiriminaja-official' ),
					'disconnect'          => __( 'Disconnect', 'kiriminaja-official' ),
					'unavailable'         => __( 'Unable to load account information. Your integration may be incomplete.', 'kiriminaja-official' ),
				),
			),
			'address'      => array(
				'title'       => $steps['address']['title'],
				'description' => $steps['address']['description'],
				'values'      => array_map( 'strval', $origin_values ),
				'i18n'        => array(
					'senderName'        => __( 'Sender name', 'kiriminaja-official' ),
					'senderPhone'       => __( 'Sender phone', 'kiriminaja-official' ),
					'address'           => __( 'Address', 'kiriminaja-official' ),
					'zipcode'           => __( 'Zipcode', 'kiriminaja-official' ),
					'subdistrict'       => __( 'Subdistrict', 'kiriminaja-official' ),
					'searchSubdistrict' => __( 'Search subdistrict', 'kiriminaja-official' ),
					'mapHelp'           => __( 'Move the map to place the pin at your pickup location.', 'kiriminaja-official' ),
				),
			),
			'couriers'     => array(
				'title'       => $steps['couriers']['title'],
				'description' => $steps['couriers']['description'],
				'i18n'        => array(
					'enableAll'  => __( 'Enable all', 'kiriminaja-official' ),
					'disableAll' => __( 'Disable all', 'kiriminaja-official' ),
					'loading'    => __( 'Loading couriers…', 'kiriminaja-official' ),
					'empty'      => __( 'No courier services are available.', 'kiriminaja-official' ),
					'enabled'    => __( 'enabled', 'kiriminaja-official' ),
					'enable'     => __( 'Enable', 'kiriminaja-official' ),
				),
			),
			'shipping'     => array(
				'title'          => $steps['shipping']['title'],
				'description'    => $steps['shipping']['description'],
				'shippingReady'  => (bool) $steps['shipping']['shipping_ready'],
				'locationsReady' => (bool) $steps['shipping']['locations_ready'],
				'settingsUrl'    => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
				'i18n'           => array(
					'methodTitle'          => __( 'KiriminAja shipping method enabled', 'kiriminaja-official' ),
					'methodDescription'    => __( 'Adds KiriminAja as an available WooCommerce shipping method for checkout rates.', 'kiriminaja-official' ),
					'locationsTitle'       => __( 'WooCommerce shipping locations configured', 'kiriminaja-official' ),
					'locationsDescription' => __( 'Confirms WooCommerce can show shipping choices to customers in supported regions.', 'kiriminaja-official' ),
					'help'                 => __( 'Shipping locations must be enabled in WooCommerce before rates can appear at checkout.', 'kiriminaja-official' ),
					'openSettings'         => __( 'Open shipping settings', 'kiriminaja-official' ),
				),
			),
		);
	}

	public function redirect_incomplete_setup(): void {
		$has_activation_redirect = (bool) get_option( self::REDIRECT_OPTION, false );
		$has_update_redirect     = (bool) get_option( self::UPDATE_REDIRECT_OPTION, false );
		$should_redirect         = $this->should_consume_activation_redirect()
			&& ( $has_activation_redirect || $has_update_redirect || $this->is_kiriminaja_admin_page() );

		if ( ! $should_redirect ) {
			return;
		}

		$state_service = new OnboardingSetupStateService();
		if ( $state_service->is_required_complete() ) {
			delete_option( self::REDIRECT_OPTION );
			delete_option( self::UPDATE_REDIRECT_OPTION );
			return;
		}

		if ( $has_activation_redirect ) {
			delete_option( self::REDIRECT_OPTION );
		}
		if ( $has_update_redirect ) {
			delete_option( self::UPDATE_REDIRECT_OPTION );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function add_body_class( string $classes ): string {
		if ( $this->is_onboarding_page() ) {
			$classes .= ' kiriof-onboarding-screen';
		}

		return $classes;
	}

	private function should_consume_activation_redirect(): bool {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) || is_network_admin() ) {
			return false;
		}

		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( $this->is_onboarding_page() ) {
			return false;
		}

		global $pagenow;
		if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'plugins.php', 'plugin-install.php', 'update.php', 'update-core.php', 'profile.php', 'user-edit.php' ), true ) ) {
			return false;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
		$tab  = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( 'wc-settings' === $page && in_array( $tab, array( 'shipping', 'kiriminaja_warehouses' ), true ) ) {
			return false;
		}

		return true;
	}

	private function is_onboarding_page(): bool {
		return self::PAGE_SLUG === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );
	}

	private function is_kiriminaja_admin_page(): bool {
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS );

		return in_array(
			$page,
			array(
				'kiriminaja-setting',
				'kiriminaja-transaction',
				'kiriminaja-request-pickup',
				'kiriminaja-request-pickup-detail',
			),
			true
		);
	}
}
