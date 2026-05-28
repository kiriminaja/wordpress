<?php
/**
 * Minimal ShopVerse checkout template fixture.
 *
 * ShopVerse ships its checkout pattern as a WooCommerce Checkout Block, not the
 * classic checkout shortcode. Keep this local fixture so static CI tests do not
 * depend on a developer's /tmp/wordpress-local theme install.
 */
?>
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
    <!-- wp:woocommerce/checkout {"align":""} -->
    <div class="wp-block-woocommerce-checkout wc-block-checkout is-loading">
        <!-- wp:woocommerce/checkout-fields-block -->
        <div class="wp-block-woocommerce-checkout-fields-block"></div>
        <!-- /wp:woocommerce/checkout-fields-block -->

        <!-- wp:woocommerce/checkout-totals-block -->
        <div class="wp-block-woocommerce-checkout-totals-block"></div>
        <!-- /wp:woocommerce/checkout-totals-block -->
    </div>
    <!-- /wp:woocommerce/checkout -->
</main>
<!-- /wp:group -->
