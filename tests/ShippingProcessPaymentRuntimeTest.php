<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShippingProcessPaymentRuntimeTest extends TestCase {
    #[Test]
    #[DataProvider( 'paymentRefreshProvider' )]
    public function refresh_preserves_confirmed_payments_and_only_promotes_with_payment_evidence(
        string $method,
        string $localStatus,
        array $remotePayment,
        bool $hasAwb,
        string $expectedStatus
    ): void {
        // All WordPress functions and repository stand-ins live only in this child process.
        $input = compact( 'method', 'localStatus', 'remotePayment', 'hasAwb' );
        $command = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( __DIR__ . '/fixtures/payment-refresh-runtime.php' ) . ' '
            . escapeshellarg( json_encode( $input, JSON_THROW_ON_ERROR ) ) . ' 2>&1';
        exec( $command, $output, $exitCode );
        $text = implode( "\n", $output );
        $this->assertSame( 0, $exitCode, 'Payment refresh fixture failed: ' . $text );
        $result = json_decode( $text, true, 512, JSON_THROW_ON_ERROR );

        $this->assertSame( 200, $result['response']['status'] );
        $data = $result['response']['data'];
        $this->assertSame( $remotePayment, $data['payment_data'] );
        $this->assertSame( 'PICKUP-TEST', $data['payment_in_wc_data']['pickup_number'] );
        $this->assertSame( $method, $data['payment_in_wc_data']['method'] );
        $this->assertSame( 1, $data['count_cod'] );
        $this->assertSame( 0, $data['sum_fee_cod'] );
        $this->assertSame( 19000, $data['sum_fee_non_cod'] );
        $this->assertSame( array( array( 'payment_id' => 'PICKUP-TEST' ) ), $result['apiRequests'] );
        $this->assertSame( array( 'PICKUP-TEST', 'PICKUP-TEST' ), $result['transactionReads'] );
        $this->assertSame( $expectedStatus, $data['payment_in_wc_data']['status'], 'Returned payment status' );
        $this->assertSame( $expectedStatus, $result['payment']['status'], 'Persisted payment status' );

        $expectedWrites = $expectedStatus === $localStatus ? array() : array(
            array(
                'changes' => array( 'status' => 'paid' ),
                'condition' => array( 'pickup_number' => 'PICKUP-TEST' ),
            ),
        );
        $this->assertSame( $expectedWrites, $result['paymentWrites'] );
    }

    public static function paymentRefreshProvider(): array {
        $qr = array( 'qr_content' => 'synthetic-test-qr' );
        $payTime = array( 'pay_time' => '2026-01-02T03:04:05Z' );

        return array(
            'QRIS integer zero with QR but no timestamp or paid label' => array( 'qris', 'unpaid', array_merge( $qr, array( 'status_code' => 0 ) ), false, 'paid' ),
            'QRIS string zero with QR but no timestamp or paid label' => array( 'qris', 'unpaid', array_merge( $qr, array( 'status_code' => '0' ) ), false, 'paid' ),
            'QRIS status nine plus generation time and AWB is not payment' => array( 'qris', 'unpaid', array_merge( $qr, $payTime, array( 'status_code' => 9 ) ), true, 'unpaid' ),
            'QRIS missing status plus AWB is not payment' => array( 'qris', 'unpaid', $qr, true, 'unpaid' ),
            'QRIS paid_at still confirms payment' => array( 'qris', 'unpaid', array( 'paid_at' => '2026-01-02T03:04:05Z' ), false, 'paid' ),
            'QRIS paid label still confirms payment' => array( 'qris', 'unpaid', array( 'payment_status' => 'paid' ), false, 'paid' ),
            'QRIS settlement label still confirms payment' => array( 'qris', 'unpaid', array( 'payment_status' => 'settlement' ), false, 'paid' ),
            'QRIS settled label still confirms payment' => array( 'qris', 'unpaid', array( 'payment_status' => 'settled' ), false, 'paid' ),
            'QRIS fallback status success still confirms payment' => array( 'qris', 'unpaid', array( 'status' => 'success' ), false, 'paid' ),
            'paid QRIS survives stale status nine' => array( 'qris', 'paid', array_merge( $qr, $payTime, array( 'status_code' => 9 ) ), true, 'paid' ),
            'paid QRIS survives missing remote status' => array( 'qris', 'paid', $qr, true, 'paid' ),
            'non-QRIS legacy integer zero still confirms payment' => array( 'bank_transfer', 'unpaid', array( 'status_code' => 0 ), false, 'paid' ),
            'non-QRIS legacy string zero still confirms payment' => array( 'bank_transfer', 'unpaid', array( 'status_code' => '0' ), false, 'paid' ),
        );
    }
}
