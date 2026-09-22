<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Technical (detail page)
 *
 * @var string $kiriof_base_url
 */

use KiriminAjaOfficial\Repositories\ShippingDiscountRegionRepository;
use KiriminAjaOfficial\Services\KiriminajaApiService;
use KiriminAjaOfficial\Services\ShippingDiscountRegionCacheService;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-local variables, not globals
$regionRepo         = new ShippingDiscountRegionRepository();
$regionCacheService = new ShippingDiscountRegionCacheService();
$cacheStatus        = $regionCacheService->getStatus();
$provinceCount      = $regionRepo->getProvinceCount();
$cityCount          = $regionRepo->getCityCount();

$state       = $cacheStatus['state'] ?? 'unknown';
$stateColors = array( 'ready' => '#00a32a', 'error' => '#d63638' );
$stateColor  = $stateColors[ $state ] ?? '#dba617';
$regionValidUntil = 'ready' === $state ? __( 'Manual refresh only', 'kiriminaja-official' ) : '—';
$downloadLogUrl = wp_nonce_url(
    admin_url( 'admin-post.php?action=kiriof_download_plugin_logs' ),
    'kiriof_download_plugin_logs'
);

$courierService  = new KiriminajaApiService();
$courierResult   = $courierService->get_couriers();
$courierCount    = ( 200 === $courierResult->status && is_array( $courierResult->data ) ) ? count( $courierResult->data ) : 0;
$courierCached   = ( false !== get_transient( 'kiriof_couriers_list_v2' ) );
$courierTimeout  = (int) get_option( '_transient_timeout_kiriof_couriers_list_v2', 0 );
$courierUpdated  = ( $courierCached && $courierTimeout > DAY_IN_SECONDS ) ? wp_date( 'Y-m-d H:i:s', $courierTimeout - DAY_IN_SECONDS ) : '—';
$courierValidUntil = ( $courierCached && $courierTimeout > 0 ) ? wp_date( 'Y-m-d H:i:s', $courierTimeout ) : '—';
$courierBadgeBg  = $courierCached ? '#00a32a' : '#dba617';
$courierBadgeTxt = $courierCached ? __( 'Cached', 'kiriminaja-official' ) : __( 'Not cached', 'kiriminaja-official' );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <?php
    $kiriof_title        = __( 'Technical', 'kiriminaja-official' );
    $kiriof_parent_url   = $kiriof_base_url;
    $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' );
    include KIRIOF_DIR . 'templates/_header.php';
    ?>
    <hr class="wp-header-end">

    <div class="kj-detail" style="display:flex;flex-direction:column;gap:16px">

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;">
            <h3 style="margin-top:0"><?php esc_html_e( 'Region Coverage Cache', 'kiriminaja-official' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Province and city data used for coupon area restrictions. Re-validate to fetch the latest data from the KiriminAja API.', 'kiriminaja-official' ); ?></p>

            <table class="form-table" role="presentation" style="margin:12px 0 20px">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-cache-state">
                        <span style="display:inline-block;padding:2px 10px;border-radius:999px;background:<?php echo esc_attr( $stateColor ); ?>;color:#fff;font-size:12px;font-weight:600">
                            <?php echo esc_html( ucfirst( $state ) ); ?>
                        </span>
                        <?php if ( ! empty( $cacheStatus['last_error'] ) ) : ?>
                            <span style="margin-left:6px;color:#d63638;font-size:12px"><?php echo esc_html( $cacheStatus['last_error'] ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Provinces', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-cache-provinces"><?php echo esc_html( number_format_i18n( $provinceCount ) ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Cities', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-cache-cities"><?php echo esc_html( number_format_i18n( $cityCount ) ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Updated', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-cache-updated"><?php echo esc_html( $cacheStatus['last_completed_at'] ?? '—' ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Valid Until', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-cache-valid-until"><?php echo esc_html( $regionValidUntil ); ?></td>
                </tr>
            </table>

            <button type="button" id="kiriof-revalidate-btn" class="button button-primary">
                <?php esc_html_e( 'Re-validate Region Cache', 'kiriminaja-official' ); ?>
            </button>
            <span id="kiriof-revalidate-msg" style="margin-left:12px;font-size:13px;color:#646970"></span>
        </div>

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;">
            <h3 style="margin-top:0"><?php esc_html_e( 'Courier List Cache', 'kiriminaja-official' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Courier names and types fetched from the KiriminAja API. Used for proper labelling in the transactions filter and coupon courier restrictions. Cached for 24 hours.', 'kiriminaja-official' ); ?></p>

            <table class="form-table" role="presentation" style="margin:12px 0 20px">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-couriers-cache-state">
                        <span style="display:inline-block;padding:2px 10px;border-radius:999px;background:<?php echo esc_attr( $courierBadgeBg ); ?>;color:#fff;font-size:12px;font-weight:600">
                            <?php echo esc_html( $courierBadgeTxt ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Couriers', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-couriers-cache-count"><?php echo esc_html( number_format_i18n( $courierCount ) ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Last Updated', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-couriers-cache-updated"><?php echo esc_html( $courierUpdated ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Valid Until', 'kiriminaja-official' ); ?></th>
                    <td id="kiriof-couriers-cache-valid-until"><?php echo esc_html( $courierValidUntil ); ?></td>
                </tr>
            </table>

            <button type="button" id="kiriof-flush-couriers-btn" class="button button-primary">
                <?php esc_html_e( 'Flush &amp; Re-fetch Couriers', 'kiriminaja-official' ); ?>
            </button>
            <span id="kiriof-flush-couriers-msg" style="margin-left:12px;font-size:13px;color:#646970"></span>
        </div>

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;">
            <h3 style="margin-top:0"><?php esc_html_e( 'Diagnostic Logs', 'kiriminaja-official' ); ?></h3>
            <p class="description"><?php esc_html_e( 'Download WooCommerce logs generated only by the KiriminAja plugin. The export excludes general WooCommerce and WordPress logs.', 'kiriminaja-official' ); ?></p>
            <p class="description" style="margin-top:8px"><?php esc_html_e( 'KiriminAja does not collect this diagnostic data automatically or send it directly to KiriminAja. Please download the file and send it to the KiriminAja support team only with your consent.', 'kiriminaja-official' ); ?></p>
            <a class="button button-primary" href="<?php echo esc_url( $downloadLogUrl ); ?>">
                <?php esc_html_e( 'Download Log', 'kiriminaja-official' ); ?>
            </a>
        </div>

    </div>
</div>
