<?php

use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;
use KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface;
use KiriminAjaOfficial\Controllers\SettingController;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use KiriminAjaOfficial\Services\OnboardingSetupStateService;
use KiriminAjaOfficial\Services\WooCommerceShippingMethodRegistrationService;
use KiriminAjaOfficial\Utils\ServiceResponse;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

final class RepositoryCompositionProgressTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private function service( SettingRepository $settings, KiriminajaApiService $api ): OnboardingSetupStateService {
		return new OnboardingSetupStateService(
			Mockery::mock( ProductVolumetricReadinessRepositoryInterface::class ),
			$settings,
			Mockery::mock( WooCommerceShippingMethodRegistrationService::class ),
			$api
		);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_settings_controller_builds_origin_fields_from_the_injected_repository(): void {
		require_once __DIR__ . '/helpers/settings-controller-wordpress.php';
		$settings = Mockery::mock( SettingRepository::class );
		$settings->shouldReceive( 'getSettingByArray' )->twice()->with( array(
			'origin_name', 'origin_phone', 'origin_address', 'origin_latitude',
			'origin_longitude', 'origin_sub_district_id', 'origin_sub_district_name', 'origin_zip_code',
		) )->andReturn( array(
			(object) array( 'key' => 'origin_name', 'value' => ' Warehouse ' ),
			(object) array( 'key' => 'origin_phone', 'value' => '08123456789' ),
			(object) array( 'key' => 'origin_sub_district_id', 'value' => '123' ),
			(object) array( 'key' => 'origin_sub_district_name', 'value' => 'District' ),
		) );
		$controller = new SettingController( Mockery::mock( TrackingPageRepositoryInterface::class ), $settings );
		$input = array( array( 'id' => 'woocommerce_store_address' ), array( 'id' => 'woocommerce_default_country' ) );
		$fields = array_column( $controller->injectWooCommerceGeneralSettings( $input ), null, 'id' );

		$this->assertSame( array( 'Warehouse', '08123456789', '123', 'District' ), array(
			$fields['kiriof_wc_origin_name']['default'],
			$fields['kiriof_wc_origin_phone']['default'],
			$fields['kiriof_wc_origin_area']['default'],
			$fields['kiriof_wc_origin_area']['origin_area_name'],
		) );
		$this->assertSame( array_values( $fields ), $controller->injectWooCommerceGeneralSettings( $input ) );
	}

	public function test_origin_and_integration_values_use_the_injected_repository(): void {
		$settings = Mockery::mock( SettingRepository::class );
		$settings->shouldReceive( 'getSettingByArray' )->once()->with( array(
			'origin_name', 'origin_phone', 'origin_address', 'origin_latitude',
			'origin_longitude', 'origin_sub_district_id', 'origin_sub_district_name', 'origin_zip_code',
		) )->andReturn( array( (object) array( 'key' => 'origin_name', 'value' => 'Warehouse' ) ) );
		$settings->shouldReceive( 'getIntegrationData' )->once()->withNoArgs()->andReturn( array(
			(object) array( 'key' => 'setup_key', 'value' => 'setup-1' ),
		) );
		$service = $this->service( $settings, Mockery::mock( KiriminajaApiService::class ) );

		$this->assertSame( array( 'origin_name' => 'Warehouse' ), $service->get_origin_values() );
		$this->assertSame( array( 'setup_key' => 'setup-1' ), $service->get_integration_values() );
	}

	public static function connection_cases(): iterable {
		yield 'connected' => array( 'setup-1', 'api-1', 'profile', true, false );
		yield 'missing setup key' => array( '', 'api-1', 'unused', false, false );
		yield 'missing API key' => array( 'setup-1', '', 'unused', false, false );
		yield 'upstream error' => array( 'setup-1', 'api-1', 'error', true, true );
		yield 'empty profile' => array( 'setup-1', 'api-1', 'empty', true, true );
		yield 'exception' => array( 'setup-1', 'api-1', 'exception', true, true );
	}

	#[DataProvider( 'connection_cases' )]
	public function test_connection_state_uses_injected_api_only_when_credentials_are_complete( string $setup, string $token, string $result, bool $connected, bool $error ): void {
		$settings = Mockery::mock( SettingRepository::class );
		$settings->shouldReceive( 'getSettingByKey' )->once()->with( 'setup_key' )->andReturn( (object) array( 'value' => $setup ) );
		$settings->shouldReceive( 'getSettingByKey' )->once()->with( 'api_key' )->andReturn( (object) array( 'value' => $token ) );
		$api = Mockery::mock( KiriminajaApiService::class );
		$profile = (object) array( 'name' => 'Merchant' );
		if ( ! $connected ) {
			$api->shouldReceive( 'getProfile' )->never();
		} elseif ( 'exception' === $result ) {
			$api->shouldReceive( 'getProfile' )->once()->withNoArgs()->andThrow( new RuntimeException( 'Unavailable' ) );
		} else {
			$api->shouldReceive( 'getProfile' )->once()->withNoArgs()->andReturn( new ServiceResponse( 'profile' === $result ? $profile : array(), '', 'error' === $result ? 503 : 200 ) );
		}

		$this->assertSame( array(
			'is_connected' => $connected,
			'profile'      => 'profile' === $result ? $profile : null,
			'profile_err'  => $error,
		), $this->service( $settings, $api )->get_connection_state() );
	}
}
