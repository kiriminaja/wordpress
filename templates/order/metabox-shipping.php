<?php
// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * KiriminAja Shipping metabox — rendered on the WooCommerce order edit screen.
 *
 * Variables available:
 * @var array    $data                Shipping info from ShippingInfoServices.
 * @var string   $tracking_url        Public tracking page URL.
 * @var string   $detail_url          Admin KiriminAja transaction process page URL.
 * @var float    $wc_subtotal         WC order subtotal (product items, pre-shipping).
 * @var float    $wc_total            WC order grand total (= COD paid by buyer).
 * @var float    $wc_discount_total   WC coupon discount total (item discounts only).
 * @var float    $wc_shipping_discount WC shipping-line discount (percentage/fixed shipping coupons).
 * @var string[] $wc_coupon_codes     Coupon code strings applied to the order.
 * @var bool     $wc_needs_payment    Whether the WC order still needs payment.
 */

$kiriof_shipping_raw  = (float) ($data['shipping_cost_raw']  ?? 0);
$kiriof_insurance_raw = (float) ($data['insurance_cost_raw'] ?? 0);
$kiriof_cod_fee_raw   = (float) ($data['cod_fee_raw']        ?? 0);
$kiriof_discount_raw  = (float) ($data['discount_amount_raw'] ?? 0);
$kiriof_total_shipping = $kiriof_shipping_raw + $kiriof_insurance_raw + $kiriof_cod_fee_raw;
$kiriof_wc_shipping_discount = isset($wc_shipping_discount) ? (float) $wc_shipping_discount : 0.0;
$kiriof_discounted_shipping = max(0, $kiriof_shipping_raw - $kiriof_wc_shipping_discount);
$kiriof_is_cod        = $kiriof_cod_fee_raw > 0 || strtolower($data['payment_type'] ?? '') === 'cod';
$kiriof_is_deficit    = ! empty($data['is_deficit']);
$kiriof_cod_minimum   = (float) ($data['cod_minimum'] ?? 0);

// COD Paid By Buyer = WC order total; payout = total – total_shipping.
$kiriof_cod_paid = $wc_total;
$kiriof_payout   = $kiriof_cod_paid - $kiriof_shipping_raw - $kiriof_insurance_raw - $kiriof_cod_fee_raw;

// Pickup badge: only shown when a pickup has been scheduled.
$kiriof_has_pickup = ! empty($data['pickup_id']) && '-' !== $data['pickup_id'];

// Effective discount: use the larger of KA discount_amount or WC coupon discount.
$kiriof_effective_discount = max($kiriof_discount_raw, $wc_discount_total);
$kiriof_first_coupon       = ! empty($wc_coupon_codes) ? strtoupper($wc_coupon_codes[0]) : '';
?>
<style>
    #kiriminaja-shipping-info .kiriof-mb-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    #kiriminaja-shipping-info .kiriof-mb-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 14px;
    }

    #kiriminaja-shipping-info .kiriof-mb-header-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #ede9fe;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #7c3aed;
        font-size: 14px;
    }

    #kiriminaja-shipping-info .kiriof-mb-badges {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    #kiriminaja-shipping-info .kiriof-mb-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid #dcdcde;
        background: #f6f7f7;
        color: #3c434a;
        white-space: nowrap;
    }

    #kiriminaja-shipping-info .kiriof-mb-badge--cod {
        background: #f0f6fc;
        border-color: #b4d0e7;
        color: #2271b1;
    }

    #kiriminaja-shipping-info .kiriof-mb-badge--pickup {
        background: #fef9ec;
        border-color: #f0c33c;
        color: #9a6700;
    }

    #kiriminaja-shipping-info .kiriof-mb-badge--paid {
        background: #edfaed;
        border-color: #68de7c;
        color: #007017;
    }

    #kiriminaja-shipping-info .kiriof-mb-badge--unpaid {
        background: #f6f7f7;
        border-color: #dcdcde;
        color: #50575e;
    }

    #kiriminaja-shipping-info .kiriof-mb-expedition-card {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 12px;
        font-size: 14px;
        font-weight: 600;
        color: #1d2327;
    }

    #kiriminaja-shipping-info .kiriof-mb-alert {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        background: #fde8e8;
        border: 1px solid #f5b7b1;
        border-radius: 6px;
        padding: 10px 12px;
        margin-bottom: 12px;
        font-size: 12px;
        color: #8b1a1a;
    }

    #kiriminaja-shipping-info .kiriof-mb-alert-icon {
        font-size: 14px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    #kiriminaja-shipping-info .kiriof-mb-alert-title {
        font-weight: 700;
        margin-bottom: 2px;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary td {
        padding: 3px 0;
        vertical-align: middle;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary td:last-child {
        text-align: right;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary .kiriof-mb-row-child td:first-child {
        padding-left: 14px;
        color: #50575e;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary .kiriof-mb-row-sep td {
        border-top: 1px solid #dcdcde;
        padding-top: 8px;
        font-weight: 700;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary .kiriof-mb-val--discount {
        color: #d63638;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary .kiriof-mb-val--positive {
        color: #007017;
    }

    #kiriminaja-shipping-info .kiriof-mb-summary .kiriof-mb-val--negative {
        color: #d63638;
    }

    #kiriminaja-shipping-info .kiriof-mb-coupon-chip {
        display: inline-block;
        font-size: 10px;
        background: #f0f0f1;
        border-radius: 3px;
        padding: 1px 5px;
        margin-left: 4px;
        font-weight: 600;
        vertical-align: middle;
    }

    #kiriminaja-shipping-info .kiriof-mb-payout-row td {
        font-weight: 700;
    }

    #kiriminaja-shipping-info .kiriof-mb-payout-label-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    #kiriminaja-shipping-info .kiriof-mb-payout-minus {
        color: #d63638;
        font-size: 11px;
        font-weight: 700;
    }

    #kiriminaja-shipping-info .kiriof-mb-actions {
        margin-top: 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #kiriminaja-shipping-info .kiriof-mb-actions .button {
        text-align: center;
    }

    #kiriminaja-shipping-info .kiriof-btn--adjust-cod {
        border-color: #2271b1;
        color: #2271b1;
    }

    #kiriminaja-shipping-info .kiriof-btn--cancel-deficit {
        border-color: #d63638;
        color: #d63638;
    }
</style>

<?php /* ── Header ── */ ?>
<div class="kiriof-mb-header">
    <div class="kiriof-mb-header-left">
        <div class="kiriof-mb-header-icon">&#9632;</div>
        <?php esc_html_e('Shipment', 'kiriminaja-official'); ?>
    </div>
    <div class="kiriof-mb-badges">
        <?php if ($kiriof_is_cod) : ?>
            <span class="kiriof-mb-badge kiriof-mb-badge--cod">
                &#8962; <?php esc_html_e('COD', 'kiriminaja-official'); ?>
            </span>
        <?php endif; ?>
        <?php if ($kiriof_has_pickup) : ?>
            <span class="kiriof-mb-badge kiriof-mb-badge--pickup">
                &#8599; <?php esc_html_e('Pickup', 'kiriminaja-official'); ?>
            </span>
        <?php endif; ?>
        <?php if ($wc_needs_payment) : ?>
            <span class="kiriof-mb-badge kiriof-mb-badge--unpaid">
                &#9675; <?php esc_html_e('Unpaid', 'kiriminaja-official'); ?>
            </span>
        <?php else : ?>
            <span class="kiriof-mb-badge kiriof-mb-badge--paid">
                &#10003; <?php esc_html_e('Paid', 'kiriminaja-official'); ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<?php /* ── Expedition card ── */ ?>
<?php if (! empty($data['service']) && '-' !== $data['service']) : ?>
    <div class="kiriof-mb-expedition-card">
        <span><?php echo esc_html($data['service']); ?></span>
    </div>
<?php endif; ?>

<?php /* ── COD Deficit alert ── */ ?>
<?php if ($kiriof_is_deficit) : ?>
    <div class="kiriof-mb-alert">
        <span class="kiriof-mb-alert-icon">&#9888;</span>
        <div>
            <div class="kiriof-mb-alert-title"><?php esc_html_e('Value less than the provisions', 'kiriminaja-official'); ?></div>
            <?php if ($kiriof_cod_minimum > 0) : ?>
                <div><?php printf(
                            /* translators: %s = minimum COD amount */
                            esc_html__('Items with a minimum value of %s cannot proceed.', 'kiriminaja-official'),
                            '<strong>Rp' . esc_html(number_format($kiriof_cod_minimum, 0, ',', '.')) . '</strong>'
                        ); ?></div>
            <?php else : ?>
                <div><?php esc_html_e('COD value is below the minimum required to avoid a deficit.', 'kiriminaja-official'); ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php /* ── Summary table ── */ ?>
<table class="kiriof-mb-summary">
    <tbody>

        <?php if (! empty($data['order_id']) && '-' !== $data['order_id']) : ?>
            <tr>
                <td><?php esc_html_e('Order ID', 'kiriminaja-official'); ?></td>
                <td><strong><?php echo esc_html($data['order_id']); ?></strong></td>
            </tr>
        <?php endif; ?>

        <tr>
            <td><?php esc_html_e('Sub Total', 'kiriminaja-official'); ?></td>
            <td><?php echo wp_kses_post(wc_price($wc_subtotal)); ?></td>
        </tr>

        <tr>
            <td><?php esc_html_e('Total Shipping', 'kiriminaja-official'); ?></td>
            <td><?php echo wp_kses_post(wc_price($kiriof_total_shipping)); ?></td>
        </tr>

        <tr class="kiriof-mb-row-child">
            <td><?php esc_html_e('Shipping', 'kiriminaja-official'); ?></td>
            <td>
                <?php echo wp_kses_post(wc_price($kiriof_shipping_raw)); ?>
            </td>
        </tr>

        <?php if ($kiriof_insurance_raw > 0) : ?>
            <tr class="kiriof-mb-row-child">
                <td><?php esc_html_e('Insurance', 'kiriminaja-official'); ?></td>
                <td><?php echo wp_kses_post(wc_price($kiriof_insurance_raw)); ?></td>
            </tr>
        <?php endif; ?>

        <?php if ($kiriof_is_cod && $kiriof_cod_fee_raw > 0) : ?>
            <tr class="kiriof-mb-row-child">
                <td><?php esc_html_e('COD Fee', 'kiriminaja-official'); ?></td>
                <td><?php echo wp_kses_post(wc_price($kiriof_cod_fee_raw)); ?></td>
            </tr>
        <?php endif; ?>

        <?php if ($kiriof_effective_discount > 0) : ?>
            <tr>
                <td>
                    <?php if ($kiriof_first_coupon) : ?>
                        <?php echo esc_html($kiriof_first_coupon); ?>
                        <span style="color:#8c8f94;font-size:11px;"><?php esc_html_e('Item', 'kiriminaja-official'); ?></span>
                    <?php else : ?>
                        <?php esc_html_e('Discount', 'kiriminaja-official'); ?>
                    <?php endif; ?>
                </td>
                <td class="kiriof-mb-val--discount">-<?php echo wp_kses_post(wc_price($kiriof_effective_discount)); ?></td>
            </tr>
        <?php endif; ?>

        <?php if ($kiriof_wc_shipping_discount > 0) :
            $kiriof_ship_coupon = ! empty($wc_coupon_codes[1]) ? strtoupper($wc_coupon_codes[1]) : $kiriof_first_coupon;
        ?>
            <tr>
                <td>
                    <?php if ($kiriof_ship_coupon) : ?>
                        <?php echo esc_html($kiriof_ship_coupon); ?>
                        <span style="color:#8c8f94;font-size:11px;"><?php esc_html_e('Shipping', 'kiriminaja-official'); ?></span>
                    <?php else : ?>
                        <?php esc_html_e('Shipping Discount', 'kiriminaja-official'); ?>
                    <?php endif; ?>
                </td>
                <td class="kiriof-mb-val--discount">-<?php echo wp_kses_post(wc_price($kiriof_wc_shipping_discount)); ?></td>
            </tr>
            <tr class="kiriof-mb-row-sep">
                <td><?php esc_html_e('Discounted Shipping', 'kiriminaja-official'); ?></td>
                <td><?php echo wp_kses_post(wc_price($kiriof_discounted_shipping)); ?></td>
            </tr>
        <?php endif; ?>

        <?php if ($kiriof_is_cod) : ?>
            <tr class="<?php echo $kiriof_wc_shipping_discount > 0 ? '' : 'kiriof-mb-row-sep'; ?>">
                <td><?php esc_html_e('COD Paid By Buyer', 'kiriminaja-official'); ?></td>
                <td><?php echo wp_kses_post(wc_price($kiriof_cod_paid)); ?></td>
            </tr>
            <tr class="kiriof-mb-payout-row">
                <td>
                    <div class="kiriof-mb-payout-label-wrap">
                        <?php esc_html_e('Estimated COD Payout', 'kiriminaja-official'); ?>
                        <?php if ($kiriof_payout <= 0) : ?>
                            <span>&#9888;</span>
                            <span class="kiriof-mb-payout-minus"><?php esc_html_e('MINUS', 'kiriminaja-official'); ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="<?php echo $kiriof_payout <= 0 ? 'kiriof-mb-val--negative' : 'kiriof-mb-val--positive'; ?>">
                    <?php echo wp_kses_post(wc_price($kiriof_payout)); ?>
                </td>
            </tr>
        <?php else : ?>
            <tr class="kiriof-mb-row-sep">
                <td><?php esc_html_e('Total', 'kiriminaja-official'); ?></td>
                <td><?php echo wp_kses_post(wc_price($wc_total)); ?></td>
            </tr>
        <?php endif; ?>

    </tbody>
</table>

<?php /* ── Action buttons ── */ ?>
<div class="kiriof-mb-actions">
    <?php if ($kiriof_is_deficit) : ?>
        <button
            type="button"
            class="button kiriof-btn--adjust-cod"
            onclick="kjShowCodAdjustModal(this)"
            data-ka-order-id="<?php echo esc_attr($data['ka_order_id'] ?? ''); ?>"
            data-current-cod="<?php echo esc_attr($wc_total); ?>"
            data-cod-minimum="<?php echo esc_attr($data['cod_minimum'] ?? 0); ?>"
            data-cod-maximum="<?php echo esc_attr((float) KIRIOF_MAX_COD_AMOUNT); ?>"
            data-shipping-cost="<?php echo esc_attr($kiriof_shipping_raw); ?>"
            data-insurance-fee="<?php echo esc_attr($kiriof_insurance_raw); ?>"
            data-cod-fee="<?php echo esc_attr($kiriof_cod_fee_raw); ?>"
            data-item-price="<?php echo esc_attr($wc_subtotal); ?>"
            data-item-discount="<?php echo esc_attr($wc_discount_total); ?>"
            data-shipping-discount="<?php echo esc_attr($kiriof_wc_shipping_discount); ?>"
            data-item-coupon="<?php echo esc_attr($kiriof_first_coupon); ?>"
            data-shipping-coupon="<?php echo esc_attr(! empty($wc_coupon_codes[1]) ? strtoupper($wc_coupon_codes[1]) : $kiriof_first_coupon); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce(KIRIOF_NONCE)); ?>">
            <?php esc_html_e('Adjust Deficit', 'kiriminaja-official'); ?>
        </button>
        <button
            type="button"
            class="button kiriof-btn--cancel-deficit"
            onclick="kjShowCancelDeficitModal(this)"
            data-ka-order-id="<?php echo esc_attr($data['ka_order_id'] ?? ''); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce(KIRIOF_NONCE)); ?>">
            <?php esc_html_e('Cancel Deficit Order', 'kiriminaja-official'); ?>
        </button>
    <?php else : ?>
        <a href="<?php echo esc_url($detail_url); ?>" class="button button-primary">
            <?php esc_html_e('View in KiriminAja', 'kiriminaja-official'); ?>
        </a>
        <a href="<?php echo esc_url($tracking_url); ?>" class="button" target="_blank">
            <?php esc_html_e('Track Shipment', 'kiriminaja-official'); ?>
        </a>
    <?php endif; ?>
</div>

<?php require_once KIRIOF_DIR . 'templates/order/partials/cod-adjustment-modal.php'; ?>