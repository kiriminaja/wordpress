<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="kj-wrapper kj-wrap">

    <div class="wrap ">
        <div id="root">
            <div class="woocommerce-layout">
                <div class="woocommerce-layout__header is-scrolled">
                    <div class="woocommerce-layout__header-wrapper">
                        <h1 data-wp-c16t="true" data-wp-component="Text" class="components-truncate components-text woocommerce-layout__header-heading css-wv5nn e19lxcc00">Payments</h1>
                    </div>
                </div>
                <div class="woocommerce-layout__primary" id="woocommerce-layout__primary">
                    <div id="woocommerce-layout__notice-list" class="woocommerce-layout__notice-list"></div>
                    <div class="woocommerce-layout__main">

                        <div class="woocommerce-homescreen">
                            <div class="woocommerce-homescreen-column" style="position: static;width: 100%">

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
                                
                                
                                <div>
                                    <div style="display: inline-block">
                                        <ul class="subsubsub">
                                            <?php
                                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                            $kiriof_status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
                                            ?>
                                            <li ><a href="#" onclick="kiriofApplySearch('status','')" <?php echo empty( $kiriof_status_filter ) || $kiriof_status_filter === 'all' ? 'class="current"' : ''; ?> >All <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['all'] ?? 0 ) ) ); ?>)</span></a> |</li>
                                            <li ><a href="#" onclick="kiriofApplySearch('status','unpaid')" <?php echo $kiriof_status_filter === 'unpaid' ? 'class="current"' : ''; ?> >Waiting for Payment <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['unpaid'] ?? 0 ) ) ); ?>)</span></a>  |</li>
                                            <li ><a href="#" onclick="kiriofApplySearch('status','paid')" <?php echo $kiriof_status_filter === 'paid' ? 'class="current"' : ''; ?> >Paid <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['paid'] ?? 0 ) ) ); ?>)</span></a>  |</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="row-divider"></div>
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select  style="width: 100%; max-width: 12.5rem" name="month_search" id="month_search_1">
                                                        <?php
                                                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filtering
                                                        $kiriof_month_filter = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
                                                        ?>
                                                        <option selected="selected" value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>>All Dates</option>
                                                        <?php
                                                        if ( ! empty( $monthOptions ) && count($monthOptions) > 0 ) {
                                                            foreach ($monthOptions as $kiriof_key => $kiriof_value){
                                                                echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                            }                                                            
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_1').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <!--Key Search-->
                                                <div style="display: flex;justify-content: end;width: 100%; gap: 2px">
                                                    <input style="width: 100%; max-width: 12.5rem" name="key_search" type="search" class="input-text regular-input" placeholder="Search Payment" value="<?php echo esc_attr( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('key',document.getElementsByName('key_search')[0].value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Search</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row-divider"></div>
                                    <table class="wp-list-table widefat fixed striped table-view-list posts">
                                        <thead>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Pickup Number',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Schedule',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Fees',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Orders',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Payment Status',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right"><?php echo esc_html( kiriof_helper()->tlThis('Action',@$locale)); ?></span></th>
                                        </tr>
                                        </thead>
                                        <tbody id="the-list">
                                        <?php
                                        if (@$results&&count($results)>0){
                                            foreach($results as $id => $kiriof_row){
                                                $kiriof_btnGroup='';
                                                $kiriof_pickup_number_js = esc_js( (string) ( $kiriof_row->pickup_number ?? '' ) );


                                                $kiriof_statusContent= '
                                                    <div class="kj-badge success">
                                                        <span>Paid</span>
                                                    </div>
                                                ';
                                                if (@$kiriof_row->status!=="paid"){
                                                    if (strtotime(@$kiriof_row->pickup_schedule)>strtotime("now")){
                                                        $kiriof_btnGroup.='
                                                        <button class="button-wp" type="button" onclick="showPaymentForm(\''.$kiriof_pickup_number_js.'\')">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <svg style="position: relative; top: 1px" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path d="M8.47961 7.20001C8.15961 7.12001 7.83961 6.96001 7.59961 6.72001C7.35961 6.64001 7.27961 6.40001 7.27961 6.24001C7.27961 6.08001 7.35961 5.84001 7.51961 5.76001C7.75961 5.60001 7.99961 5.44001 8.23961 5.52001C8.71961 5.52001 9.11961 5.76001 9.35961 6.08001L10.0796 5.12001C9.83961 4.88001 9.59961 4.72001 9.35961 4.56001C9.11961 4.40001 8.79961 4.32001 8.47961 4.32001V3.20001H7.51961V4.32001C7.11961 4.40001 6.71961 4.64001 6.39961 4.96001C6.07961 5.36001 5.83961 5.84001 5.91961 6.32001C5.91961 6.80001 6.07961 7.28001 6.39961 7.60001C6.79961 8.00001 7.35961 8.24001 7.83961 8.48001C8.07961 8.56001 8.39961 8.72001 8.63961 8.88001C8.79961 9.04001 8.87961 9.28001 8.87961 9.52001C8.87961 9.76001 8.79961 10 8.63961 10.24C8.39961 10.48 8.07961 10.56 7.83961 10.56C7.51961 10.56 7.11961 10.48 6.87961 10.24C6.63961 10.08 6.39961 9.84001 6.23961 9.60001L5.43961 10.48C5.67961 10.8 5.91961 11.04 6.23961 11.28C6.63961 11.52 7.11961 11.76 7.59961 11.76V12.8H8.47961V11.6C8.95961 11.52 9.35961 11.28 9.67961 10.96C10.0796 10.56 10.3196 9.92001 10.3196 9.36001C10.3196 8.88001 10.1596 8.32001 9.75961 8.00001C9.35961 7.60001 8.95961 7.36001 8.47961 7.20001ZM7.99961 1.60001C4.47961 1.60001 1.59961 4.48001 1.59961 8.00001C1.59961 11.52 4.47961 14.4 7.99961 14.4C11.5196 14.4 14.3996 11.52 14.3996 8.00001C14.3996 4.48001 11.5196 1.60001 7.99961 1.60001ZM7.99961 13.52C4.95961 13.52 2.47961 11.04 2.47961 8.00001C2.47961 4.96001 4.95961 2.48001 7.99961 2.48001C11.0396 2.48001 13.5196 4.96001 13.5196 8.00001C13.5196 11.04 11.0396 13.52 7.99961 13.52Z" fill="white"/>
                                                                        </svg>
                                                                        <span style="margin-left: 6px">Payment</span>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                        ';                                                        
                                                    }else{
                                                        $kiriof_btnGroup.= '
                                                        <button class="button-wp" type="button" onclick="showRescheduleForm(\''.$kiriof_pickup_number_js.'\')">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                        <span>Reschedule</span>
                                                                    </div>
                                                                </div>
                                                        </button>
                                                        ';    
                                                    }

                                                    
                                                    $kiriof_statusContent= '
                                                        <div class="kj-badge warning">
                                                            <span>Waiting For Payment</span>
                                                        </div>
                                                    ';
                                                }
                                                $kiriof_btnGroup.='
                                                            <button class="button-wp-secondary" type="button" onclick="showDetail(\''.$kiriof_pickup_number_js.'\')">
                                                                <div style="display: flex">
                                                                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                       <span>Details</span>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                ';

                                                $kiriof_allowed_html = [
                                                    'button' => [
                                                        'class' => [],
                                                        'type' => [],
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
                                                
                                                

                                                echo '
                                                <tr class="">
                                                    <td style="font-weight: 700;" class="thumb column-thumb">'.esc_html($id)+(($page-1)*$items_per_page+1).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">'.esc_html($kiriof_row->pickup_number).'</div>
                                                        <div style="font-size: 12px;">Requested: '.esc_html(wp_date('Y/m/d H:i',strtotime($kiriof_row->created_at))).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.esc_html(wp_date('Y/m/d H:i',strtotime($kiriof_row->pickup_schedule))).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="font-weight: 700">Rp. '.esc_html(kiriof_money_format($kiriof_row->cost ?? 0)).'</div>
                                                    </td>
                                                    <td class="manage-column column-thumb">'.esc_html($kiriof_row->order_amt).' Order</td>
                                                    <td class="manage-column column-thumb">'.wp_kses($kiriof_statusContent, $kiriof_allowed_status_content).'</td>
                                                    <td class="manage-column column-thumb">
                                                        <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">'.wp_kses($kiriof_btnGroup, $kiriof_allowed_html).'</div>
                                                    </td>
                                                </tr>
                                                ';
                                                }
                                            } else {
                                            echo '<tr><td colspan="7" style="text-align: center" class="manage-column column-thumb">'.esc_html( kiriof_helper()->tlThis('Not Found',@$locale)).'</td></tr>';
                                        }
                                        ?>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th style="width: 4rem;" scope="col" class="manage-column column-thumb">No</th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Pickup Number',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Schedule',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Fees',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Orders',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><?php echo esc_html( kiriof_helper()->tlThis('Payment Status',@$locale)); ?></th>
                                            <th scope="col" class="manage-column column-thumb"><span style="float: right"><?php echo esc_html( kiriof_helper()->tlThis('Action',@$locale)); ?></span></th>
                                        </tr>
                                        </tfoot>
                                    </table>

                                    <div class="row-divider"></div>
                                    <div class="container-fluid p-0">
                                        <div class="row">
                                            <div class="col">
                                                <!--Month Search-->
                                                <div style="display: flex;width: 100%; gap: 2px">
                                                    <select  style="width: 100%; max-width: 12.5rem" name="month_search_2" id="month_search_2">
                                                        <option selected="selected" value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>>All Dates</option>
                                                        <?php
                                                        if ( ! empty( $monthOptions ) && count($monthOptions) > 0 ) {
                                                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template loop variables
                                                            foreach ($monthOptions as $kiriof_key => $kiriof_value){
                                                                echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <button class="button-wp-secondary" type="button" onclick="kiriofApplySearch('month',document.getElementById('month_search_2').value)">
                                                        <div style="display: flex">
                                                            <div style="margin: auto">
                                                                <span>Apply</span>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <!--Pagination-->
                                                <div style="display: flex;justify-content: end;align-items: center;justify-items: center;gap: 6px">
                                                    <span style="font-weight: 700;"><?php echo absint( count( $results ) ); ?> items</span>
                                                    <div>
                                                        <button <?php echo @$prev_page_link!='' ? '' : 'disabled'; ?> style="position: relative" class="button-wp-blank" type="button">
                                                            <?php echo esc_attr($prev_page_link)!='' ? '<a href="'.esc_url($prev_page_link).'" class="inset-absolute"></a>' : ''; ?>
                                                            <div style="display: flex">
                                                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M11.1998 4L7.1998 8L11.1998 12L10.3998 13.6L4.7998 8L10.3998 2.4L11.1998 4Z" fill="black"/>
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </div>
                                                    <span style="font-weight: 700;"> <?php echo esc_html($page); ?> of <?php echo esc_html($total_pages); ?> </span>
                                                    <div>
                                                        <button <?php echo @$next_page_link!='' ? '' : 'disabled'; ?> style="position: relative" class="button-wp-blank" type="button">
                                                            <?php echo esc_attr($next_page_link)!='' ? '<a href="'.esc_url($next_page_link).'" class="inset-absolute"></a>' : ''; ?>

                                                            <div style="display: flex">
                                                                <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M4.7998 12L8.7998 8L4.7998 4L5.5998 2.4L11.1998 8L5.5998 13.6L4.7998 12Z" fill="black"/>
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row-divider"></div>
                                    <?php include __DIR__ . '/../../partials/footer.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="woocommerce-layout__footer">
                        <div class="components-snackbar-list woocommerce-transient-notices components-notices__snackbar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'modal-detail.php' ?>
    <?php include 'modal-payment.php' ?>
    <?php include 'modal-request-pickup.php' ?>
</div>


<!--Table Search-->
<?php // QR Code Styling library is enqueued in inc/Base/Enqueue.php (handle: kiriof-qr-code-styling) ?>
<?php ob_start(); ?>
    function kiriofApplySearch (key,value){
        if (jQuery(`#table-form [name="${key}"]`).length > 0){
            jQuery(`#table-form [name="${key}"]`).val(value)
            jQuery(`#table-form`).trigger('submit')
        }
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
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
<!--Request Pickup Detail-->
<?php ob_start(); ?>
    let previousSelectedPaymentId = null;
    function refreshShowDetail(){
        if (!previousSelectedPaymentId){return}
        showDetail(previousSelectedPaymentId)
    }

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

                window.location.href = `<?php echo esc_url(home_url()).'/wp-admin/admin.php?page=kiriminaja-request-pickup'; ?>&pickup_number=${resp?.data?.pickup_number}`;
                
                
            }
        })
    }


    function showDetail(paymentId){
        previousSelectedPaymentId = paymentId

        const modalElem = jQuery('#request-pickup-detail-modal')
        const modalElemContent = jQuery('#request-pickup-detail-modal .kj-modal-content')
        const modalElemLoader = jQuery('#request-pickup-detail-modal .kj-modal-loader')
        const modalElemErr = jQuery('#request-pickup-detail-modal .kj-err-container')

        modalElem.removeClass('kj-hidden')
        modalElemLoader.removeClass('kj-hidden')
        modalElemContent.addClass('kj-hidden')
        modalElemErr.addClass('kj-hidden')

        jQuery.ajax({
            type: "post",
            url: kiriofAjaxRoute(),
            data: {
                action: "kiriof_get_shipping_process_detail",  // the action to fire in the server
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
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
                
                const payment_data = resp?.data?.payment_data
                const transactions_data = resp?.data?.transactions_data
                const wcOrderUrlBase = '<?php echo esc_url( home_url().'/wp-admin/post.php?post='); ?>'
                
                jQuery('#request-pickup-detail-modal-title').text("<?php esc_html_e('Request Pickup Detail','kiriminaja-official'); ?> - "+payment_data.pickup_number)
                jQuery('#request-pickup-detail-modal #package-count').text(kiriofMoneyFormat(payment_data.package_count ?? 0))
                jQuery('#request-pickup-detail-modal #package-cod-count').text(kiriofMoneyFormat(payment_data.cod_count ?? 0))
                jQuery('#request-pickup-detail-modal #package-non-cod-count').text(kiriofMoneyFormat(payment_data.non_cod_count ?? 0))

                jQuery('#request-pickup-detail-modal #the-list').empty()
                let transactionIdList = [];
                transactions_data.forEach(function (transaction,index){
                    const parsedShippingInfo = JSON.parse(transaction.shipping_info)
                    
                    let codValue = 0
                    codValue += Number(transaction?.shipping_cost ?? 0)+Number(transaction?.insurance_cost ?? 0)
                    if (transaction?.cod_fee > 0){
                        codValue += Number(transaction?.cod_fee ?? 0)+Number(transaction?.transaction_value ?? 0)
                    }
                    let transactionFee = 0
                    if (transaction.codValue === 0){
                        transactionFee = Number(transaction?.shipping_cost ?? 0)+Number(transaction?.insurance_cost ?? 0)
                    }

                    const transactionUrl = `<?php echo esc_url( home_url().'/wp-admin/post.php' ) ?>?post=${transaction?.wp_wc_order_stat_order_id}&action=edit`;
                    const printResiUrl = `<?php echo esc_url( home_url().'/transaction-resi-print' ) ?>?oids=${transaction?.order_id}&_wpnonce=<?php echo esc_js( wp_create_nonce( 'kiriof_resi_print' ) ); ?>`;
                    const formatFeeString = (value) => {
                        if (!value){
                            return 
                        }
                        return '<div style="font-size: 12px;">'+value+'</div>'
                    }
                    const feeContentArr = [];
                    if (transaction?.shipping_cost > 0){
                        feeContentArr.push(formatFeeString(`Shipping: ${kiriofMoneyFormat(transaction?.shipping_cost,'Rp')}`));
                    }
                    if (transaction?.insurance_cost > 0){
                        feeContentArr.push(formatFeeString(`Insurance: ${kiriofMoneyFormat(transaction?.insurance_cost,'Rp')}`));
                    }
                    if (transaction?.cod_fee > 0){
                        feeContentArr.push(formatFeeString(`COD Fee: ${kiriofMoneyFormat(transaction?.cod_fee,'Rp')}`));
                    }
                    if (feeContentArr.length===0){
                        feeContentArr.push('-');
                    }
                    let kiriof_btnGroup = ``;
                    if (transaction?.awb){
                        transactionIdList.push(transaction?.order_id);
                        kiriof_btnGroup += `<button class="button-wp p-relative" type="button">
                                        <a href="${printResiUrl}" target="_blank" class="inset-absolute"></a>
                                        <div style="display: flex">
                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                <div style="position: relative; top: 1px">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M9.59961 8.79998H5.59961V9.59998H9.59961V8.79998ZM10.3996 12H5.59961V12.8H10.3996V12ZM7.99961 10.4H5.59961V11.2H7.99961V10.4ZM13.5996 4.79998H11.9996V1.59998H3.99961V4.79998H2.39961C1.91961 4.79998 1.59961 5.11998 1.59961 5.59998V9.59998C1.59961 10.08 1.91961 10.4 2.39961 10.4H3.99961V14.4H11.9996V10.4H13.5996C14.0796 10.4 14.3996 10.08 14.3996 9.59998V5.59998C14.3996 5.11998 14.0796 4.79998 13.5996 4.79998ZM11.1996 13.6H4.79961V7.99998H11.1996V13.6ZM11.1996 4.79998H4.79961V2.39998H11.1996V4.79998ZM12.7996 7.19998H11.9996V6.39998H12.7996V7.19998Z" fill="white"/>
                                                    </svg>
                                                    
                                                </div>
                                                <span style="margin-left: 6px">Print</span>
                                            </div>
                                        </div>
                                    </button>`;

                    }
                    kiriof_btnGroup += `<button class="button-wp-secondary p-relative" type="button">
                                        <a href="${transactionUrl}" target="_blank" class="inset-absolute"></a>
                                        <div style="display: flex">
                                            <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                                                <span>Detail</span>
                                            </div>
                                        </div>
                                    </button>`;  
                    
                    jQuery('#request-pickup-detail-modal #the-list').append(`
                        <tr class="">
                            <td style="font-weight: 700;" class="thumb column-thumb">${index+1}</td>
                            <td class="manage-column column-thumb">
                                <div style="display: flex">
                                    <div style="font-weight: 700;padding: 0.2rem 0.5rem;color: #3c82ba;border: 2px solid #3c82ba;border-radius: 5px;">
                                        ${transaction?.cod_fee > 0 ? 'COD' : 'Non-COD'}
                                    </div>
                                </div>
                                <div class="row-divider" style="margin-top: .25rem"></div>
                                <div style="font-weight: 700"><a target="_blank" href="${wcOrderUrlBase}${transaction?.wp_wc_order_stat_order_id}&action=edit">${transaction?.order_id}</a></div>
                                <div style="font-size: 12px;">${parsedShippingInfo?._billing_first_name}</div>
                            </td>
                            <td class="manage-column column-thumb">
                                <div style="font-weight: 700">${kiriofPrintAsString(transaction?.awb,'-')}</div>
                                <div style="font-weight: 700">${(transaction?.service).toUpperCase()} – ${(transaction?.service_name).toUpperCase()}</div>
                                <div style="font-size: 12px;">Pickup Schedule: ${(payment_data?.schedule)}</div>
                            </td>

                            <td class="manage-column column-thumb">
                                <div style="font-weight: 700">${kiriofMoneyFormat(transaction?.transaction_value,'Rp')}</div>
                            </td>
                            <td class="manage-column column-thumb">
                                ${feeContentArr.join(' ')}
                            </td>
                            <td class="manage-column column-thumb">
                                <div style="font-weight: 700">${kiriofMoneyFormat(codValue,'Rp')}</div>
                            </td>
                            <td class="manage-column column-thumb">
                                <div style="text-transform: capitalize" class="kj-badge ${transaction?.status_classes}">
                                    <span>${transaction?.status}</span>
                                </div>
                            </td>
                            <td class="manage-column column-thumb">
                                <div style="display: flex;justify-content: end;gap: 4px; flex-wrap: wrap">
                                `+kiriof_btnGroup+`
                                </div>
                            </td>
                        </tr>
                    `)
                })
                const printAllResiUrl = `<?php echo esc_url( home_url().'/transaction-resi-print' ) ?>?oids=${transactionIdList.join(',')}&_wpnonce=<?php echo esc_js( wp_create_nonce( 'kiriof_resi_print' ) ); ?>`;
                if( transactionIdList.length === 0){
                    jQuery('#request-pickup-detail-modal #print-all-resi-btn').hide();
                } else {
                    jQuery('#request-pickup-detail-modal #print-all-resi-btn').show();
                }
                jQuery('#request-pickup-detail-modal #print-all-resi').attr('href',printAllResiUrl);

            },
        });

    }
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
<!--Payment Detail-->
<?php ob_start(); ?>
    let showPaymentFormPaymentId = null
    function showPaymentForm(paymentId){
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
                
                /** cek jika payment sudah dibayar tampilkan detail*/
                if (resp?.data?.payment_in_wc_data?.status === "paid"){
                    /** hide modal*/
                    modalElem.addClass('kj-hidden')
                    showDetail(showPaymentFormPaymentId)
                }
                
                modalElemLoader.addClass('kj-hidden')
                modalElemContent.removeClass('kj-hidden')
                modalElemErr.addClass('kj-hidden')
                
                const responseData = resp?.data
                jQuery('#payment-modal #trx-code').text(responseData?.payment_data?.payment_id)
                jQuery('#payment-modal #trx-expired-at').text(responseData?.expired_at)
                jQuery('#payment-modal .trx-pay-amount').text(kiriofMoneyFormat(responseData?.sum_fee_non_cod,'Rp'))

                var qrcode = new QRCodeStyling({
                    data: responseData?.payment_data?.qr_content,
                    width: 256,
                    height: 256,
                    dotsOptions: {
                        color: "#000000",
                        type: "rounded"
                    },
                    backgroundOptions: {
                        color: "transparent",
                    },
                    imageOptions: {
                        crossOrigin: "anonymous",
                        margin: 20
                    }
                });

                qrcode.append(document.getElementById("paymentQR"));
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
    const urlParams = new URLSearchParams(window.location.href);
    
    jQuery(document).ready(function() {
        const pickupNumberToLoad = urlParams.get('pickup_number');
        if (pickupNumberToLoad){
            showPaymentForm(pickupNumberToLoad)
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
                    alert(resp?.message ?? 'Terjadi kesalahan')
                    return
                }

                const schedules = resp?.data?.schedules ?? [];
                const transaction_summary = resp?.data?.transaction_summary ?? {};
                const sum_cod_fee = transaction_summary?.sum_fee_cod ?? 0;
                const sum_non_cod_fee = transaction_summary?.sum_fee_non_cod ?? 0;
                const total = sum_non_cod_fee;

                
                /** transaction_summary*/
                jQuery('#schedule-transaction-summary').empty()
                jQuery('#schedule-transaction-summary').append(`
                <div>
                    <div class="row">
                        <div class="col">Tagihan Paket COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((transaction_summary?.sum_fee_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Tagihan Paket Non-COD</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((transaction_summary?.sum_fee_non_cod ?? 0))}</div>
                    </div>
                    <div class="row-divider" style="margin-top: .5rem"></div>
                    <div class="row">
                        <div class="col">Total Tagihan</div>
                        <div class="col" style="text-align: right; font-weight: 700">Rp${kiriofMoneyFormat((sum_cod_fee))}</div>
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
