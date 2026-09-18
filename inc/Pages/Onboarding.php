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
			'kiriminaja-konfigurasi',
			__( 'KiriminAja Setup', 'kiriminaja-official' ),
			__( 'Setup', 'kiriminaja-official' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function hide_page(): void {
		remove_submenu_page( 'kiriminaja-konfigurasi', self::PAGE_SLUG );
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

		include KIRIOF_DIR . 'templates/onboarding/index.php';
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
				'kiriminaja-konfigurasi',
				'kiriminaja-transaction-process',
				'kiriminaja-request-pickup',
				'kiriminaja-request-pickup-detail',
			),
			true
		);
	}
}
