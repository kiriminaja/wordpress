<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Repositories\SettingRepository;

/**
 * Provides one source of truth for onboarding and optional setup notices.
 */
class OnboardingSetupStateService {
	private ?array $steps = null;

	public function get_steps(): array {
		if ( null !== $this->steps ) {
			return $this->steps;
		}

		$repo            = new SettingRepository();
		$setup_key       = $repo->getSettingByKey( 'setup_key' );
		$api_key         = $repo->getSettingByKey( 'api_key' );
		$account_ready   = ! empty( $setup_key->value ?? null ) && ! empty( $api_key->value ?? null );
		$origin_settings = $repo->getSettingByArray(
			array(
				'origin_name',
				'origin_phone',
				'origin_address',
				'origin_latitude',
				'origin_longitude',
				'origin_sub_district_id',
				'origin_sub_district_name',
				'origin_zip_code',
			)
		);

		$origin_ready = 8 === count( $origin_settings );
		foreach ( $origin_settings as $setting ) {
			if ( empty( $setting->value ?? null ) ) {
				$origin_ready = false;
				break;
			}
		}

		$courier_setting = $repo->getSettingByKey( 'origin_whitelist_expedition_id' );
		$shipping_ready  = false;
		if ( class_exists( __NAMESPACE__ . '\\WooCommerceShippingMethodRegistrationService' ) ) {
			$shipping_ready = ( new WooCommerceShippingMethodRegistrationService() )->hasEnabledMethod();
		}

		$ship_to_countries  = get_option( 'woocommerce_ship_to_countries', '' );
		$shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_shipping_countries() : array();
		$locations_ready    = 'disabled' !== $ship_to_countries && ! empty( $shipping_countries );

		$this->steps = array(
			'account'         => array(
				'key'      => 'account',
				'required' => true,
				'done'     => $account_ready,
				'nav_title' => __( 'Account', 'kiriminaja-official' ),
				'title'    => __( 'Account Connection', 'kiriminaja-official' ),
				'description' => __( 'Connect your store to sync orders, shipments, and tracking.', 'kiriminaja-official' ),
			),
			'address'        => array(
				'key'      => 'address',
				'required' => true,
				'done'     => $origin_ready,
				'nav_title' => __( 'Address', 'kiriminaja-official' ),
				'title'    => __( 'Add your shipping address', 'kiriminaja-official' ),
				'description' => __( 'Tell couriers where to collect your packages.', 'kiriminaja-official' ),
			),
			'couriers'       => array(
				'key'      => 'couriers',
				'required' => true,
				'done'     => ! empty( $courier_setting->value ?? null ),
				'nav_title' => __( 'Couriers', 'kiriminaja-official' ),
				'title'    => __( 'Choose courier services', 'kiriminaja-official' ),
				'description' => __( 'Select the courier services your customers can use at checkout.', 'kiriminaja-official' ),
			),
			'shipping'       => array(
				'key'      => 'shipping',
				'required' => true,
				'done'     => $shipping_ready && $locations_ready,
				'nav_title' => __( 'Shipping', 'kiriminaja-official' ),
				'title'    => __( 'Enable KiriminAja shipping', 'kiriminaja-official' ),
				'description' => __( 'Make KiriminAja rates available to your customers at checkout.', 'kiriminaja-official' ),
				'shipping_ready' => $shipping_ready,
				'locations_ready' => $locations_ready,
			),
			'products'       => array(
				'key'      => 'products',
				'required' => false,
				'done'     => $this->are_products_ready(),
				'title'    => __( 'Complete product details', 'kiriminaja-official' ),
				'description' => __( 'Add weight and dimensions for accurate shipping rates.', 'kiriminaja-official' ),
			),
			'tracking'       => array(
				'key'      => 'tracking',
				'required' => false,
				'done'     => kiriof_get_tracking_page_id() > 0,
				'title'    => __( 'Create a tracking page', 'kiriminaja-official' ),
				'description' => __( 'Let customers track their shipments from your store.', 'kiriminaja-official' ),
			),
		);

		return $this->steps;
	}

	public function is_required_complete(): bool {
		foreach ( $this->get_steps() as $step ) {
			if ( $step['required'] && ! $step['done'] ) {
				return false;
			}
		}

		return true;
	}

	public function get_first_incomplete_step(): string {
		foreach ( $this->get_steps() as $key => $step ) {
			if ( ! $step['done'] ) {
				return $key;
			}
		}

		return 'complete';
	}

	public function get_origin_values(): array {
		$values = array();
		foreach ( ( new SettingRepository() )->getSettingByArray(
			array(
				'origin_name', 'origin_phone', 'origin_address', 'origin_latitude',
				'origin_longitude', 'origin_sub_district_id', 'origin_sub_district_name', 'origin_zip_code',
			)
		) as $setting ) {
			$values[ $setting->key ] = (string) $setting->value;
		}

		return $values;
	}

	public function get_integration_values(): array {
		$values = array();
		$settings = ( new SettingRepository() )->getIntegrationData();
		if ( ! is_iterable( $settings ) ) {
			return $values;
		}

		foreach ( $settings as $setting ) {
			$values[ $setting->key ] = (string) $setting->value;
		}

		return $values;
	}

	public function get_connection_state(): array {
		$repo          = new SettingRepository();
		$setup_key_row = $repo->getSettingByKey( 'setup_key' );
		$api_key_row   = $repo->getSettingByKey( 'api_key' );
		$is_connected  = ! empty( $setup_key_row->value ?? null ) && ! empty( $api_key_row->value ?? null );
		$profile       = null;
		$profile_err   = false;

		if ( $is_connected && class_exists( KiriminajaApiService::class ) ) {
			try {
				$profile_service = ( new KiriminAjaApiService() )->getProfile();
				if ( 200 === $profile_service->status && ! empty( $profile_service->data ) ) {
					$profile = $profile_service->data;
				} else {
					$profile_err = true;
				}
			} catch ( \Throwable $th ) {
				$profile_err = true;
			}
		} elseif ( $is_connected ) {
			$profile_err = true;
		}

		return array(
			'is_connected' => $is_connected,
			'profile'      => $profile,
			'profile_err'  => $profile_err,
		);
	}

	private function are_products_ready(): bool {
		global $wpdb;

		$from = "FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->posts} child_variation ON child_variation.post_parent = p.ID
				AND child_variation.post_type = 'product_variation'
				AND child_variation.post_status IN ('publish','private')
			LEFT JOIN {$wpdb->postmeta} virtual_meta ON virtual_meta.post_id = p.ID AND virtual_meta.meta_key = '_virtual'
			LEFT JOIN {$wpdb->postmeta} parent_virtual_meta ON parent_virtual_meta.post_id = p.post_parent AND parent_virtual_meta.meta_key = '_virtual'";
		$where = "WHERE ((p.post_type = 'product_variation' AND p.post_status IN ('publish','private'))
			OR (p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL))
			AND COALESCE(NULLIF(virtual_meta.meta_value, ''), parent_virtual_meta.meta_value, 'no') <> 'yes'";
		$ready = "(
			CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(weight_meta.meta_value, ''), parent_weight_meta.meta_value, '0') ELSE COALESCE(weight_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(length_meta.meta_value, ''), parent_length_meta.meta_value, '0') ELSE COALESCE(length_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(width_meta.meta_value, ''), parent_width_meta.meta_value, '0') ELSE COALESCE(width_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
			AND CAST(CASE WHEN p.post_type = 'product_variation' THEN COALESCE(NULLIF(height_meta.meta_value, ''), parent_height_meta.meta_value, '0') ELSE COALESCE(height_meta.meta_value, '0') END AS DECIMAL(10,2)) > 0
		)";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) {$from} {$where}" );
		$configured = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) {$from}
			LEFT JOIN {$wpdb->postmeta} weight_meta ON weight_meta.post_id = p.ID AND weight_meta.meta_key = '_weight'
			LEFT JOIN {$wpdb->postmeta} length_meta ON length_meta.post_id = p.ID AND length_meta.meta_key = '_length'
			LEFT JOIN {$wpdb->postmeta} width_meta ON width_meta.post_id = p.ID AND width_meta.meta_key = '_width'
			LEFT JOIN {$wpdb->postmeta} height_meta ON height_meta.post_id = p.ID AND height_meta.meta_key = '_height'
			LEFT JOIN {$wpdb->postmeta} parent_weight_meta ON parent_weight_meta.post_id = p.post_parent AND parent_weight_meta.meta_key = '_weight'
			LEFT JOIN {$wpdb->postmeta} parent_length_meta ON parent_length_meta.post_id = p.post_parent AND parent_length_meta.meta_key = '_length'
			LEFT JOIN {$wpdb->postmeta} parent_width_meta ON parent_width_meta.post_id = p.post_parent AND parent_width_meta.meta_key = '_width'
			LEFT JOIN {$wpdb->postmeta} parent_height_meta ON parent_height_meta.post_id = p.post_parent AND parent_height_meta.meta_key = '_height'
			{$where} AND {$ready}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $configured >= $total;
	}
}
