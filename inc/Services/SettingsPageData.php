<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Repositories\SettingRepository;
use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares settings page view data outside the template layer.
 */
class SettingsPageData {
	private SettingRepository $setting_repository;
	private ProductVolumetricReadinessService $product_readiness_service;
	private KiriminajaApiService $api_service;
	private ShippingDiscountRegionRepository $region_repository;
	private ShippingDiscountRegionCacheService $region_cache_service;

	public function __construct(
		?SettingRepository $setting_repository = null,
		?ProductVolumetricReadinessService $product_readiness_service = null,
		?KiriminajaApiService $api_service = null,
		?ShippingDiscountRegionRepository $region_repository = null,
		?ShippingDiscountRegionCacheService $region_cache_service = null
	) {
		$this->setting_repository        = $setting_repository ?? new SettingRepository();
		$this->product_readiness_service = $product_readiness_service ?? new ProductVolumetricReadinessService();
		$this->api_service               = $api_service ?? new KiriminajaApiService();
		$this->region_repository         = $region_repository ?? new ShippingDiscountRegionRepository();
		$this->region_cache_service      = $region_cache_service ?? new ShippingDiscountRegionCacheService();
	}

	/**
	 * Prepare data shared by the setup and connected settings views.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare(): array {
		$shipping_settings = $this->setting_repository->getSettingByArray(
			array(
				'origin_name',
				'origin_phone',
				'origin_address',
				'origin_latitude',
				'origin_longitude',
				'origin_sub_district_id',
				'origin_sub_district_name',
				'origin_zip_code',
				'origin_whitelist_expedition_id',
				'origin_whitelist_expedition_name',
			)
		);
		$advanced_settings = $this->setting_repository->getSettingByArray( array( 'callback_url' ) );
		$input_values      = array();

		foreach ( array_merge( $shipping_settings, $advanced_settings ) as $setting ) {
			$input_values[ $setting->key ] = $setting->value;
		}

		$origin_ready = true;
		foreach ( $shipping_settings as $setting ) {
			if ( in_array( $setting->key, array( 'origin_whitelist_expedition_id', 'origin_whitelist_expedition_name' ), true ) ) {
				continue;
			}
			if ( empty( $setting->value ?? null ) ) {
				$origin_ready = false;
				break;
			}
		}

		return array(
			'locale'                           => get_locale(),
			'approvedSetupKey'                 => $this->setting_repository->getSettingByKey( 'setup_key' ),
			'shippingRepo'                     => $shipping_settings,
			'advancedRepo'                     => $advanced_settings,
			'inputValueArr'                    => $input_values,
			'isOriginShippingDataReady'        => $origin_ready,
			'productVolumetricReadiness'       => $this->product_readiness_service->getReadiness(),
		);
	}

	/**
	 * Prepare connected settings list data.
	 *
	 * @return array<string, mixed>
	 */
	public function prepareList(): array {
		$cod_settings      = get_option( 'woocommerce_cod_settings', array() );
		$insurance_setting = $this->setting_repository->getSettingByKey( 'enable_insurance' );
		$ship_to_countries = get_option( 'woocommerce_ship_to_countries', '' );
		$shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_shipping_countries() : array();

		return array(
			'kiriof_cod_settings'             => $cod_settings,
			'kiriof_cod_enabled'              => $cod_settings['enabled'] ?? 'yes',
			'kiriof_insurance_setting'        => $insurance_setting,
			'kiriof_insurance_enabled'        => ( $insurance_setting && 'yes' === $insurance_setting->value ) ? 'yes' : 'no',
			'kiriof_ship_to_countries'        => $ship_to_countries,
			'kiriof_shipping_countries'       => $shipping_countries,
			'kiriof_shipping_locations_ready' => ( 'disabled' !== $ship_to_countries && ! empty( $shipping_countries ) ),
		);
	}

	/**
	 * Prepare account section data, including profile and whitelist names.
	 *
	 * @return array<string, mixed>
	 */
	public function prepareAccount(): array {
		$setup_key_row = $this->setting_repository->getSettingByKey( 'setup_key' );
		$is_connected  = ! empty( $setup_key_row->value ?? null );
		$profile       = null;
		$profile_error = false;

		if ( $is_connected ) {
			try {
				$profile_result = $this->api_service->getProfile();
				if ( 200 === $profile_result->status && ! empty( $profile_result->data ) ) {
					$profile = $profile_result->data;
				} else {
					$profile_error = true;
				}
			} catch ( \Throwable $throwable ) {
				$profile_error = true;
			}
		}

		$whitelist_rows  = $this->setting_repository->getSettingByArray( array( 'origin_whitelist_expedition_id', 'origin_whitelist_expedition_name' ) );
		$whitelist_ids   = '';
		$whitelist_names = '';
		foreach ( $whitelist_rows as $whitelist_row ) {
			if ( 'origin_whitelist_expedition_id' === $whitelist_row->key ) {
				$whitelist_ids = $whitelist_row->value;
			}
			if ( 'origin_whitelist_expedition_name' === $whitelist_row->key ) {
				$whitelist_names = $whitelist_row->value;
			}
		}

		$whitelist_id_array   = $whitelist_ids ? array_map( 'trim', explode( ',', $whitelist_ids ) ) : array();
		$whitelist_name_array = $whitelist_names ? array_map( 'trim', explode( ',', $whitelist_names ) ) : array();
		$whitelist_name_array = array_slice( array_pad( $whitelist_name_array, count( $whitelist_id_array ), '' ), 0, count( $whitelist_id_array ) );
		$whitelist_map        = empty( $whitelist_id_array ) ? array() : array_combine( $whitelist_id_array, $whitelist_name_array );

		if ( ! empty( $whitelist_id_array ) ) {
			try {
				$courier_result = $this->api_service->get_couriers();
				if ( 200 === $courier_result->status && ! empty( $courier_result->data ) ) {
					foreach ( $courier_result->data as $courier ) {
						if ( in_array( $courier->code, $whitelist_id_array, true ) && empty( $whitelist_map[ $courier->code ] ) ) {
							$whitelist_map[ $courier->code ] = $courier->name;
						}
					}
				}
			} catch ( \Throwable $throwable ) {
				// Non-critical: retain stored whitelist names.
			}
		}

		return array(
			'kiriof_setup_key_row' => $setup_key_row,
			'kiriof_is_connected'  => $is_connected,
			'kiriof_profile'       => $profile,
			'kiriof_profile_err'   => $profile_error,
			'kiriof_wl'            => $whitelist_rows,
			'kiriof_wl_ids'        => $whitelist_ids,
			'kiriof_wl_names'      => $whitelist_names,
			'kiriof_wl_id_arr'     => $whitelist_id_array,
			'kiriof_wl_name_arr'   => $whitelist_name_array,
			'kiriof_wl_map'        => $whitelist_map,
		);
	}

	/**
	 * Prepare region and courier cache diagnostics.
	 *
	 * @return array<string, mixed>
	 */
	public function prepareTechnical(): array {
		$cache_status   = $this->region_cache_service->getStatus();
		$state          = $cache_status['state'] ?? 'unknown';
		$courier_result = $this->api_service->get_couriers();
		$courier_cached = false !== get_transient( 'kiriof_couriers_list_v2' );
		$courier_timeout = (int) get_option( '_transient_timeout_kiriof_couriers_list_v2', 0 );

		return array(
			'cacheStatus'       => $cache_status,
			'provinceCount'     => $this->region_repository->getProvinceCount(),
			'cityCount'         => $this->region_repository->getCityCount(),
			'nonce'             => wp_create_nonce( KIRIOF_NONCE ),
			'state'             => $state,
			'stateColors'       => array( 'ready' => '#00a32a', 'error' => '#d63638' ),
			'stateColor'        => ( array( 'ready' => '#00a32a', 'error' => '#d63638' ) )[ $state ] ?? '#dba617',
			'regionValidUntil'  => 'ready' === $state ? __( 'Manual refresh only', 'kiriminaja-official' ) : '—',
			'downloadLogUrl'    => wp_nonce_url( admin_url( 'admin-post.php?action=kiriof_download_plugin_logs' ), 'kiriof_download_plugin_logs' ),
			'courierResult'     => $courier_result,
			'courierCount'      => ( 200 === $courier_result->status && is_array( $courier_result->data ) ) ? count( $courier_result->data ) : 0,
			'courierCached'     => $courier_cached,
			'courierTimeout'    => $courier_timeout,
			'courierUpdated'    => ( $courier_cached && $courier_timeout > DAY_IN_SECONDS ) ? wp_date( 'Y-m-d H:i:s', $courier_timeout - DAY_IN_SECONDS ) : '—',
			'courierValidUntil' => ( $courier_cached && $courier_timeout > 0 ) ? wp_date( 'Y-m-d H:i:s', $courier_timeout ) : '—',
			'courierBadgeBg'    => $courier_cached ? '#00a32a' : '#dba617',
			'courierBadgeTxt'   => $courier_cached ? __( 'Cached', 'kiriminaja-official' ) : __( 'Not cached', 'kiriminaja-official' ),
		);
	}
}
