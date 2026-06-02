<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Section: Webhooks (detail page)
 *
 * @var string $locale
 * @var array $inputValueArr
 * @var string $kiriof_base_url
 */
?>
<div class="wrap kj-wrap">

    <style><?php include '_section-css-shared.php'; ?></style>

    <?php $kiriof_title = kiriof_helper()->tlThis('Webhooks',$locale); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = kiriof_helper()->tlThis('Settings',$locale); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail">

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div class="kj-form">
                <table class="form-table">
                    <tbody>
                    <tr><th><label><?php echo esc_html( kiriof_helper()->tlThis('Callback URL',$locale) ); ?></label></th><td><input style="width:100%;max-width:25rem" name="callback_url" type="text" class="input-text regular-input" value="<?php echo esc_url( $inputValueArr['callback_url'] ?? '' );?>"></td></tr>
                    </tbody>
                </table>
                <button class="button button-primary kj-submit-btn" type="button"><?php echo esc_html( kiriof_helper()->tlThis('Save',$locale) ); ?></button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
    <?php include '_section-js-shared.php'; ?>
    jQuery('body').on('click','.kj-detail .kj-submit-btn',function(e){var $b=jQuery(this);$b.prop('disabled',true);jQuery.ajax({type:'post',url:kiriofAjaxRoute(),data:{action:'kiriof_store_call_back_data',data:{callback_url:jQuery('[name="callback_url"]').val(),nonce:kiriofAjax.nonce}},error:function(){$b.prop('disabled',false);alert('Network error')},complete:function(r){var p=kiriofParseAjaxResponse(r);$b.prop('disabled',false);if(p&&p.status===200){alert('Saved')}else{alert((p&&p.message)?p.message:'Save failed')}}});
<?php
$kiriof_inline_script = ob_get_clean();
wp_add_inline_script( 'kiriof-script', $kiriof_inline_script );
?>
