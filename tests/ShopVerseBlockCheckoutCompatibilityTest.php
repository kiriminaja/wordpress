<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for React/block checkout themes such as ShopVerse.
 */
final class ShopVerseBlockCheckoutCompatibilityTest extends TestCase
{
    #[Test]
    public function shopverse_uses_woocommerce_checkout_blocks(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/tests/fixtures/shopverse-template-checkout.php');

        $this->assertStringContainsString(
            'wp:woocommerce/checkout',
            $content,
            'ShopVerse checkout template must be treated as WooCommerce block checkout'
        );
    }

    #[Test]
    public function block_checkout_field_registration_uses_supported_select_field_without_invalid_before_attribute(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_register_block_checkout_fields');
        $this->assertNotFalse($start, 'Block checkout registration method must exist');
        $methodBody = substr($content, $start, 2600);

        $this->assertStringContainsString(
            "'type'         => 'text'",
            $methodBody,
            'District must register as text for block checkout because WooCommerce Store API rejects dynamic select values not present in the original enum'
        );

        $this->assertStringNotContainsString(
            "'type'         => 'select'",
            $methodBody,
            'Dynamic postcode search cannot use block select registration without triggering Invalid kiriminaja-official/kiriof_destination_area provided'
        );

        $this->assertStringNotContainsString(
            "'options'      => \$options",
            $methodBody,
            'Block select options become a fixed Store API enum and reject AJAX-loaded district IDs'
        );

        $this->assertStringNotContainsString(
            "'before' => 'address_2'",
            $methodBody,
            'WooCommerce blocks reject the non-standard before attribute and emit doing_it_wrong notices'
        );
    }

    #[Test]
    public function frontend_block_checkout_script_uses_store_api_extension_cart_update_for_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'extensionCartUpdate',
            $content,
            'React/block checkout must refresh cart totals through Store API extensionCartUpdate, not only jQuery update_checkout'
        );

        $this->assertStringContainsString(
            "namespace: 'kiriminaja-official'",
            $content,
            'Store API cart update must be namespaced to this plugin'
        );
    }

    #[Test]
    public function block_checkout_triggers_store_api_session_persist_before_legacy_ajax_fee_request(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $start = strpos($content, 'function kiriofCodInsurance()');
        $this->assertNotFalse($start, 'Block checkout COD/insurance recalculation function must exist');
        $functionBody = substr($content, $start, 5200);

        $extensionPosition = strpos($functionBody, 'kiriofBlockExtensionCartUpdate(data);');
        $ajaxPosition = strpos($functionBody, 'jQuery.ajax({');

        $this->assertNotFalse($extensionPosition, 'Block checkout must persist shipping/destination/payment choices through Store API before order creation');
        $this->assertNotFalse($ajaxPosition, 'Legacy AJAX fee request should still run for classic checkout compatibility');
        $this->assertLessThan(
            $ajaxPosition,
            $extensionPosition,
            'Store API session persistence must run before legacy admin-ajax fee calculation because checkout may submit before the AJAX success callback fires'
        );
    }

    #[Test]
    public function block_checkout_district_postcode_lookup_uses_localized_ajax_url(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $start = strpos($content, 'function kiriofFetchDistricts(postcode)');
        $this->assertNotFalse($start, 'Block checkout district postcode lookup function must exist');
        $functionBody = substr($content, $start, 1900);

        $this->assertStringContainsString(
            'kiriofAjax.ajaxurl',
            $functionBody,
            'Block checkout runs on the frontend where the wp-admin ajaxurl global is not guaranteed; district lookup must use localized kiriofAjax.ajaxurl'
        );

        $this->assertStringNotContainsString(
            'url: ajaxurl',
            $functionBody,
            'Using the undefined frontend ajaxurl global makes the postcode watcher fail before kiriminaja_subdistrict_search is sent'
        );
    }

    #[Test]
    public function block_checkout_district_lookup_also_watches_postcode_dom_inputs(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'input.kiriofBlockPostcode',
            $content,
            'Block checkout must listen to postcode input/change events directly because Woo blocks may only trigger wc/store/v1/batch and not update wc/store/cart synchronously'
        );

        $this->assertStringContainsString(
            'kiriofGetCheckoutPostcodeFromDom',
            $content,
            'Block checkout needs a DOM postcode fallback when cart data store postcode is not populated yet'
        );
    }

    #[Test]
    public function block_checkout_district_select_keeps_react_text_field_as_hidden_source_of_truth(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'kiriof-block-district-source',
            $content,
            'Block checkout should not replace Woo/React additional-field input; hide it and keep it as the controlled source of truth'
        );

        $this->assertStringContainsString(
            'kiriof-block-district-select',
            $content,
            'Block checkout must render a separate District select from AJAX postcode results'
        );

        $this->assertStringNotContainsString(
            '$field.replaceWith(select)',
            $content,
            'Replacing the React-controlled text input lets Woo blocks re-render the free-text field and lose the dynamic select'
        );
    }

    #[Test]
    public function block_checkout_cod_insurance_reads_namespaced_district_field(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'function kiriofGetDestinationId',
            $content,
            'Shared destination reader must exist so block checkout can pass a selected District ID into Store API fee/rate refreshes'
        );

        $this->assertStringContainsString(
            'kiriminaja-official/kiriof_destination_area',
            $content,
            'Block checkout uses the Woo additional-field namespace as the field name, not the classic kiriof_destination_area name'
        );

        $this->assertStringContainsString(
            'kiriofGetDestinationId(different_address)',
            $content,
            'kiriofCodInsurance must read the selected namespaced block District field instead of only classic selectors'
        );
    }

    #[Test]
    public function block_checkout_district_field_lookup_handles_react_rendered_inputs(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'function kiriofGetBlockDistrictField',
            $content,
            'Block checkout needs a robust field finder because Woo/React may not expose the additional field by exact name at the moment AJAX returns'
        );

        $this->assertStringContainsString(
            'input[name*="kiriof_destination_area"]',
            $content,
            'District finder must handle React/Woo sanitized or nested field names that still contain kiriof_destination_area'
        );

        $this->assertStringContainsString(
            'input[id*="kiriof-destination-area"]',
            $content,
            'Woo Blocks renders additional address fields with slash-to-dash IDs such as billing-kiriminaja-official-kiriof-destination-area, so lookup cannot rely only on underscore names'
        );

        $this->assertStringContainsString(
            '.wc-block-components-text-input',
            $content,
            'District select should be inserted at the Woo Blocks field wrapper, not only after the input node'
        );

        $this->assertStringContainsString(
            'MutationObserver',
            $content,
            'React checkout can render District after the AJAX response; a DOM observer must re-apply the select when the field appears'
        );
    }

    #[Test]
    public function block_checkout_district_change_updates_checkout_additional_fields_store(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            "wp.data.dispatch('wc/store/checkout')",
            $content,
            'District selection must update the checkout store, because additional checkout fields are stored in wc/store/checkout rather than cart billing/shipping addresses'
        );

        $this->assertStringContainsString(
            'setAdditionalFields',
            $content,
            'Block checkout District value must be written through setAdditionalFields so Store API checkout receives kiriminaja-official/kiriof_destination_area'
        );

        $this->assertStringContainsString(
            'getAdditionalFields',
            $content,
            'District updater should merge with existing checkout additional fields instead of overwriting unrelated extension fields'
        );
    }

    #[Test]
    public function block_checkout_district_selection_persists_destination_session_before_shipping_rate_refetch(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $start = strpos($content, 'change.kiriofBlockDistrict');
        $this->assertNotFalse($start, 'Block District change handler must exist');
        $handlerBody = substr($content, $start, 2200);

        $this->assertStringContainsString(
            'kiriofPersistDestinationArea',
            $handlerBody,
            'Selecting a District in block checkout must persist destination_id to WC session before rates are recalculated'
        );

        $this->assertStringContainsString(
            'kiriofGetDestinationAreaAjaxData',
            $content,
            'The block and classic District handlers should share the same destination/session payload builder'
        );
    }

    #[Test]
    public function shipping_method_supports_block_checkout_destination_and_payment_session_fallbacks(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');

        $this->assertStringContainsString(
            'shipping_destination_id',
            $content,
            'Block checkout may set the destination as shipping_destination_id before calculate_shipping runs'
        );

        $this->assertStringContainsString(
            'kiriof_payment_method',
            $content,
            'Block checkout Store API callback stores the payment method as kiriof_payment_method; shipping option filtering must read that fallback'
        );

        $this->assertStringContainsString(
            'wc()->is_store_api_request()',
            $content,
            'Shipping option filtering cannot depend only on is_checkout() because block checkout rate requests run through Store API'
        );
    }

    #[Test]
    public function block_checkout_cod_fee_reads_and_watches_wc_payment_store(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            "wp.data.select('wc/store/payment')",
            $content,
            'Woo Blocks payment radios do not use classic name=payment_method inputs; COD fee code must read wc/store/payment'
        );

        $this->assertStringContainsString(
            'getActivePaymentMethod',
            $content,
            'Block checkout must use the active Woo payment method store value so selecting COD sends payment_method=cod'
        );

        $this->assertStringContainsString(
            'kiriofLastPaymentMethod',
            $content,
            'Block checkout must subscribe to payment method changes and recalculate COD fee when the buyer selects COD'
        );
    }

    #[Test]
    public function block_checkout_district_select_uses_dedicated_wrapper_without_overlapping_source_field(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $start = strpos($content, 'function kiriofRenderBlockDistrictSelect');
        $this->assertNotFalse($start, 'Block District select renderer must exist');
        $functionBody = substr($content, $start, 4200);

        $this->assertStringContainsString(
            'kiriof-block-district-field-wrapper',
            $functionBody,
            'Block District select should live in its own Woo Blocks field wrapper instead of being inserted next to the hidden React text input wrapper'
        );

        $this->assertStringContainsString(
            '$wrapper.after($fieldWrapper)',
            $functionBody,
            'The dedicated District wrapper should be inserted as a sibling after the hidden React source wrapper to avoid ShopVerse label/input overlap'
        );

        $this->assertStringContainsString(
            '$wrapper.addClass(\'kiriof-block-district-source-wrapper\')',
            $functionBody,
            'The original Woo Blocks text-input wrapper should be marked separately so CSS can fully collapse the hidden source field area'
        );
    }

    #[Test]
    public function block_checkout_district_select_matches_woocommerce_blocks_select_markup(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $start = strpos($content, 'function kiriofRenderBlockDistrictSelect');
        $this->assertNotFalse($start, 'Block District select renderer must exist');
        $functionBody = substr($content, $start, 3200);

        $this->assertStringContainsString(
            'wc-block-components-address-form__state wc-block-components-state-input',
            $functionBody,
            'Block District selector should use the same outer address-form wrapper as WooCommerce block Province selects'
        );

        $this->assertStringContainsString(
            'wc-blocks-components-select kiriof-block-district-select-wrapper',
            $functionBody,
            'Block District selector should include the same inner Woo Blocks select wrapper as Province'
        );

        $this->assertStringContainsString(
            'wc-blocks-components-select__container',
            $functionBody,
            'Block District selector should use the same container class as WooCommerce block Province selects'
        );

        $this->assertStringContainsString(
            'wc-blocks-components-select__label',
            $functionBody,
            'Block District selector should use the same floating label class as WooCommerce block Province selects'
        );

        $this->assertStringContainsString(
            'wc-blocks-components-select__select',
            $functionBody,
            'Block District selector should use the same select class as WooCommerce block Province selects'
        );

        $this->assertStringContainsString(
            'wc-blocks-components-select__expand',
            $functionBody,
            'Block District selector should include the WooCommerce blocks select expand icon'
        );

        $this->assertStringNotContainsString(
            'style="width:100%;padding:8px',
            $functionBody,
            'Block District selector should not use ad-hoc inline styling that differs from WooCommerce block selects'
        );
    }

    #[Test]
    public function checkout_fee_amounts_show_skeleton_while_recalculating(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $template = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');

        $this->assertStringContainsString(
            'kiriof-fee-skeleton',
            $controller,
            'COD Fee and Insurance placeholder rows need a skeleton element that can be shown during checkout recalculation'
        );

        $this->assertStringContainsString(
            'function kiriofSetFeeSkeletonLoading',
            $template,
            'Frontend script needs a shared loading-state helper for the COD Fee and Insurance amount cells'
        );

        $this->assertStringContainsString(
            'kiriofSetFeeSkeletonLoading(true)',
            $template,
            'Fee skeleton should be enabled before the checkout fee AJAX request starts'
        );

        $this->assertStringContainsString(
            'kiriofSetFeeSkeletonLoading(false)',
            $template,
            'Fee skeleton should be disabled after AJAX success/error so final fee amounts are visible'
        );
    }

    #[Test]
    public function classic_checkout_keeps_live_fee_placeholder_rows(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriof_cart_item_cod_fee',
            $content,
            'Classic checkout needs the COD Fee placeholder row so AJAX can show it after courier/payment changes'
        );

        $this->assertStringContainsString(
            'kiriof_cart_item_insurane',
            $content,
            'Classic checkout needs the Insurance placeholder row so AJAX can show it after courier/insurance changes'
        );
    }

    #[Test]
    public function classic_checkout_does_not_register_district_twice_through_default_address_fields(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringNotContainsString(
            "add_filter( 'woocommerce_default_address_fields'",
            $content,
            'Classic checkout already injects District via woocommerce_checkout_fields; adding it again through default address fields renders a duplicate prefixed billing_kiriof_destination_area field'
        );

        $this->assertStringContainsString(
            "add_filter('woocommerce_checkout_fields'",
            $content,
            'Classic checkout must keep the original checkout_fields injection path for District and Insurance'
        );
    }

    #[Test]
    public function classic_checkout_uses_placeholder_rows_without_native_wc_fee_duplicates(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriof_cart_item_cod_fee',
            $content,
            'Classic checkout still needs AJAX-updated COD Fee placeholder rows'
        );

        $this->assertStringContainsString(
            'kiriof_cart_item_insurane',
            $content,
            'Classic checkout still needs AJAX-updated Insurance placeholder rows'
        );

        $this->assertStringContainsString(
            'private function kiriof_is_block_checkout_request()',
            $content,
            'Native WooCommerce fees must be limited to block checkout requests so classic checkout does not render COD Fee/Insurance twice'
        );

        $this->assertStringContainsString(
            'kiriof_should_use_native_checkout_fees',
            $content,
            'kiriof_add_checkout_fees must bail on classic checkout and only add native fees for block checkout/Store API requests'
        );
    }

    #[Test]
    public function block_checkout_native_fee_detection_includes_store_api_requests(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'private function kiriof_is_store_api_request()',
            $content,
            'Block checkout cart totals are calculated inside /wc/store/ REST requests where is_checkout() is false'
        );

        $this->assertStringContainsString(
            'strpos( $route, \'/wc/store/\' )',
            $content,
            'Native fee path must detect WooCommerce Store API recalculation requests'
        );

        $start = strpos($content, 'function kiriof_shipping_method_update()');
        $this->assertNotFalse($start, 'Shipping method update hook must exist');
        $methodBody = substr($content, $start, 800);

        $this->assertStringNotContainsString(
            "WC()->session->set( 'chosen_shipping_methods', null );",
            $methodBody,
            'Store API recalculations without classic POST shipping_method must not clear the selected KiriminAja method saved by extensionCartUpdate'
        );
    }

    #[Test]
    public function block_checkout_fee_fallback_strips_shipping_method_prefix_before_calculation(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriof_extract_expedition_from_method',
            $content,
            'Fallback fee calculation must pass only courier_service to CheckoutCalculationService, not the full shipping method ID'
        );

        $this->assertStringContainsString(
            "strlen( 'kiriminaja-official:' )",
            $content,
            'Block checkout may send colon-form rate IDs and those prefixes must be stripped too'
        );
    }

    #[Test]
    public function cod_fee_calculation_matches_main_branch_amount_field(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CheckoutCalculationService.php');
        $start = strpos($content, 'private function getCalculateCODFee');
        $this->assertNotFalse($start, 'COD fee calculation method must exist');
        $methodBody = substr($content, $start, 900);

        $this->assertStringContainsString(
            'cod_fee_amount',
            $methodBody,
            'COD Fee must use API-provided cod_fee_amount like main branch'
        );

        $this->assertStringNotContainsString(
            'codRate',
            $methodBody,
            'COD Fee must not drift by recalculating locally from a rate'
        );
    }

    #[Test]
    public function php_registers_store_api_update_callback_for_block_checkout_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'woocommerce_store_api_register_update_callback',
            $content,
            'Block checkout fee refresh needs a Store API cart/extensions callback'
        );

        $this->assertStringContainsString(
            "'namespace' => 'kiriminaja-official'",
            $content,
            'Store API update callback must use the same namespace as frontend extensionCartUpdate'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_shipping_methods'",
            $content,
            'Store API callback must persist selected shipping method before WC recalculates fees'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_expedition'",
            $content,
            'Store API callback must persist normalized expedition for transaction insertion even if checkout submits before the create_order hook sees POST data'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_destination_area'",
            $content,
            'Store API callback must persist transaction destination context, not only shipping-rate destination context'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'billing_insurance'",
            $content,
            'Store API callback must persist the insurance flag used by transaction creation'
        );
    }

    #[Test]
    public function block_checkout_order_creation_reads_store_api_checkout_context(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $service = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');

        $this->assertStringContainsString(
            "woocommerce_store_api_checkout_order_processed",
            $content,
            'Block checkout must create KiriminAja transaction rows from the Store API order-processed hook, not only the classic checkout hook'
        );

        $this->assertStringContainsString(
            'afterStoreApiCheckoutOrderProcessed',
            $content,
            'Store API checkout hook should delegate to the existing transaction creation flow'
        );

        $this->assertStringContainsString(
            'getTransactionByWCOrderId',
            $content,
            'Store API checkout hook must guard against duplicate transaction rows if Woo also fires the classic hook'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'chosen_shipping_methods'",
            $content,
            'Block checkout order creation must fall back to the Store API chosen shipping method because $_POST[shipping_method] is not present'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'kiriof_payment_method'",
            $content,
            'Block checkout order creation must fall back to the payment method persisted by the Store API extension callback'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'kiriof_insurance'",
            $content,
            'Block checkout order creation must fall back to the insurance flag persisted by the Store API extension callback'
        );

        $this->assertStringContainsString(
            'kiriof_extract_expedition_from_method',
            $content,
            'Block checkout order creation must strip kiriminaja-official prefixes from Store API rate IDs before transaction creation'
        );

        $this->assertStringContainsString(
            'getOrderCartContentsFallback',
            $service,
            'CreateTransactionService must rebuild cart contents from the persisted order when block checkout clears WC()->cart before the transaction row is inserted'
        );

        $this->assertStringContainsString(
            'wc_get_order($this->payload[\'order_id\'])',
            $service,
            'Order fallback must load the completed Woo order so COD transactions can still persist after Store API checkout'
        );
    }

    #[Test]
    public function block_checkout_order_fees_are_not_added_or_rendered_twice_after_checkout(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $service = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');

        $this->assertStringContainsString(
            'kiriof_order_has_fee_item',
            $controller,
            'Order received page should detect native Woo fee items before rendering custom COD/Insurance fallback rows'
        );

        $this->assertStringContainsString(
            '! $this->kiriof_order_has_fee_item',
            $controller,
            'Custom order-details COD/Insurance rows should be skipped when Woo already rendered native fee rows'
        );

        $this->assertStringContainsString(
            'orderHasFeeItem',
            $service,
            'CreateTransactionService should detect existing native Store API fee items before adding legacy fee items'
        );

        $this->assertStringContainsString(
            '! $this->orderHasFeeItem',
            $service,
            'CreateTransactionService must not add duplicate COD Fee/Insurance order items when block checkout already created them'
        );
    }

    #[Test]
    public function order_received_fee_fallback_never_renders_zero_or_null_transaction_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_order_details');
        $this->assertNotFalse($start, 'Order details renderer must exist');
        $methodBody = substr($content, $start, 2200);

        $this->assertStringContainsString(
            '$transaction_cod_fee > 0',
            $methodBody,
            'COD Fee fallback row should only render when a KiriminAja transaction exists with a positive fee, preventing COD Fee Rp0 rows'
        );

        $this->assertStringContainsString(
            '$transaction_insurance_cost > 0',
            $methodBody,
            'Insurance fallback row should only render when the transaction has a positive insurance cost'
        );

        $this->assertStringNotContainsString(
            '? $transactionKiriminaja->cod_fee : 0',
            $methodBody,
            'Missing transactions should not be displayed as a zero COD fee fallback row'
        );
    }

    #[Test]
    public function transaction_creation_honors_block_checkout_insurance_payload_when_post_data_is_empty(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/CreateTransactionService.php');

        $this->assertStringContainsString(
            '! empty( $this->payload[\'is_insurance\'] )',
            $content,
            'CreateTransactionService must include block checkout insurance fallback from the controller payload, not only classic checkout_post_data'
        );

        $this->assertStringContainsString(
            '$this->isInsuranceRequested() ? 1 : 0',
            $content,
            'CheckoutCalculationService must calculate insurance cost with block checkout/global insurance enabled before transaction persistence'
        );

        $this->assertStringContainsString(
            '$this->isInsuranceRequested( $forceInsurance )',
            $content,
            'Transaction payload and order fee item creation must use the same insurance decision helper so forced/global/block insurance stay consistent'
        );

        $this->assertStringContainsString(
            '$global_insurance',
            $content,
            'Insurance decision helper must honor globally forced insurance'
        );
    }
}
