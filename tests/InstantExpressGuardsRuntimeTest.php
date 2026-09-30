<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstantExpressGuardsRuntimeTest extends TestCase {
    private function runGuard( string $operation, array $rows ): array {
        $input = json_encode( array( 'operation' => $operation, 'rows' => $rows ), JSON_THROW_ON_ERROR );
        $output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( PLUGIN_DIR . '/tests/fixtures/instant-express-guards-runtime.php' ) . ' ' . escapeshellarg( $input ) );
        return json_decode( (string) $output, true, 512, JSON_THROW_ON_ERROR );
    }

    #[Test]
    public function instant_mutations_are_rejected_without_api_calls_or_writes(): void {
        foreach ( array( array( 'delivery_type' => 'instant', 'service' => 'jne' ), array( 'delivery_type' => 'express', 'service' => 'gosend' ) ) as $row ) {
            foreach ( array( 'pickup', 'cancel', 'adjust', 'deficit', 'print', 'auto', 'origin' ) as $operation ) {
                $result = $this->runGuard( $operation, array( $row ) );
                if ( 'auto' !== $operation ) { $this->assertStringContainsString( 'Instant', $result['message'], $operation ); }
                $this->assertSame( 0, $result['calls'], $operation );
                $this->assertSame( 0, $result['writes'], $operation );
            }
        }
    }

    #[Test]
    public function mixed_pickup_and_print_batches_are_rejected_as_a_whole(): void {
        foreach ( array( 'pickup', 'print' ) as $operation ) {
            foreach ( array( array( array( 'service' => 'jne' ), array( 'service' => 'borzo' ) ), array( array( 'service' => 'grab_express' ), array( 'service' => 'jne' ) ) ) as $rows ) {
                $result = $this->runGuard( $operation, $rows );
                $this->assertStringContainsString( 'Instant', $result['message'] );
                $this->assertSame( 0, $result['calls'] );
                $this->assertSame( 0, $result['writes'] );
            }
        }
    }

    #[Test]
    public function express_cancel_keeps_existing_api_and_local_update_behavior(): void {
        $result = $this->runGuard( 'cancel', array( array( 'service' => 'jne' ) ) );
        $this->assertSame( 200, $result['status'] );
        $this->assertSame( 1, $result['calls'] );
        $this->assertSame( 1, $result['writes'] );
        $result = $this->runGuard( 'pickup', array( array( 'service' => 'jne' ) ) );
        $this->assertSame( 'Schedule is required', $result['message'] );
    }
}
