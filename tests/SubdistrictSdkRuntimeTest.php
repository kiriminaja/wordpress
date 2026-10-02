<?php

use PHPUnit\Framework\TestCase;

final class SubdistrictSdkRuntimeTest extends TestCase {
    private function scenarios(): array {
        $command = escapeshellarg( PHP_BINARY ) . ' -d display_errors=stderr ' . escapeshellarg( __DIR__ . '/fixtures/subdistrict-sdk-runtime.php' );
        exec( $command, $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_parent_lookup_routes_to_actual_child_ids_and_canonical_labels(): void {
        $result = $this->scenarios()['text'];
        $this->assertSame( array( array( 'parent', 'Pleret' ), array( 'child', 548 ) ), $result['calls'] );
        $this->assertSame( 200, $result['response']['status'] );
        $rows = $result['response']['data'];
        $this->assertSame( array( 31483, 31484, 31485 ), array_column( $rows, 'id' ) );
        $this->assertSame( array( 31483, 31484, 31485 ), array_column( $rows, 'subdistrict_id' ) );
        $this->assertSame( 'Bawuran, Pleret, Kabupaten Bantul, DI Yogyakarta 55791', $rows[0]['text'] );
        $this->assertSame( '55792', $rows[1]['zip_code'] );
        $this->assertSame( '', $rows[2]['zip_code'] );
        $this->assertSame( 'Segoroyoso, Pleret, Kabupaten Bantul, DI Yogyakarta', $rows[2]['text'] );
    }

    public function test_exact_postcode_uses_addresses_and_confirms_children_without_zip(): void {
        $result = $this->scenarios()['postcode'];
        $this->assertSame( array( array( 'get', '/api/mitra/v6.1/addresses', array( 'search' => '55791' ) ), array( 'child', 548 ) ), $result['calls'] );
        $this->assertSame( 200, $result['response']['status'] );
        $this->assertSame( array( 31483, 31485 ), array_column( $result['response']['data'], 'id' ) );
        $this->assertSame( array( '55791', '55791' ), array_column( $result['response']['data'], 'zip_code' ) );
        $this->assertSame( 'Bawuran, Pleret, Kabupaten Bantul, DI Yogyakarta, 55791', $result['response']['data'][0]['text'] );
    }

    public function test_failures_are_closed_and_never_return_parent_or_partial_data(): void {
        $results = $this->scenarios();
        $this->assertSame( array(), $results['empty']['response']['data'] );
        $this->assertSame( 200, $results['empty']['response']['status'] );
        foreach ( array( 'failure', 'invalid_parent', 'malformed_child', 'wrong_parent', 'conflicting_duplicate', 'bounds', 'postcode_missing_child', 'postcode_invalid_parent', 'postcode_invalid_child', 'postcode_wrong_parent', 'postcode_failure', 'postcode_bounds' ) as $scenario ) {
            $this->assertSame( 400, $results[$scenario]['response']['status'], $scenario );
            $this->assertSame( array(), $results[$scenario]['response']['data'], $scenario );
            $this->assertSame( 'Could not load subdistricts.', $results[$scenario]['response']['message'], $scenario );
        }
        $this->assertCount( 1, $results['bounds']['calls'] );
        $this->assertCount( 1, $results['invalid_parent']['calls'] );
        $this->assertCount( 51, $results['boundary']['calls'] );
        $this->assertCount( 1, $results['postcode_bounds']['calls'] );
        $this->assertCount( 51, $results['postcode_boundary']['calls'] );
        $this->assertCount( 50, $results['postcode_boundary']['response']['data'] );
        $this->assertSame( 200, $results['postcode_wrong_only']['response']['status'] );
        $this->assertSame( array(), $results['postcode_wrong_only']['response']['data'] );
    }
}
