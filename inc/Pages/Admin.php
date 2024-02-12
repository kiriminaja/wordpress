<?php

namespace Inc\Pages;

use \Inc\Base\BaseInit;
use \Inc\Base\PageGenerator;

class Admin extends BaseInit{
    
    public function register(){
        /** add pages*/
//        add_action('admin_menu', array($this,'add_admin_pages'));
        (new PageGenerator())
            ->addPages([
                [
                    'page_title'=>'KiriminAja',
                    'menu_title'=>'KiriminAja',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja',
                    'callback'=> function(){
                        require_once $this->plugin_path.'templates/setting/index.php';
                    },
                    'icon_url'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik00MS45MDYyIDQxLjE5NDJDMzcuMDcyNSAzNi40MDU2IDI5LjU5OTkgMzYuMTU0NSAyNC43NjYyIDQwLjkzNjhDMTkuOTMyNSA0NS43MjU0IDIwLjE1NzggNTMuODA5NSAyNC45OTE1IDU4LjU5ODFMNTYuNjY0OCA5MC4xMjk5QzYxLjU2OTMgOTUuMDM0NCA2OS4xMTI3IDk1LjI3MjYgNzMuOTQ2NSA5MC40ODM5Qzc4Ljc4MDIgODUuNjk1MiA3OC43MzUxIDc4LjAyOTUgNzMuNzk4NCA3My4wOTI4TDQxLjkwNjIgNDEuMTk0MloiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMzcuOTM0OSAxNS44MDkxQzM2LjA3NDggMTYuMjAxOCAzNS4yNDQ1IDE4LjUzODIgMzYuNTEyNSAxOS45NDEzTDQxLjIzNjggMjQuNjY1NkwyNC43NzI1IDQwLjkzNjdDMjAuMTU3NyA0NS41NTE2IDE5LjI2MyA1Mi44NjMzIDI0Ljk5NzggNTguNTk4MUw1MC42OTE4IDg0LjIyMTJWNDkuOTU0TDU4LjY2IDQyLjA2OTVMNjMuMjA0MSA0Ni42MkM2NC40Nzg1IDQ4LjAxNjcgNjYuODAyIDQ3LjUzNCA2Ny4zODc3IDQ1LjczODJMNzguODc2NiAxMC4yMDMxQzc5LjQ2MjMgOC40MTM3NSA3Ny44NzI1IDYuNjYzMDYgNzYuMDEyNCA3LjA1NTY4TDM3LjkzNDkgMTUuODA5MVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=',
                    'position'=>56,
                ]
            ])
            ->addSubPages([
                [
                    'parent_slug'=>'kiriminaja',
                    'page_title'=>'KiriminAja Configuration',
                    'menu_title'=>'KiriminAja',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja-konfigurasi',
                    'callback'=> function(){
                        require_once $this->plugin_path.'templates/setting/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja',
                    'page_title'=>'Shipment Process',
                    'menu_title'=>'Shipment Process',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja-request-pickup',
                    'callback'=> function() {
                        require_once $this->plugin_path.'templates/request-pickup/index.php';
                    }
                ]
            ])
            ->register();
        
    
        /** Add pages link in plugin menu links*/
        add_filter("plugin_action_links_$this->plugin", array($this,'settings_link'));
    }

    /**  Add Pages */
    function add_admin_pages() {
        add_menu_page('KiriminAja Plugin', 'KiriminAja','manage_options','kiriminaja_opt_settings_v2', array($this,'admin_index'),'dashicons-store',110);
    }

    /** Pages */
    function admin_index(){
        require_once $this->plugin_path.'templates/admin.php';
    }

    /** Add pages link in plugin menu links*/
    function settings_link($links){
        $settings_link = '<a href="admin.php?page=kiriminaja-konfigurasi">Settings</a>';
        array_push($links,$settings_link);
        return $links;
    }
}