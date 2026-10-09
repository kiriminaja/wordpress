<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\PaymentRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Durable merchant-to-carrier payment provenance, independent of WooCommerce. */
final class ShipmentDetailPayment {
    /**
     * Instant evidence belongs to the shipment; Express evidence belongs to its
     * matching persisted pickup/payment group. Never infer it from a WC gateway,
     * order payment status, pickup identifier alone, or a remote API response.
     *
     * @param object $transaction Persisted transaction row.
     * @param PaymentRepository|null $payment_repository Optional test collaborator.
     * @return array{id:string,status:string,method:string}
     */
    public static function forTransaction( object $transaction, ?PaymentRepository $payment_repository = null ): array {
        $empty = array( 'id' => '', 'status' => '', 'method' => '' );

        if ( 'instant' === TransactionDeliveryType::resolve( $transaction ) ) {
            return array(
                'id' => (string) ( $transaction->instant_payment_id ?? '' ),
                'status' => (string) ( $transaction->instant_payment_status ?? '' ),
                'method' => (string) ( $transaction->instant_payment_method ?? '' ),
            );
        }

        $pickup_number = (string) ( $transaction->pickup_number ?? '' );
        if ( '' === trim( $pickup_number ) || '-' === trim( $pickup_number ) ) {
            return $empty;
        }

        try {
            $payment_repository = $payment_repository ?? new PaymentRepository();
            $payment = $payment_repository->getPaymentByPaymentId( $pickup_number );
            if ( ! is_object( $payment ) || (string) ( $payment->pickup_number ?? '' ) !== $pickup_number ) {
                return $empty;
            }

            return array(
                'id' => (string) $payment->pickup_number,
                'status' => (string) ( $payment->status ?? '' ),
                'method' => (string) ( $payment->method ?? '' ),
            );
        } catch ( \Throwable $error ) {
            // Optional payment enrichment must not break the detail workspace.
            // Do not publish/log exception messages that may contain credentials.
            return $empty;
        }
    }
}
