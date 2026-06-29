<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @var string $locale
 * @var array $results
 * @var string $kiriof_status_filter
 * @var string $kiriof_month_filter
 * @var array $monthOptions
 * @var string|null $prev_page_link
 * @var string|null $next_page_link
 * @var int $page
 * @var int $total_pages
 * @var int $items_per_page
 */
?>
<div class="wrap kj-wrap">

    <?php $kiriof_title = __('Payments','kiriminaja-official'); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">
    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice from print redirect.
    $kiriof_print_error = isset( $_GET['kiriof_print_error'] ) ? sanitize_text_field( wp_unslash( $_GET['kiriof_print_error'] ) ) : '';
    if ( '' !== $kiriof_print_error ) :
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $kiriof_print_error ); ?></p></div>
    <?php endif; ?>

                                <!--CONTENT-->
                                <form id="table-form" action="" style="display: none">
                                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for filtering display ?>
                                    <input type="text" name="page" value="<?php echo esc_attr( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' ); ?>">
                                    <input type="text" name="cpage" value="1">
                                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for filtering display ?>
                                    <input type="text" name="key" value="<?php echo esc_attr( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' ); ?>">
                                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for filtering display ?>
                                    <input type="text" name="status" value="<?php echo esc_attr( isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '' ); ?>">
                                    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for filtering display ?>
                                    <input type="text" name="month" value="<?php echo esc_attr( isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '' ); ?>">
                                </form>
                                
                                <div class="wp-filter" style="display: flex;justify-content: space-between;">
                                    <ul class="filter-links">
                                        <?php
                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                        $kiriof_status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
                                        ?>
                                        <li><a href="#" onclick="kiriofApplySearch('status','');return false" <?php echo empty( $kiriof_status_filter ) || $kiriof_status_filter === 'all' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['all'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','unpaid');return false" <?php echo $kiriof_status_filter === 'unpaid' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Waiting for Payment', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['unpaid'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" onclick="kiriofApplySearch('status','paid');return false" <?php echo $kiriof_status_filter === 'paid' ? 'class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Paid', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['paid'] ?? 0 ) ) ); ?>)</span></a></li>
                                    </ul>
                                    <form class="search-form search-plugins" onsubmit="return false">
                                        <label class="screen-reader-text" for="kiriof-payment-search"><?php esc_html_e( 'Search Payments', 'kiriminaja-official' ); ?></label>
                                        <input type="search" id="kiriof-payment-search" class="wp-filter-search" placeholder="<?php esc_attr_e( 'Search payment…', 'kiriminaja-official' ); ?>" value="<?php echo esc_attr( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
                                    </form>
                                </div>

                                <div class="tablenav top">
                                    <div class="alignleft actions" style="display:flex;align-items:center">
                                        <select id="month_search_1">
                                            <?php
                                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                            $kiriof_month_filter = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
                                            ?>
                                            <option value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>><?php esc_html_e( 'All Dates', 'kiriminaja-official' ); ?></option>
                                            <?php
                                            if ( ! empty( $monthOptions ) && count($monthOptions) > 0 ) {
                                                foreach ($monthOptions as $kiriof_key => $kiriof_value){
                                                    echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                }                                                            
                                            }
                                            ?>
                                        </select>
                                        <button class="button" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_1').value)"><?php esc_html_e( 'Apply', 'kiriminaja-official' ); ?></button>
                                    </div>
                                    <?php if ( $total_pages > 1 ) : ?>
                                    <div class="tablenav-pages">
                                        <span class="pagination-links">
                                            <?php if ( $prev_page_link ) : ?>
                                            <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $page - 1 ); ?>);return false"><span>&lsaquo;</span></a>
                                            <?php else : ?>
                                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                            <?php endif; ?>
                                            <span class="paging-input">
                                                <span class="tablenav-paging-text"><?php echo esc_html( $page ); ?> <?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span></span>
                                            </span>
                                            <?php if ( $next_page_link ) : ?>
                                            <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $page + 1 ); ?>);return false"><span>&rsaquo;</span></a>
                                            <?php else : ?>
                                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <br class="clear">
                                </div>
                                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                                        <thead>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb"><?php esc_html_e( 'No', 'kiriminaja-official' ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Pickup Number', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Schedule', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Fees', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Orders', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Payment Method', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Payment Status', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right"><?php echo esc_html( __( 'Action', 'kiriminaja-official' )); ?></span></th>
                                        </tr>
                                        </thead>
                                        <tbody id="the-list">
                                        <?php
                                        if (@$results&&count($results)>0){
                                            foreach($results as $id => $kiriof_row){
                                                $kiriof_btnGroup='';
                                                $kiriof_pickup_number_js = esc_js( (string) ( $kiriof_row->pickup_number ?? '' ) );
                                                $kiriof_method = strtolower(trim((string) ($kiriof_row->method ?? '')));
                                                $kiriof_is_top_method = 'top' === $kiriof_method;


                                                $kiriof_statusContent= '
                                                    <div class="kj-badge success">
                                                        <span>' . esc_html__('Paid','kiriminaja-official') . '</span>
                                                    </div>
                                                ';
                                                if (@$kiriof_row->status!=="paid" && ! $kiriof_is_top_method){
                                                    if (strtotime(@$kiriof_row->pickup_schedule)>strtotime("now")){
                                                        $kiriof_btnGroup.='
                                                        <button class="button button-primary kiriof-payment-button" type="button" data-pickup-number="'.$kiriof_pickup_number_js.'" onclick="showPaymentForm(\''.$kiriof_pickup_number_js.'\')">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <svg style="position: relative; top: 1px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path d="M8.47961 7.20001C8.15961 7.12001 7.83961 6.96001 7.59961 6.72001C7.35961 6.64001 7.27961 6.40001 7.27961 6.24001C7.27961 6.08001 7.35961 5.84001 7.51961 5.76001C7.75961 5.60001 7.99961 5.44001 8.23961 5.52001C8.71961 5.52001 9.11961 5.76001 9.35961 6.08001L10.0796 5.12001C9.83961 4.88001 9.59961 4.72001 9.35961 4.56001C9.11961 4.40001 8.79961 4.32001 8.47961 4.32001V3.20001H7.51961V4.32001C7.11961 4.40001 6.71961 4.64001 6.39961 4.96001C6.07961 5.36001 5.83961 5.84001 5.91961 6.32001C5.91961 6.80001 6.07961 7.28001 6.39961 7.60001C6.79961 8.00001 7.35961 8.24001 7.83961 8.48001C8.07961 8.56001 8.39961 8.72001 8.63961 8.88001C8.79961 9.04001 8.87961 9.28001 8.87961 9.52001C8.87961 9.76001 8.79961 10 8.63961 10.24C8.39961 10.48 8.07961 10.56 7.83961 10.56C7.51961 10.56 7.11961 10.48 6.87961 10.24C6.63961 10.08 6.39961 9.84001 6.23961 9.60001L5.43961 10.48C5.67961 10.8 5.91961 11.04 6.23961 11.28C6.63961 11.52 7.11961 11.76 7.59961 11.76V12.8H8.47961V11.6C8.95961 11.52 9.35961 11.28 9.67961 10.96C10.0796 10.56 10.3196 9.92001 10.3196 9.36001C10.3196 8.88001 10.1596 8.32001 9.75961 8.00001C9.35961 7.60001 8.95961 7.36001 8.47961 7.20001ZM7.99961 1.60001C4.47961 1.60001 1.59961 4.48001 1.59961 8.00001C1.59961 11.52 4.47961 14.4 7.99961 14.4C11.5196 14.4 14.3996 11.52 14.3996 8.00001C14.3996 4.48001 11.5196 1.60001 7.99961 1.60001ZM7.99961 13.52C4.95961 13.52 2.47961 11.04 2.47961 8.00001C2.47961 4.96001 4.95961 2.48001 7.99961 2.48001C11.0396 2.48001 13.5196 4.96001 13.5196 8.00001C13.5196 11.04 11.0396 13.52 7.99961 13.52Z" fill="white"/>
                                                                        </svg>
                                                                        <span style="margin-left: 6px">' . esc_html__( 'Payment', 'kiriminaja-official' ) . '</span>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                        ';                                                        
                                                    }else{
                                                        $kiriof_btnGroup.= '
                                                        <button class="button button-primary" type="button" onclick="showRescheduleForm(\''.$kiriof_pickup_number_js.'\')">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <span>' . esc_html__( 'Reschedule', 'kiriminaja-official' ) . '</span>
                                                                    </div>
                                                                </div>
                                                        </button>
                                                        ';    
                                                    }

                                                    
                                                    $kiriof_statusContent= '
                                                        <div class="kj-badge warning">
                                                            <span>' . esc_html__( 'Waiting for Payment', 'kiriminaja-official' ) . '</span>
                                                        </div>
                                                    ';
                                                }
                                                $kiriof_detail_url = admin_url( 'admin.php?page=kiriminaja-request-pickup-detail&pickup_number=' . urlencode( $kiriof_row->pickup_number ) );
                                                $kiriof_btnGroup.='
                                                            <a class="button button-primary-secondary" href="'.esc_url($kiriof_detail_url).'">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                       <span>' . esc_html__( 'Details', 'kiriminaja-official' ) . '</span>
                                                                    </div>
                                                                </div>
                                                            </a>
                                                ';

                                                $kiriof_allowed_html = [
                                                    'a' => [
                                                        'class' => [],
                                                        'href' => [],
                                                    ],
                                                    'button' => [
                                                        'class' => [],
                                                        'type' => [],
                                                        'data-pickup-number' => [],
                                                        'onclick' => [],
                                                    ],
                                                    'div' => [
                                                        'style' => [],
                                                    ],
                                                    'span' => [],
                                                ];

                                                $kiriof_allowed_status_content = [
                                                    'div' => [
                                                        'class' => []
                                                    ],
                                                    'span' => []
                                                ];
                                                
                                                

                                                if ($kiriof_method === 'credit') {
                                                    $kiriof_methodContent = '
                                                        <div class="kj-badge info">
                                                            <span>' . esc_html__('KA Credit', 'kiriminaja-official') . '</span>
                                                        </div>
                                                    ';
                                                } elseif ($kiriof_method === 'qris') {
                                                    $kiriof_methodContent = '
                                                        <div class="kj-badge primary">
                                                            <span>QRIS</span>
                                                        </div>
                                                    ';
                                                } elseif ($kiriof_method === 'top') {
                                                    $kiriof_methodContent = '
                                                        <div class="kj-badge" style="background:#f0f0f1;color:#50575e;">
                                                            <span>' . esc_html__('TOP', 'kiriminaja-official') . '</span>
                                                        </div>
                                                    ';
                                                } else {
                                                    $kiriof_methodContent = '
                                                        <div class="kj-badge" style="background:#f0f0f1;color:#50575e;">
                                                            <span>' . esc_html__('QRIS', 'kiriminaja-official') . '</span>
                                                        </div>
                                                    ';
                                                }

                                                $kiriof_allowed_method_content = [
                                                    'div' => [
                                                        'class' => [],
                                                        'style' => [],
                                                    ],
                                                    'span' => []
                                                ];

                                                echo '
                                                <tr class="">
                                                    <td style="font-weight: 700;" class="thumb column-thumb">'.esc_html($id)+(($page-1)*$items_per_page+1).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">'.esc_html($kiriof_row->pickup_number).'</div>
                                                        <div style="font-size: 12px;">' . esc_html__( 'Requested', 'kiriminaja-official' ) . ': '.esc_html(wp_date('Y/m/d H:i',strtotime($kiriof_row->created_at))).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.esc_html(gmdate('Y/m/d H:i',strtotime($kiriof_row->pickup_schedule)) . ' WIB').'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">Rp. '.esc_html(kiriof_money_format($kiriof_row->cost ?? 0)).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.esc_html($kiriof_row->order_amt).' ' . esc_html__( 'Order', 'kiriminaja-official' ) . '</td>
                                                    <td class="manage-column column-thumb">'.wp_kses($kiriof_methodContent, $kiriof_allowed_method_content).'</td>
                                                    <td class="manage-column column-thumb">'.wp_kses($kiriof_statusContent, $kiriof_allowed_status_content).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">'.wp_kses($kiriof_btnGroup, $kiriof_allowed_html).'</div>
                                                    </td>
                                                </tr>
                                                ';
                                                }
                                            } else {
                                            echo '<tr><td colspan="8" style="text-align: center" class="manage-column column-thumb">'.esc_html( __( 'Not Found', 'kiriminaja-official' )).'</td></tr>';
                                        }
                                        ?>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb"><?php esc_html_e( 'No', 'kiriminaja-official' ); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Pickup Number', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Schedule', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Fees', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Orders', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Payment Method', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( __( 'Payment Status', 'kiriminaja-official' )); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right"><?php echo esc_html( __( 'Action', 'kiriminaja-official' )); ?></span></th>
                                        </tr>
                                        </tfoot>
                                    </table>

                                    <div class="tablenav bottom">
                                        <div class="alignleft actions" style="display:flex;align-items:center">
                                            <select id="month_search_2" onchange="document.getElementById('month_search_1').value=this.value;kiriofApplySearch('month',this.value)">
                                                <option value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>><?php esc_html_e( 'All Dates', 'kiriminaja-official' ); ?></option>
                                                <?php
                                                if ( ! empty( $monthOptions ) && count($monthOptions) > 0 ) {
                                                    foreach ($monthOptions as $kiriof_key => $kiriof_value){
                                                        echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <button class="button" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_2').value)"><?php esc_html_e( 'Apply', 'kiriminaja-official' ); ?></button>
                                        </div>
                                        <?php if ( $total_pages > 1 ) : ?>
                                        <div class="tablenav-pages">
                                            <span class="pagination-links">
                                                <?php if ( $prev_page_link ) : ?>
                                                <a class="prev-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $page - 1 ); ?>);return false"><span>&lsaquo;</span></a>
                                                <?php else : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                                <?php endif; ?>
                                                <span class="paging-input">
                                                    <span class="tablenav-paging-text"><?php echo esc_html( $page ); ?> <?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span></span>
                                                </span>
                                                <?php if ( $next_page_link ) : ?>
                                                <a class="next-page button" href="#" onclick="kiriofGoToPage(<?php echo (int) ( $page + 1 ); ?>);return false"><span>&rsaquo;</span></a>
                                                <?php else : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <br class="clear">
                                    </div>

    <?php include 'modal-payment.php' ?>
    <?php include 'modal-request-pickup.php' ?>
</div>


<!--Table Search-->
<?php ob_start(); ?>
    function kiriofApplySearch (key,value){
        if (jQuery(`#table-form [name="${key}"]`).length > 0){
            jQuery(`#table-form [name="${key}"]`).val(value)
        }
        // Clear search when switching status tabs
        if (key === 'status' && value) {
            jQuery(`#table-form [name="key"]`).val('');
            jQuery('#kiriof-payment-search').val('');
        }
        jQuery(`#table-form [name="cpage"]`).val('1')
        jQuery(`#table-form`).trigger('submit')
    }

    function kiriofGoToPage(page){
        jQuery(`#table-form [name="cpage"]`).val(page)
        jQuery(`#table-form`).trigger('submit')
    }

    // Heartbeat nonce auto-refresh (mirrors setuped/index.php)
    jQuery(document).on('heartbeat-send', function(e, data){
        data.kiriof_nonce_check = true;
    });
    jQuery(document).on('heartbeat-tick', function(e, data){
        if (data.kiriof_new_nonce){
            kiriofAjax.nonce = data.kiriof_new_nonce;
        }
    });

    var kiriofPaymentSearchTimer;
    jQuery(document).on('keyup', '#kiriof-payment-search', function(){
        clearTimeout(kiriofPaymentSearchTimer);
        var $input = jQuery(this);
        kiriofPaymentSearchTimer = setTimeout(function(){
            kiriofApplySearch('key', $input.val());
        }, 400);
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
<!--Request Pickup Detail-->
<?php ob_start(); ?>

    function kjRequestPickupProcess(){
        jQuery('#request-pickup-modal .err_msg').addClass('kj-hidden')

        let orderid = jQuery('#request-pickup-modal').find('.kj-modal-content button').data('tid');

        let orderIds = [orderid];

        const modalElem = jQuery('#request-pickup-modal')
        const modalElemContent = jQuery('#request-pickup-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-modal .kj-err-container')

        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')
        
        jQuery.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_request_pickup_transaction",  // the action to fire in the server
                data: {
                    schedule : jQuery('[name="schedule-opt"]:checked').val(),
                    order_ids : orderIds,
                    nonce : kiriofAjax.nonce
                },         // any JS object
            },
            complete: function (response) {
                /** Reset Err*/
                jQuery('#request-pickup-modal .err_msg').empty()
                jQuery('#request-pickup-modal .err_msg').addClass('kj-hidden')
    
                
                const resp = JSON.parse(response.responseText).data;
                if (resp?.status !== 200){

                    modalElemLoader.addClass('kj-hidden')
                    modalElemErr.addClass('kj-hidden')
                    modalElemContent.removeClass('kj-hidden')
                    
                    jQuery('#request-pickup-modal .err_msg').text('*'+resp?.message)
                    jQuery('#request-pickup-modal .err_msg').removeClass('kj-hidden')
                    return
                }

                const pickupNumber = encodeURIComponent(resp?.data?.pickup_number || '')
                const redirectBase = `<?php echo esc_url( admin_url( 'admin.php?page=kiriminaja-request-pickup' ) ); ?>&pickup_number=${pickupNumber}`;
                const shouldOpenPayment = resp?.data?.open_payment === true || resp?.data?.open_payment === 1 || resp?.data?.open_payment === '1';
                window.location.href = shouldOpenPayment ? `${redirectBase}&open_payment=1` : redirectBase;
                
                
            }
        })
    }
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
<!--Payment Detail-->
<?php ob_start(); ?>
    let showPaymentFormPaymentId = null
    const kiriofPaymentQrMaxRetries = 20
    const kiriofPaymentQrRetryDelay = 1000
    function showPaymentForm(paymentId, retryCount){
        retryCount = retryCount || 0
        showPaymentFormPaymentId = paymentId
        jQuery("#paymentQR").empty()

        const modalElem = jQuery('#payment-modal')
        const modalElemContent = jQuery('#payment-modal .kj-modal-content')
        const modalElemLoader = jQuery('#payment-modal .kj-modal-loader')
        const modalElemErr = jQuery('#payment-modal .kj-err-container')

        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_get_payment_form",  // the action to fire in the server
                data: {
                    payment_id:paymentId,
                    nonce : kiriofAjax.nonce
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;

                if (resp?.status !== 200){
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    return
                }
                
                const remotePayment = resp?.data?.payment_data ?? {};
                const remoteIsPaid = parseInt(remotePayment?.status_code || 0, 10) >= 100;

                /** cek jika payment sudah dibayar lalu reload list supaya status ikut berubah */
                if (remoteIsPaid){
                    modalElem.addClass('kj-hidden')
                    window.location.reload()
                    return
                }
                
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
                
                const responseData = resp?.data
                jQuery('#payment-modal #trx-code').text(responseData?.payment_data?.payment_id)
                jQuery('#payment-modal #trx-expired-at').text(responseData?.expired_at)
                jQuery('#payment-modal .trx-pay-amount').text(kiriofMoneyFormat(responseData?.sum_fee_non_cod,'Rp'))

                const qrContent = responseData?.payment_data?.qr_content
                if (!qrContent && retryCount < kiriofPaymentQrMaxRetries) {
                    modalElemLoader.removeClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.addClass('kj-hidden')
                    setTimeout(function() {
                        showPaymentForm(paymentId, retryCount + 1)
                    }, kiriofPaymentQrRetryDelay)
                    return
                }

                kiriofRenderQrCode('#paymentQR', qrContent, {
                    width: 256,
                    height: 256
                });
            },
            error: function (xhr, status, error) {
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.addClass('kj-hidden')
                modalElemErr.removeClass('kj-hidden')
                console.error("Error fetching payment form:", error);
            }
        });
    }
    function refreshShowPaymentForm(){
        showPaymentForm(showPaymentFormPaymentId)
    }
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const pickupNumberToLoad = urlParams.get('pickup_number');
        const shouldOpenPayment = urlParams.get('open_payment');
        if (pickupNumberToLoad && (shouldOpenPayment === '1' || shouldOpenPayment === 'true')) {
            setTimeout(function() {
                urlParams.delete('pickup_number');
                urlParams.delete('open_payment');
                const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '') + window.location.hash;
                window.history.replaceState(null, '', cleanUrl);

                const paymentButton = Array.from(document.querySelectorAll('.kiriof-payment-button')).find(function(button) {
                    return button.getAttribute('data-pickup-number') === pickupNumberToLoad
                        || button.getAttribute('onclick') === "showPaymentForm('" + pickupNumberToLoad + "')";
                });
                if (paymentButton) {
                    paymentButton.click();
                }
            }, 150);
        }
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
<!--Request Pickup-->
<?php ob_start(); ?>

    function showRescheduleForm(paymentId){

        const modalElem = jQuery('#request-pickup-modal')
        const modalElemContent = jQuery('#request-pickup-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-modal .kj-err-container')

        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_get_shipping_reschedule_pickup",  // the action to fire in the server
                data: {
                    payment_id:paymentId,
                    nonce : kiriofAjax.nonce
                },         // any JS object
            },
            complete: function (response) {
                const resp = JSON.parse(response.responseText).data;
            
                if (resp?.status !== 200){
                    modalElemLoader.addClass('kj-hidden')
                    modalElemContent.addClass('kj-hidden')
                    modalElemErr.removeClass('kj-hidden')
                    alert(resp?.message ?? '<?php echo esc_js(__('An error occurred.', 'kiriminaja-official')); ?>')
                    return
                }

                const schedules = resp?.data?.schedules ?? [];
                const transaction_summary = resp?.data?.transaction_summary ?? {};
                const sum_cod_fee = 0;
                const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;
                const total = sum_non_cod_fee;

                
                /** transaction_summary*/
                jQuery('#schedule-transaction-summary').empty()
                jQuery('#schedule-transaction-summary').append(`
                <div>
                    <div class="row">
                        <div class="col"><?php echo esc_js( __( 'COD Package Charges', 'kiriminaja-official' ) ); ?></div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp0</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col"><?php echo esc_js( __( 'Non-COD Package Charges', 'kiriminaja-official' ) ); ?></div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((transaction_summary?.sum_fee_non_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col"><?php echo esc_js( __( 'Total Charges', 'kiriminaja-official' ) ); ?></div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat(total)}</div>
                    </div>
                </div>
                `)
                
                /** schedules*/
                
                jQuery('#schedule-opt-list').empty()
                jQuery.each(schedules,function (idx,schedule){
                    jQuery('#schedule-opt-list').append(`
                        <div style="margin-bottom: .75rem">
                            <div style="display: flex;align-items: center;justify-items: center;">
                                <input id="opt_${schedule?.clock}" style="margin: 0" value="${schedule?.clock}" type="radio" name="schedule-opt">
                                <span style="margin-left: .5rem;margin-top: auto;margin-bottom: auto">
                                    <label for="opt_${schedule?.clock}">${schedule?.label}</label>                                
                                </span>
                            </div>
                        </div>
                `)
                })

                
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')

                jQuery('#request-pickup-modal').find('.kj-modal-content button').attr('data-tid',transaction_summary.order_id);
            }
        });
    }

<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
