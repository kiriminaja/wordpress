<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Shared inline JS utilities for all section detail pages.
?>
    // Heartbeat nonce refresh
    jQuery(document).on('heartbeat-send', function(e, data){ data.kiriof_nonce_check = true; });
    jQuery(document).on('heartbeat-tick', function(e, data){ if (data.kiriof_new_nonce) kiriofAjax.nonce = data.kiriof_new_nonce; });

    // Safe AJAX response parser
    window.kiriofParseAjaxResponse = function(r){
        try {
            var p = JSON.parse(r.responseText);
            p = (p && typeof p === 'object' && 'data' in p) ? p.data : p;
            return (p && typeof p === 'object') ? p : { status: 0, message: '<?php echo esc_js( __( 'Unexpected response.', 'kiriminaja-official' ) ); ?>' };
        } catch(e) {
            return { status: 0, message: '<?php echo esc_js( __( 'Server returned an invalid response.', 'kiriminaja-official' ) ); ?>' };
        }
    };
