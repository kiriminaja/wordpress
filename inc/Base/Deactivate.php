<?php

namespace Inc\Base;

class Deactivate {
    public function deactivate(){
        try {
            flush_rewrite_rules();
        }catch (\Throwable $th){}
    }
}