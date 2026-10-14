<?php

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\SettingService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

final class SettingServiceDependencyInjectionRuntimeTest extends TestCase {

	use MockeryPHPUnitIntegration;

	public function test_injected_repository_serves_multiple_calls(): void {
		$settings = Mockery::mock( SettingRepository::class );
		$settings->shouldReceive( 'getIntegrationData' )->once()->withNoArgs()->andReturn( array( (object) array( 'key' => 'setup_key', 'value' => ' setup-123 ' ) ) );
		$settings->shouldReceive( 'getCallbackData' )->once()->withNoArgs()->andReturn( array( (object) array( 'key' => 'callback_url', 'value' => ' https://example.test/callback ' ) ) );
		$settings->shouldReceive( 'getSettingByKey' )->once()->with( 'is_top' )->andReturn( (object) array( 'value' => 'yes' ) );
		$service = new SettingService( $settings, Mockery::mock( KiriminajaApiRepository::class ) );

		$this->assertSame( array( 'setup_key' => 'setup-123' ), $service->getIntegrationData()->data );
		$this->assertSame( array( 'callback_url' => 'https://example.test/callback' ), $service->getCallbackData()->data );
		$this->assertTrue( $service->isTopPaymentMethod() );
	}

	public static function failed_reads(): iterable {
		foreach ( array( 'getIntegrationData', 'getCallbackData', 'getOriginData' ) as $method ) {
			yield $method . ' empty' => array( $method, false );
			yield $method . ' exception' => array( $method, true );
		}
	}

	#[DataProvider( 'failed_reads' )]
	public function test_failed_repository_reads_return_service_errors( string $method, bool $throws ): void {
		$settings = Mockery::mock( SettingRepository::class );
		$read = $settings->shouldReceive( $method )->once()->withNoArgs();
		if ( $throws ) {
			$read->andThrow( new RuntimeException( 'Read failed' ) );
		} else {
			$read->andReturn( false );
		}
		$service = new SettingService( $settings, Mockery::mock( KiriminajaApiRepository::class ) );
		$response = $service->{$method}();

		$this->assertSame( array( 400, array(), $throws ? 'Read failed' : 'Server Error' ), array( $response->status, $response->data, $response->message ) );
	}

	public function test_constructor_preserves_no_argument_compatibility(): void {
		$constructor = new ReflectionMethod( SettingService::class, '__construct' );
		$this->assertSame( 0, $constructor->getNumberOfRequiredParameters() );
	}
}
