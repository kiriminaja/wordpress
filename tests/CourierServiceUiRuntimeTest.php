<?php

use PHPUnit\Framework\TestCase;

final class CourierServiceUiRuntimeTest extends TestCase {
    public function test_shared_selection_runtime_behaviour(): void {
        if ( ! is_callable( 'exec' ) ) {
            $this->markTestSkipped( 'exec is unavailable; Bun runtime tests cannot run.' );
        }
        $candidates = array( 'bun' );
        $home = getenv( 'HOME' );
        if ( $home ) { $candidates[] = $home . '/.bun/bin/bun'; }
        $bun = null;
        foreach ( $candidates as $candidate ) {
            $output = array();
            exec( escapeshellarg( $candidate ) . ' --version 2>&1', $output, $status );
            if ( 0 === $status ) { $bun = $candidate; break; }
        }
        if ( null === $bun ) { $this->markTestSkipped( 'Bun is unavailable on PATH or in ~/.bun/bin.' ); }
        $output = array();
        exec( 'cd ' . escapeshellarg( PLUGIN_DIR ) . ' && ' . escapeshellarg( $bun ) . ' test tests/CourierServiceSelection.test.ts 2>&1', $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        $this->assertMatchesRegularExpression( '/[1-9][0-9]* pass/', implode( "\n", $output ) );
        $this->assertStringContainsString( '0 fail', implode( "\n", $output ) );
    }
}
