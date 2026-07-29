<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KiriminAjaOfficial\Base\BaseService;

class CallbackHandlerService extends BaseService {
    public $header;
    public $body;
    public array $packages = array();
    public $processing;

    private $transactions = array();
    private $transactionsByOrderId = array();
    private $transactionRepository;
    private $paymentRepository;
    private $apiToken;

    public function __construct( $transactionRepository = null, $paymentRepository = null, $apiToken = null ) {
        $this->transactionRepository = $transactionRepository ?: new \KiriminAjaOfficial\Repositories\TransactionRepository();
        $this->paymentRepository     = $paymentRepository ?: new \KiriminAjaOfficial\Repositories\PaymentRepository();
        $this->apiToken              = $apiToken;
    }

    public function header( $header ) {
        $this->header = $header;
        return $this;
    }

    public function body( $body ) {
        $this->body = $body;
        return $this;
    }

    public function call() {
        if ( ! $this->headerValidation() ) {
            $this->logWebhookEvent( 'warning', 'KiriminAja webhook authorization failed because the bearer token did not match the saved API key.' );
            return self::error( array(), 'Authorization failed', 401 );
        }

        if ( isset( $this->body->data ) && ! empty( $this->body->data ) ) {
            $this->packages = (array) $this->body->data;
        } elseif ( isset( $this->body->packages ) && ! empty( $this->body->packages ) ) {
            $this->packages = (array) $this->body->packages;
        }

        $orderIds = $this->getPackageOrderIds();
        if ( empty( $orderIds ) ) {
            $this->logWebhookEvent( 'warning', 'KiriminAja webhook request was ignored because no order identifiers were present in the payload.' );
            return self::error( array(), 'No Order ID Found' );
        }

        $this->transactions = $this->transactionRepository->getTransactionByOrderIds( $orderIds );
        if ( false === $this->transactions ) {
            $this->logWebhookEvent( 'error', 'KiriminAja webhook could not read local transaction state.', array( 'order_ids' => $orderIds ) );
            return self::error( array(), 'Transaction lookup failed', 503 );
        }

        foreach ( $this->transactions as $transaction ) {
            $this->transactionsByOrderId[ (string) $transaction->order_id ] = $transaction;
        }

        $unmatchedOrderIds = array_values( array_diff( $orderIds, array_keys( $this->transactionsByOrderId ) ) );
        if ( ! empty( $unmatchedOrderIds ) ) {
            $this->logWebhookEvent(
                'error',
                'KiriminAja webhook was deferred because one or more packages had no matching local transaction.',
                array(
                    'order_ids'           => $orderIds,
                    'matched_order_ids'   => array_keys( $this->transactionsByOrderId ),
                    'unmatched_order_ids' => $unmatchedOrderIds,
                )
            );
            return self::error( array( 'unmatched_order_ids' => $unmatchedOrderIds ), 'Not all transactions were found', 503 );
        }

        switch ( (string) ( $this->body->method ?? '' ) ) {
            case 'return_finished_packages':
                $this->processing = $this->returnFinishedPackages();
                break;
            case 'processed_packages':
                $this->processing = $this->processedPackages();
                break;
            case 'shipped_packages':
                $this->processing = $this->shippedPackages();
                break;
            case 'finished_packages':
                $this->processing = $this->finishedPackages();
                break;
            case 'returned_packages':
                $this->processing = $this->returnedPackages();
                break;
            case 'validated_packages':
                $this->processing = $this->validatedPackages();
                break;
            case 'rejected_packages':
                $this->processing = $this->rejectedPackages();
                break;
            case 'canceled_packages':
                $this->processing = $this->canceledPackages();
                break;
            default:
                $this->logWebhookEvent( 'warning', 'KiriminAja webhook request used an unsupported callback method.', array( 'order_ids' => $orderIds ) );
                return self::error( array(), 'Unsupported callback method' );
        }

        if ( ! $this->processing['status'] ) {
            $context = array(
                'order_ids'        => $orderIds,
                'failed_order_ids' => $this->processing['failed_order_ids'] ?? array(),
                'pickup_numbers'   => $this->processing['pickup_numbers'] ?? array(),
                'message'          => $this->processing['message'],
            );
            $this->logWebhookEvent( 'error', 'KiriminAja webhook processing failed while updating local state and should be retried.', $context );
            return self::error( $context, $this->processing['message'], 503 );
        }

        return self::success( array(), 'Success' );
    }

    private function headerValidation() {
        $authorization = '';
        foreach ( (array) $this->header as $key => $value ) {
            if ( 'authorization' === strtolower( (string) $key ) ) {
                $authorization = trim( (string) $value );
                break;
            }
        }

        if ( 0 === stripos( $authorization, 'Bearer ' ) ) {
            $authorization = trim( substr( $authorization, 7 ) );
        }

        $token = null !== $this->apiToken
            ? trim( (string) $this->apiToken )
            : trim( (string) ( ( new \KiriminAjaOfficial\Repositories\SettingRepository() )->getSettingByKey( 'api_key' )->value ?? '' ) );

        return '' !== $authorization && '' !== $token && hash_equals( $token, $authorization );
    }

    public function returnFinishedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array(
                    'return_finished_at' => kiriof_helper()->dateConvertGMT( $this->packageValue( $package, 'date' ) ),
                    'status'             => 'returned',
                );
            },
            'KiriminAja webhook marked packages as return finished.',
            'cancelled'
        );
    }

    public function processedPackages() {
        $failedOrderIds = array();
        $pickupNumbers  = array();

        foreach ( $this->packages as $package ) {
            $orderId     = (string) $this->packageValue( $package, 'order_id' );
            $transaction = $this->transactionsByOrderId[ $orderId ];
            $updated     = $this->transactionRepository->updateTransactionByCallbackVerified(
                array(
                    'changes'   => array( 'awb' => $this->packageValue( $package, 'awb' ) ),
                    'condition' => array( 'order_id' => $orderId ),
                )
            );

            if ( ! $updated ) {
                $failedOrderIds[] = $orderId;
                continue;
            }

            $pickupNumber = trim( (string) ( $transaction->pickup_number ?? '' ) );
            if ( '' === $pickupNumber ) {
                $failedOrderIds[] = $orderId;
                continue;
            }
            $pickupNumbers[ $pickupNumber ] = true;
        }

        if ( ! empty( $failedOrderIds ) ) {
            return $this->processingError( 'One or more package updates could not be verified', $failedOrderIds, array_keys( $pickupNumbers ) );
        }

        $paymentStatuses = array();
        foreach ( array_keys( $pickupNumbers ) as $pickupNumber ) {
            $paymentRecord = $this->paymentRepository->getPaymentByPaymentId( $pickupNumber );
            if ( ! $paymentRecord ) {
                return $this->processingError( 'A matching payment record was not found', array(), array( $pickupNumber ) );
            }

            $paymentMethod = strtolower( (string) ( $paymentRecord->method ?? '' ) );
            $paymentStatus = strtolower( (string) ( $paymentRecord->status ?? '' ) );
            if ( $paymentMethod !== 'qris' || $paymentStatus === 'paid' ) {
                $paymentUpdated = $this->paymentRepository->updatePaymentByCallbackVerified(
                    array(
                        'changes'   => array( 'status' => 'paid' ),
                        'condition' => array( 'pickup_number' => $pickupNumber ),
                    )
                );
                if ( ! $paymentUpdated ) {
                    return $this->processingError( 'A payment update could not be verified', array(), array( $pickupNumber ) );
                }
                $paymentStatus = 'paid';
            }
            $paymentStatuses[ $pickupNumber ] = array( 'method' => $paymentMethod, 'status' => $paymentStatus );
        }

        $this->logWebhookEvent(
            'notice',
            'KiriminAja webhook stored AWB numbers and synchronized payment status for processed packages.',
            array(
                'order_ids'        => $this->getPackageOrderIds(),
                'pickup_numbers'   => array_keys( $pickupNumbers ),
                'payment_statuses' => $paymentStatuses,
            )
        );

        return array( 'status' => true, 'message' => '' );
    }

    public function shippedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array(
                    'shipped_at' => kiriof_helper()->dateConvertGMT( $this->packageValue( $package, 'shipped_at' ) ),
                    'status'     => 'shipped',
                );
            },
            'KiriminAja webhook marked packages as shipped.'
        );
    }

    public function finishedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array(
                    'finished_at' => kiriof_helper()->dateConvertGMT( $this->packageValue( $package, 'finished_at' ) ),
                    'status'      => 'finished',
                );
            },
            'KiriminAja webhook marked packages as finished and completed matching WooCommerce orders.',
            'completed'
        );
    }

    public function returnedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array(
                    'returned_at' => kiriof_helper()->dateConvertGMT( $this->packageValue( $package, 'returned_at' ) ),
                    'status'      => 'return',
                );
            },
            'KiriminAja webhook marked packages as returned.'
        );
    }

    public function validatedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array( 'shipping_cost' => $this->packageValue( $package, 'shipping_cost' ) );
            },
            'KiriminAja webhook updated validated package shipping costs.'
        );
    }

    public function rejectedPackages() {
        return $this->updatePackages(
            function ( $package ) {
                return array(
                    'rejected_at'     => kiriof_helper()->dateConvertGMT( $this->packageValue( $package, 'rejected_at' ) ),
                    'rejected_reason' => $this->packageValue( $package, 'reason' ),
                    'status'          => 'rejected',
                );
            },
            'KiriminAja webhook marked packages as rejected.'
        );
    }

    public function canceledPackages() {
        $result = $this->updatePackages(
            function ( $package ) {
                $canceledAt = $this->packageValue( $package, 'canceled_at' ) ?: gmdate( 'Y-m-d H:i:s' );
                return array(
                    'canceled_at' => kiriof_helper()->dateConvertGMT( $canceledAt ),
                    'status'      => 'canceled',
                );
            },
            'KiriminAja webhook marked packages as canceled and synced matching WooCommerce orders.',
            'cancelled',
            true
        );

        return $result;
    }

    private function updatePackages( $changesCallback, $message, $wcStatus = '', $guardCancelHook = false ) {
        try {
            $failedOrderIds = array();
            foreach ( $this->packages as $package ) {
                $orderId     = (string) $this->packageValue( $package, 'order_id' );
                $transaction = $this->transactionsByOrderId[ $orderId ];
                $updated     = $this->transactionRepository->updateTransactionByCallbackVerified(
                    array(
                        'changes'   => $changesCallback( $package ),
                        'condition' => array( 'order_id' => $orderId ),
                    )
                );

                if ( ! $updated ) {
                    $failedOrderIds[] = $orderId;
                    continue;
                }

                if ( '' !== $wcStatus ) {
                    $order = wc_get_order( $transaction->wp_wc_order_stat_order_id );
                    if ( ! $order ) {
                        $failedOrderIds[] = $orderId;
                        continue;
                    }

                    $needsStatusUpdate = $guardCancelHook
                        ? $order->get_status() !== 'cancelled'
                        : $order->get_status() !== $wcStatus;
                    if ( $needsStatusUpdate ) {
                        if ( $guardCancelHook ) {
                            remove_action( 'woocommerce_order_status_cancelled', array( 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ) );
                        }
                        try {
                            $order->update_status( $wcStatus );
                        } finally {
                            if ( $guardCancelHook ) {
                                add_action( 'woocommerce_order_status_cancelled', array( 'KiriminAjaOfficial\\Controllers\\TransactionProcessController', 'handleWcOrderCancelled' ) );
                            }
                        }
                    }
                }
            }

            if ( ! empty( $failedOrderIds ) ) {
                return $this->processingError( 'One or more package updates could not be verified', $failedOrderIds );
            }

            $this->logWebhookEvent( 'notice', $message, array( 'order_ids' => $this->getPackageOrderIds() ) );
            return array( 'status' => true, 'message' => '' );
        } catch ( \Throwable $th ) {
            return $this->processingError( $th->getMessage(), $this->getPackageOrderIds() );
        }
    }

    private function processingError( $message, $failedOrderIds = array(), $pickupNumbers = array() ) {
        return array(
            'status'           => false,
            'message'          => $message,
            'failed_order_ids' => array_values( array_unique( $failedOrderIds ) ),
            'pickup_numbers'   => array_values( array_unique( $pickupNumbers ) ),
        );
    }

    private function packageValue( $package, $key ) {
        if ( is_object( $package ) ) {
            return $package->{$key} ?? '';
        }
        return is_array( $package ) ? ( $package[ $key ] ?? '' ) : '';
    }

    private function getPackageOrderIds(): array {
        $orderIds = array();
        foreach ( (array) $this->packages as $package ) {
            $orderId = (string) $this->packageValue( $package, 'order_id' );
            if ( '' !== $orderId ) {
                $orderIds[] = $orderId;
            }
        }
        return array_values( array_unique( $orderIds ) );
    }

    private function logWebhookEvent( string $level, string $message, array $context = array() ): void {
        kiriof_log(
            $level,
            $message,
            array_merge(
                array(
                    'source'          => 'kiriminaja_webhook',
                    'callback_method' => is_object( $this->body ) ? (string) ( $this->body->method ?? '' ) : '',
                    'package_count'   => count( (array) $this->packages ),
                ),
                $context
            )
        );
    }
}
