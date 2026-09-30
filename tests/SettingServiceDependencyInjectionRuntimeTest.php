<?php

use KiriminAjaOfficial\Repositories\KiriminajaApiRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\SettingService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

require_once PLUGIN_DIR . '/inc/Utils/ServiceResponse.php';
require_once PLUGIN_DIR . '/inc/Base/BaseService.php';
require_once PLUGIN_DIR . '/inc/Base/KiriminAjaApi.php';
require_once PLUGIN_DIR . '/inc/Repositories/SettingRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/KiriminajaApiRepository.php';
require_once PLUGIN_DIR . '/inc/Services/SettingService.php';

final class SettingServiceDependencyInjectionRuntimeTest extends TestCase {
	#[Test]
	public function constructor_reuses_injected_repositories_across_service_calls(): void {
		$settings = $this->createMock( SettingRepository::class );
		$settings->expects( $this->once() )
			->method( 'getIntegrationData' )
			->willReturn( array( (object) array( 'key' => 'setup_key', 'value' => ' setup-123 ' ) ) );
		$settings->expects( $this->once() )
			->method( 'getCallbackData' )
			->willReturn( array( (object) array( 'key' => 'callback_url', 'value' => ' https://example.test/callback ' ) ) );
		$settings->expects( $this->once() )
			->method( 'getSettingByKey' )
			->with( 'is_top' )
			->willReturn( (object) array( 'value' => 'yes' ) );
		$api = $this->createMock( KiriminajaApiRepository::class );

		$service = new SettingService( $settings, $api );

		$this->assertSame( array( 'setup_key' => 'setup-123' ), $service->getIntegrationData()->data );
		$this->assertSame( array( 'callback_url' => 'https://example.test/callback' ), $service->getCallbackData()->data );
		$this->assertTrue( $service->isTopPaymentMethod() );

		$reflection = new ReflectionClass( $service );
		$this->assertSame( $settings, $reflection->getProperty( 'setting_repository' )->getValue( $service ) );
		$this->assertSame( $api, $reflection->getProperty( 'api_repository' )->getValue( $service ) );
	}

	#[Test]
	public function constructor_preserves_no_argument_compatibility_and_only_constructs_defaults_once(): void {
		$constructor = ( new ReflectionClass( SettingService::class ) )->getConstructor();
		$parameters  = $constructor->getParameters();

		$this->assertCount( 2, $parameters );
		foreach ( $parameters as $parameter ) {
			$this->assertTrue( $parameter->isOptional() );
			$this->assertTrue( $parameter->allowsNull() );
			$this->assertNull( $parameter->getDefaultValue() );
		}

		$source = file_get_contents( PLUGIN_DIR . '/inc/Services/SettingService.php' );
		$this->assertSame( 1, substr_count( $source, 'new SettingRepository()' ) );
		$this->assertSame( 1, substr_count( $source, 'new KiriminajaApiRepository()' ) );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\SettingRepository', $source );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\KiriminajaApiRepository', $source );
	}
}
