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
                    'page_title'=>'KiriminAja Transactions',
                    'menu_title'=>'Transactions',
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-transaction-process',
                    'callback'=> function() use ($plugin_path){
                        require_once $plugin_path.'templates/transaction-process/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>'Payments',
                    'menu_title'=>'Payments',
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-request-pickup',
                    'callback'=> function() use ($plugin_path) {
                        require_once $plugin_path.'templates/request-pickup/index.php';
                    }
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>'Request Pickup Detail',
                    'menu_title'=>'Request Pickup Detail',
                    'capability'=>'manage_woocommerce',
                    'menu_slug'=>'kiriminaja-request-pickup-detail',
                    'callback'=> function() use ($plugin_path) {
                        require_once $plugin_path.'templates/request-pickup-detail/index.php';
                    },
                    'hidden'=>true,
                ],
                [
                    'parent_slug'=>'kiriminaja-konfigurasi',
                    'page_title'=>'KiriminAja Settings',
                    'menu_title'=>'Settings',
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
            $settings_link = '<a href="admin.php?page=kiriminaja-konfigurasi">Settings</a>';
            array_push($links,$settings_link);
            return $links;
        });

        add_filter('plugin_row_meta', [$this, 'kiriof_plugin_row_meta'], 10, 2);
        add_action( 'admin_head', [$this,'kiriof_add_transaction_status_count']);

        // Setup checklist notice on all admin pages
        add_action( 'admin_notices', [$this, 'kiriof_setup_checklist_notice'] );

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
                    $submenu['kiriminaja-konfigurasi'][ $key ][0] = 'Settings';
                    break;
                }
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

    /**
     * Setup checklist notice shown on all admin pages.
     */
    public function kiriof_setup_checklist_notice() {
        if ( ! kiriof_check_woocommerce() ) {
            return;
        }

        $repo = new \KiriminAjaOfficial\Repositories\SettingRepository();

        // 1. Account Connection
        $setup_key_row = $repo->getSettingByKey('setup_key');
        $is_connected  = ! empty( $setup_key_row->value ?? null );

        // 2. Product LWH & Weight — check if any WC product has weight
        $has_product_weight = false;
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $product_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type IN ('product','product_variation')
               AND p.post_status = 'publish'
               AND pm.meta_key = '_weight'
               AND pm.meta_value > 0"
        );
        $has_product_weight = ( $product_count > 0 );

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

        // 5. Tracking Page
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tracking_pages = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE '%[kiriminaja-tracking-front-page%'"
        );
        $tracking_ready = ( $tracking_pages > 0 );

        $steps = array(
            array( 'label' => __( 'Account Connection', 'kiriminaja-official' ), 'done' => $is_connected, 'required' => true ),
            array( 'label' => __( 'Product Weight & Dimensions', 'kiriminaja-official' ), 'done' => $has_product_weight, 'required' => true ),
            array( 'label' => __( 'Origin Setup', 'kiriminaja-official' ), 'done' => $origin_ready, 'required' => true ),
            array( 'label' => __( 'Courier Setup', 'kiriminaja-official' ), 'done' => $courier_ready, 'required' => true ),
            array( 'label' => __( 'Tracking Page', 'kiriminaja-official' ), 'done' => $tracking_ready, 'required' => false ),
        );

        $done_count = count( array_filter( $steps, function( $s ) { return $s['done']; } ) );
        $all_required_done = true;
        foreach ( $steps as $s ) {
            if ( $s['required'] && ! $s['done'] ) { $all_required_done = false; break; }
        }

        // Hide if all required steps completed
        if ( $all_required_done ) {
            return;
        }

        $settings_url = admin_url( 'admin.php?page=kiriminaja-konfigurasi' );
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php echo esc_html__( 'KiriminAja Setup Guide', 'kiriminaja-official' ); ?></strong>
                &mdash; <?php echo esc_html( sprintf( __( '%d of %d steps completed.', 'kiriminaja-official' ), $done_count, count( $steps ) ) ); ?>
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:4px 16px;margin:8px 0;">
                <?php foreach ( $steps as $step ) : ?>
                <div style="display:flex;align-items:center;gap:6px;font-size:13px;<?php echo $step['done'] ? 'color:#787c82;' : 'color:#1d2327;'; ?>">
                    <?php if ( $step['done'] ) : ?>
                    <span style="color:#00a32a;font-size:14px;">&#10003;</span>
                    <?php else : ?>
                    <span style="color:<?php echo $step['required'] ? '#d63638' : '#c3c4c7'; ?>;font-size:14px;">&#9679;</span>
                    <?php endif; ?>
                    <span style="<?php echo $step['done'] ? 'text-decoration:line-through;' : 'font-weight:500;'; ?>"><?php echo esc_html( $step['label'] ); ?></span>
                    <?php if ( ! $step['required'] ) : ?>
                    <span style="font-size:10px;color:#8c8f94;"><?php echo esc_html__( '(Optional)', 'kiriminaja-official' ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <p>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary" style="background:#7d3eb9;border-color:#7d3eb9;"><?php echo esc_html__( 'Complete Setup', 'kiriminaja-official' ); ?></a>
            </p>
        </div>
        <?php
    }
}