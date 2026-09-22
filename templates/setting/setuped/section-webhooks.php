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

    <?php $kiriof_title = __( 'Webhooks', 'kiriminaja-official' ); $kiriof_parent_url = $kiriof_base_url; $kiriof_parent_title = __( 'Settings', 'kiriminaja-official' ); include KIRIOF_DIR . 'templates/_header.php'; ?>
    <hr class="wp-header-end">

    <div class="kj-detail">

        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;">
            <div class="kj-form">
                <table class="form-table">
                    <tbody>
                    <tr><th><label><?php echo esc_html( __( 'Callback URL', 'kiriminaja-official' ) ); ?></label></th><td><input style="width:100%;max-width:25rem" name="callback_url" type="text" class="input-text regular-input" value="<?php echo esc_url( $inputValueArr['callback_url'] ?? '' );?>"></td></tr>
                    </tbody>
                </table>
                <button class="button button-primary kj-submit-btn" type="button"><?php echo esc_html( __( 'Save', 'kiriminaja-official' ) ); ?></button>
            </div>
        </div>
    </div>
</div>
