<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Tracking Page (detail page)
 *
 * @var string $locale
 * @var string $kiriof_base_url
 */

// Search all published pages for the tracking shortcode.
global $wpdb;
$kiriof_pages = array();

// Check pages
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$kiriof_results = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_status, guid
     FROM {$wpdb->posts}
     WHERE post_type = 'page'
       AND post_status = 'publish'
       AND (
           post_content LIKE '%[kiriminaja-tracking-front-page%'
           OR post_content LIKE '%[wp-tracking-front-page%'
       )
     ORDER BY post_title ASC"
);

if ( ! empty( $kiriof_results ) ) {
    $kiriof_pages = $kiriof_results;
}

// Also check posts
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$kiriof_post_results = $wpdb->get_results(
    "SELECT ID, post_title, post_name, post_status, guid
     FROM {$wpdb->posts}
     WHERE post_type = 'post'
       AND post_status = 'publish'
       AND (
           post_content LIKE '%[kiriminaja-tracking-front-page%'
           OR post_content LIKE '%[wp-tracking-front-page%'
       )
     ORDER BY post_title ASC"
);

if ( ! empty( $kiriof_post_results ) ) {
    $kiriof_pages = array_merge( $kiriof_pages, $kiriof_post_results );
}
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <?php $kiriof_title = kiriof_helper()->tlThis('Tracking Page',$locale); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = kiriof_helper()->tlThis('Settings',$locale); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail">

        <!-- Guide -->
        <div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:12px;"><?php echo esc_html( kiriof_helper()->tlThis('How to Add a Tracking Page',$locale) ); ?></div>
            <ol style="margin:0;padding-left:18px;font-size:13px;color:#50575e;line-height:1.8;">
                <li><?php echo esc_html( kiriof_helper()->tlThis('Go to Pages › Add New in your WordPress admin.',$locale) ); ?></li>
                <li><?php echo esc_html( kiriof_helper()->tlThis('Give your page a title, e.g. "Track Your Order".',$locale) ); ?></li>
                <li><?php echo esc_html( kiriof_helper()->tlThis('In the content editor, add the shortcode:',$locale) ); ?>
                    <code style="background:#f0f0f1;padding:2px 6px;border-radius:4px;font-size:12px;user-select:all;">[kiriminaja-tracking-front-page]</code>
                </li>
                <li><?php echo esc_html( kiriof_helper()->tlThis('Publish the page.',$locale) ); ?></li>
                <li><?php echo esc_html( kiriof_helper()->tlThis('Your customers can now track their orders by visiting this page and entering their order number.',$locale) ); ?></li>
            </ol>
        </div>

        <!-- Pages with shortcode -->
        <div class="kj-account-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:12px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <div style="font-size:14px;font-weight:600;color:#1d2327;margin-bottom:12px;"><?php echo esc_html( kiriof_helper()->tlThis('Pages Using Tracking Shortcode',$locale) ); ?></div>

            <?php if ( empty( $kiriof_pages ) ) : ?>
                <div style="padding:24px;text-align:center;color:#8c8f94;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" style="margin-bottom:8px;"><rect x="3" y="3" width="18" height="18" rx="2" stroke="#c3c4c7" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="#c3c4c7" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <div style="font-size:14px;font-weight:500;margin-bottom:4px;"><?php echo esc_html( kiriof_helper()->tlThis('You haven\'t configured any tracking page yet.',$locale) ); ?></div>
                    <div style="font-size:12px;"><?php echo esc_html( kiriof_helper()->tlThis('Add the shortcode [kiriminaja-tracking-front-page] to a page to enable order tracking for your customers.',$locale) ); ?></div>
                </div>
            <?php else : ?>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <?php foreach ( $kiriof_pages as $page ) : ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f6f7f7;border-radius:8px;">
                        <div>
                            <div style="font-weight:500;font-size:13px;"><?php echo esc_html( $page->post_title ); ?></div>
                            <div style="font-size:12px;color:#787c82;"><?php echo esc_html( get_permalink( $page->ID ) ); ?></div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>" target="_blank" class="button button-small" style="font-size:12px;"><?php echo esc_html( kiriof_helper()->tlThis('View',$locale) ); ?></a>
                            <a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>" class="button button-small" style="font-size:12px;"><?php echo esc_html( kiriof_helper()->tlThis('Edit',$locale) ); ?></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
