<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Reusable page header with breadcrumb support.
 *
 * Expected variables:
 * @param string $kiriof_title         Page title (required).
 * @param string $kiriof_parent_url    Parent page URL (optional — if set, renders breadcrumb).
 * @param string $kiriof_parent_title  Parent page label (optional — required if $kiriof_parent_url is set).
 * @param string $kiriof_subtitle      Subtitle appended after the title, e.g. pickup number (optional).
 * @var string $kiriof_title
 * @var string $kiriof_parent_url
 * @var string $kiriof_parent_title
 * @var string $kiriof_subtitle
 */
?>
<h1 class="wp-heading-inline" style="display:flex;align-items:center;gap:6px;">
    <?php if ( ! empty( $kiriof_parent_url ) ) : ?>
        <a href="<?php echo esc_url( $kiriof_parent_url ); ?>" style="color:#2271b1;text-decoration:none;"><?php echo esc_html( $kiriof_parent_title ?? '' ); ?></a>
        <span style="color:#8c8f94;">›</span>
    <?php endif; ?>
    <span style="font-weight:500;"><?php echo esc_html( $kiriof_title ); ?></span>
    <?php if ( ! empty( $kiriof_subtitle ) ) : ?>
        <span style="font-weight:400;">— <?php echo esc_html( $kiriof_subtitle ); ?></span>
    <?php endif; ?>
</h1>
