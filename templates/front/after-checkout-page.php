<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="woocommerce-order-details">
    <h2 class="woocommerce-order-details__title">Pembayaran</h2>

    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

        <thead>
            <tr>
                <th class="woocommerce-table__product-name product-name">NOMOR PESANAN:</th>
                <th class="woocommerce-table__product-table product-total">Total</th>
            </tr>
            <tr>
                <th class="woocommerce-table__product-name product-name">TANGGAL:</th>
                <th class="woocommerce-table__product-table product-total">Total</th>
            </tr>
        </thead>

    </table>

</section>
<?php
// Enqueue the after-checkout script and localize transaction data
wp_enqueue_script('kiriof-after-checkout', KIRIOF_URL . 'assets/js/templates/after-checkout.js', array('jquery', 'kiriof-script'), KIRIOF_VERSION, true);
$kiriof_localized_transaction = is_array( $transaction ) ? $transaction : ( is_object( $transaction ) ? (array) $transaction : array() );
wp_localize_script('kiriof-after-checkout', 'kiriofTransactionData', $kiriof_localized_transaction);
?>