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
        }catch (\Throwable $th){}
    }
}