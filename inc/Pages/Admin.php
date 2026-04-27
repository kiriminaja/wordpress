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
        
        $plugin_path = $this->plugin_path;
        
        $subPages  = [
            [
                'parent_slug'=>'kiriminaja-konfigurasi',
                'page_title'=>'KiriminAja Configuration',
                'menu_title'=>'KiriminAja',
                'capability'=>'manage_options',
                'menu_slug'=>'kiriminaja-konfigurasi',
                'callback'=> function() use ($plugin_path){
                    require_once $plugin_path.'templates/setting/index.php';
                }
            ]
        ];
        if( kiriof_check_woocommerce() ){
            $subPages = array_merge($subPages,[
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>'KiriminAja Transactions',
                    'menu_title'=>'Transactions',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja-transaction-process',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/transaction-process/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>'Payments',
                    'menu_title'=>'Payments',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja-request-pickup',
                    'callback'=> function() use ($plugin_path) {
                        require_once $plugin_path.'templates/request-pickup/index.php';
                    }
                ]
            ]);
        }
        
        
        (new PageGenerator())
            ->addPages([
                [
                    'page_title'=>'KiriminAja',
                    'menu_title'=>'KiriminAja',
                    'capability'=>'manage_options',
                    'menu_slug'=>'kiriminaja-konfigurasi',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/setting/index.php';
                    },
                    'icon_url'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik00MS45MDYyIDQxLjE5NDJDMzcuMDcyNSAzNi40MDU2IDI5LjU5OTkgMzYuMTU0NSAyNC43NjYyIDQwLjkzNjhDMTkuOTMyNSA0NS43MjU0IDIwLjE1NzggNTMuODA5NSAyNC45OTE1IDU4LjU5ODFMNTYuNjY0OCA5MC4xMjk5QzYxLjU2OTMgOTUuMDM0NCA2OS4xMTI3IDk1LjI3MjYgNzMuOTQ2NSA5MC40ODM5Qzc4Ljc4MDIgODUuNjk1MiA3OC43MzUxIDc4LjAyOTUgNzMuNzk4NCA3My4wOTI4TDQxLjkwNjIgNDEuMTk0MloiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMzcuOTM0OSAxNS44MDkxQzM2LjA3NDggMTYuMjAxOCAzNS4yNDQ1IDE4LjUzODIgMzYuNTEyNSAxOS45NDEzTDQxLjIzNjggMjQuNjY1NkwyNC43NzI1IDQwLjkzNjdDMjAuMTU3NyA0NS41NTE2IDE5LjI2MyA1Mi44NjMzIDI0Ljk5NzggNTguNTk4MUw1MC42OTE4IDg0LjIyMTJWNDkuOTU0TDU4LjY2IDQyLjA2OTVMNjMuMjA0MSA0Ni42MkM2NC40Nzg1IDQ4LjAxNjcgNjYuODAyIDQ3LjUzNCA2Ny4zODc3IDQ1LjczODJMNzguODc2NiAxMC4yMDMxQzc5LjQ2MjMgOC40MTM3NSA3Ny44NzI1IDYuNjYzMDYgNzYuMDEyNCA3LjA1NTY4TDM3LjkzNDkgMTUuODA5MVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=',
                    'position'=>56,
                ]
            ])
            ->addSubPages($subPages)
            ->register();
        
    
        /** Add pages link in plugin menu links*/
        add_filter('plugin_action_links_'.$this->plugin, function ($links){
            $settings_link = '<a href="admin.php?page=kiriminaja-konfigurasi">Settings</a>';
            array_push($links,$settings_link);
            return $links;
        });
        add_action( 'admin_head', [$this,'kiriof_add_transaction_status_count']);
    }
    function kiriof_add_transaction_status_count(){
        if ( class_exists( 'WooCommerce' ) ) {
            global $submenu;
            if ( empty( $submenu['kiriminaja-konfigurasi'] ) ) {
                return;
            }

            $transaction_count_new = (int) kiriof_helper()->kjCountTransactionProcess();
            $shipment_unpaid_count = (int) kiriof_helper()->kjCountShipmentUnpaid();

            foreach ( $submenu['kiriminaja-konfigurasi'] as $key => $menu_item ) {
                if ( $transaction_count_new > 0 && 0 === strpos( $menu_item[0], 'Transactions' ) ) {
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr( $transaction_count_new ) . '"><span class="processing-count">' . number_format_i18n( $transaction_count_new ) . '</span></span>'; // WPCS: override ok.
                    continue;
                }
                if ( $shipment_unpaid_count > 0 && 0 === strpos( $menu_item[0], 'Payments' ) ) {
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr( $shipment_unpaid_count ) . '"><span class="processing-count">' . number_format_i18n( $shipment_unpaid_count ) . '</span></span>'; // WPCS: override ok.
                    continue;
                }
            }
        }
    }
}