<?php
namespace KiriminAjaOfficial\Base;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activate {
    public function activate(){
        try {
            flush_rewrite_rules();
            if ( class_exists( '\\KiriminAjaOfficial\\Services\\ShippingDiscountRegionCacheService' ) ) {
                ( new \KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService() )->refreshAll();
            }
        }catch (\Throwable $th){}
    }
}