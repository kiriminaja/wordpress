<?php

use PHPUnit\Framework\TestCase;

final class PostalLookupRecoveryRuntimeTest extends TestCase {
    private function scenario( string $name ): array {
        exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=stderr ' . escapeshellarg( __DIR__ . '/fixtures/postal-lookup-recovery-runtime.php' ) . ' ' . escapeshellarg( $name ), $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_empty_and_wrong_postcode_results_recover_without_negative_cache_or_logs(): void {
        foreach ( array( 'empty', 'wrong_postcode' ) as $scenario ) {
            $result = $this->scenario( $scenario );
            $this->assertSame( array(), $result['response'][0]['data'] );
            $this->assertSame( 200, $result['response'][0]['status'] );
            $this->assertCount( 1, $result['response'][1]['data'] );
            $this->assertSame( $result['response'][1], $result['response'][2] );
            $this->assertCount( 2, $result['calls'] );
            $this->assertCount( 1, $result['writes'] );
            $this->assertSame( 300, $result['writes'][0][2] );
            $this->assertSame( array(), $result['logs'] );
            foreach ( $result['calls'] as $call ) {
                $this->assertSame( array( 'GET', 'api/mitra/v6.1/addresses', array( 'search' => '55581' ) ), $call );
            }
            $this->assertNotContains( 'kiriof_district_search_v3_' . md5( '55581' ), $result['reads'] );
        }
    }

    public function test_explicit_verified_retry_replaces_positive_cache(): void {
        foreach ( array( 'retry', 'nested_retry' ) as $scenario ) {
            $result = $this->scenario( $scenario );
            $this->assertTrue( $result['response']['success'] );
            $this->assertCount( 2, $result['response']['data'] );
            $this->assertCount( 2, $result['calls'] );
            $this->assertCount( 2, $result['writes'] );
            $this->assertCount( 1, $result['reads'] );
            $this->assertSame( $result['response']['data'], $result['cache']['kiriof_district_search_v4_' . md5( '55581' )] );
            $this->assertSame( array(), $result['logs'] );
        }
    }

    public function test_ordinary_and_nonliteral_retries_preserve_cache(): void {
        foreach ( array( 'ordinary', 'array_retry', 'integer_retry', 'boolean_retry', 'sanitized_retry' ) as $scenario ) {
            $result = $this->scenario( $scenario );
            $this->assertTrue( $result['response']['success'], $scenario );
            $this->assertCount( 1, $result['response']['data'] );
            $this->assertCount( 1, $result['calls'] );
            $this->assertCount( 1, $result['writes'] );
            $this->assertSame( array(), $result['logs'] );
        }
    }

    public function test_invalid_nonce_cannot_bypass_cache(): void {
        foreach ( array( 'invalid_nonce', 'array_nonce' ) as $scenario ) {
            $result = $this->scenario( $scenario );
            $this->assertFalse( $result['response']['success'] );
            $this->assertSame( 403, $result['response']['http_status'] );
            $this->assertCount( 1, $result['calls'] );
            $this->assertCount( 1, $result['writes'] );
        }
    }

    public function test_failed_retry_does_not_overwrite_good_cache_and_corrupt_cache_is_not_served(): void {
        $result = $this->scenario( 'failed_retry' );
        $this->assertFalse( $result['response']['success'] );
        $this->assertSame( 'subdistrict_lookup_failed', $result['response']['data']['code'] );
        $this->assertCount( 2, $result['calls'] );
        $this->assertCount( 1, $result['writes'] );
        $this->assertCount( 1, $result['cache']['kiriof_district_search_v4_' . md5( '55581' )] );
        $result = $this->scenario( 'corrupt' );
        $this->assertSame( 200, $result['response']['status'] );
        $this->assertSame( 46310, $result['response']['data'][0]['id'] );
        $this->assertCount( 1, $result['calls'] );
        $this->assertCount( 1, $result['writes'] );
    }
}
