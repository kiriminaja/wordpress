<?php

declare(strict_types=1);

use KiriminAjaOfficial\Controllers\CheckoutController;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\WpPostMetaRepository;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Repositories/SettingRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/WpPostMetaRepository.php';
require_once PLUGIN_DIR . '/inc/Services/CheckoutServiceFactory.php';
require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

final class CheckoutControllerDependenciesTest extends TestCase {

	use MockeryPHPUnitIntegration;

	#[Test]
	public function constructor_requires_composed_dependencies(): void {
		$constructor = new ReflectionMethod( CheckoutController::class, '__construct' );

		$this->assertSame( 4, $constructor->getNumberOfRequiredParameters() );
	}

	#[Test]
	public function constructor_reuses_injected_dependencies(): void {
		$setting_repository = Mockery::mock( SettingRepository::class );
		$transaction_repository = Mockery::mock( TransactionRepository::class );
		$wp_post_meta_repository = Mockery::mock( WpPostMetaRepository::class );
		$factory = Mockery::mock( CheckoutServiceFactory::class );

		$controller = new CheckoutController(
			$setting_repository,
			$transaction_repository,
			$wp_post_meta_repository,
			$factory
		);
		$reflection = new ReflectionClass( $controller );

		foreach ( array(
			'setting_repository'       => $setting_repository,
			'transaction_repository'   => $transaction_repository,
			'wp_post_meta_repository'  => $wp_post_meta_repository,
			'checkout_service_factory' => $factory,
		) as $property_name => $dependency ) {
			$property = $reflection->getProperty( $property_name );
			$this->assertSame( $dependency, $property->getValue( $controller ) );
		}
	}

	public static function fee_names(): iterable {
		yield 'COD' => array( 'COD Fee', 'cod_fee' );
		yield 'translated COD' => array( 'Biaya COD', 'cod_fee' );
		yield 'insurance' => array( 'Insurance', 'insurance' );
		yield 'translated insurance' => array( 'Asuransi', 'insurance' );
		yield 'unrelated fee' => array( 'Handling', null );
	}

	#[DataProvider( 'fee_names' )]
	public function test_fee_tagging_writes_only_recognized_fee_metadata( string $name, ?string $type ): void {
		$controller = new CheckoutController(
			Mockery::mock( SettingRepository::class ),
			Mockery::mock( TransactionRepository::class ),
			Mockery::mock( WpPostMetaRepository::class ),
			Mockery::mock( CheckoutServiceFactory::class )
		);
		$fee = Mockery::mock();
		$fee->shouldReceive( 'get_name' )->once()->withNoArgs()->andReturn( $name );
		if ( null === $type ) {
			$fee->shouldReceive( 'add_meta_data' )->never();
		} else {
			$fee->shouldReceive( 'add_meta_data' )->once()->with( '_kiriof_fee_type', $type, true );
		}

		$controller->kiriof_tag_fee_item_meta( $fee, '', null, array() );
	}
}
