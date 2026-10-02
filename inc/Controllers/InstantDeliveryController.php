<?php
namespace KiriminAjaOfficial\Controllers;

use InvalidArgumentException;
use KiriminAjaOfficial\Services\InstantDispatchService;
use KiriminAjaOfficial\Services\InstantLabelService;
use KiriminAjaOfficial\Services\InstantOperationsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authenticated admin endpoints for Instant shipments and local labels. */
class InstantDeliveryController {
	private InstantDispatchService $dispatch_service;
	private InstantLabelService $label_service;
	private InstantOperationsService $operations_service;

	public function __construct( InstantDispatchService $dispatch_service, InstantLabelService $label_service, InstantOperationsService $operations_service ) {
		$this->dispatch_service   = $dispatch_service;
		$this->label_service      = $label_service;
		$this->operations_service = $operations_service;
	}


	public function register(): void {
		add_action( 'wp_ajax_kiriof_instant_quote', array( $this, 'quote' ) );
		add_action( 'wp_ajax_kiriof_instant_validate_credit', array( $this, 'validateCredit' ) );
		add_action( 'wp_ajax_kiriof_instant_dispatch', array( $this, 'dispatch' ) );
		add_action( 'wp_ajax_kiriof_instant_payment', array( $this, 'payment' ) );
		add_action( 'wp_ajax_kiriof_instant_label_preview', array( $this, 'labelPreview' ) );
		add_action( 'wp_ajax_kiriof_instant_tracking', array( $this, 'tracking' ) );
		add_action( 'wp_ajax_kiriof_instant_reconcile', array( $this, 'reconcile' ) );
		add_action( 'wp_ajax_kiriof_instant_cancel', array( $this, 'cancel' ) );
		add_action( 'admin_post_kiriof_instant_labels', array( $this, 'labels' ) );
	}

	public function quote(): void {
		$this->ajax( 'quote' );
	}

	public function dispatch(): void {
		$this->ajax( 'dispatch' );
	}

	public function payment(): void {
		$this->ajax( 'payment' );
	}

	public function labelPreview(): void {
		$this->ajax( 'labelPreview' );
	}

	public function tracking(): void {
		$this->ajax( 'tracking' );
	}

	public function reconcile(): void {
		$this->ajax( 'reconcile' );
	}

	public function cancel(): void {
		$this->ajax( 'cancel' );
	}

	public function validateCredit(): void {
		$this->ajax( 'validate_credit' );
	}


	/** Validate authorization and the complete request before invoking any service. */
	private function ajax( string $operation ): void {
		try {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				throw new InvalidArgumentException( esc_html__( 'Insufficient permissions', 'kiriminaja-official' ) );
			}
			if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
				$this->invalid();
			}
			// Reject sanitized lookalikes before verifying the exact incoming nonce.
			if ( ! isset( $_POST['data']['nonce'] ) || ! is_string( $_POST['data']['nonce'] ) || sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ) !== wp_unslash( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed nonce is used only to reject sanitization changes; verification uses the identical sanitized value.
				throw new InvalidArgumentException( esc_html__( 'Security check failed', 'kiriminaja-official' ) );
			}
			$data = wp_unslash( $_POST['data'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each used field is strictly validated below; unslash the payload exactly once.
			$ids  = $this->postedIds( $data );
			if ( in_array( $operation, array( 'tracking', 'reconcile', 'cancel' ), true ) ) {
				if ( count( $ids ) > 10 ) {
					$this->invalid();
				}
				foreach ( $ids as $id ) {
					if ( ! is_string( $id ) ) {
						$this->invalid();
					}
				}
			}
			switch ( $operation ) {
				case 'tracking':
					$result = $this->operations_service->track( $ids );
					break;
				case 'reconcile':
					$result = $this->operations_service->reconcile( $ids );
					break;
				case 'cancel':
					if ( 1 !== count( $ids ) || ! isset( $data['confirmed'] ) || 'yes' !== $data['confirmed'] ) {
						$this->invalid();
					}
					$result = $this->operations_service->cancel( $ids );
					break;
				case 'quote':
					$result = $this->dispatch_service->quote( $ids );
					break;
				case 'validate_credit':
					$token  = $this->field( $data, 'token' );
					$pin    = $this->field( $data, 'pin', true );
					$result = $this->dispatch_service->validateCredit( $ids, $token, $pin );
					break;
				case 'dispatch':
					// Review is explicit; truthy values and sanitized lookalikes are not consent.
					if ( ! isset( $data['confirmed'] ) || ! is_string( $data['confirmed'] ) || 'yes' !== $data['confirmed'] ) {
						throw new InvalidArgumentException( esc_html__( 'Review and confirm the Instant shipping costs before dispatch.', 'kiriminaja-official' ) );
					}
					$token  = $this->field( $data, 'token' );
					$method = $this->field( $data, 'method' );
					$pin    = array_key_exists( 'pin', $data ) ? $this->field( $data, 'pin', true ) : '';
					$result = $this->dispatch_service->dispatch( $token, $ids, $method, $pin );
					break;
				case 'payment':
					$payment_id = $this->field( $data, 'payment_id' );
					$result     = $this->dispatch_service->refreshPayment( $ids, $payment_id );
					break;
				default:
					$this->label_service->prepare( $ids );
					$result = array(
						'url'  => add_query_arg(
							array(
								'action'   => 'kiriof_instant_labels',
								'oids'     => implode( ',', $ids ),
								'_wpnonce' => wp_create_nonce( 'kiriof_instant_labels' ),
							),
							admin_url( 'admin-post.php' )
						),
						'type' => 'html',
					);
			}
		} catch ( InvalidArgumentException $error ) {
			// These services use fixed, translated validation messages, not remote API errors.
			wp_send_json_error(
				array(
					'status'  => 400,
					'message' => $error->getMessage(),
				),
				400
			);
			return;
		} catch ( \Throwable $error ) {
			wp_send_json_error(
				array(
					'status'  => 503,
					'message' => $this->failureMessage(),
				),
				503
			);
			return;
		}
		wp_send_json_success(
			array(
				'status' => 200,
				'data'   => $result,
			)
		);
	}

	public function labels(): void {
		try {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				throw new InvalidArgumentException( esc_html__( 'Insufficient permissions', 'kiriminaja-official' ) );
			}
			// Verify the exact nonce, never a sanitized value or the general AJAX nonce.
			if ( ! isset( $_GET['_wpnonce'] ) || ! is_string( $_GET['_wpnonce'] ) || sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) !== wp_unslash( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'kiriof_instant_labels' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw unslashed nonce is compared for exactness, never accepted after sanitization changes.
				throw new InvalidArgumentException( esc_html__( 'Security check failed', 'kiriminaja-official' ) );
			}
			nocache_headers();
			if ( ! isset( $_GET['oids'] ) || ! is_string( $_GET['oids'] ) ) {
				$this->invalid();
			}
			$oids = sanitize_text_field( wp_unslash( $_GET['oids'] ) );
			if ( $oids !== wp_unslash( $_GET['oids'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compare raw input only to reject changes; only the sanitized, validated IDs reach the service.
				$this->invalid();
			}
			$ids    = $this->validateIds( explode( ',', $oids ) );
			$labels = $this->label_service->prepare( $ids );
		} catch ( InvalidArgumentException $error ) {
			wp_die( esc_html( $error->getMessage() ) );
			return;
		} catch ( \Throwable $error ) {
			wp_die( esc_html( $this->failureMessage() ) );
			return;
		}
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		// The local template uses inline CSS and a print button script.
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'" );
		include KIRIOF_DIR . 'templates/instant/labels.php';
	}

	private function postedIds( array $data ): array {
		if ( ! isset( $data['order_ids'] ) || ! is_string( $data['order_ids'] ) ) {
			$this->invalid();
		}
		// Decode without associative conversion so a JSON object cannot masquerade as a list.
		$ids = json_decode( $data['order_ids'] );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $ids ) ) {
			$this->invalid();
		}
		return $this->validateIds( $ids );
	}

	private function validateIds( array $ids ): array {
		if ( count( $ids ) < 1 || count( $ids ) > 50 ) {
			$this->invalid();
		}
		$seen = array();
		foreach ( $ids as $id ) {
			if ( ( ! is_string( $id ) && ! is_int( $id ) ) || ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', (string) $id ) || sanitize_text_field( (string) $id ) !== (string) $id || isset( $seen[ (string) $id ] ) ) {
				$this->invalid();
			}
			$seen[ (string) $id ] = true;
		}
		return $ids;
	}

	private function field( array $data, string $key, bool $allow_empty = false ): string {
		if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
			$this->invalid();
		}
		$value = $data[ $key ];
		$clean = sanitize_text_field( $value );
		if ( $clean !== $value || ( ! $allow_empty && '' === $clean ) ) {
			$this->invalid();
		}
		return $clean;
	}

	private function invalid(): void {
		throw new InvalidArgumentException( esc_html__( 'Invalid Instant request parameters.', 'kiriminaja-official' ) );
	}

	private function failureMessage(): string {
		return __( 'Unable to complete the Instant request. Please try again.', 'kiriminaja-official' );
	}
}
