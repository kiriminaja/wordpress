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
