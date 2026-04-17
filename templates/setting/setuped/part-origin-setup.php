<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<span style="font-size: 18px; font-weight: 600"><?php echo esc_html( kiriof_helper()->tlThis('Store Address',$locale) ); ?></span>
<div class="row-divider" style="margin-top: .5rem"></div>
<span><?php echo esc_html( kiriof_helper()->tlThis('This is where your business is located. Tax rates and shipping rates will use this address.', $locale) ); ?></span>

<div class="kj-form">
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html( kiriof_helper()->tlThis('Sender Name',$locale) ); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_name" placeholder="<?php echo esc_html( kiriof_helper()->tlThis('Sender Name',$locale) ); ?>" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_name'] ?? '');?>" >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html(kiriof_helper()->tlThis('Sender Phone',$locale)); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_phone" placeholder="<?php echo esc_html( kiriof_helper()->tlThis('Sender Phone',$locale) ); ?>" type="text" class="input-text regular-input kiriof_int_input" value="<?php echo esc_attr($inputValueArr['origin_phone'] ?? '');?>" >
            </td>
        </tr>
        
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html(kiriof_helper()->tlThis('Address',$locale)); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_address" placeholder="<?php echo esc_html(kiriof_helper()->tlThis('Address',$locale)); ?>" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_address'] ?? '');?>" >
            </td>
        </tr>
        <!-- Lat Long -->
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html(kiriof_helper()->tlThis('Latitude',$locale)); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_latitude" placeholder="<?php echo esc_attr(kiriof_helper()->tlThis('Latitude',$locale)); ?>" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_latitude'] ?? '');?>" >
            </td>
        </tr>

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html(kiriof_helper()->tlThis('Longitude',$locale)); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_longitude" placeholder="<?php echo esc_html(kiriof_helper()->tlThis('Longitude',$locale) ); ?>" type="text" class="input-text regular-input" value="<?php echo esc_attr($inputValueArr['origin_longitude'] ?? '');?>" >
            </td>
        </tr>

        <!-- end lat long -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html( kiriof_helper()->tlThis('Zipcode',$locale) ); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="origin_zip_code" placeholder="<?php echo esc_attr(kiriof_helper()->tlThis('Zipcode',$locale)); ?>" type="text" class="input-text regular-input kiriof_int_input" value="<?php echo esc_attr($inputValueArr['origin_zip_code'] ?? '');?>" >
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html( kiriof_helper()->tlThis('Area',$locale) ); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <select name="origin_sub_district_id" class="select-2">
                    <?php 
                    if ( @$inputValueArr['origin_sub_district_id'] && @$inputValueArr['origin_sub_district_name']){
                        echo '<option selected value="'.esc_attr($inputValueArr['origin_sub_district_id']).'">'.esc_html($inputValueArr['origin_sub_district_name']).'</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label>
                    <?php echo esc_html(kiriof_helper()->tlThis('Whitelist Expedition',$locale)); ?>
                </label>
            </th>
            <td class="forminp forminp-text">
                <select name="origin_whitelist_expedition[]" style="width:50%;" class="select-2 origin_whitelist_expedition" multiple="multiple">
                    <?php 
                    if ( @$inputValueArr['origin_whitelist_expedition_id'] && @$inputValueArr['origin_whitelist_expedition_name']){
                        $kiriof_expedition_name = explode(',',@$inputValueArr['origin_whitelist_expedition_name'] );
                        foreach ( explode(',',$inputValueArr['origin_whitelist_expedition_id']) as $kiriof_key => $kiriof_row) {
                            echo '<option selected value="'.esc_attr($kiriof_row).'">'.esc_html($kiriof_expedition_name[$kiriof_key]).'</option>';
                        }
                    }
                    ?>
                </select>
            </td>
        </tr>
        </tbody>
    </table>

    <!--ALERT-->
    <div class="alert kj-alert kj-hidden" style="margin-top: .5rem">
        <div style="display: flex">
            <svg style="position: relative; top: 2px" width="16" height="16" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6.99961 0.600006C10.5356 0.600006 13.3996 3.46401 13.3996 7.00001C13.3996 10.536 10.5356 13.4 6.99961 13.4C3.46361 13.4 0.599609 10.536 0.599609 7.00001C0.599609 3.46401 3.46361 0.600006 6.99961 0.600006ZM7.90361 8.10401L8.18361 2.93601H5.81561L6.09561 8.10401H7.90361ZM7.83161 10.792C8.02361 10.608 8.12761 10.352 8.12761 10.024C8.12761 9.68801 8.03161 9.43201 7.83961 9.24801C7.64761 9.06401 7.36761 8.96801 6.99161 8.96801C6.61561 8.96801 6.33561 9.06401 6.13561 9.24801C5.93561 9.43201 5.83961 9.68801 5.83961 10.024C5.83961 10.352 5.94361 10.608 6.14361 10.792C6.35161 10.976 6.63161 11.064 6.99161 11.064C7.35161 11.064 7.63161 10.976 7.83161 10.792Z" fill="#D63638"/>
            </svg>
            <div style="margin-left: 8px">
                <div>
                    <span style="font-weight: 600">Error</span>
                </div>
                <div>
                    <span class="msg">Invalid setup key, please try another key please</span>
                </div>
            </div>
        </div>
    </div>

    <!--Btn Group-->
    <div class="submit-container">
        <div class="row-divider"></div>
        <div class="kj-btn-container">
            <button class="button-wp woocommerce-save-button kj-submit-btn" type="button">
                <div style="display: flex">
                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                        <span><?php echo esc_html(kiriof_helper()->tlThis('Save Changes',$locale)); ?></span>
                    </div>
                </div>
            </button>
        </div>
        <div class="kj-btn-loader-container kj-hidden">
            <div class="kj-btn-loader" style="margin-top: auto; margin-bottom: auto; margin-left: .5rem"></div>
        </div>
    </div>
</div>
