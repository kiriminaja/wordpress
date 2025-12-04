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
        }catch (\Throwable $th){}
    }
}