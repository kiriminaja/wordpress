<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Page-specific styles for the tracking shortcode are loaded as a real
// stylesheet via inc/Base/Enqueue.php (handle: kiriof-tracking-style) so
// they are present in <head> before this shortcode renders in <body>.
?>

<div style="min-height: 40vh" class="woocommerce woocommerce-page">
    <form style="width: 100%" name="checkout" method="post" class="checkout woocommerce-checkout"  enctype="multipart/form-data" novalidate="novalidate">
        <div class="col2-set" id="">
            <h3 ><?php esc_html_e('Pesanan Anda','kiriminaja-official'); ?></h3>
            <div class="woocommerce-checkout-review-order">


                <p class="form-row form-row-wide" id="billing_company_field" data-priority="30">
                    <label for="billing_company" class=""><?php esc_html_e('Nomor Resi','kiriminaja-official'); ?> <span style="color:red;">*</span></label>
                    <span class="woocommerce-input-wrapper">
                        <input type="text" class="input-text" name="order_number" placeholder="<?php esc_attr_e( 'Masukan Nomor Resi atau Nomor Order ...', 'kiriminaja-official' ); ?>" value="" autocomplete="organization">
                    </span>
                </p>

                <button style="width: 100%" type="button" class="button track-btn alt wp-element-button track-btn"><?php esc_html_e('Lacak Pesanan','kiriminaja-official'); ?></button>
            </div>
        </div>
        <div class="col2-set" id="tracking-result">
            <div style="margin-top: 2rem"></div>
            <div class="state-blank">
                <div style="text-align: center">
                    <span style="font-weight: 700"><?php esc_html_e('Untuk mendapatkan informasi pesanan anda','kiriminaja-official'); ?><br><?php esc_html_e('Klik Track Pesanan','kiriminaja-official'); ?></span>
                </div>
            </div>
            <div class="state-err kj-hidden">
                <div style="text-align: center; margin-top: 4rem">
                    <span style="font-weight: 700" id="err_msg"><?php esc_html_e('Order tidak ditemukan','kiriminaja-official'); ?></span>
                </div>
            </div>
            <div class="state-loading kj-hidden">
                <div style="display: flex">
                    <div style="margin: 3rem auto">
                        <div class="kj-loader"></div>
                    </div>                    
                </div>
            </div>
            <div class="state-success kj-hidden">
                
                 <!-- Load Ajax -->
                <div class="tracking-details"></div>

                <table class="tracking-table">
                    <thead>
                        <tr>
                            <th width="20%"><?php esc_html_e( 'Tanggal', 'kiriminaja-official' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'kiriminaja-official' ); ?></th>
                        </tr>                    
                    </thead>
                    <tbody>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                        <tr>
                            <td>2021-07-14 16=>00=>00</td>
                            <td>Delivered to BAGUS | 14-07-2021 16=>00 | YOGYAKARTA </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
