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