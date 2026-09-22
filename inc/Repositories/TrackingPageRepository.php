<?php

namespace KiriminAjaOfficial\Repositories;

use KiriminAjaOfficial\Contracts\TrackingPageRepositoryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TrackingPageRepository implements TrackingPageRepositoryInterface
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Determine whether a published page contains the current tracking shortcode.
     */
    public function hasPublishedTrackingPage(): bool
    {
        $wpdb = $this->wpdb;
        $shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier and shortcode are passed through wpdb::prepare().
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i
                WHERE post_type = 'page'
                    AND post_status = 'publish'
                    AND post_content LIKE %s",
                $wpdb->posts,
                $shortcode
            )
        );

        return (int) $count > 0;
    }

    /**
     * Find published pages and posts containing a supported tracking shortcode.
     *
     * @return object[]
     */
    public function findPublishedTrackingContent(): array
    {
        $pages = $this->findPublishedTrackingContentByType( 'page' );
        $posts = $this->findPublishedTrackingContentByType( 'post' );

        return array_merge( $pages, $posts );
    }

    /**
     * Find pages containing a supported KiriminAja tracking shortcode.
     *
     * @return object[]
     */
    public function findTrackingShortcodePages(): array
    {
        $wpdb = $this->wpdb;
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier and shortcode values are passed through wpdb::prepare().
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title FROM %i
                WHERE post_type = 'page'
                    AND post_status NOT IN ('trash', 'auto-draft')
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_title ASC, ID ASC",
                $wpdb->posts,
                $current_shortcode,
                $legacy_shortcode
            )
        );
    }

    /**
     * Find the preferred existing tracking page for activation reuse.
     *
     * @return object|null
     */
    public function findPreferredTrackingShortcodePage()
    {
        $wpdb = $this->wpdb;
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier and shortcode values are passed through wpdb::prepare().
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM %i
                WHERE post_type = 'page'
                    AND post_status NOT IN ('trash', 'auto-draft')
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_status = 'publish' DESC, ID ASC
                LIMIT 1",
                $wpdb->posts,
                $current_shortcode,
                $legacy_shortcode
            )
        );
    }

    /**
     * @param string $post_type WordPress post type.
     * @return object[]
     */
    private function findPublishedTrackingContentByType( $post_type ): array
    {
        $wpdb = $this->wpdb;
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier, post type, and shortcode values are passed through wpdb::prepare().
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_name, post_status, guid
                FROM %i
                WHERE post_type = %s
                    AND post_status = 'publish'
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_title ASC",
                $wpdb->posts,
                $post_type,
                $current_shortcode,
                $legacy_shortcode
            )
        );
    }
}
