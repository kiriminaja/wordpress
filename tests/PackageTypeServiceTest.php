<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', PLUGIN_DIR . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use KiriminAjaOfficial\Services\PackageTypeService;
use KiriminAjaOfficial\Controllers\ProductController;

final class PackageTypeServiceTest extends TestCase {

	#[Test]
	public function provides_all_ten_package_categories_with_expected_metadata(): void {
		$all = PackageTypeService::all();
		$this->assertCount( 10, $all );

		$expected = array(
			1  => array( 'name' => 'Peralatan Elektronik & Gadget', 'insurance' => 1, 'is_fragile' => 0 ),
			2  => array( 'name' => 'Pakaian', 'insurance' => 0, 'is_fragile' => 0 ),
			3  => array( 'name' => 'Pecah Belah', 'insurance' => 1, 'is_fragile' => 0 ),
			4  => array( 'name' => 'Dokumen', 'insurance' => 1, 'is_fragile' => 0 ),
			5  => array( 'name' => 'Peralatan Rumah Tangga', 'insurance' => 0, 'is_fragile' => 0 ),
			6  => array( 'name' => 'Aksesoris', 'insurance' => 0, 'is_fragile' => 0 ),
			7  => array( 'name' => 'Lain-lain', 'insurance' => 0, 'is_fragile' => 0 ),
			8  => array( 'name' => 'Dokumen Berharga', 'insurance' => 1, 'is_fragile' => 0 ),
			9  => array( 'name' => 'Peralatan Kesehatan & Kecantikan', 'insurance' => 0, 'is_fragile' => 0 ),
			10 => array( 'name' => 'Peralatan Olahraga & Hiburan', 'insurance' => 0, 'is_fragile' => 0 ),
		);

		foreach ( $expected as $id => $meta ) {
			$this->assertArrayHasKey( $id, $all );
			$this->assertSame( $meta['name'], $all[ $id ]['name'] );
			$this->assertSame( $meta['insurance'], $all[ $id ]['insurance'] );
			$this->assertSame( $meta['is_fragile'], $all[ $id ]['is_fragile'] );
			$this->assertNotEmpty( $all[ $id ]['created_at'] );
			$this->assertNotEmpty( $all[ $id ]['updated_at'] );
		}

		$this->assertSame( 7, PackageTypeService::DEFAULT_PACKAGE_TYPE_ID );
		$this->assertSame( 'Lain-lain', PackageTypeService::DEFAULT_PACKAGE_TYPE_NAME );
		$this->assertSame( '_kiriof_package_type_id', PackageTypeService::META_KEY );
	}

	#[Test]
	public function validates_valid_and_invalid_category_ids(): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->assertTrue( PackageTypeService::isValidId( $i ) );
			$this->assertNotNull( PackageTypeService::findById( $i ) );
		}

		$this->assertFalse( PackageTypeService::isValidId( 0 ) );
		$this->assertFalse( PackageTypeService::isValidId( 11 ) );
		$this->assertFalse( PackageTypeService::isValidId( -1 ) );
		$this->assertNull( PackageTypeService::findById( 999 ) );
	}

	#[Test]
	public function select_options_include_nullable_default_choice_and_all_categories(): void {
		$options = PackageTypeService::getSelectOptions();

		$this->assertArrayHasKey( '', $options );
		$this->assertStringContainsString( 'Lain-lain', $options[''] );
		$this->assertStringContainsString( 'Default', $options[''] );

		for ( $i = 1; $i <= 10; $i++ ) {
			$this->assertArrayHasKey( (string) $i, $options );
		}
	}

	#[Test]
	public function resolves_category_for_product_with_default_fallback(): void {
		// Mock product with get_meta returning null/empty
		$productNoMeta = new class {
			public function get_meta( $key, $single = true ) { return ''; }
			public function get_id() { return 101; }
			public function is_virtual() { return false; }
		};
		$this->assertNull( PackageTypeService::getRawProductPackageTypeId( $productNoMeta ) );
		$this->assertSame( 7, PackageTypeService::resolveForProduct( $productNoMeta ) );

		// Mock product with explicit category
		$productWithMeta = new class {
			public function get_meta( $key, $single = true ) { return 3; }
			public function get_id() { return 102; }
			public function is_virtual() { return false; }
		};
		$this->assertSame( 3, PackageTypeService::getRawProductPackageTypeId( $productWithMeta ) );
		$this->assertSame( 3, PackageTypeService::resolveForProduct( $productWithMeta ) );

		// Mock product with invalid category ID falls back to default 7
		$productWithInvalidMeta = new class {
			public function get_meta( $key, $single = true ) { return 99; }
			public function get_id() { return 103; }
			public function is_virtual() { return false; }
		};
		$this->assertNull( PackageTypeService::getRawProductPackageTypeId( $productWithInvalidMeta ) );
		$this->assertSame( 7, PackageTypeService::resolveForProduct( $productWithInvalidMeta ) );
	}

	#[Test]
	public function resolves_category_for_order_matching_shopify_rules(): void {
		$makeItem = function( int $packageTypeId, bool $isVirtual = false ) {
			return new class( $packageTypeId, $isVirtual ) {
				private int $typeId;
				private bool $virtual;
				public function __construct( int $typeId, bool $virtual ) {
					$this->typeId  = $typeId;
					$this->virtual = $virtual;
				}
				public function get_product() {
					return new class( $this->typeId, $this->virtual ) {
						private int $typeId;
						private bool $virtual;
						public function __construct( int $typeId, bool $virtual ) {
							$this->typeId  = $typeId;
							$this->virtual = $virtual;
						}
						public function get_meta( $key, $single = true ) {
							return $this->typeId > 0 ? $this->typeId : '';
						}
						public function get_id() { return 1; }
						public function is_virtual() { return $this->virtual; }
					};
				}
			};
		};

		// Single item with Category 2 (Pakaian) -> 2
		$orderSingle = new class( array( $makeItem( 2 ) ) ) {
			private array $items;
			public function __construct( array $items ) { $this->items = $items; }
			public function get_items() { return $this->items; }
		};
		$this->assertSame( 2, PackageTypeService::resolveForOrder( $orderSingle ) );

		// Multiple items sharing Category 4 (Dokumen) -> 4
		$orderSame = new class( array( $makeItem( 4 ), $makeItem( 4 ) ) ) {
			private array $items;
			public function __construct( array $items ) { $this->items = $items; }
			public function get_items() { return $this->items; }
		};
		$this->assertSame( 4, PackageTypeService::resolveForOrder( $orderSame ) );

		// Mixed items Category 1 and Category 2 -> defaults to 7 (Lain-lain)
		$orderMixed = new class( array( $makeItem( 1 ), $makeItem( 2 ) ) ) {
			private array $items;
			public function __construct( array $items ) { $this->items = $items; }
			public function get_items() { return $this->items; }
		};
		$this->assertSame( 7, PackageTypeService::resolveForOrder( $orderMixed ) );

		// Items without meta (defaulting to 7) -> 7
		$orderDefault = new class( array( $makeItem( 0 ) ) ) {
			private array $items;
			public function __construct( array $items ) { $this->items = $items; }
			public function get_items() { return $this->items; }
		};
		$this->assertSame( 7, PackageTypeService::resolveForOrder( $orderDefault ) );

		// Virtual items are ignored in category determination
		$orderWithVirtual = new class( array( $makeItem( 5 ), $makeItem( 1, true ) ) ) {
			private array $items;
			public function __construct( array $items ) { $this->items = $items; }
			public function get_items() { return $this->items; }
		};
		$this->assertSame( 5, PackageTypeService::resolveForOrder( $orderWithVirtual ) );

		// Empty order -> 7
		$orderEmpty = new class {
			public function get_items() { return array(); }
		};
		$this->assertSame( 7, PackageTypeService::resolveForOrder( $orderEmpty ) );
	}

	#[Test]
	public function resolves_category_for_cart_shipping_package(): void {
		$makeProduct = function( int $typeId ) {
			return new class( $typeId ) {
				private int $typeId;
				public function __construct( int $typeId ) { $this->typeId = $typeId; }
				public function get_meta( $key, $single = true ) { return $this->typeId > 0 ? $this->typeId : ''; }
				public function get_id() { return 10; }
				public function is_virtual() { return false; }
			};
		};

		$pkgSame = array(
			'contents' => array(
				array( 'data' => $makeProduct( 3 ) ),
				array( 'data' => $makeProduct( 3 ) ),
			),
		);
		$this->assertSame( 3, PackageTypeService::resolveForCartPackage( $pkgSame ) );

		$pkgMixed = array(
			'contents' => array(
				array( 'data' => $makeProduct( 1 ) ),
				array( 'data' => $makeProduct( 2 ) ),
			),
		);
		$this->assertSame( 7, PackageTypeService::resolveForCartPackage( $pkgMixed ) );

		$pkgEmpty = array( 'contents' => array() );
		$this->assertSame( 7, PackageTypeService::resolveForCartPackage( $pkgEmpty ) );
	}

	#[Test]
	public function variation_inherits_parent_package_type_id_when_unset(): void {
		$variationWithParent = new class {
			public function get_meta( $key, $single = true ) { return ''; }
			public function get_id() { return 201; }
			public function get_parent_id() { return 100; }
			public function is_virtual() { return false; }
		};

		// When parent has no meta either -> defaults to 7
		$this->assertSame( 7, PackageTypeService::resolveForProduct( $variationWithParent ) );
	}

	#[Test]
	public function product_controller_hooks_category_into_shipping_tab(): void {
		$source = file_get_contents( PLUGIN_DIR . '/inc/Controllers/ProductController.php' );

		$this->assertStringContainsString( 'woocommerce_product_options_shipping', $source );
		$this->assertStringContainsString( 'kiriof_custom_field_shipping_category', $source );
		$this->assertStringContainsString( 'PackageTypeService::META_KEY', $source );
		$this->assertStringContainsString( 'delete_post_meta', $source );
		$this->assertStringContainsString( 'update_post_meta', $source );
	}

	#[Test]
	public function pickup_and_instant_services_use_package_type_service_for_orders(): void {
		$pickupSource = file_get_contents( PLUGIN_DIR . '/inc/Services/TransactionProcessServices/SendRequestPickupTransactionService.php' );
		$instantSource = file_get_contents( PLUGIN_DIR . '/inc/Services/InstantShipmentContext.php' );

		$this->assertStringContainsString( 'PackageTypeService::resolveForOrder', $pickupSource );
		$this->assertStringContainsString( 'PackageTypeService::resolveForOrder', $instantSource );
	}
}
