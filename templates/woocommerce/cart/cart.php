<?php
/**
 * Cart Page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<div class="kj-block-components-sidebar-layout-cart">
<div class="kj-cart-main">
    <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
        <?php do_action( 'woocommerce_before_cart_table' ); ?>

        <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
            <thead>
                <tr>
                    <th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'kiriminaja-official' ); ?></span></th>
                    <th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail image', 'kiriminaja-official' ); ?></span></th>
                    <th class="product-name"><?php esc_html_e( 'Product', 'kiriminaja-official' ); ?></th>
                    <th class="product-price"><?php esc_html_e( 'Price', 'kiriminaja-official' ); ?></th>
                    <th class="product-quantity"><?php esc_html_e( 'Quantity', 'kiriminaja-official' ); ?></th>
                    <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'kiriminaja-official' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php do_action( 'woocommerce_before_cart_contents' ); ?>

                <?php
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template loop and product variables
                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $kiriof_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                    $kiriof_product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                    /**
                     * Filter the product name.
                     *
                     * @since 2.1.0
                     * @param string $product_name Name of the product in the cart.
                     * @param array $cart_item The product in the cart.
                     * @param string $cart_item_key Key for the product in the cart.
                     */
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
                    $product_name = apply_filters( 'woocommerce_cart_item_name', $kiriof_product->get_name(), $cart_item, $cart_item_key );

                    if ( $kiriof_product && $kiriof_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
                        $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $kiriof_product->is_visible() ? $kiriof_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                        ?>
                        <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

                            <td class="product-remove">
                                <?php
                                    echo wp_kses_post( apply_filters(
                                        'woocommerce_cart_item_remove_link',
                                        sprintf(
                                            '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                            esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                            /* translators: %s is the product name */
                                            esc_attr( sprintf( __( 'Remove %s from cart', 'kiriminaja-official' ), wp_strip_all_tags( $product_name ) ) ),
                                            esc_attr( $kiriof_product_id ),
                                            esc_attr( $kiriof_product->get_sku() )
                                        ),
                                        $cart_item_key
                                    ) );
                                ?>
                            </td>

                            <td class="product-thumbnail">
                            <?php
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $kiriof_product->get_image(), $cart_item, $cart_item_key );

                            if ( ! $product_permalink ) {
                                echo wp_kses_post( $thumbnail );
                            } else {
                                printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), wp_kses_post( $thumbnail ) );
                            }
                            ?>
                            </td>

                            <td class="product-name" data-title="<?php esc_attr_e( 'Product', 'kiriminaja-official' ); ?>">
                            <?php
                            if ( ! $product_permalink ) {
                                echo wp_kses_post( $product_name . '&nbsp;' );
                            } else {
                                /**
                                 * This filter is documented above.
                                 *
                                 * @since 2.1.0
                                 */
                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $kiriof_product->get_name() ), $cart_item, $cart_item_key ) );
                            }

                            do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );

                            // Meta data.
                            // PHPCS: XSS ok.
                            echo wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) );

                            // Backorder notification.
                            if ( $kiriof_product->backorders_require_notification() && $kiriof_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'kiriminaja-official' ) . '</p>', $kiriof_product_id ) );
                            }
                            ?>
                            </td>

                            <td class="product-price" data-title="<?php esc_attr_e( 'Price', 'kiriminaja-official' ); ?>">
                                <?php
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $kiriof_product ), $cart_item, $cart_item_key ) );
                                    // PHPCS: XSS ok.
                                ?>
                            </td>

                            <td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'kiriminaja-official' ); ?>">
                            <?php
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variables for quantity
                            if ( $kiriof_product->is_sold_individually() ) {
                                $kiriof_min_quantity = 1;
                                $kiriof_max_quantity = 1;
                            } else {
                                $kiriof_min_quantity = 0;
                                $kiriof_max_quantity = $kiriof_product->get_max_purchase_quantity();
                            }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variable
                            
                            $kiriof_product_quantity = woocommerce_quantity_input(
                                array(
                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                    'input_value'  => $cart_item['quantity'],
                                    'max_value'    => $kiriof_max_quantity,
                                    'min_value'    => $kiriof_min_quantity,
                                    'product_name' => $product_name,
                                ),
                                $kiriof_product,
                                false
                            );

                            echo wp_kses_post( apply_filters( 'woocommerce_cart_item_quantity', $kiriof_product_quantity, $cart_item_key, $cart_item ) );
                            // PHPCS: XSS ok.
                            ?>
                            </td>

                            <td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'kiriminaja-official' ); ?>">
                                <?php
                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $kiriof_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ) );
                                    // PHPCS: XSS ok.
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>

                <?php do_action( 'woocommerce_cart_contents' ); ?>

                <tr>
                    <td colspan="6" class="actions">

                        <?php if ( wc_coupons_enabled() ) { ?>
                            <div class="coupon">
                                <label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'kiriminaja-official' ); ?></label> <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'kiriminaja-official' ); ?>" /> <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'kiriminaja-official' ); ?>"><?php esc_html_e( 'Apply coupon', 'kiriminaja-official' ); ?></button>
                                <?php do_action( 'woocommerce_cart_coupon' ); ?>
                            </div>
                        <?php } ?>

                        <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'kiriminaja-official' ); ?>"><?php esc_html_e( 'Update cart', 'kiriminaja-official' ); ?></button>

                        <?php do_action( 'woocommerce_cart_actions' ); ?>

                        <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                    </td>
                </tr>

                <?php do_action( 'woocommerce_after_cart_contents' ); ?>
            </tbody>
        </table>
        <?php do_action( 'woocommerce_after_cart_table' ); ?>
    </form>
</div><!-- end div.kj-cart-main -->


<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>

<div class="kj-cart-sidebar">    
    <div class="cart-collaterals">
        <?php
            /**
             * Cart collaterals hook.
             *
             * @hooked woocommerce_cross_sell_display
             * @hooked woocommerce_cart_totals - 10
             */
            do_action( 'woocommerce_cart_collaterals' );
        ?>
    </div>
</div><!-- end div.kj-cart-sidebar -->


</div> <!-- end div.kj-block-somponents-sidebar-layout-cart  -->


<?php do_action( 'woocommerce_after_cart' ); ?>
