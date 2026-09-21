<?php

namespace KiriminAjaOfficial\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface TrackingPageRepositoryInterface
{
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
