<?php
namespace KiriminAjaOfficial\Controllers;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles COD deficit adjustment and cancellation AJAX endpoints.
 *
 * AJAX actions:
 *   kiriof_cod_adjust        — Adjust COD value for a deficit order.
 *   kiriof_cancel_deficit    — Cancel a deficit order.
 */
class CodAdjustmentController {

    public function register(): void {
        add_action( 'wp_ajax_kiriof_cod_adjust', [ $this, 'handleAdjust' ] );
        add_action( 'wp_ajax_kiriof_cancel_deficit', [ $this, 'handleCancelDeficit' ] );
    }

    // -------------------------------------------------------------------------
    // COD Adjustment
    // -------------------------------------------------------------------------

    public function handleAdjust(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ] );
            wp_die();
        }
        if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
            wp_send_json_error( [ 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ] );
            wp_die();
        }

        $data        = isset( $_POST['data'] ) ? kiriof_sanitize_recursive( wp_unslash( $_POST['data'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $kaOrderId   = sanitize_text_field( $data['order_package_id'] ?? '' );
        $newTotalCod = (float) ( $data['new_total_cod'] ?? 0 );

        if ( empty( $kaOrderId ) || $newTotalCod <= 0 ) {
            wp_send_json_error( [ 'status' => 400, 'message' => __( 'Invalid parameters', 'kiriminaja-official' ) ] );
            wp_die();
        }

        $repo        = new \KiriminAjaOfficial\Repositories\TransactionRepository();
        $transaction = $repo->getTransactionByOrderId( $kaOrderId );

        if ( ! $transaction ) {
            wp_send_json_error( [ 'status' => 404, 'message' => __( 'Transaction not found', 'kiriminaja-official' ) ] );
            wp_die();
        }

        if ( empty( $transaction->is_deficit ) ) {
            wp_send_json_error( [ 'status' => 400, 'message' => __( 'Order is not flagged as deficit', 'kiriminaja-official' ) ] );
            wp_die();
        }

        $shippingCost = (float) ( $transaction->shipping_cost ?? 0 );
        $insuranceFee = (float) ( $transaction->insurance_cost ?? 0 );
        $originalCodFee = (float) ( $transaction->cod_fee ?? 0 );
        $adminFee     = 0.0;
        $dbCodMinimum = (float) ( $transaction->cod_minimum ?? 0 );

        $localMinimum = $shippingCost + $insuranceFee + $originalCodFee + $adminFee;
        $minimumCod   = max( $localMinimum, $dbCodMinimum );
        $maxCodAmount = defined( 'KIRIOF_MAX_COD_AMOUNT' ) ? (float) KIRIOF_MAX_COD_AMOUNT : 3000000.0;

        if ( $newTotalCod < $minimumCod ) {
            wp_send_json_error( [
                'status'  => 400,
                // Translators: %s is the minimum COD amount formatted as a price.
                'message' => sprintf( __( 'Must not be less than Rp%s to avoid deficit', 'kiriminaja-official' ), number_format( $minimumCod, 0, ',', '.' ) ),
            ] );
            wp_die();
        }

        if ( $newTotalCod > $maxCodAmount ) {
            wp_send_json_error( [
                'status'  => 400,
                // Translators: %s is the max COD amount formatted as a price.
                'message' => sprintf( __( 'Must not exceed Rp%s', 'kiriminaja-official' ), number_format( $maxCodAmount, 0, ',', '.' ) ),
            ] );
            wp_die();
        }

        // Recalculate via COD fee API (with fallback).
        $newCodFee    = $originalCodFee;
        $newCodMinimum = $minimumCod;

        $memberId = (int) ( \KiriminAjaOfficial\Repositories\SettingRepository::getValue( 'member_id' ) ?? 0 );
        if ( $memberId > 0 && ! empty( $transaction->service ) ) {
            $serviceParts = explode( '_', $transaction->service . '_' . ( $transaction->service_name ?? '' ), 2 );
            $apiResult = ( new \KiriminAjaOfficial\Repositories\CodFeeApiRepository() )->calculateBulkCod( [
                'member_id'                     => $memberId,
                'item_price'                    => (int) ( $transaction->transaction_value ?? 0 ),
                'custom_cod'                    => (int) $newTotalCod,
                'exclude_cod_amount_validation' => false,
                'couriers'                      => [
                    [
                        'courier_code'         => $serviceParts[0],
                        'courier_service_code' => $serviceParts[1] ?? '',
                        'shipping_cost'        => (int) $shippingCost,
                        'discount_amount'      => (int) ( $transaction->discount_amount ?? 0 ),
                        'insurance_amount'     => (int) $insuranceFee,
                    ],
                ],
            ] );

            if ( null !== $apiResult ) {
                $newCodFee     = (float) ( $apiResult[0]->total_fee ?? $originalCodFee );
                $localMinimum  = $shippingCost + $insuranceFee + $newCodFee + $adminFee;
                $newCodMinimum = max( $localMinimum, (float) ( $apiResult[0]->minimum_custom_cod ?? 0 ), $dbCodMinimum );
            } else {
                error_log( '[KiriminAja] CodAdjustmentController::handleAdjust — API unavailable, using original codFee as fallback.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }

        // Re-validate against the (potentially updated) minimum after API recalculation.
        // If the API returned a higher cod_fee the effective minimum may have risen.
        if ( $newTotalCod < $newCodMinimum ) {
            wp_send_json_error( [
                'status'  => 400,
                // Translators: %s is the recalculated minimum COD amount formatted as a price.
                'message' => sprintf( __( 'Must not be less than Rp%s to avoid deficit', 'kiriminaja-official' ), number_format( $newCodMinimum, 0, ',', '.' ) ),
            ] );
            wp_die();
        }

        $newPayout      = $newTotalCod - $shippingCost - $insuranceFee - $newCodFee - $adminFee;
        // After passing the minimum re-validation above, payout is guaranteed >= 0.
        $isStillDeficit = 0;

        // Update database.
        $updated = $repo->updateTransactionCodValues( $kaOrderId, [
            'transaction_value' => $newTotalCod,
            'cod_fee'           => $newCodFee,
            'cod_minimum'       => $newCodMinimum,
            'is_deficit'        => $isStillDeficit,
        ] );

        if ( ! $updated ) {
            wp_send_json_error( [ 'status' => 500, 'message' => __( 'Failed to update transaction', 'kiriminaja-official' ) ] );
            wp_die();
        }

        // Update WooCommerce order total to match the new COD value.
        $wcOrderId = (int) ( $transaction->wp_wc_order_stat_order_id ?? 0 );
        if ( $wcOrderId > 0 ) {
            $wcOrder = wc_get_order( $wcOrderId );
            if ( $wcOrder ) {
                // Remove any pre-existing COD Adjustment fee items (idempotent re-adjustment).
                $adjFeeLabel = __( 'COD Adjustment', 'kiriminaja-official' );
                foreach ( $wcOrder->get_fees() as $feeId => $existingFee ) {
                    if ( $adjFeeLabel === $existingFee->get_name() ) {
                        $wcOrder->remove_item( $feeId );
                    }
                }
                $wcOrder->calculate_totals( false );

                // Compute delta from the natural WC order total (without any prior adjustment).
                $naturalTotal    = (float) $wcOrder->get_total();
                $adjustmentDelta = $newTotalCod - $naturalTotal;

                if ( abs( $adjustmentDelta ) > 0.01 ) {
                    $feeItem = new \WC_Order_Item_Fee();
                    $feeItem->set_name( $adjFeeLabel );
                    $feeItem->set_amount( $adjustmentDelta );
                    $feeItem->set_total( $adjustmentDelta );
                    $wcOrder->add_item( $feeItem );
                }

                $wcOrder->update_meta_data( 'cod-deficit', $isStillDeficit ? '1' : '0' );
                // Translators: %s is the new COD total formatted as a price.
                $wcOrder->add_order_note( sprintf( __( 'COD value adjusted to Rp%s by merchant.', 'kiriminaja-official' ), number_format( $newTotalCod, 0, ',', '.' ) ) );
                $wcOrder->calculate_totals();
                $wcOrder->save();
            }
        }

        wp_send_json_success( [
            'newCod'    => $newTotalCod,
            'pencairan' => $newPayout,
            'isDeficit' => (bool) $isStillDeficit,
        ] );
        wp_die();
    }

    // -------------------------------------------------------------------------
    // Cancel Deficit
    // -------------------------------------------------------------------------

    public function handleCancelDeficit(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'status' => 403, 'message' => __( 'Insufficient permissions', 'kiriminaja-official' ) ] );
            wp_die();
        }
        if ( ! isset( $_POST['data']['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['data']['nonce'] ) ), KIRIOF_NONCE ) ) {
            wp_send_json_error( [ 'status' => 403, 'message' => __( 'Security check failed', 'kiriminaja-official' ) ] );
            wp_die();
        }

        $data      = isset( $_POST['data'] ) ? kiriof_sanitize_recursive( wp_unslash( $_POST['data'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $kaOrderId = sanitize_text_field( $data['order_package_id'] ?? '' );

        if ( empty( $kaOrderId ) ) {
            wp_send_json_error( [ 'status' => 400, 'message' => __( 'Invalid parameters', 'kiriminaja-official' ) ] );
            wp_die();
        }

        $repo        = new \KiriminAjaOfficial\Repositories\TransactionRepository();
        $transaction = $repo->getTransactionByOrderId( $kaOrderId );

        if ( ! $transaction ) {
            wp_send_json_error( [ 'status' => 404, 'message' => __( 'Transaction not found', 'kiriminaja-official' ) ] );
            wp_die();
        }

        if ( empty( $transaction->is_deficit ) ) {
            wp_send_json_error( [ 'status' => 400, 'message' => __( 'Order is not flagged as deficit', 'kiriminaja-official' ) ] );
            wp_die();
        }

        if ( empty( $transaction->awb ) ) {
            wp_send_json_error( [ 'status' => 400, 'message' => __( 'AWB not found for this transaction', 'kiriminaja-official' ) ] );
            wp_die();
        }

        // Cancel via KiriminAja API.
        $apiResponse = ( new \KiriminAjaOfficial\Repositories\KiriminajaApiRepository() )->cancelShipment(
            $transaction->awb,
            __( 'Deficit COD order cancelled by merchant', 'kiriminaja-official' )
        );

        if ( empty( $apiResponse['status'] ) ) {
            $errorMsg = is_string( $apiResponse['data'] ) ? $apiResponse['data'] : __( 'Failed to cancel shipment', 'kiriminaja-official' );
            wp_send_json_error( [ 'status' => 400, 'message' => $errorMsg ] );
            wp_die();
        }

        // Update DB.
        $repo->updateTransactionByCallback( [
            'changes'   => [ 'status' => 'cancel', 'is_deficit' => 0 ],
            'condition' => [ 'order_id' => $kaOrderId ],
        ] );

        // Update WC order.
        $wcOrderId = (int) ( $transaction->wp_wc_order_stat_order_id ?? 0 );
        if ( $wcOrderId > 0 ) {
            $wcOrder = wc_get_order( $wcOrderId );
            if ( $wcOrder ) {
                $wcOrder->update_status( 'cancelled' );
                $wcOrder->update_meta_data( 'cod-deficit', '0' );
                $wcOrder->add_order_note( __( 'Deficit COD order cancelled by merchant via KiriminAja.', 'kiriminaja-official' ) );
                $wcOrder->save();
            }
        }

        wp_send_json_success( [ 'message' => __( 'Order cancelled successfully', 'kiriminaja-official' ) ] );
        wp_die();
    }
}
