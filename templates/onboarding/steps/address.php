<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="kiriof-onboarding__step" data-step-panel="address">
	<p class="kiriof-onboarding__step-number"><?php echo esc_html__( 'Step 2 of 4', 'kiriminaja-official' ); ?></p>
	<h2><?php echo esc_html( $steps['address']['title'] ); ?></h2>
	<p><?php echo esc_html( $steps['address']['description'] ); ?></p>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="kiriof-origin-name"><?php echo esc_html__( 'Sender name', 'kiriminaja-official' ); ?></label></th>
				<td><input id="kiriof-origin-name" class="regular-text kiriof-onboarding__field" name="origin_name" type="text" value="<?php echo esc_attr( $origin_values['origin_name'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="kiriof-origin-phone"><?php echo esc_html__( 'Sender phone', 'kiriminaja-official' ); ?></label></th>
				<td><input id="kiriof-origin-phone" class="regular-text kiriof-onboarding__field" name="origin_phone" type="text" value="<?php echo esc_attr( $origin_values['origin_phone'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="kiriof-origin-address"><?php echo esc_html__( 'Address', 'kiriminaja-official' ); ?></label></th>
				<td><textarea id="kiriof-origin-address" class="large-text kiriof-onboarding__field" name="origin_address" rows="3"><?php echo esc_textarea( $origin_values['origin_address'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="kiriof-origin-zip-code"><?php echo esc_html__( 'Zipcode', 'kiriminaja-official' ); ?></label></th>
				<td><input id="kiriof-origin-zip-code" class="regular-text kiriof-onboarding__field" name="origin_zip_code" type="text" value="<?php echo esc_attr( $origin_values['origin_zip_code'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="kiriof-origin-sub-district-id"><?php echo esc_html__( 'Subdistrict', 'kiriminaja-official' ); ?></label></th>
				<td>
					<select id="kiriof-origin-sub-district-id" name="origin_sub_district_id" class="kiriof-onboarding-subdistrict wc-enhanced-select-nostd kiriof-onboarding__field">
						<?php if ( ! empty( $origin_values['origin_sub_district_id'] ) ) : ?><option selected value="<?php echo esc_attr( $origin_values['origin_sub_district_id'] ); ?>"><?php echo esc_html( $origin_values['origin_sub_district_name'] ?? '' ); ?></option><?php endif; ?>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
	<input name="origin_latitude" type="hidden" value="<?php echo esc_attr( $origin_values['origin_latitude'] ?? '' ); ?>">
	<input name="origin_longitude" type="hidden" value="<?php echo esc_attr( $origin_values['origin_longitude'] ?? '' ); ?>">
	<div id="kiriof-onboarding-map" class="kiriof-onboarding__map"></div>
	<p class="description"><?php echo esc_html__( 'Move the map to place the pin at your pickup location.', 'kiriminaja-official' ); ?></p>
	<div class="kiriof-onboarding__message" data-step-message="address" role="alert"></div>
</section>
