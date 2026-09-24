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
	 * @param array<string, mixed> $account Prepared account data.
	 * @return array<string, mixed>
	 */
	public function prepareAccountBootstrap( array $account ): array {
		$is_connected = (bool) $account['kiriof_is_connected'];
		$profile      = $account['kiriof_profile'];
		$couriers = array();
		foreach ( $account['kiriof_wl_id_arr'] as $code ) {
			$couriers[] = array(
				'code' => $code,
				'name' => $account['kiriof_wl_map'][ $code ] ?? strtoupper( $code ),
			);
		}

		return array(
			'view'         => 'account',
			'toolbar'      => $this->settingsToolbar( __( 'Account Configuration', 'kiriminaja-official' ) ),
			'connected'    => $is_connected,
			'profileError' => (bool) $account['kiriof_profile_err'],
			'profile'      => $profile ? array(
				'name'          => (string) ( $profile->name ?? '' ),
				'email'         => (string) ( $profile->email ?? '' ),
				'status'        => (string) ( $profile->status ?? '' ),
				'paymentMethod' => (string) ( $profile->metadata->payment_method ?? '' ),
			) : null,
			'couriers'      => $couriers,
			'termsUrl'      => 'https://kiriminaja.com/syarat-ketentuan',
			'privacyUrl'    => 'https://kiriminaja.com/privacy-policy',
			'dashboardUrl'  => 'https://app.kiriminaja.com',
			'i18n'          => array(
				'enabledCouriers'      => __( 'Enabled Couriers', 'kiriminaja-official' ),
				'connection'           => __( 'Connection', 'kiriminaja-official' ),
				'setupKey'             => __( 'Setup Key', 'kiriminaja-official' ),
				'setupKeyPlaceholder'  => __( 'Input your setup key for KiriminAja', 'kiriminaja-official' ),
				'connect'              => __( 'Connect', 'kiriminaja-official' ),
				'updateConnection'     => __( 'Update Connection', 'kiriminaja-official' ),
				'linkedAccount'        => __( 'Linked Account', 'kiriminaja-official' ),
				'credentialsTitle'     => __( 'How to Obtain Your KiriminAja Credentials', 'kiriminaja-official' ),
				'privacyTitle'         => __( 'Accept Our Privacy & Policy', 'kiriminaja-official' ),
				'credentialsSteps'     => array(
					__( 'Log in to your KiriminAja dashboard.', 'kiriminaja-official' ),
					__( 'Go to the Settings menu and select App Integrations.', 'kiriminaja-official' ),
					__( 'Click Add Integration and choose WooCommerce.', 'kiriminaja-official' ),
					__( 'Enter your store domain.', 'kiriminaja-official' ),
					__( 'Allow up to two business days for API generation.', 'kiriminaja-official' ),
					__( 'The Setup Key will appear on the App Integrations page.', 'kiriminaja-official' ),
					__( 'Copy and paste the Setup Key into the field.', 'kiriminaja-official' ),
					__( 'Update the connection and continue using KiriminAja.', 'kiriminaja-official' ),
				),
				'connecting'           => __( 'Connecting…', 'kiriminaja-official' ),
				'disconnect'           => __( 'Disconnect', 'kiriminaja-official' ),
				'disconnecting'        => __( 'Disconnecting…', 'kiriminaja-official' ),
				'disconnectConfirm'    => __( 'Disconnect KiriminAja integration?', 'kiriminaja-official' ),
				'accountUnavailable'   => __( 'Unable to load account information. Your integration may be incomplete.', 'kiriminaja-official' ),
				'connectedUnavailable' => __( 'Account is connected, but profile details are unavailable right now.', 'kiriminaja-official' ),
				'enterSetupKey'        => __( 'Please enter a setup key.', 'kiriminaja-official' ),
				'connectionFailed'     => __( 'Connection failed. Please check your setup key.', 'kiriminaja-official' ),
				'disconnectFailed'     => __( 'Disconnect failed.', 'kiriminaja-official' ),
				'agreementPrefix'      => __( 'By clicking Connect, you agree to accept KiriminAja\'s', 'kiriminaja-official' ),
				'terms'                => __( 'terms and conditions', 'kiriminaja-official' ),
				'agreementAnd'         => __( 'and its', 'kiriminaja-official' ),
				'privacy'              => __( 'privacy policy', 'kiriminaja-official' ),
			),
		);
	}

	/**
	 * @param array<int, object> $pages Tracking pages.
	 * @return array<string, mixed>
	 */
	public function prepareTrackingBootstrap( array $pages ): array {
		$serialized_pages = array();
		foreach ( $pages as $page ) {
			$serialized_pages[] = array(
				'id'      => (int) $page->ID,
				'title'   => (string) $page->post_title,
				'url'     => get_permalink( $page->ID ),
				'editUrl' => get_edit_post_link( $page->ID ),
			);
		}

		return array(
			'view'  => 'tracking',
			'toolbar' => $this->settingsToolbar( __( 'Tracking Page', 'kiriminaja-official' ) ),
			'pages' => $serialized_pages,
			'i18n'  => array(
				'guideTitle'       => __( 'How to Add a Tracking Page', 'kiriminaja-official' ),
				'guideSteps'       => array(
					__( 'Go to Pages › Add New in your WordPress admin.', 'kiriminaja-official' ),
					__( 'Give your page a title, e.g. "Track Your Order".', 'kiriminaja-official' ),
					__( 'Add the KiriminAja tracking shortcode to the content editor.', 'kiriminaja-official' ),
					__( 'Publish the page.', 'kiriminaja-official' ),
				),
				'pagesTitle'       => __( 'Pages Using Tracking Shortcode', 'kiriminaja-official' ),
				'emptyTitle'       => __( 'You haven\'t configured any tracking page yet.', 'kiriminaja-official' ),
				'emptyDescription' => __( 'Add the shortcode [kiriminaja-tracking-front-page] to a page to enable order tracking for your customers.', 'kiriminaja-official' ),
				'view'             => __( 'View', 'kiriminaja-official' ),
				'edit'             => __( 'Edit', 'kiriminaja-official' ),
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function settingsToolbar( string $title ): array {
		$logo_url = defined( 'KIRIOF_URL' )
			? KIRIOF_URL . 'assets/admin/img/icon-128x128.png'
			: 'assets/admin/img/icon-128x128.png';

		return array(
			'logoUrl'   => $logo_url,
			'rootUrl'   => admin_url( 'admin.php?page=kiriminaja-konfigurasi' ),
			'rootLabel' => __( 'Settings', 'kiriminaja-official' ),
			'title'     => $title,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function prepareCouriersBootstrap(): array {
		return array(
			'view' => 'couriers',
			'toolbar' => $this->settingsToolbar( __( 'Courier List', 'kiriminaja-official' ) ),
			'i18n' => array(
				'enableAll'  => __( 'Enable All', 'kiriminaja-official' ),
				'disableAll' => __( 'Disable All', 'kiriminaja-official' ),
				'loading'    => __( 'Loading couriers…', 'kiriminaja-official' ),
				'noCouriers' => __( 'No couriers are available for this account.', 'kiriminaja-official' ),
				'loadFailed' => __( 'Could not load couriers. Reload this page and try again.', 'kiriminaja-official' ),
				'saveFailed' => __( 'Could not save courier settings.', 'kiriminaja-official' ),
				'count'      => _x( '%1$s of %2$s enabled', 'courier enabled count', 'kiriminaja-official' ),
			),
		);
	}

	/**
	 * Prepare the serializable contract for the Svelte settings root.
	 *
	 * @param bool                 $connected         Whether the integration is already connected.
	 * @param array<string, mixed> $list_data         Prepared settings-list data.
	 * @param array<string, mixed> $product_readiness Prepared product-readiness data.
	 * @return array<string, mixed>
	 */
	public function prepareRootBootstrap( bool $connected, array $list_data = array(), array $product_readiness = array() ): array {
		$base_url = admin_url( 'admin.php?page=kiriminaja-konfigurasi' );
		$bootstrap = array(
			'view'    => 'root',
			'toolbar' => $this->settingsToolbar( __( 'Settings', 'kiriminaja-official' ) ),
			'mode'    => $connected ? 'configured' : 'unconfigured',
			'helpUrl' => 'https://help.kiriminaja.com/article/setup-wordpress',
			'i18n'    => array(
				'setupKey'            => __( 'Setup Key (Secret)', 'kiriminaja-official' ),
				'setupKeyPlaceholder' => __( 'Put your setup key here', 'kiriminaja-official' ),
				'connect'             => __( 'Connect Now', 'kiriminaja-official' ),
				'connecting'          => __( 'Connecting…', 'kiriminaja-official' ),
				'howToConnect'        => __( 'How to Connect', 'kiriminaja-official' ),
				'enterSetupKey'       => __( 'Please enter a setup key.', 'kiriminaja-official' ),
				'connectionFailed'    => __( 'Connection failed. Please check your setup key.', 'kiriminaja-official' ),
				'saveFailed'          => __( 'Save failed.', 'kiriminaja-official' ),
			),
		);

		if ( ! $connected ) {
			return $bootstrap;
		}

		if ( empty( $list_data ) ) {
			$list_data = $this->prepareList();
		}
		if ( empty( $product_readiness ) ) {
			$product_readiness = $this->product_readiness_service->getReadiness();
		}
		$product_status = $product_readiness['ready']
			? __( 'All Product Configured', 'kiriminaja-official' )
			: sprintf(
				/* translators: %1$d: configured products, %2$d: total products */
				__( '%1$d / %2$d Need Action', 'kiriminaja-official' ),
				$product_readiness['configured'],
				$product_readiness['total']
			);

		$bootstrap['toggles'] = array(
			'insurance' => 'yes' === $list_data['kiriof_insurance_enabled'],
			'cod'       => 'yes' === $list_data['kiriof_cod_enabled'],
		);
		$bootstrap['productAlert'] = array(
			'title'       => __( 'Product Volumetric Configurations', 'kiriminaja-official' ),
			'description' => __( 'Set weight, length, width, and height for every product and variation.', 'kiriminaja-official' ),
			'href'        => admin_url( 'edit.php?post_type=product' ),
			'status'      => $product_status,
			'tone'        => $product_readiness['ready'] ? 'ready' : 'warning',
		);
		$bootstrap['groups'] = array(
			array(
				'label' => __( 'Configuration', 'kiriminaja-official' ),
				'items' => array(
					$this->settingsRootItem( 'account', __( 'Account Configuration', 'kiriminaja-official' ), __( 'Manage your KiriminAja account connection and profile.', 'kiriminaja-official' ), 'account', $base_url . '&section=account' ),
				),
			),
			array(
				'label' => __( 'Online Store', 'kiriminaja-official' ),
				'items' => array(
					$this->settingsRootItem( 'tracking', __( 'Tracking Page', 'kiriminaja-official' ), __( 'Configure the order tracking page for your customers.', 'kiriminaja-official' ), 'tracking', $base_url . '&section=tracking' ),
				),
			),
			array(
				'label' => __( 'Shipping', 'kiriminaja-official' ),
				'items' => array(
					$this->settingsRootItem( 'shipping-locations', __( 'WooCommerce Shipping Locations', 'kiriminaja-official' ), __( 'Set Shipping location(s) so WooCommerce can offer KiriminAja rates at checkout.', 'kiriminaja-official' ), 'shipping', admin_url( 'admin.php?page=wc-settings' ), $list_data['kiriof_shipping_locations_ready'] ? __( 'Ready', 'kiriminaja-official' ) : __( 'Action needed', 'kiriminaja-official' ), $list_data['kiriof_shipping_locations_ready'] ? 'ready' : 'warning' ),
					$this->settingsRootItem( 'couriers', __( 'Courier List', 'kiriminaja-official' ), __( 'Choose which couriers are available at checkout.', 'kiriminaja-official' ), 'courier', $base_url . '&section=couriers' ),
					$this->settingsRootItem( 'insurance', __( 'Shipping Insurance', 'kiriminaja-official' ), __( 'Require shipping insurance on all orders.', 'kiriminaja-official' ), 'insurance', '', '', '', 'insurance' ),
					$this->settingsRootItem( 'cod', __( 'Cash on Delivery', 'kiriminaja-official' ), __( 'Allow customers to pay when they receive their order.', 'kiriminaja-official' ), 'cod', '', '', '', 'cod' ),
					$this->settingsRootItem( 'locations', __( 'Manage Locations', 'kiriminaja-official' ), __( 'Set your business location for accurate shipping rates.', 'kiriminaja-official' ), 'location', admin_url( 'admin.php?page=wc-settings&tab=kiriminaja_warehouses' ) ),
				),
			),
			array(
				'label' => __( 'Others', 'kiriminaja-official' ),
				'items' => array(
					$this->settingsRootItem( 'webhooks', __( 'Webhooks', 'kiriminaja-official' ), __( 'Configure callback URL for shipment status updates.', 'kiriminaja-official' ), 'webhook', $base_url . '&section=webhooks' ),
					$this->settingsRootItem( 'technical', __( 'Technical', 'kiriminaja-official' ), __( 'Manage cache and download KiriminAja plugin-only diagnostic logs.', 'kiriminaja-official' ), 'technical', $base_url . '&section=technical' ),
				),
			),
		);

		return $bootstrap;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function prepareWebhooksBootstrap( string $callback_url ): array {
		return array(
			'view'        => 'webhooks',
			'toolbar'     => $this->settingsToolbar( __( 'Webhooks', 'kiriminaja-official' ) ),
			'callbackUrl' => $callback_url,
			'i18n'        => array(
				'callbackUrl' => __( 'Callback URL', 'kiriminaja-official' ),
				'save'        => __( 'Save', 'kiriminaja-official' ),
				'saving'      => __( 'Saving…', 'kiriminaja-official' ),
				'saved'       => __( 'Saved.', 'kiriminaja-official' ),
				'saveFailed'  => __( 'Save failed.', 'kiriminaja-official' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $technical Prepared technical diagnostics.
	 * @return array<string, mixed>
	 */
	public function prepareTechnicalBootstrap( array $technical ): array {
		return array(
			'view'           => 'technical',
			'toolbar'        => $this->settingsToolbar( __( 'Technical', 'kiriminaja-official' ) ),
			'downloadLogUrl' => $technical['downloadLogUrl'],
			'region'         => array(
				'state'         => $technical['state'],
				'lastError'     => $technical['cacheStatus']['last_error'] ?? '',
				'provinceCount' => $technical['provinceCount'],
				'cityCount'     => $technical['cityCount'],
				'updated'       => $technical['cacheStatus']['last_completed_at'] ?? '—',
				'validUntil'    => $technical['regionValidUntil'],
			),
			'couriers'       => array(
				'cached'     => $technical['courierCached'],
				'count'      => $technical['courierCount'],
				'updated'    => $technical['courierUpdated'],
				'validUntil' => $technical['courierValidUntil'],
			),
			'i18n'           => array(
				'regionTitle'        => __( 'Region Coverage Cache', 'kiriminaja-official' ),
				'regionDescription'  => __( 'Province and city data used for coupon area restrictions. Re-validate to fetch the latest data from the KiriminAja API.', 'kiriminaja-official' ),
				'courierTitle'       => __( 'Courier List Cache', 'kiriminaja-official' ),
				'courierDescription' => __( 'Courier names and types fetched from the KiriminAja API. Used for proper labelling in the transactions filter and coupon courier restrictions. Cached for 24 hours.', 'kiriminaja-official' ),
				'logsTitle'          => __( 'Diagnostic Logs', 'kiriminaja-official' ),
				'logsDescription'    => __( 'Download WooCommerce logs generated only by the KiriminAja plugin. The export excludes general WooCommerce and WordPress logs.', 'kiriminaja-official' ),
				'logsPrivacy'        => __( 'KiriminAja does not collect this diagnostic data automatically or send it directly to KiriminAja. Please download the file and send it to the KiriminAja support team only with your consent.', 'kiriminaja-official' ),
				'status'             => __( 'Status', 'kiriminaja-official' ),
				'provinces'          => __( 'Provinces', 'kiriminaja-official' ),
				'cities'             => __( 'Cities', 'kiriminaja-official' ),
				'couriers'           => __( 'couriers', 'kiriminaja-official' ),
				'lastUpdated'        => __( 'Last Updated', 'kiriminaja-official' ),
				'validUntil'         => __( 'Valid Until', 'kiriminaja-official' ),
				'refreshRegion'      => __( 'Re-validate Region Cache', 'kiriminaja-official' ),
				'scheduling'         => __( 'Scheduling…', 'kiriminaja-official' ),
				'refreshing'         => __( 'Refreshing…', 'kiriminaja-official' ),
				'cacheUpdated'       => __( 'Cache updated successfully.', 'kiriminaja-official' ),
				'refreshFailed'      => __( 'Re-validate failed.', 'kiriminaja-official' ),
				'flushCouriers'      => __( 'Flush & Re-fetch Couriers', 'kiriminaja-official' ),
				'flushing'           => __( 'Flushing…', 'kiriminaja-official' ),
				'cacheRefreshed'     => __( 'Cache refreshed.', 'kiriminaja-official' ),
				'flushFailed'        => __( 'Flush failed. Please try again.', 'kiriminaja-official' ),
				'cached'             => __( 'Cached', 'kiriminaja-official' ),
				'notCached'          => __( 'Not cached', 'kiriminaja-official' ),
				'downloadLog'        => __( 'Download Log', 'kiriminaja-official' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function settingsRootItem( string $key, string $label, string $description, string $icon, string $href = '', string $status = '', string $status_tone = '', string $toggle = '' ): array {
		$item = array(
			'key'         => $key,
			'label'       => $label,
			'description' => $description,
			'icon'        => $icon,
		);

		if ( '' !== $href ) {
			$item['href'] = $href;
		}
		if ( '' !== $status ) {
			$item['status'] = $status;
			$item['statusTone'] = $status_tone;
		}
		if ( '' !== $toggle ) {
			$item['toggle'] = $toggle;
		}

		return $item;
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
