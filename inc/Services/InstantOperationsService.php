<?php
/** Read-only remote tracking and conservative Instant cancellation orchestration. */
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InstantOperationsService {
	private TransactionRepository $repo;
	private InstantDeliveryApiRepository $api;
	private InstantShipmentState $state;

	public function __construct( TransactionRepository $repo, InstantDeliveryApiRepository $api, InstantShipmentState $state ) {
		$this->repo = $repo;
		$this->api = $api;
		$this->state = $state;
	}

	public function track( array $ids ): array {
		return $this->query( $ids, false );
	}

	public function reconcile( array $ids ): array {
		return $this->query( $ids, true );
	}

	/** Validate the entire selection before any network access. Never book or retry. */
	private function selection( array $ids, int $limit = 10 ): array {
		if ( ! array_is_list( $ids ) || count( $ids ) < 1 || count( $ids ) > $limit ) {
			throw new InvalidArgumentException( esc_html__( 'Select 1 to 10 Instant shipments; cancellation requires exactly one shipment.', 'kiriminaja-official' ) );
		}
		$rows = array();
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) || ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $id ) || isset( $rows[ $id ] ) ) {
				$this->invalid();
			}
			$row = $this->repo->getTransactionByOrderId( $id );
			if ( ! is_object( $row ) || ( $row->order_id ?? null ) !== $id || 'instant' !== TransactionDeliveryType::resolve( $row )
				|| ! in_array( $row->service ?? null, array( 'gosend', 'grab_express' ), true )
				|| ! in_array( $row->status ?? null, array( 'pending', 'request_pickup', 'shipped', 'finished', 'canceled', 'return', 'returned', 'rejected' ), true ) ) {
				$this->invalid();
			}
			$rows[ $id ] = $row;
		}
		return $rows;
	}

	private function query( array $ids, bool $reconcile ): array {
		$reports = array();
		foreach ( $this->selection( $ids ) as $id => $row ) {
			$id = (string) $id;
			$url = InstantShipmentState::trackingUrl( $row->live_tracking_url ?? null );
			// A claimed/unknown booking must be verified even if a URL was left behind.
			if ( ! $reconcile && '' !== $url && 'pending' !== $row->status && null !== ( $row->instant_status_code ?? null ) ) {
				$reports[] = $this->report( $id, 'tracked', $url );
				continue;
			}
			$cached = get_transient( $this->key( 'report', $id ) );
			if ( is_array( $cached ) && ( $cached['id'] ?? null ) === $id ) {
				// Reuse only derived reports, never cache raw responses or replay writes.
				$cached['tracking_url'] = InstantShipmentState::trackingUrl( $cached['tracking_url'] ?? null );
				$reports[] = $cached;
				continue;
			}
			if ( ! $this->reserve( $id ) ) {
				$reports[] = $this->unknown( $id );
				continue;
			}
			$report = $this->refresh( $id, $row, $reconcile );
			set_transient( $this->key( 'report', $id ), $report, 10 );
			$reports[] = $report;
		}
		return array( 'rows' => $reports );
	}

	/** Site-scoped, shared across users and operation modes; reserve BEFORE the API. */
	private function key( string $kind, string $id ): string {
		return 'kiriof_instant_ops_' . $kind . '_' . hash( 'sha256', $id );
	}

	private function reserve( string $id ): bool {
		global $wpdb;

		$key = $this->key( 'throttle', $id );
		// Keep the cooldown beyond the 25-second GET timeout and below two
		// requests per minute per ID. Never release it on success or failure.
		$claim = array( 'owner' => wp_generate_uuid4(), 'expires' => time() + 40 );
		if ( add_option( $key, $claim, '', false ) ) {
			return true;
		}
		$previous = get_option( $key );
		// Legacy or malformed claims have no trustworthy expiry: fail closed.
		if ( ! is_array( $previous ) || ! is_string( $previous['owner'] ?? null ) || '' === $previous['owner']
			|| ! is_int( $previous['expires'] ?? null ) || $previous['expires'] > time() ) {
			return false;
		}
		// Exact-value CAS protects a replacement owner from stale cleanup.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Conditional expiry deletion cannot safely use delete_option; invalidate only after CAS succeeds.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", $key, maybe_serialize( $previous ) ) );
		if ( 1 !== $deleted ) {
			return false;
		}
		wp_cache_delete( $key, 'options' );
		return add_option( $key, $claim, '', false );
	}

	private function refresh( string $id, object $row, bool $reconcile ): array {
		try {
			$response = $this->api->tracking( $id );
			if ( true === ( $response['not_found'] ?? false ) ) {
				return $this->report( $id, 'not_found', '', __( 'Instant tracking data was not found. Reconciliation is required before retrying.', 'kiriminaja-official' ) );
			}
			$body = $this->body( $response );
			if ( ! is_object( $body->result ?? null ) ) {
				return $this->unknown( $id );
			}
			$package = get_object_vars( $body->result );
			$this->identity( $id, $row, $package, true );
			if ( array_key_exists( 'tracking_code', $package ) ) {
				if ( array_key_exists( 'awb', $package ) && $package['awb'] !== $package['tracking_code'] ) {
					$this->invalid();
				}
				$package['awb'] = $package['tracking_code'];
			}
			$dates = $package['date'] ?? array();
			$dates = is_object( $dates ) ? get_object_vars( $dates ) : $dates;
			if ( is_array( $dates ) ) {
				foreach ( array( 'finished_at', 'canceled_at' ) as $field ) {
					if ( isset( $dates[ $field ] ) ) {
						$package[ $field ] = $dates[ $field ];
					}
				}
			}
			$has_code = array_key_exists( 'status', $package ) || array_key_exists( 'status_code', $package ) || array_key_exists( 'instant_status_code', $package );
			if ( ! $has_code ) {
				$canceled = $this->properDateTime( $package['canceled_at'] ?? null );
				$finished = $this->properDateTime( $package['finished_at'] ?? null );
				if ( null !== $canceled ) {
					$package['status_code'] = 300;
					$package['canceled_at'] = $canceled;
				} elseif ( null !== $finished ) {
					$package['status_code'] = 200;
					$package['finished_at'] = $finished;
				} elseif ( ! isset( $package['awb'] ) || ! is_string( $package['awb'] ) || ! preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $package['awb'] ) ) {
					return $this->unknown( $id );
				}
			}
			$payment = $package['payment'] ?? array();
			$payment = is_object( $payment ) ? get_object_vars( $payment ) : $payment;
			if ( ! is_array( $payment ) ) {
				return $this->unknown( $id );
			}
			// AWB/URL evidence is metadata, never a fabricated remote lifecycle code.
			if ( ! array_key_exists( 'status_code', $package ) && ! $has_code ) {
				$merged = $this->state->mergeTrackingMetadata( $id, $package );
				if ( ! empty( $payment ) ) {
					$this->state->mergePayment( $id, $payment );
				}
			} else {
				$merged = $this->state->apply( $id, $package, $payment );
			}
			return $this->report( $id, $reconcile ? 'reconciled' : 'tracked', $merged['tracking_url'] );
		} catch ( \Throwable $error ) {
			return $this->unknown( $id );
		}
	}

	public function cancel( array $ids ): array {
		$rows = $this->selection( $ids, 1 );
		foreach ( $rows as $row ) {
			if ( ! $this->canCancel( $row ) ) {
				throw new InvalidArgumentException( esc_html__( 'This Instant shipment cannot be canceled.', 'kiriminaja-official' ) );
			}
		}
		$id = $ids[0];
		$row = $rows[ $id ];
		$report = $this->unknown( $id );
		if ( ! $this->reserve( $id ) ) {
			return array( 'rows' => array( $report ) );
		}
		// Never use a cached tracking result as permission to send DELETE.
		$refreshed = $this->refresh( $id, $row, true );
		if ( 'reconciled' !== $refreshed['status'] ) {
			return array( 'rows' => array( $refreshed ) );
		}
		$row = $this->repo->getTransactionByOrderId( $id );
		if ( ! $this->canCancel( $row ) ) {
			return array( 'rows' => array( $this->report( $id, 'unknown', $refreshed['tracking_url'], __( 'This Instant shipment cannot be canceled.', 'kiriminaja-official' ) ) ) );
		}
		try {
			// Persist the fence before DELETE. A competing claim or pickup wins.
			if ( ! $this->repo->claimInstantCancellation( $id, (int) $row->instant_status_code ) ) {
				return array( 'rows' => array( $report ) );
			}
			$response = $this->api->cancel( $id );
			if ( true !== ( $response['status'] ?? false ) ) {
				// A timeout may have accepted DELETE. Block repeated cancellation,
				// but do not claim terminal cancellation or touch the WC order.
				// The durable pre-request claim already blocks another DELETE.
			} else {
				$body = $this->body( $response );
				$packages = $body->result->packages ?? null;
				if ( true !== ( $response['operation_accepted'] ?? false ) || ! is_array( $packages ) || ! array_is_list( $packages ) || 1 !== count( $packages ) || ! is_object( $packages[0] ) ) {
					$this->invalid();
				}
				$package = get_object_vars( $packages[0] );
				$this->identity( $id, $row, $package, false );
				$payment_id = $body->result->payment_id ?? null;
				if ( null !== $payment_id && $payment_id !== ( $row->instant_payment_id ?? null ) ) {
					$this->invalid();
				}
				$code = null;
				foreach ( array( 'status', 'status_code', 'instant_status_code' ) as $field ) {
					if ( ! array_key_exists( $field, $package ) ) {
						continue;
					}
					$value = $package[ $field ];
					if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/\A[1-9][0-9]{2}\z/', $value ) ) ) {
						$this->invalid();
					}
					$value = (int) $value;
					if ( ! in_array( $value, array( 100, 101, 105, 106, 110, 200, 300, 302, 350, 400, 401, 402, 403, 404, 405, 500, 555, 701, 702, 703, 704, 303, 301, 333 ), true ) || ( null !== $code && $value !== $code ) ) {
						$this->invalid();
					}
					$code = $value;
				}
				if ( null === $code ) {
					$this->invalid();
				}
				$terminal = in_array( $code, array( 300, 302, '300', '302' ), true );
				// DELETE success with documented status 105 means accepted, not canceled.
				foreach ( array( 'status', 'status_code', 'instant_status_code' ) as $field ) {
					unset( $package[ $field ] );
				}
				$package['status_code'] = $terminal ? (int) $code : 350;
				$merged = $terminal ? $this->state->apply( $id, $package ) : $this->state->mergeTrackingMetadata( $id, $package );
				$confirmed = 'canceled' === $merged['status'];
				$report = $this->report( $id, $confirmed ? 'canceled' : 'cancel_requested', $merged['tracking_url'], $confirmed ? '' : __( 'Instant cancellation was requested. Reconcile to confirm the shipment state.', 'kiriminaja-official' ) );
			}
		} catch ( \Throwable $error ) {
			// Malformed, mismatched or timed-out DELETE may still have been accepted.
			// Never clear the durable claim or infer terminal cancellation here.
			$report = $this->unknown( $id );
		}
		set_transient( $this->key( 'report', $id ), $report, 10 );
		return array( 'rows' => array( $report ) );
	}

	private function body( array $response ): object {
		$body = $response['data'] ?? null;
		if ( true !== ( $response['status'] ?? false ) || ! is_object( $body ) || true !== ( $body->status ?? null ) || ! in_array( $body->code ?? null, array( 0, '0' ), true ) ) {
			$this->invalid();
		}
		return $body;
	}

	private function identity( string $id, object $row, array $package, bool $require_type ): void {
		if ( ( $package['order_id'] ?? null ) !== $id || ( $package['service'] ?? null ) !== $row->service
			|| ( $require_type && ! array_key_exists( 'service_type', $package ) )
			|| ( array_key_exists( 'service_type', $package ) && $package['service_type'] !== ( $row->service_name ?? $row->service_type ?? null ) ) ) {
			$this->invalid();
		}
		foreach ( array( 'awb', 'tracking_code' ) as $field ) {
			if ( array_key_exists( $field, $package ) && ( ! is_string( $package[ $field ] ) || ! preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $package[ $field ] ) || ( ! empty( $row->awb ) && $row->awb !== $package[ $field ] ) ) ) {
				$this->invalid();
			}
		}
	}

	private function properDateTime( $value ): ?string {
		if ( ! is_string( $value ) || strlen( $value ) > 35 ) {
			return null;
		}
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.u\Z', 'Y-m-d\TH:i:sP' ) as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );
			$errors = \DateTimeImmutable::getLastErrors();
			if ( false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( $format ) === $value && $date->getTimestamp() >= 946684800 && $date->getTimestamp() <= time() + 86400 ) {
				return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		return null;
	}

	private function canCancel( $row ): bool {
		// A known local code alone is insufficient for an uncertain booking.
		return is_object( $row ) && InstantShipmentState::canCancel( $row )
			&& is_string( $row->awb ?? null ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $row->awb );
	}

	private function report( string $id, string $status, string $url = '', string $message = '' ): array {
		return array( 'id' => $id, 'status' => $status, 'tracking_url' => InstantShipmentState::trackingUrl( $url ), 'message' => $message );
	}

	private function unknown( string $id ): array {
		return $this->report( $id, 'unknown', '', __( 'Instant shipment state requires reconciliation. Please wait before checking again.', 'kiriminaja-official' ) );
	}

	private function invalid(): void {
		throw new InvalidArgumentException( esc_html__( 'Invalid Instant shipment selection or remote identity.', 'kiriminaja-official' ) );
	}
}
