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
            $this->assertSame(
            	array(
            		'status' => 200,
            		'calls count' => 1,
            		'calls' => array( 'GET', 'api/mitra/v6.1/addresses' ),
            		'id' => 46310,
            		'subdistrict_id' => 46310,
            		'district_id' => 2275,
            		'data count' => 1,
            		'logs' => array(),
            	),
            	array(
            		'status' => $result['response']['status'],
            		'calls count' => count( $result['calls'] ),
            		'calls' => array_slice( $result['calls'][0], 0, 2 ),
            		'id' => $result['response']['data'][0]['id'],
            		'subdistrict_id' => $result['response']['data'][0]['subdistrict_id'],
            		'district_id' => $result['response']['data'][0]['district_id'],
            		'data count' => count( $result['response']['data'] ),
            		'logs' => $result['logs'],
            	)
            );
        }
        $this->assertSame(
        	array(
        		'calls' => array( 'search' => 'sari harjo' ),
        		'text' => 'Sariharjo, Ngaglik, Sleman, DI Yogyakarta, 55581',
        		'subdistrict_name' => 'Sidokerto',
        		'zip_code' => '61475',
        	),
        	array(
        		'calls' => $results['sari']['calls'][0][2],
        		'text' => $results['sari']['response']['data'][0]['text'],
        		'subdistrict_name' => $results['official']['response']['data'][0]['subdistrict_name'],
        		'zip_code' => $results['postcode']['response']['data'][0]['zip_code'],
        	)
        );
        foreach ( array( 'empty', 'postcode_empty' ) as $name ) {
            $this->assertSame(
            	array(
            		'status' => 200,
            		'data' => array(),
            	),
            	array(
            		'status' => $results[$name]['response']['status'],
            		'data' => $results[$name]['response']['data'],
            	)
            );
        }
    }

    public function test_invalid_shapes_aliases_hierarchies_limits_and_deadlines_fail_closed(): void {
        $results = $this->scenarios();
        foreach ( array( 'missing_id', 'invalid_id', 'float_id', 'parent_alias', 'parent_mismatch', 'name_mismatch', 'hierarchy', 'no_postcode', 'conflict', 'village_conflict', 'parent_conflict', 'missing_list', 'wrong_list', 'bad_status', 'rejection', 'transport', 'exception', 'late', 'short', 'limit' ) as $name ) {
            $result = $results[$name];
            $this->assertSame(
            	array(
            		'status' => 400,
            		'data' => array(),
            		'message' => 'Could not load subdistricts.',
            		'logs count' => 1,
            		'calls bound' => true,
            	),
            	array(
            		'status' => $result['response']['status'],
            		'data' => $result['response']['data'],
            		'message' => $result['response']['message'],
            		'logs count' => count( $result['logs'] ),
            		'calls bound' => count( $result['calls'] ) <= 1,
            	)
            );
            $serialized = json_encode( $result['logs'] );
            $redactions = array();
            foreach ( array( 'PRIVATE', 'Bearer', 'sari harjo', 'credentials' ) as $secret ) {
            	$redactions[ $secret ] = str_contains( $serialized, $secret );
            }
            $this->assertSame( array_fill_keys( array_keys( $redactions ), false ), $redactions, 'Diagnostic redaction contract' );
        }
        $this->assertSame(
        	array(
        		'calls count' => 0,
        		'reason' => 'deadline',
        		'logs reason' => 'result_limit',
        	),
        	array(
        		'calls count' => count( $results['short']['calls'] ),
        		'reason' => $results['late']['logs'][0][2]['reason'],
        		'logs reason' => $results['limit']['logs'][0][2]['reason'],
        	)
        );
    }
}
