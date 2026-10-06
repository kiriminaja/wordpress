<?php

namespace KiriminAjaOfficial\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service managing KiriminAja package categories / types.
 */
class PackageTypeService {

	public const DEFAULT_PACKAGE_TYPE_ID   = 7;
	public const DEFAULT_PACKAGE_TYPE_NAME = 'Lain-lain';
	public const META_KEY                  = '_kiriof_package_type_id';

	/**
	 * Available package types (KiriminAja commodities).
	 *
	 * @return array<int, array{id: int, name: string, insurance: int, is_fragile: int, created_at: string, updated_at: string}>
	 */
	public static function all(): array {
		return array(
			1  => array(
				'id'         => 1,
				'name'       => 'Peralatan Elektronik & Gadget',
				'insurance'  => 1,
				'is_fragile' => 0,
				'created_at' => '2020-12-08 09:22:36',
				'updated_at' => '2021-08-26 09:58:01',
			),
			2  => array(
				'id'         => 2,
				'name'       => 'Pakaian',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2020-12-08 09:23:01',
				'updated_at' => '2021-01-20 10:59:12',
			),
			3  => array(
				'id'         => 3,
				'name'       => 'Pecah Belah',
				'insurance'  => 1,
				'is_fragile' => 0,
				'created_at' => '2020-12-10 10:18:57',
				'updated_at' => '2021-01-25 15:32:51',
			),
			4  => array(
				'id'         => 4,
				'name'       => 'Dokumen',
				'insurance'  => 1,
				'is_fragile' => 0,
				'created_at' => '2020-12-26 19:02:25',
				'updated_at' => '2021-01-25 15:33:06',
			),
			5  => array(
				'id'         => 5,
				'name'       => 'Peralatan Rumah Tangga',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2020-12-26 19:02:36',
				'updated_at' => '2021-08-26 09:58:27',
			),
			6  => array(
				'id'         => 6,
				'name'       => 'Aksesoris',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2020-12-26 19:02:44',
				'updated_at' => '2020-12-26 19:02:44',
			),
			7  => array(
				'id'         => 7,
				'name'       => 'Lain-lain',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2021-01-25 15:32:42',
				'updated_at' => '2017-01-25 15:32:42',
			),
			8  => array(
				'id'         => 8,
				'name'       => 'Dokumen Berharga',
				'insurance'  => 1,
				'is_fragile' => 0,
				'created_at' => '2020-12-26 19:02:25',
				'updated_at' => '2021-01-25 15:33:06',
			),
			9  => array(
				'id'         => 9,
				'name'       => 'Peralatan Kesehatan & Kecantikan',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2021-08-26 09:59:59',
				'updated_at' => '2021-08-26 09:59:59',
			),
			10 => array(
				'id'         => 10,
				'name'       => 'Peralatan Olahraga & Hiburan',
				'insurance'  => 0,
				'is_fragile' => 0,
				'created_at' => '2021-08-26 10:00:16',
				'updated_at' => '2021-08-26 10:00:16',
			),
		);
	}

	/**
	 * Check whether an ID corresponds to an existing package type.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function isValidId( int $id ): bool {
		return isset( self::all()[ $id ] );
	}

	/**
	 * Find package type by ID.
	 *
	 * @param int $id
	 * @return array{id: int, name: string, insurance: int, is_fragile: int, created_at: string, updated_at: string}|null
	 */
	public static function findById( int $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Get options for WooCommerce select dropdown.
	 * Empty value indicates nullable / default selection.
	 *
	 * @return array<string, string>
	 */
	public static function getSelectOptions(): array {
		$options = array(
			'' => __( 'Lain-lain (Default)', 'kiriminaja-official' ),
		);

		foreach ( self::all() as $item ) {
			$options[ (string) $item['id'] ] = $item['name'];
		}

		return $options;
	}

	/**
	 * Get raw saved package type ID from product or variation meta.
	 * Returns null if not configured or empty.
	 *
	 * @param mixed $product Product object or post ID.
	 * @return int|null
	 */
	public static function getRawProductPackageTypeId( $product ): ?int {
		$product_id = 0;
		$meta_value = null;

		if ( is_object( $product ) ) {
			if ( method_exists( $product, 'get_meta' ) ) {
				$val = $product->get_meta( self::META_KEY, true );
				if ( '' !== $val && null !== $val && false !== $val ) {
					$meta_value = $val;
				}
			}
			if ( null === $meta_value && method_exists( $product, 'get_id' ) ) {
				$product_id = (int) $product->get_id();
			}
		} elseif ( is_numeric( $product ) ) {
			$product_id = (int) $product;
		}

		if ( null === $meta_value && $product_id > 0 && function_exists( 'get_post_meta' ) ) {
			$val = get_post_meta( $product_id, self::META_KEY, true );
			if ( '' !== $val && null !== $val && false !== $val ) {
				$meta_value = $val;
			}
		}

		// Check parent product if variation and still empty.
		if ( null === $meta_value && is_object( $product ) && method_exists( $product, 'get_parent_id' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id > 0 && function_exists( 'get_post_meta' ) ) {
				$val = get_post_meta( $parent_id, self::META_KEY, true );
				if ( '' !== $val && null !== $val && false !== $val ) {
					$meta_value = $val;
				}
			}
		} elseif ( null === $meta_value && $product_id > 0 && function_exists( 'wp_get_post_parent_id' ) && function_exists( 'get_post_meta' ) ) {
			$parent_id = (int) wp_get_post_parent_id( $product_id );
			if ( $parent_id > 0 ) {
				$val = get_post_meta( $parent_id, self::META_KEY, true );
				if ( '' !== $val && null !== $val && false !== $val ) {
					$meta_value = $val;
				}
			}
		}

		if ( null !== $meta_value && is_numeric( $meta_value ) ) {
			$id = (int) $meta_value;
			if ( self::isValidId( $id ) ) {
				return $id;
			}
		}

		return null;
	}

	/**
	 * Resolve package type ID for product, falling back to default 7 (Lain-lain).
	 *
	 * @param mixed $product
	 * @return int
	 */
	public static function resolveForProduct( $product ): int {
		$raw = self::getRawProductPackageTypeId( $product );
		return null !== $raw ? $raw : self::DEFAULT_PACKAGE_TYPE_ID;
	}

	/**
	 * Resolve package type ID for an order.
	 *
	 * - All physical items share the same package_type_id -> return that ID.
	 * - Mixed package_type_ids or no physical items -> return DEFAULT_PACKAGE_TYPE_ID (7).
	 *
	 * @param mixed $order
	 * @return int
	 */
	public static function resolveForOrder( $order ): int {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return self::DEFAULT_PACKAGE_TYPE_ID;
		}

		$ids = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product' ) ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product || ( method_exists( $product, 'is_virtual' ) && $product->is_virtual() ) ) {
				continue;
			}
			$ids[] = self::resolveForProduct( $product );
		}

		if ( empty( $ids ) ) {
			return self::DEFAULT_PACKAGE_TYPE_ID;
		}

		$unique_ids = array_values( array_unique( $ids ) );
		if ( 1 === count( $unique_ids ) ) {
			return (int) $unique_ids[0];
		}

		return self::DEFAULT_PACKAGE_TYPE_ID;
	}

	/**
	 * Resolve package type ID for a cart/shipping package.
	 *
	 * - All physical items share the same package_type_id -> return that ID.
	 * - Mixed package_type_ids or empty -> return DEFAULT_PACKAGE_TYPE_ID (7).
	 *
	 * @param array $package
	 * @return int
	 */
	public static function resolveForCartPackage( array $package ): int {
		$ids = array();
		foreach ( $package['contents'] ?? array() as $item ) {
			$product = $item['data'] ?? null;
			if ( ! is_object( $product ) || ( method_exists( $product, 'is_virtual' ) && $product->is_virtual() ) ) {
				continue;
			}
			$ids[] = self::resolveForProduct( $product );
		}

		if ( empty( $ids ) ) {
			return self::DEFAULT_PACKAGE_TYPE_ID;
		}

		$unique_ids = array_values( array_unique( $ids ) );
		if ( 1 === count( $unique_ids ) ) {
			return (int) $unique_ids[0];
		}

		return self::DEFAULT_PACKAGE_TYPE_ID;
	}
}
