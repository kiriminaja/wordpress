<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap kj-wrap">
    <style><?php include '_section-css-shared.php'; ?></style>
    <?php $kiriof_title = __( 'Courier List', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">
    <div class="kj-detail">
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div style="margin-bottom:0.75rem;">
                <button type="button" class="button kj-courier-enable-all" disabled><?php echo esc_html__( 'Enable All', 'kiriminaja-official' ); ?></button>
                <button type="button" class="button kj-courier-disable-all" disabled><?php echo esc_html__( 'Disable All', 'kiriminaja-official' ); ?></button>
                <span class="kj-courier-status" role="status" aria-live="polite"></span>
            </div>
            <div id="kiriof-courier-list" class="kiriof-services"><?php echo esc_html__( 'Loading couriers…', 'kiriminaja-official' ); ?></div>
        </div>
    </div>
</div>
<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
    jQuery(document).ready(function($) {
        var $list = $('#kiriof-courier-list');
        var $status = $('.kj-courier-status');
        var $bulk = $('.kj-courier-enable-all, .kj-courier-disable-all');
        var picker;
        var savedState;
        var strings = kiriofCourierServicesI18n;

        function result(response) {
            if (!response || response.success === false) {
                return { status: 0, message: response && response.data && response.data.message };
            }
            var parsed = response.data || response;
            return parsed.success === false ? { status: 0, message: parsed.message } : parsed;
        }
        function save() {
            var payload = picker.getPayload();
            payload.nonce = kiriofAjax.nonce;
            picker.setDisabled(true);
            $bulk.prop('disabled', true);
            $status.css('color', '').text(strings.saving);
            var failed = false;
            function rollback(text) {
                failed = true;
                picker.setState(savedState);
                $status.css('color', '#b32d2e').text(text || strings.saveFailed);
            }
            $.ajax({
                type: 'post', url: kiriofAjaxRoute(),
                data: { action: 'kiriof_store_courier_whitelist', data: payload }
            }).done(function(response) {
                var parsed = result(response);
                if (Number(parsed.status) !== 200) { rollback(parsed.message); return; }
                savedState = picker.getState();
            }).fail(function(request) {
                var parsed = kiriofParseAjaxResponse(request);
                rollback(parsed && parsed.message);
            }).always(function() {
                picker.setDisabled(false);
                $bulk.prop('disabled', false);
                $status.css('color', failed ? '#b32d2e' : '');
                if (!failed) { $status.text(strings.saved); }
            });
        }
        $.ajax({
            type: 'post', url: kiriofAjaxRoute(),
            data: { action: 'kiriof_get_courier_whitelist', data: { nonce: kiriofAjax.nonce } }
        }).done(function(response) {
            var parsed = result(response);
            if (Number(parsed.status) !== 200 || !parsed.data) {
                $list.text(parsed.message || strings.loadFailed);
                return;
            }
            picker = window.kiriofCourierServices.create($list[0], {
                data: parsed.data, onChange: save
            });
            savedState = picker.getState();
            $bulk.prop('disabled', false);
        }).fail(function(request) {
            var parsed = kiriofParseAjaxResponse(request);
            $list.text(parsed && parsed.message ? parsed.message : strings.loadFailed);
        });
        $('.kj-courier-enable-all').on('click', function() { if (picker) { picker.setAll(true); } });
        $('.kj-courier-disable-all').on('click', function() { if (picker) { picker.setAll(false); } });
    });
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
