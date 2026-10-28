<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TransactionPhoneIssue {
	public static function unbookedExpress( object $transaction ): bool {
		return 'express' === TransactionDeliveryType::resolve( $transaction )
			&& 'new' === (string) ( $transaction->status ?? '' )
			&& '' === trim( (string) ( $transaction->awb ?? '' ) )
			&& '' === trim( (string) ( $transaction->pickup_number ?? '' ) );
	}

	public static function message(): string {
		return __( 'The recipient phone number is invalid. Correct the shipping or billing phone in the WooCommerce order before requesting pickup.', 'kiriminaja-official' );
	}
}
