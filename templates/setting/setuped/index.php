<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only section navigation.
$kiriof_section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
if ( 'cache' === $kiriof_section ) {
	$kiriof_section = 'technical';
}
if ( 'webhooks' === $kiriof_section ) {
	$kiriof_section = 'technical';
}

switch ( $kiriof_section ) {
	case 'account':
		$kiriof_account_data = $settingsPageData->prepareAccount();
		$kiriof_settings_bootstrap = $settingsPageData->prepareAccountBootstrap(
			array(
				'kiriof_is_connected' => $kiriof_account_data['kiriof_is_connected'],
				'kiriof_profile'      => $kiriof_account_data['kiriof_profile'],
				'kiriof_profile_err'  => $kiriof_account_data['kiriof_profile_err'],
				'kiriof_wl_id_arr'    => $kiriof_account_data['kiriof_wl_id_arr'],
				'kiriof_wl_map'       => $kiriof_account_data['kiriof_wl_map'],
			)
		);
		break;
	case 'couriers':
		$kiriof_settings_bootstrap = $settingsPageData->prepareCouriersBootstrap();
		break;
	case 'tracking':
		$kiriof_settings_bootstrap = $settingsPageData->prepareTrackingBootstrap( kiriof_get_published_tracking_content() );
		break;
	case 'technical':
		$kiriof_technical_data = $settingsPageData->prepareTechnical();
		$kiriof_settings_bootstrap = $settingsPageData->prepareTechnicalBootstrap(
			array(
				'cacheStatus'       => $kiriof_technical_data['cacheStatus'],
				'provinceCount'     => $kiriof_technical_data['provinceCount'],
				'cityCount'         => $kiriof_technical_data['cityCount'],
				'state'             => $kiriof_technical_data['state'],
				'regionValidUntil'  => $kiriof_technical_data['regionValidUntil'],
				'downloadLogUrl'    => $kiriof_technical_data['downloadLogUrl'],
				'courierCount'      => $kiriof_technical_data['courierCount'],
				'courierCached'     => $kiriof_technical_data['courierCached'],
				'courierUpdated'    => $kiriof_technical_data['courierUpdated'],
				'courierValidUntil' => $kiriof_technical_data['courierValidUntil'],
				'callbacks'         => $kiriof_technical_data['callbacks'],
			)
		);
		break;
	case '':
		$kiriof_list_data = $settingsPageData->prepareList();
		$kiriof_settings_bootstrap = $settingsPageData->prepareRootBootstrap(
			true,
			array(
				'kiriof_cod_enabled'              => $kiriof_list_data['kiriof_cod_enabled'],
				'kiriof_insurance_enabled'        => $kiriof_list_data['kiriof_insurance_enabled'],
				'kiriof_shipping_locations_ready' => $kiriof_list_data['kiriof_shipping_locations_ready'],
				'kiriof_default_address_ready'    => $kiriof_list_data['kiriof_default_address_ready'],
				'kiriof_enabled_courier_count'    => $kiriof_list_data['kiriof_enabled_courier_count'],
			),
			$productVolumetricReadiness
		);
		break;
	default:
		$kiriof_settings_bootstrap = $settingsPageData->prepareRootBootstrap( true );
		break;
}

include KIRIOF_DIR . 'templates/setting/app.php';
