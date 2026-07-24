<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Base\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PluginUpdateNoticeService extends BaseService {
    private const CACHE_KEY = 'kiriof_wordpress_org_plugin_info';
    private const CACHE_TTL = 2 * HOUR_IN_SECONDS;
    private const DISMISSED_META_KEY = 'kiriof_plugin_update_notice_dismissed_version';
    private const PLUGIN_SLUG = 'kiriminaja-official';

    public function register() {
        add_action( 'admin_init', array( $this, 'kiriof_handle_dismiss_request' ) );
        add_action( 'admin_notices', array( $this, 'kiriof_render_update_notice' ) );
    }

    public function kiriof_handle_dismiss_request() {
        if ( ! isset( $_GET['kiriof_dismiss_update_notice'], $_GET['kiriof_update_notice_version'] ) ) {
            return;
        }

        if ( ! $this->kiriof_can_show_update_notice() ) {
            return;
        }

        $version = sanitize_text_field( wp_unslash( $_GET['kiriof_update_notice_version'] ) );
        if ( '' === $version ) {
            return;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'kiriof_dismiss_update_notice_' . $version ) ) {
            return;
        }

        update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, $version );

        wp_safe_redirect(
            remove_query_arg(
                array(
                    'kiriof_dismiss_update_notice',
                    'kiriof_update_notice_version',
                    '_wpnonce',
                )
            )
        );
        exit;
    }

    public function kiriof_render_update_notice() {
        if ( ! $this->kiriof_can_show_update_notice() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! $this->kiriof_is_allowed_screen( $screen->id ) ) {
            return;
        }

        $plugin_info = $this->kiriof_get_wordpress_org_plugin_info();
        if ( ! $plugin_info || empty( $plugin_info->version ) ) {
            return;
        }

        $latest_version = sanitize_text_field( (string) $plugin_info->version );
        if ( '' === $latest_version || version_compare( $latest_version, KIRIOF_VERSION, '<=' ) ) {
            return;
        }

        if ( $this->kiriof_is_dismissed( $latest_version ) ) {
            return;
        }

        $wordpress_org_url = ! empty( $plugin_info->homepage )
            ? esc_url( $plugin_info->homepage )
            : esc_url( 'https://wordpress.org/plugins/' . self::PLUGIN_SLUG . '/' );

        $notice_class = $this->kiriof_has_native_update( $latest_version )
            ? 'notice notice-success is-dismissible'
            : 'notice notice-info is-dismissible';

        $dismiss_url = wp_nonce_url(
            add_query_arg(
                array(
                    'kiriof_dismiss_update_notice' => '1',
                    'kiriof_update_notice_version'  => rawurlencode( $latest_version ),
                )
            ),
            'kiriof_dismiss_update_notice_' . $latest_version
        );

        echo '<div class="' . esc_attr( $notice_class ) . '">';
        echo '<p><strong>' . esc_html( sprintf( __( 'KiriminAja Official %s is available.', 'kiriminaja-official' ), $latest_version ) ) . '</strong></p>';

        if ( $this->kiriof_has_native_update( $latest_version ) ) {
            echo '<p>' . esc_html__( 'WordPress has already received this update. Open Plugins to install it from the WordPress dashboard.', 'kiriminaja-official' ) . '</p>';
            echo '<p>';
            echo '<a class="button button-primary" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Open Plugins screen', 'kiriminaja-official' ) . '</a> ';
            echo '<a class="button button-link" href="' . esc_url( $wordpress_org_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on WordPress.org', 'kiriminaja-official' ) . '</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss notice', 'kiriminaja-official' ) . '</a>';
            echo '</p>';
        } else {
            echo '<p>' . esc_html__( 'WordPress.org shows this release, but this site has not received the update yet.', 'kiriminaja-official' ) . '</p>';
            echo '<p>' . esc_html__( 'WordPress.org can take up to 24 hours to publish new plugin releases through the update system.', 'kiriminaja-official' ) . '</p>';
            echo '<p>' . esc_html__( 'If you do not see this version in Plugins, open the WordPress.org plugin page and install it manually.', 'kiriminaja-official' ) . '</p>';
            echo '<p>';
            echo '<a class="button button-primary" href="' . esc_url( $wordpress_org_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View on WordPress.org', 'kiriminaja-official' ) . '</a> ';
            echo '<a class="button button-link" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Open Plugins screen', 'kiriminaja-official' ) . '</a> ';
            echo '<a class="button button-link-delete" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss notice', 'kiriminaja-official' ) . '</a>';
            echo '</p>';
        }

        echo '</div>';
    }

    private function kiriof_can_show_update_notice(): bool {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'update_plugins' );
    }

    private function kiriof_is_allowed_screen( string $screen_id ): bool {
        return 'plugins' === $screen_id
            || 'plugins-network' === $screen_id
            || false !== strpos( $screen_id, 'kiriminaja' );
    }

    private function kiriof_get_wordpress_org_plugin_info() {
        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_object( $cached ) && ! empty( $cached->version ) ) {
            return $cached;
        }

        if ( ! function_exists( 'plugins_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $info = plugins_api(
            'plugin_information',
            array(
                'slug'   => self::PLUGIN_SLUG,
                'fields' => array(
                    'version'       => true,
                    'download_link' => true,
                    'homepage'      => true,
                    'last_updated'  => true,
                ),
            )
        );

        if ( is_wp_error( $info ) || ! is_object( $info ) || empty( $info->version ) || empty( $info->download_link ) ) {
            return null;
        }

        $download_host = wp_parse_url( esc_url_raw( (string) $info->download_link ), PHP_URL_HOST );
        if ( self::PLUGIN_SLUG !== (string) ( $info->slug ?? '' ) || 'downloads.wordpress.org' !== $download_host ) {
            return null;
        }

        set_site_transient( self::CACHE_KEY, $info, self::CACHE_TTL );

        return $info;
    }

    private function kiriof_has_native_update( string $latest_version ): bool {
        $updates = get_site_transient( 'update_plugins' );
        if ( ! is_object( $updates ) || empty( $updates->response[ KIRIOF_PLUGIN_BASENAME ] ) ) {
            return false;
        }

        $response = $updates->response[ KIRIOF_PLUGIN_BASENAME ];
        $version = isset( $response->new_version ) ? (string) $response->new_version : ( isset( $response->version ) ? (string) $response->version : '' );

        return '' !== $version && version_compare( $version, KIRIOF_VERSION, '>' ) && version_compare( $version, $latest_version, '>=' );
    }

    private function kiriof_is_dismissed( string $latest_version ): bool {
        $dismissed_version = (string) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );

        return '' !== $dismissed_version && $dismissed_version === $latest_version;
    }
}
