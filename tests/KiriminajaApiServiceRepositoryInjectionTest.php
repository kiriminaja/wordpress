<?php

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		return $GLOBALS['kiriof_settings_page_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $expiration ): bool {
		unset( $key, $value, $expiration );
		return true;
	}
}

require_once PLUGIN_DIR . '/vendor/autoload.php';
require_once PLUGIN_DIR . '/inc/Utils/ServiceResponse.php';
require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php';
require_once PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php';
require_once PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php';

final class KiriminajaApiServiceRepositoryInjectionTest extends TestCase {

	use MockeryPHPUnitIntegration;

	#[Test]
	public function constructor_keeps_no_argument_compatibility(): void {
		$constructor = ( new ReflectionClass( KiriminajaApiService::class ) )->getConstructor();

		$this->assertNotNull( $constructor );
		$this->assertSame( 0, $constructor->getNumberOfRequiredParameters() );
		$this->assertTrue( $constructor->getParameters()[0]->isDefaultValueAvailable() );
		$this->assertNull( $constructor->getParameters()[0]->getDefaultValue() );
	}

	#[Test]
	public function profile_service_accepts_the_normalized_repository_profile(): void {
		$profile = (object) array( 'name' => 'Merchant', 'email' => 'merchant@example.com' );
		$repository = Mockery::mock( KiriminajaApiRepository::class );
		$repository->shouldReceive( 'getProfile' )->once()->withNoArgs()->andReturn(
			array(
				'status' => true,
				'data'   => (object) array( 'status' => true, 'results' => $profile ),
			)
		);

		$result = ( new KiriminajaApiService( $repository ) )->getProfile();

		$this->assertSame( 200, $result->status );
		$this->assertSame( 'Merchant', $result->data->name );
	}

	#[Test]
	public function injected_repository_is_reused_for_api_calls(): void {
		$repository = Mockery::mock( KiriminajaApiRepository::class );
		$repository->shouldReceive( 'sub_district_search' )->once()->with( 'Sleman' )->andReturn(
			array(
				'status' => true,
				'data'   => (object) array(
					'status' => true,
					'result' => array( 'district' ),
				),
			)
		);
		$repository->shouldReceive( 'getPayment' )->once()->with( array( 'payment_id' => 'pay-123' ) )->andReturn(
			array(
				'status' => true,
				'data'   => (object) array(
					'status' => true,
					'data'   => (object) array( 'id' => 'pay-123' ),
				),
			)
		);

		$service = new KiriminajaApiService( $repository );

		$this->assertSame( array( 'district' ), $service->sub_district_search( 'Sleman' )->data );
		$this->assertSame( 'pay-123', $service->getPayment( 'pay-123' )->data->id );
	}

	public static function invalid_address_responses(): iterable {
		yield 'transport failure' => array( array( 'status' => false, 'data' => 'private-token' ) );
		yield 'negative acknowledgement' => array( array( 'status' => true, 'data' => (object) array( 'status' => false ) ) );
		yield 'malformed results' => array( array( 'status' => true, 'data' => (object) array( 'result' => 'private-token' ) ) );
	}

	#[DataProvider( 'invalid_address_responses' )]
	public function test_failed_address_lookup_returns_fixed_error_from_the_injected_repository( array $response ): void {
		$repository = Mockery::mock( KiriminajaApiRepository::class );
		$repository->shouldReceive( 'sub_district_search' )->once()->with( 'Sleman' )->andReturn( $response );
		$result = ( new KiriminajaApiService( $repository ) )->sub_district_search( 'Sleman' );

		$this->assertSame( array( 400, array(), 'Could not load subdistricts.' ), array( $result->status, $result->data, $result->message ) );
	}
}
