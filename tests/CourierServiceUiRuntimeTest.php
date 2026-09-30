<?php

use PHPUnit\Framework\TestCase;

final class CourierServiceUiRuntimeTest extends TestCase {
    public function test_picker_and_onboarding_runtime_behaviour(): void {
        if ( ! is_callable( 'exec' ) ) {
            $this->markTestSkipped( 'exec is unavailable; Node runtime tests cannot run.' );
        }

        $candidates = array( 'node' );
        $home = getenv( 'HOME' );
        if ( $home ) {
            $candidates = array_merge(
                $candidates,
                glob( $home . '/.local/share/fnm/node-versions/*/installation/bin/node' ) ?: array(),
                glob( $home . '/Library/Application Support/fnm/node-versions/*/installation/bin/node' ) ?: array()
            );
        }

        $node = null;
        foreach ( $candidates as $candidate ) {
            $output = array();
            $status = 1;
            exec( escapeshellarg( $candidate ) . ' --version 2>&1', $output, $status );
            if ( 0 === $status ) {
                $node = $candidate;
                break;
            }
        }
        if ( null === $node ) {
            $this->markTestSkipped( 'Node.js is unavailable on PATH or in the standard fnm directories.' );
        }

        foreach ( array( 'courier-services-runtime.js', 'onboarding-save-runtime.js' ) as $fixture ) {
            $output = array();
            $status = 1;
            exec( escapeshellarg( $node ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/' . $fixture ) . ' 2>&1', $output, $status );
            $this->assertSame( 0, $status, implode( "\n", $output ) );
            $this->assertStringContainsString( 'runtime tests passed', implode( "\n", $output ) );
        }
    }
}
