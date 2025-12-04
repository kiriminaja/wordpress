<?php
namespace KiriminAjaOfficial\Pages;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use \KiriminAjaOfficial\Base\BaseInit;
use \KiriminAjaOfficial\Base\PageGenerator;
class Admin extends BaseInit{
    
    public function register(){
        /** add pages*/
        
        $subPages =[];
        if(KJ_CHECK_WOOCOMMERCE()){
            $subPages = [
                [
                    'parent_slug'=>'kiriminaja',
                    'page_title'=>'KiriminAja Transaction Process',
                    'menu_title'=>'Transactions'. $this->transaction_count(),
                    'capability'=>'manage_options',
                    'menu_slug'=>'kaj-transactions',
                    'callback'=> function(){
                        require_once $this->plugin_path.'templates/transaction-process/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja',
                    'page_title'=>'Payments',
                    'menu_title'=>'Payments',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kaj-payment',
                    'callback'=> function() {
                        require_once $this->plugin_path.'templates/request-pickup/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja',
                    'page_title'=>'Tracking',
                    'menu_title'=>'Tracking',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kaj-tracking',
                    'callback'=> function() {
                        require_once $this->plugin_path.'templates/tracking/index.php';
                    }
                ]
            ];
        }


        $subPages[] = [
            'parent_slug'=>'kiriminaja',
            'page_title'=>'KiriminAja Settings',
            'menu_title'=>'Settings',
            'capability'=>'manage_options',
            'menu_slug'=>'kaj-settings',
            'callback'=> function(){
                require_once $this->plugin_path.'templates/setting/index.php';
            }
        ];        
        
        (new PageGenerator())
            ->addPages([
                [
                    'page_title'=>'KiriminAja',
                    'menu_title'=>'KiriminAja'. $this->transaction_count(),
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja',
                    'callback'=> function(){
                        require_once $this->plugin_path.'templates/setting/index.php';
                    },
                    'icon_url'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik00MS45MDYyIDQxLjE5NDJDMzcuMDcyNSAzNi40MDU2IDI5LjU5OTkgMzYuMTU0NSAyNC43NjYyIDQwLjkzNjhDMTkuOTMyNSA0NS43MjU0IDIwLjE1NzggNTMuODA5NSAyNC45OTE1IDU4LjU5ODFMNTYuNjY0OCA5MC4xMjk5QzYxLjU2OTMgOTUuMDM0NCA2OS4xMTI3IDk1LjI3MjYgNzMuOTQ2NSA5MC40ODM5Qzc4Ljc4MDIgODUuNjk1MiA3OC43MzUxIDc4LjAyOTUgNzMuNzk4NCA3My4wOTI4TDQxLjkwNjIgNDEuMTk0MloiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMzcuOTM0OSAxNS44MDkxQzM2LjA3NDggMTYuMjAxOCAzNS4yNDQ1IDE4LjUzODIgMzYuNTEyNSAxOS45NDEzTDQxLjIzNjggMjQuNjY1NkwyNC43NzI1IDQwLjkzNjdDMjAuMTU3NyA0NS41NTE2IDE5LjI2MyA1Mi44NjMzIDI0Ljk5NzggNTguNTk4MUw1MC42OTE4IDg0LjIyMTJWNDkuOTU0TDU4LjY2IDQyLjA2OTVMNjMuMjA0MSA0Ni42MkM2NC40Nzg1IDQ4LjAxNjcgNjYuODAyIDQ3LjUzNCA2Ny4zODc3IDQ1LjczODJMNzguODc2NiAxMC4yMDMxQzc5LjQ2MjMgOC40MTM3NSA3Ny44NzI1IDYuNjYzMDYgNzYuMDEyNCA3LjA1NTY4TDM3LjkzNDkgMTUuODA5MVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=',
                    'position'=>40,
                ]
            ])
            ->addSubPages($subPages)
            ->register();
        
    
        /** Add pages link in plugin menu links*/
        add_filter('plugin_action_links_'.$this->plugin, function ($links){
            $settings_link = '<a href="admin.php?page=settings">Settings</a>';
            array_push($links,$settings_link);
            return $links;
        });
    }

    public function transaction_count(){
        $count = 0;
        if (class_exists( 'WooCommerce' )) {
            $count = kjHelper()->kjCountTransactionProcess();
        }
        if ($count==0){
            return '';
        }
        return '<span class="update-plugins"><span class="plugin-count">' . esc_html( $count ) . '</span></span>';
    }
}