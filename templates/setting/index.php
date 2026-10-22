<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}

class Kiriof_SettingIndex {
    function __construct(){
		$settingsPageData = new \KiriminAjaOfficial\Services\SettingsPageData();
		extract( $settingsPageData->prepare(), EXTR_SKIP );
    
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only settings routing.
		$section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'warehouses' === $section ) {
			$kiriof_settings_bootstrap = ( new \KiriminAjaOfficial\Services\WarehousesPageData() )->prepare();
			include KIRIOF_DIR . 'templates/setting/app.php';
			return;
		}

        /** Return vars and view*/
        if ( ! empty( $approvedSetupKey->value ?? null ) ){
            include 'setuped/index.php';
            return;
        }
        include 'unsetuped/index.php';
    }
}


new Kiriof_SettingIndex();







?>