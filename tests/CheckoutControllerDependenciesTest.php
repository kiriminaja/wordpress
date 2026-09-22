<?php

declare(strict_types=1);

use KiriminAjaOfficial\Controllers\CheckoutController;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\WpPostMetaRepository;
use KiriminAjaOfficial\Services\CheckoutServiceFactory;
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
	#[Test]
	public function constructor_requires_composed_dependencies(): void {
		$constructor = new ReflectionMethod( CheckoutController::class, '__construct' );

		$this->assertSame( 4, $constructor->getNumberOfRequiredParameters() );
	}

	#[Test]
	public function constructor_reuses_injected_dependencies(): void {
		$setting_repository = $this->getMockBuilder( SettingRepository::class )
			->disableOriginalConstructor()
			->getMock();
		$transaction_repository = $this->getMockBuilder( TransactionRepository::class )
			->disableOriginalConstructor()
			->getMock();
		$wp_post_meta_repository = $this->getMockBuilder( WpPostMetaRepository::class )
			->disableOriginalConstructor()
			->getMock();
		$factory = $this->getMockBuilder( CheckoutServiceFactory::class )
			->disableOriginalConstructor()
			->getMock();

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

	#[Test]
	public function controller_contains_no_repository_construction(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );

		$this->assertIsString( $source );
		$this->assertStringNotContainsString( 'new SettingRepository()', $source );
		$this->assertStringNotContainsString( 'new TransactionRepository()', $source );
		$this->assertStringNotContainsString( 'new WpPostMetaRepository()', $source );
	}
}
