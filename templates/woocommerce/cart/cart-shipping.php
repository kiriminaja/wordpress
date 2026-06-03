<?php
/**
 * Shipping Methods Display
 *
 * In 2.1 we show methods per package. This allows for multiple methods per order if so desired.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.8.0
 *
 * @var string $kiriof_formatted_destination
 * @var bool $kiriof_has_calculated_shipping
 * @var bool $kiriof_show_shipping_calculator
 * @var string $kiriof_calculator_text
 * @var string $package_name
 * @var string $package_details
 * @var array $available_methods
 * @var string $chosen_method
 * @var int $index
 * @var bool $show_package_details
 * @var mixed $formatted_destination
 * @var mixed $has_calculated_shipping
 * @var mixed $package
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variables
$kiriof_formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variables
$kiriof_has_calculated_shipping  = ! empty( $has_calculated_shipping );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variables
$kiriof_show_shipping_calculator = ! empty( $show_shipping_calculator );
$kiriof_calculator_text          = '';
?>
<tr class="woocommerce-shipping-totals shipping">
	<th><?php echo wp_kses_post( $package_name ); ?></th>
	<td data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php
        if ( ! $kiriof_has_calculated_shipping || ! $kiriof_formatted_destination ){
			if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'kiriminaja-official' ) ) );
			} else {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', '' ) );
			}
		}else {
			$kiriof_calculator_text = esc_html__( 'Enter a different address', 'kiriminaja-official' );
		}
		?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>

		<?php if ( $kiriof_show_shipping_calculator ) : ?>
			<?php woocommerce_shipping_calculator( $kiriof_calculator_text ); ?>
		<?php endif; ?>

        <?php 
		if ( ! empty( $available_methods ) && is_array( $available_methods ) ) : ?>
			<ul id="shipping_method" class="woocommerce-shipping-methods kiriof-shipping-methods-list">
				<?php
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template loop variable
				foreach ( $available_methods as $method ) : ?>
					<li class="kiriof-shipping-method-item">
						<?php
						$kiriof_method_label_html = wp_kses_post( wc_cart_totals_shipping_method_label( $method ) );
						$kiriof_original_cost = method_exists( $method, 'get_meta' ) ? (float) $method->get_meta( 'kiriof_shipping_coupon_original_cost', true ) : 0.0;
						$kiriof_discount_amount = method_exists( $method, 'get_meta' ) ? (float) $method->get_meta( 'kiriof_shipping_coupon_discount_amount', true ) : 0.0;
						$kiriof_badge = method_exists( $method, 'get_meta' ) ? (string) $method->get_meta( 'kiriof_shipping_coupon_badge', true ) : '';
						$kiriof_notice = method_exists( $method, 'get_meta' ) ? (string) $method->get_meta( 'kiriof_shipping_coupon_notice', true ) : '';
						$kiriof_current_cost = isset( $method->cost ) ? (float) $method->cost : 0.0;

						if ( $kiriof_discount_amount > 0 && $kiriof_original_cost > $kiriof_current_cost ) {
							$kiriof_method_label_html = esc_html( (string) $method->get_label() );
							if ( '' !== $kiriof_badge ) {
								$kiriof_method_label_html .= ' <span class="kiriof-shipping-rate-badge">' . esc_html( $kiriof_badge ) . '</span>';
							}

							$kiriof_method_label_html .= '<span class="kiriof-shipping-rate-pricing">';
							$kiriof_method_label_html .= '<del class="kiriof-shipping-rate-original">' . wp_kses_post( wc_price( $kiriof_original_cost ) ) . '</del>';
							$kiriof_method_label_html .= '<ins class="kiriof-shipping-rate-discounted">' . wp_kses_post( wc_price( $kiriof_current_cost ) ) . '</ins>';
							$kiriof_method_label_html .= '</span>';
							$kiriof_method_label_html .= '<span class="kiriof-shipping-rate-savings">';
							$kiriof_method_label_html .= sprintf(
								/* translators: %s discount amount. */
								esc_html__( 'Save %s', 'kiriminaja-official' ),
								wp_strip_all_tags( wc_price( $kiriof_discount_amount ) )
							);
							$kiriof_method_label_html .= '</span>';
						} elseif ( '' !== $kiriof_notice ) {
							$kiriof_method_label_html = esc_html( (string) $method->get_label() );
							$kiriof_method_label_html .= '<span class="kiriof-shipping-rate-pricing"><span class="amount">' . wp_kses_post( wc_price( $kiriof_current_cost ) ) . '</span></span>';
							$kiriof_method_label_html .= '<span class="kiriof-shipping-rate-note">' . esc_html( $kiriof_notice ) . '</span>';
						}

						if ( 1 < count( $available_methods ) ) {
							printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method kiriof-shipping-method-input" %4$s />', absint( $index ), esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							// WPCS: XSS ok.
						} else {
							printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method kiriof-shipping-method-input" %4$s />', absint( $index ), esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							// WPCS: XSS ok.
						}
						printf( '<label class="kiriof-shipping-method-label" for="shipping_method_%1$s_%2$s">%3$s</label>', absint( $index ), esc_attr( sanitize_title( $method->id ) ), $kiriof_method_label_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above via wp_kses_post.
						do_action( 'woocommerce_after_shipping_rate', $method, $index );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( is_cart() ) : ?>
				<p class="woocommerce-shipping-destination" style="display:none;">
					<?php
					if ( $kiriof_formatted_destination ) {
						// Translators: $s shipping destination.
						printf( esc_html__( 'Shipping to %s.', 'kiriminaja-official' ) . ' ', '<strong>' . esc_html( $kiriof_formatted_destination ) . '</strong>' );
						$kiriof_calculator_text = esc_html__( 'Change address', 'kiriminaja-official' );
					} else {
						echo wp_kses_post( apply_filters( 'woocommerce_shipping_estimate_html', __( 'Shipping options will be updated during checkout.', 'kiriminaja-official' ) ) );
					}
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</td>
</tr>
