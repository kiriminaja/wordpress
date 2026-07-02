<?php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (! defined('ABSPATH')) {
    define('ABSPATH', PLUGIN_DIR . '/tests/wordpress/');
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}
if (! function_exists('WC')) {
    function WC()
    {
        return $GLOBALS['kiriof_test_wc'] ?? null;
    }
}

/**
 * Regression coverage for React/block checkout themes such as ShopVerse.
 */
final class ShopVerseBlockCheckoutCompatibilityTest extends TestCase
{
    private static function billingAddressTemplateContent(): string
    {
        return file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php')
            . file_get_contents(PLUGIN_DIR . '/templates/front/partials/form-billing-address-fields.php')
            . file_get_contents(PLUGIN_DIR . '/templates/front/partials/form-billing-address-config.php')
            . file_get_contents(PLUGIN_DIR . '/assets/wp/js/form-billing-address.js');
    }

    private static function billingAddressScriptContent(): string
    {
        return file_get_contents(PLUGIN_DIR . '/assets/wp/js/form-billing-address.js');
    }

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
    public function billing_address_script_is_registered_and_template_enqueues_config_before_asset(): void
    {
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $template = file_get_contents(PLUGIN_DIR . '/templates/front/form-billing-address.php');
        $script = self::billingAddressScriptContent();

        $this->assertStringContainsString("wp_register_script(\n            'kiriof-form-billing-address'", $enqueue);
        $this->assertStringContainsString("assets/wp/js/form-billing-address.js", $enqueue);
        $this->assertStringContainsString("array( 'kiriof-script' )", $enqueue);
        $this->assertStringContainsString("wp_enqueue_script( 'kiriof-form-billing-address' );", $template);
        $this->assertStringContainsString("wp_add_inline_script(\n            'kiriof-form-billing-address'", $template);
        $this->assertStringContainsString("window.kiriofBillingAddressConfig = ", $template);
        $this->assertStringContainsString("'before'", $template);
        $this->assertStringNotContainsString('<?php', $script);
    }

    #[Test]
    public function block_checkout_script_must_run_on_cart_page_when_shopverse_redirects_empty_checkout_to_cart(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'jQuery(document).ready(function($)');
        $this->assertNotFalse($start, 'Inline checkout/cart script must exist');
        $readyBody = substr($content, $start, 19000);

        $this->assertStringContainsString(
            'kiriofInitBlockCheckoutCompatibility();',
            $readyBody,
            'ShopVerse uses Woo Blocks on cart/checkout; block compatibility wiring must be called outside the runtime isCheckout branch so cart-rendered block flows get District select and COD fee updates too'
        );

        $callPosition = strpos($readyBody, 'kiriofInitBlockCheckoutCompatibility();');
        $checkoutBranchPosition = strpos($readyBody, 'if (kiriofBillingAddressConfig.isCheckout) {');
        $this->assertNotFalse($callPosition, 'Block compatibility initializer must be called');
        $this->assertNotFalse($checkoutBranchPosition, 'Classic checkout branch still exists');
        $this->assertLessThan(
            $checkoutBranchPosition,
            $callPosition,
            'Initializer call must happen before/outside the runtime isCheckout branch; otherwise /cart/ has only fee helper functions and never installs postcode/payment/shipping watchers'
        );
    }

    #[Test]
    public function classic_checkout_refresh_flags_must_be_available_to_updated_checkout_handlers(): void
    {
        $content = self::billingAddressTemplateContent();
        $readyStart = strpos($content, 'jQuery(document).ready(function($)');
        $handlerStart = strpos($content, "jQuery(document.body).on('updated_checkout', function()");
        $this->assertNotFalse($readyStart, 'Inline checkout/cart script must initialize document ready handler');
        $this->assertNotFalse($handlerStart, 'Classic updated_checkout handler must exist');

        $upstreamScriptScope = substr($content, 0, $readyStart);
        $readyBody = substr($content, $readyStart, $handlerStart - $readyStart);

        $this->assertStringContainsString(
            'var kiriofTriggeredInitialShippingUpdate = false;',
            $upstreamScriptScope,
            'updated_checkout handler reads kiriofTriggeredInitialShippingUpdate outside document.ready, so it must be declared in the outer inline-script scope'
        );

        $this->assertStringContainsString(
            'var kiriofUpdatingCheckoutLock = false;',
            $upstreamScriptScope,
            'kiriofCodInsurance reads/writes kiriofUpdatingCheckoutLock outside document.ready, so it must be declared in the outer inline-script scope'
        );

        $this->assertStringNotContainsString(
            'var kiriofTriggeredInitialShippingUpdate = false;',
            $readyBody,
            'Declaring refresh flags inside document.ready causes ReferenceError when WooCommerce fires updated_checkout'
        );
    }

    #[Test]
    public function classic_district_selector_uses_woocommerce_selectwoo_fallback(): void
    {
        $script = self::billingAddressScriptContent();
        $start = strpos($script, 'function getSearchAreaKelurahan()');
        $this->assertNotFalse($start, 'Classic District select initializer must exist');
        $body = substr($script, $start, 2600);

        $this->assertStringContainsString(
            'let select2 = jQuery.fn.selectWoo || jQuery.fn.select2;',
            $body,
            'Classic checkout District must use WooCommerce selectWoo when the select2 alias is not available'
        );

        $this->assertStringContainsString(
            "typeof kiriofAjax !== 'undefined' && kiriofAjax.ajaxurl",
            $body,
            'Classic District AJAX must fall back to the base localized ajax URL when the billing config object is incomplete'
        );

        $this->assertStringContainsString(
            "typeof kiriofAjax !== 'undefined' && kiriofAjax.nonce",
            $body,
            'Classic District AJAX must fall back to the base localized nonce when the billing config object is incomplete'
        );

        $this->assertStringContainsString(
            'if (!subDistrictSelectElem.length || !select2 || !ajaxurl || !nonce)',
            $body,
            'District initialization should no-op safely when the field, Select2 library, AJAX URL, or nonce is unavailable'
        );

        $this->assertStringContainsString(
            "if (\$field.data('select2') || \$field.data('selectWoo'))",
            $body,
            'District select must destroy an existing enhanced instance before checkout/cart fragments reinitialize it'
        );

        $this->assertStringContainsString(
            "select2.call(\$field, {",
            $body,
            'District select must initialize through the chosen selectWoo/select2 implementation'
        );

        $this->assertStringContainsString(
            'term:term',
            $body,
            'District search should send the Select2 term at the top level as well as inside data[]'
        );

        $this->assertStringContainsString(
            'response.success !== false',
            $body,
            'District search should treat WP AJAX error payloads as empty results instead of throwing inside processResults'
        );

        $this->assertStringNotContainsString(
            'subDistrictSelectElem.select2({',
            $body,
            'Calling the select2 alias directly leaves classic themes with only WooCommerce selectWoo as a native select'
        );
    }

    #[Test]
    public function subdistrict_ajax_accepts_select2_top_level_search_terms(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php');
        $start = strpos($controller, 'public function kiriminajaSubdistrictSearch()');
        $this->assertNotFalse($start, 'Subdistrict AJAX handler must exist');
        $body = substr($controller, $start, 1800);

        $this->assertStringContainsString(
            "isset( \$_POST['term'] )",
            $body,
            'Subdistrict AJAX must accept the top-level term sent by Select2/SelectWoo'
        );

        $this->assertStringContainsString(
            "isset( \$_POST['search'] )",
            $body,
            'Subdistrict AJAX must accept the top-level search fallback sent by some Select2 adapters'
        );

        $this->assertStringContainsString(
            "wp_verify_nonce",
            $body,
            'Subdistrict AJAX search must keep nonce verification before calling the API'
        );
    }

    #[Test]
    public function block_checkout_no_district_state_must_not_make_place_order_a_dead_button(): void
    {
        $script = self::billingAddressTemplateContent();
        $styles = file_get_contents(PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css');

        $this->assertStringContainsString(
            "data-kiriof-disabled",
            $script,
            'Block checkout should soft-disable the place-order button so clicks can show the district warning instead of becoming inert'
        );

        $this->assertStringContainsString(
            'click.kiriofBlockPlaceOrder',
            $script,
            'Block checkout should intercept clicks on the soft-disabled place-order button and direct the buyer back to the district field'
        );

        $this->assertStringContainsString(
            'kiriofCommitSelectedBlockDistrict',
            $script,
            'Place-order handling should recommit the selected District right before submit so Woo Blocks cannot proceed with a remounted empty hidden field'
        );

        $this->assertStringContainsString(
            "document.addEventListener('click'",
            $script,
            'Block checkout should sync the selected District in the capture phase of the place-order click before Woo Blocks processes checkout'
        );

        $this->assertStringNotContainsString(
            'pointer-events: none !important;',
            $styles,
            'CSS must not suppress pointer events on the place-order button because that makes "Lakukan Pemesanan" appear broken'
        );
    }

    #[Test]
    public function block_checkout_valid_district_during_restore_must_reenable_place_order(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'if (kiriofDistrictResultsLoading || kiriofPendingDistrictRestore)');
        $this->assertNotFalse($start, 'District loading/restore branch must exist');
        $branchBody = substr($content, $start, 1600);

        $validPosition = strpos($branchBody, 'if (hasValidDistrict)');
        $enablePosition = strpos($branchBody, 'kiriofSetPlaceOrderDisabled(false);');
        $disablePosition = strpos($branchBody, 'kiriofSetPlaceOrderDisabled(true);');

        $this->assertNotFalse(
            $validPosition,
            'Loading/restore state must explicitly handle already-valid districts'
        );
        $this->assertNotFalse(
            $enablePosition,
            'A valid District during async restore must clear the soft-disabled Place Order state'
        );
        $this->assertNotFalse(
            $disablePosition,
            'Missing District during async restore should still soft-disable Place Order'
        );
        $this->assertLessThan(
            $disablePosition,
            $enablePosition,
            'The valid-District path should re-enable Place Order before the invalid-District branch can disable it'
        );
    }

    #[Test]
    public function checkout_pricing_must_use_variation_dimensions_when_cart_item_is_variable(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/UtilServices/GetWCCartAttributeService.php');

        $this->assertStringContainsString(
            "intval(\$cart['variation_id'] ?? 0)",
            $content,
            'Pricing payload must prefer variation_id so variable products use variant-level weight and dimensions'
        );

        $this->assertStringContainsString(
            'return $variation_id;',
            $content,
            'Cart attribute lookup must return the variation ID when a cart item has one'
        );

        $this->assertStringContainsString(
            "intval(\$cart['product_id'] ?? 0)",
            $content,
            'Cart attribute lookup must still fall back to parent product_id for simple products'
        );

        $this->assertStringContainsString(
            "\$cartProducts[\$product_id]['cart_quantity'] += intval",
            $content,
            'Multiple cart rows for product variants must not overwrite each other when quantities are collected'
        );
    }

    #[Test]
    public function checkout_pricing_must_stack_multiple_quantities_using_smallest_volumetric_box(): void
    {
        $serviceContent = file_get_contents(PLUGIN_DIR . '/inc/Services/UtilServices/GetWCCartAttributeService.php');
        $volumetricContent = file_get_contents(PLUGIN_DIR . '/inc/Utils/Volumetric.php');

        $this->assertStringContainsString(
            'use KiriminAjaOfficial\\Utils\\Volumetric;',
            $serviceContent,
            'Cart attribute service must use the shared volumetric utility'
        );

        $this->assertStringContainsString(
            'Volumetric::calculateSmallestBox',
            $serviceContent,
            'Cart dimensions must be delegated to the shared volumetric utility'
        );

        $this->assertStringNotContainsString(
            'function calculateSmallestVolumetricBox',
            $serviceContent,
            'Volumetric stacking logic must live in Utils, not inside checkout services'
        );

        $this->assertStringContainsString(
            'class Volumetric',
            $volumetricContent,
            'Volumetric utility class must exist'
        );

        $this->assertStringContainsString(
            'function calculateSmallestBox',
            $volumetricContent,
            'Volumetric utility must expose the smallest box calculator'
        );

        $this->assertStringContainsString(
            "'qty' => \$quantity",
            $serviceContent,
            'Cart attribute collection must preserve cart quantity for volumetric stacking'
        );
    }

    #[Test]
    public function volumetric_box_uses_smallest_packable_axis_aligned_stack(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 10, 'height' => 2, 'qty' => 1),
            array('length' => 10, 'width' => 100, 'height' => 2, 'qty' => 2),
            array('length' => 20, 'width' => 20, 'height' => 20, 'qty' => 1),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(26.0, $box['length']);
        $this->assertSame(20.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
        $this->assertSame(52000.0, $box['length'] * $box['width'] * $box['height']);
    }

    #[Test]
    public function volumetric_box_prefers_rotation_that_reduces_package_volume(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 50, 'height' => 10, 'qty' => 1),
            array('length' => 10, 'width' => 50, 'height' => 100, 'qty' => 1),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(100.0, $box['length']);
        $this->assertSame(10.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
    }

    #[Test]
    public function volumetric_box_does_not_fake_conservatism_by_only_expanding_volume(): void
    {
        require_once PLUGIN_DIR . '/inc/Utils/Volumetric.php';

        $items = array(
            array('length' => 100, 'width' => 100, 'height' => 1, 'qty' => 1),
            array('length' => 1, 'width' => 1, 'height' => 100, 'qty' => 100),
        );

        $box = \KiriminAjaOfficial\Utils\Volumetric::calculateSmallestBox($items);

        $this->assertSame(200.0, $box['length']);
        $this->assertSame(1.0, $box['width']);
        $this->assertSame(100.0, $box['height']);
    }

    #[Test]
    public function classic_checkout_template_must_render_real_cart_total_not_hardcoded_zero(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');
        $start = strpos($content, '<tr class="order-total">');
        $this->assertNotFalse($start, 'Classic checkout review-order template must render an order total row');
        $row = substr($content, $start, 360);

        $this->assertStringContainsString(
            'wc_cart_totals_order_total_html();',
            $row,
            'Classic/shortcode checkout must use WooCommerce cart total HTML so product subtotal, shipping, Insurance, and COD Fee are reflected'
        );

        $this->assertStringNotContainsString(
            'kiriof_money_format( 0 )',
            $row,
            'Hardcoding Rp0 in the classic review-order template makes /checkout totals always show zero'
        );
    }

    #[Test]
    public function classic_checkout_review_order_must_not_render_payment_methods(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');

        $this->assertStringNotContainsString(
            'checkout/payment-method.php',
            $content,
            'Classic review-order.php must not render payment radios; WooCommerce checkout/payment.php already renders them through woocommerce_checkout_order_review'
        );

        $this->assertStringNotContainsString(
            'kj-payment-checkout',
            $content,
            'Rendering a custom payment row creates duplicate payment_method radios and duplicate #payment IDs on classic checkout'
        );

        $this->assertStringNotContainsString(
            '$available_gateways = WC()->payment_gateways->get_available_payment_gateways();',
            $content,
            'Gateway lookup belongs in WooCommerce checkout/payment.php, not review-order.php'
        );
    }

    #[Test]
    public function classic_checkout_shipping_method_must_not_require_payment_selection_before_showing_rates(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $start = strpos($content, 'public function filterOptions');
        $this->assertNotFalse($start, 'KiriminAja shipping rate filtering method must exist');
        $methodBody = substr($content, $start, 900);

        $this->assertStringNotContainsString(
            'return [];',
            $methodBody,
            'Classic checkout AJAX update_order_review may calculate shipping before any payment radio is checked; requiring a chosen payment method hides all rates even when District/address is fulfilled'
        );

        $this->assertStringContainsString(
            '$is_cod = $chosen_payment_method === \'cod\';',
            $methodBody,
            'Only COD filtering should depend on the chosen payment method; non-COD/unknown payment should still show non-COD rates'
        );
    }

    #[Test]
    public function cod_shipping_filter_must_only_return_cod_capable_rates(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $start = strpos($content, 'public function filterOptions');
        $this->assertNotFalse($start, 'KiriminAja shipping rate filtering method must exist');
        $methodBody = substr($content, $start, 3400);

        $this->assertStringContainsString(
            'if (!$is_cod || $this->isCodCapableOption($option))',
            $methodBody,
            'When COD is selected, shipping rates must be restricted to services marked COD-capable'
        );
        $this->assertStringNotContainsString(
            '$filteredOptions = $allOptions;',
            $methodBody,
            'COD checkout must not fall back to non-COD courier rows when no COD-capable rows are detected'
        );
        $this->assertStringContainsString(
            'isCodCapableOption',
            $content,
            'COD capability should be normalized instead of relying only on top-level option->cod'
        );
        $this->assertStringContainsString(
            'cod_fee_amount',
            $content,
            'Some API responses expose COD support through fee settings rather than option->cod'
        );
    }

    #[Test]
    public function legacy_pricing_ajax_cod_filter_has_same_cod_capable_restriction(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/OngkirPricingService.php');

        $this->assertStringContainsString(
            'if (!$this->is_cod || $this->isCodCapableOption($option))',
            $content,
            'Legacy pricing AJAX must restrict selected COD payment to COD-capable services'
        );
        $this->assertStringNotContainsString(
            'return $allOptions;',
            $content,
            'Legacy pricing AJAX must not fall back to non-COD services when COD is selected'
        );
        $this->assertStringContainsString(
            'isCodCapableOption',
            $content,
            'Legacy pricing AJAX should use normalized COD-capable detection too'
        );
    }

    #[Test]
    public function legacy_pricing_ajax_must_use_available_money_formatter(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/OngkirPricingService.php');

        $this->assertStringContainsString(
            'kiriof_money_format($option->cost-$option->discount_amount)',
            $content,
            'Pricing AJAX response must use the plugin money formatter that is loaded by kiriminaja.php'
        );

        $this->assertStringNotContainsString(
            'localMoneyFormat(',
            $content,
            'Undefined localMoneyFormat() breaks kiriof-get-expedition-ajax and leaves the courier/pricing list empty'
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

        $this->assertStringContainsString(
            "'required'     => false",
            $methodBody,
            'Blocks District schema must stay optional because the field can be registered before Woo has cart state; plugin validation/UI enforce it only for shippable carts'
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
        $content = self::billingAddressTemplateContent();

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

        $this->assertStringContainsString(
            'destination_name: data.destination_name',
            $content,
            'Store API cart update must persist the selected district label for later transaction creation'
        );

        $this->assertStringContainsString(
            'force_insurance: data.force_insurance',
            $content,
            'Store API cart update must persist courier-forced insurance so block checkout order creation keeps it'
        );
    }

    #[Test]
    public function block_checkout_triggers_store_api_session_persist_before_legacy_ajax_fee_request(): void
    {
        $content = self::billingAddressTemplateContent();
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
        $content = self::billingAddressTemplateContent();
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
        $content = self::billingAddressTemplateContent();

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

        $domPostcodeStart = strpos($content, 'function kiriofGetCheckoutPostcodeFromDom');
        $this->assertNotFalse($domPostcodeStart, 'DOM postcode resolver must exist');
        $domPostcodeBody = substr($content, $domPostcodeStart, 700);
        $this->assertStringContainsString(
            'if (val) {',
            $domPostcodeBody,
            'DOM postcode resolver must trust partial visible input like 5 so it does not fall back to a stale saved postcode during edits'
        );
        $this->assertStringNotContainsString(
            'String(val).length >= 3',
            $domPostcodeBody,
            'DOM postcode resolver must not ignore partial buyer input and then restore the old postcode from session/store'
        );

        $this->assertStringContainsString(
            'currentValue === savedPostcode || currentValue',
            $content,
            'Saved postcode restoration must only fill empty inputs; otherwise a buyer typing a new postcode gets overwritten by the stale session postcode'
        );

        $this->assertStringContainsString(
            'function kiriofUpdateBlockCheckoutPostcode',
            $content,
            'Block checkout must immediately mirror typed postcodes into the Woo checkout/cart stores so React cannot rehydrate the old session postcode back into the field'
        );

        $this->assertStringContainsString(
            'kiriofUpdateBlockCheckoutPostcode(kiriofLastTypedPostcode)',
            $content,
            'Postcode input handler must update checkout editing state as soon as the buyer types a new postcode'
        );

        $postcodeUpdaterStart = strpos($content, 'function kiriofUpdateBlockCheckoutPostcode');
        $this->assertNotFalse($postcodeUpdaterStart, 'Block checkout postcode updater must exist');
	        $postcodeUpdaterBody = substr($content, $postcodeUpdaterStart, 1800);
	        $this->assertStringContainsString(
	            'setEditingShippingAddress',
	            $postcodeUpdaterBody,
	            'Postcode updater must mirror the typed shipping postcode into wc/store/checkout editing state so Woo Blocks cannot rerender the old postcode'
	        );
	        $this->assertStringContainsString(
	            'setEditingBillingAddress',
	            $postcodeUpdaterBody,
	            'Postcode updater must mirror billing postcode edits into wc/store/checkout editing state when the billing postcode field is edited'
	        );
	        $this->assertStringContainsString(
	            'Object.assign({}, editingShippingAddress, {',
	            $postcodeUpdaterBody,
	            'Postcode updater should match main branch behavior by writing the new postcode into checkout editing shipping address'
	        );
	        $this->assertStringContainsString(
	            'Object.assign({}, editingBillingAddress, {',
	            $postcodeUpdaterBody,
	            'Postcode updater should match main branch behavior by writing the new postcode into checkout editing billing address so the other address group cannot rehydrate the stale postcode'
	        );
	        $this->assertStringContainsString(
	            'function kiriofSchedulePostcodeReapply(postcode)',
	            $content,
	            'Postcode edits must schedule guarded re-application so Woo Blocks rerenders cannot restore the old session postcode'
	        );
	        $this->assertStringContainsString(
	            'Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, \'value\')',
	            $content,
	            'Postcode re-application must use the native input value setter so React-controlled postcode fields accept the typed value'
	        );
	        $this->assertStringContainsString(
	            'kiriofNormalizePostcode(kiriofLastTypedPostcode) !== postcode',
	            $content,
	            'Scheduled postcode re-application must ignore stale timers after the buyer types a newer postcode'
	        );
	        $this->assertStringContainsString(
	            'kiriofSchedulePostcodeReapply(kiriofLastTypedPostcode)',
	            $content,
	            'Postcode input handler must schedule re-application after every non-empty edit'
	        );
	        $this->assertStringNotContainsString(
	            "wp.data.dispatch('wc/store/cart')",
	            $postcodeUpdaterBody,
            'Postcode updater must not write cart billing/shipping address stores because Woo Blocks can rehydrate stale session postcodes over buyer input'
        );
        $this->assertStringNotContainsString(
            'setShippingAddress',
            $postcodeUpdaterBody,
            'Postcode updater must not dispatch cart shipping address mutations'
        );
        $this->assertStringNotContainsString(
            'setBillingAddress',
            $postcodeUpdaterBody,
            'Postcode updater must not dispatch cart billing address mutations'
        );

        $currentPostcodeStart = strpos($content, 'function kiriofGetCurrentPostcodeKey');
        $this->assertNotFalse($currentPostcodeStart, 'Current postcode resolver must exist');
	        $currentPostcodeBody = substr($content, $currentPostcodeStart, 900);
	        $this->assertStringContainsString(
	            'if (recentlyTyped) {',
	            $currentPostcodeBody,
	            'Current postcode resolver must prefer recent buyer edits, including clearing the field, over stale session/store values while Woo Blocks re-renders'
	        );

        $restorePostcodeStart = strpos($content, 'function kiriofRestoreSavedPostcodeField');
        $this->assertNotFalse($restorePostcodeStart, 'Saved postcode restore helper must exist');
        $restorePostcodeBody = substr($content, $restorePostcodeStart, 600);
        $this->assertStringContainsString(
            'Date.now() - kiriofLastTypedPostcodeAt',
            $restorePostcodeBody,
            'Saved postcode restore must not run immediately after buyer typing'
        );

        $this->assertStringContainsString(
            'function kiriofScheduleFetchDistricts(postcode, delay)',
            $content,
            'Direct postcode typing should debounce district lookup so partial values like 555 or 5558 do not race the final postcode'
        );

	        $this->assertStringContainsString(
	            'requestId !== kiriofDistrictLookupRequestId || currentPostcode !== postcode',
	            $content,
	            'District lookup responses must be ignored when they no longer match the currently edited postcode'
	        );
	        $fetchStart = strpos($content, 'function kiriofFetchDistricts(postcode)');
	        $this->assertNotFalse($fetchStart, 'District lookup helper must exist');
	        $fetchBody = substr($content, $fetchStart, 1800);
	        $this->assertStringContainsString(
	            'skipCheckoutSync: true',
	            $fetchBody,
	            'Starting a district lookup should clear stale District locally without dispatching checkout field updates that can rerender postcode from old state'
	        );

	        $postcodeHandlerStart = strpos($content, 'input.kiriofBlockPostcode change.kiriofBlockPostcode');
        $this->assertNotFalse($postcodeHandlerStart, 'Block checkout postcode input handler must exist');
        $postcodeHandlerBody = substr($content, $postcodeHandlerStart, 2200);
        $this->assertStringContainsString(
            'kiriofResetBlockDistrictState({',
            $postcodeHandlerBody,
            'Any buyer postcode edit, including partial input, must clear the stale selected District immediately'
        );
        $this->assertStringContainsString(
            'skipStoreSync: true',
            $postcodeHandlerBody,
            'Clearing stale District while typing must stay local so Woo Blocks does not rehydrate the previous address postcode'
        );
	        $this->assertStringContainsString(
	            'skipCheckoutSync: true',
	            $postcodeHandlerBody,
	            'Clearing stale District while typing must not dispatch checkout additional-field updates that can rerender the postcode input from old state'
	        );
	        $this->assertStringContainsString(
	            'kiriofLastTypedPostcodeAt  = Date.now();',
	            $postcodeHandlerBody,
	            'Clearing postcode must mark the field as recently edited so the resolver does not fall back to stale store/session postcode'
	        );
	        $this->assertStringContainsString(
	            "kiriofSchedulePostcodeReapply('');",
	            $postcodeHandlerBody,
	            'Clearing postcode must also be re-applied after Woo Blocks rerenders so the old session postcode does not return'
	        );

        $resetStart = strpos($content, 'function kiriofResetBlockDistrictState');
        $this->assertNotFalse($resetStart, 'Block checkout district reset helper must exist');
        $resetBody = substr($content, $resetStart, 1200);
        $this->assertStringContainsString(
            'if (!options.skipCheckoutSync)',
            $resetBody,
            'District reset must support a local-only path while the buyer is editing postcode'
        );
    }

    #[Test]
    public function block_checkout_district_select_keeps_react_text_field_as_hidden_source_of_truth(): void
    {
        $content = self::billingAddressTemplateContent();

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
        $content = self::billingAddressTemplateContent();

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
        $content = self::billingAddressTemplateContent();

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
            "name.slice(-kiriofFieldId.length) === kiriofFieldId",
            $content,
            'District finder must match Woo Blocks address-scoped field names such as shipping_kiriminaja-official/kiriof_destination_area, not only the bare additional-field key'
        );

        $this->assertStringContainsString(
            "name.indexOf('kiriof_destination_area_name') !== -1",
            $content,
            'District finder must exclude the companion hidden destination label inputs so restore/update writes hit the real required address field'
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

        $this->assertStringContainsString(
            'kiriofDistrictObserverTimer',
            $content,
            'District DOM observer must be debounced because Woo Blocks can emit many mutations during address and shipping-rate recalculation'
        );

        $this->assertStringContainsString(
            'districtObserverTarget',
            $content,
            'District DOM observer should be scoped to the checkout root instead of watching the entire document body'
        );

        $this->assertStringNotContainsString(
            'observe(document.body',
            $content,
            'Watching document.body during Woo Blocks rerenders can create a mutation storm and freeze the checkout tab'
        );

        $this->assertStringContainsString(
            'function kiriofSyncBlockDistrictSourceField',
            $content,
            'Block checkout needs a dedicated source-field sync helper because Woo Blocks can remount the required hidden District field after the custom select already exists'
        );

        $this->assertStringContainsString(
            'kiriofSyncBlockDistrictSourceField(',
            $content,
            'Restore and submit paths must re-sync the hidden District source field so checkout validation still sees the selected value after React re-renders'
        );

        $this->assertStringContainsString(
            "wp.data.dispatch('wc/store/validation')",
            $content,
            'District source-field sync should clear stale Woo Blocks validation errors once the required field has been repopulated'
        );

        $this->assertStringContainsString(
            "clearValidationErrors([",
            $content,
            'District source-field sync must clear both shipping and billing validation error keys so checkout does not stay stuck after the field value is restored'
        );

        $this->assertStringContainsString(
            "Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')",
            $content,
            'District source-field sync must use the native input value setter so React-controlled hidden fields keep the selected District value instead of reverting to empty'
        );

        $this->assertStringContainsString(
            'sourceField.required = false;',
            $content,
            'Block District source field must not keep the browser-required constraint after the custom select takes over, otherwise Place Order becomes a dead button'
        );
    }

    #[Test]
    public function block_checkout_initializer_must_only_bind_subscribers_once(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofInitBlockCheckoutCompatibility()');
        $this->assertNotFalse($start, 'Block checkout initializer must exist');
        $functionBody = substr($content, $start, 900);

        $this->assertStringContainsString(
            'window.kiriofBlockCheckoutCompatibilityInitialized',
            $functionBody,
            'Block checkout initializer must be guarded because the template can be printed from multiple checkout hooks, otherwise wp.data subscribers and observers are registered more than once'
        );

        $this->assertStringContainsString(
            'return;',
            $functionBody,
            'Repeated block checkout initializer calls should exit before registering another set of subscribers'
        );
    }

    #[Test]
    public function block_checkout_district_change_updates_checkout_additional_fields_store(): void
    {
        $content = self::billingAddressTemplateContent();

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
            'setEditingShippingAddress',
            $content,
            'Required block checkout District field must be mirrored into the checkout editing shipping address store because live Woo Blocks submission reads from wc/store/checkout editing state'
        );

        $this->assertStringContainsString(
            'setEditingBillingAddress',
            $content,
            'Block checkout should also clear billing-side District validation through the checkout editing address store when Woo Blocks tracks both address groups'
        );

        $this->assertStringContainsString(
            'getAdditionalFields',
            $content,
            'District updater should merge with existing checkout additional fields instead of overwriting unrelated extension fields'
        );

        $functionStart = strpos($content, 'function kiriofUpdateCheckoutAdditionalFields');
        $this->assertNotFalse($functionStart, 'District additional-field updater must exist');
        $functionBody = substr($content, $functionStart, 2600);

        $this->assertStringNotContainsString(
            "wp.data.dispatch('wc/store/cart')",
            $functionBody,
            'District additional-field sync must not also write cart billing/shipping address stores; that dispatches broad cart updates and can freeze block checkout while rates are recalculating'
        );

        $this->assertStringContainsString(
            'extensionCartUpdate',
            $content,
            'District selection should persist cart/session state through the Store API extension update instead of mutating cart address stores from the additional-field helper'
        );

        $this->assertStringContainsString(
            'kiriofForceBlockCartUpdate(data.destination_name',
            $content,
            'When wc/store globals are unavailable or the extension update is deduped, District persistence still needs the raw Store API customer update fallback to trigger rates'
        );

        $this->assertStringContainsString(
            'function kiriofEnsureLegacyBlockDistrictMirror',
            $content,
            'Block checkout should maintain a classic kiriof_destination_area hidden mirror for legacy PHP validation paths that still read the non-namespaced POST key'
        );

        $this->assertStringContainsString(
            'name="kiriof_destination_area"',
            $content,
            'Legacy block District mirror must submit the classic kiriof_destination_area field name so checkout validation and order persistence do not see an empty district'
        );

        $this->assertStringContainsString(
            'function kiriofSetCheckoutTokenValue',
            $content,
            'Block checkout should explicitly manage kiriof_checkout_token because the classic changeDistrict Select2 flow is not responsible for the React District select'
        );
    }

    #[Test]
    public function block_checkout_registers_district_only_for_shipping_address(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            "'location'     => 'address'",
            $content,
            'District field must remain registered as an address field for Woo Blocks checkout'
        );

        $this->assertStringContainsString(
            "'address_type' => array( 'shipping' )",
            $content,
            'Block checkout should register District only on the shipping address because the live checkout renders a shipping field and server-side validation otherwise requires a missing billing field too'
        );

        $this->assertStringNotContainsString(
            "'address_type' => array( 'billing', 'shipping' )",
            $content,
            'Block checkout must not require District on both billing and shipping when only the shipping field is rendered'
        );
    }

    #[Test]
    public function block_checkout_requires_district_before_showing_shipping_options(): void
    {
        $content = self::billingAddressTemplateContent();
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $shippingMethod = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $css = file_get_contents(PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css');

        $this->assertStringContainsString(
            'kiriofEnsureBlockDistrictWarning',
            $content,
            'Block checkout should create a visible warning near Shipping address when District is still missing'
        );

        $this->assertStringContainsString(
            'Please select your District to view shipping options.',
            $content,
            'Buyer-facing block checkout warning should clearly explain why shipping methods are unavailable'
        );

        $this->assertStringContainsString(
            'kiriof-shipping-options-blocked',
            $content,
            'Block checkout should toggle a blocked state on shipping options until District is selected'
        );

        $this->assertStringContainsString(
            'destination_id <= 0',
            $controller,
            'Store API update callback should clear stale destination and shipping session data when District is not set'
        );

        $this->assertStringContainsString(
            'if ( empty( $destination_id ) )',
            $shippingMethod,
            'Shipping method calculation should bail out when District is missing so stale shipping costs do not show'
        );

        $this->assertStringContainsString(
            '.kiriof-shipping-options-blocked',
            $css,
            'Frontend styles should suppress the shipping options list while District is incomplete'
        );
    }

    #[Test]
    public function shipping_method_hides_rates_until_checkout_address_is_long_enough(): void
    {
        $shippingMethod = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $calculateStart = strpos($shippingMethod, 'public function calculate_shipping');
        $this->assertNotFalse($calculateStart, 'KiriminAja shipping calculation method must exist');
        $calculateBody = substr($shippingMethod, $calculateStart, 12000);

        $addressGatePosition = strpos($calculateBody, 'kiriof_has_sufficient_checkout_address');
        $freeShippingPosition = strpos($calculateBody, 'hasActiveFreeShippingCoupon');
        $pricingPosition = strpos($calculateBody, 'getPricing');

        $this->assertNotFalse(
            $addressGatePosition,
            'KiriminAja rates must be gated by checkout address length'
        );
        $this->assertNotFalse(
            $freeShippingPosition,
            'Free-shipping coupon branch must remain present'
        );
        $this->assertNotFalse(
            $pricingPosition,
            'API pricing branch must remain present'
        );
        $this->assertLessThan(
            $freeShippingPosition,
            $addressGatePosition,
            'Address-length validation must run before adding the KiriminAja free-shipping rate'
        );
        $this->assertLessThan(
            $pricingPosition,
            $addressGatePosition,
            'Address-length validation must run before requesting KiriminAja pricing'
        );

        $this->assertStringContainsString(
            'private const KIRIOF_MIN_ADDRESS_LENGTH = 20;',
            $shippingMethod,
            'KiriminAja pricing should stay hidden until the address line has at least 20 characters'
        );
        $this->assertStringContainsString(
            "array( 'address_1', 'address' )",
            $shippingMethod,
            'Address-length validation must support both Woo Blocks address_1 and classic package address keys'
        );
        $this->assertStringContainsString(
            "'get_shipping_address', 'get_billing_address'",
            $shippingMethod,
            'Address-length validation should fall back to Woo customer address accessors when package data is unavailable'
        );
        $this->assertStringContainsString(
            'KiriminAja shipping rates hidden because checkout address is too short.',
            $shippingMethod,
            'When rates are hidden by address length, the reason should be traceable in logs'
        );
    }

    #[Test]
    public function block_checkout_district_selection_persists_destination_session_before_shipping_rate_refetch(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'change.kiriofBlockDistrict');
        $this->assertNotFalse($start, 'Block District change handler must exist');
        $handlerBody = substr($content, $start, 2600);

        $persistPosition = strpos($handlerBody, 'kiriofPersistBlockDistrictSelection');
        $refreshPosition = strpos($content, 'kiriofScheduleBlockShippingRatesRefresh');

        $this->assertNotFalse(
            $persistPosition,
            'Selecting a District in block checkout must persist destination_id through Store API before rates are recalculated'
        );

        $this->assertNotFalse(
            $refreshPosition,
            'Block checkout must schedule a single Store API shipping-rate refresh after District persistence; themes may not refetch rates from an additional custom field change alone'
        );

        $this->assertStringContainsString(
            'kiriofBlockExtensionCartUpdate',
            $content,
            'Block District persistence should use the Woo Store API extension update instead of the legacy admin-ajax destination endpoint'
        );

        $this->assertStringNotContainsString(
            'kiriofPersistDestinationArea(val, label',
            $handlerBody,
            'Selecting District in block checkout must not call the legacy destination AJAX endpoint; combining that with Store API updates causes duplicate cart recalculations and freezes'
        );

        $this->assertStringNotContainsString(
            'kiriofUpdateCheckoutAdditionalFields(val)',
            $handlerBody,
            'Selecting District in block checkout must not immediately write Woo checkout additional fields; fresh block checkouts can remount and freeze while shipping rates are recalculating. Commit that field right before place order instead.'
        );

        $placeOrderStart = strpos($content, 'function kiriofCommitSelectedBlockDistrict');
        $this->assertNotFalse($placeOrderStart, 'Place-order District commit must exist');
        $placeOrderBody = substr($content, $placeOrderStart, 1200);

        $this->assertStringContainsString(
            'kiriofUpdateCheckoutAdditionalFields(districtValue)',
            $placeOrderBody,
            'Place-order handling should still push District into Woo checkout additional fields before submission'
        );
    }

    #[Test]
    public function block_checkout_raw_update_customer_uses_store_api_nonce_header(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofForceBlockCartUpdate');
        $this->assertNotFalse($start, 'Block checkout force cart update helper must exist');
        $functionBody = substr($content, $start, 5200);

        $this->assertStringContainsString(
            "headers['Nonce'] = nonce",
            $functionBody,
            'Woo Store API update-customer requires the Nonce header; X-WP-Nonce is treated as missing and blocks pricing recalculation'
        );

        $this->assertStringNotContainsString(
            "headers['X-WP-Nonce']",
            $functionBody,
            'The raw Store API update-customer fallback must not use the regular WP REST nonce header'
        );

        $this->assertStringContainsString(
            'kiriofStoreApiUpdateCustomerUrl',
            $functionBody,
            'The Store API endpoint should come from rest_url so installs in subdirectories do not hard-code /wp-json at the domain root'
        );

        $this->assertStringContainsString(
            'postData.shipping_address = shippingAddress',
            $functionBody,
            'Raw update-customer fallback must include the visible shipping address, otherwise Woo may calculate rates against an incomplete server-side address'
        );

        $this->assertStringContainsString(
            'shippingAddress[kiriofFieldId] = String(districtId)',
            $functionBody,
            'Woo Store API update-customer persists address additional fields from shipping_address, not from top-level additional_fields'
        );

        $this->assertStringContainsString(
            'postData.billing_address = billingAddress',
            $functionBody,
            'Raw update-customer fallback should include billing address when available so Store API customer state remains complete'
        );

        $this->assertStringContainsString(
            'billingAddress[kiriofFieldId] = String(districtId)',
            $functionBody,
            'Billing address payload should carry the District additional field too so Store API customer state remains complete'
        );

        $this->assertStringContainsString(
            'var currentPostcode = kiriofGetCurrentPostcodeKey();',
            $functionBody,
            'Raw update-customer fallback must capture the currently edited postcode before building address payloads'
        );

        $this->assertStringContainsString(
            'kiriofApplyCurrentPostcodeToStoreApiAddress(',
            $functionBody,
            'Raw update-customer fallback must force the visible/current postcode onto Store API addresses so stale session postcodes do not rehydrate over buyer input'
        );

        $this->assertStringContainsString(
            'wc-blocks_added_to_cart',
            $content,
            'After a raw Store API customer update, dispatch Woo Blocks cart refresh event so React checkout invalidates cart/rates even when wp.data is not available to our script'
        );

        $this->assertStringContainsString(
            'kiriofLastRawStoreCustomerUpdateKey',
            $content,
            'Raw Store API fallback needs a short throttle so refresh events do not create an update loop during checkout rerenders'
        );
    }

    #[Test]
    public function block_checkout_valid_district_hides_all_stale_district_warnings(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofSyncBlockDistrictWarningState');
        $this->assertNotFalse($start, 'District warning sync helper must exist');
        $functionBody = substr($content, $start, 1800);

        $this->assertStringContainsString(
            "jQuery('.kiriof-block-district-warning').hide();",
            $functionBody,
            'Woo Blocks can rerender the shipping step after District selection; hide all stale plugin warning nodes when a valid District is present'
        );
    }

    #[Test]
    public function block_checkout_resyncs_all_district_source_fields_after_blocks_rerender(): void
    {
        $content = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            'function kiriofGetBlockDistrictFields',
            $content,
            'Block checkout District sync must collect all matching Woo/Kiriof hidden fields, not only the first one'
        );

        $this->assertStringContainsString(
            '$fields.each(function()',
            $content,
            'District source sync should write every matching hidden field because Woo Blocks can recreate its registered field with an empty value'
        );

        $this->assertStringContainsString(
            'function kiriofResyncSelectedBlockDistrictSource',
            $content,
            'A selected visible District must be able to repopulate Woo hidden fields after a block rerender'
        );

        $this->assertStringContainsString(
            'kiriofResyncSelectedBlockDistrictSource();',
            $content,
            'MutationObserver and valid-state checks should resync Woo hidden fields when checkout blocks remount'
        );
    }

    #[Test]
    public function block_checkout_restores_saved_district_selection_for_the_same_postcode(): void
    {
        $content = self::billingAddressTemplateContent();
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'kiriofSavedDistrictByPostcode',
            $content,
            'Block checkout should preload saved District selections keyed by postcode from session'
        );

        $this->assertStringContainsString(
            'kiriofGetSavedDistrictForPostcode',
            $content,
            'District selector should look up a saved selection for the current postcode before forcing the buyer to choose again'
        );

        $this->assertStringContainsString(
            'kiriofRememberDistrictForPostcode',
            $content,
            'Selecting a District should save that postcode-to-district pairing in the frontend state'
        );

        $this->assertStringContainsString(
            'kiriofRestoreSavedDistrictForCurrentPostcode',
            $content,
            'Fetched district results should automatically restore the saved District when the postcode matches'
        );

        $this->assertStringContainsString(
            'kiriofPendingDistrictRestore',
            $content,
            'Block checkout should keep a short-lived restore state so the saved District can be re-applied before the warning banner shows'
        );

        $this->assertStringContainsString(
            'silentWarning: kiriofPendingDistrictRestore',
            $content,
            'Resetting District state for a postcode with a saved selection should suppress the warning until the restore attempt finishes'
        );

        $this->assertStringContainsString(
            'skipStoreSync: true',
            $content,
            'Postcode edits should clear local District UI without immediately sending a destination reset back through Store API, otherwise Woo blocks can snap the postcode back to the persisted address'
        );

        $this->assertStringContainsString(
            'kiriofNormalizePostcode',
            $content,
            'Saved District restoration should normalize postcode keys before looking them up in the frontend session map'
        );

        $this->assertStringContainsString(
            'kiriofSavedCheckoutPostcode',
            $content,
            'Block checkout should preload the latest checkout postcode from session so a hard refresh can restore the current postcode before District lookup runs'
        );

        $this->assertStringContainsString(
            'kiriofRestoreSavedPostcodeField',
            $content,
            'Block checkout should restore the saved postcode back into the visible Woo Blocks field before attempting District re-selection'
        );

        $this->assertStringContainsString(
            'kiriofRestoreSavedCheckoutState',
            $content,
            'Block checkout should restore postcode and District together after Woo re-renders the address form from the compact Edit state'
        );

        $this->assertStringContainsString(
            'kiriofGetFocusedPostcodeInput',
            $content,
            'Block checkout postcode sync should prefer the actively edited postcode field over stale store values'
        );

        $this->assertStringContainsString(
            'kiriofLastTypedPostcodeAt',
            $content,
            'Block checkout postcode sync should keep a short-lived typing window so store subscribers do not snap the field back to a cached postcode'
        );

        $this->assertStringContainsString(
            'postcode: data.postcode',
            $content,
            'Store API session persistence should include the current postcode so the server can remember District selections by postcode'
        );

        $this->assertStringContainsString(
            'kiriof_destination_postcode_map',
            $controller,
            'Checkout controller should store District selections in Woo session keyed by postcode'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_checkout_postcode', \$postcode );",
            $controller,
            'Store API callback should persist the latest checkout postcode in session so block checkout can restore it after a full page refresh'
        );
    }

    #[Test]
    public function block_checkout_shipping_rate_refresh_uses_cart_dispatch_invalidation_fallbacks(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofRefreshBlockShippingRates');
        $this->assertNotFalse($start, 'Block checkout must define an explicit shipping-rate refresh helper');
        $methodBody = substr($content, $start, 3200);

        $this->assertStringContainsString(
            "wp.data.dispatch('wc/store/cart')",
            $methodBody,
            'Shipping-rate refresh helper must use the Woo cart data store used by checkout blocks'
        );

        $this->assertStringContainsString(
            'invalidateResolutionForStoreSelector',
            $methodBody,
            'Blocksy/Woo Blocks may cache getShippingRates; invalidating that selector forces a Store API rates refetch'
        );

        $this->assertStringContainsString(
            'getShippingRates',
            $methodBody,
            'The invalidated selector should be getShippingRates because missing shipping list is a stale rates problem'
        );

        $this->assertStringContainsString(
            'invalidateResolutionForStore',
            $methodBody,
            'Older Woo Blocks versions need the broader store invalidation fallback'
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

        $this->assertStringNotContainsString(
            'is_checkout() || $is_store_api_request',
            $content,
            'Shipping option filtering must not hide all rates while payment is still unset; block/classic checkout may calculate rates before payment selection is persisted'
        );
    }

    #[Test]
    public function block_checkout_cod_fee_reads_and_watches_wc_payment_store(): void
    {
        $content = self::billingAddressTemplateContent();

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
            'function kiriofNormalizePaymentMethod(paymentMethod)',
            $content,
            'Payment method detection should normalize all supported classic and Woo Blocks shapes in one safe helper'
        );

        $this->assertStringContainsString(
            'paymentMethod.paymentMethodSlug',
            $content,
            'Some Woo Blocks versions expose getActivePaymentMethod as an object using paymentMethodSlug; COD detection must support that shape too'
        );

        $this->assertStringContainsString(
            'paymentMethod.name',
            $content,
            'Woo Blocks payment method objects may use name as the gateway slug'
        );

        $this->assertStringContainsString(
            'paymentMethod.id',
            $content,
            'Woo Blocks or extension wrappers may expose the gateway slug as id'
        );

        $this->assertStringContainsString(
            'paymentMethod.value',
            $content,
            'Fallback object values should be supported without changing classic checkout behavior'
        );

        $this->assertStringContainsString(
            'kiriofLastPaymentMethod',
            $content,
            'Block checkout must subscribe to payment method changes and recalculate COD fee when the buyer selects COD'
        );
    }

    #[Test]
    public function block_checkout_cod_fee_keeps_cod_when_store_api_payment_response_lags(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofGetPaymentMethod()');
        $this->assertNotFalse($start, 'Payment method reader must exist');
        $methodBody = substr($content, $start, 2600);

        $this->assertStringContainsString(
            "[name=payment_method][value=\"cod\"]",
            $methodBody,
            'Woo Blocks can render a hidden COD input without checked state; COD detection must inspect its checked/aria state instead of falling back to non-COD .val()'
        );

        $this->assertStringContainsString(
            'aria-checked',
            $methodBody,
            'Woo Blocks payment radio wrappers expose active selection via aria-checked when the Store API payment selector has not updated yet'
        );

        $this->assertStringContainsString(
            'getPaymentMethodData',
            $methodBody,
            'Payment method detection should also inspect paymentMethodData.payment_method for Store API checkout compatibility'
        );

        $this->assertStringContainsString(
            'payment_method: data.payment_method',
            $content,
            'Store API extensionCartUpdate must persist the detected payment method so native fee calculation can add COD Fee'
        );
    }

    #[Test]
    public function block_checkout_payment_reader_must_not_fall_back_to_first_payment_input_value(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofGetPaymentMethod()');
        $this->assertNotFalse($start, 'Payment method reader must exist');
        $methodBody = substr($content, $start, 3200);

        $this->assertStringNotContainsString(
            'jQuery("[name=payment_method]").val()',
            $methodBody,
            'When Woo Blocks has no checked classic radio, reading the first payment_method input can return cod even while Direct bank transfer is active'
        );

        $storePosition = strpos($methodBody, "wp.data.select('wc/store/payment')");
        $codDomPosition = strpos($methodBody, '[name=payment_method][value="cod"]');
        $this->assertNotFalse($storePosition, 'Block checkout payment reader must inspect the Woo payment store');
        $this->assertNotFalse($codDomPosition, 'DOM COD fallback can exist for Store API lag cases');
        $this->assertLessThan(
            $codDomPosition,
            $storePosition,
            'Authoritative Woo payment store must be checked before COD DOM fallbacks so switching to Direct bank transfer clears stale COD'
        );
    }

    #[Test]
    public function store_api_update_callback_persists_and_clears_payment_method_explicitly(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_store_api_update_checkout');
        $this->assertNotFalse($start, 'Store API update callback must exist');
        $methodBody = substr($content, $start, 2400);

        $this->assertStringContainsString(
            'WC()->session->set( \'chosen_payment_method\', $payment_method );',
            $methodBody,
            'Store API callback must persist COD when block checkout reports it'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_payment_method', '' );",
            $methodBody,
            'Store API callback must explicitly clear stale COD when buyer switches away from COD'
        );
    }

    #[Test]
    public function store_api_update_callback_persists_force_insurance_in_session(): void
    {
        require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

        $session = new class {
            public array $values = array();

            public function set($key, $value): void
            {
                $this->values[$key] = $value;
            }

            public function get($key, $default = null)
            {
                return $this->values[$key] ?? $default;
            }
        };
        $GLOBALS['kiriof_test_wc'] = (object) array('session' => $session);

        $controller = new \KiriminAjaOfficial\Controllers\CheckoutController();
        $controller->kiriof_store_api_update_checkout(array(
            'shipping_metode_id' => 'kiriminaja-official_jne_REG23',
            'destination_id'     => 44064,
            'destination_name'   => 'Sariharjo',
            'payment_method'     => 'bacs',
            'insurance'          => 1,
            'force_insurance'    => 1,
        ));

        $this->assertSame(1, $session->get('force_insurance'));
        $this->assertSame(1, $session->get('kiriof_force_insurance'));
        $this->assertSame(1, $session->get('kiriof_insurance'));
        $this->assertSame(array('kiriminaja-official_jne_REG23'), $session->get('kiriof_chosen_shipping_methods'));
        $this->assertSame('jne_REG23', $session->get('kiriof_expedition'));
    }

    #[Test]
    public function store_api_update_callback_does_not_overwrite_visible_block_postcode_from_session(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_store_api_update_checkout');
        $this->assertNotFalse($start, 'Store API update callback must exist');
        $methodBody = substr($content, $start, 6000);

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_checkout_postcode', \$postcode );",
            $methodBody,
            'Store API callback may remember postcode in plugin session for district restoration'
        );

        $this->assertStringNotContainsString(
            'set_shipping_postcode',
            $methodBody,
            'Store API extension updates must not write postcode back to the Woo customer object because Woo Blocks can rehydrate that stale value over the buyer typed postcode'
        );

        $this->assertStringNotContainsString(
            'set_billing_postcode',
            $methodBody,
            'Store API extension updates must not write the extension postcode into billing address state'
        );
    }

    #[Test]
    public function store_api_update_callback_recalculates_totals_after_block_destination_changes(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_store_api_update_checkout');
        $this->assertNotFalse($start, 'Store API update callback must exist');
        $methodBody = substr($content, $start, 6000);

        $destinationPosition = strpos($methodBody, "WC()->session->set( 'kiriof_destination_area', \$destination_id );");
        $calculatePosition = strpos($methodBody, 'WC()->cart->calculate_totals();');

        $this->assertNotFalse(
            $destinationPosition,
            'Store API callback must persist the selected District before rates are recalculated'
        );
        $this->assertNotFalse(
            $calculatePosition,
            'Store API callback must recalculate totals after District/payment/shipping context changes so block checkout receives fresh rates'
        );
        $this->assertLessThan(
            $calculatePosition,
            $destinationPosition,
            'Destination must be in session before Woo recalculates shipping totals'
        );
    }

    #[Test]
    public function block_checkout_payment_change_refreshes_cod_rates_and_fee_totals(): void
    {
        $content = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            'kiriofPendingPaymentMethod',
            $content,
            'Block checkout must remember the clicked payment method before the Woo payment store catches up'
        );

        $this->assertStringContainsString(
            'kiriofGetPaymentMethodFromElement',
            $content,
            'Payment refresh should read the clicked gateway directly so selecting COD sends payment_method=cod immediately'
        );

        $this->assertStringContainsString(
            "invalidateResolutionForStoreSelector('getShippingRates')",
            $content,
            'COD payment changes must invalidate shipping rates so non-COD courier services disappear from the checkout list'
        );

        $this->assertStringContainsString(
            "invalidateResolutionForStoreSelector('getCartData')",
            $content,
            'COD payment changes must invalidate full cart data so the Order Summary fee list receives COD Fee immediately'
        );

        $this->assertStringContainsString(
            'kiriofScheduleBlockCartDataRefresh',
            $content,
            'Block checkout should throttle cart data refreshes so switching payment/shipping does not keep Woo Blocks shimmer loading indefinitely'
        );

        $this->assertStringContainsString(
            'kiriofRefreshBlockPaymentMethodsData',
            $content,
            'Block checkout should invalidate payment methods after shipping/payment context changes so COD disappears for non-COD couriers'
        );

        $this->assertStringContainsString(
            "invalidateResolution('wc/store/payment'",
            $content,
            'Payment method invalidation should target the Woo Blocks payment store'
        );

        $this->assertStringNotContainsString(
            "kiriofDispatchWooBlocksCartRefresh();\n\n                    if (kiriofPendingFeeRefresh",
            $content,
            'Payment changes should not dispatch the heavy Woo Blocks added-to-cart refresh after every extensionCartUpdate response'
        );
    }

    #[Test]
    public function cod_checkout_validation_rejects_selected_non_cod_courier(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $validator = file_get_contents(PLUGIN_DIR . '/inc/Services/CheckoutServices/ValidationCodCalculationService.php');

        $this->assertStringContainsString(
            "'shipping_packages' => WC()->shipping()->get_packages()",
            $controller,
            'Checkout validation needs the selected Woo shipping rate metadata to block COD with a non-COD courier'
        );

        $this->assertStringContainsString(
            'validateSelectedCourierSupportsCod',
            $validator,
            'COD validation must check the selected courier service before the order is created'
        );

        $this->assertStringContainsString(
            "['kiriof_rate_cod_available']",
            $validator,
            'Validation should use the same COD availability metadata attached to KiriminAja shipping rates'
        );

        $this->assertStringContainsString(
            "'no' === \$cod_available",
            $validator,
            'A selected KiriminAja service explicitly marked non-COD must block COD checkout'
        );
    }

    #[Test]
    public function cod_gateway_is_removed_when_selected_kiriminaja_rate_is_not_cod_capable(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $gatewayStart = strpos($content, 'public function kiriof_filter_cod_availability');
        $this->assertNotFalse($gatewayStart, 'COD availability filter must exist');
        $gatewayBody = substr($content, $gatewayStart, 2600);

        $this->assertStringContainsString(
            'kiriof_selected_shipping_rate_supports_cod( $chosen_methods )',
            $gatewayBody,
            'COD gateway availability must inspect the selected KiriminAja shipping rate'
        );

        $this->assertStringContainsString(
            'false === $selected_rate_supports_cod',
            $gatewayBody,
            'COD gateway must be removed when the chosen courier has cod=false metadata'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_payment_method', '' );",
            $gatewayBody,
            'Stale chosen COD session state must be cleared after selecting a non-COD courier'
        );

        $helperStart = strpos($content, 'private function kiriof_selected_shipping_rate_supports_cod');
        $this->assertNotFalse($helperStart, 'Selected rate COD capability helper must exist');
        $helperBody = substr($content, $helperStart, 2600);

        $this->assertStringContainsString(
            'kiriof_shipping_coupon_rate_meta',
            $helperBody,
            'Selected-rate COD checks should use the session metadata map built while rates are added'
        );

        $this->assertStringContainsString(
            'kiriof_rate_cod_available',
            $helperBody,
            'Selected-rate COD checks should read the rate metadata generated from pricing option cod flags'
        );
    }

    #[Test]
    public function admin_ajax_cod_fee_only_sets_cod_payload_for_cod_payment(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php');
        $start = strpos($content, 'public function kiriof_getDataAfterUpdateCheckout()');
        $this->assertNotFalse($start, 'Admin AJAX checkout recalculation handler must exist');
        $methodBody = substr($content, $start, 3200);

        $this->assertStringContainsString(
            '\'is_cod\'                => $payment_method === \'cod\'',
            $methodBody,
            'Backend calculation must only request COD fee for the actual COD gateway'
        );

        $this->assertStringNotContainsString(
            'if (!empty($payment_method))',
            $methodBody,
            'A non-empty non-COD payment method must not be treated as enough evidence to expose COD Fee'
        );

        $this->assertStringContainsString(
            'if (\'cod\' === $payment_method)',
            $methodBody,
            'AJAX response should expose cod_fee/is_cod_amt only when COD is the selected payment method'
        );
    }

    #[Test]
    public function admin_ajax_fee_refresh_preserves_selected_shipping_method_before_recalculating_totals(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php');
        $start = strpos($content, 'public function kiriof_getDataAfterUpdateCheckout()');
        $this->assertNotFalse($start, 'Admin AJAX checkout recalculation handler must exist');
        $methodBody = substr($content, $start, 5600);

        $kiriofSessionPosition = strpos($methodBody, 'WC()->session->set( \'kiriof_chosen_shipping_methods\', array( $shipping_metode_id ) );');
        $sessionPosition = strpos($methodBody, 'WC()->session->set( \'chosen_shipping_methods\', array( $shipping_metode_id ) );');
        $calculatePosition = strpos($methodBody, 'WC()->cart->calculate_totals();');
        $this->assertNotFalse(
            $kiriofSessionPosition,
            'Fee AJAX must keep plugin-owned selected courier session data under the kiriof_ prefix'
        );
        $this->assertNotFalse(
            $sessionPosition,
            'Fee AJAX must persist the newly selected courier before WooCommerce recalculates totals'
        );
        $this->assertNotFalse($calculatePosition, 'Fee AJAX recalculates WooCommerce totals after caching fee data');
        $this->assertLessThan(
            $sessionPosition,
            $kiriofSessionPosition,
            'The prefixed plugin session mirror should be written before syncing the WooCommerce core chosen_shipping_methods key'
        );
        $this->assertLessThan(
            $calculatePosition,
            $sessionPosition,
            'Persisting chosen_shipping_methods before calculate_totals prevents classic checkout from re-rendering the previous/default courier'
        );
    }

    #[Test]
    public function block_checkout_district_select_uses_dedicated_wrapper_without_overlapping_source_field(): void
    {
        $content = self::billingAddressTemplateContent();
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
        $content = self::billingAddressTemplateContent();
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
        $template = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            'function kiriofSetFeeSkeletonLoading',
            $template,
            'Frontend script keeps a loading-state helper while recalculating native WooCommerce fee totals'
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
    public function classic_checkout_uses_native_wc_fee_rows_instead_of_hidden_placeholder_rows(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $reviewTemplate = file_get_contents(PLUGIN_DIR . '/templates/woocommerce/checkout/review-order.php');

        $this->assertStringContainsString(
            'foreach ( WC()->cart->get_fees() as $fee )',
            $reviewTemplate,
            'Classic checkout review-order template must render native WooCommerce fee rows'
        );

        $this->assertStringNotContainsString(
            'kiriof_should_use_native_checkout_fees',
            $content,
            'Classic checkout must not bail out of native WooCommerce fees; otherwise Insurance and COD Fee disappear on classic themes'
        );

        $this->assertStringNotContainsString(
            'kiriof_cart_item_cod_fee',
            $content,
            'Classic checkout should not rely on hidden AJAX placeholder rows that can be missing/stale after WooCommerce refreshes the order review'
        );

        $this->assertStringNotContainsString(
            'kiriof_cart_item_insurane',
            $content,
            'Classic checkout should render Insurance through native WooCommerce fee rows, not a typo-prone hidden placeholder row'
        );
    }

    #[Test]
    public function classic_checkout_updated_checkout_handler_must_not_recalculate_fees_recursively(): void
    {
        $template = self::billingAddressTemplateContent();
        $handlerStart = strpos($template, "jQuery(document.body).on( 'updated_checkout', function() {");
        $this->assertNotFalse($handlerStart, 'Classic updated_checkout compatibility handler must exist');
        $handlerBody = substr($template, $handlerStart, 420);

        $this->assertStringNotContainsString(
            'kiriofCodInsurance()',
            $handlerBody,
            'updated_checkout fires after kiriofCodInsurance refreshes native fee rows; calling kiriofCodInsurance from this handler creates an endless update_checkout/loading loop on classic themes'
        );

        $feeFunctionStart = strpos($template, 'function kiriofCodInsurance()');
        $this->assertNotFalse($feeFunctionStart, 'Fee AJAX function must exist');
        $successStart = strpos($template, 'success:function(response)', $feeFunctionStart);
        $this->assertNotFalse($successStart, 'Fee AJAX success handler must exist');
        $successBody = substr($template, $successStart, 900);
        $this->assertStringContainsString(
            "jQuery(document.body).trigger('update_checkout', { update_shipping_method: false });",
            $successBody,
            'After fee cache updates, classic checkout must refresh once so WooCommerce native fee rows render Insurance and COD Fee'
        );

        $this->assertStringContainsString(
            'if (!kiriofIsBlockCheckoutContext())',
            $successBody,
            'Fee AJAX success must skip the classic update_checkout fragment refresh path when running inside block checkout'
        );
    }

    #[Test]
    public function checkout_refresh_handlers_must_not_accumulate_duplicate_change_listeners(): void
    {
        $template = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            ".off('change.kiriofPaymentRefresh'",
            $template,
            'Payment and insurance refresh binding must unbind the previous delegated handler before rebinding, otherwise each updated_checkout adds another listener and amplifies AJAX refreshes'
        );

        $this->assertStringContainsString(
            ".on('change.kiriofPaymentRefresh'",
            $template,
            'Payment and insurance refresh binding must use a namespaced delegated handler so rebinding stays idempotent across checkout refreshes'
        );

        $this->assertStringContainsString(
            ".off('change.kiriofDifferentAddress'",
            $template,
            'Ship-to-different-address binding must be removed before re-attaching or each checkout refresh stacks another change handler'
        );

        $this->assertStringContainsString(
            ".on('change.kiriofDifferentAddress'",
            $template,
            'Ship-to-different-address binding must use a namespaced delegated handler so WooCommerce refreshes do not leak listeners'
        );
    }

    #[Test]
    public function fee_refresh_must_collapse_inflight_requests_instead_of_stacking_more_ajax(): void
    {
        $template = self::billingAddressTemplateContent();
        $start = strpos($template, 'function kiriofCodInsurance()');
        $this->assertNotFalse($start, 'Fee AJAX function must exist');
        $functionBody = substr($template, $start, 7600);

        $this->assertStringContainsString(
            'var kiriofFeeRefreshRequest = null;',
            $template,
            'Checkout script must track the in-flight fee refresh request so repeated UI/store updates do not pile up concurrent admin-ajax calls'
        );

        $this->assertStringContainsString(
            'if (kiriofUpdatingCheckoutLock)',
            $functionBody,
            'Fee refresh must short-circuit while a previous refresh is still running, otherwise block checkout can spiral into repeated refreshes and freeze the tab'
        );

        $this->assertStringContainsString(
            'kiriofFeeRefreshRequest.abort()',
            $functionBody,
            'When a newer fee refresh supersedes an older one, the stale request should be aborted instead of left running in parallel'
        );

        $this->assertStringContainsString(
            "if (textStatus === 'abort')",
            $functionBody,
            'Aborted fee refreshes should exit quietly; alerting on intentional aborts makes rapid checkout updates feel broken'
        );

        $this->assertStringContainsString(
            'complete:function()',
            $functionBody,
            'Fee refresh cleanup must happen in the AJAX complete hook so locks and loading state are always released after success, failure, or abort'
        );
    }

    #[Test]
    public function block_checkout_fee_refreshes_must_be_scheduled_and_skip_classic_fragment_cycles(): void
    {
        $template = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            'var kiriofCodInsuranceTimer = null;',
            $template,
            'Block checkout fee refreshes should be funneled through a shared timer so rapid payment/shipping/store changes collapse into one recalculation'
        );

        $this->assertStringContainsString(
            'function kiriofScheduleCodInsurance(delay)',
            $template,
            'Block checkout should debounce fee refreshes instead of calling kiriofCodInsurance directly from every subscribe and persistence callback'
        );

        $this->assertStringContainsString(
            'if ( kiriofIsBlockCheckoutContext() ) {',
            $template,
            'Block checkout should detect its own context so classic update_checkout wiring can be bypassed'
        );

        $this->assertStringContainsString(
            'kiriofRefreshBlockShippingRates();',
            $template,
            'Block checkout fee AJAX success should refresh block store rates directly instead of always triggering classic checkout fragment refreshes'
        );
    }

    #[Test]
    public function block_checkout_does_not_render_optional_insurance_checkbox_but_classic_uses_updated_wording(): void
    {
        $template = self::billingAddressTemplateContent();
        $styles = file_get_contents(PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css');
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringNotContainsString(
            'function kiriofRenderBlockInsuranceField()',
            $template,
            'Woo Blocks checkout should not inject the optional insurance checkbox until the fee refresh flow is reliable'
        );

        $this->assertStringNotContainsString(
            'id="kiriof-block-insurance"',
            $template,
            'Woo Blocks checkout should not render the block insurance checkbox'
        );

        $this->assertStringNotContainsString(
            'name="kiriof_block_insurance"',
            $template,
            'Woo Blocks checkout should not post a block-only insurance checkbox value'
        );

        $this->assertStringNotContainsString(
            '.kiriof-block-insurance-field',
            $styles,
            'Unused block insurance checkbox styles should not be shipped'
        );

        $this->assertStringContainsString(
            "esc_html__('Add shipping insurance', 'kiriminaja-official')",
            $controller,
            'Classic checkout should keep the updated insurance checkbox wording'
        );

        $this->assertStringNotContainsString(
            "esc_html__('Insurance Shipping', 'kiriminaja-official')",
            $controller,
            'Classic checkout should not use the old insurance checkbox wording'
        );

        $this->assertStringContainsString(
            'insurance: kiriofBillingAddressConfig.globalInsurance ? 1 : 0',
            $template,
            'Block checkout should only send forced insurance state while the optional checkbox is disabled'
        );
    }

    #[Test]
    public function classic_checkout_shipping_radio_change_persists_selected_method_before_fee_refresh(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, "jQuery(document.body).on( 'change', 'input.shipping_method'");
        $this->assertNotFalse($start, 'Classic shipping radio change handler must exist');
        $handlerBody = substr($content, $start, 500);

        $storePosition = strpos($handlerBody, 'kiriofRememberSelectedShippingMethod(jQuery(this).val());');
        $feePosition = strpos($handlerBody, 'kiriofHandleCodInsurance();');

        $this->assertNotFalse($storePosition, 'Changing from ID Express to JNE must persist the newly selected radio value immediately');
        $this->assertNotFalse($feePosition, 'Shipping changes must still refresh KiriminAja fee data');
        $this->assertLessThan($feePosition, $storePosition, 'The selected method should be stored before the KiriminAja fee refresh starts WooCommerce checkout recalculation');
    }

    #[Test]
    public function block_checkout_shipping_radio_click_prefers_recent_user_selection_over_stale_store_rate(): void
    {
        $template = self::billingAddressTemplateContent();

        $this->assertStringContainsString(
            'var kiriofPendingShippingMethod',
            $template,
            'Block checkout must keep the buyer-clicked shipping method while the Woo Blocks cart store is still updating'
        );

        $this->assertStringContainsString(
            "jQuery(document).on('change click', 'input[type=\"radio\"]'",
            $template,
            'Woo Blocks shipping radios must be captured from the DOM immediately, before getShippingRates can report a stale first row'
        );

        $feeFunctionStart = strpos($template, 'function kiriofCodInsurance()');
        $this->assertNotFalse($feeFunctionStart, 'Fee refresh function must exist');
        $feeFunctionBody = substr($template, $feeFunctionStart, 1700);

        $pendingPosition = strpos($feeFunctionBody, 'let shipping_metode_id = kiriofGetPendingShippingMethod()');
        $storePosition = strpos($feeFunctionBody, 'kiriofGetSelectedBlockShippingMethod()');

        $this->assertNotFalse($pendingPosition, 'Fee refresh must prefer the immediately clicked block shipping method');
        $this->assertNotFalse($storePosition, 'Fee refresh may still fall back to the Woo Blocks cart store helper');
        $this->assertLessThan($storePosition, $pendingPosition, 'The recent user selection must win over stale Store API selected-rate state');

        $helperStart = strpos($template, 'function kiriofGetSelectedBlockShippingMethod()');
        $this->assertNotFalse($helperStart, 'Block checkout selected-rate helper must exist');
        $helperBody = substr($template, $helperStart, 1000);
        $this->assertStringContainsString(
            "wp.data.select('wc/store/cart')",
            $helperBody,
            'The helper must read the Woo Blocks cart store'
        );
        $this->assertStringContainsString(
            'getShippingRates',
            $helperBody,
            'The helper must inspect Store API shipping rates'
        );
    }

    #[Test]
    public function block_checkout_shipping_radio_click_syncs_wc_blocks_selected_rate_for_order_summary(): void
    {
        $template = self::billingAddressTemplateContent();

        $handlerStart = strpos($template, "jQuery(document).on('change click', 'input[type=\"radio\"]'");
        $this->assertNotFalse($handlerStart, 'Block checkout shipping radio listener must exist');
        $handlerBody = substr($template, $handlerStart, 650);

        $rememberPosition = strpos($handlerBody, 'kiriofRememberSelectedShippingMethod(selectedMethod);');
        $selectPosition = strpos($handlerBody, 'kiriofSelectBlockShippingRate(selectedMethod);');
        $feePosition = strpos($handlerBody, 'kiriofCodInsurance();');

        $this->assertNotFalse($rememberPosition, 'The clicked shipping rate must be cached immediately');
        $this->assertNotFalse($selectPosition, 'The clicked shipping rate must be pushed into Woo Blocks cart state so the order summary updates');
        $this->assertNotFalse($feePosition, 'Shipping changes must still refresh KiriminAja fee data');
        $this->assertLessThan($selectPosition, $rememberPosition, 'The rate should be remembered before selecting it in Woo Blocks');
        $this->assertLessThan($feePosition, $selectPosition, 'Woo Blocks selected-rate state must be updated before fee recalculation reads the cart');

        $helperStart = strpos($template, 'function kiriofSelectBlockShippingRate');
        $this->assertNotFalse($helperStart, 'Block checkout must define a helper to sync selected rates into Woo Blocks');
        $helperBody = substr($template, $helperStart, 1500);

        $this->assertStringContainsString(
            "wp.data.dispatch('wc/store/cart')",
            $helperBody,
            'Selected rate sync must use the same Woo cart data store consumed by the checkout order summary'
        );
        $this->assertStringContainsString(
            'selectShippingRate',
            $helperBody,
            'Woo Blocks exposes selectShippingRate for changing the selected shipping rate in Store API state'
        );
        $this->assertStringContainsString(
            'kiriofFindBlockShippingPackageId',
            $helperBody,
            'The selected-rate sync must pass the package id when Woo Blocks requires one'
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
    public function virtual_cart_clears_stale_logistics_session_before_fee_or_store_api_sync(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            'private function kiriof_clear_logistics_session',
            $content,
            'Virtual-only carts need a central cleanup path so stale courier/session values such as BIGPACK cannot leak into checkout'
        );

        $this->assertStringContainsString(
            "'kiriof_chosen_shipping_methods'",
            $content,
            'Cleanup must clear the plugin-owned chosen shipping mirror'
        );

        $this->assertStringContainsString(
            "'chosen_shipping_methods'",
            $content,
            'Cleanup must also clear WooCommerce chosen shipping methods for virtual-only carts'
        );

        $start = strpos($content, 'function kiriof_shipping_method_update()');
        $this->assertNotFalse($start, 'Shipping method update hook must exist');
        $methodBody = substr($content, $start, 500);
        $this->assertStringContainsString(
            '! $this->kiriof_cart_needs_shipping()',
            $methodBody,
            'Cart fee/session update must clear logistics state before reading stale posted or session shipping methods when cart is virtual-only'
        );

        $storeApiStart = strpos($content, 'public function kiriof_store_api_update_checkout');
        $this->assertNotFalse($storeApiStart, 'Store API update callback must exist');
        $storeApiBody = substr($content, $storeApiStart, 500);
        $this->assertStringContainsString(
            '! $this->kiriof_cart_needs_shipping()',
            $storeApiBody,
            'Block checkout Store API extension updates must ignore stale shipping method payloads for virtual-only carts'
        );

        $gatewayStart = strpos($content, 'public function kiriof_filter_cod_availability');
        $this->assertNotFalse($gatewayStart, 'COD availability filter must exist');
        $gatewayBody = substr($content, $gatewayStart, 900);
        $virtualCartCheck = strpos($gatewayBody, '! $this->kiriof_cart_needs_shipping()');
        $checkoutPageCheck = strpos($gatewayBody, 'if (!is_checkout())');
        $this->assertNotFalse($virtualCartCheck, 'COD availability must check virtual-only carts');
        $this->assertNotFalse($checkoutPageCheck, 'COD availability should still limit KiriminAja shipping-method rules to checkout pages');
        $this->assertLessThan(
            $checkoutPageCheck,
            $virtualCartCheck,
            'Virtual-only carts must remove COD before is_checkout() because block checkout payment options can be fetched through Store API requests'
        );
        $this->assertStringContainsString(
            '! $this->kiriof_cart_needs_shipping()',
            $gatewayBody,
            'COD gateway must be removed for virtual-only carts even if stale KiriminAja shipping session data exists'
        );
        $this->assertStringContainsString(
            "unset( \$gateways['cod'] );",
            $gatewayBody,
            'Virtual-only carts must not offer Cash on Delivery'
        );
    }

    #[Test]
    public function virtual_cart_skips_district_field_script_registration_and_validation(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        foreach (array(
            'function add_custom_select_options_field_and_script' => 'Virtual-only carts must not print the District field/script template',
            'public function kiriof_billing_fields' => 'Virtual-only carts must not add classic checkout District or insurance fields',
            'public function kiriof_register_block_checkout_fields' => 'Virtual-only carts must not register the Blocks District additional field when the cart is available',
            'function kiriof_checkout_field_validation' => 'Virtual-only carts must not require District during classic checkout validation',
            'public function kiriof_validateOrder' => 'Virtual-only carts must not require District, shipping, or checkout calculation validation',
            'public function kiriof_ajax_session_save' => 'Virtual-only carts must not persist stale District session data through AJAX fallback',
        ) as $needle => $message) {
            $start = strpos($content, $needle);
            $this->assertNotFalse($start, $message);
            $body = substr($content, $start, 700);
            $this->assertStringContainsString(
                '! $this->kiriof_cart_needs_shipping()',
                $body,
                $message
            );
            $this->assertStringContainsString(
                'kiriof_clear_logistics_session',
                $body,
                $message
            );
        }

        $this->assertStringContainsString(
            'private function kiriof_render_virtual_cart_district_cleanup',
            $content,
            'Virtual-only block checkouts need a small cleanup script because Woo Blocks can render the registered District field before cart state is available'
        );
        $this->assertStringContainsString(
            'kiriof-virtual-cart-checkout',
            $content,
            'Virtual-only cleanup must add a page marker class for hiding District UI created by Woo Blocks'
        );
        $this->assertStringContainsString(
            '[name*="kiriof_destination_area"]',
            $content,
            'Virtual-only cleanup must target raw registered District fields by name'
        );
        $this->assertStringContainsString(
            'MutationObserver',
            $content,
            'Virtual-only cleanup must survive Woo Blocks React rerenders'
        );
    }

    #[Test]
    public function virtual_products_skip_kiriminaja_weight_and_volumetric_requirements(): void
    {
        $shippingMethod = file_get_contents(PLUGIN_DIR . '/wc/KiriminajaShippingMethod.php');
        $productController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ProductController.php');

        $validationStart = strpos($shippingMethod, 'function kiriof_add_date_validation');
        $this->assertNotFalse($validationStart, 'Add-to-cart validation hook must exist');
        $validationBody = substr($shippingMethod, $validationStart, 700);
        $this->assertStringContainsString(
            'needs_shipping',
            $validationBody,
            'KiriminAja add-to-cart weight/dimension validation must skip virtual/downloadable products that do not need shipping'
        );
        $this->assertStringContainsString(
            'return $passed;',
            $validationBody,
            'Virtual products should pass through without requiring hidden shipping-tab fields'
        );

        $volumetricStart = strpos($productController, 'private function kiriof_product_has_volumetric_configuration');
        $this->assertNotFalse($volumetricStart, 'Product volumetric readiness helper must exist');
        $volumetricBody = substr($productController, $volumetricStart, 1400);
        $this->assertStringContainsString(
            'needs_shipping',
            $volumetricBody,
            'Virtual products should be treated as volumetric-ready because WooCommerce hides shipping fields for them'
        );
    }

    #[Test]
    public function fee_cache_matcher_invalidates_non_cod_insurance_when_checkout_context_changes(): void
    {
        require_once PLUGIN_DIR . '/inc/Controllers/CheckoutController.php';

        $controller = new \KiriminAjaOfficial\Controllers\CheckoutController();
        $method = new ReflectionMethod($controller, 'kiriof_fee_cache_matches');
        $method->setAccessible(true);

        $cachedContext = array(
            'shipping_method' => 'kiriminaja-official_idx_06',
            'destination_id'  => 44064,
            'payment_method'  => 'bacs',
            'insurance'       => 1,
        );
        $changedContext = array(
            'shipping_method' => 'kiriminaja-official_jne_REG23',
            'destination_id'  => 44064,
            'payment_method'  => 'bacs',
            'insurance'       => 1,
        );

        $this->assertTrue($method->invoke($controller, $cachedContext, $cachedContext));
        $this->assertFalse(
            $method->invoke($controller, $cachedContext, $changedContext),
            'A non-COD checkout that changes courier must not reuse stale cached insurance amounts'
        );
    }

    #[Test]
    public function shipping_chosen_method_filter_preserves_posted_ajax_selection_and_prefixed_session_mirror(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_shipping_chosen_method');
        $this->assertNotFalse($start, 'Chosen shipping method filter must exist');
        $methodBody = substr($content, $start, 3400);

        $this->assertStringContainsString(
            '$posted_method = sanitize_text_field',
            $methodBody,
            'WooCommerce update_order_review AJAX should be able to keep the newly selected posted courier during fragment rendering'
        );

        $this->assertStringContainsString(
            'WC()->session->set( \'kiriof_chosen_shipping_methods\', array( $posted_method ) );',
            $methodBody,
            'The plugin-owned selected courier mirror must use the kiriof_ session prefix'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'kiriof_chosen_shipping_methods'",
            $methodBody,
            'If WooCommerce enters the chosen-method filter without POST data, it should fall back to the prefixed plugin mirror before using the old/default method'
        );

        $this->assertStringContainsString(
            "array_key_exists( (string) \$method, \$available_methods )",
            $methodBody,
            'A valid Woo-selected shipping method must be authoritative; otherwise a stale plugin mirror can force the Order Summary back to the previous courier'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_chosen_shipping_methods', array( (string) \$method ) );",
            $methodBody,
            'When Woo has a valid current method, the plugin mirror should be updated to that method instead of overriding it'
        );

        $this->assertStringNotContainsString(
            'checkout_kiriminaja_nonce_field',
            $methodBody,
            'The chosen-method filter runs during WooCommerce AJAX fragment rendering where the plugin checkout nonce may not be available'
        );
    }

    #[Test]
    public function plugin_ajax_shipping_metode_id_wins_over_wc_default_method_during_recalculation(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_shipping_chosen_method');
        $this->assertNotFalse($start, 'Chosen shipping method filter must exist');
        $methodBody = substr($content, $start, 3400);

        $pluginAjaxPosition = strpos($methodBody, "isset( \$_POST['shipping_metode_id'] )");
        $methodCheckPosition = strpos($methodBody, "'' !== (string) \$method && array_key_exists( (string) \$method, \$available_methods )");

        $this->assertNotFalse(
            $pluginAjaxPosition,
            'Plugin AJAX posts shipping_metode_id, so the chosen-method filter must read it during WC cart recalculation'
        );
        $this->assertNotFalse($methodCheckPosition, 'WC-resolved method fallback must still exist');
        $this->assertLessThan(
            $methodCheckPosition,
            $pluginAjaxPosition,
            'shipping_metode_id from kiriof_get_data_after_update_checkout must win before Woo can reselect the default rate'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_shipping_methods', array( \$posted_kiriof_method ) );",
            $methodBody,
            'Plugin AJAX selection must sync the core Woo chosen_shipping_methods session key before Store API cart GET runs'
        );
    }

    #[Test]
    public function block_checkout_shipping_chosen_method_filter_trusts_wc_resolved_method(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_shipping_chosen_method');
        $this->assertNotFalse($start, 'Chosen shipping method filter must exist');
        $methodBody = substr($content, $start, 3600);

        // When WooCommerce Blocks calls selectShippingRate (no form POST), WC passes the
        // newly chosen rate as $method. The filter must trust that value rather than
        // overriding it with the stale session cache — otherwise the Order Summary always
        // shows the first/cheapest courier.
        $methodCheckPosition = strpos($methodBody, "'' !== (string) \$method && array_key_exists( (string) \$method, \$available_methods )");
        $this->assertNotFalse(
            $methodCheckPosition,
            'The filter must trust the $method parameter resolved by WooCommerce when it is a valid available method (blocks selectShippingRate path)'
        );

        // The session must be updated so subsequent cart/fee hooks see the correct value.
        $sessionUpdateAfterMethodCheck = strpos($methodBody, "WC()->session->set( 'chosen_shipping_methods', array( (string) \$method ) );");
        $this->assertNotFalse(
            $sessionUpdateAfterMethodCheck,
            'Accepting the WC-resolved $method must sync chosen_shipping_methods session so cart totals reflect the real selection'
        );
        $this->assertGreaterThan(
            strpos($methodBody, "WC()->session->get( 'kiriof_chosen_shipping_methods'"),
            $methodCheckPosition,
            'The WC-resolved method remains a fallback after explicit plugin/session selections have been checked'
        );
    }

    #[Test]
    public function block_checkout_select_shipping_rate_request_body_wins_over_logged_in_stale_session(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_shipping_chosen_method');
        $this->assertNotFalse($start, 'Chosen shipping method filter must exist');
        $methodBody = substr($content, $start, 3600);

        $storeApiPosition = strpos($methodBody, '$store_api_method = $this->kiriof_get_store_api_selected_shipping_rate();');
        $methodCheckPosition = strpos($methodBody, "'' !== (string) \$method && array_key_exists( (string) \$method, \$available_methods )");

        $this->assertNotFalse(
            $storeApiPosition,
            'Store API select-shipping-rate payload must be read before trusting WooCommerce session-derived $method'
        );
        $this->assertNotFalse($methodCheckPosition, 'WC-resolved method fallback must still exist');
        $this->assertLessThan(
            $methodCheckPosition,
            $storeApiPosition,
            'The request body rate_id must win when logged-in customer session still contains the previous courier'
        );

        $this->assertStringContainsString(
            "'php://input'",
            $content,
            'Woo Blocks sends select-shipping-rate as JSON, so the filter must inspect the REST body'
        );
        $this->assertStringContainsString(
            '/cart/select-shipping-rate',
            $content,
            'The request-body override must be limited to the Store API shipping selection endpoint'
        );
    }

    #[Test]
    public function block_checkout_store_api_recalculation_prefers_plugin_session_mirror_before_wc_default(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_shipping_chosen_method');
        $this->assertNotFalse($start, 'Chosen shipping method filter must exist');
        $methodBody = substr($content, $start, 3600);

        $mirrorPosition = strpos($methodBody, "WC()->session->get( 'kiriof_chosen_shipping_methods'");
        $methodCheckPosition = strpos($methodBody, "'' !== (string) \$method && array_key_exists( (string) \$method, \$available_methods )");

        $this->assertNotFalse($mirrorPosition, 'Plugin selected-rate mirror must be read during chosen-method resolution');
        $this->assertNotFalse($methodCheckPosition, 'Woo default method fallback must still exist');
        $this->assertLessThan(
            $methodCheckPosition,
            $mirrorPosition,
            'Store API extension/cart recalculations must not overwrite the plugin-selected courier with Woo default ID Express'
        );
        $this->assertStringContainsString(
            "WC()->session->set( 'chosen_shipping_methods', array( \$kiriof_chosen_methods[0] ) );",
            $methodBody,
            'The plugin mirror must keep Woo chosen_shipping_methods synchronized for the next cart GET'
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
    public function block_checkout_fee_sync_prefers_store_selected_shipping_rate_over_stale_dom_radio(): void
    {
        $content = self::billingAddressTemplateContent();
        $start = strpos($content, 'function kiriofCodInsurance');
        $this->assertNotFalse($start, 'Block checkout fee refresh helper must exist');
        $methodBody = substr($content, $start, 2600);

        $this->assertStringContainsString(
            'function kiriofGetSelectedBlockShippingMethod',
            $content,
            'Block checkout should have a single helper for reading the selected Woo cart-store shipping rate'
        );

        $this->assertStringContainsString(
            'kiriofIsBlockCheckoutContext()',
            $methodBody,
            'Block checkout fee sync must branch away from classic DOM-first shipping method lookup'
        );

        $this->assertStringContainsString(
            'kiriofGetSelectedBlockShippingMethod()',
            $methodBody,
            'Block checkout fee sync should prefer the Woo cart store selected rate so stale checked DOM radios do not rewrite the previous courier into session'
        );

        $storePosition = strpos($methodBody, 'kiriofGetSelectedBlockShippingMethod()');
        $domPosition = strpos($methodBody, "jQuery('#shipping_method .shipping_method:checked').val()");
        $this->assertNotFalse($storePosition);
        $this->assertNotFalse($domPosition);
        $this->assertLessThan(
            $domPosition,
            $storePosition,
            'The Woo Blocks selected shipping rate must be read before DOM radios, because React can leave stale checked inputs around after a courier switch'
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

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_force_insurance'",
            $content,
            'Store API callback must persist courier-forced insurance using the plugin session prefix'
        );

        $this->assertStringContainsString(
            "WC()->session->set( 'kiriof_destination_area_name'",
            $content,
            'Store API callback must persist the selected district label used by transaction creation'
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
            "woocommerce_store_api_checkout_update_order_from_request",
            $content,
            'Block checkout must persist KiriminAja context onto the order before the Store API checkout session/cart is cleared'
        );

        $this->assertStringContainsString(
            'afterStoreApiCheckoutUpdateOrderFromRequest',
            $content,
            'Store API checkout update hook should save checkout context before transaction creation'
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
            'kiriof_get_store_api_destination_field',
            $content,
            'Block checkout order creation must read the registered additional District field when session context is unavailable'
        );

        $this->assertStringContainsString(
            'kiriof_resolve_destination_area',
            $content,
            'Block checkout order creation must resolve text district values to KiriminAja destination IDs before inserting transactions'
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
    public function native_block_checkout_cod_fee_fallback_reads_all_store_api_payment_session_keys(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'private function kiriof_add_checkout_fees()');
        $this->assertNotFalse($start, 'Native block checkout fee method must exist');
        $methodBody = substr($content, $start, 3800);

        $helperStart = strpos($content, 'private function kiriof_get_checkout_payment_method');
        $this->assertNotFalse($helperStart, 'Shared payment method fallback helper must exist');
        $helperBody = substr($content, $helperStart, 1400);

        $this->assertStringContainsString(
            'kiriof_get_checkout_payment_method',
            $methodBody,
            'Native fee fallback must use the shared Store API payment fallback helper'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'kiriof_payment_method'",
            $helperBody,
            'Store API extensionCartUpdate stores COD in kiriof_payment_method; native fee fallback must read it, not only chosen_payment_method'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'payment_method'",
            $helperBody,
            'Order creation stores payment_method separately; native fee fallback must read that key too'
        );

        $this->assertStringContainsString(
            '\'is_cod\'              => ( \'cod\' === $chosen_payment )',
            $methodBody,
            'Direct calculation fallback must request COD fee when any server-side checkout payment key says cod'
        );
    }

    #[Test]
    public function native_checkout_fees_must_clear_stale_cod_cache_when_payment_is_not_cod(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'private function kiriof_add_checkout_fees()');
        $this->assertNotFalse($start, 'Native checkout fee method must exist');
        $methodBody = substr($content, $start, 4200);

        $this->assertStringContainsString(
            'if ( \'cod\' !== $chosen_payment )',
            $methodBody,
            'Native fee rendering must zero cached COD amounts as soon as buyer switches to Direct bank transfer or another non-COD gateway'
        );

        $this->assertStringContainsString(
            '$cod_amt = 0;',
            $methodBody,
            'Stale kiriof_cached_cod_amt must not be added as COD Fee for non-COD payments'
        );
    }

    #[Test]
    public function cached_fee_amounts_are_keyed_by_checkout_context_so_courier_rule_changes_recalculate(): void
    {
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $ajax = file_get_contents(PLUGIN_DIR . '/inc/Controllers/GeneralAjaxController.php');

        $this->assertStringContainsString(
            'kiriof_cached_fee_context',
            $controller,
            'Native fee calculation must compare current shipping/payment/destination/insurance context before trusting cached fee amounts'
        );

        $this->assertStringContainsString(
            'kiriof_cached_fee_context',
            $ajax,
            'AJAX fee calculation must store cache context so changing courier can refresh force_insurance and insurance amount rules'
        );
    }

    #[Test]
    public function block_checkout_order_creation_falls_back_to_woo_order_payment_method_for_cod(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'function afterCheckoutAfterCreated');
        $this->assertNotFalse($start, 'Order processed hook must exist');
        $methodBody = substr($content, $start, 2600);

        $helperStart = strpos($content, 'private function kiriof_get_checkout_payment_method');
        $this->assertNotFalse($helperStart, 'Shared payment method fallback helper must exist');
        $helperBody = substr($content, $helperStart, 1400);

        $this->assertStringContainsString(
            'kiriof_get_checkout_payment_method( $order )',
            $methodBody,
            'Order creation must use the shared Store API/Woo order payment fallback helper'
        );

        $this->assertStringContainsString(
            '$order->get_payment_method()',
            $helperBody,
            'Store API checkout may set Woo order payment_method=cod even when plugin session/meta payment context is empty'
        );

        $orderPaymentPosition = strpos($helperBody, '$order->get_payment_method()');
        $pluginMetaPosition = strpos($helperBody, "_kiriof_checkout_payment_method");
        $this->assertNotFalse($orderPaymentPosition, 'Payment helper must read the final Woo order payment method');
        $this->assertNotFalse($pluginMetaPosition, 'Payment helper may keep plugin meta as a fallback only');
        $this->assertLessThan(
            $pluginMetaPosition,
            $orderPaymentPosition,
            'Final Woo order payment method must be authoritative over transient plugin checkout meta so stale block-session COD cannot mark a Direct bank transfer order as COD'
        );

        $this->assertStringContainsString(
            "WC()->session->get( 'kiriof_payment_method'",
            $helperBody,
            'Order creation should also read the Store API callback payment fallback key'
        );
    }

    #[Test]
    public function transaction_process_page_defaults_to_all_and_labels_payment_from_woo_order(): void
    {
        $index = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/index.php');
        $view = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/view/index.php');

        $this->assertStringContainsString(
            '$kiriof_status_filter = \'all\';',
            $index,
            'Opening the transaction-process page without a status filter should show all newly-created transactions, including BACS/on-hold orders'
        );

        $this->assertStringContainsString(
            '$status = \'all\';',
            $index,
            'The page query should default to the all filter instead of hiding non-processing checkout-block transactions'
        );

        $normalizePosition = strpos($index, '$status = \'all\';');
        $isAllPosition = strpos($index, '$isAllFilter = (\'all\' === $status);');
        $this->assertNotFalse($normalizePosition, 'The page query must normalize empty/invalid status values to all');
        $this->assertNotFalse($isAllPosition, 'The page query must calculate the all-filter flag');
        $this->assertLessThan(
            $isAllPosition,
            $normalizePosition,
            'The all-filter flag must be calculated after status normalization so the default transaction page does not query orders.status = all'
        );

        $this->assertStringContainsString(
            'wc_get_order($kiriof_row->wc_order_id)',
            $view,
            'Transaction list payment label should read the final Woo order payment method, not stale shipping_info meta'
        );

        $wooPaymentPosition = strpos($view, '$kiriof_wcOrder->get_payment_method()');
        $shippingInfoPosition = strpos($view, '$kiriof_shippingData->_payment_method');
        $this->assertNotFalse($wooPaymentPosition, 'Transaction list must read Woo order payment method');
        $this->assertNotFalse($shippingInfoPosition, 'Transaction list may retain shipping_info payment as fallback');
        $this->assertLessThan(
            $shippingInfoPosition,
            $wooPaymentPosition,
            'Woo order payment method must be preferred over stored shipping_info so BACS orders do not display as COD from stale checkout metadata'
        );
    }

    #[Test]
    public function transaction_process_page_only_lists_orders_with_shippable_products(): void
    {
        $index = file_get_contents(PLUGIN_DIR . '/templates/transaction-process/index.php');
        $repository = file_get_contents(PLUGIN_DIR . '/inc/Repositories/TransactionRepository.php');

        $this->assertStringContainsString(
            'public function getShippableOrderExistsSql',
            $repository,
            'Transaction queries need one shared SQL clause for detecting orders with at least one shippable line item'
        );
        $this->assertStringContainsString(
            'woocommerce_order_items',
            $repository,
            'Shippable-order detection should inspect WooCommerce order line items, not only transaction rows'
        );
        $this->assertStringContainsString(
            'woocommerce_order_itemmeta',
            $repository,
            'Shippable-order detection must join order itemmeta to resolve product/variation IDs'
        );
        $this->assertStringContainsString(
            "meta_key = '_virtual'",
            $repository,
            'Shippable-order detection must exclude orders whose products/variations are virtual only'
        );
        $this->assertStringContainsString(
            "COALESCE(NULLIF(pm_var.meta_value, ''), pm_prod.meta_value, 'no') <> 'yes'",
            $repository,
            'Variation virtual metadata should override product metadata, with non-virtual as the default'
        );
        $this->assertStringContainsString(
            '$shippable_order_clause = $transactionRepository->getShippableOrderExistsSql',
            $index,
            'The transaction-process page must apply the shippable-order filter to its paginated SQL'
        );
        $this->assertGreaterThanOrEqual(
            10,
            substr_count($index, '{$shippable_order_clause}'),
            'Every transaction-process filter branch must apply the shippable-order clause to both totals and rows'
        );
        $this->assertStringContainsString(
            '$shippable_order_clause = $this->getShippableOrderExistsSql',
            $repository,
            'Status counts, courier filters, and month filters must use the same shippable-order filter as the page query'
        );
    }

    #[Test]
    public function block_checkout_uses_native_order_summary_fee_rows(): void
    {
        $enqueue = file_get_contents(PLUGIN_DIR . '/inc/Base/Enqueue.php');
        $script = file_get_contents(PLUGIN_DIR . '/assets/wp/js/kiriof-block-checkout.js');
        $style = file_get_contents(PLUGIN_DIR . '/assets/wp/css/kj-wp-style.css');
        $controller = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $couponController = file_get_contents(PLUGIN_DIR . '/inc/Controllers/ShippingDiscountCouponController.php');

        $this->assertStringContainsString(
            'kiriof-block-checkout',
            $enqueue,
            'Block checkout needs a dedicated frontend script for Woo Blocks Slot/Fills'
        );

        $this->assertStringContainsString(
            'wc-blocks-checkout',
            $enqueue,
            'Slot/fill script must depend on Woo Blocks checkout APIs'
        );

        $this->assertStringContainsString(
            'isBlockCartOrCheckoutPage',
            $enqueue,
            'Shipping discount totals script must load on both Cart Block and Checkout Block pages'
        );

        $this->assertStringContainsString(
            "has_block( 'woocommerce/cart'",
            $enqueue,
            'Cart Block pages need the shipping discount totals script too'
        );

        $this->assertStringContainsString(
            'is_cart_block_default',
            $enqueue,
            'Default Woo Cart Block pages need the shipping discount totals script too'
        );

        $this->assertStringContainsString(
            'registerPlugin',
            $script,
            'Block checkout order summary integration must register a Woo Blocks plugin'
        );

        $this->assertStringNotContainsString(
            'wc-block-components-totals-fees',
            $script,
            'The block script must not hide Woo native fee rows; Woo renders cart.fees below shipping and above Total'
        );

        $this->assertStringNotContainsString(
            'kiriof-block-fee-breakdown__row',
            $script . $style,
            'Insurance and COD Fee should not be re-rendered through a custom row below Total'
        );

        $this->assertStringNotContainsString(
            'fee.name === "COD Fee"',
            $script,
            'COD Fee should use WooCommerce Blocks native order-summary fee rendering instead of plugin SlotFill rows'
        );

        // kiriof_get_current_shipping_discount is fetched to show strikethrough price in Order Summary totals row
        $this->assertStringContainsString(
            'kiriof_get_current_shipping_discount',
            $script,
            'Block checkout should fetch discount data to show strikethrough original price in the Order Summary shipping totals row'
        );

        // Shipping rate decoration removed — block themes render ALL WC_Shipping_Rate meta_data
        // as visible sub-lines in Order Summary, causing janky display on ShopVerse etc.
        $this->assertStringNotContainsString(
            'kiriof_get_shipping_rate_meta',
            $script,
            'Shipping rate meta AJAX was removed to prevent block checkout from injecting janky UI'
        );

        $this->assertStringNotContainsString(
            'scheduleShippingDecorationRefresh',
            $script,
            'Shipping decoration refresh was removed to prevent janky block checkout injection'
        );

        $this->assertStringContainsString(
            'invalidateBlockShippingRates',
            $script,
            'Block checkout should explicitly invalidate shipping rates when coupon chips change so removing a shipping coupon clears discounted courier state without a manual refresh'
        );

        $this->assertStringNotContainsString(
            'syncShippingSummaryLine',
            $script,
            'Shipping summary line decoration was removed to prevent janky block checkout injection'
        );

        $this->assertStringNotContainsString(
            'decorateShippingOptions',
            $script,
            'Shipping options decoration was removed to prevent janky block checkout injection'
        );

        $this->assertStringContainsString(
            'getCurrentShippingDiscountAjax',
            $couponController,
            'Shipping discount amount should be exposed through a frontend AJAX endpoint for block checkout refreshes'
        );

        $this->assertStringContainsString(
            'getCurrentShippingDiscountSummary',
            $couponController,
            'Block checkout AJAX should use the shipping discount summary so buyers can see the shipping method name plus original and discounted shipping prices'
        );

        $this->assertStringContainsString(
            'kiriof-block-shipping-discount__row',
            $script,
            'Block checkout order summary should render an explicit shipping discount row when a shipping coupon changes the selected rate'
        );

        $this->assertStringContainsString(
            'kiriof-block-shipping-discount__fallback-row',
            $script,
            'Cart Block should receive a DOM fallback shipping discount row because checkout Slot/Fills are not available there'
        );

        $this->assertStringContainsString(
            'bootDomShippingDiscountSummary',
            $script,
            'Cart Block should fetch the shipping discount summary and sync the totals DOM without checkout Slot/Fills'
        );

        $this->assertStringContainsString(
            'syncShippingDiscountTotalsRow',
            $script,
            'Cart block should still render a DOM fallback shipping discount row outside of checkout Slot/Fills'
        );

        $this->assertStringContainsString(
            '.kiriof-block-shipping-discount__row',
            $style,
            'Injected shipping discount row should style the actual block checkout/cart class'
        );

        $this->assertStringContainsString(
            'padding: 24px 32px !important;',
            $style,
            'Injected shipping discount row needs horizontal padding so it aligns with Woo block totals content'
        );

        $this->assertStringNotContainsString(
            'getShippingRateMetaAjax',
            $couponController,
            'Rate meta AJAX endpoint was removed along with shipping injection feature'
        );

        $this->assertStringContainsString(
            'kiriof_render_block_checkout_shipping_discount_row',
            $controller,
            'Checkout controller should add a server-rendered fallback row so block themes that SSR totals wrappers still show the shipping discount amount'
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

        $this->assertStringContainsString(
            '_kiriof_fee_type',
            $service,
            'CreateTransactionService must tag fee items with _kiriof_fee_type meta to avoid doubling COD Fee and Insurance on localized stores'
        );
    }

    #[Test]
    public function order_received_fee_fallback_never_renders_zero_or_null_transaction_fees(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');
        $start = strpos($content, 'public function kiriof_order_details');
        $this->assertNotFalse($start, 'Order details renderer must exist');
        $methodBody = substr($content, $start, 3200);

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

        $this->assertStringContainsString(
            'Shipping Discount',
            $methodBody,
            'Order details should render a dedicated shipping discount row when KiriminAja raw shipping exceeds the discounted Woo shipping total'
        );

        $this->assertStringContainsString(
            'Actual Shipping',
            $methodBody,
            'Order details should render the original shipping amount before the shipping discount so buyers can see the full breakdown'
        );

        $this->assertStringNotContainsString(
            'Ekspedisi',
            $methodBody,
            'Courier information should not be injected into the financial totals rows because Woo already renders a native Shipping total'
        );

        $this->assertStringNotContainsString(
            'Tracking',
            $methodBody,
            'Tracking should be rendered as shipment information outside the order totals table'
        );

        $this->assertStringNotContainsString(
            '? $transactionKiriminaja->cod_fee : 0',
            $methodBody,
            'Missing transactions should not be displayed as a zero COD fee fallback row'
        );
    }

    #[Test]
    public function order_received_renders_courier_and_tracking_outside_financial_totals(): void
    {
        $content = file_get_contents(PLUGIN_DIR . '/inc/Controllers/CheckoutController.php');

        $this->assertStringContainsString(
            "add_action( 'woocommerce_order_details_after_order_table', array(\$this,'kiriof_order_shipment_details') );",
            $content,
            'Courier/tracking information should render after the order details table, not inside the totals rows'
        );

        $start = strpos($content, 'public function kiriof_order_shipment_details');
        $this->assertNotFalse($start, 'Shipment details renderer must exist');
        $methodBody = substr($content, $start, 1800);

        $this->assertStringContainsString(
            '! $this->kiriof_order_needs_shipping( $order )',
            $methodBody,
            'Virtual-only orders must not render stale shipment information from a previous physical checkout'
        );

        $this->assertStringContainsString(
            'Shipping Method',
            $methodBody,
            'Shipment details should show the selected shipping method as informational content'
        );

        $this->assertStringContainsString(
            'Track Shipment',
            $methodBody,
            'Shipment details should use a clear buyer-facing tracking link label'
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

    #[Test]
    public function admin_shipping_breakdown_shows_net_shipping_paid_by_buyer_after_shipping_coupon(): void
    {
        $metabox = file_get_contents(PLUGIN_DIR . '/templates/order/metabox-shipping.php');
        $preview = file_get_contents(PLUGIN_DIR . '/inc/Controllers/TransactionProcessController.php');

        $this->assertStringContainsString(
            'Discounted Shipping',
            $metabox,
            'Order metabox should show the discounted base shipping amount after shipping discount, excluding insurance and COD fee'
        );

        $this->assertStringContainsString(
            'Discounted Shipping',
            $preview,
            'Transaction process preview should mirror the order metabox and show the discounted base shipping amount, excluding insurance and COD fee'
        );
    }
}
