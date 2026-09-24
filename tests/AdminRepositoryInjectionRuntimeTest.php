<?php

use KiriminAjaOfficial\Base\BaseInit;
use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;
use KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface;
use KiriminAjaOfficial\Pages\Admin;
use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Services\WooCommerceShippingMethodRegistrationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $path ) {
		return rtrim( dirname( $path ), '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $path ) {
		return 'https://example.test/plugins/' . basename( dirname( $path ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $path ) {
		return basename( dirname( $path ) ) . '/' . basename( $path );
	}
}

require_once PLUGIN_DIR . '/inc/Base/BaseInit.php';
require_once PLUGIN_DIR . '/inc/Contracts/ProductVolumetricReadinessRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Contracts/TrackingPageRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/SettingRepository.php';
require_once PLUGIN_DIR . '/inc/Services/WooCommerceShippingMethodRegistrationService.php';
require_once PLUGIN_DIR . '/inc/Pages/Admin.php';

final class AdminRepositoryInjectionRuntimeTest extends TestCase {
	#[Test]
	public function constructor_accepts_readiness_dependencies_and_runs_parent_constructor(): void {
		$admin = new Admin(
			new AdminProductReadinessRepositoryFake(),
			new AdminTrackingPageRepositoryFake(),
			$this->getMockBuilder( SettingRepository::class )->disableOriginalConstructor()->getMock(),
			$this->getMockBuilder( WooCommerceShippingMethodRegistrationService::class )->disableOriginalConstructor()->getMock()
		);

		$this->assertInstanceOf( BaseInit::class, $admin );
		$this->assertNotSame( '', $admin->plugin_path );
		$this->assertNotSame( '', $admin->plugin_url );
		$this->assertNotSame( '', $admin->plugin );
	}

	#[Test]
	public function admin_contains_no_database_access_or_consumer_side_dependency_construction(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Pages/Admin.php' );

		$this->assertStringNotContainsString( 'global $wpdb', $source );
		$this->assertStringNotContainsString( '$wpdb->', $source );
		$this->assertStringNotContainsString( 'SELECT ', $source );
		$this->assertStringContainsString( '$this->product_readiness_repository->getReadiness()', $source );
		$this->assertStringContainsString( '$this->tracking_page_repository->hasPublishedTrackingPage()', $source );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Repositories\\SettingRepository', $source );
		$this->assertStringNotContainsString( 'new \\KiriminAjaOfficial\\Services\\WooCommerceShippingMethodRegistrationService', $source );
	}
}

final class AdminProductReadinessRepositoryFake implements ProductVolumetricReadinessRepositoryInterface {
	public function getReadiness(): array {
		return array( 'total' => 0, 'configured' => 0, 'ready' => true );
	}
}

final class AdminTrackingPageRepositoryFake implements TrackingPageRepositoryInterface {
	public function hasPublishedTrackingPage(): bool { return false; }
	public function findPublishedTrackingContent(): array { return array(); }
	public function findTrackingShortcodePages(): array { return array(); }
	public function findPreferredTrackingShortcodePage() { return null; }
}
