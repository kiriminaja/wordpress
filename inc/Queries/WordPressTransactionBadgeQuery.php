<?php

namespace KiriminAjaOfficial\Queries;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Global pending shipment counts shared by the sidebar and delivery badges.
 *
 * Each eligible order belongs to exactly one badge. Inconsistent duplicate
 * transaction rows use issue > instant > regular priority. Persisted exact
 * delivery types are authoritative; unknown/legacy types fall back to regular
 * so the sidebar's all-delivery pending scope never loses an order.
 */
class WordPressTransactionBadgeQuery {
    /** @var object WordPress database connection. */
    private $wpdb;

    public function __construct( $wpdb = null ) {
        if ( null === $wpdb ) {
            global $wpdb;
        }
        $this->wpdb = $wpdb;
    }

    /** No list filters or transient cache: these badges always describe pending work. */
    public function getCounts(): array {
        $wpdb   = $this->wpdb;
        $prefix = $wpdb->prefix;
        $orders = array( 'table' => "{$prefix}posts", 'id' => 'ID', 'status' => 'post_status', 'type' => 'post_type' );
        if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
            $orders = array( 'table' => "{$prefix}wc_orders", 'id' => 'id', 'status' => 'status', 'type' => 'type' );
        }
        $postmeta = $wpdb->postmeta;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fresh global badges use fixed internal storage identifiers and prepared criteria.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN pending.has_issue = 0 AND pending.has_instant = 0 THEN 1 ELSE 0 END), 0) AS regular,
                    COALESCE(SUM(CASE WHEN pending.has_issue = 0 AND pending.has_instant = 1 THEN 1 ELSE 0 END), 0) AS instant,
                    COALESCE(SUM(CASE WHEN pending.has_issue = 1 THEN 1 ELSE 0 END), 0) AS issue,
                    COUNT(*) AS total
                FROM (
                    SELECT p.{$orders['id']} AS order_id,
                        MAX(CASE WHEN t.delivery_type = 'express' AND t.is_deficit = 1 THEN 1 ELSE 0 END) AS has_issue,
                        MAX(CASE WHEN t.delivery_type = 'instant' THEN 1 ELSE 0 END) AS has_instant
                    FROM {$orders['table']} p
                    INNER JOIN {$prefix}kiriminaja_transactions t ON p.{$orders['id']} = t.wp_wc_order_stat_order_id
                    WHERE p.{$orders['type']} = %s AND p.{$orders['status']} = %s AND t.status = %s
                        AND EXISTS (
                            SELECT 1 FROM {$prefix}woocommerce_order_items oi
                            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_var ON oim_var.order_item_id = oi.order_item_id AND oim_var.meta_key = '_variation_id'
                            LEFT JOIN {$postmeta} pm_var ON pm_var.post_id = oim_var.meta_value AND pm_var.meta_key = '_virtual'
                            LEFT JOIN {$prefix}woocommerce_order_itemmeta oim_prod ON oim_prod.order_item_id = oi.order_item_id AND oim_prod.meta_key = '_product_id'
                            LEFT JOIN {$postmeta} pm_prod ON pm_prod.post_id = oim_prod.meta_value AND pm_prod.meta_key = '_virtual'
                            WHERE oi.order_id = p.{$orders['id']} AND oi.order_item_type = 'line_item'
                                AND COALESCE(NULLIF(pm_var.meta_value, ''), pm_prod.meta_value, 'no') <> 'yes'
                        )
                    GROUP BY p.{$orders['id']}
                ) pending",
                'shop_order',
                'wc-processing',
                'new'
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! empty( $wpdb->last_error ) ) {
            if ( function_exists( 'kiriof_log' ) ) {
                kiriof_log( 'error', (string) $wpdb->last_error );
            }
            $row = null;
        }

        return array(
            'regular' => (int) ( $row->regular ?? 0 ),
            'instant' => (int) ( $row->instant ?? 0 ),
            'issue'   => (int) ( $row->issue ?? 0 ),
            'total'   => (int) ( $row->total ?? 0 ),
        );
    }
}
