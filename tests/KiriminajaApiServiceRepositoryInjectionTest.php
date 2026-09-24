<?php

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
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
		unset( $key );
		return false;
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
		$repository = $this->createMock( KiriminajaApiRepository::class );
		$repository->expects( $this->once() )
			->method( 'getProfile' )
			->willReturn(
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
		$repository = $this->createMock( KiriminajaApiRepository::class );
		$repository->expects( $this->once() )
			->method( 'sub_district_search' )
			->with( 'Sleman' )
			->willReturn(
				array(
					'status' => true,
					'data'   => (object) array(
						'status' => true,
						'result' => array( 'district' ),
					),
				)
			);
		$repository->expects( $this->once() )
			->method( 'getPayment' )
			->with( array( 'payment_id' => 'pay-123' ) )
			->willReturn(
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

	#[Test]
	public function service_has_no_repository_construction_outside_the_default_constructor(): void {
		$content = file_get_contents( PLUGIN_DIR . '/inc/Services/KiriminajaApiService.php' );

		$this->assertSame( 1, substr_count( $content, 'new KiriminajaApiRepository()' ) );
		$this->assertStringNotContainsString(
			'new \\KiriminAjaOfficial\\Repositories\\KiriminajaApiRepository',
			$content
		);
		$this->assertSame( 7, substr_count( $content, '$this->repository->' ) );
	}
}
