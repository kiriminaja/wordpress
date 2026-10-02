<?php
/** Monotonic merge of authenticated Instant shipment responses. */
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use RuntimeException;
use KiriminAjaOfficial\Repositories\TransactionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InstantShipmentState {
	private TransactionRepository $repo;

	public function __construct( TransactionRepository $repo ) {
		$this->repo = $repo;
	}

	/** Verified booking responses capitalize Instant; do not broaden identity matching. */
	public static function sameServiceType( $remote, $expected ): bool {
		if ( ! is_string( $remote ) || ! is_string( $expected ) ) {
			return false;
		}
		return $remote === $expected || ( in_array( strtolower( $expected ), array( 'instant', 'sameday' ), true ) && strtolower( $remote ) === strtolower( $expected ) );
	}

	/** Query uncertain bookings without interpreting the claim as remote acceptance. */
	public static function canRecheck( $row ): bool {
		$row = is_object( $row ) ? get_object_vars( $row ) : $row;
		return is_array( $row ) && 'instant' === TransactionDeliveryType::resolve( $row )
			&& in_array( $row['service'] ?? null, array( 'gosend', 'grab_express' ), true )
			&& 'pending' === ( $row['status'] ?? null ) && null === ( $row['instant_status_code'] ?? null )
			&& in_array( $row['awb'] ?? null, array( null, '' ), true )
			&& in_array( $row['instant_payment_id'] ?? null, array( null, '' ), true )
			&& is_string( $row['order_id'] ?? null ) && 1 === preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $row['order_id'] );
	}

	/**
	 * Call only after authenticating the response/webhook. This does not book orders.
	 *
	 * @return array{changed:bool,status:string,tracking_url:string,order_id:string}
	 * @throws InvalidArgumentException Malformed or mismatched remote identity.
	 * @throws RuntimeException Unverified persistence or WooCommerce side effects (retry).
	 */
	public function apply( string $id, array $package, array $payment = array(), ?string $event = null ): array {
		return $this->merge( $id, $package, $payment, $event );
	}

	/** Merge only authenticated tracking metadata, never invent a lifecycle code. */
	public function mergeTrackingMetadata( string $id, array $package ): array {
		return $this->merge( $id, $package, array(), null, 'metadata' );
	}

	/** Called only for a response matched to the authenticated booking request. */
	public function confirmBooking( string $id, array $package, array $payment, array $bookingMetadata ): array {
		return $this->merge( $id, $package, $payment, null, 'booking', $bookingMetadata );
	}

	/** Refresh an existing payment independently of shipment lifecycle. */
	public function mergePayment( string $id, array $payment ): array {
		return $this->merge( $id, array( 'order_id' => $id ), $payment, null, 'payment' );
	}

	private function merge( string $id, array $package, array $payment, ?string $event, string $mode = 'apply', array $metadata = array() ): array {
		if ( ! self::identifier( $id ) || ! isset( $package['order_id'] ) || ! is_string( $package['order_id'] ) || $id !== $package['order_id'] ) {
			$this->invalid();
		}
		$code = in_array( $mode, array( 'metadata', 'payment' ), true ) ? null : $this->remoteCode( $package, $payment, $event );
		$now = gmdate( 'Y-m-d H:i:s' );
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$row = $this->repo->getTransactionByOrderId( $id );
			if ( false === $row ) {
				$this->failed();
			}
			$this->validate( $row, $id, $package, $payment, 'booking' === $mode );
			if ( 'payment' === $mode && ( empty( $row->instant_payment_id ) || ( $payment['id'] ?? $payment['payment_id'] ?? null ) !== $row->instant_payment_id ) ) {
				$this->invalid();
			}
			if ( 'booking' === $mode ) {
				foreach ( array( 'live_tracking_url', 'live_track_url', 'tracking_url', 'live_tracking' ) as $field ) {
					if ( isset( $package[ $field ] ) && '' !== $package[ $field ] && '' === self::trackingUrl( $package[ $field ] ) ) {
						$this->invalid();
					}
				}
			}
			$order = 'payment' === $mode || ( 'metadata' === $mode && 'finished' !== $row->status ) ? null : $this->wooOrder( $row );
			$old = (string) $row->status;
			$target = null === $code ? $old : $this->localStatus( $code, $old );
			$stale = ( in_array( $old, array( 'finished', 'canceled', 'returned' ), true ) && $target !== $old )
				|| ( 'shipped' === $old && 'request_pickup' === $target )
				|| ( 'return' === $old && in_array( $target, array( 'request_pickup', 'shipped' ), true ) );
			// Issues and cancellation-in-progress must not erase a delivered/terminal code.
			if ( in_array( $old, array( 'finished', 'canceled', 'returned' ), true ) && (int) ( $row->instant_status_code ?? -1 ) !== $code ) {
				$stale = true;
			}
			$stale = $stale || null === $code || ( 350 === (int) ( $row->instant_status_code ?? -1 ) && in_array( $code, array( 100, 101, 105, 110 ), true ) );
			// A delayed booking acknowledgement cannot resolve an issue already reported by a callback.
			// Authenticated tracking/webhook state remains authoritative for subsequent recovery.
			if ( 'booking' === $mode && in_array( (int) ( $row->instant_status_code ?? -1 ), array( 405, 500, 555, 701, 702, 703, 704, 303, 301, 333 ), true ) && in_array( $code, array( 100, 101, 105, 110 ), true ) ) {
				$stale = true;
			}
			$changes = 'booking' === $mode ? $this->bookingMetadataChanges( $row, $metadata ) : array();
			// A verified lifecycle response can resolve a lost booking acknowledgement.
			// The reviewed price is a private request snapshot until that confirmation.
			$snapshot = json_decode( (string) ( $row->shipping_info ?? '' ), true );
			if ( 'apply' === $mode && null !== $code && null === ( $row->instant_status_code ?? null )
				&& 1 === ( $snapshot['_kiriof_instant_prepared']['version'] ?? null ) && is_array( $snapshot['_kiriof_instant_prepared']['metadata'] ?? null ) ) {
				// Publish the private reviewed context only after authenticated lifecycle evidence.
				$changes = $this->bookingMetadataChanges( $row, $snapshot['_kiriof_instant_prepared']['metadata'] );
			}
			if ( 'apply' === $mode && null !== $code && null === ( $row->instant_status_code ?? null )
				&& is_array( $snapshot ) && isset( $snapshot['instant_shipping_cost'] ) && is_int( $snapshot['instant_shipping_cost'] ) && $snapshot['instant_shipping_cost'] >= 0 ) {
				$changes['shipping_cost'] = $snapshot['instant_shipping_cost'];
			}

			if ( ! $stale ) {
				$changes['status'] = $target;
				$changes['instant_status_code'] = $code;
				$issue = in_array( $code, array( 405, 500, 555, 701, 702, 703, 704, 303, 301, 333 ), true );
				$changes['rejected_reason'] = $issue ? 'There is a problem with this Instant shipment.' : null;
				if ( $target !== $old ) {
					$fields = array( 'request_pickup' => 'request_pickup_at', 'shipped' => 'shipped_at', 'finished' => 'finished_at', 'canceled' => 'canceled_at', 'returned' => 'return_finished_at', 'return' => 'returned_at' );
					$field = $fields[ $target ] ?? null;
					if ( null !== $field && empty( $row->{$field} ) && ! isset( $changes[ $field ] ) ) {
						$changes[ $field ] = $this->timestamp( $package[ $field ] ?? $package['date'] ?? null ) ?? $now;
					}
				}
			}
			// Immutable AWB; stale deliveries may only fill previously absent metadata.
			if ( isset( $package['awb'] ) && empty( $row->awb ) ) {
				$changes['awb'] = $package['awb'];
			}
			$url = self::trackingUrl( $package['live_tracking_url'] ?? $package['live_track_url'] ?? $package['tracking_url'] ?? $package['live_tracking'] ?? null );
			if ( '' !== $url && ( ! $stale || empty( $row->live_tracking_url ) ) ) {
				$changes['live_tracking_url'] = $url;
			}
			$changes = array_merge( $changes, $this->paymentChanges( $row, $payment ) );
			foreach ( $changes as $field => $value ) {
				if ( ( $row->{$field} ?? null ) === $value || ( null !== $value && isset( $row->{$field} ) && (string) $row->{$field} === (string) $value ) ) {
					unset( $changes[ $field ] );
				}
			}
			$changed = ! empty( $changes );
			if ( $changed ) {
				$condition = array( 'order_id' => $id, 'status' => $old, 'instant_status_code' => $row->instant_status_code ?? null, 'rejected_reason' => $row->rejected_reason ?? null );
				// Guard metadata too: parallel empty-fill/payment callbacks must not regress it.
				foreach ( array_unique( array_merge( array( 'awb', 'live_tracking_url', 'instant_payment_id', 'instant_payment_status' ), array_keys( $changes ) ) ) as $field ) {
					$condition[ $field ] = $row->{$field} ?? null;
				}
				if ( ! $this->repo->compareAndSwapInstant( $id, $condition, $changes ) ) {
					continue;
				}
				foreach ( $changes as $field => $value ) {
					$row->{$field} = $value;
				}
			}
			if ( 'payment' === $mode ) {
				return array( 'id' => $row->instant_payment_id, 'status' => $row->instant_payment_status ?? null );
			}
			if ( 'metadata' !== $mode || 'finished' === $row->status ) {
				$this->wooLifecycle( $row, $order );
			}
			return array( 'changed' => $changed, 'status' => (string) $row->status, 'tracking_url' => self::trackingUrl( $row->live_tracking_url ?? null ), 'order_id' => $id );
		}
		$this->failed();
	}

	/** Safe for both persistence and UI. Credentials/control bytes are never accepted. */
	public static function trackingUrl( $value ): string {
		if ( ! is_string( $value ) || strlen( $value ) > 2048 || preg_match( '/[\x00-\x20\x7f\\\\]/', $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'https', 'http' ), true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/** No network requests; unknown/unconfirmed bookings are never cancelable. */
	public static function canCancel( $row ): bool {
		$row = is_object( $row ) ? get_object_vars( $row ) : $row;
		return is_array( $row ) && 'instant' === TransactionDeliveryType::resolve( $row )
			&& in_array( strtolower( trim( (string) ( $row['service'] ?? '' ) ) ), array( 'gosend', 'grab_express' ), true )
			&& in_array( $row['status'] ?? null, array( 'pending', 'request_pickup' ), true )
			&& 'refunded' !== ( $row['instant_payment_status'] ?? null )
			&& in_array( $row['instant_status_code'] ?? null, array( 100, 101, 105, 110, '100', '101', '105', '110' ), true )
			&& self::identifier( $row['order_id'] ?? null ) && self::identifier( $row['instant_payment_id'] ?? null )
			&& ( null === ( $row['awb'] ?? null ) || '' === $row['awb'] || self::identifier( $row['awb'] ) );
	}

	private static function identifier( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $value );
	}

	private function validate( $row, string $id, array $package, array $payment, bool $trusted_booking = false ): void {
		if ( ! is_object( $row ) || ( $row->order_id ?? null ) !== $id || 'instant' !== TransactionDeliveryType::resolve( $row )
			|| ! in_array( strtolower( trim( (string) ( $row->service ?? '' ) ) ), array( 'gosend', 'grab_express' ), true )
			|| ! in_array( $row->status ?? null, array( 'pending', 'request_pickup', 'shipped', 'finished', 'canceled', 'return', 'returned', 'rejected' ), true ) ) {
			$this->invalid();
		}
		foreach ( array( 'service', 'service_type', 'service_name' ) as $field ) {
			$expected = (string) ( 'service' !== $field ? ( $row->service_name ?? $row->service_type ?? '' ) : ( $row->service ?? '' ) );
			if ( array_key_exists( $field, $package ) && ( 'service' === $field ? $package[ $field ] !== $expected : ! self::sameServiceType( $package[ $field ], $expected ) ) ) {
				$this->invalid();
			}
		}
		if ( isset( $package['awb'] ) && ( ! self::identifier( $package['awb'] ) || ( ! empty( $row->awb ) && $row->awb !== $package['awb'] ) ) ) {
			$this->invalid();
		}
		foreach ( array( 'id', 'payment_id' ) as $field ) {
			if ( array_key_exists( $field, $payment ) && ( ! self::identifier( $payment[ $field ] ) || ( ! empty( $row->instant_payment_id ) && $row->instant_payment_id !== $payment[ $field ] ) || ( ! $trusted_booking && empty( $row->instant_payment_id ) && ! in_array( $row->status, array( 'pending', 'request_pickup' ), true ) ) ) ) {
				$this->invalid();
			}
		}
		if ( isset( $payment['id'], $payment['payment_id'] ) && $payment['id'] !== $payment['payment_id'] ) {
			$this->invalid();
		}
	}

	/** Pure payment merge; refunded is absorbing and paid cannot become unpaid/pending. */
	private function paymentChanges( object $row, array $payment ): array {
		$changes = array();
		$id = $payment['id'] ?? $payment['payment_id'] ?? null;
		if ( null !== $id && empty( $row->instant_payment_id ) ) {
			$changes['instant_payment_id'] = $id;
		}
		$status = $this->paymentStatus( $payment['status_code'] ?? $payment['status'] ?? null );
		$previous = $row->instant_payment_status ?? '';
		if ( null !== $status && ( ! empty( $row->instant_payment_id ) || null !== $id ) && 'refunded' !== $previous && ( 'paid' !== $previous || 'refunded' === $status ) ) {
			$changes['instant_payment_status'] = $status;
		}
		return $changes;
	}

	/** Only local request snapshots, never arbitrary remote fields or PII. */
	private function bookingMetadataChanges( object $row, array $metadata ): array {
		$changes = array();
		foreach ( array( 'shipping_info', 'shipment_location_snapshot', 'vehicle', 'shipping_cost', 'instant_payment_method', 'request_pickup_at' ) as $field ) {
			if ( ! array_key_exists( $field, $metadata ) ) {
				continue;
			}
			$value = $metadata[ $field ];
			$previous = $row->{$field} ?? null;
			if ( in_array( $field, array( 'shipping_info', 'shipment_location_snapshot' ), true ) ) {
				$decoded = is_string( $value ) ? json_decode( $value, true ) : null;
				if ( ! is_string( $value ) || ! is_array( $decoded ) || '{' !== substr( ltrim( $value ), 0, 1 ) ) {
					$this->invalid();
				}
				$saved = is_string( $previous ) ? json_decode( $previous, true ) : null;
				if ( is_array( $saved ) && ! empty( $saved ) ) {
					// Checkout shipping data may be enriched once; booked item snapshots are immutable.
					if ( 'shipping_info' === $field && isset( $saved['instant_items'] ) && $saved !== $decoded ) {
						$this->invalid();
					}
					if ( 'shipment_location_snapshot' === $field ) {
						foreach ( $saved as $key => $item ) {
							if ( ! array_key_exists( $key, $decoded ) || $decoded[ $key ] !== $item ) {
								$this->invalid();
							}
						}
					}
				}
			} elseif ( 'request_pickup_at' === $field ) {
				$value = $this->timestamp( $value );
				if ( null === $value ) {
					$this->invalid();
				}
				if ( ! empty( $previous ) ) {
					continue;
				}
			} elseif ( 'shipping_cost' === $field ) {
				if ( ! is_scalar( $value ) || is_bool( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 ) {
					$this->invalid();
				}
			} elseif ( ! is_string( $value ) || ! self::identifier( $value ) ) {
				$this->invalid();
			}
			// Once the shipping snapshot is booked, monetary/method/vehicle context is immutable.
			$booked = json_decode( (string) ( $row->shipping_info ?? '' ), true );
			if ( is_array( $booked ) && isset( $booked['instant_items'] ) && ! empty( $row->request_pickup_at ) && null !== $previous && '' !== $previous && (string) $previous !== (string) $value && ! in_array( $field, array( 'shipping_info', 'shipment_location_snapshot' ), true ) ) {
				$this->invalid();
			}
			$changes[ $field ] = $value;
		}
		return $changes;
	}

	private function remoteCode( array $package, array $payment, ?string $event ): int {
		$known = array( 100, 101, 105, 106, 110, 200, 300, 302, 350, 400, 401, 402, 403, 404, 405, 500, 555, 701, 702, 703, 704, 303, 301, 333 );
		$found = null;
		foreach ( array( 'status_code', 'status', 'instant_status_code' ) as $field ) {
			if ( ! array_key_exists( $field, $package ) ) {
				continue;
			}
			$value = $package[ $field ];
			if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/\A[1-9][0-9]{2}\z/', $value ) ) ) {
				$this->invalid();
			}
			$value = (int) $value;
			if ( ! in_array( $value, $known, true ) || ( null !== $found && $found !== $value ) ) {
				$this->invalid();
			}
			$found = $value;
		}
		if ( null === $found ) {
			// Legacy authoritative lifecycle hooks have no Instant state metadata.
			foreach ( array( 'service', 'service_type', 'live_tracking_url', 'tracking_url', 'live_tracking' ) as $field ) {
				if ( array_key_exists( $field, $package ) ) {
					$this->invalid();
				}
			}
			$events = array( 'shipped_packages' => 106, 'finished_packages' => 200, 'canceled_packages' => 300 );
			if ( ! empty( $payment ) || ! isset( $events[ $event ?? '' ] ) ) {
				$this->invalid();
			}
			$found = $events[ $event ];
		}
		// Validate even when called directly, not only through webhook prevalidation.
		$events = array( 'shipped_packages' => array( 106 ), 'finished_packages' => array( 200 ), 'canceled_packages' => array( 300, 302 ) );
		if ( null !== $event && ( ! isset( $events[ $event ] ) || ! in_array( $found, $events[ $event ], true ) ) ) {
			$this->invalid();
		}
		return $found;
	}

	private function localStatus( int $code, string $old ): string {
		if ( in_array( $code, array( 100, 101, 105, 110 ), true ) ) {
			return 'request_pickup';
		}
		$map = array( 106 => 'shipped', 200 => 'finished', 300 => 'canceled', 302 => 'canceled', 400 => 'returned', 401 => 'return', 402 => 'return', 403 => 'return', 404 => 'return', 405 => 'return' );
		// 350 (cancel requested) and issue codes retain the claimed local lifecycle.
		return $map[ $code ] ?? $old;
	}

	private function paymentStatus( $value ): ?string {
		if ( 0 === $value || '0' === $value ) {
			return 'paid';
		}
		if ( 9 === $value || '9' === $value ) {
			return 'unpaid';
		}
		return is_string( $value ) && in_array( $value, array( 'paid', 'unpaid', 'pending', 'refunded' ), true ) ? $value : null;
	}

	/** Strict dates only: reject coercions, impossible dates and unbounded timestamps. */
	private function timestamp( $value ): ?string {
		if ( ! is_string( $value ) || strlen( $value ) > 35 ) {
			return null;
		}
		$formats = array( 'Y-m-d H:i:s', 'Y-m-d\TH:i:s\Z', 'Y-m-d\TH:i:s.u\Z', 'Y-m-d\TH:i:sP' );
		foreach ( $formats as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );
			$errors = \DateTimeImmutable::getLastErrors();
			if ( false !== $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) && $date->format( $format ) === $value && $date->getTimestamp() >= 946684800 && $date->getTimestamp() <= time() + 86400 ) {
				return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}
		return null;
	}

	/** Verify the linked Woo order before committing any shipment changes. */
	private function wooOrder( object $row ): object {
		$id = $row->wp_wc_order_stat_order_id ?? null;
		if ( ! ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) || (int) $id < 1 || ! function_exists( 'wc_get_order' ) ) {
			$this->failed();
		}
		try {
			$order = wc_get_order( $id );
			if ( ! is_object( $order ) ) {
				$this->failed();
			}
			return $order;
		} catch ( \Throwable $error ) {
			$this->failed();
		}
	}

	private function wooLifecycle( object $row, object $order ): void {
		try {
			if ( 'finished' === $row->status && in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) {
				// Also run on duplicate: DB may have committed before a previous Woo failure.
				$order->update_status( 'completed', esc_html__( 'Instant shipment delivered.', 'kiriminaja-official' ) );
				if ( 'completed' !== $order->get_status() ) {
					$this->failed();
				}
			} elseif ( 'canceled' === $row->status && 'canceled' !== $order->get_meta( '_kiriof_instant_lifecycle_status', true ) ) {
				// Retry failed notes on duplicate callbacks. Mark only after note success.
				// A note and metadata save cannot be atomic; a save failure may duplicate a note.
				if ( ! $order->add_order_note( esc_html__( 'Instant shipment canceled. The WooCommerce order was not canceled.', 'kiriminaja-official' ) ) ) {
					$this->failed();
				}
				$order->update_meta_data( '_kiriof_instant_lifecycle_status', 'canceled' );
				if ( ! $order->save() ) {
					$this->failed();
				}
			}
		} catch ( \Throwable $error ) {
			$this->failed();
		}
	}

	private function invalid(): void {
		throw new InvalidArgumentException( esc_html__( 'Invalid Instant shipment state or identity.', 'kiriminaja-official' ) );
	}

	private function failed(): void {
		throw new RuntimeException( esc_html__( 'Unable to verify Instant shipment state. Please retry.', 'kiriminaja-official' ), 503 );
	}
}
