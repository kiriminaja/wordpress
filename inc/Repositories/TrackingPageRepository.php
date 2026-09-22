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
        $shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only setup readiness query against the WordPress posts table.
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->posts}
                WHERE post_type = 'page'
                    AND post_status = 'publish'
                    AND post_content LIKE %s",
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
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only admin query against the WordPress posts table.
        return (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ID, post_title FROM {$this->wpdb->posts}
                WHERE post_type = 'page'
                    AND post_status NOT IN ('trash', 'auto-draft')
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_title ASC, ID ASC",
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
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only activation lookup against the WordPress posts table.
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT ID FROM {$this->wpdb->posts}
                WHERE post_type = 'page'
                    AND post_status NOT IN ('trash', 'auto-draft')
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_status = 'publish' DESC, ID ASC
                LIMIT 1",
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
        $current_shortcode = '%' . $this->wpdb->esc_like( '[kiriminaja-tracking-front-page' ) . '%';
        $legacy_shortcode  = '%' . $this->wpdb->esc_like( '[wp-tracking-front-page' ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Read-only admin query against the WordPress posts table.
        return (array) $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ID, post_title, post_name, post_status, guid
                FROM {$this->wpdb->posts}
                WHERE post_type = %s
                    AND post_status = 'publish'
                    AND (
                        post_content LIKE %s
                        OR post_content LIKE %s
                    )
                ORDER BY post_title ASC",
                $post_type,
                $current_shortcode,
                $legacy_shortcode
            )
        );
    }
}
