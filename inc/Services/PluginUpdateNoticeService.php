<?php

namespace KiriminAjaOfficial\Services;

use KiriminAjaOfficial\Base\BaseService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PluginUpdateNoticeService extends BaseService {
    private const CACHE_KEY = 'kiriof_wordpress_org_plugin_info_v2';
    private const CACHE_TTL = 2 * HOUR_IN_SECONDS;
    private const DISMISSED_META_KEY = 'kiriof_plugin_update_notice_dismissed_version';
    private const PLUGIN_SLUG = 'kiriminaja-official';

    public function register() {
        add_action( 'admin_init', array( $this, 'kiriof_handle_dismiss_request' ) );
        add_action( 'admin_notices', array( $this, 'kiriof_render_update_notice' ) );
        add_action( 'network_admin_notices', array( $this, 'kiriof_render_update_notice' ) );
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
        $update = $this->get_toolbar_update();
        if ( ! $update ) {
            return;
        }

        $notice_class = $update['hasNativeUpdate']
            ? 'notice notice-success is-dismissible'
            : 'notice notice-info is-dismissible';

        echo '<div class="' . esc_attr( $notice_class ) . '">';
        echo '<p><strong>' . esc_html( $update['title'] ) . '</strong></p>';

        echo '<p>' . esc_html( $update['description'] ) . '</p>';
        foreach ( $update['details'] as $detail ) {
            echo '<p>' . esc_html( $detail ) . '</p>';
        }
        echo '<p>';
        echo '<a class="button button-primary" href="' . esc_url( $update['primaryUrl'] ) . '"';
        if ( $update['primaryExternal'] ) {
            echo ' target="_blank" rel="noopener noreferrer"';
        }
        echo '>' . esc_html( $update['primaryLabel'] ) . '</a> ';
        echo '<a class="button button-link-delete" href="' . esc_url( $update['dismissUrl'] ) . '">' . esc_html( $update['dismissLabel'] ) . '</a>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Prepare the update state for the shared Svelte workspace toolbar.
     *
     * @return array<string, mixed>|null
     */
    public function get_toolbar_update(): ?array {
        if ( ! $this->kiriof_can_show_update_notice() ) {
            return null;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! $this->kiriof_is_allowed_screen( $screen->id ) ) {
            return null;
        }

        $plugin_info = $this->kiriof_get_wordpress_org_plugin_info();
        if ( ! $plugin_info || empty( $plugin_info->version ) ) {
            return null;
        }

        $latest_version = ltrim( sanitize_text_field( (string) $plugin_info->version ), 'vV' );
        if ( '' === $latest_version || version_compare( $latest_version, KIRIOF_VERSION, '<=' ) || $this->kiriof_is_dismissed( $latest_version ) ) {
            return null;
        }

        $has_native_update = $this->kiriof_has_native_update( $latest_version );
        $can_update_now = $this->kiriof_can_update_now( $latest_version );
        $wordpress_org_url = 'https://wordpress.org/plugins/' . self::PLUGIN_SLUG . '/';
        $primary_url = $can_update_now ? add_query_arg(
            array(
                'action'   => 'upgrade-plugin',
                'plugin'   => KIRIOF_PLUGIN_BASENAME,
                '_wpnonce' => wp_create_nonce( 'upgrade-plugin_' . KIRIOF_PLUGIN_BASENAME ),
            ),
            self_admin_url( 'update.php' )
        ) : $wordpress_org_url;
        $plugins_url = admin_url( 'plugins.php' );
        $dismiss_url = add_query_arg(
            '_wpnonce',
            wp_create_nonce( 'kiriof_dismiss_update_notice_' . $latest_version ),
            add_query_arg(
                array(
                    'kiriof_dismiss_update_notice' => '1',
                    'kiriof_update_notice_version'  => rawurlencode( $latest_version ),
                )
            )
        );

        return array(
            /* translators: %s: latest plugin version, without a leading v. */
            'label'           => sprintf( __( 'New Update v%s Available', 'kiriminaja-official' ), $latest_version ),
            'changelog'       => $this->kiriof_get_release_changelog( $plugin_info, $latest_version ),
            'changelogLabel'  => __( 'Release changelog', 'kiriminaja-official' ),
            'changelogUnavailable' => __( 'Release notes are not available yet. View the plugin page for details.', 'kiriminaja-official' ),
            /* translators: %s: latest KiriminAja Official version available on WordPress.org. */
            'title'           => sprintf( __( 'KiriminAja Official %s is available.', 'kiriminaja-official' ), $latest_version ),
            'version'         => $latest_version,
            'hasNativeUpdate' => $has_native_update,
            'description'     => $can_update_now
                ? __( 'Review the release notes, then select Update Now to continue with the WordPress updater.', 'kiriminaja-official' )
                : ( $has_native_update
                ? __( 'Review the release notes, then update from Plugins in your WordPress dashboard.', 'kiriminaja-official' )
                : __( 'WordPress.org shows this release, but this site has not received the update yet.', 'kiriminaja-official' ) ),
            'details'         => $has_native_update ? array() : array(
                __( 'WordPress.org can take up to 24 hours to publish new plugin releases through the update system.', 'kiriminaja-official' ),
                __( 'If you do not see this version in Plugins, open the WordPress.org plugin page and install it manually.', 'kiriminaja-official' ),
            ),
            'primaryUrl'      => $primary_url,
            'primaryLabel'    => $can_update_now ? __( 'Update Now', 'kiriminaja-official' ) : __( 'View Plugin Page', 'kiriminaja-official' ),
            'primaryExternal' => ! $can_update_now,
            'secondaryUrl'    => $has_native_update ? $wordpress_org_url : $plugins_url,
            'secondaryLabel'  => $has_native_update ? __( 'View on WordPress.org', 'kiriminaja-official' ) : __( 'Open Plugins screen', 'kiriminaja-official' ),
            'secondaryExternal' => $has_native_update,
            'dismissUrl'      => $dismiss_url,
            'dismissLabel'    => __( 'Dismiss', 'kiriminaja-official' ),
            'closeLabel'      => __( 'Close', 'kiriminaja-official' ),
        );
    }

    /**
     * Return only the requested release, never the whole changelog or another release.
     * Treat remote markup as untrusted text; limits also apply to cached API responses.
     */
    private function kiriof_get_release_changelog( object $info, string $version ): string {
        $html = is_array( $info->sections ?? null ) ? ( $info->sections['changelog'] ?? '' ) : '';
        if ( ! is_string( $html ) || '' === $html || strlen( $html ) > 128 * 1024 ) {
            return '';
        }
        $html = preg_replace( '~<(script|style|iframe)\b[^>]*>.*?</\1\s*>~is', '', $html );
        // Unclosed active-content elements make the response unsafe/ambiguous.
        if ( preg_match( '~<(?:script|style|iframe)\b~i', $html ) ) {
            return '';
        }
        preg_match_all( '~<h([1-6])\b[^>]*>(.*?)</h\1\s*>~is', $html, $headings, PREG_OFFSET_CAPTURE );
        // Complete heading pairs are allowed, but unclosed, mismatched or nested
        // headings make release boundaries ambiguous. Fail closed before selecting.
        $without_headings = preg_replace( '~<h([1-6])\b[^>]*>.*?</h\1\s*>~is', '', $html );
        if ( preg_match( '~</?h[1-6]\b~i', $without_headings ) ) {
            return '';
        }
        $releases = array();
        foreach ( $headings[0] as $index => $heading ) {
            if ( preg_match( '~</?h[1-6]\b~i', $headings[2][ $index ][0] ) ) {
                return '';
            }
            $title = trim( html_entity_decode( wp_strip_all_tags( $headings[2][ $index ][0] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
            // Only version headings delimit releases; headings such as Fixes belong
            // to the current release. Keep the target and boundary grammar identical.
            if ( preg_match( '~^v?(\d+(?:\.\d+)+(?:[-+][0-9A-Za-z.-]+)?)(?:\s+(?:[-–—]\s*)?\(?\d{4}-\d{2}-\d{2}\)?)?$~i', $title, $match ) ) {
                $releases[] = array( 'version' => $match[1], 'heading' => $heading );
            }
        }
        $release = '';
        $found = false;
        foreach ( $releases as $index => $entry ) {
            if ( 0 !== strcasecmp( $version, $entry['version'] ) ) {
                continue;
            }
            if ( $found ) {
                return ''; // Duplicate target headers are ambiguous.
            }
            $found = true;
            $start = $entry['heading'][1] + strlen( $entry['heading'][0] );
            $end = isset( $releases[ $index + 1 ] ) ? $releases[ $index + 1 ]['heading'][1] : strlen( $html );
            $release = substr( $html, $start, $end - $start );
        }
        if ( ! $found ) {
            return '';
        }
        $release = preg_replace( '~</?h[1-6]\b[^>]*>~i', "\n", $release );
        $release = preg_replace( '~<li\b[^>]*>~i', "\n• ", $release );
        $release = preg_replace( '~<(?:br\b[^>]*|/(?:p|div|ul|ol|li))\s*/?>~i', "\n", $release );
        $text = html_entity_decode( wp_strip_all_tags( $release ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Strip again after entity decoding so encoded markup cannot leak into the contract.
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/[^\S\n]+/u', ' ', str_replace( array( "\r\n", "\r" ), "\n", $text ) );
        $text = trim( preg_replace( '/ *\n(?: *\n)+ */', "\n\n", $text ?? '' ) );
        if ( strlen( $text ) > 16 * 1024 ) {
            return ''; // Do not return a misleading partial release.
        }
        return $text;
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
        if ( $this->kiriof_is_valid_plugin_info( $cached ) ) {
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
                    'sections'      => true,
                    'download_link' => true,
                    'homepage'      => true,
                    'last_updated'  => true,
                ),
            )
        );

        if ( ! $this->kiriof_is_valid_plugin_info( $info ) ) {
            return null;
        }

        set_site_transient( self::CACHE_KEY, $info, self::CACHE_TTL );

        return $info;
    }

    private function kiriof_is_valid_plugin_info( $info ): bool {
        if ( is_wp_error( $info ) || ! is_object( $info ) || empty( $info->version ) || empty( $info->download_link ) ) {
            return false;
        }

        $download_host = wp_parse_url( esc_url_raw( (string) $info->download_link ), PHP_URL_HOST );
        return self::PLUGIN_SLUG === (string) ( $info->slug ?? '' ) && 'downloads.wordpress.org' === $download_host;
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

    /**
     * Link only to core's updater; preparing the notice never runs an update.
     * Multisite plugin updates belong to network admin, as in wp_plugin_update_row().
     */
    private function kiriof_can_update_now( string $latest_version ): bool {
        if ( ! current_user_can( 'update_plugins' ) || ( is_multisite() && ! is_network_admin() ) ) {
            return false;
        }

        $updates = get_site_transient( 'update_plugins' );
        $response = is_object( $updates ) ? ( $updates->response[ KIRIOF_PLUGIN_BASENAME ] ?? null ) : null;
        if ( ! is_object( $response ) ) {
            return false;
        }

        $version = $response->new_version ?? $response->version ?? null;
        if ( ! is_string( $version ) || $version !== $latest_version || ! is_string( $response->package ?? null ) || '' === trim( $response->package ) ) {
            return false;
        }

        return ( ! function_exists( 'is_php_version_compatible' ) || is_php_version_compatible( $response->requires_php ?? null ) )
            && ( ! function_exists( 'is_wp_version_compatible' ) || is_wp_version_compatible( $response->requires ?? null ) );
    }

    private function kiriof_is_dismissed( string $latest_version ): bool {
        $dismissed_version = (string) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );

        return '' !== $dismissed_version && $dismissed_version === $latest_version;
    }
}
