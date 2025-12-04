<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        jQuery('[name="kiriminaja_setting[store_destination]"]').select2();
    });
</script>