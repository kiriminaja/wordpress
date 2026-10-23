<?php

namespace KiriminAjaOfficial\Controllers;

use KiriminAjaOfficial\Services\GoogleMapsSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Protected settings-only endpoint. No public or credential-read AJAX action. */
class GoogleMapsSettingsController {
	private GoogleMapsSettings $settings;

	public function __construct( ?GoogleMapsSettings $settings = null ) {
		$this->settings = $settings ?? new GoogleMapsSettings();
	}

	public function register(): void {
		add_action( 'wp_ajax_kiriof_save_google_maps_settings', array( $this, 'save' ) );
		add_action( 'template_redirect', array( $this, 'maybeBrowserCheck' ), 0 );
	}

	/** Intercept only the protected storefront diagnostic, before canonical redirects. */
	public function maybeBrowserCheck(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route selection only; browserCheck verifies authorization and nonce before reading the key.
		$query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		$browser_check = isset( $query['kiriof_google_maps_browser_check'] ) && is_string( $query['kiriof_google_maps_browser_check'] ) ? sanitize_text_field( $query['kiriof_google_maps_browser_check'] ) : '';
		if ( '1' === $browser_check && $browser_check === $query['kiriof_google_maps_browser_check'] ) {
			$this->browserCheck();
		}
	}

	/** Render a storefront-origin diagnostic for the configured admin parent. */
	public function browserCheck(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Exact method comparison, never reflected.
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$this->browserCheckError( 405 );
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->browserCheckError( 403 );
			return;
		}
		$query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		$nonce_value = $query['_wpnonce'] ?? $query['nonce'] ?? '';
		$nonce = is_string( $nonce_value ) ? sanitize_text_field( $nonce_value ) : '';
		if ( ! is_string( $nonce_value ) || $nonce !== $nonce_value || ! wp_verify_nonce( $nonce, GoogleMapsSettings::NONCE_ACTION ) ) {
			$this->browserCheckError( 403 );
			return;
		}
		$channel_value = $query['channel'] ?? '';
		$channel = is_string( $channel_value ) ? sanitize_text_field( $channel_value ) : '';
		if ( ! is_string( $channel_value ) || $channel !== $channel_value || 1 !== preg_match( '/\A[A-Za-z0-9_-]{16,80}\z/D', $channel ) ) {
			$this->browserCheckError( 400 );
			return;
		}

		// Never accept a caller-supplied postMessage destination.
		$target_origin = $this->configuredOrigin( admin_url( '/' ) );
		$probe_origin  = $this->configuredOrigin( home_url( '/' ) );
		if ( '' === $target_origin || '' === $probe_origin ) {
			$this->browserCheckError( 500 );
			return;
		}
		// Read the saved browser key only after all authorization and request validation.
		$config = array(
			'apiKey'       => $this->settings->config()['apiKey'],
			'channel'      => $channel,
			'targetOrigin' => $target_origin,
			'probeOrigin'  => $probe_origin,
			'i18n'         => array(
				'loading' => __( 'Checking Google Maps in this browser…', 'kiriminaja-official' ),
				'ready'   => __( 'Google Maps tiles loaded in this browser.', 'kiriminaja-official' ),
				'error'   => __( 'Google Maps could not be verified. Check the saved key, allowed website referrers, Maps JavaScript API, billing, and quota in Google Cloud.', 'kiriminaja-official' ),
			),
		);
		wp_enqueue_script( 'kiriof-google-maps-browser-check', KIRIOF_URL . 'assets/admin/js/google-maps-browser-check.js', array(), KIRIOF_VERSION, array( 'strategy' => 'defer' ) );
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header_remove( 'X-Frame-Options' );
		header( 'Content-Security-Policy: frame-ancestors ' . $target_origin, false );
		require KIRIOF_DIR . 'templates/admin/google-maps-browser-check.php';
		exit;
	}

	private function browserCheckError( int $status ): void {
		nocache_headers();
		wp_die( esc_html__( 'Unable to check Google Maps in this browser.', 'kiriminaja-official' ), '', array( 'response' => absint( $status ) ) );
	}

	private function configuredOrigin( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || 1 !== preg_match( '/\A(?:[a-z0-9.-]+|\[[a-f0-9:]+\])\z/iD', $parts['host'] ) ) {
			return '';
		}
		$origin       = $parts['scheme'] . '://' . strtolower( $parts['host'] );
		$default_port = 'https' === $parts['scheme'] ? 443 : 80;
		return $origin . ( isset( $parts['port'] ) && $default_port !== $parts['port'] ? ':' . $parts['port'] : '' );
	}

	public function save(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Exact server method comparison only; never rendered or persisted.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$this->error( 405 );
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			$this->error( 403 );
			return;
		}
		// The existing postWordPressAction transport nests data and allows a dedicated nonce override.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing -- Capture only the envelope to verify its dedicated nonce below; each accepted scalar is unslashed exactly once and strictly allowlisted after authorization. Never persist this array.
		$data = isset( $_POST['data'] ) && is_array( $_POST['data'] ) ? $_POST['data'] : array();
		$nonce = isset( $data['nonce'] ) && is_string( $data['nonce'] ) ? wp_unslash( $data['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, GoogleMapsSettings::NONCE_ACTION ) ) {
			$this->error( 403 );
			return;
		}

		// Authorization is complete before parsing any key or mutation mode.
		$mode = isset( $data['mode'] ) && is_string( $data['mode'] ) ? wp_unslash( $data['mode'] ) : '';
		if ( 'remove' === $mode ) {
			$success = $this->settings->remove();
		} elseif ( 'save' === $mode ) {
			$key = $data['key'] ?? '';
			if ( ! is_string( $key ) ) {
				$this->error( 400 );
				return;
			}
			$success = $this->settings->save( wp_unslash( $key ) );
		} else {
			$this->error( 400 );
			return;
		}
		if ( ! $success ) {
			$this->error( 400 );
			return;
		}
		wp_send_json_success( array( 'status' => 200, 'data' => $this->settings->settingsSummary() ) );
	}

	private function error( int $status ): void {
		// Never reflect submitted data or exception messages into a settings response.
		wp_send_json_error( array( 'status' => $status, 'message' => __( 'Unable to update Google Maps settings.', 'kiriminaja-official' ) ), $status );
	}
}
