<?php

namespace KiriminAjaOfficial\Repositories;

use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductVolumetricReadinessRepository implements ProductVolumetricReadinessRepositoryInterface {
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Count shippable products and variations with complete volumetric data.
	 *
	 * Variations inherit missing dimensions and virtual state from their parent.
	 * Variable parents are excluded in favour of their published/private variations.
	 *
	 * @return array{total: int, configured: int, ready: bool}
	 */
	public function getReadiness(): array {
		$from = "FROM {$this->wpdb->posts} p
			LEFT JOIN {$this->wpdb->posts} child_variation ON child_variation.post_parent = p.ID
				AND child_variation.post_type = 'product_variation'
				AND child_variation.post_status IN ('publish','private')
			LEFT JOIN {$this->wpdb->postmeta} virtual_meta ON virtual_meta.post_id = p.ID AND virtual_meta.meta_key = '_virtual'
			LEFT JOIN {$this->wpdb->postmeta} parent_virtual_meta ON parent_virtual_meta.post_id = p.post_parent AND parent_virtual_meta.meta_key = '_virtual'";
		$where = "WHERE ((p.post_type = 'product_variation' AND p.post_status IN ('publish','private'))
			OR (p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL))
			AND COALESCE(NULLIF(virtual_meta.meta_value, ''), parent_virtual_meta.meta_value, 'no') <> 'yes'";
		$ready = "(
			CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(weight_meta.meta_value, ''), parent_weight_meta.meta_value, '0') ELSE COALESCE(weight_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(length_meta.meta_value, ''), parent_length_meta.meta_value, '0') ELSE COALESCE(length_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(width_meta.meta_value, ''), parent_width_meta.meta_value, '0') ELSE COALESCE(width_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(height_meta.meta_value, ''), parent_height_meta.meta_value, '0') ELSE COALESCE(height_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
		)";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query fragments are fully internal/static SQL snippets.
		$total      = (int) $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) {$from} {$where}" );
		$configured = (int) $this->wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) {$from}
			LEFT JOIN {$this->wpdb->postmeta} weight_meta ON weight_meta.post_id = p.ID AND weight_meta.meta_key = '_weight'
			LEFT JOIN {$this->wpdb->postmeta} length_meta ON length_meta.post_id = p.ID AND length_meta.meta_key = '_length'
			LEFT JOIN {$this->wpdb->postmeta} width_meta ON width_meta.post_id = p.ID AND width_meta.meta_key = '_width'
			LEFT JOIN {$this->wpdb->postmeta} height_meta ON height_meta.post_id = p.ID AND height_meta.meta_key = '_height'
			LEFT JOIN {$this->wpdb->postmeta} parent_weight_meta ON parent_weight_meta.post_id = p.post_parent AND parent_weight_meta.meta_key = '_weight'
			LEFT JOIN {$this->wpdb->postmeta} parent_length_meta ON parent_length_meta.post_id = p.post_parent AND parent_length_meta.meta_key = '_length'
			LEFT JOIN {$this->wpdb->postmeta} parent_width_meta ON parent_width_meta.post_id = p.post_parent AND parent_width_meta.meta_key = '_width'
			LEFT JOIN {$this->wpdb->postmeta} parent_height_meta ON parent_height_meta.post_id = p.post_parent AND parent_height_meta.meta_key = '_height'
			{$where} AND {$ready}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'total'      => $total,
			'configured' => $configured,
			'ready'      => $configured >= $total,
		);
	}
}
