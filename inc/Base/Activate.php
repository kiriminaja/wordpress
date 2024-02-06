<?php

namespace Inc\Base;

use Inc\Base\BaseInit;

class Activate {
    public static function activate(){
        flush_rewrite_rules();
    }
}