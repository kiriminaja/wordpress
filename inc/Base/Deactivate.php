<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivate {
    public function deactivate(){
        try {
            flush_rewrite_rules();
            wp_clear_scheduled_hook( \KiriminAjaOfficial\Services\OriginAddressMigrationService::HOOK );
        }catch (\Throwable $th){}
    }
}