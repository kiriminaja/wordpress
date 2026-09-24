<?php

use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;
use KiriminAjaOfficial\Repositories\ProductVolumetricReadinessRepository;
use KiriminAjaOfficial\Services\OnboardingSetupStateService;
use KiriminAjaOfficial\Services\ProductVolumetricReadinessService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

require_once PLUGIN_DIR . '/inc/Contracts/ProductVolumetricReadinessRepositoryInterface.php';
require_once PLUGIN_DIR . '/inc/Repositories/ProductVolumetricReadinessRepository.php';
require_once PLUGIN_DIR . '/inc/Services/ProductVolumetricReadinessService.php';
require_once PLUGIN_DIR . '/inc/Services/OnboardingSetupStateService.php';

final class ProductVolumetricReadinessRepositoryRuntimeTest extends TestCase {
	private $previous_wpdb;

	protected function setUp(): void {
		global $wpdb;
		$this->previous_wpdb = $wpdb ?? null;
	}

	#[Test]
	public function renderer_service_delegates_to_the_readiness_contract(): void {
		$repository = new ProductVolumetricReadinessRepositoryFake( false );
		$service    = new ProductVolumetricReadinessService( $repository );

		$this->assertSame(
			array(
				'total'      => 1,
				'configured' => 0,
				'ready'      => false,
			),
			$service->getReadiness()
		);
		$this->assertSame( 1, $repository->calls );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->previous_wpdb;
	}

	#[Test]
	public function repository_returns_total_configured_and_ready(): void {
		global $wpdb;
		$wpdb = new ProductVolumetricReadinessWpdbFake( array( 3, 3 ) );

		$result = ( new ProductVolumetricReadinessRepository() )->getReadiness();

		$this->assertSame(
			array(
				'total'      => 3,
				'configured' => 3,
				'ready'      => true,
			),
			$result
		);
		$this->assertCount( 2, $wpdb->queries );
	}

	#[Test]
	public function query_preserves_variation_parent_fallback_and_virtual_product_semantics(): void {
		global $wpdb;
		$wpdb = new ProductVolumetricReadinessWpdbFake( array( 4, 2 ) );

		$result = ( new ProductVolumetricReadinessRepository() )->getReadiness();
		$sql    = implode( "\n", $wpdb->queries );

		$this->assertFalse( $result['ready'] );
		$this->assertStringContainsString( "p.post_type = 'product_variation' AND p.post_status IN ('publish','private')", $sql );
		$this->assertStringContainsString( "p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL", $sql );
		$this->assertStringContainsString( "COALESCE(NULLIF(virtual_meta.meta_value, ''), parent_virtual_meta.meta_value, 'no') <> 'yes'", $sql );
		foreach ( array( 'weight', 'length', 'width', 'height' ) as $dimension ) {
			$this->assertStringContainsString( "COALESCE(NULLIF({$dimension}_meta.meta_value, ''), parent_{$dimension}_meta.meta_value, '0')", $sql );
		}
	}

	#[Test]
	public function onboarding_service_accepts_the_contract_without_breaking_default_callers(): void {
		$repository = new ProductVolumetricReadinessRepositoryFake( true );
		$service    = new OnboardingSetupStateService( $repository );
		$method     = new ReflectionMethod( $service, 'are_products_ready' );

		$this->assertTrue( $method->invoke( $service ) );
		$this->assertSame( 1, $repository->calls );

		$constructor = ( new ReflectionClass( OnboardingSetupStateService::class ) )->getConstructor();
		$this->assertTrue( $constructor->getParameters()[0]->allowsNull() );
		$this->assertTrue( $constructor->getParameters()[0]->isDefaultValueAvailable() );
	}

	#[Test]
	public function setup_template_consumes_renderer_supplied_readiness_without_database_queries(): void {
		$template = file_get_contents( PLUGIN_DIR . '/templates/setting/setuped/index.php' );
		$renderer = file_get_contents( PLUGIN_DIR . '/templates/setting/index.php' );
		$provider = file_get_contents( PLUGIN_DIR . '/inc/Services/SettingsPageData.php' );

		$this->assertStringContainsString( '$productVolumetricReadiness[\'total\']', $template );
		$this->assertStringNotContainsString( 'global $wpdb', $template );
		$this->assertStringNotContainsString( 'ProductVolumetricReadinessRepository', $template );
		$this->assertStringContainsString( 'SettingsPageData', $renderer );
		$this->assertStringContainsString( 'ProductVolumetricReadinessService', $provider );
		$this->assertStringNotContainsString( 'ProductVolumetricReadinessRepository', $renderer );
		$this->assertStringContainsString( '->getReadiness()', $provider );
	}
}

final class ProductVolumetricReadinessWpdbFake {
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';
	public array $queries = array();
	private array $results;

	public function __construct( array $results ) {
		$this->results = $results;
	}

	public function get_var( $query ) {
		$this->queries[] = $query;

		return array_shift( $this->results );
	}
}

final class ProductVolumetricReadinessRepositoryFake implements ProductVolumetricReadinessRepositoryInterface {
	public int $calls = 0;
	private bool $ready;

	public function __construct( bool $ready ) {
		$this->ready = $ready;
	}

	public function getReadiness(): array {
		++$this->calls;

		return array(
			'total'      => 1,
			'configured' => $this->ready ? 1 : 0,
			'ready'      => $this->ready,
		);
	}
}
