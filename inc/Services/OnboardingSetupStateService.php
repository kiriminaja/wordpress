<?php
namespace KiriminAjaOfficial\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use KiriminAjaOfficial\Contracts\ProductVolumetricReadinessRepositoryInterface;
use KiriminAjaOfficial\Repositories\ProductVolumetricReadinessRepository;
use KiriminAjaOfficial\Repositories\SettingRepository;

/**
 * Provides one source of truth for onboarding and optional setup notices.
 */
class OnboardingSetupStateService {
	private ?array $steps = null;
	private ProductVolumetricReadinessRepositoryInterface $product_readiness_repository;
	private SettingRepository $setting_repository;
	private ?WooCommerceShippingMethodRegistrationService $shipping_method_service;
	private ?KiriminajaApiService $api_service;

	public function __construct(
		?ProductVolumetricReadinessRepositoryInterface $product_readiness_repository = null,
		?SettingRepository $setting_repository = null,
		?WooCommerceShippingMethodRegistrationService $shipping_method_service = null,
		?KiriminajaApiService $api_service = null
	) {
		$this->product_readiness_repository = $product_readiness_repository ?? new ProductVolumetricReadinessRepository();
		$this->setting_repository           = $setting_repository ?? new SettingRepository();
		$this->shipping_method_service      = $shipping_method_service;
		$this->api_service                  = $api_service;
	}

	public function get_steps(): array {
		if ( null !== $this->steps ) {
			return $this->steps;
		}

		$repo            = $this->setting_repository;
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
		$shipping_ready = $this->get_shipping_method_service()->hasEnabledMethod();

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
		foreach ( $this->setting_repository->getSettingByArray(
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
		$settings = $this->setting_repository->getIntegrationData();
		if ( ! is_iterable( $settings ) ) {
			return $values;
		}

		foreach ( $settings as $setting ) {
			$values[ $setting->key ] = (string) $setting->value;
		}

		return $values;
	}

	public function get_connection_state(): array {
		$repo          = $this->setting_repository;
		$setup_key_row = $repo->getSettingByKey( 'setup_key' );
		$api_key_row   = $repo->getSettingByKey( 'api_key' );
		$is_connected  = ! empty( $setup_key_row->value ?? null ) && ! empty( $api_key_row->value ?? null );
		$profile       = null;
		$profile_err   = false;

		if ( $is_connected ) {
			try {
				$profile_service = $this->get_api_service()->getProfile();
				if ( 200 === $profile_service->status && ! empty( $profile_service->data ) ) {
					$profile = $profile_service->data;
				} else {
					$profile_err = true;
				}
			} catch ( \Throwable $th ) {
				$profile_err = true;
			}
		}

		return array(
			'is_connected' => $is_connected,
			'profile'      => $profile,
			'profile_err'  => $profile_err,
		);
	}

	private function are_products_ready(): bool {
		$readiness = $this->product_readiness_repository->getReadiness();

		return $readiness['ready'];
	}

	private function get_shipping_method_service(): WooCommerceShippingMethodRegistrationService {
		if ( null === $this->shipping_method_service ) {
			$this->shipping_method_service = new WooCommerceShippingMethodRegistrationService();
		}

		return $this->shipping_method_service;
	}

	private function get_api_service(): KiriminajaApiService {
		if ( null === $this->api_service ) {
			$this->api_service = new KiriminajaApiService();
		}

		return $this->api_service;
	}
}
