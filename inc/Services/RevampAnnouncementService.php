<?php

namespace KiriminAjaOfficial\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Base\BaseService;

/**
 * One-time "big revamp" announcement shown on first workspace visit after update.
 *
 * Gated per user + per version via user meta so every existing user sees it
 * exactly once after updating, without extra redirect flags.
 */
final class RevampAnnouncementService extends BaseService {
	public const ANNOUNCEMENT_VERSION = '2.4.0';
	private const SEEN_META_KEY = 'kiriof_revamp_announcement_seen_version';
	private const REVIEW_URL = 'https://wordpress.org/plugins/kiriminaja-official/#reviews';

	public function register() {
		add_action( 'wp_ajax_kiriof_dismiss_revamp_announcement', array( $this, 'kiriof_ajax_dismiss' ) );
	}

	public function kiriof_ajax_dismiss() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ), 403 );
		}

		$nonce = '';
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		} elseif ( isset( $_POST['data'] ) && is_array( $_POST['data'] ) && isset( $_POST['data']['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) );
		}
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, KIRIOF_NONCE ) ) {
			wp_send_json_error( array( 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ), 403 );
		}

		$this->mark_seen( get_current_user_id() );

		wp_send_json_success( array( 'status' => 200 ) );
	}

	/**
	 * Prepare the announcement payload for the shared Svelte workspace toolbar.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_announcement(): ?array {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( $user_id <= 0 || $this->has_seen( (int) $user_id ) ) {
			return null;
		}

		return array(
			'imageUrl'      => ( defined( 'KIRIOF_URL' ) ? KIRIOF_URL : '' ) . 'assets/admin/img/revamp-announcement.svg',
			'title'         => __( 'KiriminAja has a new look', 'kiriminaja-official' ),
			'description'   => __( 'Transactions, Pickup, Settings, and Tracking are now easier to navigate.', 'kiriminaja-official' ),
			'feedbackUrl'   => self::REVIEW_URL,
			'feedbackLabel' => __( 'Rate KiriminAja', 'kiriminaja-official' ),
			'continueLabel' => __( 'Continue to KiriminAja', 'kiriminaja-official' ),
		);
	}

	/**
	 * Attach the announcement payload to a toolbar array when available.
	 *
	 * @param array<string, mixed> $toolbar Toolbar payload.
	 * @return array<string, mixed>
	 */
	public static function attach_announcement( array $toolbar ): array {
		$announcement = ( new self() )->get_announcement();
		if ( null !== $announcement ) {
			$toolbar['announcement'] = $announcement;
		}

		return $toolbar;
	}

	public function has_seen( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return true;
		}

		return self::ANNOUNCEMENT_VERSION === (string) get_user_meta( $user_id, self::SEEN_META_KEY, true );
	}

	public function mark_seen( int $user_id ): void {
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::SEEN_META_KEY, self::ANNOUNCEMENT_VERSION );
		}
	}
}
