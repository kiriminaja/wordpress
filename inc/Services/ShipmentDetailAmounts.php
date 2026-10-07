<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read-only amounts with explicit checkout provenance, never order-total arithmetic. */
class ShipmentDetailAmounts {
	/** Matches InstantCheckoutController::ADMIN_FEE_META_KEY and the durable shipping_info key. */
	private const ADMIN_FEE_META_KEY = '_kiriof_instant_admin_fee';
	/** Matches InstantCheckoutController::FEE_TYPE. */
	private const ADMIN_FEE_TYPE = 'instant_admin_fee';

	/**
	 * Native fee totals are authoritative, including zero or a removed fee.
	 * A persisted order amount is only useful when the native fee collection is
	 * unavailable. Transaction snapshots are only used for an absent Woo order.
	 *
	 * @param object|false|null $order WooCommerce order, if still available.
	 * @param object           $transaction Durable transaction row.
	 * @return float Nonnegative admin charge, excluding tax.
	 */
	public static function adminFee( $order, object $transaction ): float {
		if ( ! $order ) {
			return self::snapshot_admin_fee( $transaction );
		}

		$persisted = null;
		if ( method_exists( $order, 'get_meta' ) ) {
			try {
				$persisted = self::amount( $order->get_meta( self::ADMIN_FEE_META_KEY, true ) );
			} catch ( \Throwable $error ) {
				// Unavailable metadata cannot supply legacy provenance.
			}
		}

		if ( method_exists( $order, 'get_items' ) ) {
			try {
				$fees = $order->get_items( 'fee' );
				if ( is_array( $fees ) || $fees instanceof \Traversable ) {
					$tagged = 0.0;
					$legacy = 0.0;
					$has_tagged = false;
					foreach ( $fees as $fee ) {
						if ( ! is_object( $fee ) || ! method_exists( $fee, 'get_total' ) ) {
							continue;
						}
						$type = method_exists( $fee, 'get_meta' ) ? (string) $fee->get_meta( '_kiriof_fee_type', true ) : '';
						$amount = self::amount( $fee->get_total() ) ?? 0.0;
						if ( self::ADMIN_FEE_TYPE === $type ) {
							$has_tagged = true;
							$tagged += $amount;
						} elseif ( '' === $type && null !== $persisted && method_exists( $fee, 'get_name' ) && in_array( $fee->get_name(), array( 'Admin Fee', __( 'Admin Fee', 'kiriminaja-official' ) ), true ) ) {
							// Exact historical label additionally needs plugin order provenance.
							$legacy += $amount;
						}
					}
					return $has_tagged ? $tagged : $legacy;
				}
			} catch ( \Throwable $error ) {
				// Native collection unavailable: only persisted order metadata is safe.
			}
		}

		return $persisted ?? 0.0;
	}

	/** Read only the shipping_info field actually persisted by Instant checkout. */
	private static function snapshot_admin_fee( object $transaction ): float {
		$shipping_info = $transaction->shipping_info ?? '';
		if ( is_string( $shipping_info ) ) {
			$shipping_info = json_decode( $shipping_info, true );
		} elseif ( is_object( $shipping_info ) ) {
			$shipping_info = (array) $shipping_info;
		}
		return is_array( $shipping_info ) ? ( self::amount( $shipping_info[ self::ADMIN_FEE_META_KEY ] ?? null ) ?? 0.0 ) : 0.0;
	}

	/** Reject invalid/non-finite values instead of inventing an amount. */
	private static function amount( $value ): ?float {
		if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
			return null;
		}
		return max( 0.0, (float) $value );
	}
}
