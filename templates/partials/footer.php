<?php
/**
 * Shared admin footer line shown at the bottom of every KiriminAja screen.
 *
 * Kept as a single partial so the version string only has to change in one
 * place and any future footer additions (links, support contact, etc.) stay
 * consistent across pages. Include from any template under templates/* via:
 *
 *     include __DIR__ . '/../../partials/footer.php';
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<p style="font-weight: 500">KiriminAja Official v<?php echo esc_html( KIRIOF_VERSION ); ?></p>
