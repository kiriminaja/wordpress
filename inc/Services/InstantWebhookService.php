<?php
namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Validates the whole Instant callback before handing any delivery to the state writer. */
final class InstantWebhookService {
    private $state;

    public function __construct( $state ) {
        $this->state = $state;
    }

    public function handle( object $body, array $transactions ): array {
        $method = $body->method ?? null;
        $codes = array(
            'processed_packages' => array( '105' ),
            'shipped_packages' => array( '106' ),
            'finished_packages' => array( '200' ),
            'canceled_packages' => array( '300', '302' ),
        );
        if ( ! is_string( $method ) || ! isset( $codes[ $method ] ) ) {
            return $this->invalid( 'Unsupported Instant callback method' );
        }
        if ( ! isset( $body->data ) || ! is_array( $body->data ) || ! count( $body->data ) || count( $body->data ) > 200 ) {
            return $this->invalid( 'Instant callback requires between 1 and 200 data rows' );
        }
        $rows = array();
        foreach ( $transactions as $transaction ) {
            $transaction = is_object( $transaction ) ? get_object_vars( $transaction ) : $transaction;
            if ( ! is_array( $transaction ) ) {
                return $this->invalid( 'Invalid local Instant transaction' );
            }
            $id = $this->orderId( $transaction );
            $service = $transaction['service'] ?? '';
            if ( null === $id || isset( $rows[ $id ] ) || TransactionDeliveryType::resolve( $transaction ) !== 'instant'
                || ! is_string( $service ) || ! in_array( strtolower( trim( $service ) ), array( 'gosend', 'grab_express' ), true ) ) {
                return $this->invalid( 'Unsupported or duplicate Instant transaction' );
            }
            $rows[ $id ] = $transaction;
        }
        $events = $this->index( $body->data );
        if ( null === $events || count( $events ) !== count( $rows ) || array_diff_key( $events, $rows ) || array_diff_key( $rows, $events ) ) {
            return $this->invalid( 'Instant data must match every local order exactly once' );
        }
        $packages = array();
        if ( property_exists( $body, 'packages' ) ) {
            if ( ! is_array( $body->packages ) || count( $body->packages ) > 200 ) {
                return $this->invalid( 'Invalid Instant package metadata' );
            }
            $packages = $this->index( $body->packages );
            if ( null === $packages || array_diff_key( $packages, $events ) ) {
                return $this->invalid( 'Instant package metadata contains unknown or duplicate orders' );
            }
        }
        $payment = array();
        if ( property_exists( $body, 'payment' ) ) {
            if ( ! is_array( $body->payment ) && ! is_object( $body->payment ) ) {
                return $this->invalid( 'Invalid Instant payment metadata' );
            }
            $payment = (array) $body->payment;
        }
        $prepared = array();
        foreach ( $events as $id => $event ) {
            $package = $packages[ $id ] ?? array();
            $row = $rows[ $id ];
            // Unlike bare terminal lifecycle hooks, processed is supported only
            // with explicit ready-for-shipment evidence from the Instant API.
            // Never infer processing/payment success from the method name alone.
            if ( 'processed_packages' === $method && (
                ! isset( $package['service'], $package['service_type'] )
                || ( ! isset( $payment['id'] ) && ! isset( $payment['payment_id'] ) )
                || ! in_array( $payment['status_code'] ?? $payment['status'] ?? null, array( 0, '0', 'paid' ), true )
            ) ) {
                return $this->invalid( 'Instant processed callback requires package and paid payment evidence' );
            }
            if ( ! in_array( $row['status'] ?? null, array( 'pending', 'request_pickup', 'shipped', 'finished', 'canceled', 'return', 'returned', 'rejected' ), true ) ) {
                return $this->invalid( 'Invalid local Instant lifecycle' );
            }
            $foundCode = null;
            foreach ( array( $event, $package ) as $source ) {
                foreach ( array( 'status', 'status_code', 'instant_status_code' ) as $field ) {
                    if ( ! array_key_exists( $field, $source ) ) {
                        continue;
                    }
                    $status = $source[ $field ];
                    if ( ! ( is_int( $status ) || is_string( $status ) ) || ! in_array( (string) $status, $codes[ $method ], true ) ) {
                        return $this->invalid( 'Instant status contradicts callback method' );
                    }
                    if ( null !== $foundCode && $foundCode !== (string) $status ) {
                        return $this->invalid( 'Conflicting Instant status metadata' );
                    }
                    $foundCode = (string) $status;
                }
                if ( isset( $source['awb'] ) && ( ! $this->identifier( $source['awb'] ) || ( ! empty( $row['awb'] ) && $row['awb'] !== $source['awb'] ) ) ) {
                    return $this->invalid( 'Invalid Instant AWB' );
                }
                foreach ( array( 'service', 'service_type' ) as $field ) {
                    $expected = (string) ( 'service_type' === $field ? ( $row['service_name'] ?? $row['service_type'] ?? '' ) : ( $row['service'] ?? '' ) );
                    if ( array_key_exists( $field, $source ) && ( 'service_type' === $field ? ! InstantShipmentState::sameServiceType( $source[ $field ], $expected ) : ! is_string( $source[ $field ] ) || trim( $source[ $field ] ) !== trim( $expected ) ) ) {
                        return $this->invalid( 'Instant courier metadata does not match local order' );
                    }
                }
            }
            foreach ( array( 'id', 'payment_id' ) as $field ) {
                if ( array_key_exists( $field, $payment ) && ( ! $this->identifier( $payment[ $field ] ) || ( ! empty( $row['instant_payment_id'] ) && $row['instant_payment_id'] !== $payment[ $field ] ) || ( empty( $row['instant_payment_id'] ) && ! in_array( $row['status'] ?? '', array( 'pending', 'request_pickup' ), true ) ) ) ) {
                    return $this->invalid( 'Instant payment metadata does not match local order' );
                }
            }
            if ( isset( $payment['id'], $payment['payment_id'] ) && $payment['id'] !== $payment['payment_id'] ) {
                return $this->invalid( 'Conflicting Instant payment identifiers' );
            }
            if ( ! empty( $event['awb'] ) && ! empty( $package['awb'] ) && $event['awb'] !== $package['awb'] ) {
                return $this->invalid( 'Conflicting Instant AWB metadata' );
            }
            // Null AWBs are omissions, not an instruction to erase event metadata.
            if ( array_key_exists( 'awb', $event ) && null === $event['awb'] ) {
                unset( $event['awb'] );
            }
            if ( array_key_exists( 'awb', $package ) && null === $package['awb'] ) {
                unset( $package['awb'] );
            }
            // Metadata is joined by exact order ID, never by payload position.
            $merged = array_merge( $event, $package );
            if ( null === $foundCode ) {
                if ( 'processed_packages' === $method ) {
                    return $this->invalid( 'Instant processed callback requires an explicit remote status' );
                }
                // Only bare lifecycle events may infer a status in the state writer.
                foreach ( array( 'service', 'service_type', 'live_tracking_url', 'live_track_url', 'tracking_url', 'live_tracking' ) as $field ) {
                    if ( array_key_exists( $field, $merged ) ) {
                        return $this->invalid( 'Instant metadata requires a remote status' );
                    }
                }
                if ( ! empty( $payment ) ) {
                    return $this->invalid( 'Instant payment requires a remote status' );
                }
            }
            foreach ( array( 'shipped_at', 'finished_at', 'canceled_at', 'date' ) as $timestamp ) {
                if ( array_key_exists( $timestamp, $event ) ) {
                    $merged[ $timestamp ] = $event[ $timestamp ];
                }
            }
            $prepared[ $id ] = $merged;
        }
        $failed = array();
        foreach ( $prepared as $id => $package ) {
            try {
                $result = $this->state->apply( (string) $id, $package, $payment, $method );
                if ( ! isset( $result['status'] ) || false === $result['status'] ) {
                    $failed[] = (string) $id;
                }
            } catch ( \Throwable $error ) {
                // Do not expose remote payloads or exception details in webhook logs.
                $failed[] = (string) $id;
            }
        }
        return array(
            'status' => ! count( $failed ),
            'message' => count( $failed ) ? 'One or more Instant updates could not be verified' : '',
            'failed_order_ids' => $failed,
            'http_status' => count( $failed ) ? 503 : 200,
        );
    }

    private function orderId( array $row ): ?string {
        $id = $row['order_id'] ?? null;
        return $this->identifier( $id ) ? $id : null;
    }

    private function identifier( $value ): bool {
        return is_string( $value ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $value );
    }

    private function index( array $sources ): ?array {
        $result = array();
        foreach ( $sources as $source ) {
            if ( ! is_array( $source ) && ! is_object( $source ) ) {
                return null;
            }
            $source = (array) $source;
            $id = $this->orderId( $source );
            if ( null === $id || isset( $result[ $id ] ) ) {
                return null;
            }
            $result[ $id ] = $source;
        }
        return $result;
    }

    private function invalid( string $message ): array {
        return array( 'status' => false, 'message' => $message, 'failed_order_ids' => array(), 'http_status' => 400 );
    }
}
