<?php

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;
use KiriminAjaOfficial\Utils\ServiceResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DistrictSearchCacheRuntimeTest extends TestCase {
    protected function setUp(): void {
        if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
        if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
        require_once dirname( __DIR__ ) . '/vendor/autoload.php';
        $GLOBALS['district_search_cache'] = array();
        $GLOBALS['district_search_reads'] = array();
        $GLOBALS['district_search_writes'] = array();
        $GLOBALS['hierarchy_events'] = array();
        $GLOBALS['hierarchy_hooks'] = array();
        function add_action( $hook, $callback ) { $GLOBALS['hierarchy_hooks'][ $hook ] = $callback; }
        function has_action( $hook ) { return isset( $GLOBALS['hierarchy_hooks'][ $hook ] ); }
        function wp_next_scheduled( $hook, $args ) { return $GLOBALS['hierarchy_events'][ $hook ][ $args[0] ] ?? false; }
        function wp_schedule_single_event( $when, $hook, $args ) { $GLOBALS['hierarchy_events'][ $hook ][ $args[0] ] = $when; return true; }
        // These stubs exist only in this test's isolated process.
        function get_transient( $key ) {
            $GLOBALS['district_search_reads'][] = $key;
            return $GLOBALS['district_search_cache'][$key] ?? false;
        }
        function set_transient( $key, $value, $ttl ) {
            if ( str_starts_with( $key, 'kiriof_district_search_' ) ) {
                $GLOBALS['district_search_writes'][] = array( $key, $value, $ttl );
            }
            $GLOBALS['district_search_cache'][$key] = $value;
            return true;
        }
    }

    private function factory( KiriminajaApiRepository $repository ): CheckoutServiceFactory {
        return new CheckoutServiceFactory(
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\SettingRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\TransactionRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\WpPostMetaRepository::class )->disableOriginalConstructor()->getMock(),
            $repository,
            $this->getMockBuilder( \KiriminAjaOfficial\Repositories\CodFeeApiRepository::class )->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder( \KiriminAjaOfficial\Services\ShipmentLocationService::class )->disableOriginalConstructor()->getMock()
        );
    }

    private function rows(): array {
        return array( array( 'id' => 222, 'subdistrict_id' => 222, 'district_id' => 12, 'city_id' => 3, 'province_id' => 1, 'subdistrict_name' => 'Village', 'district_name' => 'District', 'city_name' => 'City', 'province_name' => 'Province', 'zip_code' => '12345', 'text' => 'Village, District, City, Province, 12345' ) );
    }

    private function repository(): KiriminajaApiRepository {
        return $this->getMockBuilder( KiriminajaApiRepository::class )->disableOriginalConstructor()->onlyMethods( array( 'sub_district_search' ) )->getMock();
    }

    #[Test]
    public function successful_postcode_lookup_is_shared_between_real_factory_instances(): void {
        $rows = $this->rows();
        $GLOBALS['district_search_cache']['kiriof_district_search_v1_' . md5( '12345' )] = array( (object) array( 'id' => 111, 'text' => 'Stale parent' ) );
        $repository = $this->repository();
        $repository->expects( $this->once() )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => $rows ) ) );
        $first = $this->factory( $repository )->districtSearch( '12345' );
        $second = $this->factory( $repository )->districtSearch( '12345' );
        foreach ( array( $first, $second ) as $response ) {
            $this->assertInstanceOf( ServiceResponse::class, $response );
            $this->assertSame( 200, $response->status );
            $this->assertSame( 'success', $response->message );
            $this->assertSame( $rows, $response->data );
        }
        $this->assertSame( array( array( 'kiriof_district_search_v4_' . md5( '12345' ), $rows, 300 ) ), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    public function failures_are_retried_and_never_cached(): void {
        $repository = $this->repository();
        $repository->expects( $this->exactly( 2 ) )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => false, 'message' => 'Unavailable' ) );
        $factory = $this->factory( $repository );
        $this->assertSame( 400, $factory->districtSearch( '12345' )->status );
        $this->assertSame( 400, $factory->districtSearch( '12345' )->status );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    #[DataProvider( 'invalidCachedRows' )]
    public function unusable_v4_cache_entries_are_not_served( $cached ): void {
        $key = 'kiriof_district_search_v4_' . md5( '12345' );
        $GLOBALS['district_search_cache'][$key] = $cached;
        $rows = $this->rows();
        $repository = $this->repository();
        $repository->expects( $this->once() )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => $rows ) ) );
        $this->assertSame( $rows, $this->factory( $repository )->districtSearch( '12345' )->data );
        $this->assertSame( array( array( $key, $rows, 300 ) ), $GLOBALS['district_search_writes'] );
    }

    public static function invalidCachedRows(): array {
        $row = array( 'id' => 222, 'text' => 'Valid option' );
        return array(
            'empty' => array( array() ),
            'scalar' => array( 'invalid' ),
            'associative' => array( array( 'row' => $row ) ),
            'missing_id' => array( array( array( 'text' => 'Invalid' ) ) ),
            'invalid_id' => array( array( array( 'id' => 0, 'text' => 'Invalid' ) ) ),
            'blank_label' => array( array( array( 'id' => 222, 'text' => ' ' ) ) ),
            'wrong_postcode' => array( array( $row + array( 'zip_code' => '99999' ) ) ),
            'too_many' => array( array_fill( 0, 501, $row ) ),
        );
    }

    #[Test]
    public function valid_array_rows_remain_cache_hits(): void {
        $rows = $this->rows();
        $GLOBALS['district_search_cache']['kiriof_district_search_v4_' . md5( '12345' )] = $rows;
        $repository = $this->repository();
        $repository->expects( $this->never() )->method( 'sub_district_search' );
        $this->assertSame( $rows, $this->factory( $repository )->districtSearch( '12345' )->data );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
        $identity = \KiriminAjaOfficial\Services\AddressHierarchyResolver::resolve( 222, '12345', static function () { throw new \RuntimeException( 'Cache hit must restore identity without network.' ); } );
        $this->assertSame( 12, $identity['district_id'] );
    }

    #[Test]
    public function empty_successful_result_is_not_cached(): void {
        $repository = $this->repository();
        $repository->expects( $this->exactly( 2 ) )->method( 'sub_district_search' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => array() ) ) );
        $factory = $this->factory( $repository );
        $this->assertSame( array(), $factory->districtSearch( '12345' )->data );
        $this->assertSame( array(), $factory->districtSearch( '12345' )->data );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    public function exceptions_do_not_populate_the_cache(): void {
        $repository = $this->repository();
        $repository->expects( $this->exactly( 2 ) )->method( 'sub_district_search' )
            ->willThrowException( new \RuntimeException( 'Unavailable' ) );
        $factory = $this->factory( $repository );
        for ( $i = 0; $i < 2; $i++ ) {
            try {
                $factory->districtSearch( '12345' );
                $this->fail( 'Expected upstream exception.' );
            } catch ( \RuntimeException $exception ) {
                $this->assertSame( 'Unavailable', $exception->getMessage() );
            }
        }
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    public function malformed_success_data_is_not_transformed_or_cached(): void {
        $repository = $this->repository();
        $repository->expects( $this->exactly( 2 ) )->method( 'sub_district_search' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => 'malformed' ) ) );
        $factory = $this->factory( $repository );
        $this->assertSame( 400, $factory->districtSearch( '12345' )->status );
        $this->assertSame( 400, $factory->districtSearch( '12345' )->status );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    #[DataProvider( 'nonPostcodes' )]
    public function non_postcode_searches_remain_live( string $search ): void {
        $repository = $this->repository();
        $repository->expects( $this->exactly( 2 ) )->method( 'sub_district_search' )->with( $search )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => array() ) ) );
        $factory = $this->factory( $repository );
        $factory->districtSearch( $search );
        $factory->districtSearch( $search );
        $this->assertSame( array(), $GLOBALS['district_search_reads'] );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    #[Test]
    public function background_refresh_is_deduplicated_and_revalidates_without_checkout_state(): void {
        $rows = $this->rows();
        $GLOBALS['district_search_cache']['kiriof_district_search_v4_' . md5( '12345' )] = $rows;
        $repository = $this->repository();
        $repository->expects( $this->once() )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => $rows ) ) );
        $factory = $this->factory( $repository );
        $factory->districtSearch( '12345' );
        $factory->districtSearch( '12345' );
        $hook = \KiriminAjaOfficial\Services\AddressHierarchyResolver::REFRESH_HOOK;
        $this->assertSame( array( 12345 ), array_keys( $GLOBALS['hierarchy_events'][ $hook ] ) );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
        $GLOBALS['hierarchy_hooks'][ $hook ]( '12345' );
        $this->assertCount( 1, $GLOBALS['district_search_writes'] );
        $factory->refreshAddressHierarchy( 'PRIVATE' );
        $factory->refreshAddressHierarchy( array( '12345' ) );
    }

    #[Test]
    public function refresh_queue_has_a_global_bound_and_postcode_only_arguments(): void {
        for ( $i = 10000; $i < 10030; ++$i ) {
            \KiriminAjaOfficial\Services\AddressHierarchyResolver::scheduleRefresh( (string) $i );
        }
        \KiriminAjaOfficial\Services\AddressHierarchyResolver::scheduleRefresh( 'Village name' );
        $this->assertCount( 20, $GLOBALS['hierarchy_events'][ \KiriminAjaOfficial\Services\AddressHierarchyResolver::REFRESH_HOOK ] );
    }

    #[Test]
    public function cached_mismatch_is_revalidated_and_request_local_identity_survives_empty_shared_cache(): void {
        $rows = $this->rows();
        \KiriminAjaOfficial\Services\AddressHierarchyResolver::rememberCachedRows( $rows, '12345' );
        $GLOBALS['district_search_cache']['kiriof_address_identity_v1'] = array();
        $identity = \KiriminAjaOfficial\Services\AddressHierarchyResolver::resolve( 222, '12345', static function () { throw new \RuntimeException( 'Unexpected remote lookup.' ); } );
        $this->assertSame( 12, $identity['district_id'] );
        $calls = 0;
        $identity = \KiriminAjaOfficial\Services\AddressHierarchyResolver::resolve( 222, '54321', static function ( $postcode ) use ( &$calls, $rows ) {
            ++$calls;
            $rows[0]['zip_code'] = $postcode;
            $rows[0]['text'] = 'Village, District, City, Province, ' . $postcode;
            \KiriminAjaOfficial\Services\AddressHierarchyResolver::rememberCachedRows( $rows, $postcode );
            return array( 'status' => true );
        } );
        $this->assertSame( 1, $calls );
        $this->assertSame( '54321', $identity['postcode'] );
    }

    #[Test]
    #[DataProvider( 'tamperedHierarchies' )]
    public function inconsistent_complete_cached_rows_never_establish_identities( string $field, $value ): void {
        $rows = $this->rows();
        $rows[0][ $field ] = $value;
        $this->assertFalse( \KiriminAjaOfficial\Services\AddressHierarchyResolver::rememberCachedRows( $rows, '12345' ) );
        $this->assertArrayNotHasKey( 'kiriof_address_identity_v1', $GLOBALS['district_search_cache'] );
    }

    #[Test]
    public function complete_list_is_rejected_atomically_when_parent_ids_conflict(): void {
        $rows = $this->rows();
        $second = $rows[0];
        $second['id'] = $second['subdistrict_id'] = 223;
        $second['province_id'] = 2;
        $rows[] = $second;
        $this->assertFalse( \KiriminAjaOfficial\Services\AddressHierarchyResolver::rememberCachedRows( $rows, '12345' ) );
        $this->assertArrayNotHasKey( 'kiriof_address_identity_v1', $GLOBALS['district_search_cache'] );
    }

    #[Test]
    public function failed_resolution_queues_recovery_and_background_failure_keeps_verified_options(): void {
        $rows = $this->rows();
        $key = 'kiriof_district_search_v4_' . md5( '12345' );
        $GLOBALS['district_search_cache'][ $key ] = $rows;
        $repository = $this->repository();
        $repository->expects( $this->once() )->method( 'sub_district_search' )->with( '12345' )
            ->willReturn( array( 'status' => false ) );
        $factory = $this->factory( $repository );
        $this->assertNull( \KiriminAjaOfficial\Services\AddressHierarchyResolver::resolve( 222, '12345', static fn() => array( 'status' => false ) ) );
        $this->assertCount( 1, $GLOBALS['hierarchy_events'][ \KiriminAjaOfficial\Services\AddressHierarchyResolver::REFRESH_HOOK ] );
        $factory->refreshAddressHierarchy( '12345' );
        $this->assertSame( $rows, $GLOBALS['district_search_cache'][ $key ] );
        $this->assertSame( array(), $GLOBALS['district_search_writes'] );
    }

    public static function tamperedHierarchies(): array {
        return array( array( 'district_id', 0 ), array( 'id', 223 ), array( 'text', 'Other, District, City, Province, 12345' ), array( 'zip_code', '54321' ), array( 'kecamatan_id', 99 ), array( 'district_name', ' ' ), array( 'province_id', '1' ) );
    }

    public static function nonPostcodes(): array {
        return array_map( static fn( $search ) => array( $search ), array( 'Jakarta', '1234', '123456', '12 345', ' 12345', "12345\n", '１２３４５' ) );
    }
}
