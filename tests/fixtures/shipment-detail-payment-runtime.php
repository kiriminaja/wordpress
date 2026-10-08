<?php
/** Isolated carrier provenance with no WordPress DB, API, or gateway access. */
namespace KiriminAjaOfficial\Repositories {
    class PaymentRepository {
        public function __construct() {
            if ( ! empty( $GLOBALS['payment_payload']['constructor_error'] ) ) {
                throw new \RuntimeException( 'secret constructor credentials' );
            }
        }
        public function getPaymentByPaymentId( $id ) {
            $GLOBALS['payment_lookups'][] = $id;
            if ( ! empty( $GLOBALS['payment_payload']['lookup_error'] ) ) {
                throw new \Error( 'secret API token' );
            }
            $row = $GLOBALS['payment_payload']['carrier_payment'] ?? false;
            return is_array( $row ) ? (object) $row : $row;
        }
    }
}
namespace {
    $payment_payload = json_decode( $argv[1] ?? '{}', true ) ?: [];
    $payment_lookups = [];
    if ( 'helper' === ( $payment_payload['mode'] ?? '' ) ) {
        define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
        require ABSPATH . 'inc/Services/TransactionDeliveryType.php';
        require ABSPATH . 'inc/Services/ShipmentDetailPayment.php';
        $repository = ! empty( $payment_payload['inject_repository'] ) ? new \KiriminAjaOfficial\Repositories\PaymentRepository() : null;
        $payment = \KiriminAjaOfficial\Services\ShipmentDetailPayment::forTransaction( (object) ( $payment_payload['row'] ?? [] ), $repository );
        echo json_encode( [ 'payment' => $payment, 'lookups' => $payment_lookups ], JSON_THROW_ON_ERROR );
        exit;
    }
    // Reuse the established production detail/fallback WC collaborator harness.
    $argv[1] = json_encode( array_merge( $payment_payload['row'] ?? [], [ 'mode' => $payment_payload['mode'] ?? 'detail' ], isset( $payment_payload['wc_order'] ) ? [ 'wc_order' => $payment_payload['wc_order'] ] : [] ), JSON_THROW_ON_ERROR );
    require __DIR__ . '/shipment-detail-amounts-runtime.php';
}
