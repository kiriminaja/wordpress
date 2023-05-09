<div class="kiriminaja-setting <?php echo empty( $settings['token'] ) ? 'unset' : ''; ?>">
	<h2><?php esc_html_e( 'Server IP Anda', 'kiriminaja' ) ?></h2>
	<div class="api-field"><?php $realIP = file_get_contents("http://ipecho.net/plain");?>
		<input type="text" class="input-text regular-input" value="<?php echo $realIP; ?>" readonly>
	</div>
	<h2><?php esc_html_e( 'API Setting', 'kiriminaja' ) ?></h2>
	<div class="kiriminaja-api-setting">
		<div class="api-field">
			<input name="kiriminaja_setting[token]" type="text" class="input-text regular-input" value="<?php echo esc_attr( $settings['token'] ); ?>" <?php echo ! empty( $settings['token'] ) ? 'readonly' : ''; ?>>
			<?php if ( empty( $settings['token'] ) ) : ?>
				<button class="button" type="button" id="kiriminaja-check-key"><?php esc_html_e( 'Check Key', 'kiriminaja' ); ?></button>
			<?php else: ?>
				<button class="button" type="button" id="kiriminaja-delete-key"><?php esc_html_e( 'Delete Key', 'kiriminaja' ); ?></button>
			<?php endif; ?>
		</div>
		<?php if ( empty( $settings['token'] ) ) : ?>
			<!-- <p class="description">Lorem ipsum dolor sit amet, consectetur, adipisicing elit. Ad quod corporis minus temporibus. Repellendus, ducimus ex quae vero nam et.</p> -->
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $settings['token'] ) ) : ?>
		<h2><?php esc_html_e( 'Store Setting', 'kiriminaja' ) ?></h2>
		<?php if ( ! $this->helper->is_store_set() ) : ?>
			<p class="description"><?php esc_html_e( 'Please complete the config below.', 'kiriminaja' ); ?></p>
		<?php endif; ?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Order Prefix', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-text">
						<input readonly type="text" maxlength="4" class="input-text regular-input" value="<?php echo esc_attr( $settings['ref_prefix'] ); ?>">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store Name', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-text">
						<input name="kiriminaja_setting[store_name]" type="text" class="input-text regular-input" value="<?php echo esc_attr( $settings['store_name'] ); ?>">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store Province', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-single_select_page_with_search">
						<select name="kiriminaja_setting[store_province]" class="select">
							<option value=""><?php esc_html_e( 'Select province', 'kiriminaja' ); ?></option>
							<?php foreach ( $provinces as $key => $value ) : ?>
								<option value="<?php echo esc_attr( $key ) ?>" <?php echo intval( $key ) === intval( $settings['store_province'] ) ? 'selected' : ''; ?>><?php echo esc_html( $value ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store City', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-single_select_page_with_search">
						<select name="kiriminaja_setting[store_city]" class="select">
							<option value=""><?php esc_html_e( 'Select city', 'kiriminaja' ); ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store District', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-single_select_page_with_search">
						<select name="kiriminaja_setting[store_district]" class="select">
							<option value=""><?php esc_html_e( 'Select district', 'kiriminaja' ); ?></option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store Address', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-textarea">
						<textarea name="kiriminaja_setting[store_address]"><?php echo esc_textarea( $settings['store_address'] ); ?></textarea>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store Zipcode', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-text">
						<input name="kiriminaja_setting[store_zipcode]" type="text" class="input-text regular-input" pattern="^([1-9])[0-9]{4}$" value="<?php echo esc_attr( $settings['store_zipcode'] ); ?>">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Store Phone', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-text">
						<input name="kiriminaja_setting[store_phone]" type="text" class="input-text regular-input" pattern="(\+62 ((\d{3}([ -]\d{3,})([- ]\d{4,})?)|(\d+)))|(\(\d+\) \d+)|\d{3}( \d+)+|(\d+[ -]\d+)|\d+" minlength="10" value="<?php echo esc_attr( $settings['store_phone'] ); ?>">
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc"><?php esc_html_e( 'Active Couriers', 'kiriminaja' ); ?></th>
					<td class="forminp forminp-checkbox">
						<?php foreach ( $couriers as $courier ) : ?>
							<fieldset>
								<label for="couriers-<?php echo esc_attr( $courier->code ); ?>">
									<input name="kiriminaja_setting[couriers][]" id="couriers-<?php echo esc_attr( $courier->code ); ?>" type="checkbox" value="<?php echo esc_attr( $courier->code ); ?>" <?php echo in_array( $courier->code, $settings['couriers'], true ) ? 'checked' : ''; ?>> <?php echo esc_html( $courier->name ); ?>
								</label>
							</fieldset>
						<?php endforeach; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php wp_nonce_field( 'update_setting', 'kiriminaja_action' ); ?>
	<?php endif; ?>
</div>
