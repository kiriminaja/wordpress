<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface TrackingPageRepositoryInterface
{
    /**
     * Determine whether a published page contains the current tracking shortcode.
     */
    public function hasPublishedTrackingPage(): bool;

    /**
     * Find published pages and posts containing a supported tracking shortcode.
     *
     * Pages are returned before posts, preserving the legacy admin list shape.
     *
     * @return object[]
     */
    public function findPublishedTrackingContent(): array;

    /**
     * Find published or editable pages containing a supported tracking shortcode.
     *
     * @return object[]
     */
    public function findTrackingShortcodePages(): array;

    /**
     * Find the preferred existing tracking page for activation reuse.
     *
     * @return object|null
     */
    public function findPreferredTrackingShortcodePage();
}
