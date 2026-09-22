<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_localize_script(
    'kiriof-request-pickup',
    'kiriofRequestPickupConfig',
    array(
        'redirectUrl' => admin_url( 'admin.php?page=kiriminaja-request-pickup' ),
        'i18n'        => array(
            'error'            => __( 'An error occurred.', 'kiriminaja-official' ),
            'codCharges'       => __( 'COD Package Charges', 'kiriminaja-official' ),
            'nonCodCharges'    => __( 'Non-COD Package Charges', 'kiriminaja-official' ),
            'totalCharges'     => __( 'Total Charges', 'kiriminaja-official' ),
        ),
    )
);

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
<div class="wrap kj-wrap" data-kiriof-payments-page>

    <?php $kiriof_title = __('Payments','kiriminaja-official'); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">
    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice from print redirect.
    $kiriof_print_error = isset( $_GET['kiriof_print_error'] ) ? sanitize_text_field( wp_unslash( $_GET['kiriof_print_error'] ) ) : '';
    if ( '' !== $kiriof_print_error ) :
        ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $kiriof_print_error ); ?></p></div>
    <?php endif; ?>

                                <div data-kiriof-payments-root></div>
                                <script type="application/json" data-kiriof-payments-payload><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for a non-executable data block. ?><?php echo wp_json_encode( $kiriof_payments_bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
                                <div data-kiriof-payments-fallback>
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
                                        <li><a href="#" class="kiriof-filter-link<?php echo empty( $kiriof_status_filter ) || $kiriof_status_filter === 'all' ? ' current' : ''; ?>" data-filter-key="status" data-filter-value="" <?php echo empty( $kiriof_status_filter ) || $kiriof_status_filter === 'all' ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['all'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" class="kiriof-filter-link<?php echo $kiriof_status_filter === 'unpaid' ? ' current' : ''; ?>" data-filter-key="status" data-filter-value="unpaid" <?php echo $kiriof_status_filter === 'unpaid' ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Waiting for Payment', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['unpaid'] ?? 0 ) ) ); ?>)</span></a></li>
                                        <li><a href="#" class="kiriof-filter-link<?php echo $kiriof_status_filter === 'paid' ? ' current' : ''; ?>" data-filter-key="status" data-filter-value="paid" <?php echo $kiriof_status_filter === 'paid' ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Paid', 'kiriminaja-official' ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( (int) ( $kiriof_statusCounts['paid'] ?? 0 ) ) ); ?>)</span></a></li>
                                    </ul>
                                    <form class="search-form search-plugins kiriof-payment-search-form">
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
                                        <button class="button kiriof-month-apply" type="button" data-month-select="month_search_1"><?php esc_html_e( 'Apply', 'kiriminaja-official' ); ?></button>
                                    </div>
                                    <?php if ( $total_pages > 1 ) : ?>
                                    <div class="tablenav-pages">
                                        <span class="pagination-links">
                                            <?php if ( $prev_page_link ) : ?>
                                            <a class="prev-page button kiriof-page-link" href="#" data-page="<?php echo (int) ( $page - 1 ); ?>"><span>&lsaquo;</span></a>
                                            <?php else : ?>
                                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                            <?php endif; ?>
                                            <span class="paging-input">
                                                <span class="tablenav-paging-text"><?php echo esc_html( $page ); ?> <?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span></span>
                                            </span>
                                            <?php if ( $next_page_link ) : ?>
                                            <a class="next-page button kiriof-page-link" href="#" data-page="<?php echo (int) ( $page + 1 ); ?>"><span>&rsaquo;</span></a>
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
                                                $kiriof_pickup_number = esc_attr( (string) ( $kiriof_row->pickup_number ?? '' ) );
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
                                                            <button class="button kiriof-payment-button" type="button" data-pickup-number="'.$kiriof_pickup_number.'" title="' . esc_attr__( 'Pay', 'kiriminaja-official' ) . '" aria-label="' . esc_attr__( 'Pay', 'kiriminaja-official' ) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px">
                                                                <span class="dashicons dashicons-money-alt" aria-hidden="true" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span>
                                                            </button>
                                                        ';                                                        
                                                    }else{
                                                        $kiriof_btnGroup.= '
                                                            <button class="button kiriof-reschedule-button" type="button" data-pickup-number="'.$kiriof_pickup_number.'" title="' . esc_attr__( 'Reschedule', 'kiriminaja-official' ) . '" aria-label="' . esc_attr__( 'Reschedule', 'kiriminaja-official' ) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px">
                                                                <span class="dashicons dashicons-update-alt" aria-hidden="true" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span>
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
                                                            <a class="button" href="'.esc_url($kiriof_detail_url).'" title="' . esc_attr__( 'Details', 'kiriminaja-official' ) . '" aria-label="' . esc_attr__( 'Details', 'kiriminaja-official' ) . '" style="padding:4px;width:32px;height:32px;border:none;box-shadow:none;border-radius:4px">
                                                                <span class="dashicons dashicons-visibility" aria-hidden="true" style="font-size:20px;width:20px;height:20px;line-height:20px;"></span>
                                                            </a>
                                                ';

                                                $kiriof_allowed_html = [
                                                    'a' => [
                                                        'class' => [],
                                                        'href' => [],
                                                        'title' => [],
                                                        'aria-label' => [],
                                                        'style' => [],
                                                    ],
                                                    'button' => [
                                                        'class' => [],
                                                        'type' => [],
                                                        'data-pickup-number' => [],
                                                        'title' => [],
                                                        'aria-label' => [],
                                                        'style' => [],
                                                    ],
                                                    'div' => [
                                                        'style' => [],
                                                    ],
                                                    'span' => [
                                                        'class' => [],
                                                        'aria-hidden' => [],
                                                        'style' => [],
                                                    ],
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
                                                } elseif ($kiriof_method === 'cod') {
                                                    $kiriof_methodContent = '
                                                        <div class="kj-badge" style="background:#f0f0f1;color:#50575e;">
                                                            <span>' . esc_html__('COD', 'kiriminaja-official') . '</span>
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
                                            <select id="month_search_2" class="kiriof-month-sync" data-sync-target="month_search_1">
                                                <option value="" <?php echo empty( $kiriof_month_filter ) ? 'selected' : ''; ?>><?php esc_html_e( 'All Dates', 'kiriminaja-official' ); ?></option>
                                                <?php
                                                if ( ! empty( $monthOptions ) && count($monthOptions) > 0 ) {
                                                    foreach ($monthOptions as $kiriof_key => $kiriof_value){
                                                        echo '<option value="' . esc_attr($kiriof_key) . '" ' . ( $kiriof_month_filter === $kiriof_key ? 'selected' : '' ) . '>' . esc_html($kiriof_value) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                            <button class="button kiriof-month-apply" type="button" data-month-select="month_search_2"><?php esc_html_e( 'Apply', 'kiriminaja-official' ); ?></button>
                                        </div>
                                        <?php if ( $total_pages > 1 ) : ?>
                                        <div class="tablenav-pages">
                                            <span class="pagination-links">
                                                <?php if ( $prev_page_link ) : ?>
                                                <a class="prev-page button kiriof-page-link" href="#" data-page="<?php echo (int) ( $page - 1 ); ?>"><span>&lsaquo;</span></a>
                                                <?php else : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                                <?php endif; ?>
                                                <span class="paging-input">
                                                    <span class="tablenav-paging-text"><?php echo esc_html( $page ); ?> <?php esc_html_e( 'of', 'kiriminaja-official' ); ?> <span class="total-pages"><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span></span>
                                                </span>
                                                <?php if ( $next_page_link ) : ?>
                                                <a class="next-page button kiriof-page-link" href="#" data-page="<?php echo (int) ( $page + 1 ); ?>"><span>&rsaquo;</span></a>
                                                <?php else : ?>
                                                <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <br class="clear">
                                     </div>
								</div>

    <?php include 'modal-payment.php' ?>
    <?php include 'modal-request-pickup.php' ?>
</div>
