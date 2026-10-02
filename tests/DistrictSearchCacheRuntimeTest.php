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
        // These stubs exist only in this test's isolated process.
        function get_transient( $key ) {
            $GLOBALS['district_search_reads'][] = $key;
            return $GLOBALS['district_search_cache'][$key] ?? false;
        }
        function set_transient( $key, $value, $ttl ) {
            $GLOBALS['district_search_writes'][] = array( $key, $value, $ttl );
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

    private function repository(): KiriminajaApiRepository {
        return $this->getMockBuilder( KiriminajaApiRepository::class )->disableOriginalConstructor()->onlyMethods( array( 'sub_district_search' ) )->getMock();
    }

    #[Test]
    public function successful_postcode_lookup_is_shared_between_real_factory_instances(): void {
        $rows = array( (object) array( 'id' => 222, 'text' => 'New district' ) );
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
        $this->assertSame( array( array( 'kiriof_district_search_v3_' . md5( '12345' ), $rows, 300 ) ), $GLOBALS['district_search_writes'] );
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
    public function empty_successful_result_is_a_cache_hit(): void {
        $repository = $this->repository();
        $repository->expects( $this->once() )->method( 'sub_district_search' )
            ->willReturn( array( 'status' => true, 'data' => (object) array( 'result' => array() ) ) );
        $factory = $this->factory( $repository );
        $this->assertSame( array(), $factory->districtSearch( '12345' )->data );
        $this->assertSame( array(), $factory->districtSearch( '12345' )->data );
        $this->assertCount( 1, $GLOBALS['district_search_writes'] );
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

    public static function nonPostcodes(): array {
        return array_map( static fn( $search ) => array( $search ), array( 'Jakarta', '1234', '123456', '12 345', ' 12345', "12345\n", '１２３４５' ) );
    }
}
