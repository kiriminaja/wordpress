<?php

namespace Inc\Base;

use Inc\Base\BaseInit;

class Activate {
    public function activate(){
        try {
            flush_rewrite_rules();
        }catch (\Throwable $th){}
    }
}