<?php

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared shipment cancellation rules for Express UI and requests. */
final class ExpressCancellationEligibility {
	/**
	 * An AWB alone is not payment or pickup confirmation.
	 *
	 * @param object $transaction Persisted transaction row.
	 * @return bool
	 */
	public static function canCancel( object $transaction ): bool {
		$blocked_statuses = array( 'new', 'pending', 'unconfirmed', 'shipped', 'finished', 'returned', 'return', 'canceled' );

		return 'express' === TransactionDeliveryType::resolve( $transaction )
			&& '' !== (string) ( $transaction->awb ?? '' )
			&& ! in_array( (string) ( $transaction->status ?? 'new' ), $blocked_statuses, true );
	}
}
