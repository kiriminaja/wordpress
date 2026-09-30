<?php
/**
 * Pure Instant status presentation shared by transaction screens.
 *
 * @package KiriminAjaOfficial
 */

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maps remote Instant states without borrowing payment status from the local enum. */
final class InstantDeliveryStatus {
	/**
	 * Describe an Instant transaction without database access or input mutation.
	 *
	 * A missing/null remote code uses the legacy local status. A present but
	 * malformed/unsupported code never falls back to a potentially successful
	 * local status. An unpaid remote payment overrides the code tone, as in
	 * Shopify. Its caution tone is normalized to warning. Unknown combinations
	 * never receive a success tone, even for code 200.
	 *
	 * Issues identify problems/rejections or invalid destination coordinates.
	 * Cancellation alone is not an issue. Return rejection (405) is an issue,
	 * unlike an ordinary return. Driver replacement is automatic, not an action.
	 * All output is fixed, translated text; callers still escape for their context.
	 *
	 * @param array|object|null $row Persisted Instant transaction.
	 * @return array{key:string,label:string,tone:string,tooltip:string,issue:string}
	 */
	public static function describe( $row ): array {
		if ( is_object( $row ) ) {
			$row = get_object_vars( $row );
		}
		$row       = is_array( $row ) ? $row : array();
		$raw_code  = $row['instant_status_code'] ?? null;
		$code      = self::normalize_code( $raw_code );
		$payment   = $row['instant_payment_status'] ?? null;
		$payment   = is_string( $payment ) && in_array( $payment, array( 'pending', 'unpaid', 'paid', 'refunded' ), true ) ? $payment : '';
		$awb       = $row['awb'] ?? null;
		$has_awb   = is_string( $awb ) && '' !== trim( $awb );
		$key       = null === $raw_code ? self::local_key( $row['status'] ?? null, $has_awb ) : self::remote_key( $code, $payment, $has_awb );
		$result    = self::presentation( $key );

		if ( null !== $raw_code ) {
			$result['tone'] = self::remote_tone( $code, $payment, $key );
		}

		if ( '100' === $code && 'pending' === $payment && ! self::has_destination_coordinates( $row ) ) {
			$result            = self::presentation( 'need_confirmation' );
			$result['tooltip'] = __( 'Confirm the delivery location with the buyer and update the buyer address with valid destination coordinates before proceeding.', 'kiriminaja-official' );
			$result['issue']   = __( 'The delivery destination coordinates are missing or invalid.', 'kiriminaja-official' );
		}

		if ( 'find_new_driver' === $key ) {
			$result['tooltip'] = __( 'The system is automatically finding a new driver for this order. No manual action is required.', 'kiriminaja-official' );
		}
		if ( 'shipment_problem' === $key ) {
			$result['issue'] = __( 'There is a problem with this shipment.', 'kiriminaja-official' );
		} elseif ( '405' === $code ) {
			$result['issue'] = __( 'The shipment return was rejected.', 'kiriminaja-official' );
		}

		return $result;
	}

	/** Accept integer codes and their database string representations, not coercions. */
	private static function normalize_code( $value ): ?string {
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		if ( is_string( $value ) && preg_match( '/^(?:0|[1-9][0-9]*)$/D', $value ) ) {
			return $value;
		}
		return null;
	}

	/** Map only combinations explicitly supported by the remote source. */
	private static function remote_key( ?string $code, string $payment, bool $has_awb ): string {
		$nested = array(
			'100' => array(
				'refunded' => 'cancel',
				'pending'  => 'waiting_for_shipment',
				'unpaid'   => 'waiting_for_payment',
				'paid'     => $has_awb ? 'ready_delivered' : 'waiting_for_awb_generation',
			),
			'105' => array( 'paid' => 'ready_delivered' ),
			'110' => array( 'paid' => 'ready_delivered', 'unpaid' => 'waiting_for_payment' ),
			'106' => array( 'paid' => 'on_delivery' ),
			'200' => array( 'paid' => 'finish' ),
		);
		$direct = array(
			'101' => 'find_new_driver',
			'300' => 'cancel',
			'302' => 'cancel',
			'350' => 'cancel_requested',
			'401' => 'retur',
			'402' => 'retur',
			'403' => 'retur',
			'404' => 'retur',
			'405' => 'retur',
			'400' => 'finish_retur',
			'701' => 'shipment_problem',
			'702' => 'shipment_problem',
			'303' => 'shipment_problem',
			'500' => 'shipment_problem',
			'555' => 'shipment_problem',
			'301' => 'shipment_problem',
			'333' => 'shipment_problem',
		);
		return $nested[ $code ?? '' ][ $payment ] ?? $direct[ $code ?? '' ] ?? 'unknown';
	}

	/** Local status is a shipment enum, never a remote payment status. */
	private static function local_key( $status, bool $has_awb ): string {
		if ( ! is_string( $status ) ) {
			return 'unknown';
		}
		$map = array(
			'new'            => 'waiting_for_shipment',
			'request_pickup' => $has_awb ? 'ready_delivered' : 'waiting_for_awb_generation',
			'pending'        => 'waiting_for_payment',
			'shipped'        => 'on_delivery',
			'finished'       => 'finish',
			'return'         => 'retur',
			'returned'       => 'finish_retur',
			'rejected'       => 'shipment_problem',
			'canceled'       => 'cancel',
		);
		return $map[ $status ] ?? 'unknown';
	}

	/** Translate source tones to the shared UI's supported tone vocabulary. */
	private static function remote_tone( ?string $code, string $payment, string $key ): string {
		if ( 'unpaid' === $payment ) {
			return 'warning';
		}
		if ( 'unknown' === $key ) {
			return null === $code ? 'critical' : 'info';
		}
		if ( '200' === $code ) {
			return 'success';
		}
		if ( '105' === $code || in_array( $code, array( '400', '401', '402', '403', '404', '405' ), true ) ) {
			return 'warning';
		}
		if ( in_array( $code, array( '300', '302', '350', '555', '701', '702', '333' ), true ) ) {
			return 'critical';
		}
		return 'info';
	}

	/** Null, empty, malformed, nonfinite and out-of-range coordinates are invalid; zero is valid. */
	private static function has_destination_coordinates( array $row ): bool {
		return self::valid_coordinate( $row['destination_latitude'] ?? null, 90 )
			&& self::valid_coordinate( $row['destination_longitude'] ?? null, 180 );
	}

	/** Validate numeric database strings as well as native numeric values. */
	private static function valid_coordinate( $value, int $limit ): bool {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
			return false;
		}
		$value = (float) $value;
		return is_finite( $value ) && $value >= -$limit && $value <= $limit;
	}

	/**
	 * All translations use literal strings for extraction into PO catalogs.
	 *
	 * @return array{key:string,label:string,tone:string,tooltip:string,issue:string}
	 */
	private static function presentation( string $key ): array {
		$labels = array(
			'waiting_for_shipment'       => __( 'Waiting for Shipment', 'kiriminaja-official' ),
			'waiting_for_payment'        => __( 'Waiting for Payment', 'kiriminaja-official' ),
			'waiting_for_awb_generation' => __( 'Waiting for Airwaybill', 'kiriminaja-official' ),
			'ready_delivered'           => __( 'Ready for Shipment', 'kiriminaja-official' ),
			'find_new_driver'           => __( 'Find New Driver', 'kiriminaja-official' ),
			'on_delivery'               => __( 'On Delivery', 'kiriminaja-official' ),
			'shipment_problem'          => __( 'Shipment Problem', 'kiriminaja-official' ),
			'finish'                    => __( 'Delivered', 'kiriminaja-official' ),
			'retur'                     => __( 'Return Process', 'kiriminaja-official' ),
			'finish_retur'              => __( 'Return Finished', 'kiriminaja-official' ),
			'cancel_requested'          => __( 'Cancellation Process', 'kiriminaja-official' ),
			'cancel'                    => __( 'Cancelled', 'kiriminaja-official' ),
			'need_confirmation'         => __( 'Need Confirmation', 'kiriminaja-official' ),
			'unknown'                   => __( 'Unknown', 'kiriminaja-official' ),
		);
		$tones = array(
			'finish'             => 'success',
			'waiting_for_payment' => 'warning',
			'retur'              => 'warning',
			'finish_retur'       => 'warning',
			'cancel'             => 'critical',
			'cancel_requested'   => 'critical',
			'shipment_problem'   => 'critical',
			'need_confirmation'  => 'critical',
		);
		return array(
			'key'     => $key,
			'label'   => $labels[ $key ],
			'tone'    => $tones[ $key ] ?? 'info',
			'tooltip' => '',
			'issue'   => '',
		);
	}
}
