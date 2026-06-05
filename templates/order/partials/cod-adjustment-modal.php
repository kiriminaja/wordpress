<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$max_cod = defined( 'KIRIOF_MAX_COD_AMOUNT' ) ? KIRIOF_MAX_COD_AMOUNT : 3000000;
?>
<div id="kiriof-cod-modal-backdrop" class="kiriof-modal-backdrop" style="display:none;" aria-hidden="true">
    <div class="kiriof-modal" role="dialog" aria-modal="true" aria-labelledby="kiriof-cod-modal-title">
        <div class="kiriof-modal__header">
            <div>
                <h2 id="kiriof-cod-modal-title" class="kiriof-modal__title">
                    <?php esc_html_e( 'COD Issue Information', 'kiriminaja-official' ); ?>
                </h2>
                <p class="kiriof-modal__subtitle">
                    <?php esc_html_e( 'Please change the COD Value information', 'kiriminaja-official' ); ?>
                </p>
            </div>
            <button type="button" class="kiriof-modal__close" id="kiriof-cod-modal-close" aria-label="<?php esc_attr_e( 'Close', 'kiriminaja-official' ); ?>">
                &times;
            </button>
        </div>

        <p class="kiriof-modal__description">
            <?php
            printf(
                /* translators: %s is the KiriminAja order ID */
                esc_html__( 'Adjust your COD value to the applicable terms and conditions to continue the shipping process with order id: %s.', 'kiriminaja-official' ),
                '<strong id="kiriof-modal-order-id"></strong>'
            );
            ?>
        </p>

        <div class="kiriof-modal__field">
            <label for="kiriof-cod-input" class="kiriof-modal__label">
                <?php esc_html_e( 'COD Value', 'kiriminaja-official' ); ?>
            </label>
            <div class="kiriof-modal__input-wrap">
                <span class="kiriof-modal__input-prefix">Rp</span>
                <input
                    type="number"
                    id="kiriof-cod-input"
                    class="kiriof-modal__input"
                    min="0"
                    step="1"
                    autocomplete="off"
                    data-max-cod="<?php echo esc_attr( $max_cod ); ?>"
                />
            </div>
            <p class="kiriof-modal__hint" id="kiriof-cod-hint"></p>
        </div>

        <table class="kiriof-modal__breakdown">
            <tbody>
                <tr class="kiriof-breakdown__row">
                    <td><?php esc_html_e( 'Sub Total', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-subtotal" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--parent">
                    <td><?php esc_html_e( 'Total Shipping', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-total-shipping" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--child">
                    <td><?php esc_html_e( 'Shipping', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-shipping" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--child">
                    <td><?php esc_html_e( 'Insurance', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-insurance" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--child">
                    <td><?php esc_html_e( 'COD Fee', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-cod-fee" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--discount" id="kiriof-bd-discount-row" style="display:none;">
                    <td>
                        <?php esc_html_e( 'Discount Code', 'kiriminaja-official' ); ?>
                        <span class="kiriof-badge kiriof-badge--discount" id="kiriof-bd-discount-code"></span>
                    </td>
                    <td id="kiriof-bd-discount" class="kiriof-breakdown__value kiriof-breakdown__value--discount"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--strong">
                    <td><?php esc_html_e( 'COD Paid By Buyer', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-cod-paid" class="kiriof-breakdown__value"></td>
                </tr>
                <tr class="kiriof-breakdown__row kiriof-breakdown__row--payout">
                    <td><?php esc_html_e( 'Estimated COD Payout', 'kiriminaja-official' ); ?></td>
                    <td id="kiriof-bd-payout" class="kiriof-breakdown__value kiriof-breakdown__value--payout"></td>
                </tr>
            </tbody>
        </table>

        <div class="kiriof-modal__footer">
            <button
                type="button"
                id="kiriof-cod-confirm"
                class="kiriof-modal__btn kiriof-modal__btn--primary"
                disabled
            >
                <?php esc_html_e( 'Confirm & Process', 'kiriminaja-official' ); ?>
            </button>
        </div>
    </div>
</div>
