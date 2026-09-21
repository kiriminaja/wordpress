<?php

declare(strict_types=1);

use KiriminAjaOfficial\Controllers\CheckoutController;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\TransactionRepository;
use KiriminAjaOfficial\Repositories\WpPostMetaRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/TransactionPrintRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/SettingRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php';
require_once PLUGIN_DIR . '/inc/Repositories/WpPostMetaRepository.php';
require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

final class CheckoutControllerDependenciesTest extends TestCase {
	#[Test]
	public function constructor_remains_callable_without_arguments(): void {
		$previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_' );

		try {
			$controller = new CheckoutController();

			$this->assertInstanceOf( CheckoutController::class, $controller );
			$this->assertSame( 0, ( new ReflectionMethod( $controller, '__construct' ) )->getNumberOfRequiredParameters() );
		} finally {
			if ( null === $previous_wpdb ) {
				unset( $GLOBALS['wpdb'] );
			} else {
				$GLOBALS['wpdb'] = $previous_wpdb;
			}
		}
	}

	#[Test]
	public function constructor_reuses_injected_repository_instances(): void {
		$setting_repository = $this->getMockBuilder( SettingRepository::class )
			->disableOriginalConstructor()
			->getMock();
		$transaction_repository = $this->getMockBuilder( TransactionRepository::class )
			->disableOriginalConstructor()
			->getMock();
		$wp_post_meta_repository = $this->getMockBuilder( WpPostMetaRepository::class )
			->disableOriginalConstructor()
			->getMock();

		$controller = new CheckoutController(
			$setting_repository,
			$transaction_repository,
			$wp_post_meta_repository
		);
		$reflection = new ReflectionClass( $controller );

		foreach ( array(
			'setting_repository'      => $setting_repository,
			'transaction_repository'  => $transaction_repository,
			'wp_post_meta_repository' => $wp_post_meta_repository,
		) as $property_name => $repository ) {
			$property = $reflection->getProperty( $property_name );
			$this->assertSame( $repository, $property->getValue( $controller ) );
		}
	}

	#[Test]
	public function repository_construction_is_confined_to_optional_constructor_defaults(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/CheckoutController.php' );

		$this->assertIsString( $source );
		$this->assertStringContainsString( '?SettingRepository $setting_repository = null', $source );
		$this->assertStringContainsString( '?TransactionRepository $transaction_repository = null', $source );
		$this->assertStringContainsString( '?WpPostMetaRepository $wp_post_meta_repository = null', $source );
		$this->assertSame( 1, substr_count( $source, 'new SettingRepository()' ) );
		$this->assertSame( 1, substr_count( $source, 'new TransactionRepository()' ) );
		$this->assertSame( 1, substr_count( $source, 'new WpPostMetaRepository()' ) );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\SettingRepository', $source );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\TransactionRepository', $source );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\WpPostMetaRepository', $source );
	}
}
