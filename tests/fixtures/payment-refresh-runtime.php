<?php

// This file is executed directly, never loaded into the PHPUnit worker.
namespace KiriminAjaOfficial\Repositories {
    final class KiriminajaApiRepository {
        public function getPayment( $request ) {
            $GLOBALS['paymentRefresh']['apiRequests'][] = $request;
            return array(
                'status' => true,
                'data' => (object) array( 'data' => (object) $GLOBALS['paymentRefresh']['remotePayment'] ),
            );
        }
    }

    final class PaymentRepository {
        public function getPaymentByPaymentId( $pickupNumber ) {
            if ( $pickupNumber !== $GLOBALS['paymentRefresh']['payment']->pickup_number ) {
                throw new \RuntimeException( 'Unexpected payment lookup' );
            }
            return clone $GLOBALS['paymentRefresh']['payment'];
        }

        public function updatePaymentByCallback( $payload ) {
            if ( $payload['condition'] !== array( 'pickup_number' => 'PICKUP-TEST' ) ) {
                throw new \RuntimeException( 'Unexpected payment write condition' );
            }
            $GLOBALS['paymentRefresh']['paymentWrites'][] = $payload;
            foreach ( $payload['changes'] as $field => $value ) {
                $GLOBALS['paymentRefresh']['payment']->{$field} = $value;
            }
            return true;
        }
    }

    final class TransactionRepository {
        public function getTransactionByPickupNumber( $pickupNumber ) {
            $GLOBALS['paymentRefresh']['transactionReads'][] = $pickupNumber;
            return $GLOBALS['paymentRefresh']['transactions'];
        }
    }
}

namespace KiriminAjaOfficial\Base {
    final class BaseInit {
        public function logThis( $message, $context ) {}
    }
}

namespace {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

    function wp_timezone_string() {
        return 'UTC';
    }

    function kiriof_log( $level, $message, $context = array(), $source = null ) {
        return true;
    }

    $input = json_decode( $argv[1], true, 512, JSON_THROW_ON_ERROR );
    $GLOBALS['paymentRefresh'] = array(
        'remotePayment' => $input['remotePayment'],
        'payment' => (object) array(
            'pickup_number' => 'PICKUP-TEST',
            'method' => $input['method'],
            'status' => $input['localStatus'],
        ),
        'transactions' => array(
            (object) array(
                'awb' => $input['hasAwb'] ? 'AWB-TEST' : '',
                'cod_fee' => 0,
                'shipping_cost' => 20000,
                'discount_amount' => 2000,
                'insurance_cost' => 1000,
            ),
            (object) array(
                'awb' => '',
                'cod_fee' => 1000,
                'shipping_cost' => 25000,
                'discount_amount' => 0,
                'insurance_cost' => 0,
            ),
        ),
        'apiRequests' => array(),
        'transactionReads' => array(),
        'paymentWrites' => array(),
    );

    require ABSPATH . 'inc/Utils/ServiceResponse.php';
    require ABSPATH . 'inc/Base/BaseService.php';
    require ABSPATH . 'inc/Services/ShippingProcessServices/GetShippingProcessPayment.php';

    $response = ( new \KiriminAjaOfficial\Services\ShippingProcessServices\GetShippingProcessPayment(
        new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository(),
        new \KiriminAjaOfficial\Repositories\PaymentRepository(),
        new \KiriminAjaOfficial\Repositories\TransactionRepository()
    ) )
        ->payment_id( 'PICKUP-TEST' )->call();
    $state = $GLOBALS['paymentRefresh'];
    echo json_encode( array(
        'response' => $response,
        'payment' => $state['payment'],
        'paymentWrites' => $state['paymentWrites'],
        'apiRequests' => $state['apiRequests'],
        'transactionReads' => $state['transactionReads'],
    ), JSON_THROW_ON_ERROR );
}
