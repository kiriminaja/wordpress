<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kiriof_settings_bootstrap = $settingsPageData->prepareRootBootstrap( false );
include KIRIOF_DIR . 'templates/setting/app.php';
