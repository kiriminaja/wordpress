<?php
/**
 * Shipping Calculator
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/shipping-calculator.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_shipping_calculator' ); ?>

<form class="woocommerce-shipping-calculator" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

	<section class="shipping-calculator-form">

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_country', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_country_field">
				<label for="calc_shipping_country" class="screen-reader-text"><?php esc_html_e( 'Country / region:', 'kiriminaja-official' ); ?></label>
				<select name="calc_shipping_country" id="calc_shipping_country" class="country_to_state country_select" rel="calc_shipping_state">
					<option value="default"><?php esc_html_e( 'Select a country / region&hellip;', 'kiriminaja-official' ); ?></option>
					<?php foreach ( WC()->countries->get_shipping_countries() as $kiriof_country_key => $kiriof_country_value ) {
						echo '<option value="' . esc_attr( $kiriof_country_key ) . '"' . selected( WC()->customer->get_shipping_country(), esc_attr( $kiriof_country_key ), false ) . '>' . esc_html( $kiriof_country_value ) . '</option>';
					}
					?>
				</select>
			</p>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_state', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_state_field">
				<?php
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template variables
				$kiriof_current_cc = WC()->customer->get_shipping_country();
				$kiriof_current_r  = WC()->customer->get_shipping_state();
				$kiriof_states     = WC()->countries->get_states( $kiriof_current_cc );

				if ( is_array( $kiriof_states ) && empty( $kiriof_states ) ) {
					?>
					<input type="hidden" name="calc_shipping_state" id="calc_shipping_state" placeholder="<?php esc_attr_e( 'State / County', 'kiriminaja-official' ); ?>" />
					<?php
				} elseif ( is_array( $kiriof_states ) ) {
					?>
					<span>
						<label for="calc_shipping_state" class="screen-reader-text"><?php esc_html_e( 'State / County:', 'kiriminaja-official' ); ?></label>
						<select name="calc_shipping_state" class="state_select" id="calc_shipping_state" data-placeholder="<?php esc_attr_e( 'State / County', 'kiriminaja-official' ); ?>">
							<option value=""><?php esc_html_e( 'Select an option&hellip;', 'kiriminaja-official' ); ?></option>
							<?php
							// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce template loop variables
							foreach ( $kiriof_states as $ckiriof_country_key => $ckiriof_country_value ) {
								echo '<option value="' . esc_attr( $ckiriof_country_key ) . '" ' . selected( $kiriof_current_r, $ckiriof_country_key, false ) . '>' . esc_html( $ckiriof_country_value ) . '</option>';
							}
							?>
						</select>
					</span>
					<?php
				} else {
					?>
					<label for="calc_shipping_state" class="screen-reader-text"><?php esc_html_e( 'State / County:', 'kiriminaja-official' ); ?></label>
					<input type="text" class="input-text" value="<?php echo esc_attr( $kiriof_current_r ); ?>" placeholder="<?php esc_attr_e( 'State / County', 'kiriminaja-official' ); ?>" name="calc_shipping_state" id="calc_shipping_state" />
					<?php
				}
				?>
			</p>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_city', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_city_field">
				<label for="calc_shipping_city" class="screen-reader-text"><?php esc_html_e( 'City:', 'kiriminaja-official' ); ?></label>
				<input type="text" class="input-text" value="<?php echo esc_attr( WC()->customer->get_shipping_city() ); ?>" placeholder="<?php esc_attr_e( 'City', 'kiriminaja-official' ); ?>" name="calc_shipping_city" id="calc_shipping_city" />
			</p>
		<?php endif; ?>

		<?php if ( apply_filters( 'woocommerce_shipping_calculator_enable_postcode', true ) ) : ?>
			<p class="form-row form-row-wide" id="calc_shipping_postcode_field">
				<label for="calc_shipping_postcode" class="screen-reader-text"><?php esc_html_e( 'Postcode / ZIP:', 'kiriminaja-official' ); ?></label>
				<input type="text" class="input-text" value="<?php echo esc_attr( WC()->customer->get_shipping_postcode() ); ?>" placeholder="<?php esc_attr_e( 'Postcode / ZIP', 'kiriminaja-official' ); ?>" name="calc_shipping_postcode" id="calc_shipping_postcode" />
			</p>
		<?php endif; ?>

        <?php 
                woocommerce_form_field( 'kj_destination_area', array(
                    'type'        => 'select',
                    'label'       => esc_html__('District', 'kiriminaja-official'),
                    'required'    => true,
                    'options'     => array(WC()->session->get('destination_id') => WC()->session->get('destination_name'))
                ));        
        ?>

		<p><button type="submit" style="display:none;" name="calc_shipping" value="1" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php esc_html_e( 'Update', 'kiriminaja-official' ); ?></button></p>
		<?php wp_nonce_field( 'woocommerce-shipping-calculator', 'woocommerce-shipping-calculator-nonce' ); ?>
	</section>
</form>

<?php do_action( 'woocommerce_after_shipping_calculator' ); ?>
