<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<span style="font-size: 18px; font-weight: 600">Webhooks</span>
<div class="row-divider" style="margin-top: .5rem"></div>
<span><?php echo esc_html( kiriof_helper()->tlThis('This page is how the wordpress communicate with kiriminaja api.',$locale) ); ?></span><div class="kj-form">
    <table class="form-table">
        <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label >
                    Hooks
                </label>
            </th>
            <td class="forminp forminp-text">
                <input style="width: 100%; max-width: 25rem" name="callback_url" type="text" class="input-text regular-input" value="<?php echo esc_url( $inputValueArr['callback_url'] ?? '' );?>" >
            </td>
        </tr>
        </tbody>
    </table>
    
    <!--Btn Group-->
    <div class="submit-container">
        <div class="row-divider"></div>
        <div class="kj-btn-container">
            <?php
            /**
             * This is the Webhooks form — the button must SAVE the callback
             * URL, not disconnect the integration. Use the same `kj-submit-btn`
             * class the tab-advanced save handler binds to in
             * templates/setting/setuped/index.php.
             */
            ?>
            <button class="button-wp woocommerce-save-button kj-submit-btn" type="button">
                <div style="display: flex">
                    <div style="display: flex;align-items: center;justify-items: center;margin: auto">
                        <span><?php echo esc_html( kiriof_helper()->tlThis( 'Save', $locale ) ); ?></span>
                    </div>
                </div>
            </button>
        </div>
        <div class="kj-btn-loader-container kj-hidden">
            <div class="kj-btn-loader" style="margin-top: auto; margin-bottom: auto; margin-left: .5rem"></div>
        </div>
    </div>
</div>