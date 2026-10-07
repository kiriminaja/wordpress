<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShipmentDetailPaymentRuntimeTest extends TestCase {
    private function runFixture( array $payload ): array {
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/shipment-detail-payment-runtime.php' ) . ' ' . escapeshellarg( json_encode( $payload, JSON_THROW_ON_ERROR ) ) );
        $this->assertNotNull( $output );
        $this->assertStringNotContainsString( 'secret', $output );
        return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_instant_uses_exact_persisted_fields_without_repository_even_for_legacy_rows(): void {
        foreach ( [ [ 'delivery_type' => 'instant', 'service' => 'jne' ], [ 'delivery_type' => 'express', 'service' => 'GoSend' ] ] as $classification ) {
            $row = array_merge( $classification, [ 'instant_payment_id' => 'IN-1', 'instant_payment_status' => 'pending', 'instant_payment_method' => 'KA Credit', 'pickup_number' => 'WRONG' ] );
            $result = $this->runFixture( [ 'mode' => 'helper', 'row' => $row, 'constructor_error' => true ] );
            $this->assertSame( [ 'id' => 'IN-1', 'status' => 'pending', 'method' => 'KA Credit' ], $result['payment'] );
            $this->assertSame( [], $result['lookups'] );
        }
    }

    public function test_express_requires_matching_durable_group_and_ignores_wc_and_instant_evidence(): void {
        $row = [ 'delivery_type' => 'express', 'service' => 'jne', 'pickup_number' => 'GROUP-1', 'instant_payment_id' => 'IN-WRONG', 'payment_id' => 'WC-WRONG' ];
        $result = $this->runFixture( [ 'mode' => 'helper', 'row' => $row, 'inject_repository' => true, 'carrier_payment' => [ 'pickup_number' => 'GROUP-1', 'status' => 'unpaid', 'method' => 'bank_transfer' ] ] );
        $this->assertSame( [ 'id' => 'GROUP-1', 'status' => 'unpaid', 'method' => 'bank_transfer' ], $result['payment'] );
        $this->assertSame( [ 'GROUP-1' ], $result['lookups'] );
        foreach ( [ false, [], [ 'pickup_number' => 'OTHER', 'status' => 'paid' ] ] as $payment ) {
            $result = $this->runFixture( [ 'mode' => 'helper', 'row' => $row, 'carrier_payment' => $payment ] );
            $this->assertSame( [ 'id' => '', 'status' => '', 'method' => '' ], $result['payment'] );
        }
    }

    public function test_missing_group_skips_lookup_and_repository_errors_fail_open_without_secrets(): void {
        foreach ( [ '', '-', '   ', ' - ' ] as $pickup ) {
            $result = $this->runFixture( [ 'mode' => 'helper', 'row' => [ 'pickup_number' => $pickup ], 'constructor_error' => true ] );
            $this->assertSame( [ 'id' => '', 'status' => '', 'method' => '' ], $result['payment'] );
            $this->assertSame( [], $result['lookups'] );
        }
        foreach ( [ 'constructor_error', 'lookup_error' ] as $error ) {
            $result = $this->runFixture( [ 'mode' => 'helper', 'row' => [ 'pickup_number' => 'GROUP-1' ], $error => true ] );
            $this->assertSame( [ 'id' => '', 'status' => '', 'method' => '' ], $result['payment'] );
        }
    }

    public function test_detail_and_fallback_agree_and_separate_carrier_payment_from_paid_shopper(): void {
        foreach ( [ 'detail', 'fallback' ] as $mode ) {
            foreach ( [ 'express', 'instant' ] as $type ) {
                $payload = [ 'mode' => $mode, 'row' => [ 'delivery_type' => $type, 'service' => 'instant' === $type ? 'gosend' : 'jne', 'pickup_number' => 'GROUP-1', 'instant_payment_id' => 'IN-1', 'instant_payment_status' => 'pending', 'instant_payment_method' => 'qris' ], 'wc_order' => [ 'paid' => true, 'status' => 'completed' ], 'carrier_payment' => [ 'pickup_number' => 'GROUP-1', 'status' => 'unpaid', 'method' => 'bank_transfer' ] ];
                $data = $this->runFixture( $payload );
                $shipment = $data['transaction']['shipment'];
                $this->assertSame( 'instant' === $type ? 'IN-1' : 'GROUP-1', $shipment['paymentId'] );
                $this->assertSame( 'instant' === $type ? 'pending' : 'unpaid', $shipment['paymentStatus'] );
                $this->assertSame( 'instant' === $type ? 'qris' : 'bank_transfer', $shipment['paymentMethod'] );
                $this->assertSame( 'Paid', $shipment['buyerPaymentStatus'] );
                $this->assertSame( 'Motor', $data['i18n']['motor'] );
                $this->assertSame( 'Mobil', $data['i18n']['mobil'] );
                unset( $payload['carrier_payment'], $payload['wc_order'] );
                $payload['row'] = [ 'delivery_type' => 'express', 'service' => 'jne', 'pickup_number' => 'GROUP-1', 'cod_fee' => 100 ];
                $data = $this->runFixture( $payload );
                $this->assertSame( '', $data['transaction']['shipment']['paymentId'] );
                $this->assertSame( '', $data['transaction']['shipment']['paymentStatus'] );
                $this->assertSame( '', $data['transaction']['shipment']['buyerPaymentStatus'] );
                $this->assertSame( 'COD', $data['transaction']['paymentLabel'] );
            }
        }
    }
}
