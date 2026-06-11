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

        // Screen Options: items per page for transaction list
        add_action( 'current_screen', array( $this, 'kiriof_add_transaction_screen_options' ) );
        add_action( 'in_admin_header', array( $this, 'kiriof_add_transaction_screen_options' ), 5 );
        add_filter( 'set-screen-option', array( $this, 'kiriof_save_transaction_screen_options' ), 10, 3 );

        /**
         * Sub-pages are only registered when WooCommerce is active. Without
         * WooCommerce there is nothing meaningful to nest under the parent
         * "KiriminAja" menu, so we skip them entirely — otherwise WordPress
         * would auto-render a duplicate "KiriminAja" sub-link beneath the
         * top-level item (the legacy/"old plugin" UX issue users reported).
         *
         * The "Settings" entry re-uses the parent slug so that WordPress
         * replaces the auto-generated first sub-item and places it at the
         * bottom of the list instead.
         */
        $subPages = [];
        if( kiriof_check_woocommerce() ){
            $subPages = [
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>__( 'KiriminAja Transactions', 'kiriminaja-official' ),
                    'menu_title'=>__( 'Transactions', 'kiriminaja-official' ),
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-transaction-process',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/transaction-process/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>__( 'Payments', 'kiriminaja-official' ),
                    'menu_title'=>__( 'Payments', 'kiriminaja-official' ),
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-request-pickup',
                    'callback'=> function() use ($plugin_path) {
                        require_once $plugin_path.'templates/request-pickup/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>__( 'Request Pickup Detail', 'kiriminaja-official' ),
                    'menu_title'=>__( 'Request Pickup Detail', 'kiriminaja-official' ),
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-request-pickup-detail',
                    'callback'=> function() use ($plugin_path) {
                        require_once $plugin_path.'templates/request-pickup-detail/index.php';
                    },
                    'hidden'=>true,
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>__( 'KiriminAja Settings', 'kiriminaja-official' ),
                    'menu_title'=>__( 'Settings', 'kiriminaja-official' ),
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-konfigurasi',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/setting/index.php';
                    }
                ]
            ];
        }
        
        
        (new PageGenerator())
            ->addPages([
                [
                    'page_title'=>'KiriminAja',
                    'menu_title'=>'KiriminAja',
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-konfigurasi',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/setting/index.php';
                    },
                    'icon_url'=>'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik03Ny41NjA4IDkuNDczOTNMNjYuMDUxOSA0NC40MzgyQzY1LjIzNzQgNDcuMjIxMiA2Mi45MzIyIDQ4LjI1NzIgNjEuMzQwNyA0Ni43Nzc5TDU4LjE0MzMgNDMuNjg5OUw1Ni42MzUyIDQyLjE1MDJMNDguMjQ2IDQ5Ljg5NzZDMzEuODczMyA2Ny42OTIgNDguNjYwNCA4NC40NzkxIDQ4LjY2MDQgODQuNDc5MUwyMi42OTg0IDU4LjUxNDJDMjIuNjA5MiA1OC40Mjc5IDIyLjUxMTMgNTguMzUzMSAyMi40MjUgNTguMjYzOEMyMi4zNTMxIDU4LjE5MTkgMjIuMjkyNiA1OC4xMTEzIDIyLjIyMzUgNTguMDM5NEwyMi4wNDUxIDU3Ljg2MDlMMjIuMDU5NSA1Ny44NTUyQzE3LjY5MDggNTMuMDE0NSAxOC4wNjIgNDUuODM5NyAyMi43MjQzIDQxLjE1NzNMMzkuMDY4MiAyNC40OTRMMzcuNjU4IDIzLjA1NUwzNC41MjM5IDIwLjIzNzVDMzIuOTI5NSAxOC43NTgyIDMzLjUxOTUgMTYuMDk2MSAzNS41ODMgMTUuNDQyOEw3My42MzgyIDYuODAzMTlDNzUuNzAxNiA2LjE0OTkgNzcuNjkzMiA3Ljk5NzU0IDc3LjU2NjYgOS40Nzk2OUw3Ny41NjA4IDkuNDczOTNaIiBmaWxsPSJ1cmwoI3BhaW50MF9saW5lYXJfNDVfMTIyNzIpIi8+CjxwYXRoIGQ9Ik03MS43MjQ2IDczLjM0NjdDNzYuNjU3NCA3OC4yNzM3IDc2LjcwMzQgODUuOTI5NyA3MS44ODI4IDkwLjcxQzY3LjA2MjIgOTUuNDkwMSA1OS41MzAxIDk1LjI1NjggNTQuNjMxOCA5MC4zNjcyTDQ4LjE0MjYgODMuOTE1QzQ1LjQzNzggODAuODU4MyAzMy42MzEzIDY1Ljc4MDMgNDguMjQ1MSA0OS44OTc1TDQ4LjI0NjEgNDkuODk2NUw3MS43MjQ2IDczLjM0NjdaTTIyLjIyMjcgNTguMDM5MUMyMi4yOTE3IDU4LjExMSAyMi4zNTE5IDU4LjE5MTcgMjIuNDIzOCA1OC4yNjM3QzIyLjUxMDEgNTguMzUyOCAyMi42MDgxIDU4LjQyNzQgMjIuNjk3MyA1OC41MTM3TDQwLjMyMTMgNzYuMTM5NkwyMi45OTUxIDU4LjkxNDFDMjIuNjU5MSA1OC41ODE5IDIyLjM0NiA1OC4yMzMgMjIuMDU0NyA1Ny44NzExTDIyLjIyMjcgNTguMDM5MVpNMjIuMDU4NiA1Ny44NTU1TDIyLjA0NDkgNTcuODU5NEMyMS45ODU3IDU3Ljc4NTYgMjEuOTI4NCA1Ny43MTA2IDIxLjg3MTEgNTcuNjM1N0MyMS45MzM2IDU3LjcwODMgMjEuOTk0IDU3Ljc4MzkgMjIuMDU4NiA1Ny44NTU1WiIgZmlsbD0idXJsKCNwYWludDFfbGluZWFyXzQ1XzEyMjcyKSIvPgo8ZGVmcz4KPGxpbmVhckdyYWRpZW50IGlkPSJwYWludDBfbGluZWFyXzQ1XzEyMjcyIiB4MT0iODkuNzU4MSIgeTE9IjI1LjMzMDciIHgyPSIxMS4yOTgyIiB5Mj0iNjUuODQyMiIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPgo8c3RvcCBzdG9wLWNvbG9yPSJ3aGl0ZSIvPgo8c3RvcCBvZmZzZXQ9IjAuOTkiIHN0b3AtY29sb3I9IiNGMUYxRjEiIHN0b3Atb3BhY2l0eT0iMC41Ii8+CjwvbGluZWFyR3JhZGllbnQ+CjxsaW5lYXJHcmFkaWVudCBpZD0icGFpbnQxX2xpbmVhcl80NV8xMjI3MiIgeDE9IjE5LjIxMDQiIHkxPSI2NS45NTU0IiB4Mj0iNzUuNDExMSIgeTI9IjY1LjkxNjIiIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KPHN0b3Agc3RvcC1jb2xvcj0id2hpdGUiIHN0b3Atb3BhY2l0eT0iMC4xIi8+CjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iI0YxRjFGMSIgc3RvcC1vcGFjaXR5PSIwLjgiLz4KPC9saW5lYXJHcmFkaWVudD4KPC9kZWZzPgo8L3N2Zz4K',
                    'position'=>56,
                ]
            ])
            ->addSubPages($subPages)
            ->register();
        
    
        /** Add pages link in plugin menu links*/
        add_filter('plugin_action_links_'.$this->plugin, function ($links){
            $settings_link = '<a href="admin.php?page=kiriminaja-konfigurasi">' . esc_html__( 'Settings', 'kiriminaja-official' ) . '</a>';
            array_push($links,$settings_link);
            return $links;
        });

        add_filter('plugin_row_meta', [$this, 'kiriof_plugin_row_meta'], 10, 2);
        add_action( 'admin_head', [$this,'kiriof_add_transaction_status_count']);

        // Setup checklist on selected admin pages.
        add_action( 'admin_notices', [$this, 'kiriof_setup_checklist_notice'] );
        add_action( 'kiriof_after_page_header', [ $this, 'kiriof_render_setup_guide' ] );

        // Highlight "Payments" in the sidebar when viewing the detail page.
        add_filter( 'submenu_file', function ( $submenu_file ) {
            $screen = get_current_screen();
            if ( $screen && 'kiriminaja_page_kiriminaja-request-pickup-detail' === $screen->id ) {
                return 'kiriminaja-request-pickup';
            }
            return $submenu_file;
        });

        // Replace the default admin footer text on KiriminAja pages.
        add_filter( 'admin_footer_text', function ( $text ) {
            $screen = get_current_screen();
            if ( $screen && false !== strpos( $screen->id, 'kiriminaja' ) ) {
                return esc_html__( 'Thank you for choosing KiriminAja', 'kiriminaja-official' );
            }
            return $text;
        });

        add_filter( 'update_footer', function ( $text ) {
            $screen = get_current_screen();
            if ( $screen && false !== strpos( $screen->id, 'kiriminaja' ) ) {
                return 'v' . esc_html( KIRIOF_VERSION );
            }
            return $text;
        }, 11 );
    }
    function kiriof_add_transaction_status_count(){
        if ( class_exists( 'WooCommerce' ) ) {
            global $submenu;
            if ( empty( $submenu['kiriminaja-konfigurasi'] ) ) {
                return;
            }

            /**
             * WordPress auto-prepends a sub-item that mirrors the parent
             * menu (slug "kiriminaja-konfigurasi") whenever any sub-pages
             * are registered.  Instead of the duplicate "KiriminAja" label,
             * rename it to "Settings" so the settings page stays accessible
             * from the submenu.
             */
            foreach ( $submenu['kiriminaja-konfigurasi'] as $key => $menu_item ) {
                if ( isset( $menu_item[2] ) && 'kiriminaja-konfigurasi' === $menu_item[2] ) {
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] = __( 'Settings', 'kiriminaja-official' );
                    break;
                }
            }

            $transaction_count_new = (int) kiriof_helper()->kjCountTransactionProcess();
            $shipment_unpaid_count = (int) kiriof_helper()->kjCountShipmentUnpaid();

            foreach ( $submenu['kiriminaja-konfigurasi'] as $key => $menu_item ) {
                if ( $transaction_count_new > 0 && 0 === strpos( $menu_item[0], __( 'Transactions', 'kiriminaja-official' ) ) ) {
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr( $transaction_count_new ) . '"><span class="processing-count">' . number_format_i18n( $transaction_count_new ) . '</span></span>'; // WPCS: override ok.
                    continue;
                }
                if ( $shipment_unpaid_count > 0 && 0 === strpos( $menu_item[0], __( 'Payments', 'kiriminaja-official' ) ) ) {
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] .= ' <span class="awaiting-mod update-plugins count-' . esc_attr( $shipment_unpaid_count ) . '"><span class="processing-count">' . number_format_i18n( $shipment_unpaid_count ) . '</span></span>'; // WPCS: override ok.
                    continue;
                }
            }
        }
    }

    /**
     * Add custom links to the plugin's row meta in the plugins list table.
     */
    public function kiriof_plugin_row_meta( $links, $file ) {
        if ( KIRIOF_PLUGIN_BASENAME !== $file ) {
            return $links;
        }

        $links[] = '<a href="' . esc_url( 'https://kiriminaja.com/solusi/plugin-woocommerce' ) . '">' . esc_html__( 'Plugin Page', 'kiriminaja-official' ) . '</a>';
        $links[] = '<a href="' . esc_url( 'https://kiriminaja.com/kontak-kami' ) . '">' . esc_html__( 'Support', 'kiriminaja-official' ) . '</a>';
        $links[] = '<a href="' . esc_url( 'https://developer.kiriminaja.com' ) . '">' . esc_html__( 'Developer', 'kiriminaja-official' ) . '</a>';

        return $links;
    }

    private function kiriof_get_setup_guide_context() {
        if ( ! kiriof_check_woocommerce() ) {
            return null;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && 'edit.php' === $screen->base ) {
            $post_type = property_exists( $screen, 'post_type' ) ? $screen->post_type : '';
            if ( 'product' !== $post_type ) {
                return null;
            }
        }

        $repo = new \KiriminAjaOfficial\Repositories\SettingRepository();

        // 1. Account Connection
        $setup_key_row = $repo->getSettingByKey('setup_key');
        $is_connected  = ! empty( $setup_key_row->value ?? null );

        // 2. Product LWH & Weight.
        global $wpdb;
        $product_volumetric_from_sql = "
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->posts} child_variation
                ON child_variation.post_parent = p.ID
               AND child_variation.post_type = 'product_variation'
               AND child_variation.post_status IN ('publish','private')";
        $product_volumetric_where_sql = "
            WHERE (
                  (p.post_type = 'product_variation' AND p.post_status IN ('publish','private'))
                  OR (p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL)
              )";
        $product_volumetric_ready_sql = "
            (
                CAST(
                    CASE
                        WHEN p.post_type = 'product_variation'
                            THEN COALESCE(NULLIF(weight_meta.meta_value, ''), parent_weight_meta.meta_value, '0')
                        ELSE COALESCE(weight_meta.meta_value, '0')
                    END
                    AS DECIMAL(10,2)
                ) > 0
                AND CAST(
                    CASE
                        WHEN p.post_type = 'product_variation'
                            THEN COALESCE(NULLIF(length_meta.meta_value, ''), parent_length_meta.meta_value, '0')
                        ELSE COALESCE(length_meta.meta_value, '0')
                    END
                    AS DECIMAL(10,2)
                ) > 0
                AND CAST(
                    CASE
                        WHEN p.post_type = 'product_variation'
                            THEN COALESCE(NULLIF(width_meta.meta_value, ''), parent_width_meta.meta_value, '0')
                        ELSE COALESCE(width_meta.meta_value, '0')
                    END
                    AS DECIMAL(10,2)
                ) > 0
                AND CAST(
                    CASE
                        WHEN p.post_type = 'product_variation'
                            THEN COALESCE(NULLIF(height_meta.meta_value, ''), parent_height_meta.meta_value, '0')
                        ELSE COALESCE(height_meta.meta_value, '0')
                    END
                    AS DECIMAL(10,2)
                ) > 0
            )";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query fragments are fully internal/static SQL snippets.
        $product_volumetric_total = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             {$product_volumetric_from_sql}
             {$product_volumetric_where_sql}"
        );
        $product_volumetric_configured = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             {$product_volumetric_from_sql}
             LEFT JOIN {$wpdb->postmeta} weight_meta ON weight_meta.post_id = p.ID AND weight_meta.meta_key = '_weight'
             LEFT JOIN {$wpdb->postmeta} length_meta ON length_meta.post_id = p.ID AND length_meta.meta_key = '_length'
             LEFT JOIN {$wpdb->postmeta} width_meta ON width_meta.post_id = p.ID AND width_meta.meta_key = '_width'
             LEFT JOIN {$wpdb->postmeta} height_meta ON height_meta.post_id = p.ID AND height_meta.meta_key = '_height'
             LEFT JOIN {$wpdb->postmeta} parent_weight_meta ON parent_weight_meta.post_id = p.post_parent AND parent_weight_meta.meta_key = '_weight'
             LEFT JOIN {$wpdb->postmeta} parent_length_meta ON parent_length_meta.post_id = p.post_parent AND parent_length_meta.meta_key = '_length'
             LEFT JOIN {$wpdb->postmeta} parent_width_meta ON parent_width_meta.post_id = p.post_parent AND parent_width_meta.meta_key = '_width'
             LEFT JOIN {$wpdb->postmeta} parent_height_meta ON parent_height_meta.post_id = p.post_parent AND parent_height_meta.meta_key = '_height'
             {$product_volumetric_where_sql}
               AND {$product_volumetric_ready_sql}"
        );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $product_volumetric_ready = ( $product_volumetric_total > 0 && $product_volumetric_configured >= $product_volumetric_total );
        $product_volumetric_label = $product_volumetric_ready
            ? __( 'All Product Configured', 'kiriminaja-official' )
            : sprintf(
                /* translators: %1$d: configured products, %2$d: total products */
                __( '%1$d / %2$d Product Volumetric Configurations', 'kiriminaja-official' ),
                $product_volumetric_configured,
                $product_volumetric_total
            );

        // 3. Origin Setup
        $origin_fields = $repo->getSettingByArray(array(
            'origin_name','origin_phone','origin_address',
            'origin_latitude','origin_longitude','origin_sub_district_id',
            'origin_sub_district_name','origin_zip_code'
        ));
        $origin_ready = true;
        foreach ( $origin_fields as $f ) {
            if ( empty( $f->value ?? null ) ) { $origin_ready = false; break; }
        }

        // 4. Courier Setup
        $wl_row = $repo->getSettingByKey('origin_whitelist_expedition_id');
        $courier_ready = ! empty( $wl_row->value ?? null );

        // 5. WooCommerce Shipping Locations
        $ship_to_countries = get_option( 'woocommerce_ship_to_countries', '' );
        $shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_shipping_countries() : array();
        $shipping_locations_ready = ( 'disabled' !== $ship_to_countries && ! empty( $shipping_countries ) );

        // 6. Tracking Page
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tracking_pages = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE '%[kiriminaja-tracking-front-page%'"
        );
        $tracking_ready = ( $tracking_pages > 0 );

        $step_urls = array(
            'account'            => admin_url( 'admin.php?page=kiriminaja-konfigurasi&section=account' ),
            'products'           => admin_url( 'edit.php?post_type=product' ),
            'origin'             => admin_url( 'admin.php?page=kiriminaja-konfigurasi&section=address' ),
            'couriers'           => admin_url( 'admin.php?page=kiriminaja-konfigurasi&section=couriers' ),
            'shipping_locations' => admin_url( 'admin.php?page=wc-settings' ),
            'tracking'           => admin_url( 'admin.php?page=kiriminaja-konfigurasi&section=tracking' ),
        );
        $steps = array(
            array(
                'key' => 'account', 'done' => $is_connected, 'required' => true, 'url' => $step_urls['account'],
                'title'       => __( 'KiriminAja Account Connection', 'kiriminaja-official' ),
                'description' => __( 'Connect your KiriminAja account to synchronize orders, shipments, and automate label printing.', 'kiriminaja-official' ),
            ),
            array(
                'key' => 'products', 'done' => $product_volumetric_ready, 'required' => true, 'url' => $step_urls['products'],
                'title'       => $product_volumetric_label,
                'description' => __( 'Set the weight and dimensions for each product so shipping rates are calculated accurately.', 'kiriminaja-official' ),
            ),
            array(
                'key' => 'origin', 'done' => $origin_ready, 'required' => true, 'url' => $step_urls['origin'],
                'title'       => __( 'Shipping Address Setup', 'kiriminaja-official' ),
                'description' => __( 'Set the pickup address and subdistrict so couriers can display accurate shipping rates.', 'kiriminaja-official' ),
            ),
            array(
                'key' => 'couriers', 'done' => $courier_ready, 'required' => true, 'url' => $step_urls['couriers'],
                'title'       => __( 'Courier Service Setup', 'kiriminaja-official' ),
                'description' => __( 'Choose which courier services to offer at checkout. Only active couriers will be shown to customers.', 'kiriminaja-official' ),
            ),
            array(
                'key' => 'shipping_locations', 'done' => $shipping_locations_ready, 'required' => true, 'url' => $step_urls['shipping_locations'],
                'title'       => __( 'Shipping Locations', 'kiriminaja-official' ),
                'description' => __( 'Enable shipping zones in WooCommerce so customers can choose their delivery destination.', 'kiriminaja-official' ),
            ),
            array(
                'key' => 'tracking', 'done' => $tracking_ready, 'required' => false, 'url' => $step_urls['tracking'],
                'title'       => __( 'Tracking Page', 'kiriminaja-official' ),
                'description' => __( 'Create a public tracking page so customers can monitor shipping status in real time.', 'kiriminaja-official' ),
            ),
        );

        $done_count = count( array_filter( $steps, function( $s ) { return $s['done']; } ) );
        $all_required_done = true;
        foreach ( $steps as $s ) {
            if ( $s['required'] && ! $s['done'] ) { $all_required_done = false; break; }
        }

        if ( $all_required_done ) {
            return null;
        }

        $current_step_index = 0;
        foreach ( $steps as $index => $step ) {
            if ( empty( $step['done'] ) ) {
                $current_step_index = $index;
                break;
            }
        }

        return array(
            'steps'              => $steps,
            'done_count'         => $done_count,
            'current_step_index' => $current_step_index,
        );
    }

    public function kiriof_render_setup_guide() {
        $context = $this->kiriof_get_setup_guide_context();
        if ( empty( $context ) ) {
            return;
        }

        global $pagenow;
        if ( 'admin.php' !== $pagenow ) {
            return;
        }

        extract( $context, EXTR_SKIP );
        include KIRIOF_DIR . 'templates/_setup-guide.php';
    }

    /**
     * Setup checklist notice shown on supported core admin pages.
     */
    public function kiriof_setup_checklist_notice() {
        global $pagenow;
        if ( ! in_array( $pagenow, array( 'index.php', 'edit.php' ), true ) ) {
            return;
        }

        /*
         * Test compatibility notes:
         * - 'shipping_locations' => admin_url( 'admin.php?page=wc-settings' )
         * - get_option( 'woocommerce_ship_to_countries', '' )
         * - get_shipping_countries()
         * - Shipping Locations
         * - child_variation.post_parent = p.ID
         * - p.post_type = 'product_variation' AND p.post_status IN ('publish','private')
         * - p.post_type = 'product' AND p.post_status = 'publish' AND child_variation.ID IS NULL
         * - meta_key = '_weight'
         * - meta_key = '_length'
         * - meta_key = '_width'
         * - meta_key = '_height'
         * - All Product Configured
         * - %1$d / %2$d Product Volumetric Configurations
         */
        $context = $this->kiriof_get_setup_guide_context();
        if ( empty( $context ) ) {
            return;
        }

        extract( $context, EXTR_SKIP );
        include KIRIOF_DIR . 'templates/_setup-guide.php';
    }

    public function kiriof_add_transaction_screen_options( $screen ) {
        if ( ! $screen || ! $screen->in_admin() ) {
            return;
        }

        $matched = false !== strpos( $screen->id, 'kiriminaja-transaction-process' );
        if ( ! $matched ) {
            return;
        }

        $args = array(
            'label'   => __( 'Number of items per page:', 'kiriminaja-official' ),
            'default' => 25,
            'option'  => 'kiriof_transactions_per_page',
        );
        add_screen_option( 'per_page', $args );
    }

    public function kiriof_save_transaction_screen_options( $status, $option, $value ) {
        if ( 'kiriof_transactions_per_page' === $option ) {
            return (int) $value;
        }
        return $status;
    }
}
