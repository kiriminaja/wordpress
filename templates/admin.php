<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiriminaja-official' ) );
}
?>
<div class="wrap">

    <h1>KiriminAja</h1>


    <h2>Setup Key</h2>
    <div id="kiriminaja-api-setting" class="kiriminaja-api-setting">
        <div class="api-field">
            <input name="kiriminaja_setting[setup_key]" type="text" class="input-text regular-input" value="" >
            <button class="button form-submit" type="button">Koneksikan</button>
        </div>
    </div>

    <h2>Setup Key</h2>
    <div id="kiriminaja-api-setting" class="kiriminaja-api-setting">
        <div class="api-field">
            <select name="kiriminaja_setting[store_destination]" class="select-2"></select>

        </div>
    </div>

<!--    <table class="form-table">-->
<!--        <tbody>-->
<!--        <tr valign="top">-->
<!--            <th scope="row" class="titledesc">--><?php //esc_html_e('Store Origin', 'kiriminaja-official'); ?><!--</th>-->
<!--            <td class="forminp forminp-single_select_page_with_search">-->
<!--            </td>-->
<!--        </tr>-->
<!--        </tbody>-->
<!--    </table>-->
</div>
<?php
wp_add_inline_script(
    'kiriof-script',
    'jQuery(function($){$("[name=\"kiriminaja_setting[store_destination]\"]").select2();});'
);
?>