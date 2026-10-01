<?php
namespace KiriminAjaOfficial\Services;

use InvalidArgumentException;
use RuntimeException;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\InstantDeliveryApiRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One-shot, user-bound Instant quotes and conservative remote booking reconciliation. */
class InstantDispatchService {
	private TransactionRepository $repo;
	private InstantDeliveryApiRepository $api;
	private InstantShipmentContext $context;
	private InstantShipmentState $state;

	public function __construct( TransactionRepository $repo, InstantDeliveryApiRepository $api, InstantShipmentContext $context, ?InstantShipmentState $state = null ) {
		$this->repo = $repo;
		$this->api = $api;
		$this->context = $context;
		$this->state = $state ?? new InstantShipmentState( $repo );
	}

	/** Compare-and-delete: an expired/replaced owner must never delete a newer lease. */
	private function deleteLease( string $key, array $lease ): bool {
		global $wpdb;
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $lease ) ) );
		if ( 1 === $deleted ) {
			wp_cache_delete( $key, 'options' );
			return true;
		}
		return false;
	}

	public function quote( array $ids ): array {
		$rows = $this->selection( $ids );
		$methods = $this->paymentMethods();
		$reports = array();
		$contexts = array();
		foreach ( $rows as $id => $row ) {
			$report = array( 'id' => $id, 'before' => null, 'after' => null, 'changed' => false, 'eligible' => false, 'error' => '' );
			$order_number = $this->orderNumber( $row );
			if ( '' !== $order_number ) {
				$report['wc_order_number'] = $order_number;
			}
			try {
				// Database decimals commonly arrive as strings; return a numeric JSON price.
				$report['before'] = $this->integer( $row->shipping_cost ?? null );
				try {
					$ctx = $this->context->build( $row );
				} catch ( InvalidArgumentException $error ) {
					// Context validation uses fixed translated messages, never remote errors.
					$report['error'] = $error->getMessage();
					$reports[] = $report;
					continue;
				}
				$price = $this->price( $this->api->price( $ctx['pricing'] ), $ctx['package'] );
				// Eligible presentation is an explicit allowlist, never the context or API response.
				$report['courier'] = sanitize_text_field( $ctx['package']['service'] );
				$report['service'] = sanitize_text_field( $ctx['package']['service_type'] );
				$origin_label = sanitize_text_field( $ctx['origin']['name'] ?? '' );
				if ( '' === $origin_label ) {
					$origin_label = sanitize_text_field( $ctx['origin']['address'] ?? '' );
				}
				$destination_label = sanitize_text_field( $ctx['package']['destination']['name'] ?? '' );
				if ( '' !== $origin_label ) {
					$report['origin_label'] = $origin_label;
				}
				if ( '' !== $destination_label ) {
					$report['destination_label'] = $destination_label;
				}
				$ctx['price'] = $price;
				$contexts[ $id ] = $ctx;
				$report['after'] = $price;
				$report['changed'] = (int) $report['before'] !== $price;
				$report['eligible'] = true;
			} catch ( \Throwable $error ) {
				// Never echo an API exception: upstream errors may contain credentials.
				$report['error'] = __( 'This Instant shipment could not be quoted. Check its addresses, items and courier service.', 'kiriminaja-official' );
			}
			$reports[] = $report;
		}
		$token = bin2hex( random_bytes( 16 ) );
		$expires = time() + 120;
		if ( ! set_transient( $this->quoteKey( $token ), array( 'user' => get_current_user_id(), 'expires' => $expires, 'contexts' => $contexts, 'methods' => $methods ), 120 ) ) {
			throw new RuntimeException( esc_html__( 'Unable to save the Instant quote.', 'kiriminaja-official' ) );
		}
		return array( 'token' => $token, 'expires_at' => $expires, 'rows' => $reports, 'payment_methods' => $methods, 'batch_count' => count( $this->groups( $contexts ) ) );
	}

	public function dispatch( string $token, array $ids, string $method, string $pin = '' ): array {
		if ( ! preg_match( '/\A[a-f0-9]{32}\z/', $token ) ) {
			throw new InvalidArgumentException( esc_html__( 'The Instant quote is invalid or expired.', 'kiriminaja-official' ) );
		}
		$key = $this->quoteKey( $token );
		$quote = get_transient( $key );
		if ( ! is_array( $quote ) || $quote['user'] !== get_current_user_id() || $quote['expires'] <= time() ) {
			throw new InvalidArgumentException( esc_html__( 'The Instant quote is invalid or expired.', 'kiriminaja-official' ) );
		}
		$rows = $this->selection( $ids );
		foreach ( $rows as $id => $row ) {
			if ( ! isset( $quote['contexts'][ $id ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'Select only eligible shipments from this quote.', 'kiriminaja-official' ) );
			}
		}
		$locks = array();
		$claims = array();
		$processed = false;
		try {
			foreach ( $rows as $id => $row ) {
				$lock = 'kiriof_instant_dispatch_' . hash( 'sha256', $id );
				$lease = array( 'owner' => bin2hex( random_bytes( 16 ) ), 'expires' => time() + 300 );
				$acquired = add_option( $lock, $lease, '', false );
				if ( ! $acquired ) {
					$previous = get_option( $lock );
					// Legacy/invalid locks fail closed. Only a known expired lease is recoverable.
					if ( is_array( $previous ) && isset( $previous['owner'], $previous['expires'] ) && is_string( $previous['owner'] ) && is_int( $previous['expires'] ) && $previous['expires'] <= time() && $this->deleteLease( $lock, $previous ) ) {
						$acquired = add_option( $lock, $lease, '', false );
					}
				}
				if ( ! $acquired ) {
					throw new RuntimeException( esc_html__( 'An Instant shipment is already being processed.', 'kiriminaja-official' ) );
				}
				$locks[ $lock ] = $lease;
			}
			// Reload after acquiring locks, then validate the complete batch before any claim.
			$rows = $this->selection( $ids );
			$contexts = array();
			$total = 0;
			foreach ( $rows as $id => $row ) {
				$ctx = $this->context->build( $row );
				if ( ! hash_equals( $quote['contexts'][ $id ]['fingerprint'], $ctx['fingerprint'] ) ) {
					throw new RuntimeException( esc_html__( 'The shipment has changed. Request a new Instant quote.', 'kiriminaja-official' ) );
				}
				$ctx['price'] = $quote['contexts'][ $id ]['price'];
				$ctx['package']['shipping_cost'] = $ctx['price'];
				$contexts[ $id ] = $ctx;
				if ( $total > PHP_INT_MAX - $ctx['price'] ) {
					throw new RuntimeException( esc_html__( 'The Instant total is invalid.', 'kiriminaja-official' ) );
				}
				$total += $ctx['price'];
			}
			$methods = $this->paymentMethods();
			if ( $methods !== $quote['methods'] || ! in_array( $method, $methods, true ) ) {
				throw new InvalidArgumentException( esc_html__( 'The selected Instant payment method is unavailable. Request a new quote.', 'kiriminaja-official' ) );
			}
			if ( 'credit' === $method ) {
				if ( ! preg_match( '/\A[0-9]{6}\z/', $pin ) || true !== ( $this->api->validateCredit( $pin, $total )['status'] ?? false ) ) {
					throw new RuntimeException( esc_html__( 'Unable to validate KA Credit payment.', 'kiriminaja-official' ) );
				}
			}
			foreach ( $rows as $id => $row ) {
				if ( ! $this->repo->claimInstantDispatch( (string) $id ) ) {
					throw new RuntimeException( esc_html__( 'An Instant shipment is already being processed.', 'kiriminaja-official' ) );
				}
				$claims[] = (string) $id;
			}
			// Persist the reviewed request before any outbound booking. An ambiguous
			// response must remain recoverable without reading mutable WooCommerce data.
			$prepared = array();
			foreach ( $contexts as $id => $ctx ) {
				try {
					$current = $this->repo->getTransactionByOrderId( (string) $id );
					if ( ! is_object( $current ) || 'pending' !== $current->status || null !== ( $current->instant_status_code ?? null ) || ! in_array( $current->instant_payment_id ?? null, array( null, '' ), true ) ) {
						throw new RuntimeException();
					}
					$metadata = $this->bookingMetadata( $current, $ctx, $method );
					$expected = array( 'order_id' => (string) $id, 'status' => 'pending', 'instant_status_code' => null, 'instant_payment_id' => $current->instant_payment_id ?? null, 'awb' => $current->awb ?? null );
					foreach ( array_keys( $metadata ) as $field ) {
						$expected[ $field ] = $current->{$field} ?? null;
					}
					if ( ! $this->repo->compareAndSwapInstant( (string) $id, $expected, $metadata ) ) {
						throw new RuntimeException();
					}
					$prepared[ $id ] = $metadata;
				} catch ( \Throwable $error ) {
					throw new RuntimeException( esc_html__( 'Unable to save the prepared Instant shipment.', 'kiriminaja-official' ) );
				}
			}
			if ( $quote['expires'] <= time() || ! delete_transient( $key ) ) {
				throw new RuntimeException( esc_html__( 'The Instant quote is invalid or expired.', 'kiriminaja-official' ) );
			}
			$processed = true;
			$result = array( 'rows' => array(), 'payments' => array() );
			foreach ( $this->groups( $contexts ) as $group ) {
				$first = reset( $group );
				$origin = $first['origin'];
				$payload = $origin + array( 'packages' => array_values( array_column( $group, 'package' ) ) );
				// TOP is account-configured: omit the API method, never infer paid.
				// UI QRIS is merchant payment (not COD), represented by API cash.
				if ( 'top' !== $method ) {
					$payload['payment_method'] = 'qris' === $method ? 'cash' : 'credit';
				}
				if ( 'credit' === $method ) {
					$payload['pin'] = $pin;
				}
				try {
					$response = $this->api->book( $payload );
					$data = true === ( $response['status'] ?? false ) ? $this->result( $response ) : array();
				} catch ( \Throwable $error ) {
					$data = array();
				}
				$payment = $this->paymentData( $data['payment'] ?? array() );
				// Credit never needs a QR; do not expose a credential echoed in that field.
				if ( 'credit' === $method ) {
					$payment['qr_content'] = '';
				}
				$payment['order_ids'] = array();
				$payment_statuses = array();
				$matches = array();
				foreach ( (array) ( $data['packages'] ?? array() ) as $package ) {
					$package = (array) $package;
					$order_id = $package['order_id'] ?? null;
					if ( is_string( $order_id ) && isset( $group[ $order_id ] ) ) {
						$matches[ $order_id ][] = $package;
					}
				}
				foreach ( $group as $id => $ctx ) {
					$report = array( 'id' => $id, 'status' => 'unknown', 'awb' => '', 'message' => __( 'Check remote state before retrying', 'kiriminaja-official' ) );
					$matched = $matches[ $id ] ?? array();
					if ( 1 === count( $matched ) && '' !== $payment['id'] ) {
						try {
							$metadata = $this->bookingChanges( $ctx, $matched[0], $prepared[ $id ] );
							$effective = $this->state->confirmBooking( (string) $id, $matched[0], $payment, $metadata );
							$saved = $this->repo->getTransactionByOrderId( (string) $id );
							$awb = is_object( $saved ) ? ( $saved->awb ?? '' ) : ( $matched[0]['awb'] ?? '' );
							$report['status'] = 'booked';
							$report['shipment_status'] = $effective['status'];
							$report['awb'] = is_string( $awb ) && $this->safeId( $awb ) ? $awb : '';
							$report['message'] = __( 'Instant shipment booked.', 'kiriminaja-official' );
							$payment['order_ids'][] = $id;
							$payment_statuses[] = is_object( $saved ) ? ( $saved->instant_payment_status ?? null ) : $payment['status'];
						} catch ( \Throwable $error ) {
							// Accepted remotely but unverified locally: keep the durable claim.
						}
					}
					if ( 'unknown' === $report['status'] ) {
						try {
							// Persist only a fixed reconciliation note; never release the claim or attach payment.
							$current = $this->repo->getTransactionByOrderId( (string) $id );
							if ( is_object( $current ) && 'pending' === $current->status && empty( $current->instant_status_code ) && empty( $current->rejected_reason ) ) {
								$this->repo->compareAndSwapInstant( (string) $id, array( 'order_id' => $id, 'status' => 'pending', 'instant_status_code' => $current->instant_status_code ?? null, 'rejected_reason' => $current->rejected_reason ?? null ), array( 'rejected_reason' => 'Check remote state before retrying' ) );
							}
						} catch ( \Throwable $error ) {
							// A failed issue write must not turn an ambiguous booking into a success.
						}
					}
					$result['rows'][] = $report;
				}
				if ( ! empty( $payment['order_ids'] ) ) {
					$payment['status'] = $this->effectivePaymentStatus( $payment_statuses );
					$result['payments'][] = $payment;
				}
			}
			return $result;
		} finally {
			if ( ! $processed ) {
				foreach ( $claims as $id ) {
					$this->repo->releaseInstantDispatch( $id );
				}
			}
			foreach ( $locks as $lock => $lease ) {
				$this->deleteLease( $lock, $lease );
			}
		}
	}

	public function refreshPayment( array $ids, string $payment_id ): array {
		$rows = $this->selection( $ids );
		if ( ! $this->safeId( $payment_id ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid Instant payment ID.', 'kiriminaja-official' ) );
		}
		foreach ( $rows as $row ) {
			if ( $payment_id !== ( $row->instant_payment_id ?? '' ) ) {
				throw new InvalidArgumentException( esc_html__( 'The payment does not belong to every selected Instant shipment.', 'kiriminaja-official' ) );
			}
		}
		$response = $this->api->payment( $payment_id );
		if ( true !== ( $response['status'] ?? false ) ) {
			throw new RuntimeException( esc_html__( 'Unable to refresh the Instant payment.', 'kiriminaja-official' ) );
		}
		$data = $this->result( $response );
		$payment = $this->paymentData( $data['payment'] ?? $data );
		if ( '' !== $payment['id'] && $payment_id !== $payment['id'] ) {
			throw new RuntimeException( esc_html__( 'The Instant payment response is invalid.', 'kiriminaja-official' ) );
		}
		$payment['id'] = $payment_id;
		// Unknown remote values must not invent pending or overwrite persisted payment state.
		$raw = (array) ( $data['payment'] ?? $data );
		$known = $this->paymentStatus( $raw['status_code'] ?? $raw['status'] ?? null );
		$statuses = array();
		foreach ( $rows as $id => $row ) {
			$merge = array( 'id' => $payment_id );
			if ( null !== $known ) {
				$merge['status'] = $known;
			}
			$effective = $this->state->mergePayment( (string) $id, $merge );
			$statuses[] = $effective['status'];
		}
		$payment['status'] = $this->effectivePaymentStatus( $statuses );
		return $payment;
	}

	private function selection( array $ids ): array {
		if ( count( $ids ) < 1 || count( $ids ) > 50 ) {
			throw new InvalidArgumentException( esc_html__( 'Select between 1 and 50 Instant shipments.', 'kiriminaja-official' ) );
		}
		$normalized = array();
		foreach ( $ids as $id ) {
			if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! $this->safeId( (string) $id ) || isset( $normalized[ (string) $id ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'Invalid or duplicate Instant shipment ID.', 'kiriminaja-official' ) );
			}
			$normalized[ (string) $id ] = true;
		}
		$found = $this->repo->getTransactionByOrderIds( array_keys( $normalized ) );
		if ( ! is_array( $found ) || count( $found ) !== count( $normalized ) ) {
			throw new InvalidArgumentException( esc_html__( 'Every selected Instant shipment must exist exactly once.', 'kiriminaja-official' ) );
		}
		$rows = array();
		foreach ( $found as $row ) {
			$id = is_object( $row ) ? (string) ( $row->order_id ?? '' ) : '';
			if ( ! isset( $normalized[ $id ] ) || isset( $rows[ $id ] ) || 'instant' !== TransactionDeliveryType::resolve( $row ) || ! in_array( strtolower( (string) $row->service ), array( 'gosend', 'grab_express' ), true ) ) {
				throw new InvalidArgumentException( esc_html__( 'Select only supported Instant shipments.', 'kiriminaja-official' ) );
			}
			$rows[ $id ] = $row;
		}
		return array_replace( $normalized, $rows );
	}

	/** Merchant-facing identifier only; never replace the canonical transaction ID. */
	private function orderNumber( object $row ): string {
		$id = $row->wp_wc_order_stat_order_id ?? null;
		if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $id ) ) {
			return '';
		}
		try {
			$order = wc_get_order( $id );
			if ( is_object( $order ) && method_exists( $order, 'get_order_number' ) ) {
				$number = $order->get_order_number();
				if ( is_string( $number ) || is_int( $number ) ) {
					$number = sanitize_text_field( (string) $number );
					if ( '' !== $number ) {
						return $number;
					}
				}
			}
		} catch ( \Throwable $error ) {
			// Optional display lookup must not invalidate a quote or expose errors.
		}
		return (string) $id;
	}

	private function paymentMethods(): array {
		try {
			$profile = $this->api->profile();
			$data = (array) ( $profile['data']->results ?? array() );
			$metadata = (array) ( $data['metadata'] ?? array() );
			$method = $metadata['payment_method'] ?? null;
			if ( true !== ( $profile['status'] ?? false ) || ! is_string( $method ) || '' === trim( $method ) ) {
				throw new RuntimeException();
			}
			return 'TOP' === strtoupper( trim( $method ) ) ? array( 'top' ) : array( 'qris', 'credit' );
		} catch ( \Throwable $error ) {
			throw new RuntimeException( esc_html__( 'Unable to verify Instant account payment methods.', 'kiriminaja-official' ) );
		}
	}

	private function price( array $response, array $package ): int {
		if ( true !== ( $response['status'] ?? false ) ) {
			throw new RuntimeException();
		}
		$matches = array();
		foreach ( $this->result( $response ) as $courier ) {
			$courier = (array) $courier;
			if ( ( $courier['name'] ?? null ) !== $package['service'] ) {
				continue;
			}
			foreach ( (array) ( $courier['costs'] ?? array() ) as $cost ) {
				$cost = (array) $cost;
				if ( ( $cost['service_type'] ?? null ) === $package['service_type'] ) {
					$price = (array) ( $cost['price'] ?? array() );
					$matches[] = $this->integer( $price['shipping_costs'] ?? null );
				}
			}
		}
		if ( 1 !== count( $matches ) ) {
			throw new RuntimeException();
		}
		return $matches[0];
	}

	private function result( array $response ): array {
		$data = (array) ( $response['data'] ?? array() );
		$result = (array) ( $data['result'] ?? $data['results'] ?? array() );
		// The SDK already extracts result; tolerate one extra result envelope.
		return (array) ( $result['result'] ?? $result['results'] ?? $result );
	}

	private function groups( array $contexts ): array {
		$buckets = array();
		foreach ( $contexts as $id => $ctx ) {
			$origin = $ctx['origin'];
			ksort( $origin );
			$key = hash( 'sha256', wp_json_encode( array( $origin, $ctx['package']['service'], $ctx['package']['vehicle'] ) ) );
			$buckets[ $key ][ $id ] = $ctx;
		}
		$groups = array();
		foreach ( $buckets as $bucket ) {
			foreach ( array_chunk( $bucket, 10, true ) as $chunk ) {
				$groups[] = $chunk;
			}
		}
		return $groups;
	}

	private function bookingChanges( array $ctx, array $remote, array $metadata ): array {
		// Match the complete courier identity to the quoted request, not merely position/ID.
		foreach ( array( 'order_id', 'service', 'service_type' ) as $field ) {
			if ( ! isset( $remote[ $field ] ) || $remote[ $field ] !== $ctx['package'][ $field ] ) {
				throw new RuntimeException();
			}
		}
		return $metadata + array( 'request_pickup_at' => gmdate( 'Y-m-d H:i:s' ) );
	}

	/** Trusted local request metadata only: no acceptance timestamp or credentials. */
	private function bookingMetadata( object $row, array $ctx, string $method ): array {
		$destination = $ctx['package']['destination'];
		$snapshot = json_decode( (string) ( $row->shipping_info ?? '{}' ), true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();
		foreach ( array( 'first_name' => $destination['name'], 'last_name' => '', 'address_1' => $destination['address'], 'address_2' => '', 'phone' => $destination['phone'], 'country' => 'ID' ) as $field => $value ) {
			$snapshot[ '_shipping_' . $field ] = $value;
		}
		$snapshot['destination_latitude'] = $destination['latitude'];
		$snapshot['destination_longitude'] = $destination['longitude'];
		$snapshot['instant_items'] = $ctx['package']['items'];
		$origin = $ctx['origin'];
		$origin['timezone'] = $ctx['pricing']['timezone'];
		$changes = array( 'vehicle' => $ctx['package']['vehicle'], 'shipping_cost' => $ctx['price'], 'instant_payment_method' => $method, 'shipping_info' => wp_json_encode( $snapshot ), 'shipment_location_snapshot' => wp_json_encode( $origin ) );
		return $changes;
	}

	private function paymentData( $raw ): array {
		$raw = (array) $raw;
		$id = $raw['id'] ?? $raw['payment_id'] ?? '';
		$qr = $raw['qr_content'] ?? $raw['qris_content'] ?? $raw['qr_string'] ?? '';
		$amount = null;
		try {
			$amount = $this->integer( $raw['amount'] ?? $raw['total_amount'] ?? null );
		} catch ( \Throwable $error ) {
			// Optional display amount must never invalidate a matched booking.
		}
		return array( 'id' => is_string( $id ) && $this->safeId( $id ) ? $id : '', 'status' => $this->paymentStatus( $raw['status_code'] ?? $raw['status'] ?? null ) ?? 'pending', 'amount' => $amount, 'qr_content' => is_string( $qr ) && strlen( $qr ) <= 8192 && ! preg_match( '/[\x00-\x1f\x7f]/', $qr ) ? $qr : '' );
	}

	/** One payment can cover multiple rows; terminal refund/paid observations win. */
	private function effectivePaymentStatus( array $statuses ): string {
		foreach ( array( 'refunded', 'paid', 'unpaid', 'pending' ) as $status ) {
			if ( in_array( $status, $statuses, true ) ) {
				return $status;
			}
		}
		return 'pending';
	}

	private function paymentStatus( $value ): ?string {
		if ( 0 === $value || '0' === $value ) {
			return 'paid';
		}
		if ( 9 === $value || '9' === $value ) {
			return 'unpaid';
		}
		return is_string( $value ) && in_array( strtolower( $value ), array( 'paid', 'unpaid', 'pending', 'refunded' ), true ) ? strtolower( $value ) : null;
	}

	private function integer( $value ): int {
		if ( is_bool( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || (float) $value >= PHP_INT_MAX || floor( (float) $value ) !== (float) $value ) {
			throw new RuntimeException();
		}
		return (int) $value;
	}

	private function safeId( string $id ): bool {
		return 1 === preg_match( '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,99}\z/', $id );
	}

	private function quoteKey( string $token ): string {
		return 'kiriof_instant_quote_' . $token;
	}
}
