<?php

use PHPUnit\Framework\TestCase;

final class SubdistrictSdkRuntimeTest extends TestCase {
    private function scenarios(): array {
        $command = escapeshellarg( PHP_BINARY ) . ' -d display_errors=stderr ' . escapeshellarg( __DIR__ . '/fixtures/subdistrict-sdk-runtime.php' );
        exec( $command, $output, $status );
        $this->assertSame( 0, $status, implode( "\n", $output ) );
        return json_decode( implode( "\n", $output ), true, 512, JSON_THROW_ON_ERROR );
    }

    public function test_names_and_postcodes_use_one_authoritative_unified_lookup(): void {
        $results = $this->scenarios();
        foreach ( array( 'official', 'sari', 'duplicate', 'aliases', 'postcode', 'boundary' ) as $name ) {
            $result = $results[$name];
            $this->assertSame( 200, $result['response']['status'], $name );
            $this->assertCount( 1, $result['calls'] );
            $this->assertSame( array( 'GET', 'api/mitra/v6.1/addresses' ), array_slice( $result['calls'][0], 0, 2 ) );
            $this->assertSame( 46310, $result['response']['data'][0]['id'] );
            $this->assertSame( 46310, $result['response']['data'][0]['subdistrict_id'] );
            $this->assertSame( 2275, $result['response']['data'][0]['district_id'] );
            $this->assertCount( 1, $result['response']['data'] );
            $this->assertSame( array(), $result['logs'] );
        }
        $this->assertSame( array( 'search' => 'sari harjo' ), $results['sari']['calls'][0][2] );
        $this->assertSame( 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581', $results['sari']['response']['data'][0]['text'] );
        $this->assertSame( 'Sidokerto', $results['official']['response']['data'][0]['subdistrict_name'] );
        $this->assertSame( '61475', $results['postcode']['response']['data'][0]['zip_code'] );
        foreach ( array( 'empty', 'postcode_empty' ) as $name ) {
            $this->assertSame( 200, $results[$name]['response']['status'] );
            $this->assertSame( array(), $results[$name]['response']['data'] );
        }
    }

    public function test_invalid_shapes_aliases_hierarchies_limits_and_deadlines_fail_closed(): void {
        $results = $this->scenarios();
        foreach ( array( 'missing_id', 'invalid_id', 'float_id', 'parent_alias', 'parent_mismatch', 'name_mismatch', 'hierarchy', 'no_postcode', 'conflict', 'village_conflict', 'parent_conflict', 'missing_list', 'wrong_list', 'bad_status', 'rejection', 'transport', 'exception', 'late', 'short', 'limit' ) as $name ) {
            $result = $results[$name];
            $this->assertSame( 400, $result['response']['status'], $name );
            $this->assertSame( array(), $result['response']['data'], $name );
            $this->assertSame( 'Could not load subdistricts.', $result['response']['message'], $name );
            $this->assertCount( 1, $result['logs'], $name );
            $this->assertLessThanOrEqual( 1, count( $result['calls'] ) );
            $serialized = json_encode( $result['logs'] );
            foreach ( array( 'PRIVATE', 'Bearer', 'sari harjo', 'credentials' ) as $secret ) {
                $this->assertStringNotContainsString( $secret, $serialized );
            }
        }
        $this->assertCount( 0, $results['short']['calls'] );
        $this->assertSame( 'deadline', $results['late']['logs'][0][2]['reason'] );
        $this->assertSame( 'result_limit', $results['limit']['logs'][0][2]['reason'] );
    }
}
