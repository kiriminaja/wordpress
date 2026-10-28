<?php

namespace KiriminAjaOfficial\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Short-lived, site-local classification cache, without retaining recipient data. */
final class TransactionPhoneIssueCache {
	private const REVISION_KEY = 'kiriof_phone_issue_revision';
	private const IDS_KEY      = 'kiriof_phone_issue_ids_v1';
	private const TTL          = 60;

	public function register(): void {
		foreach ( array(
			'woocommerce_after_order_object_save',
			'woocommerce_order_status_changed',
			'woocommerce_delete_order',
			'woocommerce_trash_order',
			'woocommerce_untrash_order',
			'save_post_shop_order',
			'trashed_post',
			'untrashed_post',
			'deleted_post',
			'woocommerce_after_product_object_save',
			'woocommerce_after_product_variation_object_save',
			'woocommerce_new_order_item',
			'woocommerce_update_order_item',
			'woocommerce_delete_order_item',
		) as $hook ) {
			add_action( $hook, array( self::class, 'invalidate' ), 10, 0 );
		}

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'added_order_item_meta', 'updated_order_item_meta', 'deleted_order_item_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'invalidateMetadata' ), 10, 3 );
		}
	}

	public function invalidateMetadata( $meta_id, $object_id, $meta_key ): void {
		if ( in_array( $meta_key, array( '_shipping_phone', '_billing_phone', '_virtual', '_product_id', '_variation_id' ), true ) ) {
			self::invalidate();
		}
	}

	public static function available(): bool {
		return function_exists( 'get_transient' ) && function_exists( 'set_transient' );
	}

	public static function invalidate(): void {
		if ( self::available() ) {
			// A unique revision prevents an in-flight scan from reviving stale IDs.
			set_transient( self::REVISION_KEY, uniqid( '', true ), self::TTL * 2 );
		}
	}

	public static function revision(): string {
		$revision = get_transient( self::REVISION_KEY );
		if ( ! is_string( $revision ) || '' === $revision ) {
			self::invalidate();
			$revision = get_transient( self::REVISION_KEY );
		}
		return is_string( $revision ) ? $revision : '';
	}

	public static function get( string $revision ): ?array {
		$cached = get_transient( self::IDS_KEY );
		return '' !== $revision && is_array( $cached ) && ( $cached['revision'] ?? null ) === $revision && isset( $cached['ids'] ) && is_array( $cached['ids'] ) ? $cached['ids'] : null;
	}

	public static function put( string $revision, array $ids ): void {
		if ( '' !== $revision && self::revision() === $revision ) {
			// Fixed keys bound retention even when orders are edited frequently.
			set_transient( self::IDS_KEY, array( 'revision' => $revision, 'ids' => $ids ), self::TTL );
		}
	}
}
